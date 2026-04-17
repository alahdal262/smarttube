(function($) {
    'use strict';

    var stopRequested = false;

    // ── Category search ──
    $(document).on('input', '#stube-cleanup-search', function () {
        var q = $(this).val().toLowerCase();
        $('.stube-cleanup-cat-label').each(function () {
            $(this).toggle($(this).data('name').indexOf(q) !== -1);
        });
    });

    // ── Check all / none categories ──
    $(document).on('click', '#stube-cleanup-check-all', function () {
        $('.stube-cleanup-cat-label:visible .stube-cleanup-cat-chk').prop('checked', true);
        updateCatCount();
    });
    $(document).on('click', '#stube-cleanup-check-none', function () {
        $('.stube-cleanup-cat-chk').prop('checked', false);
        updateCatCount();
    });
    $(document).on('change', '.stube-cleanup-cat-chk', function () {
        var $label = $(this).closest('.stube-cleanup-cat-label');
        if (this.checked) {
            $label.css({'border-color': '#d63638', 'background': '#fef2f2'});
        } else {
            $label.css({'border-color': '#dcdcde', 'background': '#fff'});
        }
        updateCatCount();
    });

    function updateCatCount() {
        var n = $('.stube-cleanup-cat-chk:checked').length;
        $('#stube-cleanup-cat-count').text(n + ' selected');
    }

    // ── Preview posts from selected categories ──
    $(document).on('click', '#stube-cleanup-preview', function () {
        var catIds = [];
        $('.stube-cleanup-cat-chk:checked').each(function () {
            catIds.push($(this).val());
        });

        if (!catIds.length) {
            $('#stube-cleanup-preview-status').text('Select at least one category.').css('color', '#d63638');
            return;
        }

        var $btn = $(this);
        var allPosts = $('#stube-cleanup-all-posts').is(':checked') ? 1 : 0;
        $btn.prop('disabled', true).text('Loading...');
        $('#stube-cleanup-preview-status').text('').css('color', '#646970');
        $('#stube-cleanup-results').hide();
        $('#stube-cleanup-delete-status').html('');
        $('#stube-cleanup-progress').hide();

        $.post(stubeCleanup.ajaxUrl, {
            action:    'stube_preview_cat_posts',
            nonce:     stubeCleanup.nonce,
            cat_ids:   JSON.stringify(catIds),
            all_posts: allPosts
        }, function (res) {
            $btn.prop('disabled', false).text('Preview Posts');

            if (!res.success) {
                $('#stube-cleanup-preview-status').text(res.data || 'Error.').css('color', '#d63638');
                return;
            }

            var posts = res.data.posts;
            if (!posts.length) {
                $('#stube-cleanup-preview-status').text('No posts found in selected categories.').css('color', '#856404');
                return;
            }

            var html = '<table class="wp-list-table widefat fixed striped" style="font-size:13px;">'
                     + '<thead><tr>'
                     + '<th style="width:40px;text-align:center;"><input type="checkbox" id="stube-cleanup-master" checked></th>'
                     + '<th style="width:55px;">Image</th>'
                     + '<th>Title</th>'
                     + '<th style="width:140px;">Category</th>'
                     + '<th style="width:90px;">Date</th>'
                     + '<th style="width:70px;">Status</th>'
                     + '<th style="width:55px;">ID</th>'
                     + '</tr></thead><tbody>';

            $.each(posts, function (i, p) {
                var img = p.thumb_url
                    ? '<img src="' + p.thumb_url + '" style="width:46px;height:34px;object-fit:cover;border-radius:3px;">'
                    : '<span style="color:#ccc;">--</span>';

                html += '<tr data-post-id="' + p.id + '">'
                      + '<td style="text-align:center;"><input type="checkbox" class="stube-cleanup-chk" value="' + p.id + '" checked></td>'
                      + '<td>' + img + '</td>'
                      + '<td><strong>' + $('<div>').text(p.title).html() + '</strong></td>'
                      + '<td style="color:#646970;font-size:12px;">' + $('<div>').text(p.category).html() + '</td>'
                      + '<td style="color:#646970;">' + p.date + '</td>'
                      + '<td><span style="font-size:11px;background:#f0f0f1;padding:2px 8px;border-radius:3px;">' + p.status + '</span></td>'
                      + '<td style="color:#999;">' + p.id + '</td>'
                      + '</tr>';
            });

            html += '</tbody></table>';

            $('#stube-cleanup-list').html(html);
            $('#stube-cleanup-found').text(posts.length + ' post(s) found across ' + catIds.length + ' category/ies.');
            $('#stube-cleanup-results').show();
        }).fail(function () {
            $btn.prop('disabled', false).text('Preview Posts');
            $('#stube-cleanup-preview-status').text('Request failed.').css('color', '#d63638');
        });
    });

    // ── Master checkbox ──
    $(document).on('change', '#stube-cleanup-master', function () {
        $('.stube-cleanup-chk').prop('checked', $(this).is(':checked'));
    });
    $(document).on('click', '#stube-cleanup-select-all', function () {
        $('.stube-cleanup-chk, #stube-cleanup-master').prop('checked', true);
    });
    $(document).on('click', '#stube-cleanup-select-none', function () {
        $('.stube-cleanup-chk, #stube-cleanup-master').prop('checked', false);
    });

    // ── Stop button ──
    $(document).on('click', '#stube-cleanup-stop', function () {
        stopRequested = true;
        $(this).prop('disabled', true).text('Stopping...');
    });

    // ── Delete in batches of 5 ──
    $(document).on('click', '#stube-cleanup-delete', function () {
        var ids = [];
        $('.stube-cleanup-chk:checked').each(function () {
            ids.push($(this).val());
        });

        if (!ids.length) {
            $('#stube-cleanup-delete-status').html('<span style="color:#d63638;">No posts selected.</span>');
            return;
        }

        if (!window.confirm('Permanently delete ' + ids.length + ' post(s) and their featured images?\n\nThis CANNOT be undone!')) {
            return;
        }

        // Disable controls
        var $btn = $(this);
        $btn.prop('disabled', true);
        $('#stube-cleanup-preview').prop('disabled', true);
        $('#stube-cleanup-select-all, #stube-cleanup-select-none').prop('disabled', true);
        $('.stube-cleanup-chk, #stube-cleanup-master').prop('disabled', true);

        // Show progress
        stopRequested = false;
        $('#stube-cleanup-stop').prop('disabled', false).text('Stop');
        $('#stube-cleanup-progress').show();
        $('#stube-cleanup-delete-status').html('');

        var totalToDelete = ids.length;
        var totalDeleted = 0;
        var totalImages = 0;
        var batchSize = 5;

        function deleteBatch(startIndex) {
            if (stopRequested) {
                finishDeletion('Stopped. ' + totalDeleted + '/' + totalToDelete + ' post(s) deleted, ' + totalImages + ' image(s) removed.', '#856404');
                return;
            }

            if (startIndex >= ids.length) {
                finishDeletion('Done! ' + totalDeleted + ' post(s) deleted, ' + totalImages + ' image(s) removed from media library.', '#00a32a');
                return;
            }

            var batch = ids.slice(startIndex, startIndex + batchSize);
            var pct = Math.round((startIndex / totalToDelete) * 100);
            $('#stube-cleanup-bar').css('width', pct + '%');
            $('#stube-cleanup-bar-text').text(pct + '%');
            $('#stube-cleanup-progress-text').text('Deleting... ' + startIndex + ' / ' + totalToDelete);

            $.post(stubeCleanup.ajaxUrl, {
                action:   'stube_delete_batch',
                nonce:    stubeCleanup.nonce,
                post_ids: JSON.stringify(batch)
            }, function (res) {
                if (res.success) {
                    totalDeleted += res.data.deleted_posts;
                    totalImages  += res.data.deleted_images;

                    // Remove rows
                    $.each(batch, function (_, id) {
                        $('tr[data-post-id="' + id + '"]').css('background', '#fef2f2').fadeOut(200, function () {
                            $(this).remove();
                        });
                    });
                }

                // Next batch after short delay
                setTimeout(function () {
                    deleteBatch(startIndex + batchSize);
                }, 300);

            }).fail(function () {
                // Retry once after a longer pause (WAF cooldown)
                setTimeout(function () {
                    $.post(stubeCleanup.ajaxUrl, {
                        action:   'stube_delete_batch',
                        nonce:    stubeCleanup.nonce,
                        post_ids: JSON.stringify(batch)
                    }, function (res) {
                        if (res.success) {
                            totalDeleted += res.data.deleted_posts;
                            totalImages  += res.data.deleted_images;
                            $.each(batch, function (_, id) {
                                $('tr[data-post-id="' + id + '"]').fadeOut(200, function () { $(this).remove(); });
                            });
                        }
                        setTimeout(function () { deleteBatch(startIndex + batchSize); }, 500);
                    }).fail(function () {
                        // Skip this batch and continue
                        $('#stube-cleanup-progress-text').text('Batch failed, skipping... (' + batch.join(',') + ')');
                        setTimeout(function () { deleteBatch(startIndex + batchSize); }, 1000);
                    });
                }, 2000);
            });
        }

        deleteBatch(0);

        function finishDeletion(msg, color) {
            var pct = Math.round((totalDeleted / totalToDelete) * 100);
            $('#stube-cleanup-bar').css('width', pct + '%');
            $('#stube-cleanup-bar-text').text(pct + '%');
            $('#stube-cleanup-progress-text').text('Complete');
            $('#stube-cleanup-delete-status').html('<span style="color:' + color + ';">' + msg + '</span>');

            // Re-enable controls
            $btn.prop('disabled', false);
            $('#stube-cleanup-preview').prop('disabled', false);
            $('#stube-cleanup-select-all, #stube-cleanup-select-none').prop('disabled', false);
            $('.stube-cleanup-chk, #stube-cleanup-master').prop('disabled', false);

            var remaining = $('#stube-cleanup-list tbody tr:visible').length;
            $('#stube-cleanup-found').text(remaining + ' post(s) remaining.');
        }
    });

})(jQuery);
