<?php
/**
 * SMTP / Email Mailer configuration for Site Maintenance Manager.
 *
 * Hooks into phpmailer_init to override WordPress's default PHP mail()
 * with a configured SMTP server or API-based mailer.
 *
 * Supported mailers:
 *  default   — WordPress default (PHP mail), no changes made.
 *  smtp      — Manual SMTP: any host, port, username, password, encryption.
 *  sendgrid  — SendGrid API (SMTP relay, api.sendgrid.net:587, API key auth).
 *  mailgun   — Mailgun SMTP relay (smtp.mailgun.org:587).
 *  brevo     — Brevo (Sendinblue) SMTP (smtp-relay.brevo.com:587).
 *  sendlayer — SendLayer SMTP (smtp.sendlayer.net:587).
 *  smtpcom   — SMTP.com SMTP relay (send.smtp.com:587).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Apply mailer config on every wp_mail() call ───────────────────────────────
add_action( 'phpmailer_init', 'wpmm_configure_phpmailer' );

function wpmm_configure_phpmailer( $phpmailer ) {
    $s      = wpmm_get_settings();
    $mailer = $s['smtp_mailer'] ?? 'default';

    if ( $mailer === 'default' ) {
        return; // let WordPress use its own mail handler
    }

    // Pre-configured provider SMTP settings
    $provider_map = [
        'sendgrid'   => [ 'host' => 'smtp.sendgrid.net',         'port' => 587, 'enc' => 'tls', 'user' => 'apikey' ],
        'mailgun'    => [ 'host' => 'smtp.mailgun.org',           'port' => 587, 'enc' => 'tls', 'user' => '' ],
        'brevo'      => [ 'host' => 'smtp-relay.brevo.com',       'port' => 587, 'enc' => 'tls', 'user' => '' ],
        'sendlayer'  => [ 'host' => 'smtp.sendlayer.net',         'port' => 587, 'enc' => 'tls', 'user' => '' ],
        'smtpcom'    => [ 'host' => 'send.smtp.com',              'port' => 587, 'enc' => 'tls', 'user' => '' ],
        // Gmail / Google Workspace: requires an App Password (Google Account → Security → App Passwords).
        // Plain password auth has been disabled by Google since May 2022.
        'gmail'      => [ 'host' => 'smtp.gmail.com',             'port' => 587, 'enc' => 'tls', 'user' => '' ],
        // Microsoft 365 / Outlook.com: requires an App Password for personal accounts,
        // or SMTP AUTH enabled in the Microsoft 365 admin centre for org accounts.
        'microsoft'  => [ 'host' => 'smtp.office365.com',         'port' => 587, 'enc' => 'tls', 'user' => '' ],
    ];

    // Resolve host/port/encryption
    if ( isset( $provider_map[ $mailer ] ) ) {
        $p         = $provider_map[ $mailer ];
        $host      = $p['host'];
        $port      = $p['port'];
        $enc       = $p['enc'];
        // Username: some providers use 'apikey' literally; others use the account login
        $smtp_user = $p['user'] ?: ( $s['smtp_username'] ?? '' );
        $smtp_pass = wpmm_decrypt_smtp( $s['smtp_password_enc'] ?? '' );
    } else {
        // Manual SMTP
        $host      = $s['smtp_host']      ?? '';
        $port      = (int) ( $s['smtp_port'] ?? 587 );
        $enc       = $s['smtp_enc']       ?? 'tls';
        $smtp_user = $s['smtp_username']  ?? '';
        $smtp_pass = wpmm_decrypt_smtp( $s['smtp_password_enc'] ?? '' );
    }

    if ( ! $host ) {
        return; // not configured — fall back to default
    }

    $phpmailer->isSMTP();
    $phpmailer->Host        = $host;
    $phpmailer->Port        = $port;
    $phpmailer->SMTPAuth    = ( $smtp_user !== '' );
    $phpmailer->Username    = $smtp_user;
    $phpmailer->Password    = $smtp_pass;
    $phpmailer->SMTPSecure  = ( $enc === 'tls' ) ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
                                                  : ( $enc === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : '' );

    // From name / address.
    // Always set both so the configured sender is used regardless of what wp_mail() passed.
    // Use !empty() so an empty string falls through to the next fallback rather than
    // being used as-is (which would let PHPMailer default to the site name).
    $from_name  = ! empty( $s['smtp_from_name'] )  ? $s['smtp_from_name']
               : ( ! empty( $s['company_name'] )   ? $s['company_name']
               :   get_bloginfo( 'name' ) );
    $from_email = ! empty( $s['smtp_from_email'] ) ? sanitize_email( $s['smtp_from_email'] )
               : $phpmailer->From; // keep whatever wp_mail() already set

    $phpmailer->From     = $from_email;
    $phpmailer->FromName = $from_name;
}

// ── AJAX: save SMTP settings ──────────────────────────────────────────────────
add_action( 'wp_ajax_wpmm_save_smtp', 'wpmm_ajax_save_smtp' );

function wpmm_ajax_save_smtp() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $s = wpmm_get_settings();

    $s['smtp_mailer']     = sanitize_text_field( wp_unslash( $_POST['smtp_mailer']     ?? 'default' ) );
    $s['smtp_host']       = sanitize_text_field( wp_unslash( $_POST['smtp_host']       ?? '' ) );
    $s['smtp_port']       = absint( $_POST['smtp_port']                    ?? 587 );
    $raw_enc              = sanitize_text_field( wp_unslash( $_POST['smtp_enc'] ?? '' ) );
    $s['smtp_enc']        = in_array( $raw_enc, [ 'tls', 'ssl', 'none' ], true ) ? $raw_enc : 'tls';
    $s['smtp_username']   = sanitize_text_field( wp_unslash( $_POST['smtp_username']   ?? '' ) );
    $s['smtp_from_email'] = sanitize_email(      wp_unslash( $_POST['smtp_from_email'] ?? '' ) );
    $s['smtp_from_name']  = sanitize_text_field( wp_unslash( $_POST['smtp_from_name']  ?? '' ) );

    // Only update the password if a new non-placeholder value was submitted
    $raw_pass = sanitize_text_field( wp_unslash( $_POST['smtp_password'] ?? '' ) );
    if ( $raw_pass !== '' && $raw_pass !== '••••••••' ) {
        $s['smtp_password_enc'] = wpmm_encrypt_smtp( $raw_pass );
    }

    wpmm_save_settings( $s );
    wp_send_json_success( [ 'mailer' => $s['smtp_mailer'] ] );
}

// ── AJAX: send a test email ───────────────────────────────────────────────────
add_action( 'wp_ajax_wpmm_test_smtp', 'wpmm_ajax_test_smtp' );

function wpmm_ajax_test_smtp() {
    check_ajax_referer( 'wpmm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $to      = sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) );
    $current = wp_get_current_user();
    if ( ! $to ) {
        $to = $current->user_email;
    }
    if ( ! is_email( $to ) ) {
        wp_send_json_error( 'Invalid test email address.' );
    }

    $site    = get_bloginfo( 'name' );
    $subject = '[' . $site . '] Site Maintenance Manager — SMTP Test';
    $body    = '<p>This is a test email sent from <strong>' . esc_html( $site ) . '</strong> '
             . 'via Site Maintenance Manager\'s SMTP configuration.</p>'
             . '<p>If you received this, your SMTP settings are working correctly.</p>';

    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

    // Capture PHPMailer errors
    add_action( 'wp_mail_failed', 'wpmm_capture_mail_error' );
    $GLOBALS['wpmm_mail_error'] = '';

    $sent = wp_mail( $to, $subject, $body, $headers );

    remove_action( 'wp_mail_failed', 'wpmm_capture_mail_error' );

    if ( $sent ) {
        wp_send_json_success( 'Test email sent successfully to ' . $to . '.' );
    } else {
        $err = $GLOBALS['wpmm_mail_error'] ?: 'wp_mail() returned false. Check your SMTP credentials.';
        wp_send_json_error( $err );
    }
}

function wpmm_capture_mail_error( $wp_error ) {
    $GLOBALS['wpmm_mail_error'] = $wp_error->get_error_message();
}

// ── Encryption helpers ────────────────────────────────────────────────────────
// Passwords and API keys are encrypted with openssl (AES-256-CBC) before
// storage. The key is derived from AUTH_KEY + SECURE_AUTH_KEY so it is
// unique per WordPress installation and never stored alongside the ciphertext.

function wpmm_encrypt_smtp( $plaintext ) {
    if ( $plaintext === '' || ! function_exists( 'openssl_encrypt' ) ) {
        return base64_encode( $plaintext ); // fallback: base64 only
    }
    $key    = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
    $iv     = openssl_random_pseudo_bytes( 16 );
    $cipher = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
    return base64_encode( $iv . $cipher );
}

function wpmm_decrypt_smtp( $stored ) {
    if ( $stored === '' ) return '';
    if ( ! function_exists( 'openssl_decrypt' ) ) {
        return base64_decode( $stored ); // fallback
    }
    $data = base64_decode( $stored );
    if ( strlen( $data ) <= 16 ) {
        // Legacy base64-only fallback
        return base64_decode( $stored );
    }
    $key  = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
    $iv   = substr( $data, 0, 16 );
    $enc  = substr( $data, 16 );
    $dec  = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
    return $dec !== false ? $dec : '';
}
