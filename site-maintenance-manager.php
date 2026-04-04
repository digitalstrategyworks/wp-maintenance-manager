<?php
/**
 * Plugin Name: Site Maintenance Manager
 * Description: Manage WordPress core, plugin, and theme updates with email reporting. Supports single-site and Multisite (network) installs.
 * Version:     1.5.4
 * Author:      Tony Zeoli
 * Author URI:  https://digitalstrategyworks.com
 * License:     GPL-2.0+
 * Text Domain: site-maintenance-manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WPMM_VERSION',    '1.5.4' );
define( 'WPMM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPMM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// -- Includes ------------------------------------------------------------------
require_once WPMM_PLUGIN_DIR . 'includes/db.php';
require_once WPMM_PLUGIN_DIR . 'includes/settings-helpers.php';
require_once WPMM_PLUGIN_DIR . 'includes/error-codes.php';
require_once WPMM_PLUGIN_DIR . 'includes/updates.php';
require_once WPMM_PLUGIN_DIR . 'includes/email.php';
require_once WPMM_PLUGIN_DIR . 'includes/smtp.php';
require_once WPMM_PLUGIN_DIR . 'includes/ajax.php';
require_once WPMM_PLUGIN_DIR . 'admin/admin.php';
require_once WPMM_PLUGIN_DIR . 'admin/settings.php';

// -- Activation ----------------------------------------------------------------
register_activation_hook( __FILE__, 'wpmm_activate' );
function wpmm_activate( $network_wide = false ) {
    if ( is_multisite() && $network_wide ) {
        foreach ( get_sites( [ 'number' => 0, 'fields' => 'ids' ] ) as $site_id ) {
            switch_to_blog( $site_id );
            wpmm_create_tables();
            restore_current_blog();
        }
    } else {
        wpmm_create_tables();
    }
    delete_option( 'wpmm_db_version' ); // force upgrade check on next admin load
}

// -- DB schema upgrade — runs on every admin page load until version matches --
// Uses admin_init so it fires for both regular pages AND admin-ajax.php.
// Deletes the stored version first so a mid-run failure doesn't mark it done.
add_action( 'admin_init', 'wpmm_maybe_upgrade_db' );
function wpmm_maybe_upgrade_db() {
    if ( get_option( 'wpmm_db_version' ) === WPMM_VERSION ) {
        return; // already up to date
    }

    // Clear the stored version BEFORE running so a fatal mid-upgrade doesn't
    // leave the option set to the new version with a half-upgraded schema.
    delete_option( 'wpmm_db_version' );

    if ( is_multisite() ) {
        foreach ( get_sites( [ 'number' => 0, 'fields' => 'ids' ] ) as $site_id ) {
            switch_to_blog( $site_id );
            wpmm_create_tables();
            restore_current_blog();
        }
    } else {
        wpmm_create_tables();
    }

    update_option( 'wpmm_db_version', WPMM_VERSION );
}

// -- Force DB upgrade via AJAX (triggered from diagnostic panel) ---------------
add_action( 'wp_ajax_wpmm_force_db_upgrade', 'wpmm_ajax_force_db_upgrade' );
function wpmm_ajax_force_db_upgrade() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    delete_option( 'wpmm_db_version' ); // force re-run
    wpmm_create_tables();
    update_option( 'wpmm_db_version', WPMM_VERSION );

    $diag = wpmm_db_diagnostic();
    wp_send_json_success( $diag );
}

// -- New site provisioning (Multisite) -----------------------------------------
add_action( 'wp_initialize_site', 'wpmm_new_site_tables', 10, 1 );
function wpmm_new_site_tables( $new_site ) {
    if ( is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
        switch_to_blog( $new_site->id );
        wpmm_create_tables();
        restore_current_blog();
    }
}

// -- Context helpers -----------------------------------------------------------
function wpmm_is_network_context() {
    return is_multisite() && is_network_admin();
}

function wpmm_required_cap() {
    return wpmm_is_network_context() ? 'manage_network' : 'manage_options';
}
