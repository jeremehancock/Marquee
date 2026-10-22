<?php

declare(strict_types=1);

use App\Version\PhpCurrencyCheck;

/*
 * Run by the scheduled php-currency workflow:
 *
 *   php bin/check-php-currency.php <image PHP_VERSION> <file holding php.net's release JSON>
 *
 * Prints the verdict and exits non-zero when the image is behind or either
 * version could not be read. See PhpCurrencyCheck.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$imageVersion = $argv[1] ?? '';
$releaseFile = $argv[2] ?? '';
$releaseJson = $releaseFile !== '' && is_readable($releaseFile) ? (string) file_get_contents($releaseFile) : '';

$verdict = (new PhpCurrencyCheck())->assess($imageVersion, $releaseJson, new DateTimeImmutable('now'));

fwrite($verdict->passed ? STDOUT : STDERR, $verdict->message . PHP_EOL);
exit($verdict->passed ? 0 : 1);
