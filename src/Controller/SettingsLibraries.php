<?php

declare(strict_types=1);

namespace App\Controller;

/**
 * What the settings screen knows about the server's libraries: their names, or
 * why it has none.
 *
 * The two travel together because the screen fetches them twice — once to render
 * and once to bound a save — and an empty list means different things with and
 * without a reason attached. No libraries and no error is a server with none;
 * no libraries and an error is a server that could not be asked, and a save must
 * leave every stored exclusion alone.
 */
final class SettingsLibraries
{
    /**
     * @param list<string> $titles library names the server reports, exclusions included
     * @param string|null  $error  why the server could not be asked, or null
     */
    public function __construct(
        public readonly array $titles,
        public readonly ?string $error,
    ) {
    }
}
