<?php

declare(strict_types=1);

namespace Customs;

defined('ABSPATH') || exit;

/**
 * Idempotent schema/version migrations, run on every boot. Compares a stored
 * option against VERSION and applies forward steps as needed.
 */
final class Migrator
{
    private const OPTION   = 'customs_db_version';
    private const SETTINGS = 'customs_settings';

    /**
     * The English strings that shipped as packaged defaults up to 1.0.14 and
     * were written into the option the first time the settings screen was
     * saved.
     *
     * @var array<string, string>
     */
    private const LEGACY_TEXTS = [
        'label' => 'EU import duty (estimate)',
    ];

    public function maybeMigrate(): void
    {
        $current = (string) get_option(self::OPTION, '0');

        if (version_compare($current, VERSION, '>=')) {
            return;
        }

        // No custom tables yet. Settings are stored in the customs_settings
        // option and merged over packaged defaults at read time.
        $this->clearUntranslatableTexts();

        update_option(self::OPTION, VERSION, false);
    }

    /**
     * Clear a stored label that is byte for byte the English default.
     *
     * That value could never be translated: it came from a config array rather
     * than a gettext call, so a shop running in Polish showed English however
     * complete the language pack was. Empty means "use the translated default",
     * which is what the settings screen now says.
     *
     * Only an exact match is cleared, so a merchant's own label, including a
     * hand translation of the English one, survives untouched.
     */
    private function clearUntranslatableTexts(): void
    {
        $stored = get_option(self::SETTINGS, null);
        if (! is_array($stored)) {
            return;
        }

        $changed = false;
        foreach (self::LEGACY_TEXTS as $key => $legacy) {
            if (isset($stored[$key]) && (string) $stored[$key] === $legacy) {
                $stored[$key] = '';
                $changed      = true;
            }
        }

        if ($changed) {
            // null keeps the option's existing autoload flag. Passing false here
            // would quietly move the settings out of the autoloaded set on every
            // shop that took this update, which is not a change a text sweep gets
            // to make.
            update_option(self::SETTINGS, $stored, null);
        }
    }
}
