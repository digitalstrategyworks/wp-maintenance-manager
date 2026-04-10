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
            action: 'wpmm_get_updates',
            nonce:  wpmm.nonce
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

        var hasAny = data.core.length || data.plugins.length || data.themes.length;

        // If new updates are available, hide the success banner — it belonged to
        // the previous session and is no longer relevant.
        if (hasAny) {
            $('#wpmm-global-success').prop('hidden', true);
            $('#wpmm-global-progress').prop('hidden', true);
        }

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
            var $btn = $('<button class="wpmm-btn wpmm-btn-primary wpmm-btn-sm wpmm-update-one-btn">Update</button>')
                .attr({ 'data-type': type, 'data-slug': item.slug });
            var $st  = $('<div class="wpmm-item-status"></div>');
            $act.append($btn, $st);
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

    // ── Update Selected ────────────────────────────────────────────────────
    $(document).on('click', '#wpmm-update-selected', function () {
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
    });

    // ── Update One ─────────────────────────────────────────────────────────
    $(document).on('click', '.wpmm-update-one-btn', function () {
        var $btn = $(this);
        var $li  = $btn.closest('.wpmm-item');
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

    // ── Sequential batch runner ────────────────────────────────────────────
    // onProgress(itemName, success) called after each item completes.
    // done() called when all items are finished.
    function runUpdatesSequential(items, index, onProgress, done) {
        if (index >= items.length) { done(); return; }
        var item = items[index];
        var $li  = $('.wpmm-item[data-type="' + item.type + '"]').filter(function () {
            return $(this).attr('data-slug') === item.slug;
        });
        var $btn = $li.find('.wpmm-update-one-btn');
        runSingleUpdate(item.type, item.slug, item.pkg, $li, $btn, function (itemName, success) {
            if (onProgress) { onProgress(itemName, success); }
            runUpdatesSequential(items, index + 1, onProgress, done);
        });
    }

    // ── Core AJAX update call ──────────────────────────────────────────────
    function runSingleUpdate(type, slug, pkg, $li, $btn, callback) {
        var $status = $li.find('.wpmm-item-status');
        $btn.prop('disabled', true).html(
            '<span class="dashicons dashicons-update wpmm-spin"></span> Updating&hellip;'
        );
        $status.html('');

        $.post(wpmm.ajax_url, {
            action:     'wpmm_run_update',
            nonce:      wpmm.nonce,
            item_type:  type,
            item_slug:  slug,
            session_id: sessionId,
            package:    pkg
        }, function (res) {
            $btn.prop('disabled', false);
            var itemName = (res.data && res.data.name) ? res.data.name : slug;
            var success  = res.success && res.data && res.data.status === 'success';

            if (success) {
                $btn.html('Updated &#10003;')
                    .addClass('wpmm-btn-success')
                    .removeClass('wpmm-btn-primary')
                    .prop('disabled', true);
                $status.html('<span class="wpmm-status-success">&#9989; Update Successful</span>');
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
        }).fail(function () {
            $btn.prop('disabled', false).html('Retry');
            $status.html(
                '<span class="wpmm-status-failed">&#10060; Request failed. Please try again.</span>'
            );
            callback(slug, false);
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

        $.post(wpmm.ajax_url, {
            action:          'wpmm_send_email',
            nonce:           wpmm.nonce,
            to_email:        toEmail,
            subject:         subject,
            session_id:      resolvedSession,
            admin_id:        performingAdminId,
            manual_entries:  manualEntries,
            update_note:     updateNote
        }, function (res) {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-email"></span> Send Report Email');
            if (res.success) {
                $result.html(
                    '<div class="wpmm-notice wpmm-notice-success">' +
                    '<span class="dashicons dashicons-yes-alt"></span> ' +
                    escHtml(res.data.message) +
                    ' <a href="' + (wpmm.url_log || '#') + '">View log &rarr;</a></div>'
                );
            } else {
                $result.html(
                    '<div class="wpmm-notice wpmm-notice-error">' +
                    '<span class="dashicons dashicons-warning"></span> ' +
                    escHtml(res.data || 'Send failed.') + '</div>'
                );
            }
        }).fail(function () {
            $btn.prop('disabled', false)
                .html('<span class="dashicons dashicons-email"></span> Send Report Email');
            $result.html('<div class="wpmm-notice wpmm-notice-error">AJAX request failed.</div>');
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
        // Copy key to clipboard
        $(document).on('click', '#wpmm-copy-api-key', function () {
            var key = $(this).data('key');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(key).then(function () {
                    $('#wpmm-api-key-msg').html('<span style="color:#16a34a;">&#10003; Copied!</span>');
                    setTimeout(function () { $('#wpmm-api-key-msg').html(''); }, 2000);
                });
            } else {
                // Fallback for older browsers
                var $tmp = $('<textarea>').val(key).appendTo('body').select();
                document.execCommand('copy');
                $tmp.remove();
                $('#wpmm-api-key-msg').html('<span style="color:#16a34a;">&#10003; Copied!</span>');
                setTimeout(function () { $('#wpmm-api-key-msg').html(''); }, 2000);
            }
        });

        // Generate / rotate key
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
                if (res && res.success) {
                    $msg.html('<span style="color:#16a34a;">&#10003; Key generated. Reloading…</span>');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    $btn.prop('disabled', false)
                        .html('<span class="dashicons dashicons-update"></span> ' + (isRotate ? 'Rotate Key' : 'Generate API Key'));
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


}); // end jQuery ready
