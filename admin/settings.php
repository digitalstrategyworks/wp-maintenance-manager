<?php
/**
 * Settings page — Site Maintenance Manager.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── AJAX handler ──────────────────────────────────────────────────────────────
add_action( 'wp_ajax_wpmm_save_settings',     'wpmm_ajax_save_settings' );
add_action( 'wp_ajax_wpmm_generate_api_key',  'wpmm_ajax_generate_api_key' );
add_action( 'wp_ajax_wpmm_revoke_api_key',    'wpmm_ajax_revoke_api_key' );

function wpmm_ajax_generate_api_key() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }
    $new_key = wpmm_generate_api_key();
    $s = wpmm_get_settings();
    $s['api_key'] = $new_key;
    wpmm_save_settings( $s );
    wp_send_json_success( [
        'api_key'  => $new_key,
        'rest_url' => get_rest_url( null, 'smm/v1' ),
    ] );
}

function wpmm_ajax_revoke_api_key() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }
    $s = wpmm_get_settings();
    $s['api_key'] = '';
    wpmm_save_settings( $s );
    wp_send_json_success( [ 'message' => 'API key revoked. Remote access is now disabled.' ] );
}

function wpmm_ajax_save_settings() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $s = wpmm_get_settings();

    if ( array_key_exists( 'company_name', $_POST ) ) {
        $s['company_name'] = sanitize_text_field( wp_unslash( $_POST['company_name'] ) );
    }
    if ( array_key_exists( 'client_email', $_POST ) ) {
        $val = sanitize_email( wp_unslash( $_POST['client_email'] ) );
        $s['client_email'] = $val;
        update_option( 'wpmm_client_email', $val );
    }
    if ( array_key_exists( 'logo_url', $_POST ) ) {
        $s['logo_url'] = esc_url_raw( wp_unslash( $_POST['logo_url'] ) );
    }
    if ( array_key_exists( 'default_admin_id', $_POST ) ) {
        $s['default_admin_id'] = absint( $_POST['default_admin_id'] );
    }

    wpmm_save_settings( $s );
    wp_send_json_success( $s );
}

// ── Page renderer ─────────────────────────────────────────────────────────────

function wpmm_render_settings() {
    wpmm_cap_gate();
    $s = wpmm_get_settings();

    $admins           = get_users( [ 'role' => 'administrator', 'orderby' => 'display_name', 'order' => 'ASC' ] );
    $default_admin_id = (int) ( $s['default_admin_id'] ?? 0 );
    $company_name     = $s['company_name'] ?? '';
    $client_email     = $s['client_email'] ?? get_option( 'wpmm_client_email', '' );
    $logo_url         = $s['logo_url']     ?? '';

    // REST API key
    $api_key         = $s['api_key'] ?? '';

    // SMTP settings
    $smtp_mailer     = $s['smtp_mailer']     ?? 'default';
    $smtp_host       = $s['smtp_host']       ?? '';
    $smtp_port       = $s['smtp_port']       ?? 587;
    $smtp_enc        = $s['smtp_enc']        ?? 'tls';
    $smtp_username   = $s['smtp_username']   ?? '';
    $smtp_from_email = $s['smtp_from_email'] ?? '';
    $smtp_from_name  = $s['smtp_from_name']  ?? '';
    $has_password    = ! empty( $s['smtp_password_enc'] );
    ?>
    <div class="wpmm-wrap">
        <?php wpmm_page_header( WPMM_SLUG_SETTINGS ); ?>
        <div class="wpmm-content">

            <div id="wpmm-settings-msg"></div>

            <!-- ── Company & Branding ──────────────────────────────────── -->
            <div class="wpmm-card">
                <h2 class="wpmm-card-title">
                    <span class="dashicons dashicons-building"></span> Company &amp; Branding
                </h2>
                <p class="wpmm-card-desc">
                    Your logo and company name appear in the plugin header on every page
                    and in each email report sent to clients.
                </p>

                <!-- Logo -->
                <div class="wpmm-settings-group">
                    <div class="wpmm-settings-group-label">
                        <strong>Company Logo</strong>
                        <span>Shown in the header and at the top of email reports.</span>
                    </div>
                    <div class="wpmm-settings-group-control">
                        <div class="wpmm-logo-well" id="wpmm-logo-well">
                            <?php if ( $logo_url ) : ?>
                                <img src="<?php echo esc_url( $logo_url ); ?>"
                                     alt="Company logo" class="wpmm-logo-preview-img"
                                     id="wpmm-logo-preview-img">
                                <button type="button" class="wpmm-logo-remove-btn"
                                        id="wpmm-logo-remove" title="Remove logo">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            <?php else : ?>
                                <div class="wpmm-logo-empty-state" id="wpmm-logo-empty">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <span>No logo uploaded yet</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" id="wpmm-logo-url"
                               value="<?php echo esc_attr( $logo_url ); ?>">
                        <div class="wpmm-logo-actions">
                            <button type="button" id="wpmm-logo-upload-btn"
                                    class="wpmm-btn wpmm-btn-primary wpmm-btn-sm">
                                <span class="dashicons dashicons-upload"></span>
                                <?php echo $logo_url ? 'Change Logo' : 'Upload Logo'; ?>
                            </button>
                        </div>
                        <p class="wpmm-hint">PNG, JPG or SVG &mdash; at least 300&thinsp;px wide, transparent background recommended.</p>
                    </div>
                </div>

                <div class="wpmm-settings-divider"></div>

                <!-- Company name -->
                <div class="wpmm-settings-group">
                    <div class="wpmm-settings-group-label">
                        <strong>Company Name</strong>
                        <span>Your agency or company name, shown on reports and in the header.</span>
                    </div>
                    <div class="wpmm-settings-group-control">
                        <div class="wpmm-inline-edit-wrap" id="wpmm-company-wrap">
                            <?php if ( $company_name ) : ?>
                                <div class="wpmm-saved-field-display" id="wpmm-company-display" style="display:flex;">
                                    <span class="wpmm-saved-field-value"
                                          id="wpmm-company-text"><?php echo esc_html( $company_name ); ?></span>
                                    <a href="#" class="wpmm-edit-link" data-target="wpmm-company">Edit</a>
                                </div>
                                <div class="wpmm-field-edit-row wpmm-hidden" id="wpmm-company-edit" style="display:none;">
                                    <input type="text" id="wpmm-company-input"
                                           value="<?php echo esc_attr( $company_name ); ?>"
                                           class="wpmm-input"
                                           placeholder="Digital Strategy Works">
                                    <button type="button" class="wpmm-btn wpmm-btn-primary wpmm-btn-sm"
                                            data-save="company">
                                        <span class="dashicons dashicons-yes"></span> Save
                                    </button>
                                    <a href="#" class="wpmm-cancel-link"
                                       data-target="wpmm-company">Cancel</a>
                                </div>
                            <?php else : ?>
                                <div class="wpmm-saved-field-display wpmm-hidden" id="wpmm-company-display" style="display:none;">
                                    <span class="wpmm-saved-field-value"
                                          id="wpmm-company-text"></span>
                                    <a href="#" class="wpmm-edit-link" data-target="wpmm-company">Edit</a>
                                </div>
                                <div class="wpmm-field-edit-row" id="wpmm-company-edit" style="display:flex;">
                                    <input type="text" id="wpmm-company-input" value=""
                                           class="wpmm-input"
                                           placeholder="Digital Strategy Works">
                                    <button type="button" class="wpmm-btn wpmm-btn-primary wpmm-btn-sm"
                                            data-save="company">
                                        <span class="dashicons dashicons-yes"></span> Save
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Client Contact ──────────────────────────────────────── -->
            <div class="wpmm-card">
                <h2 class="wpmm-card-title">
                    <span class="dashicons dashicons-businessman"></span> Client Contact
                </h2>
                <p class="wpmm-card-desc">
                    The email address maintenance reports are sent to.
                    Save it once here and it pre-populates everywhere in the plugin.
                </p>

                <div class="wpmm-settings-group">
                    <div class="wpmm-settings-group-label">
                        <strong>Client Email Address</strong>
                        <span>Recipient of all weekly maintenance report emails.</span>
                    </div>
                    <div class="wpmm-settings-group-control">
                        <div class="wpmm-inline-edit-wrap" id="wpmm-email-wrap">
                            <?php if ( $client_email ) : ?>
                                <div class="wpmm-saved-field-display" id="wpmm-email-display" style="display:flex;">
                                    <span class="wpmm-saved-field-value"
                                          id="wpmm-email-text"><?php echo esc_html( $client_email ); ?></span>
                                    <a href="#" class="wpmm-edit-link" data-target="wpmm-email">Edit</a>
                                </div>
                                <div class="wpmm-field-edit-row wpmm-hidden" id="wpmm-email-edit" style="display:none;">
                                    <input type="email" id="wpmm-email-input"
                                           value="<?php echo esc_attr( $client_email ); ?>"
                                           class="wpmm-input"
                                           placeholder="client@example.com">
                                    <button type="button" class="wpmm-btn wpmm-btn-primary wpmm-btn-sm"
                                            data-save="email">
                                        <span class="dashicons dashicons-yes"></span> Save
                                    </button>
                                    <a href="#" class="wpmm-cancel-link"
                                       data-target="wpmm-email">Cancel</a>
                                </div>
                            <?php else : ?>
                                <div class="wpmm-saved-field-display wpmm-hidden" id="wpmm-email-display" style="display:none;">
                                    <span class="wpmm-saved-field-value"
                                          id="wpmm-email-text"></span>
                                    <a href="#" class="wpmm-edit-link" data-target="wpmm-email">Edit</a>
                                </div>
                                <div class="wpmm-field-edit-row" id="wpmm-email-edit" style="display:flex;">
                                    <input type="email" id="wpmm-email-input" value=""
                                           class="wpmm-input"
                                           placeholder="client@example.com">
                                    <button type="button" class="wpmm-btn wpmm-btn-primary wpmm-btn-sm"
                                            data-save="email">
                                        <span class="dashicons dashicons-yes"></span> Save
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Administrators ──────────────────────────────────────── -->
            <div class="wpmm-card">
                <h2 class="wpmm-card-title">
                    <span class="dashicons dashicons-admin-users"></span> Site Administrators
                </h2>
                <p class="wpmm-card-desc">
                    Select the default administrator who performs updates.
                    Their name and email appear on maintenance reports and in the From: header.
                    This can be overridden per-session on the Updates page.
                </p>

                <div class="wpmm-admin-table-wrap">
                    <table class="wpmm-table wpmm-settings-admin-table">
                        <thead>
                            <tr>
                                <th class="wpmm-col-radio">Default</th>
                                <th>Administrator</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Member Since</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $admins as $admin ) :
                            $selected = ( $default_admin_id === $admin->ID );
                        ?>
                            <tr class="<?php echo $selected ? 'wpmm-admin-row-selected' : ''; ?>">
                                <td class="wpmm-col-radio">
                                    <input type="radio"
                                           name="wpmm_default_admin"
                                           class="wpmm-admin-radio"
                                           value="<?php echo absint( $admin->ID ); ?>"
                                           <?php checked( $default_admin_id, $admin->ID ); ?>>
                                </td>
                                <td>
                                    <div class="wpmm-admin-identity">
                                        <?php echo get_avatar( $admin->ID, 32, '', '', [ 'class' => 'wpmm-admin-avatar' ] ); ?>
                                        <span class="wpmm-admin-name">
                                            <?php echo esc_html( $admin->display_name ); ?>
                                            <?php if ( $selected ) : ?>
                                                <span class="wpmm-badge wpmm-badge-success wpmm-default-badge">Default</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="wpmm-text-muted"><?php echo esc_html( $admin->user_login ); ?></td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr( $admin->user_email ); ?>"
                                       class="wpmm-email-link">
                                        <?php echo esc_html( $admin->user_email ); ?>
                                    </a>
                                </td>
                                <td class="wpmm-text-muted">
                                    <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $admin->user_registered ) ) ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="wpmm-settings-save-row">
                    <button type="button" class="wpmm-btn wpmm-btn-primary"
                            id="wpmm-save-admin-btn">
                        <span class="dashicons dashicons-yes"></span> Save Default Administrator
                    </button>
                    <span id="wpmm-admin-save-msg" class="wpmm-save-feedback"></span>
                </div>
            </div>

            <!-- ── Remote API Access ───────────────────────────────── -->
            <div class="wpmm-card" id="wpmm-api-card">
                <h2 class="wpmm-card-title">
                    <span class="dashicons dashicons-rest-api"></span> Remote API Access
                </h2>
                <p class="wpmm-card-desc">
                    Generate an API key to allow a remote hub site to manage updates on this site
                    via the Site Maintenance Manager REST API. Keep this key secure &mdash; anyone
                    with it can run updates and send reports on your behalf.
                </p>

                <div class="wpmm-settings-group">
                    <div class="wpmm-settings-group-label">
                        <strong>API Key</strong>
                        <span>Used by your hub site to authenticate requests.</span>
                    </div>
                    <div class="wpmm-settings-group-control">
                        <?php if ( $api_key ) : ?>
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <code id="wpmm-api-key-display"
                                      style="background:#f1f5f9;border:1px solid var(--wpmm-border);padding:8px 14px;border-radius:6px;font-size:13px;letter-spacing:.04em;flex:1;max-width:440px;word-break:break-all;">
                                    <?php echo esc_html( $api_key ); ?>
                                </code>
                                <button type="button" id="wpmm-copy-api-key"
                                        class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm"
                                        data-key="<?php echo esc_attr( $api_key ); ?>">
                                    <span class="dashicons dashicons-clipboard"></span> Copy
                                </button>
                            </div>
                            <p class="wpmm-hint" style="margin-top:8px;">
                                REST API base URL:
                                <code><?php echo esc_url( get_rest_url( null, 'smm/v1' ) ); ?></code>
                            </p>
                        <?php else : ?>
                            <p style="color:var(--wpmm-gray);font-size:13px;margin:0 0 12px;">
                                No API key generated yet. Remote API access is disabled until you generate a key.
                            </p>
                        <?php endif; ?>

                        <div style="display:flex;align-items:center;gap:10px;margin-top:<?php echo $api_key ? '12px' : '0'; ?>">
                            <button type="button" id="wpmm-generate-api-key"
                                    class="wpmm-btn wpmm-btn-primary wpmm-btn-sm">
                                <span class="dashicons dashicons-update"></span>
                                <?php echo $api_key ? 'Rotate Key' : 'Generate API Key'; ?>
                            </button>
                            <?php if ( $api_key ) : ?>
                                <button type="button" id="wpmm-revoke-api-key"
                                        class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm"
                                        style="color:var(--wpmm-red);border-color:#fca5a5;">
                                    <span class="dashicons dashicons-trash"></span> Revoke Key
                                </button>
                            <?php endif; ?>
                            <span id="wpmm-api-key-msg" class="wpmm-save-feedback"></span>
                        </div>

                        <?php if ( $api_key ) : ?>
                        <div class="wpmm-api-endpoints" style="margin-top:16px;background:#f8fafc;border:1px solid var(--wpmm-border);border-radius:6px;padding:14px 16px;">
                            <p style="margin:0 0 8px;font-size:12px;font-weight:700;color:var(--wpmm-blue);text-transform:uppercase;letter-spacing:.05em;">Available Endpoints</p>
                            <?php
                            $base = get_rest_url( null, 'smm/v1' );
                            $endpoints = [
                                [ 'GET',  '/status',      'Site health snapshot' ],
                                [ 'GET',  '/updates',     'Scan for available updates' ],
                                [ 'POST', '/update',      'Run a single update' ],
                                [ 'GET',  '/log',         'Fetch paginated update log' ],
                                [ 'POST', '/send-report', 'Send the maintenance email report' ],
                                [ 'POST', '/rotate-key',  'Generate a new API key' ],
                            ];
                            foreach ( $endpoints as $ep ) :
                            ?>
                                <div style="display:flex;gap:10px;align-items:baseline;padding:4px 0;border-bottom:1px solid var(--wpmm-border);font-size:12px;">
                                    <code style="background:<?php echo $ep[0] === 'GET' ? '#dcfce7' : '#eff6ff'; ?>;color:<?php echo $ep[0] === 'GET' ? '#15803d' : '#1d4ed8'; ?>;padding:2px 6px;border-radius:3px;font-weight:700;min-width:38px;text-align:center;"><?php echo esc_html( $ep[0] ); ?></code>
                                    <code style="color:var(--wpmm-blue2);"><?php echo esc_html( $base . $ep[1] ); ?></code>
                                    <span style="color:var(--wpmm-gray);"><?php echo esc_html( $ep[2] ); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── SMTP / Email Delivery ────────────────────────────── -->
            <div class="wpmm-card" id="wpmm-smtp-card">
                <h2 class="wpmm-card-title">
                    <span class="dashicons dashicons-email-alt2"></span> SMTP &amp; Email Delivery
                </h2>
                <p class="wpmm-card-desc">
                    By default WordPress sends email via PHP&rsquo;s <code>mail()</code> function,
                    which many hosting providers block or which major inboxes mark as spam.
                    Configure a dedicated SMTP server or third-party email service here to ensure
                    maintenance reports are reliably delivered.
                </p>

                <!-- Mailer selector -->
                <div class="wpmm-settings-group">
                    <div class="wpmm-settings-group-label">
                        <strong>Email Service</strong>
                        <span>Choose how WordPress sends email.</span>
                    </div>
                    <div class="wpmm-settings-group-control">
                        <div class="wpmm-mailer-grid" id="wpmm-mailer-grid">
                            <?php
                            $mailers = [
                                'default'   => [ 'label' => 'WordPress Default',  'sub' => 'PHP mail() — no changes', 'icon' => 'dashicons-wordpress' ],
                                'smtp'      => [ 'label' => 'SMTP (Manual)',       'sub' => 'Any SMTP server',          'icon' => 'dashicons-admin-network' ],
                                'sendgrid'  => [ 'label' => 'SendGrid',            'sub' => '100 emails/day free',      'icon' => 'dashicons-email-alt' ],
                                'mailgun'   => [ 'label' => 'Mailgun',             'sub' => '5,000 emails/mo free',     'icon' => 'dashicons-email-alt' ],
                                'brevo'     => [ 'label' => 'Brevo',               'sub' => '300 emails/day free',      'icon' => 'dashicons-email-alt' ],
                                'sendlayer' => [ 'label' => 'SendLayer',           'sub' => 'Simple &amp; affordable',  'icon' => 'dashicons-email-alt' ],
                                'smtpcom'   => [ 'label' => 'SMTP.com',            'sub' => '50K email free trial',     'icon' => 'dashicons-email-alt' ],
                                'gmail'     => [ 'label' => 'Gmail / Google',      'sub' => 'Gmail or Workspace',       'icon' => 'dashicons-google' ],
                                'microsoft' => [ 'label' => 'Microsoft / Outlook', 'sub' => 'Outlook, Office 365',      'icon' => 'dashicons-admin-site-alt3' ],
                            ];
                            foreach ( $mailers as $key => $m ) :
                                $active = ( $smtp_mailer === $key );
                            ?>
                                <button type="button"
                                        class="wpmm-mailer-tile <?php echo $active ? 'wpmm-mailer-active' : ''; ?>"
                                        data-mailer="<?php echo esc_attr( $key ); ?>">
                                    <span class="dashicons <?php echo esc_attr( $m['icon'] ); ?> wpmm-mailer-icon"></span>
                                    <span class="wpmm-mailer-label"><?php echo esc_html( $m['label'] ); ?></span>
                                    <span class="wpmm-mailer-sub"><?php echo wp_kses_post( $m['sub'] ); ?></span>
                                    <?php if ( $active ) : ?>
                                        <span class="wpmm-mailer-check">&#10003;</span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="wpmm-smtp-mailer" value="<?php echo esc_attr( $smtp_mailer ); ?>">
                    </div>
                </div>

                <div class="wpmm-settings-divider"></div>

                <!-- Provider help text -->
                <div id="wpmm-mailer-help" class="wpmm-mailer-help-wrap">
                    <div class="wpmm-mailer-help" data-for="default">
                        <p>WordPress will use PHP&rsquo;s built-in <code>mail()</code> function. No additional configuration is needed, but emails may be unreliable on many hosting providers.</p>
                    </div>
                    <div class="wpmm-mailer-help" data-for="smtp">
                        <p>Enter the SMTP details provided by your email host or provider. Common ports: <strong>587</strong> (TLS/STARTTLS) &mdash; <strong>465</strong> (SSL) &mdash; <strong>25</strong> (no encryption).</p>
                    </div>
                    <div class="wpmm-mailer-help" data-for="sendgrid">
                        <p><strong>SendGrid:</strong> Free plan — 100 emails/day. Create a free account at <a href="https://sendgrid.com" target="_blank" rel="noopener">sendgrid.com</a>, then go to <em>Settings → API Keys → Create API Key</em> (choose <em>Restricted Access → Mail Send</em>). Enter the API key below as the Password. Username is always <code>apikey</code>.</p>
                    </div>
                    <div class="wpmm-mailer-help" data-for="mailgun">
                        <p><strong>Mailgun:</strong> Free tier — 5,000 emails/month for 3 months. Sign up at <a href="https://mailgun.com" target="_blank" rel="noopener">mailgun.com</a>. In the Mailgun dashboard go to <em>Sending → Domain Settings → SMTP credentials</em> and copy your SMTP login and password. Enter your Mailgun login (usually your email) as the Username.</p>
                    </div>
                    <div class="wpmm-mailer-help" data-for="brevo">
                        <p><strong>Brevo (Sendinblue):</strong> Free — 300 emails/day. Sign up at <a href="https://brevo.com" target="_blank" rel="noopener">brevo.com</a>. Go to <em>SMTP &amp; API → SMTP</em> to find your login and generate a password. Enter that login as the Username below.</p>
                    </div>
                    <div class="wpmm-mailer-help" data-for="sendlayer">
                        <p><strong>SendLayer:</strong> Simple, affordable SMTP. Sign up at <a href="https://sendlayer.com" target="_blank" rel="noopener">sendlayer.com</a>. From your dashboard copy your SMTP username and password and enter them below.</p>
                    </div>
                    <div class="wpmm-mailer-help" data-for="smtpcom">
                        <p><strong>SMTP.com:</strong> 50,000 email free trial. Sign up at <a href="https://smtp.com" target="_blank" rel="noopener">smtp.com</a>. Go to <em>Sender → SMTP credentials</em> and copy your API key; enter it as the Password below. Username is your sender name/channel.</p>
                    </div>
                    <div class="wpmm-mailer-help" data-for="gmail">
                        <p><strong>Gmail / Google Workspace:</strong> Google disabled plain password authentication in May 2022. You must use an <strong>App Password</strong>:</p>
                        <ol style="margin:8px 0 0 18px;padding:0;line-height:1.8;">
                            <li>Sign in to your Google Account and go to <em>Security</em>.</li>
                            <li>Make sure <em>2-Step Verification</em> is turned on.</li>
                            <li>Search for <em>"App Passwords"</em> and create a new one for Mail / Other (name it "WordPress").</li>
                            <li>Google will show a 16-character code — enter that as the <strong>Password</strong> below.</li>
                            <li>Enter your full Gmail address (<code>you@gmail.com</code> or <code>you@yourdomain.com</code>) as the <strong>Username</strong>.</li>
                        </ol>
                        <p style="margin-top:8px;">For <strong>Google Workspace</strong>: SMTP relay is also available via <em>Apps → Google Workspace → Gmail → SMTP relay service</em> in the Workspace admin console — this does not require App Passwords and is suitable for high-volume sending.</p>
                    </div>
                    <div class="wpmm-mailer-help" data-for="microsoft">
                        <p><strong>Microsoft 365 / Outlook.com:</strong> Microsoft deprecated basic auth for Exchange Online in October 2022 but preserved it specifically for SMTP AUTH.</p>
                        <p style="margin-top:8px;"><strong>For personal Outlook.com accounts:</strong></p>
                        <ol style="margin:4px 0 8px 18px;padding:0;line-height:1.8;">
                            <li>Go to <a href="https://account.microsoft.com/security" target="_blank" rel="noopener">account.microsoft.com/security</a> and enable two-step verification.</li>
                            <li>Under <em>Advanced security options → App passwords</em>, create a new app password.</li>
                            <li>Enter your full Outlook address as the <strong>Username</strong> and the app password as the <strong>Password</strong>.</li>
                        </ol>
                        <p><strong>For Microsoft 365 / Office 365 organisations:</strong> A Microsoft 365 admin must first enable SMTP AUTH for your mailbox: <em>Microsoft 365 admin centre → Users → Active Users → select user → Mail → Manage email apps → enable Authenticated SMTP</em>. Then use your regular Microsoft 365 email and password (or an app password if your org enforces MFA).</p>
                        <p style="margin-top:8px;"><strong>Note:</strong> The server <code>smtp.office365.com</code> covers both personal Outlook.com and Microsoft 365 accounts. For older Outlook.com accounts you can also try <code>smtp-mail.outlook.com</code> on port 587.</p>
                    </div>
                </div>

                <!-- SMTP credential fields (hidden for 'default') -->
                <div id="wpmm-smtp-fields" <?php echo $smtp_mailer === 'default' ? 'hidden' : ''; ?>>

                    <div class="wpmm-settings-divider"></div>

                    <!-- Manual SMTP host / port / encryption (only for 'smtp') -->
                    <div id="wpmm-smtp-manual-fields" <?php echo $smtp_mailer === 'smtp' ? '' : 'hidden'; ?>>
                        <div class="wpmm-settings-group">
                            <div class="wpmm-settings-group-label">
                                <strong>SMTP Host</strong>
                                <span>Your mail server address.</span>
                            </div>
                            <div class="wpmm-settings-group-control">
                                <input type="text" id="wpmm-smtp-host" class="wpmm-input"
                                       value="<?php echo esc_attr( $smtp_host ); ?>"
                                       placeholder="mail.example.com" autocomplete="off">
                            </div>
                        </div>
                        <div class="wpmm-settings-group">
                            <div class="wpmm-settings-group-label">
                                <strong>Port &amp; Encryption</strong>
                                <span>Use 587 + TLS for most providers.</span>
                            </div>
                            <div class="wpmm-settings-group-control">
                                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                    <input type="number" id="wpmm-smtp-port" class="wpmm-input"
                                           value="<?php echo absint( $smtp_port ); ?>"
                                           min="1" max="65535" style="width:90px;">
                                    <select id="wpmm-smtp-enc" class="wpmm-input" style="width:auto;">
                                        <option value="tls"  <?php selected( $smtp_enc, 'tls' ); ?>>TLS / STARTTLS (recommended)</option>
                                        <option value="ssl"  <?php selected( $smtp_enc, 'ssl' ); ?>>SSL</option>
                                        <option value="none" <?php selected( $smtp_enc, 'none' ); ?>>None</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Username (all SMTP providers) -->
                    <div class="wpmm-settings-group">
                        <div class="wpmm-settings-group-label">
                            <strong>Username / Login</strong>
                            <span id="wpmm-username-hint">Your SMTP account login.</span>
                        </div>
                        <div class="wpmm-settings-group-control">
                            <input type="text" id="wpmm-smtp-username" class="wpmm-input"
                                   value="<?php echo esc_attr( $smtp_username ); ?>"
                                   placeholder="you@example.com" autocomplete="off">
                        </div>
                    </div>

                    <!-- Password / API key -->
                    <div class="wpmm-settings-group">
                        <div class="wpmm-settings-group-label">
                            <strong>Password / API Key</strong>
                            <span>Stored encrypted. Leave blank to keep the existing value.</span>
                        </div>
                        <div class="wpmm-settings-group-control">
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                <input type="password" id="wpmm-smtp-password" class="wpmm-input"
                                       value="" autocomplete="new-password"
                                       placeholder="<?php echo $has_password ? '••••••••  (saved)' : 'Enter password or API key'; ?>">
                                <button type="button" id="wpmm-smtp-toggle-pw"
                                        class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm"
                                        title="Show / hide password">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </div>
                            <?php if ( $has_password ) : ?>
                                <p class="wpmm-hint">A password is currently saved. Enter a new one to replace it.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="wpmm-settings-divider"></div>

                    <!-- From name / email -->
                    <div class="wpmm-settings-group">
                        <div class="wpmm-settings-group-label">
                            <strong>From Name</strong>
                            <span>Display name on outgoing emails.</span>
                        </div>
                        <div class="wpmm-settings-group-control">
                            <input type="text" id="wpmm-smtp-from-name" class="wpmm-input"
                                   value="<?php echo esc_attr( $smtp_from_name ); ?>"
                                   placeholder="<?php echo esc_attr( $company_name ?: get_bloginfo('name') ); ?>">
                        </div>
                    </div>
                    <div class="wpmm-settings-group">
                        <div class="wpmm-settings-group-label">
                            <strong>From Email</strong>
                            <span>Must be authorised by your sending domain.</span>
                        </div>
                        <div class="wpmm-settings-group-control">
                            <input type="email" id="wpmm-smtp-from-email" class="wpmm-input"
                                   value="<?php echo esc_attr( $smtp_from_email ); ?>"
                                   placeholder="<?php echo esc_attr( get_option('admin_email') ); ?>">
                        </div>
                    </div>

                </div><!-- #wpmm-smtp-fields -->

                <!-- Save + Test row -->
                <div class="wpmm-settings-save-row" style="margin-top:18px;border-top:1px solid var(--wpmm-border);padding-top:18px;">
                    <button type="button" class="wpmm-btn wpmm-btn-primary" id="wpmm-save-smtp-btn">
                        <span class="dashicons dashicons-yes"></span> Save Email Settings
                    </button>
                    <button type="button" class="wpmm-btn wpmm-btn-secondary" id="wpmm-test-smtp-btn"
                            <?php echo $smtp_mailer === 'default' ? '' : ''; ?>>
                        <span class="dashicons dashicons-email-alt"></span> Send Test Email
                    </button>
                    <span id="wpmm-smtp-msg" class="wpmm-save-feedback" style="margin-left:4px;"></span>
                </div>

                <!-- Test email address input (shown when Test is clicked) -->
                <div id="wpmm-test-email-row" hidden style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <input type="email" id="wpmm-test-email-addr" class="wpmm-input"
                           placeholder="Recipient email for test…" style="max-width:280px;">
                    <button type="button" class="wpmm-btn wpmm-btn-primary wpmm-btn-sm" id="wpmm-send-test-btn">
                        <span class="dashicons dashicons-email"></span> Send Now
                    </button>
                    <a href="#" id="wpmm-cancel-test-link" style="font-size:12px;">Cancel</a>
                </div>

            </div><!-- #wpmm-smtp-card -->

            <?php wpmm_tip_card(); ?>
        </div>
    </div>
    <?php
}
