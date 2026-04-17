<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/helpers/notification_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user']['id'])) {
    echo json_encode(['ok' => false, 'msg' => 'not login']);
    exit;
}

$userId = (int) $_SESSION['user']['id'];
$notificationId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if ($notificationId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'bad id']);
    exit;
}

$conn = Database::getInstance()->getConnection();
notification_mark_read($conn, $userId, $notificationId);
$unread = notifications_unread_count($conn, $userId);

echo json_encode(['ok' => true, 'unread' => $unread]);
