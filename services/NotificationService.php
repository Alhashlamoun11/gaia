<?php
/**
 * services/NotificationService.php
 * ------------------------------------------------------------
 * Customer notification service for the GAIA platform.
 *
 * DB-ready with NO email sending in this phase. Notifications are
 * stored in the `notifications` table and shown in the customer
 * Notification Center (account/notifications.php).
 *
 * Types: booking | payment | system
 *
 * SECURITY: all lookups are scoped to user_id to prevent IDOR.
 * All queries use PDO prepared statements.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

class NotificationService
{
    public const TYPE_BOOKING = 'booking';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_SYSTEM  = 'system';

    /**
     * Create a notification for a user.
     *
     * @return int notification id (0 on failure)
     */
    public static function create(
        int $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?string $link = null
    ): int {
        $type = self::normalizeType($type);
        if ($userId <= 0) {
            return 0;
        }
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, type, title, message, link)
                 VALUES (:uid, :type, :title, :msg, :link)"
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':type'  => $type,
                ':title' => $title,
                ':msg'   => $message !== null && $message !== '' ? $message : null,
                ':link'  => $link !== null && $link !== '' ? $link : null,
            ]);
            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('NotificationService::create failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch notifications for a user, optionally filtered by type.
     * Newest first.
     */
    public static function list(int $userId, ?string $type = null, int $limit = 50): array
    {
        if ($userId <= 0) {
            return [];
        }
        try {
            $pdo = getPDO();
            if ($type !== null && $type !== '') {
                $type = self::normalizeType($type);
                $stmt = $pdo->prepare(
                    "SELECT * FROM notifications
                     WHERE user_id = :uid AND type = :type
                     ORDER BY created_at DESC, id DESC LIMIT :lim"
                );
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':type', $type, PDO::PARAM_STR);
                $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare(
                    "SELECT * FROM notifications
                     WHERE user_id = :uid
                     ORDER BY created_at DESC, id DESC LIMIT :lim"
                );
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
                $stmt->execute();
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Count unread notifications for a user (used for the unread badge).
     */
    public static function unreadCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0"
            );
            $stmt->execute([':uid' => $userId]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Mark a single notification as read (ownership-scoped).
     */
    public static function markRead(int $notificationId, int $userId): bool
    {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare(
                "UPDATE notifications SET is_read = 1
                 WHERE id = :id AND user_id = :uid"
            );
            return $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Mark ALL of a user's notifications as read (ownership-scoped).
     */
    public static function markAllRead(int $userId): bool
    {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare(
                "UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0"
            );
            return $stmt->execute([':uid' => $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Delete a notification (ownership-scoped).
     */
    public static function delete(int $notificationId, int $userId): bool
    {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare(
                "DELETE FROM notifications WHERE id = :id AND user_id = :uid"
            );
            return $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Normalise a notification type to the allowed set.
     */
    private static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        return in_array($type, [self::TYPE_BOOKING, self::TYPE_PAYMENT, self::TYPE_SYSTEM], true)
            ? $type
            : self::TYPE_SYSTEM;
    }
}
