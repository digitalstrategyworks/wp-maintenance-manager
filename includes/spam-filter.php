<?php
/**
 * Spam Filter — Greenskeeper
 *
 * Provides two layers of comment spam protection:
 *
 *  Layer 1 — Local filtering (always active when spam filtering is enabled)
 *    • Honeypot hidden field — catches bots that fill every field
 *    • Minimum submission time — catches bots that submit instantly
 *    • Maximum links per comment — catches link-spam bots
 *    • Keyword blocklist — configurable list of banned phrases
 *    • IP blocklist — configurable list of banned IP addresses
 *    • Duplicate comment detection — catches repeat-submission bots
 *
 *  Layer 2 — Akismet API (active only when an API key is configured)
 *    • Skipped automatically when the standalone Akismet plugin is active
 *    • Skipped silently when no key is saved
 *
 *  Disable comments — optionally removes comment support from all post
 *  types and hides comment-related admin UI elements entirely.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// Settings defaults (merged into wpmm_get_settings())
// =========================================================================
add_filter( 'wpmm_settings_defaults', 'wpmm_spam_settings_defaults' );
function wpmm_spam_settings_defaults( $defaults ) {
    return array_merge( $defaults, [
        'spam_filter_enabled'  => 0,       // master switch
        'comments_disabled'    => 0,       // disable all comments site-wide
        'spam_min_time'        => 5,       // seconds
        'spam_max_links'       => 3,       // links per comment
        'spam_keywords'        => '',      // newline-separated
        'spam_ip_blocklist'    => '',      // newline-separated
        'akismet_key'          => '',      // Akismet API key
    ] );
}

// =========================================================================
// Disable comments — runs on init when the option is active
// =========================================================================
add_action( 'init', 'wpmm_maybe_disable_comments' );
function wpmm_maybe_disable_comments() {
    $s = wpmm_get_settings();
    if ( empty( $s['comments_disabled'] ) ) {
        return;
    }

    // Remove comment support from every post type that has it.
    foreach ( get_post_types() as $post_type ) {
        if ( post_type_supports( $post_type, 'comments' ) ) {
            remove_post_type_support( $post_type, 'comments' );
            remove_post_type_support( $post_type, 'trackbacks' );
        }
    }

    // Close comments on all existing posts.
    add_filter( 'comments_open',    '__return_false', 20, 2 );
    add_filter( 'pings_open',       '__return_false', 20, 2 );

    // Hide comment counts in the admin bar.
    add_action( 'wp_before_admin_bar_render', function () {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu( 'comments' );
    } );

    // Hide the Comments menu item in wp-admin.
    add_action( 'admin_menu', function () {
        remove_menu_page( 'edit-comments.php' );
    }, 999 );

    // Redirect direct attempts to reach the comments admin page.
    add_action( 'admin_init', function () {
        global $pagenow;
        if ( $pagenow === 'edit-comments.php' ) {
            wp_safe_redirect( admin_url() );
            exit;
        }
    } );

    // Remove the Discussion meta box from post/page edit screens.
    add_action( 'admin_init', function () {
        remove_meta_box( 'commentstatusdiv', 'post', 'normal' );
        remove_meta_box( 'commentstatusdiv', 'page', 'normal' );
        remove_meta_box( 'commentsdiv',      'post', 'normal' );
        remove_meta_box( 'commentsdiv',      'page', 'normal' );
        foreach ( get_post_types() as $pt ) {
            remove_meta_box( 'commentstatusdiv', $pt, 'normal' );
            remove_meta_box( 'commentsdiv',      $pt, 'normal' );
        }
    } );

    // Remove comments from the Dashboard "At a Glance" widget.
    add_filter( 'dashboard_glance_items', function ( $items ) {
        foreach ( $items as $key => $item ) {
            if ( strpos( $item, 'comment' ) !== false ) {
                unset( $items[ $key ] );
            }
        }
        return $items;
    } );
}

// =========================================================================
// Honeypot — inject hidden field into comment form
// =========================================================================
add_action( 'comment_form_after_fields', 'wpmm_honeypot_field' );
add_action( 'comment_form_logged_in_after', 'wpmm_honeypot_field' );
function wpmm_honeypot_field() {
    $s = wpmm_get_settings();
    if ( empty( $s['spam_filter_enabled'] ) || ! empty( $s['comments_disabled'] ) ) {
        return;
    }
    // Visually hidden but reachable by bots. Real users never see or fill it.
    echo '<p style="display:none!important;" aria-hidden="true">'
       . '<label for="wpmm_website_url">Website URL</label>'
       . '<input type="text" id="wpmm_website_url" name="wpmm_website_url" '
       . 'tabindex="-1" autocomplete="off" value="">'
       . '</p>';
}

// =========================================================================
// Pre-process comment — runs before WordPress saves the comment
// =========================================================================
add_filter( 'preprocess_comment', 'wpmm_filter_comment', 1 );
function wpmm_filter_comment( $comment_data ) {
    $s = wpmm_get_settings();

    // Master switch — do nothing if spam filtering is off.
    if ( empty( $s['spam_filter_enabled'] ) ) {
        return $comment_data;
    }

    // Administrators bypass the filter entirely.
    if ( current_user_can( 'manage_options' ) ) {
        return $comment_data;
    }

    // ── Layer 1: Local checks ──────────────────────────────────────────────

    // 1a. Honeypot — bot filled the hidden field.
    if ( ! empty( $_POST['wpmm_website_url'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wpmm_spam_die( 'honeypot', $comment_data );
    }

    // 1b. Submission time — comment submitted impossibly fast.
    $min_time = absint( $s['spam_min_time'] ?? 5 );
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- floatval() sanitizes the numeric timestamp
    if ( $min_time > 0 && isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ) {
        // WordPress sets WPLANG cookie on page load; we use comment_post_ID
        // submit time baked into the nonce as a rough proxy. Since we can't
        // easily know the page-load time, we use a _wpmm_ts hidden field.
        $ts_submitted = absint( $_POST['wpmm_ts'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( $ts_submitted > 0 ) {
            $elapsed = time() - $ts_submitted;
            if ( $elapsed < $min_time ) {
                wpmm_spam_die( 'too_fast', $comment_data );
            }
        }
    }

    $content = $comment_data['comment_content'] ?? '';
    $author  = $comment_data['comment_author'] ?? '';
    $email   = $comment_data['comment_author_email'] ?? '';
    $url     = $comment_data['comment_author_url'] ?? '';
    $ip      = $comment_data['comment_author_IP'] ?? '';

    // 1c. IP blocklist.
    $ip_list = wpmm_parse_list( $s['spam_ip_blocklist'] ?? '' );
    if ( $ip && in_array( $ip, $ip_list, true ) ) {
        wpmm_spam_die( 'blocked_ip', $comment_data );
    }

    // 1d. Keyword blocklist — checks content, author name, and URL.
    $keywords = wpmm_parse_list( $s['spam_keywords'] ?? '' );
    $haystack = strtolower( $content . ' ' . $author . ' ' . $url );
    foreach ( $keywords as $kw ) {
        if ( $kw !== '' && strpos( $haystack, strtolower( $kw ) ) !== false ) {
            wpmm_spam_die( 'keyword', $comment_data );
        }
    }

    // 1e. Link count.
    $max_links = absint( $s['spam_max_links'] ?? 3 );
    if ( $max_links > 0 ) {
        $link_count = substr_count( strtolower( $content ), 'http' );
        if ( $link_count > $max_links ) {
            wpmm_spam_die( 'too_many_links', $comment_data );
        }
    }

    // 1f. Duplicate comment detection — same content from same IP recently.
    if ( $content && $ip ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $dupe = $wpdb->get_var( $wpdb->prepare(
            "SELECT comment_ID FROM {$wpdb->comments}
             WHERE comment_author_IP = %s
               AND comment_content   = %s
               AND comment_date_gmt  > DATE_SUB( NOW(), INTERVAL 1 HOUR )
             LIMIT 1",
            $ip, $content
        ) );
        if ( $dupe ) {
            wpmm_spam_die( 'duplicate', $comment_data );
        }
    }

    // ── Layer 2: Akismet API ───────────────────────────────────────────────
    // Skip entirely if the standalone Akismet plugin is already active —
    // it hooks into the same flow and we don't want to double-check.
    if ( ! defined( 'AKISMET_VERSION' ) ) {
        $akismet_key = trim( $s['akismet_key'] ?? '' );
        if ( $akismet_key ) {
            $is_spam = wpmm_akismet_check( $akismet_key, $comment_data );
            if ( $is_spam ) {
                // Log to our spam log AND mark as spam in WordPress
                // so it appears both in our Spam Log page and in Comments → Spam.
                wpmm_log_spam( 'akismet', $comment_data );
                $comment_data['comment_approved'] = 'spam';
            }
        }
    }

    return $comment_data;
}

// =========================================================================
// Timestamp field — inject into comment form so we can measure time-to-submit
// =========================================================================
add_action( 'comment_form_before', 'wpmm_inject_timestamp_field' );
function wpmm_inject_timestamp_field() {
    $s = wpmm_get_settings();
    if ( empty( $s['spam_filter_enabled'] ) || ! empty( $s['comments_disabled'] ) ) {
        return;
    }
    echo '<input type="hidden" name="wpmm_ts" value="' . esc_attr( time() ) . '">';
}

// =========================================================================
// Akismet API call
// =========================================================================
function wpmm_akismet_check( $api_key, $comment_data ) {
    $site_url = get_bloginfo( 'url' );
    $body     = [
        'blog'                 => $site_url,
        'user_ip'              => $comment_data['comment_author_IP']    ?? '',
        // $_SERVER values can be manipulated by the request sender and must
        // be sanitized before use, even though they are not user form input.
        'user_agent'           => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
        'referrer'             => sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER']    ?? '' ) ),
        'permalink'            => get_permalink( $comment_data['comment_post_ID'] ?? 0 ),
        'comment_type'         => $comment_data['comment_type']         ?? 'comment',
        'comment_author'       => $comment_data['comment_author']       ?? '',
        'comment_author_email' => $comment_data['comment_author_email'] ?? '',
        'comment_author_url'   => $comment_data['comment_author_url']   ?? '',
        'comment_content'      => $comment_data['comment_content']      ?? '',
    ];

    $response = wp_remote_post(
        'https://' . $api_key . '.rest.akismet.com/1.1/comment-check',
        [
            'body'      => $body,
            'timeout'   => 10,
            'headers'   => [
                'User-Agent' => 'Greenskeeper/' . WPMM_VERSION . ' | Akismet/WP',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        // If Akismet is unreachable, fail open — allow the comment through.
        return false;
    }

    $result = trim( wp_remote_retrieve_body( $response ) );
    return $result === 'true';
}

// =========================================================================
// Verify Akismet key — called via AJAX from the Settings page
// =========================================================================
add_action( 'wp_ajax_wpmm_verify_akismet_key', 'wpmm_ajax_verify_akismet_key' );
function wpmm_ajax_verify_akismet_key() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $key = sanitize_text_field( wp_unslash( $_POST['akismet_key'] ?? '' ) );
    if ( ! $key ) {
        wp_send_json_error( 'No key provided.' );
    }

    $response = wp_remote_post(
        'https://rest.akismet.com/1.1/verify-key',
        [
            'body'    => [ 'key' => $key, 'blog' => get_bloginfo( 'url' ) ],
            'timeout' => 10,
        ]
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Could not reach Akismet servers. Check your connection.' );
    }

    $body = trim( wp_remote_retrieve_body( $response ) );
    if ( $body === 'valid' ) {
        // Save the key
        $s = wpmm_get_settings();
        $s['akismet_key'] = $key;
        wpmm_save_settings( $s );
        wp_send_json_success( [ 'message' => 'API key verified and saved.' ] );
    } else {
        wp_send_json_error( 'Invalid API key. Please check and try again.' );
    }
}

// =========================================================================
// Revoke Akismet key — called via AJAX from the Settings page
// =========================================================================
add_action( 'wp_ajax_wpmm_revoke_akismet_key', 'wpmm_ajax_revoke_akismet_key' );
function wpmm_ajax_revoke_akismet_key() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }
    $s = wpmm_get_settings();
    $s['akismet_key'] = '';
    wpmm_save_settings( $s );
    wp_send_json_success( 'API key removed.' );
}

// =========================================================================
// Save spam settings — called via AJAX from the Settings page
// =========================================================================
add_action( 'wp_ajax_wpmm_save_spam_settings', 'wpmm_ajax_save_spam_settings' );
function wpmm_ajax_save_spam_settings() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $site_id  = absint( $_POST['spam_site_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $switched = false;
    if ( is_multisite() && $site_id > 0 && $site_id !== get_current_blog_id() ) {
        switch_to_blog( $site_id );
        $switched = true;
    }

    $s = wpmm_get_settings();

    $s['spam_filter_enabled'] = isset( $_POST['spam_filter_enabled'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $s['comments_disabled']   = isset( $_POST['comments_disabled'] )   ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $s['spam_min_time']       = absint( $_POST['spam_min_time']   ?? 5 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $s['spam_max_links']      = absint( $_POST['spam_max_links']  ?? 3 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $s['spam_keywords']       = sanitize_textarea_field( wp_unslash( $_POST['spam_keywords']    ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $s['spam_ip_blocklist']   = sanitize_textarea_field( wp_unslash( $_POST['spam_ip_blocklist'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

    wpmm_save_settings( $s );
    if ( $switched ) {
        restore_current_blog();
    }
    wp_send_json_success( 'Spam filter settings saved.' );
}

// =========================================================================
// Helpers
// =========================================================================

/**
 * Log a blocked comment attempt to wpmm_spam_log.
 *
 * @param string $rule         The rule that triggered: honeypot|too_fast|blocked_ip|keyword|too_many_links|duplicate|akismet
 * @param array  $comment_data The comment data array from WordPress.
 */
function wpmm_log_spam( $rule, $comment_data = [] ) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $wpdb->insert(
        esc_sql( $wpdb->prefix . 'wpmm_spam_log' ),
        [
            'blocked_at'      => current_time( 'mysql' ),
            'rule'            => sanitize_text_field( $rule ),
            'author_ip'       => sanitize_text_field( wp_unslash( $comment_data['comment_author_IP'] ?? ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) ),
            'author_name'     => sanitize_text_field( $comment_data['comment_author']       ?? '' ),
            'author_email'    => sanitize_email(      $comment_data['comment_author_email'] ?? '' ),
            'author_url'      => esc_url_raw(         $comment_data['comment_author_url']   ?? '' ),
            'comment_content' => sanitize_textarea_field( $comment_data['comment_content']  ?? '' ),
            'post_id'         => absint( $comment_data['comment_post_ID'] ?? 0 ),
        ],
        [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
    );
}

/**
 * Parse a newline-separated list into a trimmed array, dropping blanks.
 */
function wpmm_parse_list( $raw ) {
    return array_filter(
        array_map( 'trim', explode( "\n", str_replace( "\r", '', $raw ) ) ),
        'strlen'
    );
}

/**
 * Log and hard-stop a comment submission identified as spam.
 *
 * @param string $reason       Rule code that triggered the block.
 * @param array  $comment_data Comment data for logging.
 */
function wpmm_spam_die( $reason = 'spam', $comment_data = [] ) {
    wpmm_log_spam( $reason, $comment_data );
    $messages = [
        'honeypot'       => 'Your comment could not be posted.',
        'too_fast'       => 'You are submitting too quickly. Please wait a moment and try again.',
        'blocked_ip'     => 'Your comment could not be posted.',
        'keyword'        => 'Your comment contains content that is not allowed.',
        'too_many_links' => 'Your comment contains too many links and could not be posted.',
        'duplicate'      => 'Duplicate comment detected. It looks like you already submitted that comment.',
    ];
    $msg = $messages[ $reason ] ?? 'Your comment could not be posted.';
    wp_die(
        esc_html( $msg ),
        esc_html__( 'Comment Blocked', 'greenskeeper' ),
        [ 'response' => 403, 'back_link' => true ]
    );
}

// =========================================================================
// AJAX: Delete spam log entries
// =========================================================================
add_action( 'wp_ajax_wpmm_delete_spam_entries', 'wpmm_ajax_delete_spam_entries' );
function wpmm_ajax_delete_spam_entries() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    global $wpdb;
    $ids = array_map( 'absint', (array) ( $_POST['ids'] ?? [] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( empty( $ids ) ) {
        wp_send_json_error( 'No IDs provided.' );
    }

    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    // Dynamic IN() clause — placeholders are %d repeated per ID count, built safely via array_fill().
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    $deleted = $wpdb->query(
        $wpdb->prepare( 'DELETE FROM ' . esc_sql( $wpdb->prefix . 'wpmm_spam_log' ) . ' WHERE id IN (' . $placeholders . ')', $ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    );

    wp_send_json_success( [ 'deleted' => $deleted ] );
}

// =========================================================================
// AJAX: Delete ALL spam log entries
// =========================================================================
add_action( 'wp_ajax_wpmm_clear_spam_log', 'wpmm_ajax_clear_spam_log' );
function wpmm_ajax_clear_spam_log() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }
    global $wpdb;
    // TRUNCATE is DDL and cannot use $wpdb->prepare(). Table name is $wpdb->prefix + fixed string — no user input.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query( 'TRUNCATE TABLE ' . esc_sql( $wpdb->prefix . 'wpmm_spam_log' ) );
    wp_send_json_success( 'Spam log cleared.' );
}

// =========================================================================
// AJAX: Add IP to blocklist from spam log
// =========================================================================
add_action( 'wp_ajax_wpmm_blocklist_ip', 'wpmm_ajax_blocklist_ip' );
function wpmm_ajax_blocklist_ip() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $ip = sanitize_text_field( wp_unslash( $_POST['ip'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( ! $ip ) {
        wp_send_json_error( 'No IP provided.' );
    }

    $s        = wpmm_get_settings();
    $existing = wpmm_parse_list( $s['spam_ip_blocklist'] ?? '' );

    if ( ! in_array( $ip, $existing, true ) ) {
        $existing[] = $ip;
        $s['spam_ip_blocklist'] = implode( "\n", $existing );
        wpmm_save_settings( $s );
        wp_send_json_success( [ 'message' => $ip . ' added to IP blocklist.' ] );
    } else {
        wp_send_json_success( [ 'message' => $ip . ' is already in the IP blocklist.' ] );
    }
}
