<?php

require_once __DIR__ . '/../../config/database.php';

class Notification {

    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    public function getByUser($userId) {

        $sql = "
        SELECT 
            n.*,
            COALESCE(u_like.username, u_comment.username, u_follow.username, u_share.username) AS actor_name

        FROM notifications n

        LEFT JOIN likes l 
            ON n.type = 'like' AND l.post_id = n.reference_id
        LEFT JOIN users u_like 
            ON l.user_id = u_like.id

        LEFT JOIN comments c 
            ON n.type = 'comment' AND c.post_id = n.reference_id
        LEFT JOIN users u_comment 
            ON c.user_id = u_comment.id

        LEFT JOIN users u_follow 
            ON n.type = 'follow' AND u_follow.id = n.reference_id

        LEFT JOIN shares s 
            ON n.type = 'share' AND s.post_id = n.reference_id
        LEFT JOIN users u_share 
            ON s.user_id = u_share.id

        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 50
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUnread($userId) {
        $sql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function markAllRead($userId) {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$userId]);
    }
}