<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * The outcome of submitting the settings form: what to store, or what to say.
 *
 * It carries the submitted values either way. A refused submission has to
 * re-render the form holding what the user typed — discarding it to show a
 * banner loses work, and a screen with a dozen fields is enough work to lose.
 */
final class SettingsSubmission
{
    /**
     * @param array<string, string|bool|list<string>> $values   what was submitted, keyed by field name, for re-rendering
     * @param array<string, string>                   $errors   one message per refused field, keyed by field name
     * @param array<string, string|int|bool|list<string>> $settings what to store, keyed by {@see SettingKey} value
     */
    private function __construct(
        public readonly array $values,
        public readonly array $errors,
        public readonly array $settings,
    ) {
    }

    /**
     * @param array<string, string|bool|list<string>>     $values
     * @param array<string, string|int|bool|list<string>> $settings
     */
    public static function accepted(array $values, array $settings): self
    {
        return new self($values, [], $settings);
    }

    /**
     * @param array<string, string|bool|list<string>> $values
     * @param array<string, string>                   $errors
     */
    public static function refused(array $values, array $errors): self
    {
        return new self($values, $errors, []);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
