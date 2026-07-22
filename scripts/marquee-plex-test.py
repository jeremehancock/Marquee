#!/usr/bin/env python3
"""
Marquee <-> Plex integration tester.

Drives Marquee's "Send to Plex" for one poster and verifies the result directly
in Plex:

  * Lock test    - after Marquee sends a poster, is the item's `thumb` locked?
  * Kometa test  - with PLEX_REMOVE_OVERLAY_LABEL enabled, does Marquee remove
                   the Plex "Overlay" label that Kometa uses?

Pure standard library - no `pip install` needed. Works with Python 3.8+.

------------------------------------------------------------------------------
HOW TO USE
------------------------------------------------------------------------------
1. Edit the CONFIG block below (or set the same names as environment variables,
   which override the file).
2. Pick ONE test item and fill in:
     * CATEGORY + FILENAME - the poster in Marquee. Easiest source: hover the
       poster and look at its image URL, `/posters/<CATEGORY>/<FILENAME>`.
       CATEGORY is one of: movies | tv-shows | tv-seasons | collections
     * RATING_KEY - the Plex item. In Plex Web: item -> ... -> Get Info ->
       View XML; the number in the URL (.../library/metadata/<RATING_KEY>?...).
3. Run:  python3 marquee-plex-test.py

Notes / side effects:
  * "Send to Plex" re-applies the poster Marquee already stores and LOCKS it.
    That is the app's normal behaviour; the item stays locked afterwards.
  * The Kometa test temporarily ADDS an "Overlay" label if the item doesn't
    already have one, and cleans it up at the end if Marquee didn't remove it.
  * EXPECT_LABEL_REMOVED must match your Marquee PLEX_REMOVE_OVERLAY_LABEL
    setting: "true" if the feature is enabled, "false" if it's off.
------------------------------------------------------------------------------
"""

import os
import ssl
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from http.cookiejar import CookieJar
import xml.etree.ElementTree as ET

# ----------------------------  EDIT THESE  ------------------------------------
CONFIG = {
    # Marquee
    "MARQUEE_URL":  "http://localhost:1818",
    "MARQUEE_USER": "admin",          # leave "" if AUTH_BYPASS=true in Marquee
    "MARQUEE_PASS": "change-me",

    # Plex
    "PLEX_URL":     "http://192.168.1.10:32400",
    "PLEX_TOKEN":   "xxxxxxxxxxxxxxxxxxxx",

    # The one test item
    "CATEGORY":     "movies",         # movies | tv-shows | tv-seasons | collections
    "FILENAME":     "Solaris (1972) [Movies].jpg",
    "RATING_KEY":   "12345",

    # Which tests to run
    "RUN_LOCK_TEST":   "true",
    "RUN_KOMETA_TEST": "true",

    # Set to match your PLEX_REMOVE_OVERLAY_LABEL (true = feature enabled)
    "EXPECT_LABEL_REMOVED": "true",

    # true to skip TLS verification (self-signed certs)
    "INSECURE": "false",
}
# ------------------------------------------------------------------------------

PLEX_TYPE_NUMBER = {"movie": 1, "show": 2, "season": 3, "collection": 18}
SETTLE_SECONDS = 1.0  # brief pause so Plex reflects the change before re-reading


def cfg(key):
    return os.environ.get(key, CONFIG.get(key, ""))


def is_true(v):
    return str(v).strip().lower() in ("1", "true", "yes", "on")


# -- output helpers ------------------------------------------------------------
_COLOR = sys.stdout.isatty()


def _c(code, text):
    return f"\033[{code}m{text}\033[0m" if _COLOR else text


def info(msg):
    print(f"  {_c('36', '-')} {msg}")


def ok(msg):
    print(f"  {_c('32', 'PASS')} {msg}")


def fail(msg):
    print(f"  {_c('31', 'FAIL')} {msg}")


def head(msg):
    print("\n" + _c("1", msg))


def die(msg):
    print(_c("31", f"\nError: {msg}"))
    sys.exit(2)


# -- HTTP plumbing -------------------------------------------------------------
def ssl_ctx():
    return ssl._create_unverified_context() if is_true(cfg("INSECURE")) else None


def open_url(url, method="GET", data=None, opener=None):
    req = urllib.request.Request(url, data=data, method=method)
    if data is not None:
        req.add_header("Content-Type", "application/x-www-form-urlencoded")
    try:
        if opener is not None:
            return opener.open(req, timeout=30)
        return urllib.request.urlopen(req, timeout=30, context=ssl_ctx())
    except urllib.error.HTTPError as e:
        return e  # callers inspect .status / .read()
    except Exception as e:  # noqa: BLE001
        die(f"request to {url} failed: {e}")


def status_of(resp):
    return getattr(resp, "status", getattr(resp, "code", 200))


# -- Plex ----------------------------------------------------------------------
def plex_url(path, **params):
    params["X-Plex-Token"] = cfg("PLEX_TOKEN")
    return cfg("PLEX_URL").rstrip("/") + path + "?" + urllib.parse.urlencode(params)


def plex_item():
    """Fetch the test item's metadata element, or exit with a clear error."""
    resp = open_url(plex_url(f"/library/metadata/{cfg('RATING_KEY')}"))
    code = status_of(resp)
    body = resp.read()
    if code == 401:
        die("Plex rejected the token (401). Check PLEX_TOKEN.")
    if code == 404:
        die(f"Plex has no item with ratingKey {cfg('RATING_KEY')} (404).")
    if code >= 400:
        die(f"Plex returned HTTP {code} for the item metadata.")
    child = next(iter(ET.fromstring(body)), None)
    if child is None:
        die("Plex metadata response contained no item.")
    return child


def thumb_locked(item):
    for field in item.findall("Field"):
        if field.get("name") == "thumb" and str(field.get("locked")) in ("1", "true"):
            return True
    return False


def has_label(item, tag):
    return any(lbl.get("tag") == tag for lbl in item.findall("Label"))


def plex_set_label(item, tag, add=True):
    section = item.get("librarySectionID")
    type_num = PLEX_TYPE_NUMBER.get(item.get("type"))
    if not section or not type_num:
        die("Could not determine the item's library section/type for label edits.")
    key = "label[0].tag.tag" if add else "label[].tag.tag-"
    url = plex_url(
        f"/library/sections/{section}/all",
        **{"type": type_num, "id": cfg("RATING_KEY"), key: tag},
    )
    resp = open_url(url, method="PUT")
    if status_of(resp) >= 400:
        die(f"Plex label edit returned HTTP {status_of(resp)}.")


# -- Marquee -------------------------------------------------------------------
def marquee_login():
    """Return a urllib opener with a live session (logs in unless bypassed)."""
    handlers = [urllib.request.HTTPCookieProcessor(CookieJar())]
    if ssl_ctx() is not None:
        handlers.append(urllib.request.HTTPSHandler(context=ssl_ctx()))
    opener = urllib.request.build_opener(*handlers)

    base = cfg("MARQUEE_URL").rstrip("/")
    if cfg("MARQUEE_USER"):
        data = urllib.parse.urlencode(
            {"username": cfg("MARQUEE_USER"), "password": cfg("MARQUEE_PASS")}
        ).encode()
        resp = open_url(base + "/login", method="POST", data=data, opener=opener)
        if "/login" in resp.geturl():
            die("Marquee login failed - check MARQUEE_USER / MARQUEE_PASS.")

    # Confirm we can reach a protected page (catches wrong creds / bad URL).
    resp = open_url(base + "/library/" + cfg("CATEGORY"), opener=opener)
    if "/login" in resp.geturl():
        die("Marquee is asking for login. Set MARQUEE_USER/PASS (or enable AUTH_BYPASS).")
    return opener


def marquee_send_to_plex(opener):
    base = cfg("MARQUEE_URL").rstrip("/")
    data = urllib.parse.urlencode({"filename": cfg("FILENAME")}).encode()
    open_url(f"{base}/library/{cfg('CATEGORY')}/send-to-plex",
             method="POST", data=data, opener=opener)
    time.sleep(SETTLE_SECONDS)


# -- tests ---------------------------------------------------------------------
def test_lock(opener):
    head("Test A - poster lock")
    info(f"thumb locked before: {thumb_locked(plex_item())}")
    info("Sending poster to Plex via Marquee...")
    marquee_send_to_plex(opener)
    after = thumb_locked(plex_item())
    info(f"thumb locked after:  {after}")
    if after:
        ok("Marquee locked the poster in Plex.")
        return True
    fail("Poster is NOT locked after Send to Plex.")
    info("Hints: is the poster linked to a Plex item (imported, not added)? "
         "Are PLEX_SERVER_URL/PLEX_TOKEN correct in Marquee? Check marquee.log.")
    return False


def test_kometa(opener):
    head("Test B - Kometa Overlay label")
    expect_removed = is_true(cfg("EXPECT_LABEL_REMOVED"))
    info(f"Expecting label removal: {expect_removed} (match PLEX_REMOVE_OVERLAY_LABEL)")

    item = plex_item()
    added_by_us = False
    if not has_label(item, "Overlay"):
        info("Item has no 'Overlay' label; adding one for the test...")
        plex_set_label(item, "Overlay", add=True)
        added_by_us = True
        if not has_label(plex_item(), "Overlay"):
            die("Could not add the 'Overlay' label via Plex (check token permissions).")
    info("Overlay label present before: True")

    info("Sending poster to Plex via Marquee...")
    marquee_send_to_plex(opener)

    present_after = has_label(plex_item(), "Overlay")
    info(f"Overlay label present after:  {present_after}")
    removed = not present_after

    passed = (removed == expect_removed)
    if passed:
        ok(f"Overlay label removed = {removed} (as expected).")
    else:
        fail(f"Overlay label removed = {removed}, expected {expect_removed}.")
        if expect_removed:
            info("Hints: is PLEX_REMOVE_OVERLAY_LABEL=true AND the container "
                 "recreated (docker compose up -d) after the change? Check marquee.log.")

    if added_by_us and present_after:
        info("Cleaning up the 'Overlay' label we added...")
        plex_set_label(plex_item(), "Overlay", add=False)

    return passed


def preflight(opener):
    head("Preflight")
    item = plex_item()
    info(f"Plex item: {item.get('title')!r} "
         f"(type={item.get('type')}, ratingKey={cfg('RATING_KEY')})")
    info(f"Marquee reachable and authenticated ({cfg('MARQUEE_URL')})")
    _ = opener


def warn_placeholders():
    checks = {
        "PLEX_TOKEN": "xxxx",
        "PLEX_URL": "http://192.168.1.10",
        "RATING_KEY": "12345",
    }
    for key, placeholder in checks.items():
        if cfg(key).startswith(placeholder):
            print(_c("33", f"Heads up: '{key}' still looks like a placeholder - "
                           "edit CONFIG or set env vars before running."))


def main():
    print(_c("1", "Marquee <-> Plex tester"))
    warn_placeholders()

    valid = ("movies", "tv-shows", "tv-seasons", "collections")
    if cfg("CATEGORY") not in valid:
        die("CATEGORY must be one of: " + ", ".join(valid))

    opener = marquee_login()
    preflight(opener)

    results = {}
    if is_true(cfg("RUN_LOCK_TEST")):
        results["Lock"] = test_lock(opener)
    if is_true(cfg("RUN_KOMETA_TEST")):
        results["Kometa label"] = test_kometa(opener)

    head("Summary")
    if not results:
        info("No tests selected (RUN_LOCK_TEST / RUN_KOMETA_TEST are both off).")
        return 0
    for name, passed in results.items():
        (ok if passed else fail)(name)
    all_passed = all(results.values())
    print("\n" + (_c("32", "All selected tests passed.") if all_passed
                  else _c("31", "Some tests failed - see above.")))
    return 0 if all_passed else 1


if __name__ == "__main__":
    sys.exit(main())
