# Connecting Marquee to Plex

Marquee needs a Plex token to import posters and send them back. There are two
ways to give it one, and they are equivalent in what they can do — both end up
as an `X-Plex-Token` header on requests to your server. They differ in who holds
the credential and how you change it.

**`PLEX_TOKEN` always wins.** If it is set, Marquee uses it and ignores any
token stored by signing in. Nothing about signing in can override a token you
declared in your environment.

## The two options

| | Sign in to Plex | `PLEX_TOKEN` |
| --- | --- | --- |
| Where the token is stored | `/config/data/plex-connection.json`, mode `0600` | Your compose file, and the container's environment |
| Who manages it | Marquee | You |
| How to change it | Sign out and sign in again, in the app | Edit the compose file and restart |
| Survives losing `/config` | No — sign in again | Yes |
| Visible in `docker inspect` | No | Yes |
| Included in backups of `/config` | Yes | No |
| Needs internet access | Yes, to `plex.tv`, while signing in | No |
| Suits automated / GitOps deployment | No | Yes |
| Precedence | Used only when `PLEX_TOKEN` is unset | **Always wins** |

Neither is more secure than the other in any absolute sense — they expose the
credential in different places. `PLEX_TOKEN` lives in a file people commit to
version control and paste into support threads, which is the leak that actually
happens; the stored token lives in a volume that may be backed up somewhere
else. Pick the one whose failure mode you would rather have.

`PLEX_SERVER_URL` is always set through the environment. Signing in does not
discover your server's address, and it never will as long as probing candidate
addresses would mean Marquee fetching URLs it was handed.

## Signing in

1. Set `PLEX_SERVER_URL` to your Plex server's address, if you have not already.
2. Open **Import from Plex** in Marquee.
3. Choose **Sign in to Plex**. A Plex window opens; approve Marquee there.
4. The panel reports the connected server by name.

The Plex window is opened by your browser and talks to Plex directly. Nothing
has to route back into Marquee, so this works unchanged behind a reverse proxy
and needs no publicly reachable address.

## Moving from `PLEX_TOKEN` to signing in

You do not have to. `PLEX_TOKEN` is supported indefinitely, is not deprecated,
and remains the right choice for deployments that are configured from a file
rather than by hand.

If you do want to move, do it in this order — there is no downtime and no
window where Marquee is disconnected:

1. **Sign in first.** With `PLEX_TOKEN` still set, open Import from Plex and
   sign in. The token is stored but not yet used; the panel says so.
2. **Then remove `PLEX_TOKEN`** from your `docker-compose.yml`.
3. **Restart.** `docker compose up -d`. The stored token takes over.

Doing it the other way round — removing the variable first — leaves Marquee
unconnected between the restart and the sign-in, and a scheduled auto-import
falling in that gap will skip.

To go back at any point, put `PLEX_TOKEN` back and restart. It wins again
immediately, whatever is stored.

## What the panel is telling you

| Panel says | Meaning |
| --- | --- |
| Connected to *server* · Signed in to Plex | A stored token is in use |
| Connected to *server* · Using `PLEX_TOKEN` | The environment variable is in use |
| Connected to *server* · Your Plex sign-in is stored but not in use | Both exist; `PLEX_TOKEN` wins. Remove it and restart to use the sign-in |
| Plex is not connected | Neither source supplied a token |

The connection is also reported in the footer and the mobile menu on every page,
so it is legible from the gallery where posters are sent and fetched.

That status describes how Marquee is **configured**, not whether Plex is
reachable this second. Marquee deliberately does not check reachability on page
render: with a ten-second connect timeout, a Plex server that is down would
stall every page in the app. When a Plex operation actually fails, the error
says so at that moment and names the fix that matches your connection.

## Things worth knowing

- **Signing out** clears the stored token only. Marquee stays the same
  registered device in your Plex account, and poster-wall images already on
  screen keep working.
- **The token is never shown.** The panel names your server, never the
  credential. No page or log entry contains it.
- **`AUTH_BYPASS=true` means anyone who can reach Marquee can sign it in or out
  of Plex**, the same way bypass already lets anyone delete every poster. That
  option is for trusted networks only.
- **A sign-in belongs to the browser session that started it.** Another session
  cannot complete it.
- **The scheduled auto-import** reads the stored token from disk, so it keeps
  working after you remove `PLEX_TOKEN`.
- **Deleting `/config/data/marquee.sqlite`** is still safe and still costs only
  cached data. The token is deliberately kept out of it.
