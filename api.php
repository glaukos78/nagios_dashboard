<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$hostgroups_map = [];
$all_groups = [];

// Parse objects.cache for Hostgroups
if (file_exists(NAGIOS_OBJECTS_CACHE) && $handle = fopen(NAGIOS_OBJECTS_CACHE, "r")) {
    $in_hg = false; $hg_name = '';
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line === 'define hostgroup {') { $in_hg = true; $hg_name = ''; }
        elseif ($in_hg && $line === '}') { $in_hg = false; }
        elseif ($in_hg) {
            if (strpos($line, 'hostgroup_name') === 0) {
                $parts = preg_split('/\s+/', $line, 2); $hg_name = trim($parts[1] ?? '');
                if ($hg_name !== '') $all_groups[$hg_name] = $hg_name;
            } elseif (strpos($line, 'alias') === 0) {
                $parts = preg_split('/\s+/', $line, 2); $hg_alias = trim($parts[1] ?? '');
                if ($hg_name !== '' && $hg_alias !== '') $all_groups[$hg_name] = $hg_alias;
            } elseif (strpos($line, 'members') === 0) {
                $parts = preg_split('/\s+/', $line, 2); $members = explode(',', trim($parts[1] ?? ''));
                foreach ($members as $m) { $m = trim($m); if ($m !== '' && $hg_name !== '') $hostgroups_map[$m][] = $hg_name; }
            }
        }
    }
    fclose($handle);
}

$file_path = NAGIOS_STATUS_FILE;
$items = ['CRITICAL' => [], 'WARNING' => [], 'DISABLED' => []];
$comments_map = [];
$all_statuses = [];

// Parse status.dat for Alerts and Comments
if (file_exists($file_path) && $handle = fopen($file_path, "r")) {
    $current_block = null;
    $current_data = [];

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;

        if (strpos($line, '{') !== false) {
            $parts = explode(' ', $line); $current_block = $parts[0]; $current_data = [];
        } elseif ($line === '}') {
            if ($current_block === 'hoststatus' || $current_block === 'servicestatus') {
                $all_statuses[] = ['type' => $current_block, 'data' => $current_data];
            } elseif ($current_block === 'hostcomment' || $current_block === 'servicecomment') {
                $h = $current_data['host_name'] ?? '';
                $s = ($current_block === 'servicecomment') ? ($current_data['service_description'] ?? '') : 'HOST PING';
                $key = $h . '||' . $s;

                if (!isset($comments_map[$key])) $comments_map[$key] = [];
                $comments_map[$key][] = [
                    'id' => $current_data['comment_id'] ?? '',
                    'author' => $current_data['author'] ?? 'Unknown',
                    'text' => htmlspecialchars($current_data['comment_data'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'time' => isset($current_data['entry_time']) ? date("Y-m-d H:i", (int)$current_data['entry_time']) : '',
                    'type' => $current_block
                ];
            }
            $current_block = null;
        } elseif ($current_block !== null) {
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) $current_data[trim($parts[0])] = trim($parts[1]);
        }
    }
    fclose($handle);

    // Merge Alerts with their Comments
    foreach ($all_statuses as $st) {
        $b_type = $st['type'];
        $d = $st['data'];
        $is_service = ($b_type === 'servicestatus');

        $active_checks = isset($d['active_checks_enabled']) ? (int)$d['active_checks_enabled'] : 1;
        $state = isset($d['current_state']) ? (int)$d['current_state'] : 0;

        // Το Nagios χρησιμοποιεί το 'problem_has_been_acknowledged'
        $ack = isset($d['problem_has_been_acknowledged']) ? (int)$d['problem_has_been_acknowledged'] : 0;

        $h_name = $d['host_name'] ?? 'UNKNOWN';
        $s_name = $is_service ? ($d['service_description'] ?? 'UNKNOWN') : 'HOST PING';

        $key = $h_name . '||' . $s_name;

        $item = [
            'host' => $h_name,
            'service' => $s_name,
            'plugin' => isset($d['plugin_output']) ? htmlspecialchars($d['plugin_output'], ENT_QUOTES, 'UTF-8') : '',
            'last_check' => isset($d['last_check']) ? date("Y-m-d H:i:s", (int)$d['last_check']) : 'N/A',
            'ack' => $ack,
            'hostgroups' => isset($hostgroups_map[$h_name]) ? $hostgroups_map[$h_name] : [],
            'comments' => isset($comments_map[$key]) ? $comments_map[$key] : []
        ];

        if ($active_checks === 0) $items['DISABLED'][] = $item;
        elseif ($state === 2) $items['CRITICAL'][] = $item;
        elseif ($state === 1) $items['WARNING'][] = $item;
    }
} else {
    echo json_encode(['error' => "Error: File not found or unreadable: " . $file_path]);
    exit;
}

// Προσθήκη του CONFIG στο JSON Response
echo json_encode([
    'DATA' => $items,
    'HOSTGROUPS' => $all_groups,
    'CONFIG' => [
        'VERSION' => defined('DASHBOARD_VERSION') ? DASHBOARD_VERSION : 'v1.0',
        'CREATOR' => defined('DASHBOARD_CREATOR') ? DASHBOARD_CREATOR : 'Unknown'
    ]
]);
?>
