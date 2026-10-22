<?php

declare(strict_types=1);

namespace App\Version;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Decides whether the PHP inside a published image has fallen behind upstream.
 *
 * Release tooling rather than application code: the scheduled php-currency
 * workflow runs it, through bin/check-php-currency.php, against the published
 * `latest` image. It exists because PHP once froze inside the image for ten
 * months while every build succeeded, so the one thing it must never do is pass
 * when it does not know. Every input it cannot read is a failure.
 */
final class PhpCurrencyCheck
{
    /**
     * How long upstream has to publish a release into the official PHP image
     * before the image counts as behind. Inside it, a failure would only report
     * a delay nobody can act on.
     */
    public const GRACE_DAYS = 7;

    /**
     * @param string $imageVersion the image's PHP_VERSION, e.g. `8.5.12`
     * @param string $releaseJson  php.net's answer to `releases/index.php?json&version=<major.minor>`
     */
    public function assess(string $imageVersion, string $releaseJson, DateTimeImmutable $today): PhpCurrencyVerdict
    {
        $imageVersion = trim($imageVersion);
        if (preg_match('/^(\d+\.\d+)\.\d+$/', $imageVersion, $imageMatch) !== 1) {
            return PhpCurrencyVerdict::fail(sprintf(
                'Could not read the image\'s PHP version (got "%s").',
                $imageVersion,
            ));
        }

        $release = json_decode($releaseJson, true);
        $latest = is_array($release) && is_string($release['version'] ?? null) ? $release['version'] : '';
        $date = is_array($release) && is_string($release['date'] ?? null) ? $release['date'] : '';
        $releasedOn = DateTimeImmutable::createFromFormat('!j M Y', $date, new DateTimeZone('UTC'));

        if (preg_match('/^(\d+\.\d+)\.\d+$/', $latest, $latestMatch) !== 1 || $releasedOn === false) {
            return PhpCurrencyVerdict::fail('Could not read the newest PHP release from php.net.');
        }

        // A release from another line means the lookup asked the wrong question,
        // not that the image is current or stale.
        if ($latestMatch[1] !== $imageMatch[1]) {
            return PhpCurrencyVerdict::fail(sprintf(
                'php.net answered for PHP %s, but the image runs the %s line.',
                $latestMatch[1],
                $imageMatch[1],
            ));
        }

        if (version_compare($imageVersion, $latest, '>=')) {
            return PhpCurrencyVerdict::pass(sprintf('The image runs PHP %s, the newest %s release.', $imageVersion, $imageMatch[1]));
        }

        $age = (int) $releasedOn->diff($today->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0))->format('%r%a');
        if ($age <= self::GRACE_DAYS) {
            return PhpCurrencyVerdict::pass(sprintf(
                'The image runs PHP %s; %s was released %d day(s) ago, inside the %d-day grace period.',
                $imageVersion,
                $latest,
                $age,
                self::GRACE_DAYS,
            ));
        }

        return PhpCurrencyVerdict::fail(sprintf(
            'The image runs PHP %s but %s was released %d days ago. Cut a patch release so the image is rebuilt.',
            $imageVersion,
            $latest,
            $age,
        ));
    }
}
