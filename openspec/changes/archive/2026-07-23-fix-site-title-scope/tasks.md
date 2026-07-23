## 1. Product name

- [x] 1.1 In `src/Config/AppConfig.php`, add `public const APP_NAME = 'Marquee';`
      to the class. It is a constant, not an `Env::str()` lookup, so no
      environment variable can override it.
- [x] 1.2 In the same file, change the `siteTitle` default from the literal
      `'Marquee'` to `self::APP_NAME`, so an unconfigured install is unchanged
      and the literal has exactly one home.
- [x] 1.3 In `src/bootstrap.php`, register an `app_name` Twig global next to the
      existing `site_title` global (line 95), set to `AppConfig::APP_NAME`.

## 2. Stop SITE_TITLE from naming the product

- [x] 2.1 In `templates/layout.html.twig` line 8, change the
      `apple-mobile-web-app-title` meta tag content from `{{ site_title }}` to
      `{{ app_name }}`.
- [x] 2.2 In `templates/layout.html.twig` line 30, change the footer from
      `{{ site_title }}` to `{{ app_name }}`.
- [x] 2.3 In `src/Controller/ManifestController.php`, set `name` and
      `short_name` to `AppConfig::APP_NAME` instead of `$this->config->siteTitle`.
- [x] 2.4 Update the class docblock on `ManifestController` — it currently says
      "named after SITE_TITLE", which will no longer be true.

## 3. Leave the brand and tab titles alone

- [x] 3.1 Confirm `templates/layout.html.twig` line 20 (`<a class="brand">`)
      still uses `site_title`. This is the one place it belongs.
- [x] 3.2 Confirm the `<title>` tags in `layout`, `gallery`, `orphans`, `plex`,
      `login`, and `wall` still use `site_title`. Tab titles are intentionally
      out of scope.

## 4. Verify

- [x] 4.1 Invert `tests/Functional/PwaTest::testManifestIsPublicAndNamedAfterSiteTitle`:
      it currently asserts `"name":"My Wall"` when `SITE_TITLE=My Wall`, which
      enforces the bug. Assert the manifest is named `Marquee` instead, and
      rename the test to reflect that the manifest ignores `SITE_TITLE`.
- [x] 4.2 Add a functional assertion that with `SITE_TITLE=My Wall` a rendered
      page's footer contains `Marquee` and not `My Wall`, and that the
      `apple-mobile-web-app-title` meta tag contains `Marquee`.
- [x] 4.3 Keep `ApplicationShellTest::testGalleryRendersSiteTitle` green — the
      brand must still render `My Wall`. Tighten it to assert the brand link
      specifically rather than a bare body substring, so it cannot pass on an
      incidental match elsewhere in the page.
- [x] 4.4 Update the `SITE_TITLE` row in the `README.md` config table (line 113):
      "Title shown in the UI" is now too broad. Say it sets the site name shown
      in the header, and note it does not affect the installed app name.
- [x] 4.5 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
      PHPStan and PHP-CS-Fixer clean. The full PHPUnit run reports 52 errors and
      1 failure on the dev machine, but the identical 52/1 appear on an
      unmodified checkout — they come from missing ext-gd, ext-intl, ext-iconv,
      and pdo_sqlite, not from this change. The affected suites
      (`PwaTest`, `ApplicationShellTest`) run green: 8 tests, 23 assertions.
      Confirm the full suite in CI.
- [x] 4.6 `openspec validate fix-site-title-scope --strict` passes.
