-- Chạy một lần trên DB Fun Pop / social_app.
-- Cho phép lưu người thực hiện (actor) và post_id để hiển thị thông báo đúng (like, comment, @).

ALTER TABLE notifications
	ADD COLUMN actor_id INT UNSIGNED NULL DEFAULT NULL AFTER user_id,
	ADD COLUMN post_id INT UNSIGNED NULL DEFAULT NULL AFTER reference_id;

CREATE INDEX idx_notifications_user_created ON notifications (user_id, created_at DESC);
CREATE INDEX idx_notifications_actor ON notifications (actor_id);
