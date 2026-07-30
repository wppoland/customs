<?php

declare(strict_types=1);

/**
 * Customs uninstall: remove the plugin's options, and the leftover meta from the
 * PRO notice that used to live on the settings screen.
 *
 * @package plogins-customs
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Remove everything Customs wrote on the current site.
 */
function customs_uninstall_site(): void
{
    delete_option('customs_settings');
    delete_option('customs_db_version');

    // Dismissal flag of the PRO notice removed in 1.0.9. Installs that ran an
    // earlier version still carry it on the user who dismissed the notice.
    delete_metadata('user', 0, 'customs_pro_banner_dismissed', '', true);
}

// Each site in a network keeps its own settings.
if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $customs_site_id) {
        switch_to_blog((int) $customs_site_id);
        customs_uninstall_site();
        restore_current_blog();
    }
} else {
    customs_uninstall_site();
}
