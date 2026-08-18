<?php

declare(strict_types=1);

namespace App\Tests\Unit\Settings;

use App\Config\AuthConfig;
use App\Config\PlexConfig;
use App\Config\PosterConfig;
use App\Settings\SettingKey;
use App\Settings\SettingsForm;
use App\Settings\SettingsStore;
use PHPUnit\Framework\TestCase;

/**
 * The form's own rules: conversions, refusals, and the exclusions merge.
 *
 * The screen that renders it is exercised in
 * {@see \App\Tests\Functional\SettingsScreenTest}; this is about the arithmetic
 * and the decisions, which are easier to pin one at a time.
 */
final class SettingsFormTest extends TestCase
{
    private string $dataDir = '';

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir() . '/marquee-form-' . bin2hex(random_bytes(6));
        mkdir($this->dataDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dataDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dataDir);
    }

    private function store(): SettingsStore
    {
        return new SettingsStore($this->dataDir);
    }

    /**
     * @param array<string, string|list<string>> $overrides
     *
     * @return array<string, string|list<string>>
     */
    private function submission(array $overrides = []): array
    {
        return array_merge([
            SettingsForm::FIELD_SITE_TITLE => 'Marquee',
            SettingsForm::FIELD_PER_PAGE => '24',
            SettingsForm::FIELD_DEFAULT_SORT => 'alphabetical',
            SettingsForm::FIELD_MAX_FILE_SIZE => '5',
            SettingsForm::FIELD_CONNECT_TIMEOUT => '10',
            SettingsForm::FIELD_REQUEST_TIMEOUT => '60',
            SettingsForm::FIELD_SESSION_DURATION => '30',
            SettingsForm::FIELD_AUTO_IMPORT_INTERVAL => '24h',
        ], $overrides);
    }

    /**
     * The whole point of the floors living on the configuration objects: what
     * this refuses and what bootstrap corrects are the same number, so a value
     * the screen accepts is never quietly changed on the next request.
     */
    public function testFloorsAreTheOnesBootstrapEnforces(): void
    {
        self::assertSame(PosterConfig::MINIMUM_PER_PAGE, SettingsForm::PER_PAGE_MIN);
        self::assertSame(PlexConfig::MINIMUM_TIMEOUT, SettingsForm::TIMEOUT_MIN);
        // Days rather than seconds, and deliberately stricter — see the class
        // comment on SettingsForm. Stricter is safe; looser would not be.
        self::assertGreaterThan(AuthConfig::MINIMUM_DURATION, SettingsForm::SESSION_DURATION_MIN * 86400);
    }

    /**
     * What was displayed is what saving stores: nothing is rounded invisibly on
     * the way back down into seconds and bytes.
     */
    public function testConvertedUnitsRoundTrip(): void
    {
        $form = new SettingsForm($this->store());
        $values = $form->values();

        // The defaults as the store keeps them: 2592000 seconds, 5242880 bytes.
        self::assertSame('30', $values[SettingsForm::FIELD_SESSION_DURATION]);
        self::assertSame('5', $values[SettingsForm::FIELD_MAX_FILE_SIZE]);

        $submission = $form->submit($this->submission([
            SettingsForm::FIELD_SESSION_DURATION => '30',
            SettingsForm::FIELD_MAX_FILE_SIZE => '5',
        ]), []);

        self::assertTrue($submission->isValid());
        self::assertSame(2592000, $submission->settings[SettingKey::SessionDuration->value]);
        self::assertSame(5242880, $submission->settings[SettingKey::MaxFileSize->value]);
    }

    /**
     * A value too small to be a whole day still shows as one. Rounding it to
     * nothing would invite saving nothing, which is a lockout.
     */
    public function testASubDayDurationRendersAsOneDayRatherThanZero(): void
    {
        $store = $this->store();
        $store->set(SettingKey::SessionDuration, 3600);

        self::assertSame('1', (new SettingsForm($store))->values()[SettingsForm::FIELD_SESSION_DURATION]);
    }

    /**
     * A value seeded before this screen existed must not hold the form hostage:
     * it renders adjusted into range rather than refused, so every other field
     * on the page can still be saved.
     */
    public function testAStoredValueOutsideTheOfferedRangeRendersClamped(): void
    {
        $store = $this->store();
        $store->set(SettingKey::ImagesPerPage, 5000);
        $store->set(SettingKey::PlexRequestTimeout, 9999);

        $values = (new SettingsForm($store))->values();

        self::assertSame((string) SettingsForm::PER_PAGE_MAX, $values[SettingsForm::FIELD_PER_PAGE]);
        self::assertSame((string) SettingsForm::TIMEOUT_MAX, $values[SettingsForm::FIELD_REQUEST_TIMEOUT]);
    }

    public function testAnEmptySiteTitleIsRefused(): void
    {
        $submission = (new SettingsForm($this->store()))
            ->submit($this->submission([SettingsForm::FIELD_SITE_TITLE => '   ']), []);

        self::assertFalse($submission->isValid());
        self::assertArrayHasKey(SettingsForm::FIELD_SITE_TITLE, $submission->errors);
        self::assertSame([], $submission->settings);
    }

    public function testAValueBelowAFloorIsRefused(): void
    {
        $submission = (new SettingsForm($this->store()))
            ->submit($this->submission([SettingsForm::FIELD_CONNECT_TIMEOUT => '0']), []);

        self::assertFalse($submission->isValid());
        self::assertArrayHasKey(SettingsForm::FIELD_CONNECT_TIMEOUT, $submission->errors);
    }

    public function testANonNumericFieldIsRefused(): void
    {
        $submission = (new SettingsForm($this->store()))
            ->submit($this->submission([SettingsForm::FIELD_PER_PAGE => 'lots']), []);

        self::assertFalse($submission->isValid());
        self::assertArrayHasKey(SettingsForm::FIELD_PER_PAGE, $submission->errors);
    }

    /**
     * Bootstrap falls back to A–Z for an unrecognised slug because it has a page
     * to render and nobody to ask. A form has someone to ask.
     */
    public function testAnUnrecognisedSortIsRefusedRatherThanDefaulted(): void
    {
        $submission = (new SettingsForm($this->store()))
            ->submit($this->submission([SettingsForm::FIELD_DEFAULT_SORT => 'by_vibes']), []);

        self::assertFalse($submission->isValid());
        self::assertArrayHasKey(SettingsForm::FIELD_DEFAULT_SORT, $submission->errors);
    }

    public function testARefusedSubmissionCarriesWhatWasTyped(): void
    {
        $submission = (new SettingsForm($this->store()))->submit($this->submission([
            SettingsForm::FIELD_SITE_TITLE => 'Home Cinema',
            SettingsForm::FIELD_PER_PAGE => 'lots',
            SettingsForm::FIELD_UPDATE_CHECK => 'on',
        ]), []);

        self::assertFalse($submission->isValid());
        self::assertSame('Home Cinema', $submission->values[SettingsForm::FIELD_SITE_TITLE]);
        self::assertSame('lots', $submission->values[SettingsForm::FIELD_PER_PAGE]);
        self::assertTrue($submission->values[SettingsForm::FIELD_UPDATE_CHECK]);
    }

    public function testCheckboxesAreReadByPresence(): void
    {
        $submission = (new SettingsForm($this->store()))->submit($this->submission([
            SettingsForm::FIELD_IGNORE_ARTICLES => 'on',
        ]), []);

        self::assertTrue($submission->isValid());
        self::assertTrue($submission->settings[SettingKey::IgnoreArticlesInSort->value]);
        // Absent, therefore off — an unchecked box submits nothing at all.
        self::assertFalse($submission->settings[SettingKey::UpdateCheckEnabled->value]);
    }

    public function testExclusionsAreReplacedOnlyForReportedLibraries(): void
    {
        $store = $this->store();
        $store->set(SettingKey::ExcludedLibraries, ['Kids', 'Gone']);
        $form = new SettingsForm($store);

        $merged = $form->mergeExclusions(['Movies'], ['Movies', 'Kids', 'TV']);

        // Kids was reported and left unticked, so it is no longer excluded.
        // Gone was not reported at all, so the save leaves it exactly as it was.
        self::assertSame(['Gone', 'Movies'], $merged);
    }

    /**
     * The failure this rule exists to prevent: a library that is renamed,
     * removed, or on a server that did not answer must not un-hide itself
     * because someone changed the site title.
     */
    public function testAnUnreportedExclusionSurvivesASaveThatReportsNothing(): void
    {
        $store = $this->store();
        $store->set(SettingKey::ExcludedLibraries, ['Kids']);

        self::assertSame(['Kids'], (new SettingsForm($store))->mergeExclusions([], []));
    }

    public function testUnreportedExclusionsCanBeClearedDeliberately(): void
    {
        $store = $this->store();
        $store->set(SettingKey::ExcludedLibraries, ['Kids', 'Gone']);

        $merged = (new SettingsForm($store))->mergeExclusions(['Kids'], ['Kids', 'TV'], clearUnreported: true);

        self::assertSame(['Kids'], $merged);
    }

    /**
     * An unreachable server reports nothing, which would make every exclusion
     * look stale. One tick must not become "forget every library I ever hid".
     */
    public function testClearingIsIgnoredWhenNoLibraryWasReported(): void
    {
        $store = $this->store();
        $store->set(SettingKey::ExcludedLibraries, ['Kids']);

        self::assertSame(['Kids'], (new SettingsForm($store))->mergeExclusions([], [], clearUnreported: true));
    }

    /**
     * Matching goes through the same case-insensitive rule the application
     * excludes by, so what the checkbox shows and what Plex listing does cannot
     * disagree.
     */
    public function testALibraryIsMarkedExcludedRegardlessOfStoredCase(): void
    {
        $choices = (new SettingsForm($this->store()))->libraryChoices(['Kids Movies'], ['kids movies']);

        self::assertSame([['title' => 'Kids Movies', 'excluded' => true]], $choices);
    }

    public function testEverySortOrderIsOfferedWithADistinctLabel(): void
    {
        $options = (new SettingsForm($this->store()))->sortOptions();

        $labels = array_map(static fn (array $o): string => $o['label'], $options);

        self::assertCount(4, $options);
        self::assertSame($labels, array_unique($labels));
    }

    /**
     * The form offers no server address, so a submission carrying one is either
     * a stale template or someone posting by hand. Either way it is ignored
     * rather than stored.
     *
     * Worth pinning because the form builds what it saves from the fields it
     * knows: a future change that started copying the body through would reopen
     * the browser path to the address without anything looking wrong.
     */
    public function testASubmittedServerAddressIsNotStored(): void
    {
        $form = new SettingsForm($this->store());

        $submission = $form->submit($this->submission([
            'plex_server_url' => 'http://attacker.example:32400',
        ]), []);

        self::assertTrue($submission->isValid());
        self::assertArrayNotHasKey('plex_server_url', $submission->settings);
    }
}
