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
function wpmm_build_email_body( $log_entries, $admin_id = 0, $manual_entries = [], $update_note = '' ) {

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

    // Build full name: First Last preferred; fall back to display_name.
    $admin_display_name = '';
    if ( $perf_admin ) {
        $first = get_user_meta( $perf_admin->ID, 'first_name', true );
        $last  = get_user_meta( $perf_admin->ID, 'last_name',  true );
        $full  = trim( $first . ' ' . $last );
        $admin_display_name = $full ?: $perf_admin->display_name;
    }

    // ── Group entries by session so multiple update runs appear as separate
    //    date-labelled sections rather than one undifferentiated block.
    //    This handles the case where plugins were updated Monday and themes
    //    updated Wednesday — both batches show in a single email clearly
    //    labelled with their respective dates and times.
    $sessions_ordered = []; // [ session_key => [ 'date' => ..., 'entries' => [...] ] ]

    if ( is_array( $log_entries ) ) {
        foreach ( $log_entries as $entry ) {
            $session_key = ( ! empty( $entry->session_id ) )
                ? $entry->session_id
                : 'legacy-' . substr( $entry->updated_at ?? '', 0, 10 );

            if ( ! isset( $sessions_ordered[ $session_key ] ) ) {
                // Use the first entry's timestamp as the session date label.
                $sessions_ordered[ $session_key ] = [
                    'date'    => $entry->updated_at ?? '',
                    'entries' => [],
                ];
            }
            $sessions_ordered[ $session_key ]['entries'][] = $entry;
        }
    }

    // Determine whether to show session date headers — only when more than
    // one distinct session is present in this email.
    $multi_session = count( $sessions_ordered ) > 1;

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

    // ── Assemble update sections — grouped by session with date headers ───────
    $sections = '';

    foreach ( $sessions_ordered as $session_key => $session_data ) {
        $session_entries = $session_data['entries'];
        $session_date    = $session_data['date'];

        // Bucket this session's entries by type.
        $core_rows   = [];
        $plugin_rows = [];
        $theme_rows  = [];

        foreach ( $session_entries as $entry ) {
            $type = isset( $entry->item_type ) ? strtolower( trim( $entry->item_type ) ) : '';
            if ( $type === 'core' ) {
                $core_rows[] = $entry;
            } elseif ( $type === 'theme' || $type === 'themes' ) {
                $theme_rows[] = $entry;
            } else {
                $plugin_rows[] = $entry;
            }
        }

        // When multiple sessions are present, show a date header between them
        // so the client can clearly see which updates happened on which date.
        if ( $multi_session && $session_date ) {
            $label = date_i18n( 'l, F j, Y \a\t g:i A', strtotime( $session_date ) );
            $sections .= "
        <div style='margin:32px 0 8px;padding:10px 16px;background:#f0f9ff;border-left:4px solid #2563eb;border-radius:0 6px 6px 0;'>
          <span style='font-size:13px;font-weight:700;color:#1e3a5f;'>&#128197; Update Session: {$label}</span>
        </div>";
        }

        $sections .= $build_section( '&#127758; WordPress Core', $core_rows );
        $sections .= $build_section( '&#128268; Plugins',        $plugin_rows );
        $sections .= $build_section( '&#127912; Themes',          $theme_rows );
    }

    if ( $sections === '' ) {
        $sections = "<p style='color:#6b7280;font-size:13px;margin:24px 0;'>No update entries were found for this session.</p>";
    }

    // ── External updates since last report ───────────────────────────────────
    // Updates run outside Greenskeeper (WP Updates screen, Avada dashboard, etc.)
    $external_section = '';
    if ( function_exists( 'wpmm_get_external_updates_since_last_report' ) ) {
        $ext_rows = wpmm_get_external_updates_since_last_report();
        if ( ! empty( $ext_rows ) ) {
            $ext_html = '';
            foreach ( $ext_rows as $er ) {
                $ename   = esc_html( $er->item_name );
                $enew    = esc_html( $er->new_version );
                $etype   = esc_html( ucfirst( $er->item_type ) );
                $ext_html .= "
                <tr>
                  <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;'>
                    &#9989; <strong>{$ename}</strong>
                    <br><small style='color:#9ca3af;'>{$etype}</small>
                  </td>
                  <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;'>
                    <span style='color:#16a34a;font-weight:700;'>Updated to {$enew}</span>
                    <br><small style='color:#9ca3af;'>Updated outside Greenskeeper</small>
                  </td>
                </tr>";
            }
            $external_section = "
        <h3 style='color:#1e3a5f;font-size:15px;margin:28px 0 10px;border-bottom:2px solid #e5e7eb;padding-bottom:6px;'>&#128260; Updates Made Outside Greenskeeper</h3>
        <p style='font-size:12px;color:#6b7280;margin:0 0 10px;'>The following updates were applied through the WordPress Updates screen, the Avada plugins dashboard, or another external tool. Greenskeeper detected these automatically via WordPress update hooks.</p>
        <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;'>
          <thead>
            <tr style='background:#f0fdf4;'>
              <th style='padding:9px 14px;text-align:left;font-size:12px;color:#166534;text-transform:uppercase;letter-spacing:.05em;'>Item</th>
              <th style='padding:9px 14px;text-align:right;font-size:12px;color:#166534;text-transform:uppercase;letter-spacing:.05em;'>Status</th>
            </tr>
          </thead>
          <tbody>{$ext_html}</tbody>
        </table>
        <p style='font-size:11px;color:#9ca3af;margin:6px 0 0;font-style:italic;'>Note: previous version numbers are not available for externally-triggered updates. Avada Patches applied through Avada&rsquo;s own maintenance dashboard are not detected here and should be documented using the Additional Manual Updates field.</p>";
        }
    }

    // ── Spam activity since last report ──────────────────────────────────────
    // Find the most recent sent email timestamp and query spam blocked since then.
    $spam_section = '';
    if ( function_exists( 'wpmm_get_spam_since_last_report' ) ) {
        $spam_rows = wpmm_get_spam_since_last_report();
        if ( ! empty( $spam_rows ) ) {
            $rule_labels = [
                'honeypot'       => 'Honeypot',
                'too_fast'       => 'Submission Too Fast',
                'blocked_ip'     => 'Blocked IP',
                'keyword'        => 'Keyword Match',
                'too_many_links' => 'Too Many Links',
                'duplicate'      => 'Duplicate Comment',
                'akismet'        => 'Akismet',
            ];
            $spam_rows_html = '';
            foreach ( $spam_rows as $sr ) {
                $rule    = esc_html( $rule_labels[ $sr->rule ] ?? $sr->rule );
                $ip      = esc_html( $sr->author_ip );
                $preview = esc_html( mb_substr( wp_strip_all_tags( $sr->comment_content ?? '' ), 0, 60 ) );
                $when    = esc_html( date_i18n( 'M j g:i A', strtotime( $sr->blocked_at ) ) );
                $spam_rows_html .= "
                <tr>
                  <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#6b7280;white-space:nowrap;'>{$when}</td>
                  <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb;'><span style='font-size:11px;font-weight:700;color:#dc2626;'>{$rule}</span></td>
                  <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;font-family:monospace;color:#374151;'>{$ip}</td>
                  <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#6b7280;'>{$preview}</td>
                </tr>";
            }
            $spam_count   = count( $spam_rows );
            $spam_section = "
        <h3 style='color:#1e3a5f;font-size:15px;margin:28px 0 10px;border-bottom:2px solid #e5e7eb;padding-bottom:6px;'>&#128737; Spam Activity Since Last Report</h3>
        <p style='font-size:12px;color:#6b7280;margin:0 0 10px;'>{$spam_count} comment attempt" . ( $spam_count === 1 ? '' : 's' ) . " blocked by the spam filter.</p>
        <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;'>
          <thead>
            <tr style='background:#fef2f2;'>
              <th style='padding:8px 12px;text-align:left;font-size:11px;color:#991b1b;text-transform:uppercase;letter-spacing:.05em;'>When</th>
              <th style='padding:8px 12px;text-align:left;font-size:11px;color:#991b1b;text-transform:uppercase;letter-spacing:.05em;'>Rule</th>
              <th style='padding:8px 12px;text-align:left;font-size:11px;color:#991b1b;text-transform:uppercase;letter-spacing:.05em;'>IP Address</th>
              <th style='padding:8px 12px;text-align:left;font-size:11px;color:#991b1b;text-transform:uppercase;letter-spacing:.05em;'>Content Preview</th>
            </tr>
          </thead>
          <tbody>{$spam_rows_html}</tbody>
        </table>";
        }
    }

    // ── Additional Manual Updates section ─────────────────────────────────────
    if ( ! empty( $manual_entries ) ) {
        $manual_rows = '';
        foreach ( $manual_entries as $entry ) {
            if ( empty( $entry['name'] ) ) continue;
            $old = esc_html( $entry['old_version'] ?? '' );
            $new = esc_html( $entry['new_version'] ?? '' );
            $ver = $old . ( $new ? ' &rarr; <strong>' . $new . '</strong>' : '' );
            $manual_rows .= "
            <tr>
              <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;'>
                &#9989; <strong>" . esc_html( $entry['name'] ) . "</strong>
                <br><small style='color:#9ca3af;'>" . $ver . "</small>
              </td>
              <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;'>
                <span style='color:#16a34a;font-weight:700;'>Updated Manually</span>
              </td>
            </tr>";
        }
        if ( $manual_rows ) {
            $manual_note = "<p style='color:#6b7280;font-size:12px;font-style:italic;margin:8px 0 0;'>Plugins or themes updated manually outside the control of Greenskeeper due to functional licensing issues that prevent this plugin from accessing the specific panels where these plugins are located in the plugin or theme admin.</p>";
            $sections .= "
        <h3 style='color:#1e3a5f;font-size:15px;margin:28px 0 10px;border-bottom:2px solid #e5e7eb;padding-bottom:6px;'>&#128295; Additional Manual Updates</h3>
        " . $manual_note . "
        <table width='100%' cellpadding='0' cellspacing='0' style='margin-top:10px;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;'>
          <thead>
            <tr style='background:#f3f4f6;'>
              <th style='padding:9px 14px;text-align:left;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;'>Item</th>
              <th style='padding:9px 14px;text-align:right;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;'>Status</th>
            </tr>
          </thead>
          <tbody>" . $manual_rows . "</tbody>
        </table>";
        }
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
        $admin_name      = esc_html( $admin_display_name );
        $administered_by = "<p style='color:#bfdbfe;font-size:13px;margin:8px 0 0;line-height:1.6;'>"
                         . "WordPress website updates administered by "
                         . "<strong style='color:#fff;'>" . $admin_display_name . "</strong>.</p>";
    }

    // ── Divider between site block and brand row ──────────────────────────────
    $divider = ( $brand_block || $administered_by )
        ? "<div style='border-top:1px solid rgba(255,255,255,.2);margin:16px 0 14px;'></div>"
        : '';

    // ── Footer ────────────────────────────────────────────────────────────────
    $sender = $company ?: 'Greenskeeper';

    // ── Update note block ─────────────────────────────────────────────────────
    $update_note_block = '';
    if ( ! empty( $update_note ) ) {
        $note_lines = nl2br( esc_html( $update_note ) );
        $update_note_block = '
    <div style="margin-top:28px;">
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:18px 22px;">
        <p style="margin:0 0 8px;font-size:12px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;">Note from your administrator</p>
        <p style="margin:0;font-size:14px;color:#78350f;line-height:1.7;">' . $note_lines . '</p>
      </div>
    </div>';
    }

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

    <!-- BODY — padding-bottom is intentionally generous so the last section
         never collides with the footer regardless of content length. -->
    <div style="padding:32px 36px 48px;">
      <h2 style="color:#1e3a5f;margin:0 0 6px;font-size:18px;font-family:Georgia,serif;">Weekly WordPress Maintenance Report</h2>
      <p style="color:#6b7280;font-size:13px;margin:0 0 4px;">The following updates were performed on your site.</p>
      ' . $sections . '
      ' . $external_section . '
      ' . $spam_section . '
      ' . $update_note_block . '
    </div>

    <!-- FOOTER — separated from body by a full border-top; never overlaps content -->
    <div style="background:#f8fafc;padding:20px 36px;border-top:2px solid #e5e7eb;text-align:center;font-size:12px;color:#9ca3af;clear:both;">
      Sent by ' . esc_html( $sender ) . '
      &bull; <a href="' . esc_url( $site_url ) . '" style="color:#9ca3af;">' . esc_html( $site_url ) . '</a>
    </div>

  </div>
</body>
</html>';
}


/**
 * Return external update log entries (session_id starting with 'ext-')
 * that occurred since the last sent email report.
 * These are updates run outside Greenskeeper — via the WP Updates screen,
 * the Avada plugins dashboard, etc.
 */
function wpmm_get_external_updates_since_last_report() {
    global $wpdb;
    $log_table   = esc_sql( $wpdb->prefix . 'wpmm_update_log' );
    $email_table = esc_sql( $wpdb->prefix . 'wpmm_email_log' );

    // Find the most recent successfully sent email.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $last_sent = $wpdb->get_var(
        "SELECT sent_at FROM {$email_table} WHERE status = 'sent' ORDER BY sent_at DESC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    );

    if ( $last_sent ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . $log_table . " WHERE session_id LIKE %s AND updated_at > %s ORDER BY updated_at ASC",
            'ext-%',
            $last_sent
        ) );
    }

    // No previous report — show all external entries.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
    return $wpdb->get_results( $wpdb->prepare(
        'SELECT * FROM ' . $log_table . ' WHERE session_id LIKE %s ORDER BY updated_at ASC',
        'ext-%'
    ) );
}


/**
 * Return spam log entries blocked since the last sent email report.
 * Limited to 50 rows to keep the email a reasonable length.
 */
function wpmm_get_spam_since_last_report() {
    global $wpdb;
    $spam_table  = esc_sql( $wpdb->prefix . 'wpmm_spam_log' );
    $email_table = esc_sql( $wpdb->prefix . 'wpmm_email_log' );

    // Find the most recent successfully sent email timestamp.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $last_sent = $wpdb->get_var(
        "SELECT sent_at FROM {$email_table} WHERE status = 'sent' ORDER BY sent_at DESC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    );

    if ( $last_sent ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$spam_table} WHERE blocked_at > %s ORDER BY blocked_at DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $last_sent
        ) );
    }

    // No previous report — show last 50 all-time.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return $wpdb->get_results(
        "SELECT * FROM {$spam_table} ORDER BY blocked_at DESC LIMIT 50" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    );
}

/**
 * Send the maintenance email and log it to wpmm_email_log.
 */
function wpmm_send_email( $to, $subject, $body, $admin_id = 0 ) {
    global $wpdb;

    $from_name  = 'Greenskeeper';
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
        esc_sql( $wpdb->prefix . 'wpmm_email_log' ),
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
        trigger_error( 'Greenskeeper: email log insert failed: ' . esc_html( $wpdb->last_error ), E_USER_WARNING );
    }

    return [ 'success' => $sent, 'email_id' => $wpdb->insert_id ];
}

/**
 * Build a consolidated network maintenance email.
 * Called when updates were run in All Sites mode.
 *
 * @param array $sites_data  Array of [ 'blog_id' => N, 'site_name' => '', 'site_url' => '', 'entries' => [...] ]
 * @param int   $admin_id    Performing administrator.
 * @param string $update_note Optional note from administrator.
 */
function wpmm_build_network_email_body( $sites_data, $admin_id = 0, $update_note = '' ) {

    $s        = wpmm_get_settings();
    $logo_url = ! empty( $s['logo_url'] )     ? $s['logo_url']     : '';
    $company  = ! empty( $s['company_name'] ) ? $s['company_name'] : '';

    $perf_admin = null;
    if ( $admin_id ) {
        $u = get_user_by( 'id', $admin_id );
        if ( $u ) { $perf_admin = $u; }
    }
    if ( ! $perf_admin ) {
        $perf_admin = wpmm_get_default_admin();
    }

    // ── Build per-site sections ───────────────────────────────────────────────
    $all_sections = '';
    foreach ( $sites_data as $site ) {
        $site_name = esc_html( $site['site_name'] ?? '' );
        $site_url  = esc_url( $site['site_url']  ?? '' );
        $entries   = $site['entries'] ?? [];

        if ( empty( $entries ) ) {
            continue;
        }

        $build_rows = function( array $ents ) {
            $html = '';
            foreach ( $ents as $entry ) {
                $success = isset( $entry->status ) && $entry->status === 'success';
                $icon    = $success ? '&#9989;' : '&#10060;';
                $badge   = $success
                    ? '<span style="color:#16a34a;font-weight:700;">Updated Successfully</span>'
                    : '<span style="color:#dc2626;font-weight:700;">Update Failed</span>';
                $old = isset( $entry->old_version ) ? esc_html( $entry->old_version ) : '';
                $new = isset( $entry->new_version ) && $entry->new_version
                    ? ' &rarr; <strong>' . esc_html( $entry->new_version ) . '</strong>' : '';
                $name = isset( $entry->item_name ) ? esc_html( $entry->item_name ) : '(unknown)';
                $html .= "<tr>
                  <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;'>
                    {$icon} <strong>{$name}</strong>
                    <br><small style='color:#9ca3af;'>{$old}{$new}</small>
                  </td>
                  <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;'>{$badge}</td>
                </tr>";
            }
            return $html;
        };

        $core_rows = $plugin_rows = $theme_rows = [];
        foreach ( $entries as $entry ) {
            $type = strtolower( trim( $entry->item_type ?? '' ) );
            if ( $type === 'core' )        { $core_rows[]   = $entry; }
            elseif ( $type === 'theme' )   { $theme_rows[]  = $entry; }
            else                           { $plugin_rows[] = $entry; }
        }

        $build_section = function( $title, $ents ) use ( $build_rows ) {
            if ( empty( $ents ) ) return '';
            $rows = $build_rows( $ents );
            return "<h4 style='color:#374151;font-size:13px;margin:16px 0 8px;'>{$title}</h4>
            <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;border:1px solid #e5e7eb;'>
              <thead><tr style='background:#f3f4f6;'>
                <th style='padding:8px 12px;text-align:left;font-size:11px;color:#6b7280;text-transform:uppercase;'>Item</th>
                <th style='padding:8px 12px;text-align:right;font-size:11px;color:#6b7280;text-transform:uppercase;'>Status</th>
              </tr></thead>
              <tbody>{$rows}</tbody>
            </table>";
        };

        $site_sections = $build_section( '&#127758; WordPress Core', $core_rows )
                       . $build_section( '&#128268; Plugins', $plugin_rows )
                       . $build_section( '&#127912; Themes', $theme_rows );

        $all_sections .= "
        <div style='margin:28px 0 8px;padding:14px 16px;background:#f0f7ff;border-left:4px solid #2563eb;border-radius:0 6px 6px 0;'>
            <p style='margin:0;font-size:15px;font-weight:700;color:#1e3a5f;'>{$site_name}</p>
            <p style='margin:2px 0 0;font-size:12px;color:#6b7280;'>
                <a href='{$site_url}' style='color:#2563eb;text-decoration:none;'>{$site_url}</a>
            </p>
        </div>
        {$site_sections}";
    }

    if ( $all_sections === '' ) {
        $all_sections = "<p style='color:#6b7280;font-size:13px;margin:24px 0;'>No updates were performed on any site in this network run.</p>";
    }

    // ── Header elements (same as single-site) ─────────────────────────────────
    $network_url  = network_site_url();
    $network_name = get_network()->site_name ?? 'WordPress Network';

    $ext_icon = "<svg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='#93c5fd' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block;vertical-align:middle;margin-left:3px;'><path d='M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6'/><polyline points='15 3 21 3 21 9'/><line x1='10' y1='14' x2='21' y2='3'/></svg>";

    $site_block = "<p style='margin:0 0 3px;color:#bfdbfe;font-size:11px;text-transform:uppercase;letter-spacing:.08em;'>Network</p>
        <p style='margin:0 0 3px;color:#ffffff;font-size:20px;font-weight:700;line-height:1.2;'>" . esc_html( $network_name ) . "</p>
        <p style='margin:0;font-size:13px;'><a href='" . esc_url( $network_url ) . "' style='color:#93c5fd;text-decoration:none;'>" . esc_html( $network_url ) . $ext_icon . "</a></p>";

    $logo_img = $logo_url ? "<img src='" . esc_url( $logo_url ) . "' alt='" . esc_attr( $company ) . "' style='max-height:24px;max-width:90px;display:block;filter:brightness(0) invert(1);'>" : '';
    $brand_cells = '';
    if ( $logo_img )  { $brand_cells .= "<td style='vertical-align:middle;padding-right:10px;'>{$logo_img}</td>"; }
    if ( $company )   { $brand_cells .= "<td style='vertical-align:middle;'><span style='color:#fff;font-size:16px;font-weight:700;'>" . esc_html( $company ) . "</span></td>"; }
    $brand_block = $brand_cells ? "<table cellpadding='0' cellspacing='0' border='0'><tr>{$brand_cells}</tr></table>" : '';

    $administered_by = $perf_admin
        ? "<p style='color:#bfdbfe;font-size:13px;margin:8px 0 0;'>Network maintenance performed by <strong style='color:#fff;'>" . esc_html( $perf_admin->display_name ) . "</strong>.</p>"
        : '';

    $divider = ( $brand_block || $administered_by ) ? "<div style='border-top:1px solid rgba(255,255,255,.2);margin:16px 0 14px;'></div>" : '';
    $sender  = $company ?: 'Greenskeeper';

    $update_note_block = '';
    if ( ! empty( $update_note ) ) {
        $note_lines = nl2br( esc_html( $update_note ) );
        $update_note_block = '<div style="padding:24px 36px 0;"><div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:18px 22px;"><p style="margin:0 0 8px;font-size:12px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;">Note from your administrator</p><p style="margin:0;font-size:14px;color:#78350f;line-height:1.7;">' . $note_lines . '</p></div></div>';
    }

    return '<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Network Maintenance Report</title></head>
<body style="font-family:Georgia,serif;background:#f1f5f9;margin:0;padding:0;">
  <div style="max-width:680px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.1);">
    <div style="background:#1e3a5f;padding:28px 36px 24px;">
      ' . $site_block . '
      ' . $divider . '
      ' . $brand_block . '
      ' . $administered_by . '
    </div>
    <div style="padding:32px 36px;">
      <h2 style="color:#1e3a5f;margin:0 0 6px;font-size:18px;font-family:Georgia,serif;">Network WordPress Maintenance Report</h2>
      <p style="color:#6b7280;font-size:13px;margin:0 0 4px;">The following updates were performed across your network.</p>
      ' . $all_sections . '
    </div>
    ' . $update_note_block . '
    <div style="background:#f8fafc;padding:18px 36px;border-top:1px solid #e5e7eb;text-align:center;font-size:12px;color:#9ca3af;">
      Sent by ' . esc_html( $sender ) . ' &bull; <a href="' . esc_url( $network_url ) . '" style="color:#9ca3af;">' . esc_html( $network_url ) . '</a>
    </div>
  </div>
</body></html>';
}
