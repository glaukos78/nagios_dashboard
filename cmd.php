<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

function fail(string $message, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function sanitizeNagiosField(string $value, int $maxLength = 255): string {
    $value = str_replace([";", "\r", "\n"], ' ', $value);
    $value = preg_replace('/\p{C}/u', '', $value);
    preg_match('/\A.{0,' . $maxLength . '}/us', trim($value), $match);
    return $match[0];
}

function writeCommands(string $commands): void {
    $written = file_put_contents(NAGIOS_CMD_FILE, $commands, FILE_APPEND | LOCK_EX);
    if ($written === false || $written !== strlen($commands)) {
        fail('Failed to write commands to Nagios.', 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    fail('Only POST requests are supported.', 405);
}

if (DASHBOARD_REQUIRE_API_TOKEN) {
    $token = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if (!is_string(DASHBOARD_API_TOKEN) || DASHBOARD_API_TOKEN === '') {
        fail('API token protection is not configured.', 500);
    }
    if (!is_string($token) || !hash_equals(DASHBOARD_API_TOKEN, $token)) {
        fail('Unauthorized.', 401);
    }
}

// If session-cookie authentication is added, validate a CSRF token here before any writes.
$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($contentType !== 'application/json') {
    fail('Content-Type must be application/json.', 415);
}
$raw = file_get_contents('php://input', false, null, 0, 1048577);
if ($raw === false) fail('Unable to read request body.', 400);
if (strlen($raw) > 1048576) fail('Request body is too large.', 413);
$data = json_decode($raw);
if (json_last_error() !== JSON_ERROR_NONE || !($data instanceof stdClass)) {
    fail('Invalid JSON object.');
}
$data = get_object_vars($data);
$action = $data['action'] ?? null;
$allowed = ['recheck', 'ack', 'remove_ack', 'enable_notif', 'custom_notif', 'add_comment', 'downtime', 'del_comment'];
if (!is_string($action) || !in_array($action, $allowed, true)) fail('Unknown action.');

foreach (['host', 'service', 'custom_message'] as $field) {
    if (array_key_exists($field, $data) && !is_string($data[$field])) {
        fail('Invalid ' . $field . '.');
    }
}

$cmd_file = NAGIOS_CMD_FILE;
if (!is_writable($cmd_file)) fail('Write permission denied for nagios.cmd', 500);
$user = sanitizeNagiosField(DASHBOARD_USER, 64);
$comment = sanitizeNagiosField('Action triggered via NOC Dashboard', 1024);
$duration_mins = 60;
if ($action === 'downtime' && array_key_exists('duration', $data)) {
    $duration = $data['duration'];
    if ((!is_int($duration) && !is_string($duration)) ||
        !preg_match('/\A[0-9]{1,6}\z/', (string)$duration) ||
        (int)$duration < 1 || (int)$duration > 525600) {
        fail('Duration must be between 1 and 525600 minutes.');
    }
    $duration_mins = (int)$duration;
}

// Delete comment logic
if ($action === 'del_comment') {
    $c_id = $data['comment_id'] ?? '';
    $c_type = $data['comment_type'] ?? '';
    if ((!is_string($c_id) && !is_int($c_id)) ||
        !preg_match('/\A[0-9]{1,20}\z/', (string)$c_id)) {
        fail('Invalid comment_id: expected digits only (up to 20).');
    }
    if (!in_array($c_type, ['servicecomment', 'hostcomment'], true)) fail('Invalid comment_type.');
    $now = time();
    $cmd_str = ($c_type === 'servicecomment')
        ? sprintf("[%lu] DEL_SVC_COMMENT;%s\n", $now, $c_id)
        : sprintf("[%lu] DEL_HOST_COMMENT;%s\n", $now, $c_id);

    writeCommands($cmd_str);
    echo json_encode(["status" => "success", "message" => "Comment deleted successfully."]);
    exit;
}

$targets = array_key_exists('targets', $data) ? $data['targets'] : [];
if (!is_array($targets) || count($targets) > 1000) fail('Invalid targets (maximum 1000).');
foreach ($targets as &$target) {
    if (!($target instanceof stdClass)) fail('Each target must be an object.');
    $target = get_object_vars($target);
}
unset($target);
if (isset($data['host']) && $data['host'] !== '') {
    $targets[] = ['host' => $data['host'], 'service' => $data['service'] ?? ''];
}

if (empty($targets)) fail('No targets (Hosts) provided.');
if (count($targets) > 1000) fail('Invalid targets (maximum 1000).');

$now = time();
$cmd_str = "";

foreach ($targets as $target) {
    if (!isset($target['host']) || !is_string($target['host']) ||
        (array_key_exists('service', $target) && !is_string($target['service']))) {
        fail('Each target requires a string host and an optional string service.');
    }
    $h = sanitizeNagiosField($target['host']);
    $s = sanitizeNagiosField($target['service'] ?? '');
    if ($h === '') fail('Target host must not be empty.');

    if ($action === 'recheck') {
        if ($s !== '' && $s !== 'HOST PING') $cmd_str .= sprintf("[%lu] SCHEDULE_FORCED_SVC_CHECK;%s;%s;%lu\n", $now, $h, $s, $now);
        else $cmd_str .= sprintf("[%lu] SCHEDULE_FORCED_HOST_CHECK;%s;%lu\n", $now, $h, $now);
    }
    elseif ($action === 'ack') {
        if ($s !== '' && $s !== 'HOST PING') $cmd_str .= sprintf("[%lu] ACKNOWLEDGE_SVC_PROBLEM;%s;%s;1;0;1;%s;%s\n", $now, $h, $s, $user, $comment);
        else $cmd_str .= sprintf("[%lu] ACKNOWLEDGE_HOST_PROBLEM;%s;1;0;1;%s;%s\n", $now, $h, $user, $comment);
    }
    elseif ($action === 'remove_ack') {
        if ($s !== '' && $s !== 'HOST PING') $cmd_str .= sprintf("[%lu] REMOVE_SVC_ACKNOWLEDGEMENT;%s;%s\n", $now, $h, $s);
        else $cmd_str .= sprintf("[%lu] REMOVE_HOST_ACKNOWLEDGEMENT;%s\n", $now, $h);
    }
    elseif ($action === 'enable_notif') {
        if ($s !== '' && $s !== 'HOST PING') $cmd_str .= sprintf("[%lu] ENABLE_SVC_NOTIFICATIONS;%s;%s\n", $now, $h, $s);
        else $cmd_str .= sprintf("[%lu] ENABLE_HOST_NOTIFICATIONS;%s\n", $now, $h);
    }
    elseif ($action === 'custom_notif') {
        $custom_msg = sanitizeNagiosField($data['custom_message'] ?? 'Custom alert from NOC Dashboard', 1024);
        if ($s !== '' && $s !== 'HOST PING') $cmd_str .= sprintf("[%lu] SEND_CUSTOM_SVC_NOTIFICATION;%s;%s;1;%s;%s\n", $now, $h, $s, $user, $custom_msg);
        else $cmd_str .= sprintf("[%lu] SEND_CUSTOM_HOST_NOTIFICATION;%s;1;%s;%s\n", $now, $h, $user, $custom_msg);
    }
    elseif ($action === 'add_comment') {
        $custom_msg = sanitizeNagiosField($data['custom_message'] ?? 'No text provided', 1024);
        if ($s !== '' && $s !== 'HOST PING') $cmd_str .= sprintf("[%lu] ADD_SVC_COMMENT;%s;%s;1;%s;%s\n", $now, $h, $s, $user, $custom_msg);
        else $cmd_str .= sprintf("[%lu] ADD_HOST_COMMENT;%s;1;%s;%s\n", $now, $h, $user, $custom_msg);
    }
    elseif ($action === 'downtime') {
        $end_time = $now + ($duration_mins * 60);
        if ($s !== '' && $s !== 'HOST PING') $cmd_str .= sprintf("[%lu] SCHEDULE_SVC_DOWNTIME;%s;%s;%lu;%lu;1;0;%lu;%s;%s\n", $now, $h, $s, $now, $end_time, ($duration_mins * 60), $user, "Maintenance via Dashboard");
        else $cmd_str .= sprintf("[%lu] SCHEDULE_HOST_DOWNTIME;%s;%lu;%lu;1;0;%lu;%s;%s\n", $now, $h, $now, $end_time, ($duration_mins * 60), $user, "Maintenance via Dashboard");
    }
}

writeCommands($cmd_str);
echo json_encode(["status" => "success", "message" => "Commands successfully sent to Nagios!"]);
?>
