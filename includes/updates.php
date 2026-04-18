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
        foreach ( $update_plugins->response as $plugin_file => $plugin_data ) {
            if ( strpos( $plugin_file, 'greenskeeper' ) !== false ) {
                continue;
            }
            // When scoped to a specific site, only show plugins active on that site.
            if ( $active_plugins_for_site !== null && ! in_array( $plugin_file, $active_plugins_for_site, true ) ) {
                continue;
            }
            $result['plugins'][] = [
                'name'        => isset( $all_plugins[ $plugin_file ]['Name'] )    ? $all_plugins[ $plugin_file ]['Name']    : $plugin_file,
                'slug'        => $plugin_file,
                'old_version' => isset( $all_plugins[ $plugin_file ]['Version'] ) ? $all_plugins[ $plugin_file ]['Version'] : '',
                'new_version' => isset( $plugin_data->new_version )               ? $plugin_data->new_version               : '',
                // package URL stored so the upgrader can use it directly if the
                // transient has been cleared by the time the update AJAX call fires.
                'package'     => isset( $plugin_data->package )                   ? $plugin_data->package                   : '',
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
                // Transient entry is gone but we have the package URL from the scan.
                // IMPORTANT: never use install() on an already-active plugin — it calls
                // deactivate_plugins() internally as part of a fresh install flow, which
                // disables the plugin after the update completes.
                // Instead, inject a minimal transient entry so that upgrade() can find
                // the package URL. upgrade() preserves the plugin's active/inactive state.
                $t = get_site_transient( 'update_plugins' );
                if ( ! $t ) {
                    $t = new stdClass();
                }
                if ( ! isset( $t->response ) || ! is_array( $t->response ) ) {
                    $t->response = [];
                }
                $injected_entry = new stdClass();
                $injected_entry->id          = $slug;
                $injected_entry->slug        = dirname( $slug );
                $injected_entry->plugin      = $slug;
                $injected_entry->new_version = '';
                $injected_entry->package     = $pkg_url;
                $t->response[ $slug ]        = $injected_entry;
                set_site_transient( 'update_plugins', $t );

                $upgrader = new Plugin_Upgrader( $skin );
                $result   = $upgrader->upgrade( $slug );
                list( $status, $new_version, $error_code, $message ) =
                    wpmm_interpret_plugin_result( $result, $skin, $slug, $old_version );

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
            // Normal path: transient entry exists — use Plugin_Upgrader::upgrade().
            $upgrader = new Plugin_Upgrader( $skin );
            $result   = $upgrader->upgrade( $slug );
            list( $status, $new_version, $error_code, $message ) =
                wpmm_interpret_plugin_result( $result, $skin, $slug, $old_version );

            if ( $status === 'success' ) {
                // Surgically remove this item from the transient so the next
                // plugin in the batch still finds its own entry intact.
                $t = get_site_transient( 'update_plugins' );
                if ( $t && isset( $t->response[ $slug ] ) ) {
                    unset( $t->response[ $slug ] );
                    set_site_transient( 'update_plugins', $t );
                }
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

        if ( ! $transient_has_entry ) {
            $theme_now = wp_get_theme( $slug );
            $ver_now   = $theme_now->get( 'Version' );
            if ( version_compare( $ver_now, $old_version, '>' ) ) {
                $status      = 'success';
                $new_version = $ver_now;
                $message     = 'Updated to ' . $new_version . ' (already applied earlier in this session).';
            } elseif ( $pkg_url ) {
                // Same as the plugin path: inject into transient so upgrade()
                // is used instead of install(), preserving active theme state.
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

                $upgrader = new Theme_Upgrader( $skin );
                $result   = $upgrader->upgrade( $slug );
                list( $status, $new_version, $error_code, $message ) =
                    wpmm_interpret_theme_result( $result, $skin, $slug, $old_version );

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

    // Persist the session ID and current blog ID so that the Email Reports page
    // can retrieve the correct log entries even after a page navigation.
    // On Multisite, admin-ajax.php runs on the main blog by default, so we
    // store the blog_id alongside the session_id and switch_to_blog() when
    // fetching entries in wpmm_ajax_send_email().
    if ( $session_id ) {
        update_option( 'wpmm_last_session', [
            'session_id' => $session_id,
            'blog_id'    => get_current_blog_id(),
            'updated_at' => current_time( 'mysql' ),
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
 */
function wpmm_interpret_plugin_result( $result, $skin, $slug, $old_version ) {
    if ( $result === true ) {
        clearstatcache();
        $plugins     = get_plugins();
        $new_version = isset( $plugins[ $slug ]['Version'] ) ? $plugins[ $slug ]['Version'] : '';
        return [ 'success', $new_version, '', '' ];
    }

    if ( is_null( $result ) ) {
        // Upgrader ran but found nothing to do — check installed version.
        clearstatcache();
        $plugins = get_plugins();
        $ver_now = isset( $plugins[ $slug ]['Version'] ) ? $plugins[ $slug ]['Version'] : $old_version;
        if ( version_compare( $ver_now, $old_version, '>' ) ) {
            return [ 'success', $ver_now, '', 'Updated successfully (confirmed via version comparison).' ];
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
 */
function wpmm_interpret_theme_result( $result, $skin, $slug, $old_version ) {
    if ( $result === true ) {
        $theme = wp_get_theme( $slug );
        return [ 'success', $theme->get( 'Version' ), '', '' ];
    }

    if ( is_null( $result ) ) {
        $theme   = wp_get_theme( $slug );
        $ver_now = $theme->get( 'Version' );
        if ( version_compare( $ver_now, $old_version, '>' ) ) {
            return [ 'success', $ver_now, '', 'Updated successfully (confirmed via version comparison).' ];
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
// Hook into upgrader_process_complete to catch updates made outside
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
    $session_id = 'ext-' . date( 'Ymd' );

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

