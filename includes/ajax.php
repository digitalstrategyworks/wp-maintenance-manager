<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_wpmm_run_update',    'wpmm_ajax_run_update' );
add_action( 'wp_ajax_wpmm_send_email',    'wpmm_ajax_send_email' );
add_action( 'wp_ajax_wpmm_resend_email',  'wpmm_ajax_resend_email' );
add_action( 'wp_ajax_wpmm_get_updates',   'wpmm_ajax_get_updates' );
add_action( 'wp_ajax_wpmm_get_email_body','wpmm_ajax_get_email_body' );
// wpmm_save_settings is registered in admin/settings.php

// ── Shared capability check ───────────────────────────────────────────────────
/**
 * Verifies the nonce and base capability for all Greenskeeper AJAX actions.
 * For multisite cross-site requests (site_id targets a different blog), also
 * requires super admin / manage_network — a site-level admin must not be able
 * to trigger actions on another site by passing an arbitrary site_id.
 */
function wpmm_ajax_cap_check() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( wpmm_required_cap() ) ) {
        wp_send_json_error( 'Permission denied.' );
    }
}

/**
 * Extended cap check for actions that accept a site_id parameter.
 * Validates that the site exists and — when targeting a different site —
 * that the current user has network-level permission.
 * Returns the validated site ID (0 = current site, no switch needed).
 */
function wpmm_ajax_cap_check_with_site() {
    wpmm_ajax_cap_check();

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in wpmm_ajax_cap_check().
    $site_id = absint( $_POST['site_id'] ?? 0 );

    if ( ! is_multisite() || $site_id === 0 || $site_id === get_current_blog_id() ) {
        return $site_id; // Same-site request — existing capability is sufficient.
    }

    // Cross-site request: verify the site exists.
    $site = get_site( $site_id );
    if ( ! $site ) {
        wp_send_json_error( 'Invalid site ID.' );
    }

    // Cross-site request: require network-level permission.
    if ( ! current_user_can( 'manage_network' ) && ! is_super_admin() ) {
        wp_send_json_error( 'Network administrator permission required to perform actions on another site.' );
    }

    return $site_id;
}

// ── Run a single update ───────────────────────────────────────────────────────
function wpmm_ajax_run_update() {
    // Validates nonce, base capability, AND cross-site permission if site_id
    // targets a different blog. Returns the validated site_id.
    $site_id = wpmm_ajax_cap_check_with_site();

    // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- set_time_limit is required for long-running plugin updates on shared hosting.
    if ( function_exists( 'set_time_limit' ) ) {
        set_time_limit( 300 ); // 5 minutes per individual update.
    }

    // Nonce verified by wpmm_ajax_cap_check_with_site() above — phpcs cannot
    // trace through the function call so suppression is required here.
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $type       = sanitize_text_field( wp_unslash( $_POST['item_type']  ?? '' ) );
    $slug       = sanitize_text_field( wp_unslash( $_POST['item_slug']  ?? '' ) );
    $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
    $package    = esc_url_raw( wp_unslash( $_POST['package']    ?? '' ) );
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    if ( ! $type || ! $slug ) {
        wp_send_json_error( 'Missing parameters.' );
    }

    // ── Active plugin snapshot ────────────────────────────────────────────────
    // IMPORTANT: snapshot must be taken BEFORE switch_to_blog() so we capture
    // the true network state. After switch_to_blog(), get_option('active_plugins')
    // returns the sub-site's plugin list, not the main site's.
    //
    // On multisite, plugins can be active in THREE places:
    //   active_sitewide_plugins — network option (network/sitewide activation)
    //   active_plugins on blog 1 — primary site's per-site activations
    //   active_plugins on blog N — each sub-site's per-site activations
    //
    // CPTUI and similar plugins activated only on a specific sub-site are stored
    // in THAT sub-site's active_plugins option — a completely separate database
    // row. Previous versions only snapshotted blog 1's active_plugins and missed
    // all sub-site-specific activations entirely.
    //
    // We snapshot ALL sites' active_plugins on multisite so any sub-site plugin
    // deactivated as collateral damage can be detected and restored.
    //
    // We use get_site_transient/set_site_transient (network-level) rather than
    // get_transient/set_transient (per-blog) because per-blog transients are
    // stored in the current blog's options table and are lost when blog context
    // switches mid-request via switch_to_blog().
    $snapshot_key = 'wpmm_active_snapshot_' . md5( $session_id ?: 'default' );
    $snapshot     = get_site_transient( $snapshot_key );
    if ( false === $snapshot ) {
        // Build per-site active_plugins map: [ blog_id => [ plugin slugs ] ]
        $per_site_active = [];
        if ( is_multisite() ) {
            try {
                $sites = get_sites( [ 'number' => 200, 'fields' => 'ids' ] );
                foreach ( $sites as $bid ) {
                    switch_to_blog( (int) $bid );
                    $per_site_active[ (int) $bid ] = (array) get_option( 'active_plugins', [] );
                    restore_current_blog();
                }
            } catch ( \Throwable $e ) {
                // If the per-site loop fails on a specific multisite configuration,
                // fall back gracefully — the primary site and sitewide snapshots
                // still provide partial protection.
                restore_current_blog();
                $per_site_active = [];
            }
        } else {
            $per_site_active[1] = (array) get_option( 'active_plugins', [] );
        }

        $snapshot = [
            'active_plugins'          => (array) get_option( 'active_plugins', [] ),
            'active_sitewide_plugins' => is_multisite()
                ? (array) get_site_option( 'active_sitewide_plugins', [] )
                : [],
            'per_site_active'         => $per_site_active,
        ];
        set_site_transient( $snapshot_key, $snapshot, 30 * MINUTE_IN_SECONDS );
    }
    $active_before     = $snapshot['active_plugins'];
    $sitewide_before   = $snapshot['active_sitewide_plugins'];
    $per_site_before   = $snapshot['per_site_active'] ?? [];
    // ─────────────────────────────────────────────────────────────────────────

    $switched = false;
    if ( is_multisite() && $site_id > 0 && $site_id !== get_current_blog_id() ) {
        switch_to_blog( $site_id );
        $switched = true;
    }

    $GLOBALS['wpmm_session_id'] = $session_id;

    $result = wpmm_do_update( $type, $slug, $package );

    // Restore blog context BEFORE reading post-update plugin state.
    // The snapshot was taken in the main site context (before switch_to_blog),
    // so the comparison must also happen in the main site context.
    if ( $switched ) {
        restore_current_blog();
        $switched = false; // Prevent double-restore below.
    }

    // ── Restore collaterally deactivated plugins ──────────────────────────────
    if ( $type === 'plugin' ) {
        $active_after   = (array) get_option( 'active_plugins', [] );
        $sitewide_after = is_multisite()
            ? (array) get_site_option( 'active_sitewide_plugins', [] )
            : [];

        $restored = [];

        // Restore primary site-level plugins.
        $deactivated = array_diff( $active_before, $active_after );
        $to_restore  = array_values( array_filter( $deactivated, function( $p ) use ( $slug ) {
            return $p !== $slug;
        } ) );
        if ( ! empty( $to_restore ) ) {
            $new_active = array_unique( array_merge( $active_after, $to_restore ) );
            sort( $new_active );
            update_option( 'active_plugins', $new_active );
            $restored = array_merge( $restored, $to_restore );
        } else {
            $new_active = $active_after;
        }

        // Restore network-activated plugins (multisite only).
        if ( is_multisite() && ! empty( $sitewide_before ) ) {
            $deactivated_keys = array_diff_key( $sitewide_before, $sitewide_after );
            unset( $deactivated_keys[ $slug ] );
            if ( ! empty( $deactivated_keys ) ) {
                $new_sitewide = array_merge( $sitewide_after, $deactivated_keys );
                update_site_option( 'active_sitewide_plugins', $new_sitewide );
                $restored = array_merge( $restored, array_keys( $deactivated_keys ) );
            } else {
                $new_sitewide = $sitewide_after;
            }
        } else {
            $new_sitewide = $sitewide_after;
        }

        // Restore per-site active plugins across ALL sub-sites.
        $new_per_site = $per_site_before;
        if ( is_multisite() && ! empty( $per_site_before ) ) {
            try {
                foreach ( $per_site_before as $bid => $plugins_before ) {
                    switch_to_blog( (int) $bid );
                    $plugins_after = (array) get_option( 'active_plugins', [] );
                    restore_current_blog();

                    $site_deactivated = array_diff( $plugins_before, $plugins_after );
                    $site_to_restore  = array_values( array_filter(
                        $site_deactivated,
                        function( $p ) use ( $slug ) { return $p !== $slug; }
                    ) );

                    if ( ! empty( $site_to_restore ) ) {
                        $new_site_active = array_unique( array_merge( $plugins_after, $site_to_restore ) );
                        sort( $new_site_active );
                        switch_to_blog( (int) $bid );
                        update_option( 'active_plugins', $new_site_active );
                        restore_current_blog();
                        $new_per_site[ (int) $bid ] = $new_site_active;
                        $restored = array_merge( $restored, array_map( function( $p ) use ( $bid ) {
                            return 'blog:' . $bid . ':' . $p;
                        }, $site_to_restore ) );
                    }
                }
            } catch ( \Throwable $e ) {
                restore_current_blog();
            }
        }

        if ( ! empty( $restored ) ) {
            set_site_transient( $snapshot_key, [
                'active_plugins'          => $new_active,
                'active_sitewide_plugins' => $new_sitewide,
                'per_site_active'         => $new_per_site,
            ], 30 * MINUTE_IN_SECONDS );
            // Format restored list for UI display — strip blog: prefix for readability.
            $result['collateral_restored'] = array_map( function( $p ) {
                return preg_replace( '/^blog:\d+:/', '', $p );
            }, $restored );
        }
    }
    // ─────────────────────────────────────────────────────────────────────────

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

    // ── Session resolution strategy ──────────────────────────────────────────
    // Priority order:
    // 1. session_id explicitly posted (same-page flow from Updates page) →
    //    use that single session only.
    // 2. wpmm_pending_sessions option (cross-page flow) → fetch ALL unsent
    //    sessions so plugins updated in one session and themes in another
    //    both appear in the same email report.
    // 3. wpmm_last_session fallback for backward compatibility.
    // 4. Empty → fall back to the 100 most recent log entries.
    $posted_session = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.

    if ( ! is_email( $to ) ) {
        wp_send_json_error( 'Invalid email address.' );
    }

    global $wpdb;

    if ( $posted_session ) {
        // Explicit single-session send (from the Updates page send button).
        $last    = get_option( 'wpmm_last_session', [] );
        $blog_id = isset( $last['blog_id'] ) ? (int) $last['blog_id'] : get_current_blog_id();

        $switched = false;
        if ( is_multisite() && $blog_id && $blog_id !== get_current_blog_id() ) {
            switch_to_blog( $blog_id );
            $switched = true;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $log_entries = $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . esc_sql( $wpdb->prefix . 'wpmm_update_log' ) . ' WHERE session_id = %s ORDER BY updated_at ASC',
            $posted_session
        ) );

        if ( $switched ) { restore_current_blog(); }

    } else {
        // Cross-page flow — fetch ALL pending (unsent) sessions.
        $pending = get_option( 'wpmm_pending_sessions', [] );

        if ( ! empty( $pending ) ) {
            // Collect entries from every pending session, grouped by blog.
            $log_entries = [];
            $seen_blogs  = [];

            foreach ( $pending as $p ) {
                $sid     = isset( $p['session_id'] ) ? $p['session_id'] : '';
                $blog_id = isset( $p['blog_id'] )    ? (int) $p['blog_id'] : get_current_blog_id();
                if ( ! $sid ) { continue; }

                $switched = false;
                if ( is_multisite() && $blog_id && $blog_id !== get_current_blog_id() ) {
                    switch_to_blog( $blog_id );
                    $switched = true;
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $rows = $wpdb->get_results( $wpdb->prepare(
                    'SELECT * FROM ' . esc_sql( $wpdb->prefix . 'wpmm_update_log' ) . ' WHERE session_id = %s ORDER BY updated_at ASC',
                    $sid
                ) );

                if ( $switched ) { restore_current_blog(); }

                $log_entries = array_merge( $log_entries, $rows ?: [] );
            }

            // Sort merged entries chronologically.
            usort( $log_entries, function( $a, $b ) {
                return strcmp( $a->updated_at, $b->updated_at );
            } );

        } else {
            // No pending sessions — try wpmm_last_session for backward compat.
            $last    = get_option( 'wpmm_last_session', [] );
            $blog_id = isset( $last['blog_id'] ) ? (int) $last['blog_id'] : get_current_blog_id();

            $switched = false;
            if ( is_multisite() && $blog_id && $blog_id !== get_current_blog_id() ) {
                switch_to_blog( $blog_id );
                $switched = true;
            }

            if ( ! empty( $last['session_id'] ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $log_entries = $wpdb->get_results( $wpdb->prepare(
                    'SELECT * FROM ' . esc_sql( $wpdb->prefix . 'wpmm_update_log' ) . ' WHERE session_id = %s ORDER BY updated_at ASC',
                    $last['session_id']
                ) );
            } else {
                // No session at all — use most recent 100 entries.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $log_entries = $wpdb->get_results( $wpdb->prepare(
                    'SELECT * FROM ' . esc_sql( $wpdb->prefix . 'wpmm_update_log' ) . ' ORDER BY updated_at DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    100
                ) );
            }

            if ( $switched ) { restore_current_blog(); }
        }
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

    // ── All-Sites network email mode — checked FIRST to avoid stale single-site send ──
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
    $network_all = ( isset( $_POST['network_all'] ) && (int) $_POST['network_all'] === 1 );
    if ( is_multisite() && $network_all ) {
        $sites            = get_sites( [ 'number' => 200, 'fields' => 'ids' ] );
        $sites_data       = [];
        $pending_sessions = get_option( 'wpmm_pending_sessions', [] );
        foreach ( $sites as $bid ) {
            switch_to_blog( $bid );
            $blog_sessions = array_filter( $pending_sessions, function( $p ) use ( $bid ) {
                return isset( $p['blog_id'] ) && (int) $p['blog_id'] === (int) $bid;
            } );
            if ( ! empty( $blog_sessions ) ) {
                $site_entries = [];
                foreach ( $blog_sessions as $bp ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $rows = $wpdb->get_results( $wpdb->prepare(
                        'SELECT * FROM ' . esc_sql( $wpdb->prefix . 'wpmm_update_log' ) . ' WHERE session_id = %s ORDER BY updated_at ASC',
                        $bp['session_id']
                    ) );
                    $site_entries = array_merge( $site_entries, $rows ?: [] );
                }
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
            delete_option( 'wpmm_pending_sessions' );
            wp_send_json_success( [ 'message' => 'Network report sent successfully.', 'email_id' => $result['email_id'] ] );
        } else {
            wp_send_json_error( 'Network email failed to send.' );
        }
        return; // Never fall through to single-site send.
    }

    // ── Single-site email ─────────────────────────────────────────────────────
    $body   = wpmm_build_email_body( $log_entries, $admin_id, $manual_entries, $update_note );
    $result = wpmm_send_email( $to, $subject, $body, $admin_id );

    if ( $result['success'] ) {
        // Clear the pending sessions list now that the email has been sent.
        // This ensures the next batch of updates starts a fresh accumulation.
        if ( ! $posted_session ) {
            delete_option( 'wpmm_pending_sessions' );
        }

        // Return enough data for the JS to prepend a new row to the history
        // table immediately without a page reload.
        wp_send_json_success( [
            'message'    => 'Email sent successfully.',
            'email_id'   => $result['email_id'],
            'row'        => [
                'id'      => $result['email_id'],
                'sent_at' => current_time( 'mysql' ),
                'to'      => $to,
                'subject' => $subject,
                'status'  => 'sent',
            ],
        ] );
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
    $site_id = wpmm_ajax_cap_check_with_site();
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
