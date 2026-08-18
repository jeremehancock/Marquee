<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\Claim\ClaimAttempts;
use App\Auth\Claim\ClaimServerProbe;
use App\Auth\Claim\ClaimService;
use App\Auth\ClaimMiddleware;
use App\Auth\PlexConnectionMiddleware;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * Step one of the first run: prove host access, and say where Plex is.
 *
 * The only screen in Marquee reachable with neither a session nor a connection,
 * and the only one that exists before an install belongs to anybody. Everything
 * else about a first run — signing in, choosing settings — happens after this and
 * is unchanged by it.
 *
 * No response from here discloses the claim code, including when it refuses one.
 * A message that said "expected X" would hand over the thing being protected, and
 * a message that narrowed it ("the first four characters are right") would be
 * worse. There is one refusal message.
 */
final class ClaimController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly ClaimService $claim,
        private readonly ClaimServerProbe $probe,
        private readonly Flash $flash,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->claim->isClaimed()) {
            // Nothing to do here twice. A claimed install sends anyone who finds
            // this URL onward rather than showing a form that cannot succeed.
            return $response
                ->withHeader('Location', PlexConnectionMiddleware::CONNECTION_PATH)
                ->withStatus(302);
        }

        return $this->render($response, '', '', null);
    }

    public function claim(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->claim->isClaimed()) {
            return $response
                ->withHeader('Location', PlexConnectionMiddleware::CONNECTION_PATH)
                ->withStatus(302);
        }

        $body = (array) $request->getParsedBody();
        $code = isset($body['code']) && is_string($body['code']) ? trim($body['code']) : '';
        $serverUrl = isset($body['server_url']) && is_string($body['server_url']) ? trim($body['server_url']) : '';

        $cooling = $this->claim->coolingOffSeconds();
        if ($cooling > 0) {
            return $this->render($response, $code, $serverUrl, sprintf(
                'Too many incorrect codes. Try again in %d minute%s.',
                (int) ceil($cooling / 60),
                $cooling > 60 ? 's' : '',
            ));
        }

        if ($code === '') {
            return $this->render($response, $code, $serverUrl, 'Enter the claim code.');
        }

        if ($serverUrl === '') {
            return $this->render($response, $code, $serverUrl, 'Enter your Plex server address.');
        }

        // Probed before the code is checked, so a correct code is never spent on
        // an address that was going to fail anyway — the code is single-use, and
        // burning it on a typo would mean digging the file out again.
        if (!$this->probe->isPlexServer($serverUrl)) {
            return $this->render(
                $response,
                $code,
                $serverUrl,
                'No Plex server answered at that address. Check the host and port, then try again.',
            );
        }

        if (!$this->claim->claim($code, $serverUrl)) {
            // One message for a wrong code and for a code offered during the
            // cooling-off, so neither reveals which it was.
            return $this->render($response, $code, $serverUrl, 'That claim code is not correct.');
        }

        $this->flash->add('success', 'Marquee is yours. Sign in to Plex to finish setting it up.');

        return $response
            ->withHeader('Location', PlexConnectionMiddleware::CONNECTION_PATH)
            ->withStatus(302);
    }

    private function render(
        ResponseInterface $response,
        string $code,
        string $serverUrl,
        ?string $error,
    ): ResponseInterface {
        return $this->twig->render($response, 'claim.html.twig', [
            'code' => $code,
            'server_url' => $serverUrl,
            'error' => $error,
            'code_path' => ClaimService::FILENAME,
            'attempts_limit' => ClaimAttempts::LIMIT,
            'claim_path' => ClaimMiddleware::CLAIM_PATH,
        ]);
    }
}
