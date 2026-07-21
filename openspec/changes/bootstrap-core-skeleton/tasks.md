## 1. Project scaffolding

- [ ] 1.1 Create `composer.json` (PSR-4 `App\`, PHP 8.3 platform) with Slim 4,
      PHP-DI, Guzzle, Twig, Monolog; dev: PHPUnit, PHPStan, PHP-CS-Fixer.
- [ ] 1.2 Add tooling config: `phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.dist.php`.
- [ ] 1.3 Run `composer install` and commit `composer.lock`.

## 2. Application shell

- [ ] 2.1 `src/bootstrap.php`: build the DI container and typed config objects.
- [ ] 2.2 `src/Config/{AppConfig,AuthConfig}.php` + env reader helpers.
- [ ] 2.3 `public/index.php`: front controller wiring middleware and routes.
- [ ] 2.4 `src/Routes.php`: register `/`, `/login`, `/logout`, `/health`.
- [ ] 2.5 Error-handling middleware + Monolog logger to `/config/data`.
- [ ] 2.6 `HomeController` and `HealthController`; Twig `layout.html.twig` + home.

## 3. Authentication

- [ ] 3.1 `Auth\SessionAuthenticator`: verify credentials, start/expire sessions.
- [ ] 3.2 `Auth\AuthMiddleware`: guard routes, honor `AUTH_BYPASS`, allow `/health`.
- [ ] 3.3 Login controller + Twig login page; logout route clears the session.

## 4. Tests

- [ ] 4.1 Health endpoint returns 200 without auth.
- [ ] 4.2 Protected route redirects to login when unauthenticated.
- [ ] 4.3 Valid credentials authenticate; expired session is rejected.
- [ ] 4.4 `AUTH_BYPASS=true` grants access without login.

## 5. Container & CI

- [ ] 5.1 `Dockerfile` (LinuxServer Alpine-nginx base, Composer build stage).
- [ ] 5.2 `docker/` s6 service + nginx config; `/config` volume; healthcheck.
- [ ] 5.3 `.github/workflows/ci.yml`: cs-fixer (dry-run), phpstan, phpunit.

## 6. Cleanup & docs

- [ ] 6.1 Remove legacy `src/index.php`, `src/include/`, `src/poster-wall/`,
      old `Dockerfile` and `docker/` layout.
- [ ] 6.2 New `README.md`, `.gitignore`, and `VERSION` for Marquee.
- [ ] 6.3 `openspec validate bootstrap-core-skeleton` passes.
