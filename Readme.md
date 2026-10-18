# Nagios Operations Center (NOC) Dashboard

An enterprise-grade, lightning-fast, and modern web interface for **Nagios Core**. Built specifically for NOC teams and System Administrators, this dashboard provides real-time visibility, bulk operations, and advanced filtering without the clutter of the legacy Nagios UI.

## 🚀 Key Features

* **No Database Required:** Directly and efficiently parses `status.dat` and `objects.cache` from the filesystem.
* **Real-Time Polling & Optimistic UI:** Auto-refreshes every 10 seconds. Actions like "Acknowledge" update the UI instantly without waiting for Nagios to write to the disk.
* **Smart Filtering:** Filter by Hostgroup (parsed dynamically) or perform instantaneous text-searches across hosts, services, and plugin outputs. Toggle "Hide Warnings" and "Hide ACKed".
* **Advanced Alert Management:**
  * Single & Bulk Acknowledgment
  * Remove Acknowledgment
  * Schedule Service/Host Downtime (30m, 1h, 2h, 4h)
  * Force Re-check
  * Enable Notifications & Send Custom Email/SMS Alerts
* **Comments Management:** View, add, and delete Nagios comments directly from the side panel. Perfect for shift handovers.
* **Desktop & Audio Notifications:** Native browser pop-ups and audible alerts for new CRITICAL events.
* **Export to CSV:** One-click generation of shift reports.
* **Dark / Light Mode:** Easy on the eyes for night-shift operators.

## 📂 File Structure

The project consists of 4 lightweight files:
1. `config.php` - Global paths, application version, and creator settings.
2. `api.php` - The high-performance JSON parser for Nagios states and hostgroups.
3. `cmd.php` - The backend engine handling Nagios External Commands.
4. `index.html` - The modern, single-page application frontend.

## ⚙️ Prerequisites

* A running instance of **Nagios Core** (v4.x recommended).
* A web server (**Apache** or **Nginx**).
* **PHP** (7.4 or 8.x).

## 🛠️ Installation & Setup

### 1. Copy Files
Place the 4 project files (`index.html`, `api.php`, `cmd.php`, `config.php`) into a dedicated directory in your web root, for example: `/var/www/html/noc/`

### 2. Configure Paths
Open `config.php` and verify the paths to your Nagios installation:
```php
define('NAGIOS_STATUS_FILE', '/usr/local/nagios/var/status.dat');
define('NAGIOS_CMD_FILE', '/usr/local/nagios/var/rw/nagios.cmd');
define('NAGIOS_OBJECTS_CACHE', '/usr/local/nagios/var/objects.cache');
```

### 3. Set Permissions

Enable external commands in Nagios (`check_external_commands=1`). The PHP worker
user (usually `www-data` or `apache`) needs read access to `status.dat` and
`objects.cache`, directory traversal access to their parent directories, and write
access to the existing `nagios.cmd` pipe. Do not create a regular file in its place.

For a typical source installation using the `nagcmd` command group:

```bash
sudo usermod -a -G nagcmd www-data
sudo chown nagios:nagcmd /usr/local/nagios/var/rw
sudo chmod 2770 /usr/local/nagios/var/rw
sudo chown nagios:nagcmd /usr/local/nagios/var/rw/nagios.cmd
sudo chmod 660 /usr/local/nagios/var/rw/nagios.cmd
```

Grant status/cache read access through an appropriate group or ACL rather than
making sensitive monitoring data world-readable. Configure Nagios permissions so
access survives file/pipe recreation. Restart PHP-FPM or Apache as appropriate
after group membership changes.

### 4. Open the Dashboard

Visit your dashboard directory (for example, `https://monitor.example/noc/`).
No database, framework, or additional PHP extensions are required.

## 🔒 Security Recommendations

- Restrict the **entire directory**, including `api.php`, using web-server
  authentication and/or trusted-network access rules. The read API exposes
  monitoring data; command access can change Nagios state. Do not expose an
  unprotected installation to the public internet.
- Use HTTPS. Keep application files and configuration read-only to the PHP user;
  grant only the necessary Nagios file permissions, never `chmod 777`.
- Optional mutation protection is **off by default** for backward compatibility.
  To enable it, set `DASHBOARD_REQUIRE_API_TOKEN` to `true` in `config.php` and
  provide a strong random `NOC_API_TOKEN` environment variable to PHP-FPM/Apache.
  Never commit the secret or put it in frontend files, URLs, or access logs.
  An empty configured token fails closed with HTTP 500 when enforcement is on.
- With enforcement enabled, the dashboard prompts for the token on the first
  action and keeps it only in page memory until reload/close. API clients must
  send `X-API-Token` with every command request. Missing/incorrect tokens return
  HTTP 401. Read-only polling is unchanged; this is not a replacement for
  protecting the read API at the web server.
- Commands require POST with `Content-Type: application/json`. Requests are
  limited to 1 MiB and 1,000 targets. Delimiters, line breaks, and controls are
  neutralized; fields are limited to 255 characters (author: 64, message: 1,024).
  Downtime accepts integer minutes from 1 to 525,600 (default: 60).
- There is no session-cookie authentication today. If it is introduced, add CSRF
  validation at the marked extension point in `cmd.php` before allowing writes.

## 🐛 Troubleshooting

- **Write permission denied / failed command write:** Verify the configured pipe
  path, that Nagios is running, and PHP worker permissions. Check PHP/web-server
  error logs for failed or partial writes. On SELinux systems, inspect audit
  denials and apply a narrowly scoped policy rather than disabling enforcement.
  A success response means the command was written, not that Nagios has processed it.
- **Unauthorized:** Check the token and that PHP receives `NOC_API_TOKEN` (PHP-FPM
  may clear environment variables) and the proxy forwards `X-API-Token`. Reload
  the page to clear a previously entered token.
- **Missing alerts or hostgroups:** Verify paths and read permissions for
  `status.dat` and `objects.cache`. PHP errors are logged, not displayed in JSON.
- **Actions appear delayed:** Nagios processes commands asynchronously; the UI
  waits approximately 12 seconds before refreshing after an action.
- **Audio/desktop notifications:** Use HTTPS (or localhost), allow notifications
  in the browser, and enable Audio in the dashboard.
