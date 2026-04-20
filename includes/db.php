<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Direct database queries in this file are intentional and necessary for schema
 * management (CREATE TABLE, ALTER TABLE, SHOW COLUMNS, INFORMATION_SCHEMA).
 * All table names are derived from $wpdb->prefix + fixed strings — no user input
 * is ever interpolated. $wpdb->prepare() cannot be used for DDL statements.
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 */

/**
 * Creates or upgrades the plugin tables for the current blog.
 *
 * Safe to call on every page load — dbDelta handles idempotency for new
 * tables, and every ALTER TABLE is guarded by a SHOW COLUMNS check.
 */
function wpmm_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $log_table   = $wpdb->prefix . 'wpmm_update_log';
    $email_table = $wpdb->prefix . 'wpmm_email_log';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // ── wpmm_update_log ───────────────────────────────────────────────────────
    dbDelta( "CREATE TABLE IF NOT EXISTS {$log_table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id  VARCHAR(64)     NOT NULL DEFAULT '',
        item_name   VARCHAR(255)    NOT NULL DEFAULT '',
        item_type   VARCHAR(20)     NOT NULL DEFAULT '',
        item_slug   VARCHAR(255)    NOT NULL DEFAULT '',
        old_version VARCHAR(50)     NOT NULL DEFAULT '',
        new_version VARCHAR(50)     NOT NULL DEFAULT '',
        status      VARCHAR(20)     NOT NULL DEFAULT '',
        error_code  VARCHAR(100)    NOT NULL DEFAULT '',
        message     TEXT,
        updated_at  DATETIME        NOT NULL,
        PRIMARY KEY (id),
        KEY session_id (session_id)
    ) {$charset};" );

    // ── wpmm_email_log ────────────────────────────────────────────────────────
    dbDelta( "CREATE TABLE IF NOT EXISTS {$email_table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id  VARCHAR(64)     NOT NULL DEFAULT '',
        to_email    VARCHAR(255)    NOT NULL DEFAULT '',
        subject     VARCHAR(500)    NOT NULL DEFAULT '',
        body        LONGTEXT        NOT NULL,
        status      VARCHAR(20)     NOT NULL DEFAULT '',
        sent_at     DATETIME        NOT NULL,
        PRIMARY KEY (id)
    ) {$charset};" );

    // ── wpmm_spam_log ─────────────────────────────────────────────────────────
    $spam_table = $wpdb->prefix . 'wpmm_spam_log';
    dbDelta( "CREATE TABLE IF NOT EXISTS {$spam_table} (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        blocked_at      DATETIME        NOT NULL,
        rule            VARCHAR(50)     NOT NULL DEFAULT '',
        author_ip       VARCHAR(100)    NOT NULL DEFAULT '',
        author_name     VARCHAR(255)    NOT NULL DEFAULT '',
        author_email    VARCHAR(255)    NOT NULL DEFAULT '',
        author_url      VARCHAR(500)    NOT NULL DEFAULT '',
        comment_content TEXT,
        post_id         BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY blocked_at (blocked_at),
        KEY author_ip  (author_ip)
    ) {$charset};" );

    // ── Column upgrade paths ──────────────────────────────────────────────────
    // Add any column that might be missing from installs that predate its addition.
    // Each ALTER is guarded individually so one failure does not block others.
    $upgrades = [
        $log_table => [
            'session_id' => "ALTER TABLE {$log_table} ADD COLUMN session_id  VARCHAR(64)  NOT NULL DEFAULT '' AFTER id",
            'error_code' => "ALTER TABLE {$log_table} ADD COLUMN error_code  VARCHAR(100) NOT NULL DEFAULT '' AFTER status",
        ],
        $email_table => [
            'session_id' => "ALTER TABLE {$email_table} ADD COLUMN session_id VARCHAR(64) NOT NULL DEFAULT '' AFTER id",
        ],
    ];

    foreach ( $upgrades as $table => $cols ) {
        foreach ( $cols as $col => $alter_sql ) {
            $exists = $wpdb->get_results( $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME, $table, $col
            ) );
            if ( empty( $exists ) ) {
                // DDL statements (ALTER TABLE, CREATE INDEX) cannot use wpdb::prepare().
                // prepare() is for parameterised DML queries (SELECT/INSERT/UPDATE/DELETE).
                // The $alter_sql strings above are composed exclusively from:
                //   $wpdb->prefix  — a trusted value set by WordPress on initialisation
                //   hardcoded column names and SQL keywords — no user input ever appears here.
                // This is the standard WordPress pattern for schema upgrades (see dbDelta source).
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query( $alter_sql );
            }
        }
    }

    // ── Index upgrade ─────────────────────────────────────────────────────────
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $idx = $wpdb->get_results( $wpdb->prepare(
        'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s',
        DB_NAME,
        $log_table,
        'session_id'
    ) );
    if ( empty( $idx ) ) {
        // DDL — cannot use prepare(). Table name is $wpdb->prefix + fixed string.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( "ALTER TABLE {$log_table} ADD INDEX session_id (session_id)" );
    }
}

/**
 * Returns a snapshot of both tables for the diagnostic panel.
 */
function wpmm_db_diagnostic() {
    global $wpdb;
    $log_table   = $wpdb->prefix . 'wpmm_update_log';
    $email_table = $wpdb->prefix . 'wpmm_email_log';

    $info = [];

    foreach ( [ 'update_log' => $log_table, 'email_log' => $email_table ] as $key => $table ) {
        // Check table exists
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME, $table
        ) );

        if ( ! $exists ) {
            $info[ $key ] = [ 'exists' => false, 'table' => $table ];
            continue;
        }

        $cols     = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        $count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $recent   = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 5" );
        $info[ $key ] = [
            'exists'  => true,
            'table'   => $table,
            'cols'    => $cols,
            'count'   => $count,
            'recent'  => $recent,
        ];
    }

    $info['db_version']     = get_option( 'wpmm_db_version', 'not set' );
    $info['plugin_version'] = WPMM_VERSION;
    $info['last_error']     = $wpdb->last_error;

    return $info;
}
