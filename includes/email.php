<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Build the HTML email body from update log entries.
 *
 * Header order (top to bottom):
 *  1. Site Name + Site URL  (with external-link icon)
 *  2. Divider line
 *  3. Company logo + Company name side-by-side
 *  4. "This update was completed by [Admin] from [Logo] [Company]"
 *
 * @param array $log_entries  Row objects from wpmm_update_log.
 * @param int   $admin_id     Optional override for the performing administrator.
 */
function wpmm_build_email_body( $log_entries, $admin_id = 0 ) {

    $site_name = get_bloginfo( 'name' );
    $site_url  = get_bloginfo( 'url' );

    // Settings
    $s        = wpmm_get_settings();
    $logo_url = ! empty( $s['logo_url'] )     ? $s['logo_url']     : '';
    $company  = ! empty( $s['company_name'] ) ? $s['company_name'] : '';

    // Performing administrator
    $perf_admin = null;
    if ( $admin_id ) {
        $u = get_user_by( 'id', $admin_id );
        if ( $u ) { $perf_admin = $u; }
    }
    if ( ! $perf_admin ) {
        $perf_admin = wpmm_get_default_admin();
    }

    // ── Bucket entries by type ────────────────────────────────────────────────
    $core_rows   = [];
    $plugin_rows = [];
    $theme_rows  = [];

    if ( is_array( $log_entries ) ) {
        foreach ( $log_entries as $entry ) {
            $type = isset( $entry->item_type ) ? strtolower( trim( $entry->item_type ) ) : '';
            if ( $type === 'core' ) {
                $core_rows[] = $entry;
            } elseif ( $type === 'theme' ) {
                $theme_rows[] = $entry;
            } else {
                // 'plugin', '' (empty), or anything else → treat as plugin
                $plugin_rows[] = $entry;
            }
        }
    }

    // ── Row builder ───────────────────────────────────────────────────────────
    $build_rows = function ( array $entries ) {
        $html = '';
        foreach ( $entries as $entry ) {
            $success = isset( $entry->status ) && $entry->status === 'success';
            $icon    = $success ? '&#9989;' : '&#10060;';
            $badge   = $success
                ? '<span style="color:#16a34a;font-weight:700;">Updated Successfully</span>'
                : '<span style="color:#dc2626;font-weight:700;">Update Failed</span>';

            $old = isset( $entry->old_version ) ? esc_html( $entry->old_version ) : '';
            $new = isset( $entry->new_version ) && $entry->new_version
                ? ' &rarr; <strong>' . esc_html( $entry->new_version ) . '</strong>'
                : '';
            $ver = $old . $new;

            $note = '';
            if ( ! $success ) {
                if ( ! empty( $entry->error_code ) ) {
                    $explain = wpmm_explain_error( $entry->error_code );
                    $note = '<br><small style="color:#dc2626;">'
                          . esc_html( $explain['label'] ) . ': '
                          . esc_html( $explain['detail'] ) . '</small>';
                } elseif ( ! empty( $entry->message ) ) {
                    $note = '<br><small style="color:#6b7280;">' . esc_html( $entry->message ) . '</small>';
                }
            }

            $name = isset( $entry->item_name ) ? esc_html( $entry->item_name ) : '(unknown)';

            $html .= "
            <tr>
              <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;'>
                {$icon} <strong>{$name}</strong>
                <br><small style='color:#9ca3af;'>{$ver}</small>{$note}
              </td>
              <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;'>{$badge}</td>
            </tr>";
        }
        return $html;
    };

    // ── Section builder ───────────────────────────────────────────────────────
    $build_section = function ( $title, array $entries ) use ( $build_rows ) {
        if ( empty( $entries ) ) {
            return '';
        }
        $rows = $build_rows( $entries );
        return "
        <h3 style='color:#1e3a5f;font-size:15px;margin:28px 0 10px;border-bottom:2px solid #e5e7eb;padding-bottom:6px;'>{$title}</h3>
        <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;'>
          <thead>
            <tr style='background:#f3f4f6;'>
              <th style='padding:9px 14px;text-align:left;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;'>Item</th>
              <th style='padding:9px 14px;text-align:right;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;'>Status</th>
            </tr>
          </thead>
          <tbody>{$rows}</tbody>
        </table>";
    };

    // ── Assemble update sections ──────────────────────────────────────────────
    $sections = $build_section( '&#127758; WordPress Core', $core_rows )
              . $build_section( '&#128268; Plugins',        $plugin_rows )
              . $build_section( '&#127912; Themes',          $theme_rows );

    if ( $sections === '' ) {
        $sections = "<p style='color:#6b7280;font-size:13px;margin:24px 0;'>No update entries were found for this session.</p>";
    }

    // ── Header: Site block (top) ──────────────────────────────────────────────
    $ext_icon = "<svg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24'"
              . " fill='none' stroke='#93c5fd' stroke-width='2.5' stroke-linecap='round'"
              . " stroke-linejoin='round' style='display:inline-block;vertical-align:middle;margin-left:3px;'>"
              . "<path d='M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6'/>"
              . "<polyline points='15 3 21 3 21 9'/><line x1='10' y1='14' x2='21' y2='3'/>"
              . "</svg>";

    $site_block = "
        <p style='margin:0 0 3px;color:#bfdbfe;font-size:11px;text-transform:uppercase;letter-spacing:.08em;'>Site</p>
        <p style='margin:0 0 3px;color:#ffffff;font-size:20px;font-weight:700;line-height:1.2;'>"
              . esc_html( $site_name ) . "</p>
        <p style='margin:0;font-size:13px;'>
          <a href='" . esc_url( $site_url ) . "' style='color:#93c5fd;text-decoration:none;'>"
              . esc_html( $site_url ) . $ext_icon . "</a>
        </p>";

    // ── Header: Brand row — [Logo] [Company Name] inline ─────────────────────
    // Logo rendered as an inline <img> inside a table cell so it sits on the
    // same baseline as the company name text across all email clients.
    $logo_img_inline = '';
    if ( $logo_url ) {
        $logo_img_inline = "<img src='" . esc_url( $logo_url ) . "'"
            . " alt='" . esc_attr( $company ) . "'"
            . " style='max-height:24px;max-width:90px;display:block;"
            . "filter:brightness(0) invert(1);'>";
    }

    // Build the brand row as an HTML table for reliable inline layout in email.
    if ( $logo_img_inline || $company ) {
        $brand_cells = '';
        if ( $logo_img_inline ) {
            $brand_cells .= "<td style='vertical-align:middle;padding-right:10px;'>"
                          . $logo_img_inline . "</td>";
        }
        if ( $company ) {
            $brand_cells .= "<td style='vertical-align:middle;'>"
                          . "<span style='color:#fff;font-size:16px;font-weight:700;line-height:1;'>"
                          . esc_html( $company ) . "</span></td>";
        }
        $brand_block = "<table cellpadding='0' cellspacing='0' border='0'>"
                     . "<tr>" . $brand_cells . "</tr></table>";
    } else {
        $brand_block = '';
    }

    // ── Header: Administered-by line ─────────────────────────────────────────
    $administered_by = '';
    if ( $perf_admin ) {
        $admin_name      = esc_html( $perf_admin->display_name );
        $administered_by = "<p style='color:#bfdbfe;font-size:13px;margin:8px 0 0;line-height:1.6;'>"
                         . "WordPress website updates administered by "
                         . "<strong style='color:#fff;'>" . $admin_name . "</strong>.</p>";
    }

    // ── Divider between site block and brand row ──────────────────────────────
    $divider = ( $brand_block || $administered_by )
        ? "<div style='border-top:1px solid rgba(255,255,255,.2);margin:16px 0 14px;'></div>"
        : '';

    // ── Footer ────────────────────────────────────────────────────────────────
    $sender = $company ?: 'Site Maintenance Manager';

    // ── Assemble ─────────────────────────────────────────────────────────────
    return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>WordPress Maintenance Report</title>
</head>
<body style="font-family:Georgia,serif;background:#f1f5f9;margin:0;padding:0;">
  <div style="max-width:680px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.1);">

    <!-- HEADER -->
    <div style="background:#1e3a5f;padding:28px 36px 24px;">
      ' . $site_block . '
      ' . $divider . '
      ' . $brand_block . '
      ' . $administered_by . '
    </div>

    <!-- BODY -->
    <div style="padding:32px 36px;">
      <h2 style="color:#1e3a5f;margin:0 0 6px;font-size:18px;font-family:Georgia,serif;">Weekly WordPress Maintenance Report</h2>
      <p style="color:#6b7280;font-size:13px;margin:0 0 4px;">The following updates were performed on your site.</p>
      ' . $sections . '
    </div>

    <!-- FOOTER -->
    <div style="background:#f8fafc;padding:18px 36px;border-top:1px solid #e5e7eb;text-align:center;font-size:12px;color:#9ca3af;">
      Sent by ' . esc_html( $sender ) . '
      &bull; <a href="' . esc_url( $site_url ) . '" style="color:#9ca3af;">' . esc_html( $site_url ) . '</a>
    </div>

  </div>
</body>
</html>';
}

/**
 * Send the maintenance email and log it to wpmm_email_log.
 */
function wpmm_send_email( $to, $subject, $body, $admin_id = 0 ) {
    global $wpdb;

    $from_name  = 'Site Maintenance Manager';
    $from_email = get_option( 'admin_email' );

    $s = wpmm_get_settings();
    if ( ! empty( $s['company_name'] ) ) {
        $from_name = $s['company_name'];
    }

    $perf_id = $admin_id ?: ( $s['default_admin_id'] ?? 0 );
    if ( $perf_id ) {
        $u = get_user_by( 'id', $perf_id );
        if ( $u ) {
            $from_name  = $u->display_name;
            $from_email = $u->user_email;
        }
    }

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
    ];

    $sent   = wp_mail( $to, $subject, $body, $headers );
    $status = $sent ? 'sent' : 'failed';

    $last_session     = get_option( 'wpmm_last_session', [] );
    $email_session_id = $last_session['session_id'] ?? '';

    $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->prefix . 'wpmm_email_log',
        [
            'session_id' => $email_session_id,
            'to_email'   => $to,
            'subject'    => $subject,
            'body'       => $body,
            'status'     => $status,
            'sent_at'    => current_time( 'mysql' ),
        ]
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- legitimate insert to custom plugin table.
    if ( $result === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
        trigger_error( 'Site Maintenance Manager: email log insert failed: ' . esc_html( $wpdb->last_error ), E_USER_WARNING );
    }

    return [ 'success' => $sent, 'email_id' => $wpdb->insert_id ];
}
