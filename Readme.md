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

3. Set Linux Permissions (CRITICAL)

Your web server user (usually www-data on Debian/Ubuntu or apache on RHEL/CentOS) MUST have read access to the .dat/.cache files and write access to the nagios.cmd pipe.

Run the following commands as root:
Bash

# Add web user to the nagcmd group (Use 'apache' instead of 'www-data' for CentOS)
sudo usermod -a -G nagcmd www-data

# Ensure correct permissions on the Nagios rw folder
sudo chown nagios:nagcmd /usr/local/nagios/var/rw
EOFlt for modern infrastructure teams.d to be served over HTTPS (or localhost) to grant Notification API permissions. Ensure you have clicked "Allow" when the browser prompts you. Run sudo setsebool -P h
root@nagios:/var/www/html/nagios_ui# vim README.md
 1 README.md                                                                                                                                                                                             X
 ## ⚙️ Prerequisites

 * A running instance of **Nagios Core** (v4.x recommended).
 * A web server (**Apache** or **Nginx**).
 * **PHP** (7.4 or 8.x).

 ## 🛠️  Installation & Setup

 ### 1. Copy Files
 Place the 4 project files (`index.html`, `api.php`, `cmd.php`, `config.php`) into a dedicated directory in your web root, for example: `/var/www/html/noc/`

 ### 2. Configure Paths
 Open `config.php` and verify the paths to your Nagios installation:
 ```php
 define('NAGIOS_STATUS_FILE', '/usr/local/nagios/var/status.dat');
 define('NAGIOS_CMD_FILE', '/usr/local/nagios/var/rw/nagios.cmd');
 define('NAGIOS_OBJECTS_CACHE', '/usr/local/nagios/var/objects.cache');

 3. Set Linux Permissions (CRITICAL)

 Your web server user (usually www-data on Debian/Ubuntu or apache on RHEL/CentOS) MUST have read access to the .dat/.cache files and write access to the nagios.cmd pipe.

 Run the following commands as root:
 Bash

 # Add web user to the nagcmd group (Use 'apache' instead of 'www-data' for CentOS)
 sudo usermod -a -G nagcmd www-data

 # Ensure correct permissions on the Nagios rw folder
 sudo chown nagios:nagcmd /usr/local/nagios/var/rw
 sudo chmod u+rwx,g+rwx,o-rwx /usr/local/nagios/var/rw
 sudo chmod u+rw,g+rw,o-rw /usr/local/nagios/var/rw/nagios.cmd

 # Ensure read permissions on status files
 sudo chmod 644 /usr/local/nagios/var/status.dat
 sudo chmod 644 /usr/local/nagios/var/objects.cache

 # Restart the web server
 sudo systemctl restart apache2  # (or httpd)

 🔒 Security Recommendations

 This dashboard interacts directly with the Nagios command pipe. It is highly recommended to secure the directory using Basic Authentication (htpasswd) or restrict access via IP filtering in your Apache/ Nginx configuration.
 🐛 Troubleshooting

     "Write permission denied for nagios.cmd" toast error:
     The web server user does not have permission to write to the command pipe. Double-check step 3 of the installation. If you are using CentOS/RHEL, SELinux might be blocking it. Run sudo setsebool -P  httpd_sys_script_anon_write 1.

     No Hostgroups in the dropdown:
     Ensure the path to objects.cache is correct in config.php and the web user has read permissions for it.

     Audio/Desktop Notifications not working:
     Your browser requires the dashboard to be served over HTTPS (or localhost) to grant Notification API permissions. Ensure you have clicked "Allow" when the browser prompts you.

 Built for modern infrastructure teams.
