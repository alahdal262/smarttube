(function($) {
    'use strict';

    // Sync color picker with text input
    $(document).on('input', 'input[type="color"]', function() {
        $(this).nextAll('input[type="text"]').first().val(this.value);
    });
    $(document).on('input', 'input[name$="_color_text"]', function() {
        var val = $(this).val();
        if (/^#[0-9a-fA-F]{6}$/.test(val)) {
            $(this).prevAll('input[type="color"]').first().val(val);
        }
        var realName = $(this).attr('name').replace('_text', '');
        $('input[name="' + realName + '"]').val(val);
    });

    // Save settings
    $(document).on('submit', '#stube-settings-form', function(e) {
        e.preventDefault();
        var $status = $('#stube-status');
        $status.text('Saving...');
        $.post(stubeAdmin.ajaxUrl, $(this).serialize() + '&action=stube_save_settings&nonce=' + stubeAdmin.nonce, function(r) {
            $status.text(r.success ? r.data : 'Error: ' + r.data);
            setTimeout(function() { $status.text(''); }, 3000);
        });
    });

    // Fetch playlists
    $(document).on('click', '#stube-fetch-playlists', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Fetching...');
        $.post(stubeAdmin.ajaxUrl, {action: 'stube_fetch_playlists', nonce: stubeAdmin.nonce}, function(r) {
            $btn.prop('disabled', false).text('Fetch Playlists');
            if (r.success) {
                alert('Found ' + r.data.count + ' playlists! Reloading...');
                location.reload();
            } else {
                alert('Error: ' + r.data);
            }
        });
    });

    // Clear cache
    $(document).on('click', '#stube-clear-cache', function() {
        $.post(stubeAdmin.ajaxUrl, {action: 'stube_clear_cache', nonce: stubeAdmin.nonce}, function(r) {
            $('#stube-status').text(r.success ? r.data : 'Error');
            setTimeout(function() { $('#stube-status').text(''); }, 3000);
        });
    });

    // Copy to clipboard
    $(document).on('click', '.stube-copy', function() {
        var text = $(this).text().trim();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        $('#stube-copy-toast').stop(true).fadeIn(200);
        setTimeout(function() { $('#stube-copy-toast').fadeOut(400); }, 2000);
    });

    // === Exclude Categories ===
    $(document).on('click', '#stube-excl-all', function() {
        $('.stube-excl-check').prop('checked', true);
        updateExclCount();
    });
    $(document).on('click', '#stube-excl-none', function() {
        $('.stube-excl-check').prop('checked', false);
        updateExclCount();
    });
    $(document).on('change', '.stube-excl-check', function() {
        updateExclCount();
    });
    function updateExclCount() {
        var count = $('.stube-excl-check:checked').length;
        $('#stube-excl-count').text(count + ' excluded');
    }

    // Save excluded categories
    $(document).on('click', '#stube-save-excluded', function() {
        var $btn = $(this);
        var $status = $('#stube-excl-status');
        var excluded = [];

        $('.stube-excl-check:checked').each(function() {
            excluded.push($(this).val());
        });

        $btn.prop('disabled', true);
        $status.text('Saving...').css('color', '#856404');

        $.ajax({
            url: stubeAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'stube_save_excluded',
                nonce: stubeAdmin.nonce,
                excluded_json: JSON.stringify(excluded)
            },
            success: function(r) {
                $btn.prop('disabled', false);
                if (r.success) {
                    $status.text('✓ ' + r.data).css('color', '#00a32a');
                } else {
                    $status.text('✗ ' + (r.data || 'Error')).css('color', '#d63638');
                }
                setTimeout(function() { $status.text(''); }, 4000);
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false);
                $status.text('✗ Network error: ' + error).css('color', '#d63638');
            }
        });
    });

    // Toggle display label
    $(document).on('change', '.stube-enable-check', function() {
        var $label = $(this).closest('label').find('.stube-display-label');
        if (this.checked) {
            $label.text('📺 Videos').css('color', '#00a32a');
        } else {
            $label.text('📰 Posts').css('color', '#d63638');
        }
    });

    // Show/hide opacity when category changes
    $(document).on('change', '.stube-cat-select', function() {
        var $row = $(this).closest('tr');
        $row.css('opacity', $(this).val() ? '1' : '0.5');
    });

    // Bulk set all limits
    $(document).on('click', '#stube-apply-bulk-limit', function() {
        var val = $('#stube-bulk-limit').val();
        if (!val || val < 1) return;
        // Only apply to linked (rows with category selected)
        $('#stube-pl-cat-table tbody tr').each(function() {
            if ($(this).find('.stube-cat-select').val()) {
                $(this).find('.stube-limit-input').val(val);
            }
        });
    });

    // Enable all linked
    $(document).on('click', '#stube-enable-all-linked', function() {
        $('#stube-pl-cat-table tbody tr').each(function() {
            if ($(this).find('.stube-cat-select').val()) {
                $(this).find('.stube-enable-check').prop('checked', true).trigger('change');
            }
        });
    });

    // Disable all linked
    $(document).on('click', '#stube-disable-all-linked', function() {
        $('#stube-pl-cat-table tbody tr').each(function() {
            if ($(this).find('.stube-cat-select').val()) {
                $(this).find('.stube-enable-check').prop('checked', false).trigger('change');
            }
        });
    });

    // Update shortcode when limit changes
    $(document).on('change', '.stube-limit-input', function() {
        var limit = $(this).val();
        var $code = $(this).closest('tr').find('.stube-copy');
        var text = $code.text();
        $code.text(text.replace(/limit="\d+"/, 'limit="' + limit + '"'));
    });

    // === SAVE CATEGORY LINKS (as JSON to avoid max_input_vars) ===
    $(document).on('click', '#stube-save-links', function() {
        var $btn = $(this);
        var $status = $('#stube-links-status');

        // Collect links
        var links = {};
        var limits = {};
        var enabled = {};

        $('.stube-cat-select').each(function() {
            var pl = $(this).data('playlist');
            var cat = $(this).val();
            var $row = $(this).closest('tr');
            var limit = $row.find('.stube-limit-input').val();
            var isOn = $row.find('.stube-enable-check').is(':checked') ? 1 : 0;

            if (cat) links[pl] = cat;
            limits[pl] = limit || '12';
            enabled[pl] = isOn;
        });

        // Collect excluded categories
        var excluded = [];
        $('.stube-excl-check:checked').each(function() {
            excluded.push($(this).val());
        });

        $btn.prop('disabled', true);
        $status.text('Saving...').css('color', '#646970');

        $.ajax({
            url: stubeAdmin.ajaxUrl,
            type: 'POST',
            contentType: 'application/x-www-form-urlencoded',
            data: {
                action: 'stube_save_links',
                nonce: stubeAdmin.nonce,
                links_json: JSON.stringify(links),
                limits_json: JSON.stringify(limits),
                enabled_json: JSON.stringify(enabled),
                excluded_json: JSON.stringify(excluded)
            },
            success: function(r) {
                $btn.prop('disabled', false);
                if (r.success) {
                    $status.text('✓ ' + r.data).css('color', '#00a32a');
                } else {
                    $status.text('✗ ' + (r.data || 'Error')).css('color', '#d63638');
                }
                setTimeout(function() { $status.text(''); }, 4000);
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false);
                $status.text('✗ Error: ' + (error || status)).css('color', '#d63638');
                console.log('SmartTube save error:', status, error, xhr.responseText);
            }
        });
    });

    // === Create Categories for Playlists ===
    $(document).on('click', '#stube-create-cats', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Creating...');

        $.post(stubeAdmin.ajaxUrl, {
            action: 'stube_create_categories',
            nonce: stubeAdmin.nonce
        }, function(r) {
            $btn.prop('disabled', false).text('🏷️ Create Categories for Unlinked Playlists');
            if (r.success) {
                alert(r.data);
                location.reload();
            } else {
                alert('Error: ' + r.data);
            }
        });
    });

    // === Tabs Builder ===
    // Search playlists
    $(document).on('input', '#stube-pl-search', function() {
        var q = $(this).val().toLowerCase();
        $('#stube-pl-grid .stube-pl-label').each(function() {
            var name = $(this).data('name') || '';
            $(this).toggle(name.indexOf(q) !== -1);
        });
    });

    // Select All / None
    $(document).on('click', '#stube-select-all', function() {
        $('#stube-pl-grid .stube-pl-check:visible').prop('checked', true).trigger('change');
    });
    $(document).on('click', '#stube-select-none', function() {
        $('#stube-pl-grid .stube-pl-check').prop('checked', false).trigger('change');
    });

    // Checkbox change → generate shortcode
    $(document).on('change', '.stube-pl-check', function() {
        updateGeneratedShortcode();
        $(this).closest('.stube-pl-label').css(
            'border-color', this.checked ? '#2271b1' : '#dcdcde'
        ).css(
            'background', this.checked ? '#f0f6fc' : '#fff'
        );
    });

    function updateGeneratedShortcode() {
        var selected = [];
        var count = 0;

        $('.stube-pl-check:checked').each(function() {
            var id = $(this).val();
            var title = $(this).data('title');
            selected.push(id + ':' + title);
            count++;
        });

        $('#stube-selected-count').text(count + ' selected');

        var shortcode;
        if (count === 0) {
            shortcode = '[smarttube_tabs header="برامج القناة"]';
        } else {
            shortcode = '[smarttube_tabs playlists="' + selected.join(',') + '" header="برامج القناة"]';
        }

        $('#stube-generated-shortcode').text(shortcode);
    }

    // Filter playlists: All / Linked / Unlinked
    $(document).on('click', '.stube-filter-btn', function() {
        $('.stube-filter-btn').removeClass('active').css({'background':'','color':'','border-color':''});
        $(this).addClass('active').css({'background':'#2271b1','color':'#fff','border-color':'#2271b1'});

        var filter = $(this).data('filter');
        $('#stube-pl-cat-table tbody tr').each(function() {
            var linked = $(this).data('linked');
            if (filter === 'all') {
                $(this).show();
            } else if (filter === 'linked') {
                $(this).toggle(linked === 'yes');
            } else if (filter === 'unlinked') {
                $(this).toggle(linked === 'no');
            }
        });
    });

    // Highlight active filter on load
    $('.stube-filter-btn.active').css({'background':'#2271b1','color':'#fff','border-color':'#2271b1'});

    // Search playlists in table
    $(document).on('input', '#stube-pl-table-search', function() {
        var q = $(this).val().toLowerCase();
        $('#stube-pl-cat-table tbody tr').each(function() {
            var name = $(this).data('plname') || '';
            $(this).toggle(name.indexOf(q) !== -1);
        });
    });


})(jQuery);
