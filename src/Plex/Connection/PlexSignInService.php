<?php

declare(strict_types=1);

namespace App\Plex\Connection;

use App\Auth\SessionAuthenticator;
use App\Support\Scalar;
use App\Support\Session\SessionInterface;

/**
 * Drives signing in to Plex: start a request, poll it to completion, sign out.
 *
 * Signing in to Plex is how Marquee is entered, so a completed sign-in produces
 * two things from one approval — the token Marquee acts with, and the session
 * the browser is trusted by. They are produced together here and destroyed
 * separately: logging out ends the session, disconnecting forgets the token.
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
        private readonly SessionAuthenticator $auth,
    ) {
    }

    /**
     * Begin a sign-in and return the address the browser should open.
     *
     * An outstanding request is reused rather than replaced. Starting a sign-in
     * is reachable without a session — it is how a session is obtained — and is
     * the only unauthenticated action that calls plex.tv, holding a worker for
     * the round trip. Minting a request per call would turn every repeated
     * attempt into another plex.tv call and another parked worker.
     *
     * It also fixes something visible in ordinary use: activating the sign-in
     * control twice used to create a second request and abandon the first, so
     * the Plex window the user was looking at was no longer the one being
     * polled.
     *
     * @throws PlexSignInException
     */
    public function start(): string
    {
        $pin = $this->pending();
        if ($pin !== null && !$pin->hasExpired()) {
            return $this->client->authorizationUrl($pin);
        }

        $pin = $this->client->create();

        $this->session->set(self::KEY_ID, $pin->id);
        $this->session->set(self::KEY_CODE, $pin->code);
        $this->session->set(self::KEY_EXPIRES_AT, $pin->expiresAt);

        return $this->client->authorizationUrl($pin);
    }

    /**
     * Check the outstanding request, completing the sign-in if it was approved.
     *
     * No outcome other than approval touches the store or the session. A sign-in
     * the user abandoned must leave an already-connected Marquee exactly as it
     * was, and must authenticate nobody.
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

        $refusal = $this->decide($token);
        if ($refusal !== null) {
            // Refused before anything is written and before anyone is
            // authenticated. Plex would stop this account altering the library,
            // but not deleting posters here — and a poster that never reached
            // Plex has no copy to restore.
            $this->forget();

            return $refusal;
        }

        // Stored on every accepted sign-in, not only the first. Signing in again
        // is how a user replaces a token they revoked in their Plex account, and
        // keeping the old one would leave that install permanently unable to
        // reach Plex with no action left to take.
        $this->store->storeToken($token);
        $this->forget();
        $this->auth->establish();

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
     * Decide the sign-in: null when the token is accepted, or the reason it is
     * refused. Accepting a token that had no recorded owner records the owner it
     * was verified against, because this is the one place holding it.
     *
     * Fails closed, and is written so that it does so by shape: every path but
     * one returns a refusal, and the single accepting path requires a named
     * owner that the account behind the token matches. If the server will not
     * say who owns it, or plex.tv will not say who the account is, the answer
     * is a refusal — a check that passes when it cannot run is not a check, and
     * this one is the only thing standing between a stranger's Plex account and
     * the delete button.
     *
     * Where an owner has already been recorded, that value is what the account
     * is compared against and the server is not asked. The server is the right
     * authority for a first connection, when no token is stored and the
     * candidate is the only one there is. As a check on every login it would
     * make entering Marquee depend on the user's own Plex server answering, so
     * a server reboot would lock the owner out of an application that otherwise
     * still works without it.
     *
     * The refusals differ only in what they tell the user. An unreachable
     * server is reported as such rather than as an ownership verdict, because
     * the owner reading "your account does not own this server" is being sent
     * to audit the one part of the system that is working.
     *
     * @throws PlexSignInException when plex.tv cannot say who the token belongs
     *                             to, which is not a fact about the account
     */
    private function decide(string $token): ?PlexSignInStatus
    {
        $recorded = $this->store->owner();
        if ($recorded !== null) {
            return $this->client->account($token)?->matches($recorded) === true
                ? null
                : PlexSignInStatus::NotOwner;
        }

        $lookup = $this->owner->forToken($token);
        if ($lookup->isUnreachable()) {
            return PlexSignInStatus::Unreachable;
        }

        $owner = $lookup->owner();
        if ($owner === null) {
            return PlexSignInStatus::NotOwner;
        }

        if ($this->client->account($token)?->matches($owner) !== true) {
            return PlexSignInStatus::NotOwner;
        }

        $this->store->storeOwner($owner);

        return null;
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
