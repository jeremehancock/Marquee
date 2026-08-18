<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

/**
 * Which environment variables Marquee advertises, and to whom.
 *
 * Documentation is under test here because the split is a decision, not an
 * accident. `AppConfig` reads `DATA_DIR` and `POSTERS_DIR`, and they are kept
 * out of the README on purpose: the README promises that `/config` holds
 * everything and that backing it up is enough, and offering the subpaths as
 * knobs makes that promise conditional on an install nobody asked for.
 *
 * The argument that justifies {@see AppConfigTest} justifies this file too. A
 * decision recorded only in prose is one a later edit reverses with nothing
 * failing — so if someone adds `DATA_DIR` to the table, this test stops them and
 * sends them to the spec to overturn the decision deliberately.
 *
 * Matching is on the variable name alone, never on table syntax or the prose
 * around it, so the documentation stays free to be rewritten.
 */
final class ConfigurationSurfaceTest extends TestCase
{
    /**
     * Variables an install is expected to set. Their presence is the positive
     * control: without it, every absence assertion below would also pass against
     * a README that had lost its configuration section entirely.
     *
     * `SITE_TITLE` used to be the first of these and is deliberately no longer
     * here. It became a field on the settings screen, so an install is no longer
     * expected to set it — and the README's compose example is now asserted to
     * name no variable the store owns. A control has to be a variable that will
     * still be in the README next year, which is why the two left are the one
     * the connection cannot work without and the one that is a path rather than
     * a preference.
     */
    private const DOCUMENTED_FOR_USERS = ['PLEX_SERVER_URL', 'SESSION_DIR'];

    /**
     * Read by `AppConfig`, deliberately not offered. The `/config` layout is
     * presented as fixed; these remain available to an operator who has already
     * gone looking in the source.
     */
    private const WITHHELD_FROM_USERS = ['DATA_DIR', 'POSTERS_DIR'];

    /** Settings that exist for the development loop and nothing else. */
    private const DEVELOPER_ONLY = ['DISPLAY_ERRORS'];

    public function testTheReadmeDocumentsWhatAnInstallHasToDecide(): void
    {
        foreach (self::DOCUMENTED_FOR_USERS as $variable) {
            self::assertTrue(
                self::mentions($this->readme(), $variable),
                sprintf(
                    '%s is a setting an install is expected to choose, so it must stay in the README. '
                    . 'It is also the control that gives this file\'s absence assertions meaning.',
                    $variable,
                ),
            );
        }
    }

    /**
     * The absence is checked across the whole README, commented-out compose
     * examples included: a commented `# DATA_DIR:` line advertises the setting
     * just as effectively as a table row does.
     */
    public function testTheVolumeLayoutIsNotOfferedAsConfigurable(): void
    {
        foreach (self::WITHHELD_FROM_USERS as $variable) {
            self::assertFalse(
                self::mentions($this->readme(), $variable),
                sprintf(
                    '%s is deliberately absent from the README: the /config layout is documented as fixed, '
                    . 'and advertising the subpaths makes "back up /config" conditional. '
                    . 'Overturn that decision in the application-shell spec before documenting it here.',
                    $variable,
                ),
            );
        }
    }

    public function testDeveloperSettingsAreDocumentedWhereDevelopersLook(): void
    {
        foreach (self::DEVELOPER_ONLY as $variable) {
            self::assertTrue(
                self::mentions($this->developmentWorkflow(), $variable),
                sprintf('%s must stay documented in docs/development-workflow.md.', $variable),
            );
        }
    }

    public function testDeveloperSettingsStayOutOfTheUserFacingTable(): void
    {
        foreach (self::DEVELOPER_ONLY as $variable) {
            self::assertFalse(
                self::mentions($this->readme(), $variable),
                sprintf(
                    '%s is a development setting, not a knob an install is meant to turn. '
                    . 'The README table is for decisions a user actually has to make.',
                    $variable,
                ),
            );
        }
    }

    private function readme(): string
    {
        return $this->read('README.md');
    }

    private function developmentWorkflow(): string
    {
        return $this->read('docs/development-workflow.md');
    }

    private function read(string $relative): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $relative);
        self::assertIsString($source, sprintf('%s must be readable.', $relative));

        return $source;
    }

    /**
     * The variable name as a whole token, so that neither `MY_DATA_DIR` nor
     * `DATA_DIRECTORY` counts as a mention.
     */
    private static function mentions(string $source, string $variable): bool
    {
        return preg_match('/\b' . preg_quote($variable, '/') . '\b/', $source) === 1;
    }
}
