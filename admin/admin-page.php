<div class="wrap">
    <h1><?php _e( 'Smart Link & Image Cleaner', 'smart-cleaner' ); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="#scanner" class="nav-tab nav-tab-active"><?php _e( 'Scanner', 'smart-cleaner' ); ?></a>
        <a href="#report" class="nav-tab"><?php _e( 'Report', 'smart-cleaner' ); ?></a>
        <a href="#settings" class="nav-tab"><?php _e( 'Settings', 'smart-cleaner' ); ?></a>
    </h2>

    <!-- Scanner Tab -->
    <div id="scanner-tab" class="tab-content">
        <div class="smart-cleaner-dashboard">
            <div class="card">
                <h2><?php _e( 'Status & Actions', 'smart-cleaner' ); ?></h2>
                <p><?php _e( 'Scan your posts for broken links and images. Use "Dry Run" to simulate the process without deleting anything.', 'smart-cleaner' ); ?></p>
                
                <form id="smart-cleaner-form">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e( 'Settings', 'smart-cleaner' ); ?></span></legend>
                        <label for="dry_run">
                            <input type="checkbox" name="dry_run" id="dry_run" value="1" checked>
                            <?php _e( 'Dry Run (Simulation Mode)', 'smart-cleaner' ); ?>
                        </label>
                        <br><br>
                        <label for="post_type">
                            <?php _e( 'Post Type:', 'smart-cleaner' ); ?>
                            <select name="post_type" id="post_type">
                                <option value="post">Post</option>
                                <option value="page">Page</option>
                                <option value="all">All (Post & Page)</option>
                            </select>
                        </label>
                    </fieldset>
                    
                    <br>
                    <div id="action-buttons">
                        <button type="button" id="start-scan" class="button button-primary button-large"><?php _e( 'Start New Scan', 'smart-cleaner' ); ?></button>
                        <button type="button" id="resume-scan" class="button button-primary button-large" style="display:none;"><?php _e( 'Resume Scan', 'smart-cleaner' ); ?></button>
                        <button type="button" id="stop-scan" class="button button-secondary button-large" disabled><?php _e( 'Stop', 'smart-cleaner' ); ?></button>
                        <button type="button" id="delete-pending" class="button button-primary button-large" style="background-color: #d63638; border-color: #d63638; display:none;"><?php _e( 'Delete Found Items', 'smart-cleaner' ); ?></button>
                        <br><br>
                        <button type="button" id="reset-scan" class="button button-link button-large" style="color: #b32d2e; display:none;"><?php _e( 'Reset Progress', 'smart-cleaner' ); ?></button>
                        <button type="button" id="clear-all" class="button button-link button-large" style="color: #d63638;"><?php _e( 'Clear All (Fresh Start)', 'smart-cleaner' ); ?></button>
                    </div>
                </form>
            </div>

            <div class="card" id="progress-card" style="display:none;">
                <h2><?php _e( 'Progress', 'smart-cleaner' ); ?></h2>
                <div class="smart-cleaner-progress-bar-wrapper">
                    <div class="smart-cleaner-progress-bar" style="width: 0%;"></div>
                </div>
                <p id="progress-text"><?php _e( 'Waiting to start...', 'smart-cleaner' ); ?></p>
                <div id="current-status-log" style="max-height: 150px; overflow-y: auto; background: #f0f0f1; padding: 10px; border: 1px solid #c3c4c7;"></div>
            </div>

            <div class="card">
                <h2><?php _e( 'Statistics', 'smart-cleaner' ); ?></h2>
                <table class="widefat fixed" cellspacing="0">
                    <thead>
                        <tr>
                            <th><?php _e( 'Metric', 'smart-cleaner' ); ?></th>
                            <th><?php _e( 'Count', 'smart-cleaner' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php _e( 'Posts Scanned', 'smart-cleaner' ); ?></td>
                            <td id="stat-posts-scanned">0</td>
                        </tr>
                        <tr>
                            <td><?php _e( 'Broken Links Found', 'smart-cleaner' ); ?></td>
                            <td id="stat-links-found">0</td>
                        </tr>
                        <tr>
                            <td><?php _e( 'Broken Images Found', 'smart-cleaner' ); ?></td>
                            <td id="stat-images-found">0</td>
                        </tr>
                        <tr>
                            <td><?php _e( 'Items Removed (Simulated if Dry Run)', 'smart-cleaner' ); ?></td>
                            <td id="stat-items-removed">0</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Report Tab -->
    <div id="report-tab" class="tab-content" style="display:none;">
        <div class="card">
            <h2><?php _e( 'Broken Items Report', 'smart-cleaner' ); ?></h2>
            <p><?php _e( 'Below are all posts with broken links or images found during the last scan. Select the posts you want to clean and click "Delete Selected".', 'smart-cleaner' ); ?></p>
            
            <div id="report-loading" style="text-align: center; padding: 20px;">
                <p><?php _e( 'Loading report...', 'smart-cleaner' ); ?></p>
            </div>
            
            <div id="report-content" style="display:none;">
                <div style="margin-bottom: 15px;">
                    <button type="button" id="select-all-posts" class="button"><?php _e( 'Select All', 'smart-cleaner' ); ?></button>
                    <button type="button" id="deselect-all-posts" class="button"><?php _e( 'Deselect All', 'smart-cleaner' ); ?></button>
                    <button type="button" id="delete-selected" class="button button-primary" style="background-color: #d63638; border-color: #d63638; margin-left: 10px;"><?php _e( 'Delete Selected', 'smart-cleaner' ); ?></button>
                    <button type="button" id="clear-report" class="button button-secondary" style="margin-left: 10px;"><?php _e( 'Clear Report', 'smart-cleaner' ); ?></button>
                </div>
                
                <!-- Deletion Progress Bar -->
                <div id="deletion-progress" style="display:none; margin-bottom: 15px; padding: 15px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px;">
                    <h3 style="margin-top: 0;"><?php _e( 'Deleting Broken Items...', 'smart-cleaner' ); ?></h3>
                    <div class="smart-cleaner-progress-bar-wrapper">
                        <div id="deletion-progress-bar" class="smart-cleaner-progress-bar" style="width: 0%;"></div>
                    </div>
                    <p id="deletion-progress-text"><?php _e( 'Starting...', 'smart-cleaner' ); ?></p>
                    <div id="deletion-status-log" style="max-height: 100px; overflow-y: auto; background: #fff; padding: 10px; border: 1px solid #ddd; margin-top: 10px;"></div>
                </div>
                
                <table class="wp-list-table widefat fixed striped" id="report-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="select-all-checkbox"></th>
                            <th><?php _e( 'Post Title', 'smart-cleaner' ); ?></th>
                            <th><?php _e( 'Broken Links', 'smart-cleaner' ); ?></th>
                            <th><?php _e( 'Broken Images', 'smart-cleaner' ); ?></th>
                            <th><?php _e( 'Actions', 'smart-cleaner' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="report-tbody">
                        <!-- Will be populated by JS -->
                    </tbody>
                </table>
            </div>
            
            <div id="report-empty" style="display:none; text-align: center; padding: 40px;">
                <p><?php _e( 'No broken items found. Run a scan first.', 'smart-cleaner' ); ?></p>
            </div>
        </div>
    </div>

    <!-- Settings Tab -->
    <div id="settings-tab" class="tab-content" style="display:none;">
        <div class="card">
            <h2><?php _e( 'Domain Whitelist', 'smart-cleaner' ); ?></h2>
            <p><?php _e( 'Add domains that should be skipped during scanning. Enter one domain per line (e.g., orsozox.com, example.com). Links from these domains will not be checked.', 'smart-cleaner' ); ?></p>
            
            <form id="settings-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="whitelist_domains"><?php _e( 'Whitelisted Domains', 'smart-cleaner' ); ?></label>
                        </th>
                        <td>
                            <textarea id="whitelist_domains" name="whitelist_domains" rows="10" cols="50" class="large-text code" placeholder="orsozox.com&#10;example.com&#10;another-site.net"><?php echo esc_textarea( get_option( 'smart_cleaner_whitelist', '' ) ); ?></textarea>
                            <p class="description">
                                <?php _e( 'Enter one domain per line. Do not include http:// or https://. Examples: orsozox.com, 4shared.com, mediafire.com', 'smart-cleaner' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" id="save-settings" class="button button-primary"><?php _e( 'Save Settings', 'smart-cleaner' ); ?></button>
                    <span id="settings-saved" style="display:none; color: green; margin-left: 10px;">✓ <?php _e( 'Settings saved!', 'smart-cleaner' ); ?></span>
                </p>
            </form>
        </div>
        
        <div class="card">
            <h2><?php _e( 'Default Protected Domains', 'smart-cleaner' ); ?></h2>
            <p><?php _e( 'These domains are automatically protected from 403 errors (built-in):', 'smart-cleaner' ); ?></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>4shared.com</li>
                <li>mediafire.com</li>
                <li>mega.nz</li>
                <li>drive.google.com</li>
                <li>dropbox.com</li>
                <li>onedrive.live.com</li>
                <li>box.com</li>
                <li>sendspace.com</li>
                <li>zippyshare.com</li>
                <li>uploaded.net</li>
                <li>rapidgator.net</li>
            </ul>
            <p class="description"><?php _e( 'You can add more domains in the whitelist above.', 'smart-cleaner' ); ?></p>
        </div>
    </div>

    <p class="description" style="margin-top: 20px; text-align: right;">Version: <?php echo SMART_CLEANER_VERSION; ?></p>
</div>
