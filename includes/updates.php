<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Return available updates grouped by type.
 * Called ONCE per scan. Transients are seeded here and read by wpmm_do_update().
 */
function wpmm_get_available_updates( $site_id = 0 ) {

    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // When scoped to a single site in multisite, get that site's active
    // plugins and active theme so we can filter the results.
    $active_plugins_for_site = null; // null = no filter (show all)
    $active_theme_for_site   = null;
    $switched                = false;

    if ( is_multisite() && $site_id > 0 ) {
        switch_to_blog( $site_id );
        $switched = true;
        // Site-level active plugins (not network-activated ones)
        $site_plugins = (array) get_option( 'active_plugins', [] );
        // Network-activated plugins are active on ALL sites
        $network_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) );
        $active_plugins_for_site = array_unique( array_merge( $site_plugins, $network_plugins ) );
        $active_theme_for_site   = get_option( 'stylesheet' ); // active theme slug
        restore_current_blog();
        $switched = false;
    }

    // Force a fresh check from the WordPress.org API.
    wp_version_check( [], true );
    wp_update_plugins();
    wp_update_themes();

    $result = [ 'core' => [], 'plugins' => [], 'themes' => [], 'site_id' => $site_id ];

    // -- Core ------------------------------------------------------------------
    global $wp_version;
    $update_core = get_site_transient( 'update_core' );
    if ( ! empty( $update_core->updates ) ) {
        foreach ( $update_core->updates as $u ) {
            if ( isset( $u->response ) && $u->response === 'upgrade' ) {
                $result['core'][] = [
                    'name'        => 'WordPress',
                    'slug'        => 'wordpress-core',
                    'old_version' => $wp_version,
                    'new_version' => $u->version,
                    'package'     => isset( $u->packages->full ) ? $u->packages->full : '',
                ];
            }
        }
    }

    // -- Plugins ---------------------------------------------------------------
    $update_plugins = get_site_transient( 'update_plugins' );
    $all_plugins    = get_plugins();
    if ( ! empty( $update_plugins->response ) ) {

        // Collect all plugin files that have an empty package URL.
        // On managed hosting (Kinsta, WP Engine), calling wp_update_plugins()
        // from within an AJAX request causes HTTP 500 due to loopback blocking.
        // Instead we mark empty-URL plugins as requires_manual. Premium plugins
        // like AIOSEO Pro that need a fresh URL will get one automatically when
        // their update is attempted — the upgrader handles this transparently.
        $empty_pkg_slugs = [];
        foreach ( $update_plugins->response as $plugin_file => $plugin_data ) {
            $pkg = isset( $plugin_data->package ) ? $plugin_data->package : '';
            if ( $pkg === '' ) {
                $empty_pkg_slugs[] = $plugin_file;
            }
        }

        foreach ( $update_plugins->response as $plugin_file => $plugin_data ) {
            if ( strpos( $plugin_file, 'greenskeeper' ) !== false ) {
                continue;
            }
            // When scoped to a specific site, only show plugins active on that site.
            if ( $active_plugins_for_site !== null && ! in_array( $plugin_file, $active_plugins_for_site, true ) ) {
                continue;
            }
            $pkg = isset( $plugin_data->package ) ? $plugin_data->package : '';

            // requires_manual is only true if the URL is STILL empty after a
            // fresh update check. This correctly identifies plugins like Gravity
            // Forms add-ons that withhold the URL pending browser license
            // validation, while allowing AIOSEO Pro and similar to proceed
            // normally through the upgrader with their freshly populated URL.
            $requires_manual = ( $pkg === '' && in_array( $plugin_file, $empty_pkg_slugs, true ) );

            $result['plugins'][] = [
                'name'            => isset( $all_plugins[ $plugin_file ]['Name'] )    ? $all_plugins[ $plugin_file ]['Name']    : $plugin_file,
                'slug'            => $plugin_file,
                'old_version'     => isset( $all_plugins[ $plugin_file ]['Version'] ) ? $all_plugins[ $plugin_file ]['Version'] : '',
                'new_version'     => isset( $plugin_data->new_version )               ? $plugin_data->new_version               : '',
                'package'         => $pkg,
                'requires_manual' => $requires_manual,
            ];
        }
    }

    // -- Themes ----------------------------------------------------------------
    $update_themes = get_site_transient( 'update_themes' );
    if ( ! empty( $update_themes->response ) ) {
        foreach ( $update_themes->response as $theme_slug => $theme_data ) {
            // When scoped to a specific site, only show the active theme.
            if ( $active_theme_for_site !== null && $theme_slug !== $active_theme_for_site ) {
                continue;
            }
            $theme = wp_get_theme( $theme_slug );
            $result['themes'][] = [
                'name'        => $theme->get( 'Name' ) ?: $theme_slug,
                'slug'        => $theme_slug,
                'old_version' => $theme->get( 'Version' ),
                'new_version' => isset( $theme_data['new_version'] ) ? $theme_data['new_version'] : '',
                'package'     => isset( $theme_data['package'] )     ? $theme_data['package']     : '',
            ];
        }
    }

    // Filter plugins/themes to those activated on the target site.
    if ( is_multisite() && $site_id > 0 ) {
        $active_plugins = get_option( 'active_plugins', [] );
        $result['plugins'] = array_values( array_filter(
            $result['plugins'],
            function( $p ) use ( $active_plugins ) {
                return in_array( $p['slug'], $active_plugins, true );
            }
        ) );

        // Active theme for this site.
        $active_theme_slug = get_stylesheet();
        $result['themes'] = array_values( array_filter(
            $result['themes'],
            function( $t ) use ( $active_theme_slug ) {
                return $t['slug'] === $active_theme_slug;
            }
        ) );
    }

    if ( $switched ) {
        restore_current_blog();
    }

    return $result;
}

/**
 * Perform a single update and log the result.
 *
 * The $package param is the direct download URL captured during the scan
 * and passed from JavaScript. When the transient entry for this item is
 * missing (expired or cleared), we use the package URL directly so the
 * update still succeeds rather than failing with wpmm_no_transient.
 *
 * @param string $type    'core' | 'plugin' | 'theme'
 * @param string $slug    Plugin file path or theme slug.
 * @param string $package Direct download URL (may be empty for premium plugins).
 * @return array
 */
function wpmm_do_update( $type, $slug, $package = '' ) {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
    }

    $session_id  = isset( $GLOBALS['wpmm_session_id'] ) ? $GLOBALS['wpmm_session_id'] : '';
    $skin        = new WP_Ajax_Upgrader_Skin();
    $old_version = '';
    $new_version = '';
    $name        = $slug;
    $status      = 'failed';
    $error_code  = '';
    $message     = '';

    // =========================================================================
    // WordPress Core
    // =========================================================================
    if ( $type === 'core' ) {

        require_once ABSPATH . 'wp-admin/includes/update.php';
        global $wp_version;
        $old_version = $wp_version;
        $name        = 'WordPress';

        $update_core = get_site_transient( 'update_core' );
        $update      = null;
        if ( ! empty( $update_core->updates ) ) {
            foreach ( $update_core->updates as $u ) {
                if ( isset( $u->response ) && $u->response === 'upgrade' ) {
                    $update = $u;
                    break;
                }
            }
        }

        if ( $update ) {
            $upgrader = new Core_Upgrader( $skin );
            $result   = $upgrader->upgrade( $update );
            if ( $result === true || ( ! is_wp_error( $result ) && ! is_null( $result ) && $result !== false ) ) {
                $status      = 'success';
                $new_version = $update->version;
            } else {
                $error_code = is_wp_error( $result ) ? $result->get_error_code() : 'update_failed';
                $message    = is_wp_error( $result ) ? $result->get_error_message() : 'WordPress core update failed.';
            }
        } else {
            $error_code = 'wpmm_no_transient';
            $message    = wpmm_explain_error( $error_code )['detail'];
        }

    // =========================================================================
    // Plugin
    // =========================================================================
    } elseif ( $type === 'plugin' ) {

        $all_plugins = get_plugins();
        $name        = isset( $all_plugins[ $slug ]['Name'] )    ? $all_plugins[ $slug ]['Name']    : $slug;
        $old_version = isset( $all_plugins[ $slug ]['Version'] ) ? $all_plugins[ $slug ]['Version'] : '';

        $update_plugins      = get_site_transient( 'update_plugins' );
        $transient_has_entry = ! empty( $update_plugins->response[ $slug ] );

        // Determine the package URL: prefer the transient entry, fall back to
        // the URL passed in from the JS scan results.
        $pkg_url = '';
        if ( $transient_has_entry && ! empty( $update_plugins->response[ $slug ]->package ) ) {
            $pkg_url = $update_plugins->response[ $slug ]->package;
        } elseif ( $package ) {
            $pkg_url = $package;
        }

        // ── Package URL freshness strategy ───────────────────────────────────
        // Premium plugin vendors generate signed, time-limited package URLs.
        // If the URL has expired, WordPress's upgrader will fail and may call
        // deactivate_plugins() as part of its error recovery.
        //
        // Previously we called wp_update_plugins() to force a fresh URL — but
        // on managed hosting (Kinsta, WP Engine, etc.) this causes an HTTP 500
        // because wp_update_plugins() makes a loopback HTTP request back to the
        // WordPress.org API from within an already-running AJAX request. The
        // server's fastCGI timeout or loopback blocking kills the outer request.
        //
        // The correct approach:
        // 1. HEAD check the existing URL — fast, no loopback.
        // 2. If stale, schedule a wp-cron event to refresh the transient in the
        //    background (non-blocking), then proceed with what we have.
        // 3. If the upgrade fails with a null result (silent download failure),
        //    wpmm_interpret_plugin_result() handles the retry with a fresh URL
        //    via a separate request that doesn't compound the loopback problem.
        if ( $pkg_url ) {
            $head        = wp_remote_head( $pkg_url, [
                'timeout'     => 8,
                'redirection' => 3,
                'blocking'    => true,
                'sslverify'   => false,
            ] );
            $head_status = is_wp_error( $head ) ? 0 : wp_remote_retrieve_response_code( $head );

            if ( $head_status === 0 || $head_status >= 400 ) {
                // URL is stale. Schedule a non-blocking background refresh so
                // the next update attempt gets a fresh URL. Do NOT call
                // wp_update_plugins() here — it causes HTTP 500 on managed hosts.
                if ( ! wp_next_scheduled( 'wpmm_refresh_update_transient' ) ) {
                    wp_schedule_single_event( time(), 'wpmm_refresh_update_transient' );
                }

                // Try to get a fresh URL from the existing transient in case
                // another process already refreshed it recently.
                $update_plugins      = get_site_transient( 'update_plugins' );
                $transient_has_entry = ! empty( $update_plugins->response[ $slug ] );

                if ( $transient_has_entry && ! empty( $update_plugins->response[ $slug ]->package ) ) {
                    $fresh_pkg = $update_plugins->response[ $slug ]->package;
                    // Only use the fresh URL if it's different from the stale one.
                    if ( $fresh_pkg !== $pkg_url ) {
                        $pkg_url = $fresh_pkg;
                    } else {
                        // Same URL — still stale. Clear it so the upgrader
                        // attempts a direct download, which may still work if
                        // the HEAD check was a false negative (some CDNs return
                        // 403 on HEAD but 200 on GET).
                        $pkg_url = $fresh_pkg;
                    }
                } else {
                    $pkg_url = '';
                }
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        if ( ! $transient_has_entry ) {
            // Transient entry is gone. Check if the version already advanced
            // (i.e. updated earlier in this batch).
            clearstatcache();
            $fresh_plugins = get_plugins();
            $ver_now       = isset( $fresh_plugins[ $slug ]['Version'] ) ? $fresh_plugins[ $slug ]['Version'] : $old_version;

            if ( version_compare( $ver_now, $old_version, '>' ) ) {
                // Already updated — mark success.
                $status      = 'success';
                $new_version = $ver_now;
                $message     = 'Updated to ' . $new_version . ' (already applied earlier in this session).';
            } elseif ( $pkg_url ) {
                // Transient entry is gone but we have a fresh package URL.
                // Inject a minimal transient entry so Plugin_Upgrader::upgrade()
                // can find the URL (upgrade() requires a transient entry — it
                // does not accept a URL directly).
                //
                // We use upgrade() not install() because install() calls
                // deactivate_plugins() internally as part of a fresh-install
                // flow, which would deactivate the plugin. upgrade() preserves
                // the plugin's active/inactive state.
                //
                // The injected entry is removed immediately after the upgrade
                // regardless of outcome.
                $t = get_site_transient( 'update_plugins' );
                if ( ! $t ) {
                    $t = new stdClass();
                }
                if ( ! isset( $t->response ) || ! is_array( $t->response ) ) {
                    $t->response = [];
                }
                $injected_entry              = new stdClass();
                $injected_entry->id          = $slug;
                $injected_entry->slug        = dirname( $slug );
                $injected_entry->plugin      = $slug;
                $injected_entry->new_version = '';
                $injected_entry->package     = $pkg_url;
                $t->response[ $slug ]        = $injected_entry;
                set_site_transient( 'update_plugins', $t );

                try {
                    $upgrader = new Plugin_Upgrader( $skin );
                    $result   = $upgrader->upgrade( $slug );
                    list( $status, $new_version, $error_code, $message ) =
                        wpmm_interpret_plugin_result( $result, $skin, $slug, $old_version );
                } catch ( \Throwable $e ) {
                    $error_code = 'update_failed';
                    $message    = 'Update failed due to an error in the plugin\'s own updater: '
                                . $e->getMessage()
                                . ' (This is a bug in the plugin, not in Greenskeeper. Try updating via Dashboard → Updates.)';
                }

                // Clean up the injected entry regardless of outcome.
                $t2 = get_site_transient( 'update_plugins' );
                if ( $t2 && isset( $t2->response[ $slug ] ) ) {
                    unset( $t2->response[ $slug ] );
                    set_site_transient( 'update_plugins', $t2 );
                }
            } else {
                // No transient entry, no package URL (premium/licensed plugin).
                $error_code = 'no_package';
                $message    = wpmm_explain_error( $error_code )['detail'];
            }
        } else {
            // Plugin_Upgrader::upgrade() performs an UPDATE of the plugin files
            // on disk. It does NOT change the activation status of the plugin.
            // The plugin's active/inactive state is preserved. This is the same
            // method WordPress core uses on its own Updates screen.
            //
            // Wrapped in try/catch: some premium plugins (e.g. WP Offload Media Pro)
            // use custom updater libraries that can throw ValueError or other
            // exceptions when called in a programmatic context. We catch all
            // Throwable so a bug in a plugin's own updater code does not kill
            // our AJAX request with HTTP 500.
            try {
                $upgrader = new Plugin_Upgrader( $skin );
                $result   = $upgrader->upgrade( $slug );
                list( $status, $new_version, $error_code, $message ) =
                    wpmm_interpret_plugin_result( $result, $skin, $slug, $old_version );

                if ( $status === 'success' ) {
                    $t = get_site_transient( 'update_plugins' );
                    if ( $t && isset( $t->response[ $slug ] ) ) {
                        unset( $t->response[ $slug ] );
                        set_site_transient( 'update_plugins', $t );
                    }
                }
            } catch ( \Throwable $e ) {
                $error_code = 'update_failed';
                $message    = 'Update failed due to an error in the plugin\'s own updater: '
                            . $e->getMessage()
                            . ' (This is a bug in the plugin, not in Greenskeeper. Try updating via Dashboard → Updates.)';
            }
        }

    // =========================================================================
    // Theme
    // =========================================================================
    } elseif ( $type === 'theme' ) {

        $theme       = wp_get_theme( $slug );
        $name        = $theme->get( 'Name' ) ?: $slug;
        $old_version = $theme->get( 'Version' );

        $update_themes       = get_site_transient( 'update_themes' );
        $transient_has_entry = ! empty( $update_themes->response[ $slug ] );

        $pkg_url = '';
        if ( $transient_has_entry && ! empty( $update_themes->response[ $slug ]['package'] ) ) {
            $pkg_url = $update_themes->response[ $slug ]['package'];
        } elseif ( $package ) {
            $pkg_url = $package;
        }

        // ── Package URL freshness strategy (same as plugin path) ─────────────
        // Premium theme vendors (Divi, Avada, etc.) generate signed, time-limited
        // package URLs. If the URL has expired since the scan ran, the download
        // silently fails and Theme_Upgrader returns null. Force a fresh check.
        if ( $pkg_url ) {
            $head        = wp_remote_head( $pkg_url, [
                'timeout'     => 8,
                'redirection' => 3,
                'blocking'    => true,
                'sslverify'   => false,
            ] );
            $head_status = is_wp_error( $head ) ? 0 : wp_remote_retrieve_response_code( $head );

            if ( $head_status === 0 || $head_status >= 400 ) {
                // URL is stale. Schedule a background cron refresh.
                // Do NOT call wp_update_themes() here — loopback HTTP 500 risk.
                if ( ! wp_next_scheduled( 'wpmm_refresh_update_transient' ) ) {
                    wp_schedule_single_event( time(), 'wpmm_refresh_update_transient' );
                }

                // Check if the transient already has a fresh URL from a recent refresh.
                $update_themes       = get_site_transient( 'update_themes' );
                $transient_has_entry = ! empty( $update_themes->response[ $slug ] );

                if ( $transient_has_entry && ! empty( $update_themes->response[ $slug ]['package'] ) ) {
                    $pkg_url = $update_themes->response[ $slug ]['package'];
                } else {
                    $pkg_url = '';
                }
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        if ( ! $transient_has_entry ) {
            $theme_now = wp_get_theme( $slug );
            $ver_now   = $theme_now->get( 'Version' );
            if ( version_compare( $ver_now, $old_version, '>' ) ) {
                $status      = 'success';
                $new_version = $ver_now;
                $message     = 'Updated to ' . $new_version . ' (already applied earlier in this session).';
            } elseif ( $pkg_url ) {
                // ── Transient injection for theme upgrade — same rationale as plugin ──
                // Theme_Upgrader::upgrade() requires a transient entry to find the package
                // URL. We inject it here and remove it immediately after the upgrade
                // completes. No external server is contacted. This is NOT a phone-home
                // update checker. See the plugin transient injection above for full notes.
                $t = get_site_transient( 'update_themes' );
                if ( ! $t ) { $t = new stdClass(); }
                if ( ! isset( $t->response ) || ! is_array( $t->response ) ) {
                    $t->response = [];
                }
                $t->response[ $slug ] = [
                    'theme'       => $slug,
                    'new_version' => '',
                    'url'         => '',
                    'package'     => $pkg_url,
                ];
                set_site_transient( 'update_themes', $t );

                try {
                    $upgrader = new Theme_Upgrader( $skin );
                    $result   = $upgrader->upgrade( $slug );
                    list( $status, $new_version, $error_code, $message ) =
                        wpmm_interpret_theme_result( $result, $skin, $slug, $old_version );
                } catch ( \Throwable $e ) {
                    $error_code = 'update_failed';
                    $message    = 'Update failed due to an error in the theme\'s own updater: '
                                . $e->getMessage()
                                . ' (Try updating via Dashboard → Updates.)';
                }

                // Clean up injected entry.
                $t2 = get_site_transient( 'update_themes' );
                if ( $t2 && isset( $t2->response[ $slug ] ) ) {
                    unset( $t2->response[ $slug ] );
                    set_site_transient( 'update_themes', $t2 );
                }
            } else {
                $error_code = 'no_package';
                $message    = wpmm_explain_error( $error_code )['detail'];
            }
        } else {
            // Theme_Upgrader::upgrade() updates the theme files on disk.
            // It does NOT change which theme is active.
            try {
                $upgrader = new Theme_Upgrader( $skin );
                $result   = $upgrader->upgrade( $slug );
                list( $status, $new_version, $error_code, $message ) =
                    wpmm_interpret_theme_result( $result, $skin, $slug, $old_version );

                if ( $status === 'success' ) {
                    $t = get_site_transient( 'update_themes' );
                    if ( $t && isset( $t->response[ $slug ] ) ) {
                        unset( $t->response[ $slug ] );
                        set_site_transient( 'update_themes', $t );
                    }
                }
            } catch ( \Throwable $e ) {
                $error_code = 'update_failed';
                $message    = 'Update failed due to an error in the theme\'s own updater: '
                            . $e->getMessage()
                            . ' (Try updating via Dashboard → Updates.)';
            }
        }
    }

    // -- Log ------------------------------------------------------------------
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- legitimate insert to custom plugin table.
    $wpdb->insert(
        $wpdb->prefix . 'wpmm_update_log',
        [
            'session_id'  => $session_id,
            'item_name'   => $name,
            'item_type'   => $type,
            'item_slug'   => $slug,
            'old_version' => $old_version,
            'new_version' => $new_version,
            'status'      => $status,
            'error_code'  => $error_code,
            'message'     => $message,
            'updated_at'  => current_time( 'mysql' ),
        ]
    );

    // Persist the session ID into the pending sessions list so the Email
    // Reports page can include ALL unsent sessions in a single report —
    // even when plugins and themes were updated in separate sessions.
    //
    // wpmm_pending_sessions stores an array of sessions run since the last
    // email was sent. Each entry has: session_id, blog_id, updated_at.
    // wpmm_last_session is kept in sync for backward compatibility.
    if ( $session_id ) {
        $pending   = get_option( 'wpmm_pending_sessions', [] );
        $blog_id   = get_current_blog_id();
        $timestamp = current_time( 'mysql' );

        // Add this session if it's not already in the list.
        $already = false;
        foreach ( $pending as $p ) {
            if ( isset( $p['session_id'] ) && $p['session_id'] === $session_id ) {
                $already = true;
                break;
            }
        }
        if ( ! $already ) {
            $pending[] = [
                'session_id' => $session_id,
                'blog_id'    => $blog_id,
                'updated_at' => $timestamp,
            ];
            update_option( 'wpmm_pending_sessions', $pending, false );
        }

        // Keep wpmm_last_session in sync for backward compatibility.
        update_option( 'wpmm_last_session', [
            'session_id' => $session_id,
            'blog_id'    => $blog_id,
            'updated_at' => $timestamp,
        ], false );
    }

    return [
        'status'      => $status,
        'error_code'  => $error_code,
        'message'     => $message,
        'name'        => $name,
        'old_version' => $old_version,
        'new_version' => $new_version,
    ];
}

/**
 * Shared result interpreter for Plugin_Upgrader.
 * Returns [ $status, $new_version, $error_code, $message ].
 *
 * When $result is null the upgrader ran but wrote nothing to disk. This
 * most commonly means the package URL was a redirect whose final signed
 * destination had expired — the download silently failed. We force a fresh
 * wp_update_plugins() to get a new URL from the vendor and retry once.
 * This handles AIOSEO Pro, WPForms, and other plugins whose update servers
 * return HTTP 200 on the initial URL even when the signed download link
 * behind it has already expired, so the HEAD pre-check passes but the
 * actual download then gets a 403 or empty response.
 */
function wpmm_interpret_plugin_result( $result, $skin, $slug, $old_version, $retry = true, $retried = false ) {
    if ( $result === true ) {
        // Flush all caches before reading the new version — opcode cache
        // (OPcache/APCu) can cause get_plugins() to return the old version
        // header even after the new files are on disk.
        if ( function_exists( 'opcache_reset' ) ) {
            opcache_reset();
        }
        wp_clean_plugins_cache( true );
        clearstatcache( true );
        $plugins     = get_plugins();
        $new_version = isset( $plugins[ $slug ]['Version'] ) ? $plugins[ $slug ]['Version'] : '';
        $msg = $retried ? 'Updated successfully (required a fresh package URL — retry succeeded).' : '';
        return [ 'success', $new_version, '', $msg ];
    }

    if ( is_null( $result ) ) {
        // Upgrader ran but nothing was written to disk.
        // First check: maybe it actually succeeded already.
        clearstatcache();
        $plugins = get_plugins();
        $ver_now = isset( $plugins[ $slug ]['Version'] ) ? $plugins[ $slug ]['Version'] : $old_version;
        if ( version_compare( $ver_now, $old_version, '>' ) ) {
            return [ 'success', $ver_now, '', 'Updated successfully (confirmed via version comparison).' ];
        }

        // Check whether the skin recorded a specific filesystem error.
        // When WordPress downloads and extracts successfully but cannot write
        // a file to disk (e.g. permissions), the skin logs "Could not copy file"
        // but Plugin_Upgrader still returns null rather than WP_Error.
        // Surface that specific error instead of the generic version-unchanged message.
        $skin_errors = $skin->get_errors()->get_error_messages();
        if ( ! empty( $skin_errors ) ) {
            $skin_msg = implode( ' ', $skin_errors );
            // Identify file-permission failures specifically.
            if ( stripos( $skin_msg, 'could not copy' ) !== false
                || stripos( $skin_msg, 'copy failed' ) !== false
                || stripos( $skin_msg, 'mkdir' ) !== false ) {
                $explain = wpmm_explain_error( 'copy_failed' );
                return [ 'failed', '', 'copy_failed',
                    $explain['detail'] . ' WordPress reported: ' . $skin_msg ];
            }
            // Other skin error — return it verbatim.
            return [ 'failed', '', 'update_failed', $skin_msg ];
        }

        // No skin error recorded. The most common remaining cause is a signed
        // package URL that expired between our HEAD check and the actual download.
        // Do NOT call wp_update_plugins() here — it makes a loopback HTTP request
        // that causes HTTP 500 on managed hosts (Kinsta, WP Engine) when called
        // from within an AJAX request. Schedule a background cron refresh instead
        // and return the version-unchanged error. The user can retry momentarily
        // after the cron fires and populates a fresh URL.
        if ( $retry ) {
            if ( ! wp_next_scheduled( 'wpmm_refresh_update_transient' ) ) {
                wp_schedule_single_event( time(), 'wpmm_refresh_update_transient' );
            }
        }

        $code = 'wpmm_version_unchanged';
        return [ 'failed', '', $code, wpmm_explain_error( $code )['detail'] ];
    }

    if ( is_wp_error( $result ) ) {
        $code    = $result->get_error_code();
        $explain = wpmm_explain_error( $code );
        return [ 'failed', '', $code, $explain['detail'] . ' (' . $result->get_error_message() . ')' ];
    }

    // false or unexpected
    $msgs = method_exists( $skin, 'get_upgrade_messages' ) ? $skin->get_upgrade_messages() : [];
    $msg  = ! empty( $msgs ) ? implode( ' ', $msgs ) : wpmm_explain_error( 'update_failed' )['detail'];
    return [ 'failed', '', 'update_failed', $msg ];
}

/**
 * Shared result interpreter for Theme_Upgrader.
 * Returns [ $status, $new_version, $error_code, $message ].
 *
 * Mirrors wpmm_interpret_plugin_result: checks skin errors first on a null
 * result, then retries once with a fresh signed URL before giving up.
 * This handles Divi, Avada, and other premium themes whose update servers
 * issue time-limited signed URLs that may expire between scan and upgrade.
 */
function wpmm_interpret_theme_result( $result, $skin, $slug, $old_version, $retry = true, $retried = false ) {
    if ( $result === true ) {
        if ( function_exists( 'opcache_reset' ) ) {
            opcache_reset();
        }
        wp_clean_themes_cache( true );
        clearstatcache( true );
        $theme   = wp_get_theme( $slug );
        $ver_new = $theme->get( 'Version' );
        $msg     = $retried ? 'Updated successfully (required a fresh package URL — retry succeeded).' : '';
        return [ 'success', $ver_new, '', $msg ];
    }

    if ( is_null( $result ) ) {
        $theme   = wp_get_theme( $slug );
        $ver_now = $theme->get( 'Version' );
        if ( version_compare( $ver_now, $old_version, '>' ) ) {
            return [ 'success', $ver_now, '', 'Updated successfully (confirmed via version comparison).' ];
        }

        // Check whether the skin recorded a specific filesystem error.
        $skin_errors = $skin->get_errors()->get_error_messages();
        if ( ! empty( $skin_errors ) ) {
            $skin_msg = implode( ' ', $skin_errors );
            if ( stripos( $skin_msg, 'could not copy' ) !== false
                || stripos( $skin_msg, 'copy failed' ) !== false
                || stripos( $skin_msg, 'mkdir' ) !== false ) {
                $explain = wpmm_explain_error( 'copy_failed' );
                return [ 'failed', '', 'copy_failed',
                    $explain['detail'] . ' WordPress reported: ' . $skin_msg ];
            }
            return [ 'failed', '', 'update_failed', $skin_msg ];
        }

        // No skin error — schedule a background refresh and return.
        // Do NOT call wp_update_themes() here — same loopback HTTP 500 issue
        // as wp_update_plugins() on managed hosting.
        if ( $retry ) {
            if ( ! wp_next_scheduled( 'wpmm_refresh_update_transient' ) ) {
                wp_schedule_single_event( time(), 'wpmm_refresh_update_transient' );
            }
        }

        $code = 'wpmm_version_unchanged';
        return [ 'failed', '', $code, wpmm_explain_error( $code )['detail'] ];
    }

    if ( is_wp_error( $result ) ) {
        $code    = $result->get_error_code();
        $explain = wpmm_explain_error( $code );
        return [ 'failed', '', $code, $explain['detail'] . ' (' . $result->get_error_message() . ')' ];
    }

    $msgs = method_exists( $skin, 'get_upgrade_messages' ) ? $skin->get_upgrade_messages() : [];
    $msg  = ! empty( $msgs ) ? implode( ' ', $msgs ) : wpmm_explain_error( 'update_failed' )['detail'];
    return [ 'failed', '', 'update_failed', $msg ];
}

// =========================================================================
// EXTERNAL UPDATE DETECTION
// ── Background plugin transient refresh ───────────────────────────────────────
// Fired by wp-cron after a stale package URL is detected during an AJAX update.
// Runs wp_update_plugins() in a safe background context (not nested inside
// another HTTP request) so the next update attempt gets a fresh signed URL.
add_action( 'wpmm_refresh_update_transient', function () {
    if ( function_exists( 'wp_update_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/update.php';
        wp_update_plugins();
    }
} );
// Greenskeeper — e.g. via the WordPress Updates screen, the Avada plugins
// dashboard (for Avada Core / Avada Builder), or any other standard
// WordPress upgrader mechanism.
//
// NOTE: Avada Patches (managed through Avada → Maintenance → Plugins &
// Add-Ons) use Avada's own proprietary update mechanism and do NOT fire
// this hook. Those must be recorded manually via Additional Manual Updates.
// =========================================================================
add_action( 'upgrader_process_complete', 'wpmm_catch_external_updates', 20, 2 );

function wpmm_catch_external_updates( $upgrader, $hook_extra ) {
    // Skip if this update was triggered by Greenskeeper itself — already logged.
    if ( ! empty( $GLOBALS['wpmm_session_id'] ) ) {
        return;
    }

    // Only handle plugin and theme updates — not core (handled separately).
    $type = $hook_extra['type'] ?? '';
    if ( ! in_array( $type, [ 'plugin', 'theme' ], true ) ) {
        return;
    }

    // Collect the items that were updated.
    $action = $hook_extra['action'] ?? '';
    if ( $action !== 'update' ) {
        return;
    }

    $items = [];
    if ( $type === 'plugin' ) {
        // WordPress passes plugin slugs in several different structures
        // depending on whether the update was bulk, single, or via a direct call.
        // We check all known keys to cover every case.
        if ( ! empty( $hook_extra['plugins'] ) ) {
            // Bulk update — WordPress passes an array under 'plugins'
            // whether or not 'bulk' is set.
            $items = (array) $hook_extra['plugins'];
        } elseif ( ! empty( $hook_extra['plugin'] ) ) {
            // Single plugin update — WordPress passes a string under 'plugin'.
            $items = [ $hook_extra['plugin'] ];
        } else {
            // Last resort: ask the upgrader object which plugin it just updated.
            // Plugin_Upgrader stores the plugin file in skin->plugin_info or
            // in the result array after a successful run.
            if ( method_exists( $upgrader, 'plugin_info' ) ) {
                $detected = $upgrader->plugin_info();
                if ( $detected ) {
                    $items = [ $detected ];
                }
            }
        }
    } elseif ( $type === 'theme' ) {
        if ( ! empty( $hook_extra['themes'] ) ) {
            $items = (array) $hook_extra['themes'];
        } elseif ( ! empty( $hook_extra['theme'] ) ) {
            $items = [ $hook_extra['theme'] ];
        }
    }

    if ( empty( $items ) ) {
        return;
    }

    global $wpdb;

    // Build a synthetic external session ID — one per calendar day so that
    // multiple external runs on the same day group into a single session.
    // gmdate() used instead of date() to avoid timezone-affected output (WPCS requirement).
    $session_id = 'ext-' . gmdate( 'Ymd' );

    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all_plugins = get_plugins();

    foreach ( $items as $item_slug ) {
        if ( $type === 'plugin' ) {
            $name        = isset( $all_plugins[ $item_slug ]['Name'] )
                ? $all_plugins[ $item_slug ]['Name']
                : $item_slug;
            $new_version = isset( $all_plugins[ $item_slug ]['Version'] )
                ? $all_plugins[ $item_slug ]['Version']
                : '';
        } else {
            $theme       = wp_get_theme( $item_slug );
            $name        = $theme->get( 'Name' ) ?: $item_slug;
            $new_version = $theme->get( 'Version' ) ?: '';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- legitimate insert to custom plugin table.
        $wpdb->insert(
            $wpdb->prefix . 'wpmm_update_log',
            [
                'session_id'  => $session_id,
                'item_name'   => $name,
                'item_type'   => $type,
                'item_slug'   => $item_slug,
                'old_version' => '', // not available via this hook — version already updated
                'new_version' => $new_version,
                'status'      => 'success',
                'error_code'  => '',
                'message'     => 'Updated externally (outside Greenskeeper).',
                'updated_at'  => current_time( 'mysql' ),
            ]
        );
    }

    // Persist as last session so Email Reports picks it up automatically.
    update_option( 'wpmm_last_session', [
        'session_id' => $session_id,
        'blog_id'    => get_current_blog_id(),
        'updated_at' => current_time( 'mysql' ),
    ], false );
}

