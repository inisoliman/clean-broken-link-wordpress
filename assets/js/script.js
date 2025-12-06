jQuery(document).ready(function ($) {
    var isScanning = false;
    var stats = {
        postsScanned: 0,
        linksFound: 0,
        imagesFound: 0,
        itemsRemoved: 0
    };

    // Tab switching
    $('.nav-tab').on('click', function (e) {
        e.preventDefault();
        var target = $(this).attr('href');

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').hide();
        $(target + '-tab').show();

        if (target === '#report') {
            loadReport();
        }
    });

    // Check for saved progress
    if (smartCleaner.saved_progress) {
        $('#resume-scan').show();
        $('#reset-scan').show();
        $('#start-scan').text('Start New Scan (Resets Progress)');
        logStatus('Saved progress found: ' + smartCleaner.saved_progress.percentage + '% complete.');

        if (smartCleaner.saved_progress.dry_run) {
            $('#delete-pending').show();
        }
    }

    $('#start-scan').on('click', function () {
        if (isScanning) return;
        if (smartCleaner.saved_progress) {
            if (!confirm('Starting a new scan will reset your previous progress. Are you sure?')) {
                return;
            }
        }

        resetProgress(function () {
            startScan(0, 0);
        });
    });

    $('#resume-scan').on('click', function () {
        if (isScanning) return;
        if (smartCleaner.saved_progress) {
            var offset = parseInt(smartCleaner.saved_progress.offset);
            var linkOffset = smartCleaner.saved_progress.link_offset ? parseInt(smartCleaner.saved_progress.link_offset) : 0;
            startScan(offset, linkOffset);
        }
    });

    $('#reset-scan').on('click', function () {
        if (confirm('Are you sure you want to reset the saved progress?')) {
            resetProgress(function () {
                alert('Progress reset.');
                location.reload();
            });
        }
    });

    $('#stop-scan').on('click', function () {
        isScanning = false;
        logStatus('Stopping scan...');
        toggleButtons(false);
        if ($('#dry_run').is(':checked')) {
            $('#delete-pending').show();
        }
    });

    $('#delete-pending').on('click', function () {
        if (confirm('Are you sure you want to delete all broken items found so far? This cannot be undone.')) {
            deletePending();
        }
    });

    function startScan(offset, linkOffset) {
        isScanning = true;
        toggleButtons(true);
        $('#progress-card').show();
        $('#delete-pending').hide();

        if (offset === 0 && linkOffset === 0) {
            stats = { postsScanned: 0, linksFound: 0, imagesFound: 0, itemsRemoved: 0 };
            $('#current-status-log').html('');
            $('.smart-cleaner-progress-bar').css('width', '0%');
            $('#progress-text').text('Starting...');
        } else {
            logStatus('Resuming from post offset ' + offset + ', link offset ' + linkOffset + '...');
        }

        updateStatsUI();

        var postType = $('#post_type').val();
        var dryRun = $('#dry_run').is(':checked');

        processBatch(offset, linkOffset, postType, dryRun);
    }

    function processBatch(offset, linkOffset, postType, dryRun) {
        if (!isScanning) return;

        $.ajax({
            url: smartCleaner.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_cleaner_scan',
                nonce: smartCleaner.nonce,
                offset: offset,
                link_offset: linkOffset,
                post_type: postType,
                dry_run: dryRun
            },
            success: function (response) {
                if (response.success) {
                    if (response.data.done) {
                        finishScan();
                    } else {
                        updateProgress(response.data);
                        setTimeout(function () {
                            processBatch(response.data.offset, response.data.link_offset, postType, dryRun);
                        }, 100);
                    }
                } else {
                    logStatus('Error: ' + (response.data || 'Unknown error'));
                    isScanning = false;
                    toggleButtons(false);
                }
            },
            error: function (xhr, status, error) {
                logStatus('AJAX Error: ' + error + '. Status: ' + status);
                logStatus('Response: ' + xhr.responseText);
                isScanning = false;
                toggleButtons(false);
            }
        });
    }

    function deletePending() {
        $.ajax({
            url: smartCleaner.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_cleaner_delete_pending',
                nonce: smartCleaner.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert('Successfully deleted ' + response.data.count + ' items.');
                    stats.itemsRemoved += response.data.count;
                    updateStatsUI();
                    $('#delete-pending').hide();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function () {
                alert('Error deleting items.');
            }
        });
    }

    function resetProgress(callback) {
        $.ajax({
            url: smartCleaner.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_cleaner_reset',
                nonce: smartCleaner.nonce
            },
            success: function () {
                if (callback) callback();
            }
        });
    }

    function updateProgress(data) {
        $('.smart-cleaner-progress-bar').css('width', data.percentage + '%');
        $('#progress-text').text(data.percentage + '% Complete');

        if (data.results && data.results.length > 0) {
            data.results.forEach(function (result) {
                if (!result.partial) {
                    stats.postsScanned++;
                }

                var linksCount = result.scan.links ? result.scan.links.length : 0;
                var imagesCount = result.scan.images ? result.scan.images.length : 0;

                stats.linksFound += linksCount;
                stats.imagesFound += imagesCount;

                if (linksCount > 0 || imagesCount > 0) {
                    logStatus('Post #' + result.post_id + ': Found ' + linksCount + ' broken links, ' + imagesCount + ' broken images.');
                }
            });
            updateStatsUI();
        }
    }

    function updateStatsUI() {
        $('#stat-posts-scanned').text(stats.postsScanned);
        $('#stat-links-found').text(stats.linksFound);
        $('#stat-images-found').text(stats.imagesFound);
        $('#stat-items-removed').text(stats.itemsRemoved);
    }

    function logStatus(msg) {
        var time = new Date().toLocaleTimeString();
        $('#current-status-log').prepend('<p>[' + time + '] ' + msg + '</p>');
    }

    function toggleButtons(scanning) {
        if (scanning) {
            $('#start-scan').prop('disabled', true);
            $('#resume-scan').prop('disabled', true);
            $('#reset-scan').prop('disabled', true);
            $('#stop-scan').prop('disabled', false);
            $('#delete-pending').hide();
        } else {
            $('#start-scan').prop('disabled', false);
            $('#resume-scan').prop('disabled', false);
            $('#reset-scan').prop('disabled', false);
            $('#stop-scan').prop('disabled', true);
        }
    }

    function finishScan() {
        isScanning = false;
        $('.smart-cleaner-progress-bar').css('width', '100%');
        $('#progress-text').text('Scan Complete!');
        toggleButtons(false);
        logStatus('Process finished.');

        if ($('#dry_run').is(':checked')) {
            $('#delete-pending').show();
        }

        resetProgress();
    }

    // Report Tab Functions
    function loadReport() {
        $('#report-loading').show();
        $('#report-content').hide();
        $('#report-empty').hide();

        $.ajax({
            url: smartCleaner.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_cleaner_get_pending',
                nonce: smartCleaner.nonce
            },
            success: function (response) {
                $('#report-loading').hide();

                if (response.success && response.data.length > 0) {
                    populateReport(response.data);
                    $('#report-content').show();
                } else {
                    $('#report-empty').show();
                }
            },
            error: function () {
                $('#report-loading').hide();
                $('#report-empty').show();
            }
        });
    }

    function populateReport(data) {
        var tbody = $('#report-tbody');
        tbody.empty();

        data.forEach(function (item) {
            var linksCount = item.links.length;
            var imagesCount = item.images.length;

            var linksHtml = '';
            if (linksCount > 0) {
                linksHtml = '<details><summary>' + linksCount + ' broken link(s)</summary><ul>';
                item.links.forEach(function (link) {
                    linksHtml += '<li><code>' + link.url + '</code> (' + link.status + ')</li>';
                });
                linksHtml += '</ul></details>';
            } else {
                linksHtml = '0';
            }

            var imagesHtml = '';
            if (imagesCount > 0) {
                imagesHtml = '<details><summary>' + imagesCount + ' broken image(s)</summary><ul>';
                item.images.forEach(function (img) {
                    imagesHtml += '<li><code>' + img.url + '</code> (' + img.status + ')</li>';
                });
                imagesHtml += '</ul></details>';
            } else {
                imagesHtml = '0';
            }

            var row = '<tr>' +
                '<td><input type="checkbox" class="post-checkbox" value="' + item.post_id + '"></td>' +
                '<td><a href="' + item.edit_link + '" target="_blank">' + item.title + '</a></td>' +
                '<td>' + linksHtml + '</td>' +
                '<td>' + imagesHtml + '</td>' +
                '<td><a href="' + item.view_link + '" target="_blank" class="button button-small">View Post</a></td>' +
                '</tr>';

            tbody.append(row);
        });
    }

    // Select/Deselect all
    $('#select-all-checkbox').on('change', function () {
        $('.post-checkbox').prop('checked', $(this).is(':checked'));
    });

    $('#select-all-posts').on('click', function () {
        $('.post-checkbox').prop('checked', true);
        $('#select-all-checkbox').prop('checked', true);
    });

    $('#deselect-all-posts').on('click', function () {
        $('.post-checkbox').prop('checked', false);
        $('#select-all-checkbox').prop('checked', false);
    });

    // Delete selected
    $('#delete-selected').on('click', function () {
        var selectedIds = [];
        $('.post-checkbox:checked').each(function () {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert('Please select at least one post.');
            return;
        }

        if (!confirm('Are you sure you want to delete broken items from ' + selectedIds.length + ' post(s)? This cannot be undone.')) {
            return;
        }

        $.ajax({
            url: smartCleaner.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_cleaner_delete_selected',
                nonce: smartCleaner.nonce,
                post_ids: selectedIds
            },
            success: function (response) {
                if (response.success) {
                    alert('Successfully deleted ' + response.data.count + ' items from ' + selectedIds.length + ' post(s).');
                    loadReport();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function () {
                alert('Error deleting items.');
            }
        });
    });
});
