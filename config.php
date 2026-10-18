<?php
// ==========================================
// NAGIOS DASHBOARD - CONFIGURATION
// ==========================================

// Path to Nagios status.dat
define('NAGIOS_STATUS_FILE', '/usr/local/nagios/var/status.dat');

// Path to Nagios command pipe (must be writable by www-data/apache)
define('NAGIOS_CMD_FILE', '/usr/local/nagios/var/rw/nagios.cmd');

// Path to Nagios objects.cache (for Hostgroups)
define('NAGIOS_OBJECTS_CACHE', '/usr/local/nagios/var/objects.cache');

// The author name that will appear in Nagios logs/comments
define('DASHBOARD_USER', 'NOC_Dashboard_UI');

// Optional command endpoint protection. Set NOC_API_TOKEN in the PHP server environment.
define('DASHBOARD_REQUIRE_API_TOKEN', false);
define('DASHBOARD_API_TOKEN', getenv('NOC_API_TOKEN') ?: '');

// --- FOOTER SETTINGS ---
// Ορίστε την τρέχουσα έκδοση και το όνομα του δημιουργού / ομάδας
define('DASHBOARD_VERSION', 'v2.1.0 Enterprise');
define('DASHBOARD_CREATOR', 'NOC Team & IT Dept.');
?>
