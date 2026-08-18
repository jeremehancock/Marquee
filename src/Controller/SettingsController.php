<?php

declare(strict_types=1);

namespace App\Controller;

use App\Plex\PlexClient;
use App\Plex\PlexException;
use App\Plex\PlexFailureMessage;
use App\Plex\PlexLibrary;
use App\Settings\SettingsForm;
use App\Settings\SettingsStore;
use App\Settings\SupersededEnvironment;
use App\Support\Flash;
use App\Support\LastCategory;
use App\Support\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * The settings screen: change the configuration without recreating the
 * container.
 *
 * Libraries are fetched with {@see PlexClient::allLibraries()} rather than
 * `libraries()`, and this is the only place in the application that does so.
 * The ordinary listing hides excluded libraries, which would make exclusion a
 * one-way door — the screen for undoing it could not see what to undo.
 *
 * A failed fetch is not a failed page. Configuration is what someone reaches for
 * when something is wrong, so a settings screen that needs a working Plex
 * connection would be unavailable exactly when it is wanted. The library section
 * explains itself and the rest of the form still saves.
 */
final class SettingsController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly PlexClient $plex,
        private readonly SettingsForm $form,
        private readonly SettingsStore $store,
        private readonly SupersededEnvironment $superseded,
        private readonly PlexFailureMessage $plexMessage,
        private readonly Flash $flash,
        private readonly SessionInterface $session,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $values = $this->form->values();

        return $this->render($response, $values, []);
    }

    /**
     * Save, or re-render carrying what was typed.
     *
     * The libraries are fetched again here rather than trusted from the
     * submission. They bound which exclusions the save may change, so taking
     * them from the request would let the bound be set by the thing it bounds.
     * A server that has become unreachable between rendering and saving reports
     * nothing, which the merge reads as "change no exclusions" — the safe
     * direction.
     */
    public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $libraries = $this->libraries();

        $submission = $this->form->submit(self::fields($request->getParsedBody()), $libraries->titles);

        if (!$submission->isValid()) {
            return $this->render($response, $submission->values, $submission->errors, $libraries);
        }

        $this->store->setMany($submission->settings);
        $this->flash->add('success', 'Settings saved.');

        return $response->withHeader('Location', '/settings')->withStatus(302);
    }

    /**
     * @param array<string, string|bool|list<string>> $values
     * @param array<string, string>                   $errors
     */
    private function render(
        ResponseInterface $response,
        array $values,
        array $errors,
        ?SettingsLibraries $libraries = null,
    ): ResponseInterface {
        $libraries ??= $this->libraries();

        $excluded = $values[SettingsForm::FIELD_EXCLUDED] ?? [];

        return $this->twig->render($response, 'settings.html.twig', [
            'values' => $values,
            'errors' => $errors,
            'sort_options' => $this->form->sortOptions(),
            'interval_options' => $this->form->intervalOptions(),
            'libraries' => $this->form->libraryChoices(
                $libraries->titles,
                is_array($excluded) ? $excluded : [],
            ),
            // Stored exclusions the server did not report — kept by the merge,
            // listed here so a stale one can be removed deliberately.
            'unreported_exclusions' => $this->form->unreportedExclusions($libraries->titles),
            'libraries_error' => $libraries->error,
            'plex_configured' => $this->plex->isConfigured(),
            // Relocated only. What replaced a retired variable is a different
            // sentence in a different place — see the connection screen.
            'superseded' => $this->superseded->relocatedNames(),
            'flash' => $this->flash->pull(),
            'back_url' => LastCategory::backUrl($this->session),
        ]);
    }

    /**
     * The parsed body as named fields.
     *
     * A parsed body is whatever the request carried; a form field has a name. A
     * numeric key cannot name one of these fields, so dropping those here means
     * the form works in field names alone.
     *
     * @return array<string, mixed>
     */
    private static function fields(mixed $body): array
    {
        if (!is_array($body)) {
            return [];
        }

        $fields = [];
        foreach ($body as $key => $value) {
            if (is_string($key)) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    private function libraries(): SettingsLibraries
    {
        if (!$this->plex->isConfigured()) {
            return new SettingsLibraries([], null);
        }

        try {
            return new SettingsLibraries(
                array_map(static fn (PlexLibrary $library): string => $library->title, $this->plex->allLibraries()),
                null,
            );
        } catch (PlexException $e) {
            return new SettingsLibraries([], $this->plexMessage->for($e));
        }
    }
}
