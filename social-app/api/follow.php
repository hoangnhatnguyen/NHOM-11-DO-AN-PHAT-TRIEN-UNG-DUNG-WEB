<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/helpers/notification_helper.php';

header('Content-Type: application/json; charset=utf-8');

$currentUserId = (int) ($_SESSION['user']['id'] ?? 0);
$targetUserId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

if (!$currentUserId || !$targetUserId || $targetUserId === $currentUserId) {
    echo json_encode(['ok' => false, 'msg' => 'invalid']);
    exit;
}

$conn = Database::getInstance()->getConnection();

$stmt = $conn->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1');
$stmt->execute([$currentUserId, $targetUserId]);

if ($stmt->fetchColumn()) {
    echo json_encode(['ok' => true, 'already' => true]);
    exit;
}

$stmt = $conn->prepare('INSERT INTO follows (follower_id, following_id) VALUES (?, ?)');
$stmt->execute([$currentUserId, $targetUserId]);

create_notification($conn, $targetUserId, $currentUserId, 'follow', $currentUserId, null);

echo json_encode(['ok' => true]);
