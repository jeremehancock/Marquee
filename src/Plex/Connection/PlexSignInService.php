<?php

declare(strict_types=1);

namespace App\Plex\Connection;

use App\Support\Scalar;
use App\Support\Session\SessionInterface;

/**
 * Drives signing in to Plex: start a request, poll it to completion, sign out.
 *
 * The outstanding request lives in the session and nowhere else, and polling
 * takes no identifier from the caller. That is what binds a sign-in to the
 * browser that began it — another session has no request recorded, so there is
 * nothing for it to claim, and no id it could supply would be honoured.
 */
final class PlexSignInService
{
    private const KEY_ID = 'plex_pin_id';
    private const KEY_CODE = 'plex_pin_code';
    private const KEY_EXPIRES_AT = 'plex_pin_expires_at';

    public function __construct(
        private readonly PlexPinClient $client,
        private readonly PlexConnectionStore $store,
        private readonly SessionInterface $session,
        private readonly PlexServerOwner $owner,
    ) {
    }

    /**
     * Begin a sign-in and return the address the browser should open.
     *
     * @throws PlexSignInException
     */
    public function start(): string
    {
        $pin = $this->client->create();

        $this->session->set(self::KEY_ID, $pin->id);
        $this->session->set(self::KEY_CODE, $pin->code);
        $this->session->set(self::KEY_EXPIRES_AT, $pin->expiresAt);

        return $this->client->authorizationUrl($pin);
    }

    /**
     * Check the outstanding request, storing the token if it has been approved.
     *
     * No outcome other than approval touches the store. A sign-in the user
     * abandoned must leave an already-connected Marquee exactly as it was.
     *
     * @throws PlexSignInException when plex.tv cannot be reached
     */
    public function poll(): PlexSignInStatus
    {
        $pin = $this->pending();
        if ($pin === null) {
            return PlexSignInStatus::NotStarted;
        }

        if ($pin->hasExpired()) {
            $this->forget();

            return PlexSignInStatus::Expired;
        }

        try {
            $token = $this->client->token($pin);
        } catch (PlexSignInException $e) {
            if ($e->expired) {
                $this->forget();

                return PlexSignInStatus::Expired;
            }

            throw $e;
        }

        if ($token === null) {
            return PlexSignInStatus::Pending;
        }

        $refusal = $this->refusal($token);
        if ($refusal !== null) {
            // Refused before anything is written. Plex would stop this account
            // altering the library, but not deleting posters here — and a
            // poster that never reached Plex has no copy to restore.
            $this->forget();

            return $refusal;
        }

        $this->store->storeToken($token);
        $this->forget();

        return PlexSignInStatus::Completed;
    }

    /**
     * Forget the stored token, leaving the client identifier and the wall's
     * signing secret in place.
     */
    public function signOut(): void
    {
        $this->store->clearToken();
        $this->forget();
    }

    /**
     * Why this token may not be stored, or null when there is no reason.
     *
     * Fails closed, and is written so that it does so by shape: every path but
     * one returns a refusal, and the single accepting path requires a named
     * owner that the account behind the token matches. If the server will not
     * say who owns it, or plex.tv will not say who the account is, the answer
     * is a refusal — a check that passes when it cannot run is not a check, and
     * this one is the only thing standing between a stranger's Plex account and
     * the delete button.
     *
     * The refusals differ only in what they tell the user. An unreachable
     * server is reported as such rather than as an ownership verdict, because
     * the owner reading "your account does not own this server" is being sent
     * to audit the one part of the system that is working.
     *
     * @throws PlexSignInException when plex.tv cannot say who the token belongs
     *                             to, which is not a fact about the account
     */
    private function refusal(string $token): ?PlexSignInStatus
    {
        $lookup = $this->owner->forToken($token);
        if ($lookup->isUnreachable()) {
            return PlexSignInStatus::Unreachable;
        }

        $owner = $lookup->owner();
        if ($owner === null) {
            return PlexSignInStatus::NotOwner;
        }

        return $this->client->account($token)?->matches($owner) === true
            ? null
            : PlexSignInStatus::NotOwner;
    }

    /**
     * The request this session started, or null when it has none.
     */
    private function pending(): ?PlexPin
    {
        $id = Scalar::intOrNull($this->session->get(self::KEY_ID));
        $code = Scalar::stringOrNull($this->session->get(self::KEY_CODE));
        if ($id === null || $code === null) {
            return null;
        }

        return new PlexPin($id, $code, Scalar::int($this->session->get(self::KEY_EXPIRES_AT, 0)));
    }

    private function forget(): void
    {
        $this->session->set(self::KEY_ID, null);
        $this->session->set(self::KEY_CODE, null);
        $this->session->set(self::KEY_EXPIRES_AT, null);
    }
}
