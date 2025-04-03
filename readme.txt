=== WP File Integrity Checker ===
Contributors: vapvarun, wbcomdesigns
Tags: security, file integrity, malware detection, file monitoring, security scanner
Requires at least: 5.0
Tested up to: 6.7.2
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor and detect unauthorized file changes in your WordPress installation to enhance security and prevent malware infections.

== Description ==

WP File Integrity Checker is a comprehensive security tool that continuously monitors your WordPress files for any unauthorized changes, helping you detect potential security breaches or malware injections quickly.

### Key Features

- **Automated File Scanning**: Schedule regular scans of your WordPress installation
- **Real-time Change Detection**: Identify modified, added, or deleted files
- **Email Notifications**: Receive alerts when suspicious changes are detected
- **Detailed Reporting**: View comprehensive logs of all file changes
- **Customizable Settings**: Configure scan frequency and notification preferences
- **User-friendly Dashboard**: Easy-to-use interface for managing file integrity

### How It Works

WP File Integrity Checker creates SHA-256 hashes of all files in your WordPress installation and stores them securely in your database. During scheduled scans, it compares the current state of your files with the stored hashes to detect any changes. When modifications are found, the plugin categorizes them as modified, added, or deleted files and alerts you accordingly.

### Why File Integrity Monitoring Matters

File integrity monitoring is a critical security practice that helps:

- Detect unauthorized changes to core WordPress files
- Identify potential malware injections
- Discover backdoors installed by hackers
- Ensure theme and plugin files haven't been compromised
- Maintain compliance with security best practices

== Installation ==

1. Upload the `wp-file-integrity-checker` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Tools > File Integrity' to configure settings and run your first scan

== Frequently Asked Questions ==

= How often should I run integrity scans? =

We recommend running scans at least weekly for most websites. High-traffic or e-commerce sites may benefit from daily scans. You can configure the scan frequency in the plugin settings.

= Will this plugin slow down my website? =

No. The scanning process runs in the background using WordPress cron jobs and doesn't affect your website's front-end performance.

= What happens if changes are detected? =

When the plugin detects file changes, it logs them in the dashboard and sends an email notification (if enabled) with details about the modified, added, or deleted files.

= Can I exclude certain files or directories from scanning? =

Yes, you can configure exclusions in the plugin settings to skip specific files or directories that change frequently as part of normal operations.

= Is this plugin compatible with multisite installations? =

Yes, the plugin works with WordPress multisite installations.

= What should I do if suspicious changes are detected? =

If you notice unexpected file changes, you should:
1. Investigate the modified files
2. Compare with original versions if possible
3. Check for recent legitimate updates that might explain the changes
4. If suspicious, consider restoring from a clean backup
5. Update passwords and security keys

== Screenshots ==

1. Dashboard overview showing scan statistics
2. Detailed file change logs
3. Plugin settings page
4. Email notification example

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP File Integrity Checker.

---
Answer from Perplexity: pplx.ai/share
