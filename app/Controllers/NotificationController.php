<?php
class NotificationController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function create($user_id, $message)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, message, status, created_at)
                VALUES (?, ?, 'Sent', NOW())
            ");
            $stmt->execute([$user_id, $message]);
            return true;
        } catch (PDOException $e) {
            error_log('Notification create error: ' . $e->getMessage());
            return false;
        }
    }

    public function getUnread($user_id, $limit = 10)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Notification fetch error: ' . $e->getMessage());
            return [];
        }
    }

    public function markAsRead($notification_id, $user_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications SET status = 'Read'
                WHERE notification_id = ? AND user_id = ?
            ");
            $stmt->execute([$notification_id, $user_id]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function markAllAsRead($user_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications SET status = 'Read'
                WHERE user_id = ? AND status = 'Sent'
            ");
            $stmt->execute([$user_id]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function delete($notification_id, $user_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM notifications
                WHERE notification_id = ? AND user_id = ?
            ");
            $stmt->execute([$notification_id, $user_id]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getUnreadCount($user_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications
                WHERE user_id = ? AND status = 'Sent'
            ");
            $stmt->execute([$user_id]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}
