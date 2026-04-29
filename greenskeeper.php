<?php
/**
 * Plugin Name: Greenskeeper
 * Description: Manage WordPress updates, filter comment spam, send branded email reports, and configure SMTP delivery — all from one dashboard. Supports single-site and Multisite.
 * Version:     2.1.2
 * Author:      Tony Zeoli
 * Author URI:  https://digitalstrategyworks.com
 * License:     GPL-2.0+
 * Text Domain: greenskeeper
 *
 * Plugin code is licensed under GPL-2.0+ (see License URI above).
 * Documentation, written content, FAQs, setup guides, and all non-code
 * creative content © 2026 Digital Strategy Works LLC. All rights reserved.
 * Unauthorised reproduction of the documentation or written content is
 * prohibited outside the terms of the GPL as it applies to software.
 *
 * @package Greenskeeper
 * @author  Tony Zeoli <tony@digitalstrategyworks.com>
 * @link    https://digitalstrategyworks.com
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WPMM_VERSION',    '2.1.2' );
define( 'WPMM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPMM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Note: load_plugin_textdomain() is included here at the request of the WordPress.org
// plugin review team. Once the plugin is approved and hosted on WordPress.org,
// WordPress will load translations automatically and this call becomes a no-op.
// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
add_action( 'init', function () {
    load_plugin_textdomain( 'greenskeeper', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

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
require_once WPMM_PLUGIN_DIR . 'includes/rest-api.php';
require_once WPMM_PLUGIN_DIR . 'includes/spam-filter.php';

// -- Activation ----------------------------------------------------------------
register_activation_hook( __FILE__, 'wpmm_activate' );
function wpmm_activate( $network_wide = false ) {
    if ( is_multisite() && $network_wide ) {
        foreach ( get_sites( [ 'number' => 0, 'fields' => 'ids' ] ) as $site_id ) {
            switch_to_blog( $site_id );
            wpmm_create_tables();
            wpmm_grant_access_to_admins();
            restore_current_blog();
        }
    } else {
        wpmm_create_tables();
        wpmm_grant_access_to_admins();
    }
    delete_option( 'wpmm_db_version' ); // force upgrade check on next admin load
}

/**
 * Grant wpmm_access to all current administrators on first activation,
 * and to any administrator listed in wpmm_settings['access_user_ids'].
 * Called on activation and on settings save so the cap always matches the
 * saved list.
 */
function wpmm_grant_access_to_admins() {
    $s        = get_option( 'wpmm_settings', [] );
    $saved_ids = isset( $s['access_user_ids'] ) ? array_map( 'absint', $s['access_user_ids'] ) : [];

    // On first activation (no saved list yet), grant access to every current
    // administrator so the plugin is immediately usable after install.
    if ( empty( $saved_ids ) ) {
        $admins = get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] );
        $saved_ids = array_map( 'absint', $admins );
        $s['access_user_ids'] = $saved_ids;
        update_option( 'wpmm_settings', $s );
    }

    // Revoke from everyone first, then re-grant only those in the saved list.
    $all_admins = get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] );
    foreach ( $all_admins as $uid ) {
        $user = get_user_by( 'id', absint( $uid ) );
        if ( $user ) {
            $user->remove_cap( 'wpmm_access' );
        }
    }
    foreach ( $saved_ids as $uid ) {
        $user = get_user_by( 'id', $uid );
        if ( $user && user_can( $user, 'manage_options' ) ) {
            $user->add_cap( 'wpmm_access' );
        }
    }
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

// -- Access control defaults --------------------------------------------------
add_filter( 'wpmm_settings_defaults', 'wpmm_access_settings_defaults' );
function wpmm_access_settings_defaults( $defaults ) {
    return array_merge( $defaults, [
        'access_user_ids' => [], // empty = fall back to manage_options
    ] );
}

// -- 2FA notice ---------------------------------------------------------------
add_action( 'admin_notices', 'wpmm_two_factor_notice' );
function wpmm_two_factor_notice() {
    if ( ! current_user_can( 'wpmm_access' ) && ! current_user_can( 'manage_options' ) ) {
        return;
    }
    // Only show on plugin pages.
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'wpmm' ) === false ) {
        return;
    }
    // Check if any known 2FA plugin is active.
    $two_factor_plugins = [
        'two-factor/two-factor.php',          // Two Factor (official)
        'wp-2fa/wp-2fa.php',                   // WP 2FA by Melapress
        'google-authenticator/google-authenticator.php', // miniOrange
        'wordfence/wordfence.php',              // Wordfence (includes 2FA)
        'ithemes-security-pro/ithemes-security-pro.php', // iThemes Security Pro
    ];
    foreach ( $two_factor_plugins as $plugin ) {
        if ( is_plugin_active( $plugin ) ) {
            return; // A 2FA plugin is active — no notice needed.
        }
    }
    // Dismissible notice.
    if ( get_user_meta( get_current_user_id(), 'wpmm_2fa_notice_dismissed', true ) ) {
        return;
    }
    echo '<div class="notice notice-warning is-dismissible" id="wpmm-2fa-notice">'
       . '<p><strong>Greenskeeper:</strong> No two-factor authentication plugin detected. '
       . 'We recommend installing <a href="' . esc_url( admin_url( 'plugin-install.php?s=wp-2fa&tab=search&type=term' ) ) . '" target="_blank">WP 2FA</a> '
       . 'or <a href="' . esc_url( admin_url( 'plugin-install.php?s=two-factor&tab=search&type=term' ) ) . '" target="_blank">Two Factor</a> '
       . 'to protect the administrator account that manages this plugin. '
       . '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpmm_dismiss_2fa_notice' ), 'wpmm_dismiss_2fa' ) ) . '">Dismiss permanently &rarr;</a></p>'
       . '</div>';
}

add_action( 'admin_post_wpmm_dismiss_2fa_notice', 'wpmm_dismiss_2fa_notice' );
function wpmm_dismiss_2fa_notice() {
    check_admin_referer( 'wpmm_dismiss_2fa' );
    update_user_meta( get_current_user_id(), 'wpmm_2fa_notice_dismissed', 1 );
    wp_safe_redirect( wp_get_referer() ?: admin_url() );
    exit;
}

// -- Context helpers -----------------------------------------------------------
function wpmm_is_network_context() {
    return is_multisite() && is_network_admin();
}

function wpmm_required_cap() {
    // On network admin, always require manage_network.
    // On single-site, use the custom wpmm_access capability so only
    // explicitly authorised administrators can access the plugin.
    // Falls back to manage_options when no user has been granted wpmm_access
    // yet (e.g. immediately after activation, or on legacy installs).
    if ( wpmm_is_network_context() ) {
        return 'manage_network';
    }
    return 'wpmm_access';
}
