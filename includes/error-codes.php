<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Comprehensive dictionary of WordPress upgrader error codes.
 *
 * Keys are the WP_Error->get_error_code() strings returned by Plugin_Upgrader,
 * Theme_Upgrader, Core_Upgrader, WP_Filesystem, and the HTTP API.
 *
 * Each entry has:
 *   'label'  — short human-readable reason (used in the log table badge)
 *   'detail' — full plain-English explanation (shown in the email and log detail)
 *   'action' — what the admin should do
 */
function wpmm_error_dictionary() {
    return [

        // ── License / repository ──────────────────────────────────────────────
        'no_package' => [
            'label'  => 'No Update Package',
            'detail' => 'WordPress could not find a download package for this update. '
                      . 'This usually means the plugin or theme is not hosted on WordPress.org '
                      . 'and requires a valid license key to access the vendor\'s update server.',
            'action' => 'Log in to the plugin vendor\'s website, confirm the license is active, '
                      . 'and re-enter the license key in the plugin\'s settings.',
        ],
        'download_failed' => [
            'label'  => 'Download Failed',
            'detail' => 'The update package could not be downloaded. This may indicate the '
                      . 'plugin\'s license has expired or been revoked, the vendor\'s update '
                      . 'server was unreachable, or the site\'s outgoing HTTP requests are blocked.',
            'action' => 'Check the license status at the vendor\'s site. If the license is valid, '
                      . 'try updating manually by downloading the latest zip and uploading via FTP.',
        ],
        'unauthorized' => [
            'label'  => 'License Unauthorised',
            'detail' => 'The update server refused the download request, most commonly because '
                      . 'the license key is invalid, expired, or not activated for this domain.',
            'action' => 'Renew or reactivate the license at the vendor\'s website, then retry.',
        ],
        'license_expired' => [
            'label'  => 'License Expired',
            'detail' => 'The plugin or theme\'s support and update license has expired. '
                      . 'The installed version will continue to work, but updates are unavailable '
                      . 'until the license is renewed.',
            'action' => 'Purchase a renewal from the plugin vendor to restore update access.',
        ],
        'license_limit' => [
            'label'  => 'Site Limit Reached',
            'detail' => 'The license is active but has reached its maximum number of activated '
                      . 'sites. The update server rejected the request for this domain.',
            'action' => 'Deactivate the license on an unused site, or upgrade to a higher-tier '
                      . 'license to add more activations.',
        ],
        'plugin_not_found' => [
            'label'  => 'Plugin Not in Repository',
            'detail' => 'This plugin was not found in the WordPress.org plugin repository. '
                      . 'It may have been closed, removed for guideline violations, or was never '
                      . 'hosted on WordPress.org.',
            'action' => 'Check the plugin\'s website for an alternative update method. '
                      . 'Consider replacing the plugin if it has been permanently removed.',
        ],
        'theme_not_found' => [
            'label'  => 'Theme Not in Repository',
            'detail' => 'This theme was not found in the WordPress.org theme repository. '
                      . 'It may be a premium theme that requires a license, or it was removed.',
            'action' => 'Obtain the updated theme zip directly from the vendor and update manually.',
        ],

        // ── Filesystem / permissions ──────────────────────────────────────────
        'mkdir_failed' => [
            'label'  => 'Directory Creation Failed',
            'detail' => 'WordPress could not create a required directory during the update. '
                      . 'This is typically a file system permissions issue on the server.',
            'action' => 'Check that the web server user has write access to wp-content/plugins '
                      . 'or wp-content/themes. Contact your host if permissions cannot be changed.',
        ],
        'copy_failed' => [
            'label'  => 'File Copy Failed',
            'detail' => 'One or more files could not be copied during the update process, '
                      . 'indicating a server file system permissions or disk space problem.',
            'action' => 'Verify available disk space and ensure the web server has write '
                      . 'permissions to the plugin or theme directory.',
        ],
        'remove_old_failed' => [
            'label'  => 'Old Version Removal Failed',
            'detail' => 'WordPress downloaded the new version but could not delete the old '
                      . 'plugin or theme directory, leaving the site in a partially updated state.',
            'action' => 'Manually delete the old plugin/theme folder via FTP or the server file '
                      . 'manager, then re-upload the new version.',
        ],
        'fs_unavailable' => [
            'label'  => 'Filesystem Unavailable',
            'detail' => 'WordPress could not connect to the filesystem to perform the update. '
                      . 'This often occurs when FTP credentials are required but not configured, '
                      . 'or when file ownership is set to a different user than the web server.',
            'action' => 'Define FS_METHOD as "direct" in wp-config.php if the server supports it, '
                      . 'or provide FTP credentials in the WordPress update screen.',
        ],
        'could_not_remove_dir' => [
            'label'  => 'Could Not Remove Directory',
            'detail' => 'The updater could not remove the existing plugin or theme directory '
                      . 'before installing the new version.',
            'action' => 'Check folder permissions on the server. Remove the directory manually '
                      . 'via FTP and re-run the update.',
        ],
        'disk_full' => [
            'label'  => 'Disk Full',
            'detail' => 'The server\'s disk does not have enough free space to complete '
                      . 'the update and extract the package.',
            'action' => 'Free up disk space on the server and retry the update.',
        ],

        // ── Package / zip issues ──────────────────────────────────────────────
        'incompatible_archive' => [
            'label'  => 'Incompatible Archive',
            'detail' => 'The downloaded update package is not a valid zip archive, '
                      . 'or it is structured in a way WordPress cannot process.',
            'action' => 'Download the plugin or theme zip manually from the vendor and '
                      . 'install via Plugins > Add New > Upload.',
        ],
        'bad_request' => [
            'label'  => 'Bad Update Request',
            'detail' => 'The update server returned an error response, suggesting the '
                      . 'request was malformed or the package URL is no longer valid.',
            'action' => 'Retry the update. If it fails again, update manually.',
        ],
        'folder_exists' => [
            'label'  => 'Destination Folder Exists',
            'detail' => 'A folder with the same name as the update destination already '
                      . 'exists and could not be removed before installation.',
            'action' => 'Remove the conflicting folder via FTP and retry.',
        ],
        'source_selection_failed' => [
            'label'  => 'Source Selection Failed',
            'detail' => 'WordPress could not identify the correct folder inside the '
                      . 'downloaded zip archive. The package may be structured incorrectly.',
            'action' => 'Contact the plugin vendor and report the issue. Update manually '
                      . 'in the meantime.',
        ],

        // ── Compatibility ─────────────────────────────────────────────────────
        'php_incompatible' => [
            'label'  => 'PHP Version Incompatible',
            'detail' => 'The new version of this plugin or theme requires a higher PHP '
                      . 'version than the one currently running on this server.',
            'action' => 'Upgrade PHP on the server to meet the plugin\'s minimum requirement, '
                      . 'or contact your host to arrange a PHP upgrade.',
        ],
        'wp_incompatible' => [
            'label'  => 'WordPress Version Incompatible',
            'detail' => 'The new version requires a higher version of WordPress than is '
                      . 'currently installed on this site.',
            'action' => 'Update WordPress core first, then retry the plugin or theme update.',
        ],

        // ── HTTP / network ────────────────────────────────────────────────────
        'http_request_failed' => [
            'label'  => 'HTTP Request Failed',
            'detail' => 'WordPress could not reach the update server. This may be caused '
                      . 'by a firewall, DNS issue, or the update server being temporarily '
                      . 'unavailable.',
            'action' => 'Check that the server allows outgoing HTTP/HTTPS requests. '
                      . 'Retry in a few minutes.',
        ],
        'http_404' => [
            'label'  => 'Update URL Not Found (404)',
            'detail' => 'The URL for the update package returned a 404 Not Found error. '
                      . 'The plugin may have been removed from its repository.',
            'action' => 'Visit the plugin vendor\'s website to confirm it is still available '
                      . 'and find an alternative download source if needed.',
        ],

        // ── Generic / catch-all ───────────────────────────────────────────────
        'update_failed' => [
            'label'  => 'Update Failed',
            'detail' => 'The update process encountered an unspecified error. '
                      . 'See the notes field for any additional detail returned by WordPress.',
            'action' => 'Try updating again. If the problem persists, update manually via FTP.',
        ],
        'wpmm_no_transient' => [
            'label'  => 'No Pending Update Found',
            'detail' => 'WordPress reported no pending update for this item at the time '
                      . 'the update was attempted. The update transient may have expired or '
                      . 'been cleared by another process.',
            'action' => 'Return to the Updates page, click Refresh Updates, and try again.',
        ],
        'wpmm_version_unchanged' => [
            'label'  => 'Version Unchanged After Update',
            'detail' => 'The update process ran without a reported error, but the installed '
                      . 'version did not change. This can happen if file permissions prevented '
                      . 'the new files from being written, or if the package was identical.',
            'action' => 'Check server file permissions. Try updating manually via FTP.',
        ],
    ];
}

/**
 * Look up a human-readable explanation for a given error code.
 * Returns an array with keys: label, detail, action.
 * Falls back to generic messaging if the code is not in the dictionary.
 *
 * @param string $code  WP_Error code or one of the wpmm_* internal codes.
 * @return array
 */
function wpmm_explain_error( $code ) {
    $dict = wpmm_error_dictionary();
    if ( $code && isset( $dict[ $code ] ) ) {
        return $dict[ $code ];
    }
    return [
        'label'  => 'Update Failed',
        'detail' => 'An unexpected error occurred. Code: ' . ( $code ?: 'unknown' ),
        'action' => 'Try updating manually. If the error persists, contact your host or plugin vendor.',
    ];
}
