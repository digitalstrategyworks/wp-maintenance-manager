<?php
/**
 * Settings helpers — read/write the wpmm_settings option.
 * Loaded early so email.php and admin pages can both use wpmm_get_settings().
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Return the full settings array with defaults filled in.
 */
function wpmm_get_settings() {
    $defaults = [
        'company_name'     => '',
        'logo_url'         => '',
        'client_email'     => get_option( 'wpmm_client_email', '' ),
        'default_admin_id' => 0,
    ];
    // Allow other modules (e.g. spam-filter.php) to register their own defaults.
    $defaults = apply_filters( 'wpmm_settings_defaults', $defaults );
    $saved    = get_option( 'wpmm_settings', [] );
    return wp_parse_args( $saved, $defaults );
}

/**
 * Persist the settings array.
 */
function wpmm_save_settings( array $settings ) {
    update_option( 'wpmm_settings', $settings, false );
}

/**
 * Return the WP_User object for the default administrator, or null.
 */
function wpmm_get_default_admin() {
    $s  = wpmm_get_settings();
    $id = absint( $s['default_admin_id'] ?? 0 );
    if ( ! $id ) { return null; }
    $user = get_user_by( 'id', $id );
    return ( $user && user_can( $user, 'manage_options' ) ) ? $user : null;
}
