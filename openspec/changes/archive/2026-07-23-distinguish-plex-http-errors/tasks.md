## 1. Exception factories

- [x] 1.1 Add `PlexException::itemNotFound()` returning a user-facing message that the item no longer exists in Plex, that the poster may be orphaned, and directing to the Orphans page
- [x] 1.2 Add `PlexException::authFailed()` returning a user-facing message that the Plex token was rejected

## 2. HTTP error classification

- [x] 2.1 Add a private `classify(GuzzleException $e): PlexException` helper to `HttpPlexClient` that returns `itemNotFound()` for a 404 response, `authFailed()` for a 401 response, and `connectionFailed($e)` otherwise (including when there is no response, e.g. `ConnectException`)
- [x] 2.2 Replace the `throw PlexException::connectionFailed($e)` in every Guzzle catch block (`get()`, `write()`, `downloadPoster()`, `itemPoster()`) with `throw $this->classify($e)`
- [x] 2.3 Confirm `unexpectedResponse()` paths are untouched (they are not connection failures)

## 3. Tests

- [x] 3.1 Unit test: a 404 from the Plex client on Fetch surfaces the `itemNotFound()` message and leaves the local file unchanged
- [x] 3.2 Unit test: a 404 from the Plex client on Re-send surfaces the `itemNotFound()` message
- [x] 3.3 Unit test: a 401 surfaces the `authFailed()` message
- [x] 3.4 Unit test: a transport failure (`ConnectException`, no response) still surfaces the `connectionFailed()` message

## 4. Verification

- [x] 4.1 Run PHPStan (max level) and PHPUnit; both pass
- [x] 4.2 `openspec validate distinguish-plex-http-errors --strict` passes
