# Smart Link & Image Cleaner

A powerful WordPress plugin to automatically scan, detect, and clean broken links and images from your posts with intelligent batch processing.

![Version](https://img.shields.io/badge/version-1.0.5-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-5.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/php-7.0%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)

## 🌟 Features

### Core Functionality
- **Smart Scanning**: Uses DOMDocument for accurate HTML parsing and link/image detection
- **HTTP Status Checking**: Verifies link validity using WordPress HTTP API (HEAD/GET requests)
- **Batch Processing**: Processes posts one at a time with AJAX to prevent server timeouts
- **Partial Post Processing**: Handles large posts by splitting link checking into chunks (15-second time limit per batch)
- **Dry Run Mode**: Simulate the cleaning process without making any changes

### Advanced Features
- **Resume Capability**: Automatically saves progress and allows resuming interrupted scans
- **Detailed Reporting**: View all posts with broken items in a comprehensive table
- **Selective Deletion**: Choose which posts to clean using checkboxes
- **Real-time Progress**: Live progress bar and status log
- **Statistics Dashboard**: Track scanned posts, broken links/images found, and items removed

### Safety Features
- **Dry Run by Default**: Always starts in simulation mode
- **Progress Persistence**: Saves scan state to WordPress options table
- **Cloudflare Timeout Protection**: Optimized for sites behind Cloudflare with timeout handling
- **Smart Link Removal**: Removes broken links but preserves anchor text
- **Complete Image Removal**: Removes broken `<img>` tags entirely

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- MySQL 5.6 or higher
- DOMDocument PHP extension (usually enabled by default)
- mbstring PHP extension

## 🚀 Installation

### Method 1: Upload via WordPress Admin
1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate"

### Method 2: Manual Installation
1. Download and extract the plugin files
2. Upload the `clean` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress

### Method 3: Git Clone
```bash
cd wp-content/plugins/
git clone https://github.com/yourusername/smart-link-cleaner.git clean
```

## 📖 Usage

### Basic Workflow

1. **Navigate to Plugin**
   - Go to WordPress Admin → Smart Cleaner

2. **Configure Settings**
   - Select Post Type (Post, Page, or All)
   - Enable "Dry Run" for testing (recommended first time)

3. **Start Scanning**
   - Click "Start New Scan"
   - Monitor progress in real-time
   - View statistics as they update

4. **Review Results**
   - Switch to "Report" tab
   - See detailed list of posts with broken items
   - Expand details to view specific broken URLs

5. **Clean Posts**
   - Select posts to clean using checkboxes
   - Click "Delete Selected" to remove broken items
   - Or use "Delete Found Items" to clean all at once

### Resume Interrupted Scans

If a scan is interrupted:
1. Refresh the page
2. Click "Resume Scan" button
3. The scan continues from where it stopped

### Reset Progress

To start fresh:
1. Click "Reset Progress"
2. Confirm the action
3. Start a new scan

## 🏗️ Architecture

### File Structure
```
clean/
├── clean-links.php           # Main plugin file
├── admin/
│   └── admin-page.php        # Admin UI template
├── includes/
│   ├── class-scanner.php     # Scanning logic
│   └── class-cleaner.php     # Cleaning logic
├── assets/
│   ├── css/
│   │   └── style.css         # Admin styles
│   └── js/
│       └── script.js         # Admin JavaScript
└── README.md                 # This file
```

### Key Components

#### Scanner Class (`class-scanner.php`)
- Parses post content using DOMDocument
- Checks URL validity (filters out anchors, mailto, tel links)
- Supports partial processing with time limits
- Returns broken links and images with status codes

#### Cleaner Class (`class-cleaner.php`)
- Removes broken links while preserving text
- Removes broken images completely
- Uses DOMDocument for safe HTML manipulation
- Updates post content via `wp_update_post()`

#### AJAX Handlers
- `smart_cleaner_scan`: Process batch scanning
- `smart_cleaner_get_pending`: Fetch report data
- `smart_cleaner_delete_pending`: Delete all found items
- `smart_cleaner_delete_selected`: Delete selected posts' items
- `smart_cleaner_reset`: Reset progress

## ⚙️ Configuration

### Adjustable Parameters

In `includes/class-scanner.php`:
```php
private $time_limit = 15; // Max execution time per batch (seconds)
```

In `clean-links.php`:
```php
$batch_size = 1; // Posts processed per AJAX request
```

In `includes/class-scanner.php`:
```php
'timeout' => 2, // HTTP request timeout (seconds)
```

## 🔧 Troubleshooting

### Scan Stops Unexpectedly
- Check browser console for JavaScript errors
- Verify server PHP error logs
- Increase PHP `max_execution_time` if needed
- Use "Resume Scan" to continue

### Cloudflare 524 Timeout Errors
- Plugin is optimized to prevent this
- Reduce `$time_limit` in scanner class if still occurring
- Ensure Cloudflare timeout settings allow at least 30 seconds

### Links Not Being Removed
- Verify "Dry Run" is disabled for actual deletion
- Check that links match exactly (including http/https)
- Review browser console for AJAX errors

### Entire Post Deleted (Critical Bug - Fixed in v1.0.5)
- **Update immediately to v1.0.5 or higher**
- Restore from backup if affected
- Always test with "Dry Run" first

## 🛡️ Security

- Nonce verification on all AJAX requests
- Capability checks (`manage_options`)
- Sanitized user inputs
- WordPress coding standards compliant
- No external API calls (uses WordPress HTTP API)

## 🔄 Changelog

### Version 1.0.5 (2025-12-06) - CRITICAL FIX
- **FIXED**: Critical bug where entire posts were deleted instead of just broken links
- Improved HTML content preservation
- Enhanced DOMDocument save method

### Version 1.0.4 (2025-12-06)
- Fixed JavaScript loading issues
- Cleaned up code formatting
- Improved browser compatibility

### Version 1.0.3 (2025-12-05)
- Added detailed Report tab
- Implemented selective deletion with checkboxes
- Added Select All/Deselect All functionality

### Version 1.0.2 (2025-12-03)
- Fixed Cloudflare timeout issues
- Implemented partial post processing
- Added pending deletion queue
- Improved error handling

### Version 1.0.1 (2025-12-03)
- Added Resume/Reset functionality
- Improved error logging
- Enhanced UI controls

### Version 1.0.0 (2025-12-03)
- Initial release
- Basic scanning and cleaning functionality
- Dry run mode
- Progress tracking

## 📝 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Setup
1. Clone the repository
2. Install WordPress locally
3. Symlink or copy plugin to `wp-content/plugins/`
4. Activate and test

### Coding Standards
- Follow WordPress Coding Standards
- Use meaningful variable and function names
- Comment complex logic
- Test thoroughly before submitting PR

## 📧 Support

For bug reports and feature requests, please use the [GitHub Issues](https://github.com/yourusername/smart-link-cleaner/issues) page.

## ⚠️ Important Notes

1. **Always backup your database** before running the cleaner in non-dry-run mode
2. **Test with Dry Run first** to see what will be removed
3. **Review the Report tab** before bulk deletion
4. **Use selective deletion** for better control
5. **Keep the plugin updated** for bug fixes and improvements

## 🙏 Credits

Developed with ❤️ by Ibrahim Noshy Soliman

## 📊 Stats

- **Active Installations**: TBD
- **WordPress Version Tested**: 6.4
- **PHP Version Tested**: 8.1
- **Last Updated**: 2025-12-06

---

**Made with ❤️ for the WordPress community**
