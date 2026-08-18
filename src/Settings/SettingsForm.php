<?php

declare(strict_types=1);

namespace App\Settings;

use App\Config\AuthConfig;
use App\Config\LibraryExclusions;
use App\Config\PlexConfig;
use App\Config\PosterConfig;
use App\Poster\SortOrder;

/**
 * The settings screen's form: the stored configuration rendered as fields, and a
 * submission validated back into settings to store.
 *
 * It owns no rules of its own. Every floor it enforces is the constant its
 * configuration object enforces at bootstrap, so a value this accepts is never
 * one the next request silently corrects — the failure that makes a setting look
 * like it will not stick.
 *
 * Where this is *stricter* than bootstrap it says so at the constant. Stricter is
 * safe in a way looser is not: nothing this accepts is corrected later, which is
 * the property that matters. A session measured in whole days cannot express the
 * sixty-second floor {@see AuthConfig} enforces, and nothing is lost by that —
 * a session shorter than a day is a lockout dressed as a preference.
 *
 * Units are converted here and nowhere else. The store keeps seconds and bytes,
 * exactly as an install seeded before this screen existed left them; the screen
 * shows days and megabytes, because those are the units the question is asked in.
 */
final class SettingsForm
{
    public const FIELD_SITE_TITLE = 'site_title';
    public const FIELD_PER_PAGE = 'images_per_page';
    public const FIELD_DEFAULT_SORT = 'default_sort';
    public const FIELD_IGNORE_ARTICLES = 'ignore_articles';
    public const FIELD_MAX_FILE_SIZE = 'max_file_size';
    public const FIELD_CONNECT_TIMEOUT = 'plex_connect_timeout';
    public const FIELD_REQUEST_TIMEOUT = 'plex_request_timeout';
    public const FIELD_REMOVE_OVERLAY_LABEL = 'remove_overlay_label';
    public const FIELD_SESSION_DURATION = 'session_duration';
    public const FIELD_UPDATE_CHECK = 'update_check';
    public const FIELD_EXCLUDED = 'excluded';
    public const FIELD_CLEAR_UNREPORTED = 'clear_unreported';

    public const SITE_TITLE_MAX_LENGTH = 60;

    public const PER_PAGE_MIN = PosterConfig::MINIMUM_PER_PAGE;
    public const PER_PAGE_MAX = 200;

    /**
     * Megabytes, not bytes. One megabyte is stricter than
     * {@see PosterConfig::MINIMUM_FILE_SIZE}, which floors at a single byte —
     * a limit no poster could pass.
     */
    public const MAX_FILE_SIZE_MIN = 1;
    public const MAX_FILE_SIZE_MAX = 100;

    public const TIMEOUT_MIN = PlexConfig::MINIMUM_TIMEOUT;
    public const TIMEOUT_MAX = 300;

    /**
     * Days. Stricter than {@see AuthConfig::MINIMUM_DURATION}, which floors at
     * sixty seconds; see the class comment for why stricter is safe here.
     */
    public const SESSION_DURATION_MIN = 1;
    public const SESSION_DURATION_MAX = 365;

    private const SECONDS_PER_DAY = 86400;
    private const BYTES_PER_MB = 1048576;

    public function __construct(private readonly SettingsStore $store)
    {
    }

    /**
     * The stored configuration as field values.
     *
     * A stored value outside the range the screen offers is displayed adjusted
     * into it rather than refused. Rendering a value the form cannot accept
     * would make every other field on the screen unsavable — a value seeded
     * before this screen existed would hold the whole form hostage.
     *
     * @return array<string, string|bool|list<string>>
     */
    public function values(): array
    {
        return [
            self::FIELD_SITE_TITLE => $this->store->string(SettingKey::SiteTitle),
            self::FIELD_PER_PAGE => (string) self::clamp(
                $this->store->int(SettingKey::ImagesPerPage),
                self::PER_PAGE_MIN,
                self::PER_PAGE_MAX,
            ),
            self::FIELD_DEFAULT_SORT => $this->storedSort()->value,
            self::FIELD_IGNORE_ARTICLES => $this->store->bool(SettingKey::IgnoreArticlesInSort),
            self::FIELD_MAX_FILE_SIZE => (string) self::clamp(
                self::toWholeUnits($this->store->int(SettingKey::MaxFileSize), self::BYTES_PER_MB),
                self::MAX_FILE_SIZE_MIN,
                self::MAX_FILE_SIZE_MAX,
            ),
            self::FIELD_CONNECT_TIMEOUT => (string) self::clamp(
                $this->store->int(SettingKey::PlexConnectTimeout),
                self::TIMEOUT_MIN,
                self::TIMEOUT_MAX,
            ),
            self::FIELD_REQUEST_TIMEOUT => (string) self::clamp(
                $this->store->int(SettingKey::PlexRequestTimeout),
                self::TIMEOUT_MIN,
                self::TIMEOUT_MAX,
            ),
            self::FIELD_REMOVE_OVERLAY_LABEL => $this->store->bool(SettingKey::PlexRemoveOverlayLabel),
            self::FIELD_SESSION_DURATION => (string) self::clamp(
                self::toWholeUnits($this->store->int(SettingKey::SessionDuration), self::SECONDS_PER_DAY),
                self::SESSION_DURATION_MIN,
                self::SESSION_DURATION_MAX,
            ),
            self::FIELD_UPDATE_CHECK => $this->store->bool(SettingKey::UpdateCheckEnabled),
            self::FIELD_EXCLUDED => $this->store->list(SettingKey::ExcludedLibraries),
        ];
    }

    /**
     * Validate a submission into settings to store.
     *
     * `$reportedLibraries` are the library names the connected server reports
     * now. They bound which exclusions this submission may change: see
     * {@see mergeExclusions()}.
     *
     * @param array<string, mixed> $body
     * @param list<string>         $reportedLibraries
     */
    public function submit(array $body, array $reportedLibraries): SettingsSubmission
    {
        $errors = [];

        $title = trim(self::string($body, self::FIELD_SITE_TITLE));
        if ($title === '') {
            $errors[self::FIELD_SITE_TITLE] = 'Enter a site title.';
        } elseif (mb_strlen($title) > self::SITE_TITLE_MAX_LENGTH) {
            $errors[self::FIELD_SITE_TITLE] = sprintf(
                'Keep the site title to %d characters or fewer.',
                self::SITE_TITLE_MAX_LENGTH,
            );
        }

        $perPage = $this->number($body, self::FIELD_PER_PAGE, self::PER_PAGE_MIN, self::PER_PAGE_MAX, $errors);
        $fileSize = $this->number(
            $body,
            self::FIELD_MAX_FILE_SIZE,
            self::MAX_FILE_SIZE_MIN,
            self::MAX_FILE_SIZE_MAX,
            $errors,
        );
        $connect = $this->number($body, self::FIELD_CONNECT_TIMEOUT, self::TIMEOUT_MIN, self::TIMEOUT_MAX, $errors);
        $request = $this->number($body, self::FIELD_REQUEST_TIMEOUT, self::TIMEOUT_MIN, self::TIMEOUT_MAX, $errors);
        $duration = $this->number(
            $body,
            self::FIELD_SESSION_DURATION,
            self::SESSION_DURATION_MIN,
            self::SESSION_DURATION_MAX,
            $errors,
        );

        // Refused rather than quietly defaulted. Bootstrap falls back to A–Z for
        // an unrecognised slug because it has a page to render and no one to ask;
        // a form has someone to ask, and silently storing something other than
        // what was submitted is the behaviour this screen exists to end.
        $sortSlug = self::string($body, self::FIELD_DEFAULT_SORT);
        $sort = SortOrder::fromSlug($sortSlug);
        if ($sort === null) {
            $errors[self::FIELD_DEFAULT_SORT] = 'Choose one of the sort orders offered.';
        }

        $checked = self::strings($body, self::FIELD_EXCLUDED);

        $values = [
            self::FIELD_SITE_TITLE => $title,
            self::FIELD_PER_PAGE => self::string($body, self::FIELD_PER_PAGE),
            self::FIELD_DEFAULT_SORT => $sortSlug,
            self::FIELD_IGNORE_ARTICLES => self::checked($body, self::FIELD_IGNORE_ARTICLES),
            self::FIELD_MAX_FILE_SIZE => self::string($body, self::FIELD_MAX_FILE_SIZE),
            self::FIELD_CONNECT_TIMEOUT => self::string($body, self::FIELD_CONNECT_TIMEOUT),
            self::FIELD_REQUEST_TIMEOUT => self::string($body, self::FIELD_REQUEST_TIMEOUT),
            self::FIELD_REMOVE_OVERLAY_LABEL => self::checked($body, self::FIELD_REMOVE_OVERLAY_LABEL),
            self::FIELD_SESSION_DURATION => self::string($body, self::FIELD_SESSION_DURATION),
            self::FIELD_UPDATE_CHECK => self::checked($body, self::FIELD_UPDATE_CHECK),
            self::FIELD_EXCLUDED => $checked,
        ];

        if ($errors !== [] || $sort === null
            || $perPage === null || $fileSize === null
            || $connect === null || $request === null || $duration === null) {
            return SettingsSubmission::refused($values, $errors);
        }

        return SettingsSubmission::accepted($values, [
            SettingKey::SiteTitle->value => $title,
            SettingKey::ImagesPerPage->value => $perPage,
            SettingKey::DefaultSort->value => $sort->value,
            SettingKey::IgnoreArticlesInSort->value => self::checked($body, self::FIELD_IGNORE_ARTICLES),
            SettingKey::MaxFileSize->value => $fileSize * self::BYTES_PER_MB,
            SettingKey::PlexConnectTimeout->value => $connect,
            SettingKey::PlexRequestTimeout->value => $request,
            SettingKey::PlexRemoveOverlayLabel->value => self::checked($body, self::FIELD_REMOVE_OVERLAY_LABEL),
            SettingKey::SessionDuration->value => $duration * self::SECONDS_PER_DAY,
            SettingKey::UpdateCheckEnabled->value => self::checked($body, self::FIELD_UPDATE_CHECK),
            SettingKey::ExcludedLibraries->value => $this->mergeExclusions(
                $checked,
                $reportedLibraries,
                self::checked($body, self::FIELD_CLEAR_UNREPORTED),
            ),
        ]);
    }

    /**
     * The exclusions to store: the submitted choices, plus every stored name the
     * server did not report.
     *
     * A save replaces exclusions only for libraries that were on offer. Anything
     * else would un-hide a library as a side effect of saving an unrelated
     * field — the worst thing this screen could do, because excluding a library
     * is how a user hides content deliberately, and a renamed, removed, or
     * briefly unreachable library would silently reappear.
     *
     * A checked name is stored with the server's spelling; a preserved one keeps
     * the spelling it already had.
     *
     * `$clearUnreported` is the deliberate way out, so that a preserved entry is
     * removable rather than permanent. It is honoured only when the server
     * actually reported libraries: an unreachable server reports none, which
     * would make every exclusion look stale and turn one tick into "forget every
     * library I ever hid".
     *
     * @param list<string> $checked           library names the form submitted as excluded
     * @param list<string> $reportedLibraries library names the server reports
     * @param bool         $clearUnreported   whether to drop the preserved names rather than keep them
     *
     * @return list<string>
     */
    public function mergeExclusions(array $checked, array $reportedLibraries, bool $clearUnreported = false): array
    {
        $excluded = $clearUnreported && $reportedLibraries !== []
            ? []
            : $this->unreportedExclusions($reportedLibraries);

        foreach ($reportedLibraries as $title) {
            if (in_array($title, $checked, true)) {
                $excluded[] = $title;
            }
        }

        return array_values(array_unique($excluded));
    }

    /**
     * Stored exclusions naming no library the server reports.
     *
     * Shown on the screen so a stale entry is visible and removable rather than
     * invisible and permanent — the cost of preserving them.
     *
     * @param list<string> $reportedLibraries
     *
     * @return list<string>
     */
    public function unreportedExclusions(array $reportedLibraries): array
    {
        $reported = new LibraryExclusions($reportedLibraries);

        $names = [];
        foreach ($this->store->list(SettingKey::ExcludedLibraries) as $stored) {
            if (!$reported->isExcluded($stored)) {
                $names[] = $stored;
            }
        }

        return $names;
    }

    /**
     * The reported libraries, each marked with whether it is excluded.
     *
     * Matching goes through {@see LibraryExclusions} rather than a string
     * comparison here, so what the checkbox shows and what the application acts
     * on are decided by the same rule.
     *
     * @param list<string> $reportedLibraries
     * @param list<string> $excludedNames     the exclusions in effect for this render
     *
     * @return list<array{title: string, excluded: bool}>
     */
    public function libraryChoices(array $reportedLibraries, array $excludedNames): array
    {
        $exclusions = new LibraryExclusions($excludedNames);

        $choices = [];
        foreach ($reportedLibraries as $title) {
            $choices[] = ['title' => $title, 'excluded' => $exclusions->isExcluded($title)];
        }

        return $choices;
    }

    /**
     * The sort orders offered, each with the label the select shows.
     *
     * Built from the enum rather than listed, so an order added there appears
     * here. The label names the field and the direction, because two orders of
     * the same field share a short label ("Date added") and a select cannot
     * lean on an arrow to tell them apart the way the gallery's buttons do.
     *
     * @return list<array{value: string, label: string}>
     */
    public function sortOptions(): array
    {
        $options = [];
        foreach (SortOrder::cases() as $order) {
            $options[] = [
                'value' => $order->value,
                'label' => ucfirst(sprintf('%s, %s', $order->field()->phrase(), $order->directionPhrase())),
            ];
        }

        return $options;
    }

    private function storedSort(): SortOrder
    {
        return SortOrder::fromSlug($this->store->string(SettingKey::DefaultSort)) ?? SortOrder::default();
    }

    /**
     * A whole-number field, or null with a message recorded against it.
     *
     * @param array<string, mixed>  $body
     * @param array<string, string> $errors
     *
     * @param-out array<string, string> $errors
     */
    private function number(array $body, string $field, int $min, int $max, array &$errors): ?int
    {
        $raw = trim(self::string($body, $field));
        if ($raw === '' || preg_match('/^\d+$/', $raw) !== 1) {
            $errors[$field] = sprintf('Enter a whole number between %d and %d.', $min, $max);

            return null;
        }

        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            $errors[$field] = sprintf('Enter a whole number between %d and %d.', $min, $max);

            return null;
        }

        return $value;
    }

    /**
     * A stored value in its display unit, rounded to the nearest whole one.
     *
     * Never to zero: a value small enough to round away is still a value the
     * user set, and showing it as nothing invites saving nothing.
     */
    private static function toWholeUnits(int $value, int $unit): int
    {
        return max(1, (int) round($value / $unit));
    }

    private static function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function string(array $body, string $field): string
    {
        $value = $body[$field] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * An unchecked checkbox submits nothing at all, so presence is the value.
     *
     * @param array<string, mixed> $body
     */
    private static function checked(array $body, string $field): bool
    {
        return isset($body[$field]);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    private static function strings(array $body, string $field): array
    {
        $value = $body[$field] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $entry) {
            if (is_string($entry) && $entry !== '') {
                $items[] = $entry;
            }
        }

        return $items;
    }
}
