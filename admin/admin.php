<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu',                    'wpmm_register_menu' );
add_action( 'network_admin_menu',            'wpmm_register_network_menu' );
add_action( 'admin_enqueue_scripts',          'wpmm_enqueue_assets' );
add_action( 'network_admin_enqueue_scripts',  'wpmm_enqueue_assets' );

// =========================================================================
// Menu slugs — centralised so every renderer references the same constants.
// =========================================================================
define( 'WPMM_SLUG_PARENT',     'wpmm-maintenance-manager' ); // top-level (non-linked)
define( 'WPMM_SLUG_DASHBOARD',  'wpmm-dashboard' );
define( 'WPMM_SLUG_UPDATES',    'wpmm-updates' );
define( 'WPMM_SLUG_LOG',        'wpmm-update-log' );
define( 'WPMM_SLUG_EMAIL',      'wpmm-email-reports' );
define( 'WPMM_SLUG_SETTINGS',   'wpmm-settings' );
define( 'WPMM_SLUG_SPAM',        'wpmm-spam-log' );

// =========================================================================
// Menu registration helpers
// =========================================================================

/**
 * Register parent + submenus for one capability level.
 * The parent top-level item is intentionally non-clickable: its callback
 * renders a wp_die() notice, and we hide it from the browser via CSS so
 * only the four labelled submenu items are visible.
 */
function wpmm_build_menus( $cap ) {
    add_menu_page(
        'Greenskeeper',
        'Greenskeeper',
        $cap,
        WPMM_SLUG_PARENT,
        'wpmm_render_no_page',
        'dashicons-shield-alt',
        81
    );

    // Store hook suffixes as WordPress generates them so the enqueue
    // function can match reliably regardless of admin context or title sanitization.
    $hooks = [];

    $hooks[] = add_submenu_page(
        WPMM_SLUG_PARENT,
        'Greenskeeper — Dashboard',
        'Dashboard',
        $cap,
        WPMM_SLUG_DASHBOARD,
        'wpmm_render_dashboard'
    );
    $hooks[] = add_submenu_page(
        WPMM_SLUG_PARENT,
        'Greenskeeper — Updates',
        'Updates',
        $cap,
        WPMM_SLUG_UPDATES,
        'wpmm_render_updates'
    );
    $hooks[] = add_submenu_page(
        WPMM_SLUG_PARENT,
        'Greenskeeper — Update Log',
        'Update Log',
        $cap,
        WPMM_SLUG_LOG,
        'wpmm_render_log'
    );
    $hooks[] = add_submenu_page(
        WPMM_SLUG_PARENT,
        'Greenskeeper — Email Reports',
        'Email Reports',
        $cap,
        WPMM_SLUG_EMAIL,
        'wpmm_render_email'
    );

    // Persist hook suffixes so wpmm_enqueue_assets() can match them.
    // add_submenu_page() returns false on failure, so filter those out.
    $existing = get_option( 'wpmm_page_hooks', [] );
    update_option( 'wpmm_page_hooks', array_unique( array_merge(
        $existing,
        array_filter( $hooks )
    ) ), false );

    $hooks[] = add_submenu_page(
        WPMM_SLUG_PARENT,
        'Greenskeeper — Spam Log',
        'Spam Log',
        $cap,
        WPMM_SLUG_SPAM,
        'wpmm_render_spam_log'
    );
    $hooks[] = add_submenu_page(
        WPMM_SLUG_PARENT,
        'Greenskeeper — Settings',
        'Settings',
        $cap,
        WPMM_SLUG_SETTINGS,
        'wpmm_render_settings'
    );

    remove_submenu_page( WPMM_SLUG_PARENT, WPMM_SLUG_PARENT );
}

function wpmm_register_menu() {
    wpmm_build_menus( wpmm_required_cap() );
}

function wpmm_register_network_menu() {
    wpmm_build_menus( 'manage_network' );
}

// =========================================================================
// Asset enqueue — runs on all four subpage hook suffixes
// =========================================================================

function wpmm_enqueue_assets( $hook ) {
    // Match by slug suffix in the hook string rather than relying on a stored
    // option. The stored option may be stale (e.g. Settings was added after
    // the option was first written) causing CSS/JS to silently not load.
    // WordPress hook suffixes for submenu pages follow the pattern:
    //   {sanitized-parent-title}_page_{page-slug}
    // We match on the slug portion which is always at the end.
    $our_slugs = [
        WPMM_SLUG_PARENT,    // toplevel_page_wpmm-maintenance-manager
        WPMM_SLUG_DASHBOARD,
        WPMM_SLUG_UPDATES,
        WPMM_SLUG_LOG,
        WPMM_SLUG_EMAIL,
        WPMM_SLUG_SPAM,
        WPMM_SLUG_SETTINGS,
    ];

    $is_our_page = false;
    foreach ( $our_slugs as $slug ) {
        // The toplevel page hook is "toplevel_page_{slug}".
        // Submenu hooks end with "_page_{slug}".
        if ( $hook === 'toplevel_page_' . $slug || substr( $hook, -( strlen( $slug ) + 6 ) ) === '_page_' . $slug ) {
            $is_our_page = true;
            break;
        }
    }
    if ( ! $is_our_page ) return;

    // Use the jQuery UI CSS bundled with WordPress (avoids external resource offloading).
    wp_enqueue_style( 'wp-jquery-ui-dialog' );
    wp_enqueue_style( 'wpmm-admin', WPMM_PLUGIN_URL . 'admin/css/admin.css', [], WPMM_VERSION );
    // On the Settings page, load the media library first so wp.media is
    // available when our script's jQuery ready handler runs.
    $is_settings = (
        substr( $hook, -( strlen( WPMM_SLUG_SETTINGS ) + 6 ) ) === '_page_' . WPMM_SLUG_SETTINGS
        || $hook === 'toplevel_page_' . WPMM_SLUG_SETTINGS
    );
    if ( $is_settings ) {
        wp_enqueue_media();
    }

    $script_deps = [ 'jquery', 'jquery-ui-datepicker' ];
    if ( $is_settings ) {
        // Ensure media scripts are fully loaded before ours so wp.media exists.
        $script_deps[] = 'media-editor';
    }
    wp_enqueue_script( 'wpmm-admin', WPMM_PLUGIN_URL . 'admin/js/admin.js',
        $script_deps, WPMM_VERSION, true );

    wp_localize_script( 'wpmm-admin', 'wpmm', [
        'ajax_url'    => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'wpmm_nonce' ),
        'site_name'   => get_bloginfo( 'name' ),
        'site_url'    => get_bloginfo( 'url' ),
        'is_network'  => wpmm_is_network_context() ? '1' : '0',
        'saved_email' => get_option( 'wpmm_client_email', '' ),
        // Subpage URLs passed to JS so it can link cross-page (e.g. "View log →")
        'url_updates' => wpmm_subpage_url( WPMM_SLUG_UPDATES ),
        'url_log'     => wpmm_subpage_url( WPMM_SLUG_LOG ),
        'url_email'   => wpmm_subpage_url( WPMM_SLUG_EMAIL ),
        'url_dash'    => wpmm_subpage_url( WPMM_SLUG_DASHBOARD ),
        'url_settings'   => wpmm_subpage_url( WPMM_SLUG_SETTINGS ),
        // Last persisted session — lets the Email Reports page pre-populate
        // the correct session_id without needing localStorage.
        'last_session_id' => ( function() {
            $ls = get_option( 'wpmm_last_session', [] );
            return isset( $ls['session_id'] ) ? $ls['session_id'] : '';
        } )(),
        'last_session_date' => ( function() {
            $ls = get_option( 'wpmm_last_session', [] );
            return isset( $ls['updated_at'] ) ? $ls['updated_at'] : '';
        } )(),
    ] );
}

// =========================================================================
// Helper: build the correct admin URL for a subpage in either context
// =========================================================================
function wpmm_subpage_url( $slug ) {
    $base = wpmm_is_network_context() ? 'network/admin.php' : 'admin.php';
    return add_query_arg( 'page', $slug, wpmm_is_network_context()
        ? network_admin_url( 'admin.php' )
        : admin_url( 'admin.php' ) );
}

// =========================================================================
// Shared page header (brand bar + active-nav highlighting)
// =========================================================================
function wpmm_page_header( $active_slug ) {
    $links = [
        WPMM_SLUG_DASHBOARD => [ 'label' => 'Dashboard',     'icon' => 'dashboard' ],
        WPMM_SLUG_UPDATES   => [ 'label' => 'Updates',       'icon' => 'update' ],
        WPMM_SLUG_LOG       => [ 'label' => 'Update Log',    'icon' => 'list-view' ],
        WPMM_SLUG_EMAIL     => [ 'label' => 'Email Reports', 'icon' => 'email-alt' ],
        WPMM_SLUG_SPAM      => [ 'label' => 'Spam Log',      'icon' => 'shield' ],
        WPMM_SLUG_SETTINGS  => [ 'label' => 'Settings',      'icon' => 'admin-settings' ],
    ];
    ?>
    <?php
    $wpmm_s        = wpmm_get_settings();
    $wpmm_logo     = ! empty( $wpmm_s['logo_url'] )     ? $wpmm_s['logo_url']     : '';
    $wpmm_company  = ! empty( $wpmm_s['company_name'] ) ? $wpmm_s['company_name'] : '';
    ?>
    <div class="wpmm-header" style="display:flex;flex-direction:column;gap:0;">

        <!-- Row 1: Greenskeeper product logo left, site name/URL right -->
        <div class="wpmm-header-row wpmm-header-row-primary"
             style="display:flex;flex-direction:row;align-items:center;width:100%;gap:14px;padding-bottom:10px;">
            <img src="<?php echo esc_url( WPMM_PLUGIN_URL . 'admin/images/greenskeeper-logo.png?v=' . WPMM_VERSION ); ?>"
                 alt="Greenskeeper"
                 width="140" height="42"
                 style="height:42px;width:auto;max-width:200px;display:block;flex-shrink:0;">
            <div style="margin-left:auto;display:flex;align-items:center;gap:5px;font-size:12px;color:#93c5fd;flex-wrap:wrap;justify-content:flex-end;">
                <span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
                <span style="color:rgba(147,197,253,.4);">&mdash;</span>
                <span style="color:rgba(147,197,253,.75);"><?php echo esc_url( get_bloginfo( 'url' ) ); ?></span>
                <?php if ( wpmm_is_network_context() ) : ?>
                    <span class="wpmm-badge wpmm-badge-network" style="margin-left:8px;">Network Admin</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Row 2: Agency logo + company name — secondary, only when configured -->
        <?php if ( $wpmm_logo || $wpmm_company ) : ?>
        <div class="wpmm-header-row wpmm-header-row-secondary"
             style="display:flex;flex-direction:row;align-items:center;width:100%;gap:10px;padding-top:8px;border-top:1px solid rgba(255,255,255,.2);">
            <span style="font-size:11px;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.06em;margin-right:4px;">Managed by</span>
            <?php if ( $wpmm_logo ) : ?>
                <img src="<?php echo esc_url( $wpmm_logo ); ?>"
                     alt="<?php echo esc_attr( $wpmm_company ?: 'Agency Logo' ); ?>"
                     style="height:18px;width:auto;max-width:64px;object-fit:contain;filter:brightness(0) invert(1);opacity:.8;flex-shrink:0;display:block;">
            <?php endif; ?>
            <?php if ( $wpmm_company ) : ?>
                <span style="font-size:12px;font-weight:600;color:rgba(255,255,255,.7);white-space:nowrap;"><?php echo esc_html( $wpmm_company ); ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
    <nav class="wpmm-tabs">
        <?php foreach ( $links as $slug => $info ) : ?>
            <a href="<?php echo esc_url( wpmm_subpage_url( $slug ) ); ?>"
               class="wpmm-tab <?php echo $active_slug === $slug ? 'active' : ''; ?>">
                <span class="dashicons dashicons-<?php echo esc_attr( $info['icon'] ); ?>"></span>
                <?php echo esc_html( $info['label'] ); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}


// =========================================================================
// Capability gate (shared by all renderers)
// =========================================================================
function wpmm_cap_gate() {
    if ( wpmm_user_can_access() ) {
        return;
    }
    wp_die( esc_html__( 'You do not have permission to access this page.', 'greenskeeper' ) );
}

/**
 * Returns true if the current user may access the plugin.
 *
 * Access logic:
 * 1. Network admin always requires manage_network.
 * 2. If any administrator has been explicitly granted wpmm_access, only those
 *    users may access the plugin.
 * 3. If no administrator has wpmm_access yet (fresh install or legacy install),
 *    fall back to manage_options so the plugin is not accidentally locked.
 */
function wpmm_user_can_access() {
    if ( wpmm_is_network_context() ) {
        return current_user_can( 'manage_network' );
    }
    // Check if wpmm_access has been explicitly granted to anyone.
    $granted = get_users( [
        'capability' => 'wpmm_access',
        'fields'     => 'ID',
        'number'     => 1,
    ] );
    if ( empty( $granted ) ) {
        // No explicit grants yet — fall back to manage_options.
        return current_user_can( 'manage_options' );
    }
    return current_user_can( 'wpmm_access' );
}



// =========================================================================
// MULTISITE SITE SCOPE HELPERS
// =========================================================================

/**
 * Returns the currently-selected site ID from the ?site_id= URL parameter.
 * 0 means "All Sites". Only meaningful in Network Admin context.
 */
function wpmm_get_scoped_site_id() {
    if ( ! wpmm_is_network_context() ) {
        return get_current_blog_id();
    }
    return absint( $_GET['site_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

/**
 * Render the Site Scope Bar — shown at top of Updates, Spam Log, and Settings
 * (Spam Filter card) pages when in Network Admin context.
 *
 * @param string $page_slug  The current page slug, used to build the base URL.
 */
function wpmm_site_scope_bar( $page_slug ) {
    if ( ! wpmm_is_network_context() ) {
        return;
    }

    $sites      = get_sites( [ 'number' => 200, 'orderby' => 'domain', 'order' => 'ASC' ] );
    $current_id = wpmm_get_scoped_site_id();
    $base_url   = wpmm_subpage_url( $page_slug );

    ?>
    <div class="wpmm-scope-bar" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px;padding:12px 16px;background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;">
        <span class="dashicons dashicons-networking" style="color:#2563eb;font-size:18px;flex-shrink:0;"></span>
        <strong style="font-size:13px;color:#1e3a5f;white-space:nowrap;">Network Scope:</strong>
        <select id="wpmm-site-scope" class="wpmm-input" style="max-width:300px;min-width:200px;">
            <option value="0" <?php selected( $current_id, 0 ); ?>>— All Sites —</option>
            <?php foreach ( $sites as $site ) :
                switch_to_blog( $site->blog_id );
                $site_name = get_bloginfo( 'name' );
                $site_url  = get_bloginfo( 'url' );
                restore_current_blog();
            ?>
            <option value="<?php echo absint( $site->blog_id ); ?>"
                <?php selected( $current_id, (int) $site->blog_id ); ?>>
                <?php echo esc_html( $site_name ); ?> &mdash; <?php echo esc_html( $site_url ); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm" id="wpmm-scope-apply"
                data-base-url="<?php echo esc_url( $base_url ); ?>">
            <span class="dashicons dashicons-filter"></span> Apply
        </button>
        <?php if ( $current_id > 0 ) :
            switch_to_blog( $current_id );
            $active_name = get_bloginfo( 'name' );
            restore_current_blog();
        ?>
        <span style="font-size:12px;color:#2563eb;font-weight:600;">
            <span class="dashicons dashicons-admin-site" style="font-size:14px;vertical-align:middle;"></span>
            Showing: <?php echo esc_html( $active_name ); ?>
        </span>
        <a href="<?php echo esc_url( $base_url ); ?>"
           style="font-size:12px;color:#6b7280;">&times; All Sites</a>
        <?php endif; ?>
    </div>
    <?php
}

// =========================================================================
// SPAM LOG page
// =========================================================================
function wpmm_render_spam_log() {
    wpmm_cap_gate();
    global $wpdb;

    // In Network Admin, scope bar selects which site's spam log to show.
    $wpmm_scope_site_id = wpmm_get_scoped_site_id();
    if ( wpmm_is_network_context() && $wpmm_scope_site_id > 0 ) {
        switch_to_blog( $wpmm_scope_site_id );
    }

    $spam_table = $wpdb->prefix . 'wpmm_spam_log';
    $per_page   = 25;
    $curr_page  = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $offset     = ( $curr_page - 1 ) * $per_page;

    // Filters
    $filter_rule = sanitize_text_field( wp_unslash( $_GET['rule'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $filter_ip   = sanitize_text_field( wp_unslash( $_GET['ip']   ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $where = 'WHERE 1=1';
    $args  = [];
    if ( $filter_rule ) {
        $where  .= ' AND rule = %s';
        $args[]  = $filter_rule;
    }
    if ( $filter_ip ) {
        $where  .= ' AND author_ip = %s';
        $args[]  = $filter_ip;
    }

    if ( $args ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$spam_table} {$where} ORDER BY blocked_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            array_merge( $args, [ $per_page, $offset ] )
        ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$spam_table} {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            $args
        ) );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$spam_table} ORDER BY blocked_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $per_page, $offset
        ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$spam_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    // Stats for the summary row
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $stats = $wpdb->get_results(
        "SELECT rule, COUNT(*) AS cnt FROM {$spam_table} GROUP BY rule ORDER BY cnt DESC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    );

    if ( wpmm_is_network_context() && $wpmm_scope_site_id > 0 ) {
        restore_current_blog();
    }

    $total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
    $page_url    = add_query_arg(
        $wpmm_scope_site_id > 0 ? [ 'site_id' => $wpmm_scope_site_id ] : [],
        wpmm_subpage_url( WPMM_SLUG_SPAM )
    );

    $rule_labels = [
        'honeypot'       => 'Honeypot',
        'too_fast'       => 'Too Fast',
        'blocked_ip'     => 'Blocked IP',
        'keyword'        => 'Keyword',
        'too_many_links' => 'Too Many Links',
        'duplicate'      => 'Duplicate',
        'akismet'        => 'Akismet',
    ];
    $rule_colours = [
        'honeypot'       => '#7c3aed',
        'too_fast'       => '#d97706',
        'blocked_ip'     => '#dc2626',
        'keyword'        => '#0369a1',
        'too_many_links' => '#b45309',
        'duplicate'      => '#4b5563',
        'akismet'        => '#16a34a',
    ];

    ?>
    <div class="wpmm-wrap">
        <?php wpmm_page_header( WPMM_SLUG_SPAM ); ?>
        <div class="wpmm-content">

            <?php wpmm_site_scope_bar( WPMM_SLUG_SPAM ); ?>

            <!-- Stats summary -->
            <?php if ( $total > 0 ) : ?>
            <div class="wpmm-card" style="margin-bottom:16px;">
                <h2 class="wpmm-card-title">
                    <span class="dashicons dashicons-chart-bar"></span> Spam Blocked — All Time
                </h2>
                <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:4px;">
                    <?php foreach ( $stats as $stat ) : ?>
                    <div style="background:#f8fafc;border:1px solid var(--wpmm-border);border-radius:6px;padding:10px 16px;min-width:130px;">
                        <div style="font-size:22px;font-weight:700;color:<?php echo esc_attr( $rule_colours[ $stat->rule ] ?? '#1e3a5f' ); ?>;">
                            <?php echo absint( $stat->cnt ); ?>
                        </div>
                        <div style="font-size:12px;color:var(--wpmm-gray);margin-top:2px;">
                            <?php echo esc_html( $rule_labels[ $stat->rule ] ?? $stat->rule ); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="background:#f8fafc;border:2px solid var(--wpmm-blue);border-radius:6px;padding:10px 16px;min-width:130px;">
                        <div style="font-size:22px;font-weight:700;color:var(--wpmm-blue);">
                            <?php echo absint( $total ); ?>
                        </div>
                        <div style="font-size:12px;color:var(--wpmm-gray);margin-top:2px;">Total Blocked</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filter bar + bulk actions -->
            <div class="wpmm-card">
                <h2 class="wpmm-card-title">
                    <span class="dashicons dashicons-shield"></span> Blocked Comment Attempts
                    <span class="wpmm-badge wpmm-badge-error" style="margin-left:8px;"><?php echo absint( $total ); ?></span>
                </h2>

                <!-- Filters -->
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:var(--wpmm-gray);margin-bottom:4px;">Filter by Rule</label>
                        <select id="wpmm-spam-filter-rule" class="wpmm-input" style="max-width:180px;">
                            <option value="">All Rules</option>
                            <?php foreach ( $rule_labels as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"
                                    <?php selected( $filter_rule, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:var(--wpmm-gray);margin-bottom:4px;">Filter by IP</label>
                        <input type="text" id="wpmm-spam-filter-ip" class="wpmm-input"
                               style="max-width:160px;" placeholder="e.g. 192.168.1.1"
                               value="<?php echo esc_attr( $filter_ip ); ?>">
                    </div>
                    <button type="button" class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm" id="wpmm-spam-apply-filters">
                        <span class="dashicons dashicons-filter"></span> Apply
                    </button>
                    <?php if ( $filter_rule || $filter_ip ) : ?>
                        <a href="<?php echo esc_url( $page_url ); ?>"
                           class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm">&times; Clear</a>
                    <?php endif; ?>

                    <div style="margin-left:auto;display:flex;gap:8px;">
                        <button type="button" class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm" id="wpmm-spam-delete-selected"
                                title="Delete selected rows">
                            <span class="dashicons dashicons-trash"></span> Delete Selected
                        </button>
                        <button type="button" class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm" id="wpmm-spam-clear-all"
                                style="color:var(--wpmm-red);border-color:#fca5a5;"
                                title="Delete all spam log entries">
                            <span class="dashicons dashicons-trash"></span> Clear All
                        </button>
                    </div>
                </div>

                <div id="wpmm-spam-action-msg" style="margin-bottom:10px;font-size:13px;"></div>

                <?php if ( empty( $rows ) ) : ?>
                    <div class="wpmm-all-good">
                        <span class="dashicons dashicons-shield-alt"></span>
                        <strong>No blocked attempts recorded.</strong><br>
                        <?php if ( empty( wpmm_get_settings()['spam_filter_enabled'] ) ) : ?>
                            Enable spam filtering in <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_SETTINGS ) ); ?>">Settings</a> to start logging blocked spam.
                        <?php else : ?>
                            Spam filtering is active. Blocked attempts will appear here.
                        <?php endif; ?>
                    </div>
                <?php else : ?>

                <table class="wpmm-table" id="wpmm-spam-table">
                    <thead>
                        <tr>
                            <th style="width:32px;">
                                <input type="checkbox" id="wpmm-spam-select-all" title="Select all">
                            </th>
                            <th>Date / Time</th>
                            <th>Rule</th>
                            <th>IP Address</th>
                            <th>Author</th>
                            <th>Content Preview</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) : ?>
                        <tr data-id="<?php echo absint( $row->id ); ?>"
                            data-ip="<?php echo esc_attr( $row->author_ip ); ?>">
                            <td>
                                <input type="checkbox" class="wpmm-spam-cb" value="<?php echo absint( $row->id ); ?>">
                            </td>
                            <td style="white-space:nowrap;font-size:12px;">
                                <?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $row->blocked_at ) ) ); ?>
                            </td>
                            <td>
                                <span class="wpmm-spam-rule-badge" style="background:<?php echo esc_attr( $rule_colours[ $row->rule ] ?? '#6b7280' ); ?>20;color:<?php echo esc_attr( $rule_colours[ $row->rule ] ?? '#6b7280' ); ?>;border:1px solid <?php echo esc_attr( $rule_colours[ $row->rule ] ?? '#6b7280' ); ?>40;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;white-space:nowrap;">
                                    <?php echo esc_html( $rule_labels[ $row->rule ] ?? $row->rule ); ?>
                                </span>
                            </td>
                            <td style="font-size:12px;font-family:monospace;">
                                <?php echo esc_html( $row->author_ip ); ?>
                            </td>
                            <td style="font-size:12px;">
                                <?php if ( $row->author_name ) : ?>
                                    <strong><?php echo esc_html( $row->author_name ); ?></strong><br>
                                <?php endif; ?>
                                <?php if ( $row->author_email ) : ?>
                                    <span style="color:var(--wpmm-gray);"><?php echo esc_html( $row->author_email ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12px;color:#374151;max-width:280px;">
                                <?php
                                $preview = wp_strip_all_tags( $row->comment_content ?? '' );
                                echo esc_html( mb_strlen( $preview ) > 120 ? mb_substr( $preview, 0, 120 ) . '…' : $preview );
                                ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <?php if ( $row->author_ip ) : ?>
                                <button type="button"
                                        class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm wpmm-spam-blocklist-ip"
                                        data-ip="<?php echo esc_attr( $row->author_ip ); ?>"
                                        title="Add this IP to the blocklist">
                                    <span class="dashicons dashicons-shield-alt"></span> Block IP
                                </button>
                                <?php endif; ?>
                                <button type="button"
                                        class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm wpmm-spam-delete-row"
                                        data-id="<?php echo absint( $row->id ); ?>"
                                        style="color:var(--wpmm-red);border-color:#fca5a5;"
                                        title="Delete this entry">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ( $total_pages > 1 ) : ?>
                <div class="wpmm-pagination" style="margin-top:16px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                    <?php if ( $curr_page > 1 ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( [ 'paged' => $curr_page - 1, 'rule' => $filter_rule, 'ip' => $filter_ip ], $page_url ) ); ?>"
                           class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm">&laquo; Previous</a>
                    <?php endif; ?>
                    <span style="font-size:13px;color:var(--wpmm-gray);">
                        Page <?php echo absint( $curr_page ); ?> of <?php echo absint( $total_pages ); ?>
                        &mdash; <?php echo absint( $total ); ?> entries
                    </span>
                    <?php if ( $curr_page < $total_pages ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( [ 'paged' => $curr_page + 1, 'rule' => $filter_rule, 'ip' => $filter_ip ], $page_url ) ); ?>"
                           class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php endif; // empty rows ?>

            </div><!-- .wpmm-card -->

            <?php wpmm_tip_card(); ?>
        </div><!-- .wpmm-content -->
    </div><!-- .wpmm-wrap -->
    <?php
}


// =========================================================================
// Tip card — call wpmm_tip_card() inside any page's .wpmm-content div
// =========================================================================
function wpmm_tip_card() {
    ?>
    <div class="wpmm-card wpmm-tip-card">
        <div class="wpmm-tip-inner">
            <span class="wpmm-tip-coffee">&#9749;</span>
            <div class="wpmm-tip-body">
                <h2 class="wpmm-card-title" style="margin-bottom:6px;">
                    Enjoying Greenskeeper?
                </h2>
                <p class="wpmm-tip-text">
                    If this plugin saves you time, consider buying the author a coffee &mdash;
                    every tip is appreciated and helps support continued development.
                </p>
                <div class="wpmm-tip-actions">
                    <a href="https://www.paypal.com/ncp/payment/NQVL9AFHQ2ALG"
                       target="_blank" rel="noopener noreferrer"
                       class="wpmm-tip-btn wpmm-tip-btn-paypal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:5px;"><path d="M7.144 19.532l1.049-5.751c.11-.606.691-1.002 1.304-.948 2.155.19 6.242.258 7.891-3.484 1.985-4.51-1.551-6.58-4.59-6.58H7.29c-.56 0-1.039.407-1.131.958L4.01 17.981a.696.696 0 0 0 .687.811h2.169a.756.756 0 0 0 .745-.636l.277-1.52a.758.758 0 0 1 .745-.636h.511c3.358 0 6.163-1.396 6.952-5.437.347-1.775.037-3.218-.82-4.205 2.404.857 3.543 2.864 2.841 6.025-.91 4.204-4.139 5.52-7.836 5.52H9.33a.756.756 0 0 0-.745.636l-.55 3.019a.696.696 0 0 1-.687.588H5.193a.42.42 0 0 1-.415-.48l.366-2.159z"/></svg>
                        Tip via PayPal
                    </a>
                    <span class="wpmm-tip-venmo">
                        or &nbsp;<strong>Venmo</strong>&nbsp;
                        <span class="wpmm-tip-venmo-handle">&#64;dswks</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// =========================================================================
// Non-page renderer (parent slug — should never be reached directly,
// but we redirect to Dashboard just in case a user lands on it)
// =========================================================================
function wpmm_render_no_page() {
    wpmm_cap_gate();
    wp_safe_redirect( wpmm_subpage_url( WPMM_SLUG_DASHBOARD ) );
    exit;
}

// =========================================================================
// DASHBOARD page
// =========================================================================
function wpmm_render_dashboard() {
    wpmm_cap_gate();
    global $wpdb;

    $s            = wpmm_get_settings();
    $client_email = ! empty( $s['client_email'] ) ? $s['client_email'] : get_option( 'wpmm_client_email', '' );
    $company      = ! empty( $s['company_name'] ) ? $s['company_name'] : '';
    $logo_url     = ! empty( $s['logo_url'] )     ? $s['logo_url']     : '';
    $default_admin = wpmm_get_default_admin();

    // Most recent update session
    $log_table   = $wpdb->prefix . 'wpmm_update_log';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is safe (prefix + fixed string), no user input involved.
    $last_row    = $wpdb->get_row( "SELECT updated_at, COUNT(*) AS total, SUM(status='success') AS successes, SUM(status!='success') AS failures FROM {$log_table} ORDER BY updated_at DESC LIMIT 1" );
    $last_update = $last_row && $last_row->updated_at
        ? date_i18n( 'F j, Y 	 g:i A', strtotime( $last_row->updated_at ) )
        : null;
    ?>
    <div class="wpmm-wrap">
        <?php wpmm_page_header( WPMM_SLUG_DASHBOARD ); ?>
        <div class="wpmm-content">

            <!-- ── Status summary ── -->
            <div class="wpmm-dashboard-summary">

                <div class="wpmm-summary-card wpmm-summary-update">
                    <span class="wpmm-summary-icon dashicons dashicons-calendar-alt"></span>
                    <div>
                        <span class="wpmm-summary-label">Most Recent Update</span>
                        <span class="wpmm-summary-value">
                            <?php echo $last_update ? esc_html( $last_update ) : 'No updates recorded yet'; ?>
                        </span>
                    </div>
                </div>

                <div class="wpmm-summary-card wpmm-summary-email">
                    <span class="wpmm-summary-icon dashicons dashicons-email-alt"></span>
                    <div>
                        <span class="wpmm-summary-label">Client Email</span>
                        <span class="wpmm-summary-value">
                            <?php if ( $client_email ) : ?>
                                <?php echo esc_html( $client_email ); ?>
                                <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_SETTINGS ) ); ?>" class="wpmm-summary-edit">Edit</a>
                            <?php else : ?>
                                <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_SETTINGS ) ); ?>" style="color:#2563eb;">Set in Settings &rarr;</a>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="wpmm-summary-card wpmm-summary-admin">
                    <span class="wpmm-summary-icon dashicons dashicons-admin-users"></span>
                    <div>
                        <span class="wpmm-summary-label">Default Administrator</span>
                        <span class="wpmm-summary-value">
                            <?php if ( $default_admin ) : ?>
                                <?php echo esc_html( $default_admin->display_name ); ?>
                                &mdash; <span style="color:#6b7280;"><?php echo esc_html( $default_admin->user_email ); ?></span>
                                <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_SETTINGS ) ); ?>" class="wpmm-summary-edit">Change</a>
                            <?php else : ?>
                                <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_SETTINGS ) ); ?>" style="color:#2563eb;">Set in Settings &rarr;</a>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <?php if ( $company || $logo_url ) : ?>
                <div class="wpmm-summary-card wpmm-summary-brand">
                    <span class="wpmm-summary-icon dashicons dashicons-building"></span>
                    <div>
                        <span class="wpmm-summary-label">Agency</span>
                        <span class="wpmm-summary-value">
                            <?php if ( $logo_url ) : ?>
                                <img src="<?php echo esc_url( $logo_url ); ?>" style="height:28px;vertical-align:middle;margin-right:8px;" alt="">
                            <?php endif; ?>
                            <?php echo esc_html( $company ); ?>
                            <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_SETTINGS ) ); ?>" class="wpmm-summary-edit">Edit</a>
                        </span>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- .wpmm-dashboard-summary -->

            <!-- ── Quick nav ── -->
            <div class="wpmm-dashboard-tiles">
                <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_UPDATES ) ); ?>" class="wpmm-tile">
                    <span class="dashicons dashicons-update"></span>
                    <strong>Run Updates</strong>
                    <span>Scan and apply WordPress core, plugin &amp; theme updates</span>
                </a>
                <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_LOG ) ); ?>" class="wpmm-tile">
                    <span class="dashicons dashicons-list-view"></span>
                    <strong>Update Log</strong>
                    <span>View paginated history of all past update sessions</span>
                </a>
                <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_EMAIL ) ); ?>" class="wpmm-tile">
                    <span class="dashicons dashicons-email-alt"></span>
                    <strong>Email Reports</strong>
                    <span>Send or resend maintenance reports to the client</span>
                </a>
                <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_SETTINGS ) ); ?>" class="wpmm-tile">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <strong>Settings</strong>
                    <span>Logo, company name, client email, administrator</span>
                </a>
                <?php
                $s_spam = wpmm_get_settings();
                if ( ! empty( $s_spam['spam_filter_enabled'] ) ) :
                ?>
                <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_SPAM ) ); ?>" class="wpmm-tile">
                    <span class="dashicons dashicons-shield"></span>
                    <strong>Spam Log</strong>
                    <span>Review blocked comment attempts and manage the IP blocklist</span>
                </a>
                <?php endif; ?>
            </div>

            <?php wpmm_tip_card(); ?>
        </div><!-- .wpmm-content -->
    </div><!-- .wpmm-wrap -->
    <?php
}

// =========================================================================
// UPDATES page
// =========================================================================
function wpmm_render_updates() {
    wpmm_cap_gate();

    // Multisite scope — which site are we updating?
    $scoped_site_id   = wpmm_get_scoped_site_id(); // 0 = All Sites (network), >0 = single site
    $is_network_scope = wpmm_is_network_context() && $scoped_site_id === 0;

    // Switch context to the scoped site for settings/admin reads.
    if ( wpmm_is_network_context() && $scoped_site_id > 0 ) {
        switch_to_blog( $scoped_site_id );
    }
    $s            = wpmm_get_settings();
    $client_email = ! empty( $s['client_email'] ) ? $s['client_email'] : get_option( 'wpmm_client_email', '' );
    $default_admin = wpmm_get_default_admin();
    if ( wpmm_is_network_context() && $scoped_site_id > 0 ) {
        restore_current_blog();
    }

    // All administrators for the per-session override selector
    $admins = get_users( [ 'role' => 'administrator', 'orderby' => 'display_name' ] );
    ?>
    <div class="wpmm-wrap">
        <?php wpmm_page_header( WPMM_SLUG_UPDATES ); ?>
        <div class="wpmm-content">

            <?php wpmm_site_scope_bar( WPMM_SLUG_UPDATES ); ?>

            <!-- Client email / settings notice -->
            <?php if ( $client_email ) : ?>
                <div class="wpmm-notice wpmm-notice-info" style="margin-bottom:16px;">
                    <span class="dashicons dashicons-email"></span>
                    Report will be sent to: <strong><?php echo esc_html( $client_email ); ?></strong>
                    &mdash; <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_SETTINGS ) ); ?>">Edit in Settings</a>
                </div>
            <?php else : ?>
                <div class="wpmm-notice wpmm-notice-info" style="margin-bottom:16px;">
                    <span class="dashicons dashicons-warning"></span>
                    No client email address saved.
                    <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_SETTINGS ) ); ?>">Set it in Settings</a>
                    before running updates.
                </div>
            <?php endif; ?>

            <?php
            // ── Avada detection ─────────────────────────────────────────────────────
            // Check whether the Avada theme is active or installed.
            // If so, show a contextual notice about the required update order for
            // Avada Core and Avada Builder, and a link to Avada's Plugins dashboard.
            $avada_active  = ( get_template() === 'Avada' || get_stylesheet() === 'Avada' );
            $avada_theme   = wp_get_theme( 'Avada' );
            $avada_present = $avada_theme->exists();

            // Detect whether Avada Core / Builder have pending updates in the transient.
            $avada_plugin_files = [
                'avada-core'    => 'avada-core/avada-core.php',
                'avada-builder' => 'fusion-builder/fusion-builder.php',
            ];
            $update_plugins = get_site_transient( 'update_plugins' );
            $avada_pending  = [];
            foreach ( $avada_plugin_files as $label => $file ) {
                if ( ! empty( $update_plugins->response[ $file ] ) ) {
                    $avada_pending[ $label ] = $update_plugins->response[ $file ]->new_version ?? '';
                }
            }

            if ( $avada_active || $avada_present ) :
            ?>
            <div class="wpmm-avada-notice wpmm-card" style="margin-bottom:16px;border-left:4px solid #7c3aed;">
                <h2 class="wpmm-card-title" style="color:#7c3aed;">
                    <span class="dashicons dashicons-info-outline"></span> Avada Theme Detected
                </h2>
                <p style="margin:0 0 10px;font-size:13px;">
                    Avada requires its companion plugins to be updated in a specific order.
                    Always update them in this sequence to avoid compatibility issues:
                </p>
                <ol style="margin:0 0 12px 20px;font-size:13px;line-height:1.8;">
                    <li><strong>Avada theme</strong> — update the theme first</li>
                    <li><strong>Avada Core</strong> — update immediately after the theme</li>
                    <li><strong>Avada Builder</strong> — update last</li>
                </ol>
                <?php if ( $avada_pending ) : ?>
                    <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;padding:10px 14px;margin-bottom:12px;">
                        <strong style="color:#7c3aed;font-size:13px;">
                            <span class="dashicons dashicons-warning" style="font-size:14px;vertical-align:middle;"></span>
                            Avada companion plugin updates are available:
                        </strong>
                        <ul style="margin:6px 0 0 18px;font-size:13px;line-height:1.8;">
                            <?php foreach ( $avada_pending as $label => $ver ) : ?>
                                <li>
                                    <strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $label ) ) ); ?></strong>
                                    <?php if ( $ver ) : ?>
                                        &rarr; <?php echo esc_html( $ver ); ?>
                                    <?php endif; ?>
                                    — will appear in the Plugins section below
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <p style="margin:0;font-size:12px;color:#6b7280;">
                    <strong>Avada Patches</strong> are managed separately through the
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=avada-maintenance' ) ); ?>" style="color:#7c3aed;">
                        Avada &rarr; Maintenance &rarr; Plugins &amp; Add-Ons
                    </a> page and do not appear in the standard WordPress update list.
                    Check that page after completing updates here.
                </p>
            </div>
            <?php endif; ?>

            <!-- Administrator override for this session -->
            <div class="wpmm-card" style="margin-bottom:16px;">
                <h2 class="wpmm-card-title" style="margin-bottom:12px;">
                    <span class="dashicons dashicons-admin-users"></span> Performing Administrator
                </h2>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <select id="wpmm-performing-admin" class="wpmm-input" style="max-width:320px;">
                        <?php foreach ( $admins as $admin ) : ?>
                            <option value="<?php echo absint( $admin->ID ); ?>"
                                data-name="<?php echo esc_attr( $admin->display_name ); ?>"
                                data-email="<?php echo esc_attr( $admin->user_email ); ?>"
                                <?php selected( $default_admin ? $default_admin->ID : 0, $admin->ID ); ?>>
                                <?php echo esc_html( $admin->display_name ); ?> &lt;<?php echo esc_html( $admin->user_email ); ?>&gt;
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="wpmm-hint" style="margin:0;">
                        Defaults to the administrator set in
                        <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_SETTINGS ) ); ?>">Settings</a>.
                        Override here for this session only.
                    </p>
                </div>
            </div>

            <?php if ( $is_network_scope ) : ?>
            <div class="wpmm-notice wpmm-notice-info" style="margin-bottom:16px;">
                <span class="dashicons dashicons-networking"></span>
                <strong>Network scope:</strong> Showing all updates available across the network.
                Plugins and themes activated on any site are included. Updates apply network-wide.
            </div>
            <?php elseif ( wpmm_is_network_context() && $scoped_site_id > 0 ) :
                switch_to_blog( $scoped_site_id );
                $scoped_name = get_bloginfo( 'name' );
                restore_current_blog();
            ?>
            <div class="wpmm-notice wpmm-notice-info" style="margin-bottom:16px;">
                <span class="dashicons dashicons-admin-site"></span>
                <strong>Site scope:</strong> Showing updates for plugins and themes
                activated on <strong><?php echo esc_html( $scoped_name ); ?></strong> only.
            </div>
            <?php endif; ?>

            <!-- site_id passed to AJAX so JS can filter and log correctly -->
            <input type="hidden" id="wpmm-scope-site-id" value="<?php echo absint( $scoped_site_id ); ?>">

            <!-- Action toolbar -->
            <div class="wpmm-toolbar">
                <button class="wpmm-btn wpmm-btn-primary" id="wpmm-refresh-updates">
                    <span class="dashicons dashicons-update"></span> Refresh Updates
                </button>
                <button class="wpmm-btn wpmm-btn-success" id="wpmm-update-selected">
                    <span class="dashicons dashicons-yes-alt"></span> Update Selected
                </button>
            </div>

            <div id="wpmm-update-sections">
                <p class="wpmm-loading">
                    <span class="dashicons dashicons-update wpmm-spin"></span> Loading available updates&hellip;
                </p>
            </div>

            <!-- Progress bar — visible while a batch is running -->
            <div id="wpmm-global-progress" hidden>
                <div class="wpmm-progress-wrap">
                    <div class="wpmm-progress-bar-track">
                        <div class="wpmm-progress-bar-fill" id="wpmm-progress-fill" style="width:0%"></div>
                    </div>
                    <div class="wpmm-progress-label" id="wpmm-progress-label">
                        Preparing updates&hellip;
                    </div>
                </div>
            </div>

            <!-- Success banner — shown only after a batch completes with updates present -->
            <div id="wpmm-global-success" class="wpmm-notice wpmm-notice-success" hidden>
                <span class="dashicons dashicons-yes-alt"></span>
                All selected items were updated successfully!
                <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_EMAIL ) ); ?>" style="margin-left:12px;">
                    Send Report Email &rarr;
                </a>
            </div>

            <?php wpmm_tip_card(); ?>
        </div>
    </div>
    <?php
}

// =========================================================================

// UPDATE LOG page — sessions grouped with accordion expand
// =========================================================================
function wpmm_render_log() {
    wpmm_cap_gate();
    global $wpdb;

    $log_search = sanitize_text_field( wp_unslash( $_GET['log_search'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter, no state change.
    $log_from   = sanitize_text_field( wp_unslash( $_GET['log_from']   ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $log_to     = sanitize_text_field( wp_unslash( $_GET['log_to']     ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $sess_page  = max( 1, absint( $_GET['sess_page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $log_table  = $wpdb->prefix . 'wpmm_update_log';
    $page_url   = wpmm_subpage_url( WPMM_SLUG_LOG );

    // Per-page limit — default 20, selectable to 50 or 100.
    $allowed_limits = [ 20, 50, 100 ];
    $per_page       = absint( wp_unslash( $_GET['per_page'] ?? 20 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display preference, no state change.
    if ( ! in_array( $per_page, $allowed_limits, true ) ) {
        $per_page = 20;
    }

    // Build WHERE for filtering.
    $where = 'WHERE 1=1';
    $args  = [];
    if ( $log_search ) {
        $where  .= ' AND (item_name LIKE %s OR item_slug LIKE %s)';
        $args[]  = '%' . $wpdb->esc_like( $log_search ) . '%';
        $args[]  = '%' . $wpdb->esc_like( $log_search ) . '%';
    }
    if ( $log_from ) { $where .= ' AND DATE(updated_at) >= %s'; $args[] = $log_from; }
    if ( $log_to )   { $where .= ' AND DATE(updated_at) <= %s'; $args[] = $log_to; }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe (prefix + fixed string); user values are passed via prepare() args.
    $sql  = "SELECT * FROM {$log_table} {$where} ORDER BY updated_at DESC";
    $rows = $args
        ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

    // Group rows into sessions in PHP.
    $sessions      = [];
    $session_items = [];

    foreach ( (array) $rows as $row ) {
        $key = ( isset( $row->session_id ) && $row->session_id !== '' )
            ? $row->session_id
            : 'legacy-' . substr( $row->updated_at, 0, 10 );

        if ( ! isset( $session_items[ $key ] ) ) {
            $sessions[]            = $key;
            $session_items[ $key ] = [];
        }
        $session_items[ $key ][] = $row;
    }

    // Sort items within each session ascending (oldest first = session start time at top).
    foreach ( $session_items as $key => &$items_ref ) {
        usort( $items_ref, function( $a, $b ) {
            return strcmp( $a->updated_at, $b->updated_at );
        } );
    }
    unset( $items_ref );

    $sess_total = count( $sessions );
    $sess_pages = max( 1, (int) ceil( $sess_total / $per_page ) );
    $sess_page  = min( $sess_page, $sess_pages ); // clamp to valid range
    $page_keys  = array_slice( $sessions, ( $sess_page - 1 ) * $per_page, $per_page );

    // Base URL carries all active filters (used by pagination and limit links).
    $filter_qs = '';
    if ( $log_search ) $filter_qs .= '&log_search=' . urlencode( $log_search );
    if ( $log_from )   $filter_qs .= '&log_from='   . urlencode( $log_from );
    if ( $log_to )     $filter_qs .= '&log_to='     . urlencode( $log_to );
    if ( $per_page !== 20 ) $filter_qs .= '&per_page=' . $per_page;

    // Build a pagination block (reused at top and bottom).
    $make_pagination = function() use ( $sess_total, $sess_pages, $sess_page, $per_page, $page_url, $filter_qs ) {
        if ( $sess_total === 0 ) return '';

        $prev_url = ( $sess_page > 1 )
            ? esc_url( $page_url . $filter_qs . '&sess_page=' . ( $sess_page - 1 ) )
            : '';
        $next_url = ( $sess_page < $sess_pages )
            ? esc_url( $page_url . $filter_qs . '&sess_page=' . ( $sess_page + 1 ) )
            : '';

        $from = ( $sess_page - 1 ) * $per_page + 1;
        $to   = min( $sess_page * $per_page, $sess_total );

        $out  = '<div class="wpmm-pagination-bar">';
        $out .= '<div class="wpmm-pagination-nav">';
        if ( $prev_url ) {
            $out .= '<a href="' . $prev_url . '" class="wpmm-page-btn wpmm-page-prev">'
                  . '<span class="dashicons dashicons-arrow-left-alt2"></span> Previous</a>';
        } else {
            $out .= '<span class="wpmm-page-btn wpmm-page-prev disabled">'
                  . '<span class="dashicons dashicons-arrow-left-alt2"></span> Previous</span>';
        }
        $out .= '<span class="wpmm-page-info">'
              . 'Sessions ' . $from . '–' . $to . ' of ' . $sess_total . '</span>';
        if ( $next_url ) {
            $out .= '<a href="' . $next_url . '" class="wpmm-page-btn wpmm-page-next">'
                  . 'Next <span class="dashicons dashicons-arrow-right-alt2"></span></a>';
        } else {
            $out .= '<span class="wpmm-page-btn wpmm-page-next disabled">'
                  . 'Next <span class="dashicons dashicons-arrow-right-alt2"></span></span>';
        }
        $out .= '</div>';
        $out .= '</div>';
        return $out;
    };
    ?>
    <div class="wpmm-wrap">
        <?php wpmm_page_header( WPMM_SLUG_LOG ); ?>
        <div class="wpmm-content">

            <div class="wpmm-card">

                <!-- Card header: title + limit selector + refresh -->
                <div class="wpmm-card-title-row">
                    <h2 class="wpmm-card-title" style="margin:0;">
                        <span class="dashicons dashicons-list-view"></span> Update History
                        <span class="wpmm-card-title-sub">
                            <?php echo absint( $sess_total ); ?> session<?php echo $sess_total !== 1 ? 's' : ''; ?>
                        </span>
                    </h2>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <label for="wpmm-per-page" style="font-size:12px;color:var(--wpmm-gray);white-space:nowrap;">
                            Show:
                        </label>
                        <select id="wpmm-per-page" class="wpmm-input wpmm-input-sm"
                                style="width:auto;padding:4px 8px;font-size:13px;"
                                onchange="location.href=this.value">
                            <?php foreach ( [ 20, 50, 100 ] as $lim ) :
                                $lim_qs = $page_url . $filter_qs;
                                // Remove existing per_page param so we rebuild cleanly
                                $lim_qs = preg_replace( '/&per_page=\d+/', '', $lim_qs );
                                if ( $lim !== 20 ) $lim_qs .= '&per_page=' . $lim;
                            ?>
                                <option value="<?php echo esc_url( $lim_qs ); ?>"
                                    <?php selected( $per_page, $lim ); ?>>
                                    Last <?php echo absint( $lim ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="<?php echo esc_url( $page_url ); ?>" class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm">
                            <span class="dashicons dashicons-update"></span> Refresh
                        </a>
                    </div>
                </div>

                <!-- Search / filter form -->
                <form method="get" class="wpmm-search-form" id="wpmm-log-search-form">
                    <input type="hidden" name="page" value="<?php echo esc_attr( WPMM_SLUG_LOG ); ?>">
                    <?php if ( $per_page !== 20 ) : ?>
                        <input type="hidden" name="per_page" value="<?php echo absint( $per_page ); ?>">
                    <?php endif; ?>
                    <div class="wpmm-search-row">
                        <div class="wpmm-autocomplete-wrap">
                            <input type="text"
                                   name="log_search"
                                   id="wpmm-log-search"
                                   value="<?php echo esc_attr( $log_search ); ?>"
                                   placeholder="Search plugins, themes&hellip;"
                                   class="wpmm-input"
                                   autocomplete="off"
                                   aria-label="Search update history"
                                   aria-autocomplete="list"
                                   aria-controls="wpmm-autocomplete-list"
                                   aria-expanded="false">
                            <ul class="wpmm-autocomplete-list" id="wpmm-autocomplete-list" hidden role="listbox"></ul>
                        </div>
                        <input type="date" name="log_from" value="<?php echo esc_attr( $log_from ); ?>"
                            class="wpmm-input" title="From date">
                        <input type="date" name="log_to" value="<?php echo esc_attr( $log_to ); ?>"
                            class="wpmm-input" title="To date">
                        <button class="wpmm-btn wpmm-btn-primary" type="submit">
                            <span class="dashicons dashicons-search"></span> Search
                        </button>
                        <a href="<?php echo esc_url( $page_url ); ?>" class="wpmm-btn wpmm-btn-secondary">Reset</a>
                    </div>
                </form>

                <?php if ( $page_keys ) : ?>

                <!-- Pagination — top -->
                <?php echo wp_kses_post( $make_pagination() ); ?>

                <!-- Session accordion -->
                <div class="wpmm-session-list">
                <?php foreach ( $page_keys as $i => $key ) :
                    $items         = $session_items[ $key ];
                    $is_legacy     = ( strpos( $key, 'legacy-' ) === 0 );
                    $first         = $items[0];
                    $acc_id        = 'wpmm-acc-' . sanitize_key( $key );
                    $dt            = new DateTime( $first->updated_at );
                    $date_label    = $dt->format( 'F j, Y' );
                    $time_label    = $dt->format( 'g:i A' );
                    $is_external   = ( strpos( $key, 'ext-' ) === 0 );
                    $success_count = 0;
                    $fail_count    = 0;
                    foreach ( $items as $r ) {
                        if ( $r->status === 'success' ) { $success_count++; } else { $fail_count++; }
                    }
                    $is_open = ( $i === 0 );
                ?>
                    <div class="wpmm-session-row" id="<?php echo esc_attr( $acc_id ); ?>">

                        <button class="wpmm-session-header" type="button"
                                aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
                                data-target="<?php echo esc_attr( $acc_id . '-body' ); ?>">
                            <span class="wpmm-session-header-left">
                                <span class="wpmm-session-icon dashicons dashicons-backup"></span>
                                <span class="wpmm-session-meta">
                                    <strong class="wpmm-session-date"><?php echo esc_html( $date_label ); ?></strong>
                                    <span class="wpmm-session-time"><?php echo esc_html( $time_label ); ?></span>
                                    <?php if ( $is_legacy ) : ?>
                                        <span class="wpmm-badge wpmm-badge-legacy">Legacy</span>
                                    <?php endif; ?>
                                    <?php if ( $is_external ) : ?>
                                        <span class="wpmm-badge" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;font-size:10px;">External</span>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <span class="wpmm-session-header-right">
                                <span class="wpmm-session-counts">
                                    <?php if ( $success_count > 0 ) : ?>
                                        <span class="wpmm-count-pill wpmm-count-success">
                                            &#10003; <?php echo absint( $success_count ); ?> updated
                                        </span>
                                    <?php endif; ?>
                                    <?php if ( $fail_count > 0 ) : ?>
                                        <span class="wpmm-count-pill wpmm-count-fail">
                                            &#10007; <?php echo absint( $fail_count ); ?> failed
                                        </span>
                                    <?php endif; ?>
                                    <span class="wpmm-count-pill wpmm-count-total">
                                        <?php echo count( $items ); ?> item<?php echo count( $items ) !== 1 ? 's' : ''; ?>
                                    </span>
                                </span>
                                <span class="wpmm-session-caret" aria-hidden="true">&#9660;</span>
                            </span>
                        </button>

                        <div class="wpmm-session-body"
                             id="<?php echo esc_attr( $acc_id . '-body' ); ?>"
                             <?php echo $is_open ? '' : 'hidden'; ?>>
                            <table class="wpmm-table wpmm-session-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Version</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ( $items as $row ) :
                                    $err_info = ( $row->status !== 'success' && ! empty( $row->error_code ) )
                                        ? wpmm_explain_error( $row->error_code )
                                        : null;
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $row->item_name ); ?></strong></td>
                                        <td><?php echo esc_html( ucfirst( $row->item_type ) ); ?></td>
                                        <td>
                                            <?php echo esc_html( $row->old_version ); ?>
                                            <?php if ( $row->new_version ) : ?>
                                                &rarr; <strong><?php echo esc_html( $row->new_version ); ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ( $row->status === 'success' ) : ?>
                                                <span class="wpmm-badge wpmm-badge-success">&#10003; Success</span>
                                            <?php else : ?>
                                                <span class="wpmm-badge wpmm-badge-error">
                                                    &#10007; <?php echo $err_info ? esc_html( $err_info['label'] ) : 'Failed'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="wpmm-notes-cell">
                                            <?php if ( $row->status !== 'success' && $err_info ) : ?>
                                                <details class="wpmm-error-details">
                                                    <summary><?php echo esc_html( $err_info['label'] ); ?></summary>
                                                    <p><?php echo esc_html( $err_info['detail'] ); ?></p>
                                                    <p class="wpmm-action-note">
                                                        <strong>Action:</strong> <?php echo esc_html( $err_info['action'] ); ?>
                                                    </p>
                                                </details>
                                            <?php elseif ( ! empty( $row->message ) ) : ?>
                                                <span class="wpmm-note-text"><?php echo esc_html( $row->message ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

                <!-- Pagination — bottom -->
                <?php echo wp_kses_post( $make_pagination() ); ?>

                <?php else : ?>
                    <div class="wpmm-empty" style="padding:30px 0;">
                        <p style="margin:0 0 8px;">
                            No update sessions found<?php echo ( $log_search || $log_from || $log_to ) ? ' matching your criteria' : ''; ?>.
                        </p>
                        <?php if ( ! $log_search && ! $log_from && ! $log_to ) : ?>
                            <p style="font-size:12px;color:#9ca3af;margin:0;">
                                Updates run through this plugin will appear here automatically.
                                <a href="<?php echo esc_url( $page_url ); ?>">Refresh the page</a> if you have
                                just completed an update session.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>


            <!-- ── Database Diagnostic ────────────────────────────────── -->
            <?php $diag = wpmm_db_diagnostic(); ?>
            <details class="wpmm-card wpmm-debug-card" id="wpmm-diag-panel">
                <summary class="wpmm-debug-summary">
                    <span class="dashicons dashicons-database"></span>
                    Database Diagnostic
                    <?php if ( isset( $diag['update_log']['count'] ) ) : ?>
                        <span class="wpmm-card-title-sub">
                            <?php echo absint( $diag['update_log']['count'] ); ?> row<?php echo $diag['update_log']['count'] !== 1 ? 's' : ''; ?> in update log
                        </span>
                    <?php endif; ?>
                </summary>
                <div class="wpmm-debug-body" id="wpmm-diag-body">

                    <!-- Version -->
                    <p>
                        <strong>Plugin version:</strong> <?php echo esc_html( $diag['plugin_version'] ); ?> &nbsp;|&nbsp;
                        <strong>DB schema version:</strong>
                        <?php if ( $diag['db_version'] === $diag['plugin_version'] ) : ?>
                            <span style="color:#16a34a;"><?php echo esc_html( $diag['db_version'] ); ?> &#10003;</span>
                        <?php else : ?>
                            <span style="color:#dc2626;"><?php echo esc_html( $diag['db_version'] ); ?> — mismatch!</span>
                        <?php endif; ?>
                        &nbsp;
                        <button type="button" class="wpmm-btn wpmm-btn-sm wpmm-btn-primary" id="wpmm-force-upgrade-btn">
                            <span class="dashicons dashicons-update"></span> Force DB Upgrade Now
                        </button>
                        <span id="wpmm-upgrade-result" style="margin-left:10px;font-size:12px;"></span>
                    </p>

                    <?php if ( $diag['last_error'] ) : ?>
                        <p><strong>Last DB error:</strong> <span style="color:#dc2626;"><?php echo esc_html( $diag['last_error'] ); ?></span></p>
                    <?php endif; ?>

                    <?php foreach ( [ 'update_log' => 'wpmm_update_log', 'email_log' => 'wpmm_email_log' ] as $key => $label ) :
                        $t = $diag[ $key ];
                    ?>
                        <h4 style="margin:16px 0 6px;color:#1e3a5f;"><?php echo esc_html( $t['table'] ); ?></h4>
                        <?php if ( ! $t['exists'] ) : ?>
                            <p style="color:#dc2626;"><strong>Table does not exist.</strong> Click "Force DB Upgrade Now" above.</p>
                        <?php else : ?>
                            <p>
                                <strong>Columns:</strong>
                                <?php foreach ( $t['cols'] as $col ) :
                                    $needed = ( $key === 'update_log' )
                                        ? [ 'id','session_id','item_name','item_type','item_slug','old_version','new_version','status','error_code','message','updated_at' ]
                                        : [ 'id','session_id','to_email','subject','body','status','sent_at' ];
                                    $ok = in_array( $col, $needed, true );
                                ?>
                                    <code style="background:<?php echo $ok ? '#dcfce7' : '#fee2e2'; ?>;padding:1px 5px;border-radius:3px;margin:0 2px;font-size:11px;"><?php echo esc_html( $col ); ?></code>
                                <?php endforeach; ?>
                                <?php
                                $needed = ( $key === 'update_log' )
                                    ? [ 'session_id', 'error_code' ]
                                    : [ 'session_id' ];
                                foreach ( $needed as $req ) :
                                    if ( ! in_array( $req, $t['cols'], true ) ) :
                                ?>
                                    <code style="background:#fee2e2;padding:1px 5px;border-radius:3px;margin:0 2px;font-size:11px;">&#10007; MISSING: <?php echo esc_html( $req ); ?></code>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </p>
                            <p><strong>Row count:</strong> <?php echo absint( $t['count'] ); ?></p>
                            <?php if ( ! empty( $t['recent'] ) ) : ?>
                                <table class="wpmm-table" style="margin-top:4px;font-size:12px;">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <?php if ( $key === 'update_log' ) : ?>
                                                <th>Session ID</th><th>Item</th><th>Type</th><th>Status</th><th>Updated At</th>
                                            <?php else : ?>
                                                <th>Session ID</th><th>To</th><th>Status</th><th>Sent At</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ( $t['recent'] as $r ) : ?>
                                        <tr>
                                            <td><?php echo (int) $r->id; ?></td>
                                            <td style="max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $r->session_id ?: '(empty)' ); ?></td>
                                            <?php if ( $key === 'update_log' ) : ?>
                                                <td><?php echo esc_html( $r->item_name ); ?></td>
                                                <td><?php echo esc_html( $r->item_type ); ?></td>
                                                <td><?php echo esc_html( $r->status ); ?></td>
                                                <td><?php echo esc_html( $r->updated_at ); ?></td>
                                            <?php else : ?>
                                                <td><?php echo esc_html( $r->to_email ); ?></td>
                                                <td><?php echo esc_html( $r->status ); ?></td>
                                                <td><?php echo esc_html( $r->sent_at ); ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <p style="color:#d97706;">No rows in this table yet.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>

                </div>
            </details>

            <?php wpmm_tip_card(); ?>
        </div>
    </div>
    <?php
}

// =========================================================================
// EMAIL REPORTS page  — with email preview modal
// =========================================================================
function wpmm_render_email() {
    wpmm_cap_gate();
    $wpmm_email_scope_id = wpmm_is_network_context()
        ? absint( $_GET['site_id'] ?? 0 ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        : 0;
    global $wpdb;

    $saved_email  = get_option( 'wpmm_client_email', '' );
    $email_table  = $wpdb->prefix . 'wpmm_email_log';
    $email_rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        "SELECT * FROM {$email_table} ORDER BY sent_at DESC LIMIT 50" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    );
    ?>
    <div class="wpmm-wrap">
        <?php wpmm_page_header( WPMM_SLUG_EMAIL ); ?>
        <div class="wpmm-content">
            <?php wpmm_site_scope_bar( WPMM_SLUG_EMAIL ); ?>
            <input type="hidden" id="wpmm-email-scope-site-id" value="<?php echo absint( $wpmm_email_scope_id ); ?>">

            <!-- Send form -->
            <div class="wpmm-card">
                <h2 class="wpmm-card-title">
                    <span class="dashicons dashicons-email-alt"></span> Send Maintenance Report
                </h2>

                <div class="wpmm-form-row">
                    <label for="wpmm-email-to-tab">Recipient Email</label>
                    <?php if ( $saved_email ) : ?>
                        <div class="wpmm-saved-email-display" style="margin-bottom:8px;">
                            <span class="wpmm-saved-email-address"><?php echo esc_html( $saved_email ); ?></span>
                            <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_DASHBOARD ) ); ?>"
                               class="wpmm-edit-email-link">Edit on Dashboard</a>
                        </div>
                        <input type="hidden" id="wpmm-email-to-tab" value="<?php echo esc_attr( $saved_email ); ?>">
                    <?php else : ?>
                        <input type="email" id="wpmm-email-to-tab"
                            value="" placeholder="client@example.com" class="wpmm-input">
                        <p class="wpmm-hint">
                            <a href="<?php echo esc_url( wpmm_subpage_url( WPMM_SLUG_DASHBOARD ) ); ?>">
                                Save a default address on the Dashboard
                            </a> to pre-fill this field automatically.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="wpmm-form-row">
                    <label for="wpmm-email-subject-tab">Subject</label>
                    <input type="text" id="wpmm-email-subject-tab"
                        value="<?php echo esc_attr( get_bloginfo('name') . ' [' . get_bloginfo('url') . '] Weekly WordPress Upgrades and Maintenance' ); ?>"
                        class="wpmm-input"
                        data-base-subject="<?php echo esc_attr( get_bloginfo('name') . ' [' . get_bloginfo('url') . '] Weekly WordPress Upgrades and Maintenance' ); ?>">
                </div>

                <div class="wpmm-form-row">
                    <label for="wpmm-report-date">Report Week-Ending Date</label>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <input type="text" id="wpmm-report-date"
                            class="wpmm-input wpmm-date-input"
                            placeholder="Select date to append to subject&hellip;"
                            readonly
                            style="max-width:240px;">
                        <a href="#" id="wpmm-clear-report-date" style="font-size:12px;display:none;">
                            &times; Clear date
                        </a>
                    </div>
                    <p class="wpmm-hint">
                        Choosing a date appends <em>for week of: [date]</em> to the subject line above.
                        Leave blank to send without a date.
                    </p>
                </div>

                <div class="wpmm-email-preview">
                    <p><strong>Email Template</strong></p>
                    <div class="wpmm-email-meta">
                        <?php
                        $ls = get_option( 'wpmm_last_session', [] );
                        $perf_admin = wpmm_get_default_admin();
                        if ( $perf_admin ) {
                            $first_name = get_user_meta( $perf_admin->ID, 'first_name', true );
                            $last_name  = get_user_meta( $perf_admin->ID, 'last_name',  true );
                            $full_name  = trim( $first_name . ' ' . $last_name ) ?: $perf_admin->display_name;
                            $from_label = $full_name . ' <' . $perf_admin->user_email . '>';
                        } else {
                            $from_label = get_option( 'admin_email' );
                        }
                        ?>
                        <span><strong>From:</strong> <?php echo esc_html( $from_label ); ?></span>
                        <?php if ( ! empty( $ls['session_id'] ) ) : ?>
                            <span><strong>Content:</strong>
                                Updates from session on
                                <?php echo esc_html( date_i18n( 'F j, Y 	 g:i A', strtotime( $ls['updated_at'] ) ) ); ?>
                            </span>
                        <?php else : ?>
                            <span><strong>Content:</strong> Most recent 100 log entries (no session found)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Hidden: passes the last persisted session to the AJAX handler.
                     JS will override this with the in-page sessionId when updates
                     were run in the same browser session. -->
                <input type="hidden" id="wpmm-last-session-id"
                    value="<?php echo esc_attr( isset( $ls['session_id'] ) ? $ls['session_id'] : '' ); ?>">

                <hr style="border:none;border-top:1px solid var(--wpmm-border);margin:20px 0;">

                <!-- Update Notes — merged into send card -->
                <div class="wpmm-form-row">
                    <label for="wpmm-update-notes">
                        <strong>Update Notes</strong>
                        <span style="font-weight:400;color:var(--wpmm-gray);margin-left:6px;">(optional — appended to the email above the footer)</span>
                    </label>
                    <textarea id="wpmm-update-notes"
                              class="wpmm-input"
                              rows="4"
                              placeholder="e.g. Avada and its companion plugins were updated manually this week. These updates require license authentication through the Avada dashboard and cannot be applied automatically by Greenskeeper&hellip;"
                              style="resize:vertical;line-height:1.6;"></textarea>
                </div>

                <hr style="border:none;border-top:1px solid var(--wpmm-border);margin:20px 0;">

                <!-- Additional Manual Updates — merged into send card -->
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:6px;">
                        Additional Manual Updates
                        <span style="font-weight:400;color:var(--wpmm-gray);margin-left:6px;">(optional — plugins or themes updated outside Greenskeeper)</span>
                    </label>
                    <p class="wpmm-hint" style="margin-bottom:12px;">
                        Use this for licensed plugins updated through their own dashboards (e.g. Avada, ACF Pro, Gravity Forms).
                        These entries appear as a separate table in the email report.
                    </p>

                    <div id="wpmm-manual-rows">
                        <!-- Repeater rows injected by JS -->
                    </div>
                    <div style="margin-top:10px;">
                        <button type="button" class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm" id="wpmm-add-manual-row">
                            <span class="dashicons dashicons-plus-alt"></span> Add Manual Update
                        </button>
                    </div>

                    <template id="wpmm-manual-row-template">
                        <div class="wpmm-manual-row">
                            <select class="wpmm-input wpmm-manual-select" data-field="name">
                                <option value="">— Select plugin or theme —</option>
                                <?php
                                if ( ! function_exists( 'get_plugins' ) ) {
                                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                                }
                                $all_installed = get_plugins();
                                foreach ( $all_installed as $file => $data ) :
                                ?>
                                    <option value="<?php echo esc_attr( $data['Name'] ); ?>"
                                            data-version="<?php echo esc_attr( $data['Version'] ); ?>">
                                        <?php echo esc_html( $data['Name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php
                                $themes = wp_get_themes();
                                foreach ( $themes as $slug => $theme ) :
                                ?>
                                    <option value="<?php echo esc_attr( $theme->get('Name') ); ?>"
                                            data-version="<?php echo esc_attr( $theme->get('Version') ); ?>">
                                        <?php echo esc_html( $theme->get('Name') ); ?> (Theme)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="wpmm-manual-versions">
                                <div>
                                    <label class="wpmm-manual-label">Previous Version</label>
                                    <input type="text" class="wpmm-input wpmm-manual-old-version" data-field="old_version"
                                           placeholder="e.g. 6.7.1" style="max-width:120px;">
                                </div>
                                <div>
                                    <label class="wpmm-manual-label">Updated To</label>
                                    <input type="text" class="wpmm-input wpmm-manual-new-version" data-field="new_version"
                                           placeholder="e.g. 6.8.0" style="max-width:120px;">
                                </div>
                            </div>
                            <button type="button" class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm wpmm-manual-remove"
                                    title="Remove this entry">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </template>
                </div>

                <hr style="border:none;border-top:1px solid var(--wpmm-border);margin:20px 0;">

                <div class="wpmm-toolbar">
                    <button class="wpmm-btn wpmm-btn-primary" id="wpmm-send-email-btn">
                        <span class="dashicons dashicons-email"></span> Send Report Email
                    </button>
                </div>
                <div id="wpmm-email-send-result"></div>
            </div>

            <!-- Sent email history -->
            <div class="wpmm-card">
                <h2 class="wpmm-card-title">
                    <span class="dashicons dashicons-backup"></span> Sent Email History
                </h2>
                <?php if ( $email_rows ) : ?>
                <table class="wpmm-table">
                    <thead>
                        <tr>
                            <th>Sent At</th>
                            <th>To</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th style="text-align:center;">Preview</th>
                            <th>Resend</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $email_rows as $email ) : ?>
                        <tr>
                            <td><?php echo esc_html( $email->sent_at ); ?></td>
                            <td><?php echo esc_html( $email->to_email ); ?></td>
                            <td>
                                <?php
                                $subj = $email->subject;
                                echo esc_html( strlen( $subj ) > 65 ? substr( $subj, 0, 65 ) . '…' : $subj );
                                ?>
                            </td>
                            <td>
                                <?php if ( $email->status === 'sent' ) : ?>
                                    <span class="wpmm-badge wpmm-badge-success">Sent</span>
                                <?php else : ?>
                                    <span class="wpmm-badge wpmm-badge-error">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <button class="wpmm-preview-btn" type="button"
                                    title="Preview email"
                                    data-id="<?php echo absint( $email->id ); ?>"
                                    data-subject="<?php echo esc_attr( $email->subject ); ?>"
                                    data-to="<?php echo esc_attr( $email->to_email ); ?>"
                                    data-sent="<?php echo esc_attr( $email->sent_at ); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </td>
                            <td>
                                <button class="wpmm-btn wpmm-btn-sm wpmm-resend-btn"
                                    data-id="<?php echo absint( $email->id ); ?>">
                                    <span class="dashicons dashicons-controls-repeat"></span> Resend
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                    <p class="wpmm-empty">No emails have been sent yet.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- ── Email Preview Modal ─────────────────────────────────────────── -->
    <!-- Starts hidden via CSS class, not HTML hidden attribute, to avoid
         specificity conflicts with WordPress admin CSS -->
    <div id="wpmm-email-modal" class="wpmm-modal wpmm-modal-closed" role="dialog" aria-modal="true" aria-labelledby="wpmm-modal-title">
        <div class="wpmm-modal-overlay"></div>
        <div class="wpmm-modal-box">
            <div class="wpmm-modal-header">
                <div class="wpmm-modal-meta">
                    <h2 id="wpmm-modal-title" class="wpmm-modal-title">Email Preview</h2>
                    <p class="wpmm-modal-subtitle" id="wpmm-modal-subtitle"></p>
                </div>
                <button class="wpmm-modal-close" type="button" aria-label="Close preview">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="wpmm-modal-body">
                <!-- Loading spinner — visible by default when modal opens -->
                <div id="wpmm-modal-loading" class="wpmm-modal-loading-wrap">
                    <span class="dashicons dashicons-update wpmm-spin"></span> Loading email&hellip;
                </div>
                <!-- Iframe starts hidden; shown only once content is loaded -->
                <iframe id="wpmm-modal-iframe" class="wpmm-modal-iframe wpmm-hidden" title="Email preview" sandbox="allow-same-origin"></iframe>
            </div>
            <?php wpmm_tip_card(); ?>
        </div>
    </div>
    <?php
}
