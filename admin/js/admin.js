/* global wpmm, jQuery */
jQuery(function ($) {
    'use strict';

    // =========================================================================
    // Session ID — unique per page load, shared across all updates in this
    // batch so the email report filters to only this session's entries.
    // =========================================================================
    var sessionId           = '';      // set only when updates are actually run this page load
    var performingAdminId   = 0;       // set when updates begin, passed through to email
    var updatesRanThisLoad  = false;   // true only if wpmm_run_update was called this page load

    // =========================================================================
    // Date picker (Email Reports page)
    // Appends "for week of: [date]" to the subject line when a date is selected.
    // The subject field keeps its base value (stored in data-base-subject) and
    // the date portion is appended/removed as the date changes.
    // =========================================================================
    var $reportDate    = $('#wpmm-report-date');
    var $subjectField  = $('#wpmm-email-subject-tab');
    var $clearDateLink = $('#wpmm-clear-report-date');

    if ($reportDate.length) {
        $reportDate.datepicker({
            dateFormat: 'MM d, yy',
            onSelect: function (dateText) {
                applyReportDate(dateText);
            }
        });
    }

    function applyReportDate(dateText) {
        var base = $subjectField.data('base-subject') || $subjectField.val();
        // Store the base if we haven't yet
        if (!$subjectField.data('base-subject')) {
            $subjectField.data('base-subject', base);
        }
        if (dateText) {
            $subjectField.val(base + ' for week of: ' + dateText);
            $clearDateLink.show();
        } else {
            $subjectField.val(base);
            $clearDateLink.hide();
        }
    }

    // Allow the user to type directly in the subject field — update base-subject
    // so date re-appends to whatever they typed, not the original PHP value.
    $subjectField.on('input', function () {
        // Strip any existing date suffix so typing doesn't accumulate suffixes
        var current = $(this).val();
        var forIdx  = current.indexOf(' for week of: ');
        if (forIdx !== -1) {
            current = current.substring(0, forIdx);
        }
        $(this).data('base-subject', current);
    });

    $(document).on('click', '#wpmm-clear-report-date', function (e) {
        e.preventDefault();
        $reportDate.val('');
        applyReportDate('');
    });

    // =========================================================================
    // Stored client email — Save / Edit / Cancel (Dashboard page)
    // =========================================================================

    // =========================================================================
    // UPDATE LOG — Session Accordion
    // =========================================================================
    // All accordion rows rendered by PHP. Toggle open/closed on header click.
    $(document).on('click', '.wpmm-session-header', function () {
        var $btn     = $(this);
        var targetId = $btn.data('target');
        var $body    = $('#' + targetId);
        var isOpen   = $btn.attr('aria-expanded') === 'true';

        if (isOpen) {
            $btn.attr('aria-expanded', 'false');
            // Use the native hidden attribute — it's set by PHP on load and
            // toggled here. WordPress admin CSS honours [hidden] { display:none }
            // without conflict because .wpmm-session-body has no display rule.
            $body[0].setAttribute('hidden', '');
        } else {
            $btn.attr('aria-expanded', 'true');
            $body[0].removeAttribute('hidden');
        }
    });

    // =========================================================================
    // EMAIL PREVIEW MODAL (Email Reports page)
    // =========================================================================
    // NOTE: modal elements are found at click-time, not at document-ready,
    // because the modal lives on a different page than the Updates section.
    // ── helpers to show/hide modal internals without [hidden] attr conflicts ──
    function modalShow($el) { $el.removeClass('wpmm-hidden'); }
    function modalHide($el) { $el.addClass('wpmm-hidden'); }

    function openModal(emailId, subject, toEmail, sentAt) {
        var $modal   = $('#wpmm-email-modal');
        var $iframe  = $('#wpmm-modal-iframe');
        var $loading = $('#wpmm-modal-loading');

        if (!$modal.length) { return; }

        // Reset: hide iframe, show loading spinner with original text
        modalHide($iframe);
        $iframe.attr('srcdoc', '');
        $loading.html(
            '<span class="dashicons dashicons-update wpmm-spin"></span> Loading email&hellip;'
        );
        modalShow($loading);

        $('#wpmm-modal-subtitle').text(
            'To: ' + toEmail + '  ·  Sent: ' + sentAt + '  ·  ' + subject
        );

        // Open modal
        $modal.removeClass('wpmm-modal-closed');
        $('body').css('overflow', 'hidden');
        $modal.find('.wpmm-modal-close').first().trigger('focus');

        $.post(wpmm.ajax_url, {
            action:   'wpmm_get_email_body',
            nonce:    wpmm.nonce,
            email_id: emailId
        }, function (res) {
            modalHide($loading);
            if (res.success && res.data && res.data.body) {
                $iframe.attr('srcdoc', res.data.body);

                // Auto-resize the iframe to its content height once loaded
                // so the modal body can scroll the full email without clipping.
                $iframe[0].onload = function () {
                    try {
                        var doc = this.contentDocument || this.contentWindow.document;
                        var h   = doc.documentElement.scrollHeight
                               || doc.body.scrollHeight
                               || 600;
                        // Add a small buffer so the very last pixel of the
                        // footer is never flush against the iframe edge.
                        $(this).css('height', ( h + 32 ) + 'px');
                    } catch (e) {
                        // Cross-origin guard — fall back to a tall fixed height.
                        $(this).css('height', '1200px');
                    }
                };

                modalShow($iframe);
            } else {
                var errMsg = (res.data && typeof res.data === 'string') ? res.data : 'Unknown error';
                $loading.html(
                    '<span style="color:#dc2626;">&#10007; Could not load preview: ' + escHtml(errMsg) + '</span>'
                );
                modalShow($loading);
            }
        }).fail(function () {
            modalHide($loading);
            $loading.html(
                '<span style="color:#dc2626;">&#10007; Request failed. Please try again.</span>'
            );
            modalShow($loading);
        });
    }

    function closeModal() {
        var $modal  = $('#wpmm-email-modal');
        var $iframe = $('#wpmm-modal-iframe');
        if (!$modal.length) { return; }
        $modal.addClass('wpmm-modal-closed');
        $iframe.attr('srcdoc', '');
        modalHide($iframe);
        $('body').css('overflow', '');
    }

    $(document).on('click', '.wpmm-preview-btn', function () {
        var $btn = $(this);
        openModal(
            $btn.data('id'),
            $btn.data('subject') || '',
            $btn.data('to')      || '',
            $btn.data('sent')    || ''
        );
    });

    $(document).on('click', '.wpmm-modal-close, .wpmm-modal-overlay', function () {
        closeModal();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') { closeModal(); }
    });

    // =========================================================================
    // UPDATES PAGE — load available updates on page load
    // =========================================================================
    function loadUpdates() {
        var $container = $('#wpmm-update-sections');
        if (!$container.length) { return; }
        $container.html(
            '<p class="wpmm-loading">' +
            '<span class="dashicons dashicons-update wpmm-spin"></span> ' +
            'Scanning for available updates&hellip;</p>'
        );
        $('#wpmm-global-success').prop('hidden', true);
        $('#wpmm-global-progress').prop('hidden', true);

        $.post(wpmm.ajax_url, {
            action:  'wpmm_get_updates',
            nonce:   wpmm.nonce,
            site_id: $('#wpmm-scope-site-id').val() || 0
        }, function (res) {
            if (!res.success) {
                $container.html(
                    '<div class="wpmm-notice wpmm-notice-error">' +
                    '<span class="dashicons dashicons-warning"></span> ' +
                    'Failed to load updates: ' + escHtml(res.data || 'Unknown error') + '</div>'
                );
                return;
            }
            renderUpdates(res.data);
        }).fail(function () {
            $container.html(
                '<div class="wpmm-notice wpmm-notice-error">' +
                '<span class="dashicons dashicons-warning"></span> ' +
                'AJAX request failed. Please try again.</div>'
            );
        });
    }

    function renderUpdates(data) {
        var $container = $('#wpmm-update-sections');
        $container.empty();

        // Always hide the success banner at the start of renderUpdates.
        // It should only appear after a batch with at least one update
        // actually completes — never just because the page loaded clean.
        $('#wpmm-global-success').prop('hidden', true);
        $('#wpmm-global-progress').prop('hidden', true);

        var hasAny = data.core.length || data.plugins.length || data.themes.length;

        if (!hasAny) {
            $container.html(
                '<div class="wpmm-card"><div class="wpmm-all-good">' +
                '<span class="dashicons dashicons-yes-alt"></span>' +
                '<strong>Everything is up to date!</strong><br>' +
                'No WordPress core, plugin, or theme updates are available.</div></div>'
            );
            return;
        }

        if (data.core.length)    { $container.append(buildSection('WordPress Core', 'core',   data.core,    'admin-home')); }
        if (data.plugins.length) { $container.append(buildSection('Plugins',         'plugin', data.plugins, 'admin-plugins')); }
        if (data.themes.length)  { $container.append(buildSection('Themes',          'theme',  data.themes,  'admin-appearance')); }
    }

    function buildSection(title, type, items, icon) {
        var $card = $('<div class="wpmm-card wpmm-section-card"></div>');
        $card.append(
            '<h2 class="wpmm-card-title">' +
            '<span class="dashicons dashicons-' + icon + '"></span> ' + title +
            ' <span class="wpmm-badge wpmm-badge-error" style="margin-left:8px;">' +
            items.length + ' update' + (items.length !== 1 ? 's' : '') + '</span></h2>'
        );

        var sectionId  = 'section-' + type;
        var $selectRow = $('<div class="wpmm-select-all-row"></div>');
        var $cbAll     = $('<input type="checkbox" class="wpmm-select-all-cb wpmm-item-checkbox">').attr('data-section', sectionId);
        $selectRow.append($cbAll, $('<label style="cursor:pointer;margin-left:6px;">Select All</label>'));
        $card.append($selectRow);

        var $list = $('<ul class="wpmm-item-list"></ul>').attr('id', sectionId);

        items.forEach(function (item) {
            var $li  = $('<li class="wpmm-item"></li>').attr({
                'data-type':    type,
                'data-slug':    item.slug,
                'data-package': item.package || ''
            });
            var $cb  = $('<input type="checkbox" class="wpmm-item-checkbox wpmm-item-cb">').val(item.slug);
            var $inf = $('<div class="wpmm-item-info"></div>');
            $inf.append('<div class="wpmm-item-name">' + escHtml(item.name) + '</div>');
            $inf.append(
                '<div class="wpmm-item-meta">Current: <strong>' + escHtml(item.old_version) +
                '</strong> &rarr; Available: <strong>' + escHtml(item.new_version) + '</strong></div>'
            );
            var $act = $('<div class="wpmm-item-action"></div>');

            if ( item.requires_manual ) {
                // No package URL — vendor requires a browser-based license check
                // before providing a download link (e.g. Gravity Forms add-ons).
                // Show an informational warning instead of an Update button.
                $cb.prop('disabled', true).prop('checked', false);
                var $warn = $('<div class="wpmm-item-status"></div>').html(
                    '<span class="wpmm-status-warning" style="color:#b45309;">' +
                    '&#9888; Manual update required &mdash; this plugin\'s vendor requires a ' +
                    'browser-based license check to download updates. Please update it via ' +
                    'Dashboard &rarr; Updates or the plugin\'s own settings page.' +
                    '</span>'
                );
                $act.append($warn);
            } else {
                var $btn = $('<button class="wpmm-btn wpmm-btn-primary wpmm-btn-sm wpmm-update-one-btn">Update</button>')
                    .attr({ 'data-type': type, 'data-slug': item.slug });
                var $st  = $('<div class="wpmm-item-status"></div>');
                $act.append($btn, $st);
            }

            $li.append($cb, $inf, $act);
            $list.append($li);
        });

        $card.append($list);
        return $card;
    }

    // ── Select All per section ─────────────────────────────────────────────
    $(document).on('change', '.wpmm-select-all-cb', function () {
        var sectionId = $(this).data('section');
        $('#' + sectionId).find('.wpmm-item-cb').prop('checked', $(this).is(':checked'));
    });

    // ── Refresh button ─────────────────────────────────────────────────────
    $(document).on('click', '#wpmm-refresh-updates', function () {
        sessionId          = '';
        updatesRanThisLoad = false;
        performingAdminId  = parseInt($('#wpmm-performing-admin').val() || 0, 10);
        // Clear both state banners on a full refresh
        $('#wpmm-global-success').prop('hidden', true);
        $('#wpmm-global-progress').prop('hidden', true);
        loadUpdates();
    });

    // ── Backup Warning Modal ───────────────────────────────────────────────
    // Shown once per page load before the first update fires.
    // After the user confirms, all subsequent updates in the session
    // proceed without showing the modal again.
    var backupConfirmed = false;

    function requireBackupConfirmation(callback) {
        if (backupConfirmed) {
            callback(true);
            return;
        }
        var $modal = $('#wpmm-backup-modal');
        $modal.removeClass('wpmm-modal-closed');

        function cleanup() {
            $('#wpmm-backup-confirm, #wpmm-backup-cancel').off('click.backup');
            $('#wpmm-backup-modal .wpmm-modal-overlay').off('click.backup');
            $(document).off('keydown.backup');
        }

        $('#wpmm-backup-confirm').off('click.backup').on('click.backup', function () {
            backupConfirmed = true;
            $modal.addClass('wpmm-modal-closed');
            cleanup();
            callback(true);
        });

        $('#wpmm-backup-cancel, #wpmm-backup-modal .wpmm-modal-overlay')
            .off('click.backup').on('click.backup', function () {
            $modal.addClass('wpmm-modal-closed');
            cleanup();
            callback(false);
        });

        $(document).off('keydown.backup').on('keydown.backup', function (e) {
            if (e.key === 'Escape') {
                $modal.addClass('wpmm-modal-closed');
                cleanup();
                callback(false);
            }
        });
    }

    // ── Update Selected ────────────────────────────────────────────────────
    $(document).on('click', '#wpmm-update-selected', function () {
        requireBackupConfirmation(function (confirmed) {
            if (!confirmed) { return; }

        performingAdminId  = parseInt($('#wpmm-performing-admin').val() || 0, 10);
        sessionId          = 'wpmm-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
        updatesRanThisLoad = true;
        var items = [];
        $('.wpmm-item-cb:checked').each(function () {
            var $li = $(this).closest('.wpmm-item');
            items.push({
                type: $li.data('type'),
                slug: $li.data('slug'),
                pkg:  $li.data('package') || ''
            });
        });
        if (!items.length) {
            alert('Please select at least one item to update.');
            return;
        }

        // Warn if Avada theme is selected — companion plugins must follow in order.
        var avadaSelected = items.some(function (item) {
            return item.type === 'theme' && item.slug === 'Avada';
        });
        if (avadaSelected) {
            var msg = 'You have selected the Avada theme for update.\n\n'
                    + 'After this update completes, you must also update:\n'
                    + '  1. Avada Core\n'
                    + '  2. Avada Builder\n\n'
                    + 'Update them in that order from the Plugins section below, '
                    + 'or visit Avada → Maintenance → Plugins & Add-Ons.\n\n'
                    + 'Continue with the Avada theme update now?';
            if (!window.confirm(msg)) {
                return;
            }
        }

        // Reset progress bar state for the new batch.
        var totalItems     = items.length;
        var completedItems = 0;

        $('#wpmm-global-success').prop('hidden', true);
        $('#wpmm-progress-fill').css('width', '0%');
        $('#wpmm-progress-label').text(
            'Starting ' + totalItems + ' update' + (totalItems !== 1 ? 's' : '') + '…'
        );
        $('#wpmm-global-progress').prop('hidden', false);

        // Wrap the done callback to advance the progress bar after each item.
        var originalItems = items.slice(); // keep a reference to the full list

        function onItemComplete(itemName, success) {
            completedItems++;
            var pct = Math.round( (completedItems / totalItems) * 100 );
            $('#wpmm-progress-fill').css('width', pct + '%');
            var statusWord = success ? 'Updated' : 'Failed';
            var remaining  = totalItems - completedItems;
            if (remaining > 0) {
                $('#wpmm-progress-label').text(
                    statusWord + ': ' + itemName +
                    ' — ' + remaining + ' remaining…'
                );
            } else {
                $('#wpmm-progress-label').text(
                    statusWord + ': ' + itemName + ' — All done!'
                );
            }
        }

        runUpdatesSequential(items, 0, onItemComplete, function () {
            // Small pause so the 100% bar is visible before hiding.
            setTimeout(function () {
                $('#wpmm-global-progress').prop('hidden', true);
                $('#wpmm-global-success').prop('hidden', false);
            }, 600);
        });

        }); // end requireBackupConfirmation callback
    });

    // ── Update One ─────────────────────────────────────────────────────────
    $(document).on('click', '.wpmm-update-one-btn', function () {
        var $btn = $(this);
        var $li  = $btn.closest('.wpmm-item');
        requireBackupConfirmation(function (confirmed) {
            if (!confirmed) { return; }
            if ( !updatesRanThisLoad ) {
                sessionId          = 'wpmm-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
                updatesRanThisLoad = true;
            }
            runSingleUpdate(
                $li.data('type'),
                $li.data('slug'),
                $li.data('package') || '',
                $li,
                $btn,
                function () {}
            );
        });
    });

    // ── Sequential batch runner ────────────────────────────────────────────
    // onProgress(itemName, success) called after each item completes.
    // done() called when all items are finished.
    // A short delay between items prevents rapid-fire requests from being
    // throttled or timed out by shared hosting environments.
    function runUpdatesSequential(items, index, onProgress, done) {
        if (index >= items.length) { done(); return; }
        var item = items[index];
        var $li  = $('.wpmm-item[data-type="' + item.type + '"]').filter(function () {
            return $(this).attr('data-slug') === item.slug;
        });
        var $btn = $li.find('.wpmm-update-one-btn');
        runSingleUpdate(item.type, item.slug, item.pkg, $li, $btn, function (itemName, success) {
            if (onProgress) { onProgress(itemName, success); }
            // 800ms breathing room between updates — gives the server time to
            // fully complete the previous update before the next request lands.
            setTimeout(function () {
                runUpdatesSequential(items, index + 1, onProgress, done);
            }, 800);
        });
    }

    // ── Core AJAX update call ──────────────────────────────────────────────
    function runSingleUpdate(type, slug, pkg, $li, $btn, callback) {
        var $status = $li.find('.wpmm-item-status');
        $btn.prop('disabled', true).html(
            '<span class="dashicons dashicons-update wpmm-spin"></span> Updating&hellip;'
        );
        $status.html('');

        $.ajax({
            url:     wpmm.ajax_url,
            method:  'POST',
            timeout: 120000,   // 120s — plugin updates on slow hosts can take a while
            data: {
                action:     'wpmm_run_update',
                nonce:      wpmm.nonce,
                item_type:  type,
                item_slug:  slug,
                session_id: sessionId,
                package:    pkg,
                site_id:    $('#wpmm-scope-site-id').val() || 0
            },
            success: function (res) {
                $btn.prop('disabled', false);
                var itemName = (res.data && res.data.name) ? res.data.name : slug;
                var success  = res.success && res.data && res.data.status === 'success';

                if (success) {
                    $btn.html('Updated &#10003;')
                        .addClass('wpmm-btn-success')
                        .removeClass('wpmm-btn-primary')
                        .prop('disabled', true);
                    var successHtml = '<span class="wpmm-status-success">&#9989; Update Successful</span>';
                    // If other plugins were collaterally deactivated by WordPress's
                    // error recovery and Greenskeeper restored them, show a notice.
                    if (res.data.collateral_restored && res.data.collateral_restored.length) {
                        successHtml += '<div class="wpmm-status-warning" style="color:#b45309;margin-top:4px;font-size:12px;">' +
                            '&#9888; WordPress deactivated ' + res.data.collateral_restored.length +
                            ' other plugin(s) during this update — Greenskeeper restored them: ' +
                            res.data.collateral_restored.map(function(p){ return escHtml(p); }).join(', ') +
                            '</div>';
                    }
                    $status.html(successHtml);
                    $li.find('.wpmm-item-meta').text('Updated to version ' + res.data.new_version);
                } else {
                    var msg = '';
                    if (res.data && typeof res.data === 'object' && res.data.message) {
                        msg = res.data.message;
                    } else if (typeof res.data === 'string') {
                        msg = res.data;
                    } else {
                        msg = 'Update failed.';
                    }
                    $btn.html('Retry').removeClass('wpmm-btn-success');
                    $status.html(
                        '<span class="wpmm-status-failed">&#10060; Update Failed</span>' +
                        '<div class="wpmm-status-failed-reason">' + escHtml(msg) + '</div>'
                    );
                }
                callback(itemName, success);
            },
            error: function (xhr, status) {
                $btn.prop('disabled', false).html('Retry');
                var msg = status === 'timeout'
                    ? 'Request timed out. The server may be slow \u2014 click Retry to try again.'
                    : 'Request failed (HTTP ' + (xhr.status || '?') + '). Please try again.';
                $status.html(
                    '<span class="wpmm-status-failed">&#10060; ' + escHtml(msg) + '</span>'
                );
                callback(slug, false);
            }
        });
    }

    // ── Send email (Email Reports page) ────────────────────────────────────
    $(document).on('click', '#wpmm-send-email-btn', function () {
        var $btn    = $(this);
        var toEmail = $('#wpmm-email-to-tab').val().trim();
        var subject = $('#wpmm-email-subject-tab').val().trim();
        var $result = $('#wpmm-email-send-result');

        if (!toEmail) {
            $result.html('<div class="wpmm-notice wpmm-notice-error">Please enter a recipient email address.</div>');
            return;
        }
        if (!subject) {
            $result.html('<div class="wpmm-notice wpmm-notice-error">Please enter a subject line.</div>');
            return;
        }

        $btn.prop('disabled', true).html(
            '<span class="dashicons dashicons-update wpmm-spin"></span> Sending&hellip;'
        );
        $result.html('');

        // Resolve which session_id to send.
        // Only use the in-page sessionId if updates were actually run this page load.
        // Otherwise fall back to the persisted last session from the hidden field,
        // which PHP populated from the wpmm_last_session option.
        var lastSessionField = $('#wpmm-last-session-id').val();
        var resolvedSession  = updatesRanThisLoad ? sessionId : (lastSessionField || sessionId);

        var manualEntries = (typeof window.wpmm_getManualEntries === 'function')
            ? JSON.stringify(window.wpmm_getManualEntries())
            : '[]';

        var updateNote = $('#wpmm-update-notes').val() || '';

        $.ajax({
            url:     wpmm.ajax_url,
            method:  'POST',
            timeout: 60000,
            data: {
                action:          'wpmm_send_email',
                nonce:           wpmm.nonce,
                to_email:        toEmail,
                subject:         subject,
                session_id:      resolvedSession,
                admin_id:        performingAdminId,
                manual_entries:  manualEntries,
                update_note:     updateNote,
                site_id:         $('#wpmm-email-scope-site-id').val() || 0,
                network_all:     ($('#wpmm-email-scope-site-id').length && $('#wpmm-email-scope-site-id').val() == '0') ? 1 : 0
            },
            success: function (res) {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-email"></span> Send Report Email');
                if (res.success) {
                    $result.html(
                        '<div class="wpmm-notice wpmm-notice-success">' +
                        '<span class="dashicons dashicons-yes-alt"></span> ' +
                        escHtml(res.data.message) +
                        ' <a href="' + (wpmm.url_log || '#') + '">View log &rarr;</a></div>'
                    );

                    // ── Prepend new row to the Sent Email History table ───────
                    var row = res.data.row;
                    if (row) {
                        var subj   = row.subject.length > 65
                            ? row.subject.substring(0, 65) + '\u2026'
                            : row.subject;
                        var newRow =
                            '<tr id="wpmm-email-row-' + row.id + '" class="wpmm-history-new">' +
                            '<td>' + escHtml(row.sent_at) + '</td>' +
                            '<td>' + escHtml(row.to) + '</td>' +
                            '<td>' + escHtml(subj) + '</td>' +
                            '<td><span class="wpmm-badge wpmm-badge-success">Sent</span></td>' +
                            '<td style="text-align:center;">' +
                                '<button class="wpmm-preview-btn" type="button" title="Preview email"' +
                                ' data-id="' + row.id + '"' +
                                ' data-subject="' + escHtml(row.subject) + '"' +
                                ' data-to="' + escHtml(row.to) + '"' +
                                ' data-sent="' + escHtml(row.sent_at) + '">' +
                                '<span class="dashicons dashicons-visibility"></span>' +
                                '</button>' +
                            '</td>' +
                            '<td>' +
                                '<button class="wpmm-btn wpmm-btn-sm wpmm-resend-btn" data-id="' + row.id + '">' +
                                '<span class="dashicons dashicons-controls-repeat"></span> Resend' +
                                '</button>' +
                            '</td>' +
                            '</tr>';

                        var $tbody = $('#wpmm-email-history-tbody');
                        if ($tbody.length) {
                            $tbody.prepend(newRow);
                        } else {
                            var $empty = $('#wpmm-email-history-empty');
                            var table  =
                                '<table class="wpmm-table" id="wpmm-email-history-table">' +
                                '<thead><tr>' +
                                '<th>Sent At</th><th>To</th><th>Subject</th>' +
                                '<th>Status</th>' +
                                '<th style="text-align:center;">Preview</th>' +
                                '<th>Resend</th>' +
                                '</tr></thead>' +
                                '<tbody id="wpmm-email-history-tbody">' + newRow + '</tbody>' +
                                '</table>';
                            $empty.replaceWith(table);
                        }

                        // Green flash so the user sees the new row land.
                        setTimeout(function () {
                            $('#wpmm-email-row-' + row.id).addClass('wpmm-history-new-active');
                            setTimeout(function () {
                                $('#wpmm-email-row-' + row.id).removeClass('wpmm-history-new-active');
                            }, 2000);
                        }, 50);
                    }
                } else {
                    $result.html(
                        '<div class="wpmm-notice wpmm-notice-error">' +
                        '<span class="dashicons dashicons-warning"></span> ' +
                        escHtml(res.data || 'Send failed.') + '</div>'
                    );
                }
            },
            error: function (xhr, status) {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-email"></span> Send Report Email');
                var msg = status === 'timeout'
                    ? 'Request timed out. Please try again.'
                    : 'AJAX request failed (HTTP ' + (xhr.status || '?') + '). Please try again.';
                $result.html('<div class="wpmm-notice wpmm-notice-error">' + escHtml(msg) + '</div>');
            }
        });
    });

    // ── Resend email ───────────────────────────────────────────────────────
    $(document).on('click', '.wpmm-resend-btn', function () {
        var $btn    = $(this);
        var emailId = $btn.data('id');
        $btn.prop('disabled', true).html(
            '<span class="dashicons dashicons-update wpmm-spin"></span>'
        );

        $.post(wpmm.ajax_url, {
            action:   'wpmm_resend_email',
            nonce:    wpmm.nonce,
            email_id: emailId
        }, function (res) {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-controls-repeat"></span> Resend');
            alert(res.success
                ? 'Email resent successfully!'
                : 'Resend failed: ' + escHtml(res.data || 'Unknown error'));
        }).fail(function () {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-controls-repeat"></span> Resend');
            alert('Request failed.');
        });
    });

    // =========================================================================
    // Helpers
    // =========================================================================
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // =========================================================================
    // Auto-load updates on Updates page
    // =========================================================================
    if ($('#wpmm-update-sections').length) {
        loadUpdates();
    }


    // =========================================================================
    // UPDATE LOG — Search Autocomplete
    // =========================================================================
    (function () {
        var $input    = $('#wpmm-log-search');
        var $list     = $('#wpmm-autocomplete-list');
        var debounce  = null;
        var activeIdx = -1;

        if (!$input.length) return;  // only runs on the log page

        function showList(items) {
            $list.empty();
            if (!items.length) {
                hideList();
                return;
            }
            jQuery.each(items, function (i, name) {
                var $li = $('<li role="option" tabindex="-1"></li>')
                    .text(name)
                    .attr('id', 'wpmm-ac-opt-' + i);
                $li.on('mousedown', function (e) {
                    e.preventDefault();      // don't blur the input
                    selectItem(name);
                });
                $list.append($li);
            });
            $list.prop('hidden', false);
            $input.attr('aria-expanded', 'true');
            activeIdx = -1;
        }

        function hideList() {
            $list.prop('hidden', true).empty();
            $input.attr('aria-expanded', 'false');
            activeIdx = -1;
        }

        function selectItem(name) {
            $input.val(name);
            hideList();
            // Submit the form so the search fires immediately
            $('#wpmm-log-search-form').trigger('submit');
        }

        function highlightItem(idx) {
            var $items = $list.find('li');
            $items.removeClass('wpmm-ac-active').removeAttr('aria-selected');
            if (idx >= 0 && idx < $items.length) {
                $items.eq(idx).addClass('wpmm-ac-active').attr('aria-selected', 'true');
                $input.attr('aria-activedescendant', 'wpmm-ac-opt-' + idx);
            } else {
                $input.removeAttr('aria-activedescendant');
            }
            activeIdx = idx;
        }

        $input.on('input', function () {
            clearTimeout(debounce);
            var term = $input.val().trim();
            if (term.length < 2) { hideList(); return; }

            debounce = setTimeout(function () {
                jQuery.post(wpmm.ajax_url, {
                    action: 'wpmm_search_items',
                    nonce:  wpmm.nonce,
                    term:   term
                })
                .done(function (res) {
                    if (res && res.success && res.data.length) {
                        showList(res.data);
                    } else {
                        hideList();
                    }
                })
                .fail(function () { hideList(); });
            }, 200);
        });

        // Keyboard navigation inside the dropdown
        $input.on('keydown', function (e) {
            var $items = $list.find('li');
            if ($list.prop('hidden') || !$items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightItem(Math.min(activeIdx + 1, $items.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightItem(Math.max(activeIdx - 1, 0));
            } else if (e.key === 'Enter' && activeIdx >= 0) {
                e.preventDefault();
                selectItem($items.eq(activeIdx).text());
            } else if (e.key === 'Escape') {
                hideList();
            }
        });

        // Close when focus leaves the widget
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.wpmm-autocomplete-wrap').length) {
                hideList();
            }
        });
    })();


    // =========================================================================
    // DATABASE DIAGNOSTIC — Force Upgrade button
    // =========================================================================
    $(document).on('click', '#wpmm-force-upgrade-btn', function () {
        var $btn = $(this);
        var $result = $('#wpmm-upgrade-result');

        $btn.prop('disabled', true)
            .html('<span class="dashicons dashicons-update wpmm-spin"></span> Running…');
        $result.html('');

        jQuery.post(wpmm.ajax_url, {
            action: 'wpmm_force_db_upgrade',
            nonce:  wpmm.nonce
        })
        .done(function (res) {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-update"></span> Force DB Upgrade Now');
            if (res && res.success) {
                $result.html('<span style="color:#16a34a;font-weight:600;">&#10003; Upgrade complete. Reloading…</span>');
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                $result.html('<span style="color:#dc2626;">Upgrade failed: ' + (res.data || 'unknown error') + '</span>');
            }
        })
        .fail(function () {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-update"></span> Force DB Upgrade Now');
            $result.html('<span style="color:#dc2626;">Request failed.</span>');
        });
    });


    // =========================================================================
    // SETTINGS PAGE
    // =========================================================================

    // ── Inline edit helpers ───────────────────────────────────────────────────
    // Show/hide helpers for Settings inline-edit fields.
    // Using explicit inline style='display:...' is the most reliable approach —
    // it defeats any CSS specificity issue, any stale class state, and any
    // interference from other jQuery handlers that may have set display:none.
    function settingsShowDisplay(target) {
        var $edit    = $('#' + target + '-edit');
        var $display = $('#' + target + '-display');

        // Force hide the edit row with inline style (beats any class or cascade)
        $edit.css('display', 'none').addClass('wpmm-hidden');

        // Force show the display row — use flex to match the CSS intent
        // Remove the wpmm-hidden class first so the !important rule won't fight us
        $display.removeClass('wpmm-hidden').css('display', 'flex');
    }
    function settingsShowEdit(target) {
        var $edit    = $('#' + target + '-edit');
        var $display = $('#' + target + '-display');

        $display.css('display', 'none').addClass('wpmm-hidden');
        $edit.removeClass('wpmm-hidden').css('display', 'flex');
        $edit.find('input').first().focus();
    }

    $(document).on('click', '.wpmm-edit-link', function (e) {
        e.preventDefault();
        settingsShowEdit($(this).data('target'));
    });

    $(document).on('click', '.wpmm-cancel-link', function (e) {
        e.preventDefault();
        settingsShowDisplay($(this).data('target'));
    });

    // ── Save company name ─────────────────────────────────────────────────────
    $(document).on('click', '[data-save="company"]', function () {
        var $btn  = $(this);
        var value = $('#wpmm-company-input').val().trim();
        var $msg  = $('#wpmm-settings-msg');

        $btn.prop('disabled', true)
            .html('<span class="dashicons dashicons-update wpmm-spin"></span>');

        jQuery.post(wpmm.ajax_url, {
            action:       'wpmm_save_settings',
            nonce:        wpmm.nonce,
            company_name: value
        })
        .done(function (res) {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-yes"></span> Save');
            if (res && res.success) {
                $('#wpmm-company-text').text(value);
                settingsShowDisplay('wpmm-company');
                $msg.html('<div class="wpmm-notice wpmm-notice-success" style="margin-top:10px;">'
                    + '<span class="dashicons dashicons-yes-alt"></span> Company name saved.</div>');
                setTimeout(function () { $msg.html(''); }, 3000);
            } else {
                $msg.html('<div class="wpmm-notice wpmm-notice-error" style="margin-top:10px;">'
                    + '<span class="dashicons dashicons-warning"></span> Save failed.</div>');
            }
        })
        .fail(function () {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-yes"></span> Save');
            $msg.html('<div class="wpmm-notice wpmm-notice-error" style="margin-top:10px;">'
                + 'Request failed. Please try again.</div>');
        });
    });

    // ── Save client email ─────────────────────────────────────────────────────
    $(document).on('click', '[data-save="email"]', function () {
        var $btn  = $(this);
        var value = $('#wpmm-email-input').val().trim();
        var $msg  = $('#wpmm-settings-msg');

        $btn.prop('disabled', true)
            .html('<span class="dashicons dashicons-update wpmm-spin"></span>');

        jQuery.post(wpmm.ajax_url, {
            action:       'wpmm_save_settings',
            nonce:        wpmm.nonce,
            client_email: value
        })
        .done(function (res) {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-yes"></span> Save');
            if (res && res.success) {
                $('#wpmm-email-text').text(value);
                settingsShowDisplay('wpmm-email');
                $msg.html('<div class="wpmm-notice wpmm-notice-success" style="margin-top:10px;">'
                    + '<span class="dashicons dashicons-yes-alt"></span> Client email saved.</div>');
                setTimeout(function () { $msg.html(''); }, 3000);
            } else {
                $msg.html('<div class="wpmm-notice wpmm-notice-error" style="margin-top:10px;">'
                    + 'Save failed.</div>');
            }
        })
        .fail(function () {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-yes"></span> Save');
            $msg.html('<div class="wpmm-notice wpmm-notice-error" style="margin-top:10px;">'
                + 'Request failed. Please try again.</div>');
        });
    });

    // ── Save default administrator ────────────────────────────────────────────
    $(document).on('click', '#wpmm-save-admin-btn', function () {
        var $btn    = $(this);
        var adminId = jQuery('input[name="wpmm_default_admin"]:checked').val();
        var $msg    = $('#wpmm-admin-save-msg');

        if (!adminId) {
            $msg.text('Please select an administrator first.');
            return;
        }

        $btn.prop('disabled', true)
            .html('<span class="dashicons dashicons-update wpmm-spin"></span> Saving…');

        jQuery.post(wpmm.ajax_url, {
            action:           'wpmm_save_settings',
            nonce:            wpmm.nonce,
            default_admin_id: adminId
        })
        .done(function (res) {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-yes"></span> Save Default Administrator');
            if (res && res.success) {
                $msg.html('<span style="color:#16a34a;">&#10003; Default administrator saved.</span>');
                setTimeout(function () { $msg.html(''); }, 3000);
            } else {
                $msg.html('<span style="color:#dc2626;">Save failed. Please try again.</span>');
            }
        })
        .fail(function () {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-yes"></span> Save Default Administrator');
            $msg.html('<span style="color:#dc2626;">Request failed. Please try again.</span>');
        });
    });

    // ── Logo uploader (WP Media Library) ─────────────────────────────────────
    $(document).on('click', '#wpmm-logo-upload-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            alert('The WordPress media library is not available. Please reload the page and try again.');
            return;
        }

        if (!window._wpmm_media_frame) {
            window._wpmm_media_frame = wp.media({
                title:    'Select or Upload Company Logo',
                button:   { text: 'Use this logo' },
                multiple: false,
                library:  { type: 'image' }
            });
        }

        window._wpmm_media_frame.off('select').on('select', function () {
            var attachment = window._wpmm_media_frame.state()
                                .get('selection').first().toJSON();
            var url = attachment.url;

            // Update hidden field
            $('#wpmm-logo-url').val(url);

            // Update the preview well
            var $well = $('#wpmm-logo-well');
            $well.find('.wpmm-logo-empty-state').remove();
            if (!$well.find('.wpmm-logo-preview-img').length) {
                $well.prepend(
                    '<img class="wpmm-logo-preview-img" id="wpmm-logo-preview-img" alt="Logo">'
                    + '<button type="button" class="wpmm-logo-remove-btn" id="wpmm-logo-remove">'
                    + '<span class="dashicons dashicons-no-alt"></span></button>'
                );
            }
            $('#wpmm-logo-preview-img').attr('src', url);
            $('#wpmm-logo-upload-btn')
                .html('<span class="dashicons dashicons-upload"></span> Change Logo');

            // Save
            var $msg = $('#wpmm-settings-msg');
            $msg.html('<div class="wpmm-notice wpmm-notice-info" style="margin-top:10px;">'
                + '<span class="dashicons dashicons-update wpmm-spin"></span> Saving logo…</div>');

            jQuery.post(wpmm.ajax_url, {
                action:   'wpmm_save_settings',
                nonce:    wpmm.nonce,
                logo_url: url
            })
            .done(function (res) {
                if (res && res.success) {
                    $msg.html('<div class="wpmm-notice wpmm-notice-success" style="margin-top:10px;">'
                        + '<span class="dashicons dashicons-yes-alt"></span> Logo saved.</div>');
                    setTimeout(function () { $msg.html(''); }, 3000);
                } else {
                    $msg.html('<div class="wpmm-notice wpmm-notice-error" style="margin-top:10px;">'
                        + 'Logo save failed.</div>');
                }
            })
            .fail(function () {
                $msg.html('<div class="wpmm-notice wpmm-notice-error" style="margin-top:10px;">'
                    + 'Request failed.</div>');
            });
        });

        window._wpmm_media_frame.open();
    });

    // ── Remove logo ───────────────────────────────────────────────────────────
    $(document).on('click', '#wpmm-logo-remove', function (e) {
        e.preventDefault();
        $('#wpmm-logo-url').val('');
        var $well = $('#wpmm-logo-well');
        $well.find('.wpmm-logo-preview-img, .wpmm-logo-remove-btn').remove();
        if (!$well.find('.wpmm-logo-empty-state').length) {
            $well.append(
                '<div class="wpmm-logo-empty-state" id="wpmm-logo-empty">'
                + '<span class="dashicons dashicons-format-image"></span>'
                + '<span>No logo uploaded yet</span></div>'
            );
        }
        $('#wpmm-logo-upload-btn')
            .html('<span class="dashicons dashicons-upload"></span> Upload Logo');

        jQuery.post(wpmm.ajax_url, {
            action:   'wpmm_save_settings',
            nonce:    wpmm.nonce,
            logo_url: ''
        });
    });


    // =========================================================================
    // SMTP SETTINGS (Settings page)
    // =========================================================================
    (function () {
        // Provider-specific username hints
        var usernameHints = {
            default:   '',
            smtp:      'Your SMTP account login or email address.',
            sendgrid:  'Always enter <strong>apikey</strong> (literally) as the username.',
            mailgun:   'Your Mailgun SMTP login (usually your email address).',
            brevo:     'Your Brevo login email address.',
            sendlayer: 'Your SendLayer SMTP username (from your dashboard).',
            smtpcom:   'Your SMTP.com sender name / channel name.',
            gmail:     'Your full Gmail or Google Workspace address (e.g. <code>you@gmail.com</code>).',
            microsoft: 'Your full Microsoft / Outlook email address (e.g. <code>you@outlook.com</code> or <code>you@company.com</code>).'
        };

        function setActiveMailer(mailer) {
            // Tile selection
            $('.wpmm-mailer-tile').removeClass('wpmm-mailer-active')
                .find('.wpmm-mailer-check').remove();
            $('.wpmm-mailer-tile[data-mailer="' + mailer + '"]')
                .addClass('wpmm-mailer-active')
                .append('<span class="wpmm-mailer-check">&#10003;</span>');

            $('#wpmm-smtp-mailer').val(mailer);

            // Show/hide credential fields
            if (mailer === 'default') {
                $('#wpmm-smtp-fields').prop('hidden', true);
            } else {
                $('#wpmm-smtp-fields').prop('hidden', false);
            }

            // Show manual SMTP host/port only for 'smtp'
            if (mailer === 'smtp') {
                $('#wpmm-smtp-manual-fields').prop('hidden', false);
            } else {
                $('#wpmm-smtp-manual-fields').prop('hidden', true);
            }

            // Update username hint
            var hint = usernameHints[mailer] || usernameHints.smtp;
            $('#wpmm-username-hint').html(hint);

            // Show provider help
            $('.wpmm-mailer-help').hide();
            $('.wpmm-mailer-help[data-for="' + mailer + '"]').show();
        }

        // Initialise on page load
        var currentMailer = $('#wpmm-smtp-mailer').val() || 'default';
        if ($('#wpmm-mailer-grid').length) {
            setActiveMailer(currentMailer);
        }

        // Tile click
        $(document).on('click', '.wpmm-mailer-tile', function () {
            setActiveMailer($(this).data('mailer'));
        });

        // Show/hide password toggle
        $(document).on('click', '#wpmm-smtp-toggle-pw', function () {
            var $input = $('#wpmm-smtp-password');
            var $icon  = $(this).find('.dashicons');
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // Save SMTP settings
        $(document).on('click', '#wpmm-save-smtp-btn', function () {
            var $btn = $(this);
            var $msg = $('#wpmm-smtp-msg');
            var mailer = $('#wpmm-smtp-mailer').val();

            $btn.prop('disabled', true)
                .html('<span class="dashicons dashicons-update wpmm-spin"></span> Saving…');

            jQuery.post(wpmm.ajax_url, {
                action:          'wpmm_save_smtp',
                nonce:           wpmm.nonce,
                smtp_mailer:     mailer,
                smtp_host:       $('#wpmm-smtp-host').val()       || '',
                smtp_port:       $('#wpmm-smtp-port').val()       || 587,
                smtp_enc:        $('#wpmm-smtp-enc').val()        || 'tls',
                smtp_username:   $('#wpmm-smtp-username').val()   || '',
                smtp_password:   $('#wpmm-smtp-password').val()   || '',
                smtp_from_name:  $('#wpmm-smtp-from-name').val()  || '',
                smtp_from_email: $('#wpmm-smtp-from-email').val() || ''
            })
            .done(function (res) {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-yes"></span> Save Email Settings');
                if (res && res.success) {
                    $msg.html('<span style="color:#16a34a;">&#10003; Email settings saved.</span>');
                    // Clear password field — never leave it populated
                    $('#wpmm-smtp-password').val('').attr('placeholder', '‪•••••••  (saved)');
                    setTimeout(function () { $msg.html(''); }, 4000);
                } else {
                    $msg.html('<span style="color:#dc2626;">Save failed: '
                        + (res && res.data ? res.data : 'unknown error') + '</span>');
                }
            })
            .fail(function () {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-yes"></span> Save Email Settings');
                $msg.html('<span style="color:#dc2626;">Request failed.</span>');
            });
        });

        // Show test email input row
        $(document).on('click', '#wpmm-test-smtp-btn', function () {
            var $row = $('#wpmm-test-email-row');
            $row.prop('hidden', false).css('display', 'flex');
            $('#wpmm-test-email-addr').val('').focus();
        });

        $(document).on('click', '#wpmm-cancel-test-link', function (e) {
            e.preventDefault();
            $('#wpmm-test-email-row').prop('hidden', true);
        });

        // Send test email
        $(document).on('click', '#wpmm-send-test-btn', function () {
            var $btn  = $(this);
            var $msg  = $('#wpmm-smtp-msg');
            var email = $('#wpmm-test-email-addr').val().trim();

            $btn.prop('disabled', true)
                .html('<span class="dashicons dashicons-update wpmm-spin"></span>');

            jQuery.post(wpmm.ajax_url, {
                action:     'wpmm_test_smtp',
                nonce:      wpmm.nonce,
                test_email: email
            })
            .done(function (res) {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-email"></span> Send Now');
                if (res && res.success) {
                    $msg.html('<span style="color:#16a34a;">&#10003; ' + res.data + '</span>');
                    $('#wpmm-test-email-row').prop('hidden', true);
                } else {
                    $msg.html('<span style="color:#dc2626;">&#10007; Test failed: '
                        + (res && res.data ? res.data : 'Unknown error') + '</span>');
                }
            })
            .fail(function () {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-email"></span> Send Now');
                $msg.html('<span style="color:#dc2626;">Request failed.</span>');
            });
        });
    })();


    // =========================================================================
    // MANUAL UPDATES REPEATER (Email Reports page)
    // =========================================================================
    (function () {
        var $container = $('#wpmm-manual-rows');
        var $template  = $('#wpmm-manual-row-template');
        if (!$container.length || !$template.length) return;

        function addRow(name, oldVer, newVer) {
            // Clone the template content
            var clone = document.importNode($template[0].content, true);
            var $row  = $(clone).find('.wpmm-manual-row').length
                ? $(clone)
                : $(clone.firstElementChild || clone);

            // If name supplied, pre-select it
            if (name) {
                $row.find('.wpmm-manual-select').val(name);
            }
            if (oldVer) { $row.find('.wpmm-manual-old-version').val(oldVer); }
            if (newVer) { $row.find('.wpmm-manual-new-version').val(newVer); }

            // Auto-fill old version when plugin selected
            $row.find('.wpmm-manual-select').on('change', function () {
                var $opt = $(this).find('option:selected');
                var ver  = $opt.data('version') || '';
                $(this).closest('.wpmm-manual-row').find('.wpmm-manual-old-version').val(ver);
            });

            $container.append($row);
        }

        // Add initial empty row
        addRow();

        // Add row button
        $(document).on('click', '#wpmm-add-manual-row', function () {
            addRow();
        });

        // Remove row button
        $(document).on('click', '.wpmm-manual-remove', function () {
            $(this).closest('.wpmm-manual-row').remove();
        });

        // Expose a function to collect manual entries for the send handler
        window.wpmm_getManualEntries = function () {
            var entries = [];
            $container.find('.wpmm-manual-row').each(function () {
                var name    = $(this).find('.wpmm-manual-select').val();
                var oldVer  = $(this).find('.wpmm-manual-old-version').val().trim();
                var newVer  = $(this).find('.wpmm-manual-new-version').val().trim();
                if (name) {
                    entries.push({ name: name, old_version: oldVer, new_version: newVer });
                }
            });
            return entries;
        };
    })();


    // =========================================================================
    // REMOTE API KEY (Settings page)
    // =========================================================================
    (function () {
        // Copy key — reads from the one-time reveal panel, not a data attribute
        $(document).on('click', '#wpmm-copy-api-key', function () {
            var key = $('#wpmm-api-key-once').text().trim();
            if (!key) { return; }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(key).then(function () {
                    $('#wpmm-api-key-msg').html('<span style="color:#16a34a;">&#10003; Copied!</span>');
                    setTimeout(function () { $('#wpmm-api-key-msg').html(''); }, 2000);
                });
            } else {
                var $tmp = $('<textarea>').val(key).appendTo('body').select();
                document.execCommand('copy');
                $tmp.remove();
                $('#wpmm-api-key-msg').html('<span style="color:#16a34a;">&#10003; Copied!</span>');
                setTimeout(function () { $('#wpmm-api-key-msg').html(''); }, 2000);
            }
        });

        // Generate / rotate key — show key ONCE in reveal panel, never reload to display
        $(document).on('click', '#wpmm-generate-api-key', function () {
            var $btn = $(this);
            var $msg = $('#wpmm-api-key-msg');
            var isRotate = $btn.text().indexOf('Rotate') !== -1;

            if (isRotate && !window.confirm(
                'Rotating the key will immediately invalidate the current key.\n\n' +
                'Your hub site will need to be updated with the new key before it can ' +
                'connect to this site again.\n\nContinue?'
            )) { return; }

            $btn.prop('disabled', true)
                .html('<span class="dashicons dashicons-update wpmm-spin"></span> Generating…');

            jQuery.post(wpmm.ajax_url, {
                action: 'wpmm_generate_api_key',
                nonce:  wpmm.nonce
            })
            .done(function (res) {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-update"></span> Rotate Key');
                if (res && res.success && res.data.api_key) {
                    // Show the raw key exactly once — never store or display it again.
                    $('#wpmm-api-key-once').text(res.data.api_key);
                    $('#wpmm-new-key-reveal').show();
                    // Update the display badge to show key is configured.
                    $('#wpmm-api-key-display').text('\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022 (key configured \u2014 rotate to view a new key)');
                    // Show revoke button if it was hidden (first generation).
                    if (!$('#wpmm-revoke-api-key').length) {
                        $btn.after(' <button type="button" id="wpmm-revoke-api-key" class="wpmm-btn wpmm-btn-secondary wpmm-btn-sm" style="color:var(--wpmm-red);border-color:#fca5a5;"><span class="dashicons dashicons-trash"></span> Revoke Key</button>');
                    }
                    $msg.html('<span style="color:#16a34a;">&#10003; Key generated. Copy it now.</span>');
                } else {
                    $msg.html('<span style="color:#dc2626;">Failed: ' + (res.data || 'unknown error') + '</span>');
                }
            })
            .fail(function () {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-update"></span> ' + (isRotate ? 'Rotate Key' : 'Generate API Key'));
                $msg.html('<span style="color:#dc2626;">Request failed.</span>');
            });
        });

        // Revoke key
        $(document).on('click', '#wpmm-revoke-api-key', function () {
            if (!window.confirm(
                'Revoking the API key will immediately disable all remote access to this site.\n\nContinue?'
            )) { return; }

            var $btn = $(this);
            var $msg = $('#wpmm-api-key-msg');
            $btn.prop('disabled', true);

            jQuery.post(wpmm.ajax_url, {
                action: 'wpmm_revoke_api_key',
                nonce:  wpmm.nonce
            })
            .done(function (res) {
                if (res && res.success) {
                    $msg.html('<span style="color:#16a34a;">&#10003; Key revoked. Reloading…</span>');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    $btn.prop('disabled', false);
                    $msg.html('<span style="color:#dc2626;">Failed.</span>');
                }
            })
            .fail(function () {
                $btn.prop('disabled', false);
                $msg.html('<span style="color:#dc2626;">Request failed.</span>');
            });
        });
    })();


    // =========================================================================
    // SPAM FILTER SETTINGS (Settings page)
    // =========================================================================
    (function () {
        var $card = $('#wpmm-spam-card');
        if ( !$card.length ) return;

        // Toggle local options opacity when master switch changes
        $(document).on('change', '#wpmm-spam-filter-enabled', function () {
            var on = $(this).is(':checked');
            $('#wpmm-spam-local-options').css({
                opacity: on ? '1' : '0.5',
                'pointer-events': on ? '' : 'none'
            });
        });

        // Toggle label text for comments disabled
        $(document).on('change', '#wpmm-comments-disabled', function () {
            var on = $(this).is(':checked');
            $(this).closest('.wpmm-settings-group-control')
                   .find('.wpmm-toggle-label')
                   .text( on ? 'Comments are disabled site-wide' : 'Comments are enabled' );
        });

        // Toggle label text for spam filter switch
        $(document).on('change', '#wpmm-spam-filter-enabled', function () {
            var on = $(this).is(':checked');
            $(this).closest('.wpmm-settings-group-control')
                   .find('.wpmm-toggle-label')
                   .text( on ? 'Spam filtering is active' : 'Spam filtering is disabled' );
        });

        // Save spam settings
        $(document).on('click', '#wpmm-save-spam-btn', function () {
            var $btn = $(this);
            var $msg = $('#wpmm-spam-save-msg');

            $btn.prop('disabled', true)
                .html('<span class="dashicons dashicons-update wpmm-spin"></span> Saving…');

            $.post(wpmm.ajax_url, {
                action:               'wpmm_save_spam_settings',
                nonce:                wpmm.nonce,
                spam_filter_enabled:  $('#wpmm-spam-filter-enabled').is(':checked') ? 1 : '',
                comments_disabled:    $('#wpmm-comments-disabled').is(':checked')   ? 1 : '',
                spam_site_id:         $('#wpmm-spam-scope-site-id').val() || '0',
                spam_min_time:        $('#wpmm-spam-min-time').val(),
                spam_max_links:       $('#wpmm-spam-max-links').val(),
                spam_keywords:        $('#wpmm-spam-keywords').val(),
                spam_ip_blocklist:    $('#wpmm-spam-ip-blocklist').val(),
                site_id:              $('#wpmm-spam-site-id').val() || 0
            })
            .done(function (res) {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-yes"></span> Save Spam Settings');
                if (res && res.success) {
                    $msg.html('<span style="color:#16a34a;">&#10003; Settings saved.</span>');
                } else {
                    $msg.html('<span style="color:#dc2626;">Failed to save.</span>');
                }
                setTimeout(function () { $msg.html(''); }, 3000);
            })
            .fail(function () {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-yes"></span> Save Spam Settings');
                $msg.html('<span style="color:#dc2626;">Request failed.</span>');
            });
        });

        // Verify Akismet key
        $(document).on('click', '#wpmm-verify-akismet-btn', function () {
            var $btn = $(this);
            var $msg = $('#wpmm-akismet-msg');
            var key  = $('#wpmm-akismet-key').val().trim();

            if (!key) {
                $msg.html('<span style="color:#dc2626;">Please enter an API key first.</span>');
                return;
            }

            $btn.prop('disabled', true)
                .html('<span class="dashicons dashicons-update wpmm-spin"></span> Verifying…');

            $.post(wpmm.ajax_url, {
                action:      'wpmm_verify_akismet_key',
                nonce:       wpmm.nonce,
                akismet_key: key
            })
            .done(function (res) {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-yes-alt"></span> Re-verify Key');
                if (res && res.success) {
                    $msg.html('<span style="color:#16a34a;">&#10003; ' + res.data.message + '</span>');
                } else {
                    $msg.html('<span style="color:#dc2626;">&#10008; ' + (res.data || 'Verification failed.') + '</span>');
                }
            })
            .fail(function () {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-yes-alt"></span> Re-verify Key');
                $msg.html('<span style="color:#dc2626;">Request failed. Check your connection.</span>');
            });
        });

        // Revoke Akismet key
        $(document).on('click', '#wpmm-revoke-akismet-btn', function () {
            if (!window.confirm('Remove the Akismet API key? Cloud filtering will be disabled.')) return;
            var $btn = $(this);
            var $msg = $('#wpmm-akismet-msg');
            $btn.prop('disabled', true);

            $.post(wpmm.ajax_url, {
                action: 'wpmm_revoke_akismet_key',
                nonce:  wpmm.nonce
            })
            .done(function (res) {
                if (res && res.success) {
                    $msg.html('<span style="color:#16a34a;">&#10003; Key removed. Reloading…</span>');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    $btn.prop('disabled', false);
                    $msg.html('<span style="color:#dc2626;">Failed to remove key.</span>');
                }
            })
            .fail(function () {
                $btn.prop('disabled', false);
                $msg.html('<span style="color:#dc2626;">Request failed.</span>');
            });
        });

    })();


    // =========================================================================
    // SPAM LOG PAGE
    // =========================================================================
    (function () {
        var $table = $('#wpmm-spam-table');
        if ( !$table.length ) return;

        var $msg = $('#wpmm-spam-action-msg');

        function showMsg( text, ok ) {
            $msg.html('<span style="color:' + (ok ? '#16a34a' : '#dc2626') + ';">' + text + '</span>');
            setTimeout(function () { $msg.html(''); }, 4000);
        }

        // Select all checkbox
        $(document).on('change', '#wpmm-spam-select-all', function () {
            $table.find('.wpmm-spam-cb').prop('checked', $(this).is(':checked'));
        });

        // Apply filters — rebuild URL with filter params
        $(document).on('click', '#wpmm-spam-apply-filters', function () {
            var rule = $('#wpmm-spam-filter-rule').val();
            var ip   = $('#wpmm-spam-filter-ip').val().trim();
            var url  = new URL( window.location.href );
            url.searchParams.set('paged', '1');
            if (rule) { url.searchParams.set('rule', rule); }
            else       { url.searchParams.delete('rule'); }
            if (ip)   { url.searchParams.set('ip', ip); }
            else       { url.searchParams.delete('ip'); }
            window.location.href = url.toString();
        });

        // Delete selected rows
        $(document).on('click', '#wpmm-spam-delete-selected', function () {
            var ids = $table.find('.wpmm-spam-cb:checked').map(function () {
                return $(this).val();
            }).get();
            if (!ids.length) { showMsg('No rows selected.', false); return; }
            if (!window.confirm('Delete ' + ids.length + ' selected entr' + (ids.length === 1 ? 'y' : 'ies') + '?')) return;

            $.post(wpmm.ajax_url, {
                action:        'wpmm_delete_spam_entries',
                nonce:         wpmm.nonce,
                ids:           ids,
                spam_site_id:  $('#wpmm-spam-scope-site-id').val() || '0'
            }).done(function (res) {
                if (res && res.success) {
                    ids.forEach(function (id) {
                        $table.find('tr[data-id="' + id + '"]').remove();
                    });
                    showMsg('Deleted ' + ids.length + ' entr' + (ids.length === 1 ? 'y.' : 'ies.'), true);
                } else {
                    showMsg('Delete failed.', false);
                }
            }).fail(function () { showMsg('Request failed.', false); });
        });

        // Delete single row
        $(document).on('click', '.wpmm-spam-delete-row', function () {
            var id  = $(this).data('id');
            var $tr = $(this).closest('tr');
            if (!window.confirm('Delete this entry?')) return;

            $.post(wpmm.ajax_url, {
                action:       'wpmm_delete_spam_entries',
                nonce:        wpmm.nonce,
                ids:          [id],
                spam_site_id: $('#wpmm-spam-scope-site-id').val() || '0'
            }).done(function (res) {
                if (res && res.success) { $tr.remove(); showMsg('Entry deleted.', true); }
                else { showMsg('Delete failed.', false); }
            }).fail(function () { showMsg('Request failed.', false); });
        });

        // Clear all
        $(document).on('click', '#wpmm-spam-clear-all', function () {
            if (!window.confirm('Delete ALL spam log entries? This cannot be undone.')) return;
            $.post(wpmm.ajax_url, {
                action:       'wpmm_clear_spam_log',
                nonce:        wpmm.nonce,
                spam_site_id: $('#wpmm-spam-scope-site-id').val() || '0'
            }).done(function (res) {
                if (res && res.success) {
                    showMsg('Spam log cleared. Reloading…', true);
                    setTimeout(function () { location.reload(); }, 1200);
                } else { showMsg('Clear failed.', false); }
            }).fail(function () { showMsg('Request failed.', false); });
        });

        // Add IP to blocklist
        $(document).on('click', '.wpmm-spam-blocklist-ip', function () {
            var $btn = $(this);
            var ip   = $btn.data('ip');
            $btn.prop('disabled', true);
            $.post(wpmm.ajax_url, {
                action:       'wpmm_blocklist_ip',
                nonce:        wpmm.nonce,
                ip:           ip,
                spam_site_id: $('#wpmm-spam-scope-site-id').val() || '0'
            }).done(function (res) {
                $btn.prop('disabled', false);
                if (res && res.success) { showMsg(res.data.message, true); }
                else { showMsg('Failed: ' + (res.data || 'unknown error'), false); }
            }).fail(function () {
                $btn.prop('disabled', false);
                showMsg('Request failed.', false);
            });
        });

    })();


    // =========================================================================
    // MANAGE ACCESS (Settings page)
    // =========================================================================
    (function () {
        var $card = $('#wpmm-access-card');
        if ( !$card.length ) return;

        $(document).on('click', '#wpmm-save-access-btn', function () {
            var $btn = $(this);
            var $msg = $('#wpmm-access-save-msg');

            var ids = $card.find('.wpmm-access-cb:checked').map(function () {
                return $(this).val();
            }).get();

            // Always include disabled (locked) checkboxes — those are the current user
            $card.find('.wpmm-access-cb:disabled').each(function () {
                var val = $(this).val();
                if (ids.indexOf(val) === -1) { ids.push(val); }
            });

            if (!ids.length) {
                $msg.html('<span style="color:#dc2626;">At least one administrator must have access.</span>');
                return;
            }

            $btn.prop('disabled', true)
                .html('<span class="dashicons dashicons-update wpmm-spin"></span> Saving…');

            $.post(wpmm.ajax_url, {
                action:     'wpmm_save_access',
                nonce:      wpmm.nonce,
                access_ids: ids
            })
            .done(function (res) {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-yes"></span> Save Access Settings');
                if (res && res.success) {
                    $msg.html('<span style="color:#16a34a;">&#10003; Access settings saved.</span>');
                } else {
                    $msg.html('<span style="color:#dc2626;">Failed: ' + (res.data || 'unknown error') + '</span>');
                }
                setTimeout(function () { $msg.html(''); }, 3500);
            })
            .fail(function () {
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-yes"></span> Save Access Settings');
                $msg.html('<span style="color:#dc2626;">Request failed.</span>');
            });
        });
    })();


    // =========================================================================
    // SITE SCOPE BAR (Network Admin — Updates, Spam Log, Settings)
    // =========================================================================
    (function () {
        // The select renders with id="wpmm-site-scope" — guard and read use that ID.
        if ( !$('#wpmm-site-scope').length ) return;

        $(document).on('click', '#wpmm-scope-apply', function () {
            var $btn   = $(this);
            var siteId = $('#wpmm-site-scope').val();

            // Use the base URL stored in data-base-url on the button, which
            // wpmm_site_scope_bar() sets to the correct admin page URL without
            // any existing query args. Fall back to window.location if missing.
            var baseUrl = $btn.data('base-url') || window.location.href.split('?')[0];
            var url     = new URL( baseUrl, window.location.origin );

            // Preserve existing non-scope params (paged, rule, ip etc.)
            var existing = new URL( window.location.href );
            existing.searchParams.forEach( function( val, key ) {
                if ( key !== 'site_id' && key !== 'paged' ) {
                    url.searchParams.set( key, val );
                }
            });

            if ( siteId && siteId !== '0' ) {
                url.searchParams.set( 'site_id', siteId );
            } else {
                url.searchParams.delete( 'site_id' );
            }

            $btn.prop( 'disabled', true )
                .html( '<span class="dashicons dashicons-update wpmm-spin"></span> Loading&hellip;' );

            window.location.href = url.toString();
        });

        // Also trigger on Enter key in select
        $(document).on('keydown', '#wpmm-site-scope', function(e) {
            if (e.key === 'Enter') { $('#wpmm-scope-apply').trigger('click'); }
        });

        // Live feedback — change the select updates the button label
        $(document).on('change', '#wpmm-site-scope', function () {
            var val  = $(this).val();
            var text = val && val !== '0' ? 'Apply' : 'All Sites';
            $('#wpmm-scope-apply').html(
                '<span class="dashicons dashicons-filter"></span> ' + text
            );
        });
    })();


}); // end jQuery ready
