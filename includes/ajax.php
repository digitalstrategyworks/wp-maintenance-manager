<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_wpmm_run_update',    'wpmm_ajax_run_update' );
add_action( 'wp_ajax_wpmm_send_email',    'wpmm_ajax_send_email' );
add_action( 'wp_ajax_wpmm_resend_email',  'wpmm_ajax_resend_email' );
add_action( 'wp_ajax_wpmm_get_updates',   'wpmm_ajax_get_updates' );
add_action( 'wp_ajax_wpmm_get_email_body','wpmm_ajax_get_email_body' );
// wpmm_save_settings is registered in admin/settings.php

// ── Shared capability check ───────────────────────────────────────────────────
function wpmm_ajax_cap_check() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( wpmm_required_cap() ) ) {
        wp_send_json_error( 'Permission denied.' );
    }
}

// ── Run a single update ───────────────────────────────────────────────────────
function wpmm_ajax_run_update() {
    wpmm_ajax_cap_check();

    $type       = sanitize_text_field( wp_unslash( $_POST['item_type']  ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via wpmm_ajax_cap_check().
    $slug       = sanitize_text_field( wp_unslash( $_POST['item_slug']  ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $package    = esc_url_raw( wp_unslash( $_POST['package'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $site_id    = absint( $_POST['site_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

    if ( ! $type || ! $slug ) {
        wp_send_json_error( 'Missing parameters.' );
    }

    // Switch to the scoped site before running the update so the update
    // runs in that site's context and logs to its own wpmm_update_log table.
    $switched = false;
    if ( is_multisite() && $site_id > 0 && $site_id !== get_current_blog_id() ) {
        switch_to_blog( $site_id );
        $switched = true;
    }

    $GLOBALS['wpmm_session_id'] = $session_id;

    $result = wpmm_do_update( $type, $slug, $package );

    if ( $switched ) {
        restore_current_blog();
    }

    wp_send_json_success( $result );
}

// ── Send email report ─────────────────────────────────────────────────────────
function wpmm_ajax_send_email() {
    wpmm_ajax_cap_check();

    $to       = sanitize_email( wp_unslash( $_POST['to_email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via wpmm_ajax_cap_check().
    $subject  = sanitize_text_field( wp_unslash( $_POST['subject']  ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $admin_id = absint( $_POST['admin_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

    // Session resolution strategy (in priority order):
    // 1. session_id explicitly posted (same-page flow from Updates page).
    // 2. wpmm_last_session option (cross-page flow: updates → Email Reports).
    // 3. Empty string → fall back to the 100 most recent log entries.
    $posted_session = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
    $last           = get_option( 'wpmm_last_session', [] );

    $session_id = $posted_session;
    $blog_id    = get_current_blog_id();

    if ( ! $session_id && ! empty( $last['session_id'] ) ) {
        $session_id = $last['session_id'];
        $blog_id    = isset( $last['blog_id'] ) ? (int) $last['blog_id'] : get_current_blog_id();
    }

    if ( ! is_email( $to ) ) {
        wp_send_json_error( 'Invalid email address.' );
    }

    global $wpdb;

    // On Multisite, switch to the blog where the updates were actually run
    // so we read from the correct per-site table (e.g. wp_2_wpmm_update_log).
    $switched = false;
    if ( is_multisite() && $blog_id && $blog_id !== get_current_blog_id() ) {
        switch_to_blog( $blog_id );
        $switched = true;
    }

    if ( $session_id ) {
        $log_entries = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT * FROM {$wpdb->prefix}wpmm_update_log WHERE session_id = %s ORDER BY updated_at ASC",
            $session_id
        ) );
    } else {
        // No session at all — use the most recent 100 entries.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $log_entries = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpmm_update_log ORDER BY updated_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            100
        ) );
    }

    if ( $switched ) {
        restore_current_blog();
    }

    // Update note and manual updates added on the Email Reports page.
    $update_note = sanitize_textarea_field( wp_unslash( $_POST['update_note'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

    // Manual updates added on the Email Reports page.
    $manual_raw     = isset( $_POST['manual_entries'] ) ? sanitize_text_field( wp_unslash( $_POST['manual_entries'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $manual_entries = [];
    if ( $manual_raw ) {
        $decoded = json_decode( $manual_raw, true );
        if ( is_array( $decoded ) ) {
            foreach ( $decoded as $entry ) {
                $manual_entries[] = [
                    'name'        => sanitize_text_field( $entry['name']        ?? '' ),
                    'old_version' => sanitize_text_field( $entry['old_version'] ?? '' ),
                    'new_version' => sanitize_text_field( $entry['new_version'] ?? '' ),
                ];
            }
        }
    }

    $body   = wpmm_build_email_body( $log_entries, $admin_id, $manual_entries, $update_note );
    $result = wpmm_send_email( $to, $subject, $body, $admin_id );

    // ── All-Sites network email mode ──────────────────────────────────────────
    // NOTE: This check must come AFTER the single-site send above so that
    // non-network installs always use the path above. On network installs in
    // All-Sites mode, the network email overwrites $result and returns early.
    $network_all = ( isset( $_POST['network_all'] ) && (int) $_POST['network_all'] === 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( is_multisite() && $network_all ) {
        // Gather log entries from every site that had a session recently.
        $sites      = get_sites( [ 'number' => 200, 'fields' => 'ids' ] );
        $sites_data = [];
        foreach ( $sites as $bid ) {
            switch_to_blog( $bid );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( $session_id ) {
                $site_entries = $wpdb->get_results( $wpdb->prepare(
                    'SELECT * FROM ' . esc_sql( $wpdb->prefix . 'wpmm_update_log' ) . ' WHERE session_id = %s ORDER BY updated_at ASC',
                    $session_id
                ) );
            } else {
                $site_entries = $wpdb->get_results( $wpdb->prepare(
                    'SELECT * FROM ' . esc_sql( $wpdb->prefix . 'wpmm_update_log' ) . ' ORDER BY updated_at DESC LIMIT %d',
                    50
                ) );
            }
            if ( ! empty( $site_entries ) ) {
                $sites_data[] = [
                    'blog_id'   => $bid,
                    'site_name' => get_bloginfo( 'name' ),
                    'site_url'  => get_bloginfo( 'url' ),
                    'entries'   => $site_entries,
                ];
            }
            restore_current_blog();
        }
        $body   = wpmm_build_network_email_body( $sites_data, $admin_id, $update_note );
        $result = wpmm_send_email( $to, $subject, $body, $admin_id );
        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => 'Network report sent successfully.', 'email_id' => $result['email_id'] ] );
        } else {
            wp_send_json_error( 'Network email failed to send.' );
        }
        return;
    }

    if ( $result['success'] ) {
        wp_send_json_success( [ 'message' => 'Email sent successfully.', 'email_id' => $result['email_id'] ] );
    } else {
        wp_send_json_error( 'Email failed to send. Check your WordPress mail configuration.' );
    }
}

// ── Resend a previously sent email ────────────────────────────────────────────
function wpmm_ajax_resend_email() {
    wpmm_ajax_cap_check();

    global $wpdb;
    $email_id = absint( $_POST['email_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via wpmm_ajax_cap_check().
    $row      = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        "SELECT * FROM {$wpdb->prefix}wpmm_email_log WHERE id = %d", $email_id
    ) );

    if ( ! $row ) {
        wp_send_json_error( 'Email record not found.' );
    }

    // Rebuild the email body using the current template (not the stale stored HTML)
    // so that resent emails always reflect the latest design changes.
    // Use the stored session_id to fetch the original log entries.
    $body = $row->body; // fallback: use stored body if we can't rebuild
    if ( ! empty( $row->session_id ) ) {
        $log_entries = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT * FROM {$wpdb->prefix}wpmm_update_log
             WHERE session_id = %s ORDER BY updated_at ASC",
            $row->session_id
        ) );
        if ( $log_entries ) {
            $body = wpmm_build_email_body( $log_entries );
        }
    }

    $result = wpmm_send_email( $row->to_email, $row->subject, $body );
    if ( $result['success'] ) {
        wp_send_json_success( 'Email resent successfully.' );
    } else {
        wp_send_json_error( 'Resend failed.' );
    }
}

// ── Retrieve available updates ────────────────────────────────────────────────
function wpmm_ajax_get_updates() {
    wpmm_ajax_cap_check();
    $site_id = absint( $_POST['site_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via wpmm_ajax_cap_check().
    $updates = wpmm_get_available_updates( $site_id );
    wp_send_json_success( $updates );
}

// ── Fetch email body for preview modal ───────────────────────────────────────
// Always rebuilds the body from the original log entries using the current
// template so that preview reflects the latest design, not stale stored HTML.
function wpmm_ajax_get_email_body() {
    wpmm_ajax_cap_check();

    global $wpdb;
    $email_id = absint( $_POST['email_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via wpmm_ajax_cap_check().
    $row      = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        "SELECT * FROM {$wpdb->prefix}wpmm_email_log WHERE id = %d",
        $email_id
    ) );

    if ( ! $row ) {
        wp_send_json_error( 'Email record not found.' );
    }

    // Rebuild body from log entries if we have a session_id stored.
    $body = $row->body;
    if ( ! empty( $row->session_id ) ) {
        $log_entries = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT * FROM {$wpdb->prefix}wpmm_update_log
             WHERE session_id = %s ORDER BY updated_at ASC",
            $row->session_id
        ) );
        if ( ! empty( $log_entries ) ) {
            $body = wpmm_build_email_body( $log_entries );
        }
    }

    wp_send_json_success( [
        'to_email' => $row->to_email,
        'subject'  => $row->subject,
        'body'     => $body,
        'status'   => $row->status,
        'sent_at'  => $row->sent_at,
    ] );
}

// ── Autocomplete: search item names in the log ────────────────────────────────
add_action( 'wp_ajax_wpmm_search_items', 'wpmm_ajax_search_items' );

function wpmm_ajax_search_items() {
    wpmm_ajax_cap_check();

    global $wpdb;
    $term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via wpmm_ajax_cap_check().

    if ( strlen( $term ) < 1 ) {
        wp_send_json_success( [] );
    }

    $results = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        "SELECT DISTINCT item_name
         FROM {$wpdb->prefix}wpmm_update_log
         WHERE item_name LIKE %s
         ORDER BY item_name ASC
         LIMIT 20",
        '%' . $wpdb->esc_like( $term ) . '%'
    ) );

    wp_send_json_success( $results ?: [] );
}
