<?php
/**
 * REST API — Site Maintenance Manager spoke endpoints.
 *
 * Registers a set of REST API endpoints under the smm/v1 namespace so that
 * a remote hub site (e.g. an agency dashboard) can manage this site's
 * WordPress core, plugin, and theme updates without requiring a WordPress
 * admin login on this site.
 *
 * All endpoints are authenticated with a shared secret API key stored in
 * wpmm_settings['api_key']. The hub sends the key as the X-SMM-API-Key
 * HTTP header on every request.
 *
 * Endpoint map:
 *
 *  GET  /wp-json/smm/v1/status          — site health snapshot
 *  GET  /wp-json/smm/v1/updates         — available updates (forces fresh scan)
 *  POST /wp-json/smm/v1/update          — run a single update
 *  GET  /wp-json/smm/v1/log             — paginated update log
 *  POST /wp-json/smm/v1/send-report     — send the maintenance email report
 *  POST /wp-json/smm/v1/rotate-key      — generate and store a new API key
 *
 * Authentication header:
 *  X-SMM-API-Key: <key>
 *
 * All responses follow the envelope:
 *  { "success": true|false, "data": {...} }
 *  HTTP 200 on success, 401 on auth failure, 400 on bad input, 500 on error.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Register routes ───────────────────────────────────────────────────────────
add_action( 'rest_api_init', 'wpmm_register_rest_routes' );

function wpmm_register_rest_routes() {
    $ns = 'smm/v1';

    register_rest_route( $ns, '/status', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'wpmm_rest_status',
        'permission_callback' => 'wpmm_rest_auth',
    ] );

    register_rest_route( $ns, '/updates', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'wpmm_rest_get_updates',
        'permission_callback' => 'wpmm_rest_auth',
    ] );

    register_rest_route( $ns, '/update', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'wpmm_rest_run_update',
        'permission_callback' => 'wpmm_rest_auth',
        'args'                => [
            'type' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function( $v ) {
                    return in_array( $v, [ 'core', 'plugin', 'theme' ], true );
                },
            ],
            'slug' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'package' => [
                'required'          => false,
                'default'           => '',
                'sanitize_callback' => 'esc_url_raw',
            ],
            'session_id' => [
                'required'          => false,
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ] );

    register_rest_route( $ns, '/log', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'wpmm_rest_get_log',
        'permission_callback' => 'wpmm_rest_auth',
        'args'                => [
            'per_page' => [
                'required'          => false,
                'default'           => 20,
                'sanitize_callback' => 'absint',
                'validate_callback' => function( $v ) { return $v > 0 && $v <= 200; },
            ],
            'page' => [
                'required'          => false,
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'session_id' => [
                'required'          => false,
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ] );

    register_rest_route( $ns, '/send-report', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'wpmm_rest_send_report',
        'permission_callback' => 'wpmm_rest_auth',
        'args'                => [
            'to_email' => [
                'required'          => false,
                'default'           => '',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => function( $v ) {
                    return empty( $v ) || is_email( $v );
                },
            ],
            'subject' => [
                'required'          => false,
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'session_id' => [
                'required'          => false,
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'update_note' => [
                'required'          => false,
                'default'           => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
        ],
    ] );

    register_rest_route( $ns, '/rotate-key', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'wpmm_rest_rotate_key',
        'permission_callback' => 'wpmm_rest_auth',
    ] );
}

// ── Authentication ─────────────────────────────────────────────────────────────
/**
 * Shared permission callback — validates the X-SMM-API-Key header.
 * Returns true to allow, WP_Error to deny.
 */
function wpmm_rest_auth( WP_REST_Request $request ) {
    $stored_key = wpmm_get_api_key();

    // No key configured — API access disabled.
    if ( empty( $stored_key ) ) {
        return new WP_Error(
            'smm_api_disabled',
            'Remote API access is not enabled. Generate an API key in Site Maintenance Manager → Settings.',
            [ 'status' => 401 ]
        );
    }

    $sent_key = $request->get_header( 'X-SMM-API-Key' );

    if ( empty( $sent_key ) || ! hash_equals( $stored_key, $sent_key ) ) {
        return new WP_Error(
            'smm_unauthorized',
            'Invalid or missing API key.',
            [ 'status' => 401 ]
        );
    }

    return true;
}

// ── Key helpers ───────────────────────────────────────────────────────────────
function wpmm_get_api_key() {
    $s = wpmm_get_settings();
    return $s['api_key'] ?? '';
}

function wpmm_generate_api_key() {
    // 32 bytes = 64 hex chars — cryptographically random.
    return bin2hex( random_bytes( 32 ) );
}

// ── Endpoint: GET /status ─────────────────────────────────────────────────────
/**
 * Returns a health/status snapshot of this site.
 * The hub uses this to populate its site list dashboard.
 */
function wpmm_rest_status( WP_REST_Request $request ) {
    global $wp_version, $wpdb;

    // Count available updates without forcing a rescan (uses cached transients).
    $update_plugins  = get_site_transient( 'update_plugins' );
    $update_themes   = get_site_transient( 'update_themes' );
    $update_core     = get_site_transient( 'update_core' );

    $plugin_count = ! empty( $update_plugins->response ) ? count( $update_plugins->response ) : 0;
    $theme_count  = ! empty( $update_themes->response )  ? count( $update_themes->response )  : 0;
    $core_update  = false;
    if ( ! empty( $update_core->updates ) ) {
        foreach ( $update_core->updates as $u ) {
            if ( isset( $u->response ) && $u->response === 'upgrade' ) {
                $core_update = $u->version;
                break;
            }
        }
    }

    // Most recent update session from log.
    $log_table = $wpdb->prefix . 'wpmm_update_log';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $last = $wpdb->get_row( "SELECT updated_at, COUNT(*) AS total FROM {$log_table} ORDER BY updated_at DESC LIMIT 1" );

    $s = wpmm_get_settings();

    return rest_ensure_response( [
        'success' => true,
        'data'    => [
            'site_name'        => get_bloginfo( 'name' ),
            'site_url'         => get_bloginfo( 'url' ),
            'wp_version'       => $wp_version,
            'php_version'      => PHP_VERSION,
            'smm_version'      => WPMM_VERSION,
            'updates_available' => [
                'core'    => $core_update ? [ 'available' => true, 'version' => $core_update ] : [ 'available' => false ],
                'plugins' => $plugin_count,
                'themes'  => $theme_count,
                'total'   => ( $core_update ? 1 : 0 ) + $plugin_count + $theme_count,
            ],
            'last_update'      => $last ? $last->updated_at : null,
            'client_email'     => $s['client_email'] ?? '',
            'timezone'         => wp_timezone_string(),
            'timestamp'        => current_time( 'c' ), // ISO 8601
        ],
    ] );
}

// ── Endpoint: GET /updates ────────────────────────────────────────────────────
/**
 * Forces a fresh update scan and returns all available updates.
 * This is intentionally slow — it hits WordPress.org API.
 * Hub should call this deliberately, not on every poll.
 */
function wpmm_rest_get_updates( WP_REST_Request $request ) {
    if ( ! function_exists( 'wpmm_get_available_updates' ) ) {
        return new WP_Error( 'smm_missing_function', 'Update scanner unavailable.', [ 'status' => 500 ] );
    }

    $updates = wpmm_get_available_updates();

    return rest_ensure_response( [
        'success' => true,
        'data'    => $updates,
    ] );
}

// ── Endpoint: POST /update ────────────────────────────────────────────────────
/**
 * Runs a single update on the spoke site.
 *
 * Request body (JSON):
 *  { "type": "plugin|theme|core", "slug": "...", "package": "...", "session_id": "..." }
 */
function wpmm_rest_run_update( WP_REST_Request $request ) {
    $type       = $request->get_param( 'type' );
    $slug       = $request->get_param( 'slug' );
    $package    = $request->get_param( 'package' );
    $session_id = $request->get_param( 'session_id' );

    // Inject session_id into the global so wpmm_do_update() picks it up.
    if ( $session_id ) {
        $GLOBALS['wpmm_session_id'] = $session_id;
    }

    if ( ! function_exists( 'wpmm_do_update' ) ) {
        return new WP_Error( 'smm_missing_function', 'Update runner unavailable.', [ 'status' => 500 ] );
    }

    $result = wpmm_do_update( $type, $slug, $package );

    return rest_ensure_response( [
        'success' => $result['status'] === 'success',
        'data'    => $result,
    ] );
}

// ── Endpoint: GET /log ────────────────────────────────────────────────────────
/**
 * Returns paginated update log entries.
 *
 * Query params: per_page, page, session_id
 */
function wpmm_rest_get_log( WP_REST_Request $request ) {
    global $wpdb;

    $per_page   = $request->get_param( 'per_page' );
    $page       = max( 1, $request->get_param( 'page' ) );
    $session_id = $request->get_param( 'session_id' );
    $offset     = ( $page - 1 ) * $per_page;
    $log_table  = $wpdb->prefix . 'wpmm_update_log';

    if ( $session_id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$log_table} WHERE session_id = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $session_id, $per_page, $offset
        ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$log_table} WHERE session_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $session_id
        ) );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$log_table} ORDER BY updated_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $per_page, $offset
        ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    return rest_ensure_response( [
        'success' => true,
        'data'    => [
            'entries'    => $rows,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        ],
    ] );
}

// ── Endpoint: POST /send-report ───────────────────────────────────────────────
/**
 * Builds and sends the HTML maintenance report email.
 *
 * Request body (JSON):
 *  { "to_email": "...", "subject": "...", "session_id": "...", "update_note": "..." }
 *
 * Falls back to saved client_email and last session if params are empty.
 */
function wpmm_rest_send_report( WP_REST_Request $request ) {
    global $wpdb;

    $s          = wpmm_get_settings();
    $to         = $request->get_param( 'to_email' ) ?: ( $s['client_email'] ?? '' );
    $subject    = $request->get_param( 'subject'  ) ?: ( get_bloginfo( 'name' ) . ' — Weekly WordPress Maintenance Report' );
    $session_id = $request->get_param( 'session_id' );
    $note       = $request->get_param( 'update_note' );

    // Fall back to last persisted session if none provided.
    if ( ! $session_id ) {
        $last       = get_option( 'wpmm_last_session', [] );
        $session_id = $last['session_id'] ?? '';
    }

    if ( ! is_email( $to ) ) {
        return new WP_Error( 'smm_invalid_email', 'No valid recipient email address.', [ 'status' => 400 ] );
    }

    // Fetch log entries.
    $log_table = $wpdb->prefix . 'wpmm_update_log';
    if ( $session_id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $log_entries = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$log_table} WHERE session_id = %s ORDER BY updated_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $session_id
        ) );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $log_entries = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT * FROM {$log_table} ORDER BY updated_at DESC LIMIT 100"
        );
    }

    $body   = wpmm_build_email_body( $log_entries, 0, [], $note );
    $result = wpmm_send_email( $to, $subject, $body );

    if ( ! $result['success'] ) {
        return new WP_Error( 'smm_send_failed', 'Email failed to send. Check SMTP configuration.', [ 'status' => 500 ] );
    }

    return rest_ensure_response( [
        'success' => true,
        'data'    => [
            'message'  => 'Report sent successfully to ' . $to . '.',
            'email_id' => $result['email_id'],
        ],
    ] );
}

// ── Endpoint: POST /rotate-key ────────────────────────────────────────────────
/**
 * Generates a new API key, stores it, and returns it.
 * The hub must immediately update its stored key for this site.
 * After rotation the old key is immediately invalid.
 */
function wpmm_rest_rotate_key( WP_REST_Request $request ) {
    $new_key = wpmm_generate_api_key();
    $s       = wpmm_get_settings();
    $s['api_key'] = $new_key;
    wpmm_save_settings( $s );

    return rest_ensure_response( [
        'success' => true,
        'data'    => [
            'api_key' => $new_key,
            'message' => 'API key rotated. Update this key in your hub site immediately — the previous key is no longer valid.',
        ],
    ] );
}
