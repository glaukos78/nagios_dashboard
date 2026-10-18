<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$cmd_file = NAGIOS_CMD_FILE;
$data = json_decode(file_get_contents('php://input'), true);

$action = $data['action'] ?? '';
$user = DASHBOARD_USER;
$comment = "Action triggered via NOC Dashboard";
$duration_mins = (int)($data['duration'] ?? 60);

// Delete comment logic
if ($action === 'del_comment') {
    $c_id = $data['comment_id'] ?? '';
    $c_type = $data['comment_type'] ?? '';
    if ($c_id !== '') {
        $now = time();
        $cmd_str = ($c_type === 'servicecomment')
            ? sprintf("[%lu] DEL_SVC_COMMENT;%s\n", $now, $c_id)
            : sprintf("[%lu] DEL_HOST_COMMENT;%s\n", $now, $c_id);

        file_put_contents($cmd_file, $cmd_str, FILE_APPEND);
        echo json_encode(["status" => "success", "message" => "Comment deleted successfully."]);
        exit;
    }
}

$targets = $data['targets'] ?? [];
if (isset($data['host']) && $data['host'] !== '') {
    $targets[] = ['host' => $data['host'], 'service' => $data['service'] ?? ''];
}

if (empty($targets)) {
    echo json_encode(["status" => "error", "message" => "No targets (Hosts) provided."]);
    exit;
}

$now = time();
$cmd_str = "";

foreach ($targets as $target) {
    $h = $target['host'];
    $s = $target['service'];

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
        $custom_msg = $data['custom_message'] ?? 'Custom alert from NOC Dashboard';
        if ($s !== '' && $s !== 'HOST PING') $cmd_str .= sprintf("[%lu] SEND_CUSTOM_SVC_NOTIFICATION;%s;%s;1;%s;%s\n", $now, $h, $s, $user, $custom_msg);
        else $cmd_str .= sprintf("[%lu] SEND_CUSTOM_HOST_NOTIFICATION;%s;1;%s;%s\n", $now, $h, $user, $custom_msg);
    }
    elseif ($action === 'add_comment') {
        $custom_msg = $data['custom_message'] ?? 'No text provided';
        if ($s !== '' && $s !== 'HOST PING') $cmd_str .= sprintf("[%lu] ADD_SVC_COMMENT;%s;%s;1;%s;%s\n", $now, $h, $s, $user, $custom_msg);
        else $cmd_str .= sprintf("[%lu] ADD_HOST_COMMENT;%s;1;%s;%s\n", $now, $h, $user, $custom_msg);
    }
    elseif ($action === 'downtime') {
        $end_time = $now + ($duration_mins * 60);
        if ($s !== '' && $s !== 'HOST PING') $cmd_str .= sprintf("[%lu] SCHEDULE_SVC_DOWNTIME;%s;%s;%lu;%lu;1;0;%lu;%s;%s\n", $now, $h, $s, $now, $end_time, ($duration_mins * 60), $user, "Maintenance via Dashboard");
        else $cmd_str .= sprintf("[%lu] SCHEDULE_HOST_DOWNTIME;%s;%lu;%lu;1;0;%lu;%s;%s\n", $now, $h, $now, $end_time, ($duration_mins * 60), $user, "Maintenance via Dashboard");
    }
}

if ($cmd_str !== "") {
    if (is_writable($cmd_file)) {
        file_put_contents($cmd_file, $cmd_str, FILE_APPEND);
        echo json_encode(["status" => "success", "message" => "Commands successfully sent to Nagios!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Write permission denied for nagios.cmd"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Unknown action."]);
}
?>
