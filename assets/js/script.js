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

    $('#clear-all').on('click', function () {
        if (confirm('Are you sure you want to clear all data (report + progress)? This will give you a completely fresh start. No content will be deleted from posts.')) {
            clearAll();
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

    function clearAll() {
        $.ajax({
            url: smartCleaner.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_cleaner_clear_report',
                nonce: smartCleaner.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message || 'All data cleared successfully. Starting fresh!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function () {
                alert('Error clearing data.');
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
        console.log('Update Progress Data:', data);

        $('.smart-cleaner-progress-bar').css('width', data.percentage + '%');
        $('#progress-text').text(data.percentage + '% Complete');

        if (data.results && data.results.length > 0) {
            data.results.forEach(function (result) {
                console.log('Processing result:', result);

                if (!result.partial) {
                    stats.postsScanned++;
                }

                var linksCount = result.scan.links ? result.scan.links.length : 0;
                var imagesCount = result.scan.images ? result.scan.images.length : 0;

                console.log('Links found:', linksCount, 'Images found:', imagesCount);

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

    // Delete selected - with batch processing
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

        // Start batch deletion
        startBatchDeletion(selectedIds);
    });

    function startBatchDeletion(postIds) {
        // Show progress bar
        $('#deletion-progress').show();
        $('#deletion-progress-bar').css('width', '0%');
        $('#deletion-progress-text').text('Starting deletion...');
        $('#deletion-status-log').html('');

        // Disable buttons during deletion
        $('#delete-selected, #select-all-posts, #deselect-all-posts, #clear-report').prop('disabled', true);

        var totalPosts = postIds.length;
        var currentIndex = 0;
        var totalItemsDeleted = 0;

        function deleteNextPost() {
            if (currentIndex >= totalPosts) {
                // All done
                finishBatchDeletion(totalItemsDeleted);
                return;
            }

            var postId = postIds[currentIndex];
            var progress = ((currentIndex / totalPosts) * 100).toFixed(1);

            $('#deletion-progress-bar').css('width', progress + '%');
            $('#deletion-progress-text').text('Processing post ' + (currentIndex + 1) + ' of ' + totalPosts + ' (' + progress + '%)');

            $.ajax({
                url: smartCleaner.ajax_url,
                type: 'POST',
                data: {
                    action: 'smart_cleaner_delete_single',
                    nonce: smartCleaner.nonce,
                    post_id: postId
                },
                success: function (response) {
                    if (response.success) {
                        var count = response.data.count || 0;
                        totalItemsDeleted += count;

                        var time = new Date().toLocaleTimeString();
                        $('#deletion-status-log').prepend('<p>[' + time + '] Post #' + postId + ': Deleted ' + count + ' item(s)</p>');
                    } else {
                        var time = new Date().toLocaleTimeString();
                        $('#deletion-status-log').prepend('<p style="color:red;">[' + time + '] Post #' + postId + ': Error - ' + response.data + '</p>');
                    }

                    currentIndex++;
                    // Continue with next post
                    setTimeout(deleteNextPost, 100);
                },
                error: function () {
                    var time = new Date().toLocaleTimeString();
                    $('#deletion-status-log').prepend('<p style="color:red;">[' + time + '] Post #' + postId + ': AJAX Error</p>');

                    currentIndex++;
                    // Continue with next post even if there was an error
                    setTimeout(deleteNextPost, 100);
                }
            });
        }

        // Start the batch process
        deleteNextPost();
    }

    function finishBatchDeletion(totalDeleted) {
        $('#deletion-progress-bar').css('width', '100%');
        $('#deletion-progress-text').text('Deletion complete! Total items deleted: ' + totalDeleted);

        // Re-enable buttons
        $('#delete-selected, #select-all-posts, #deselect-all-posts, #clear-report').prop('disabled', false);

        alert('Successfully deleted ' + totalDeleted + ' broken items!');

        // Reload report to show updated data
        setTimeout(function () {
            $('#deletion-progress').hide();
            loadReport();
        }, 2000);
    }

    // Clear report button - using event delegation since button is in a tab
    $(document).on('click', '#clear-report', function () {
        console.log('Clear Report button clicked');

        if (!confirm('Are you sure you want to clear the report and progress? This will reset everything for a fresh scan. No content will be deleted from posts.')) {
            console.log('User cancelled clear report');
            return;
        }

        console.log('Sending AJAX request to clear report...');

        $.ajax({
            url: smartCleaner.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_cleaner_clear_report',
                nonce: smartCleaner.nonce
            },
            success: function (response) {
                console.log('Clear report response:', response);

                if (response.success) {
                    alert(response.data.message || 'Report and progress cleared successfully.');
                    // Reload page to reset UI state
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error('Clear report error:', error);
                console.error('Response:', xhr.responseText);
                alert('Error clearing report: ' + error);
            }
        });
    });

    // Save settings button
    $('#save-settings').on('click', function () {
        var whitelist = $('#whitelist_domains').val();

        $.ajax({
            url: smartCleaner.ajax_url,
            type: 'POST',
            data: {
                action: 'smart_cleaner_save_settings',
                nonce: smartCleaner.nonce,
                whitelist: whitelist
            },
            success: function (response) {
                if (response.success) {
                    $('#settings-saved').fadeIn().delay(2000).fadeOut();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function () {
                alert('Error saving settings.');
            }
        });
    });
});
