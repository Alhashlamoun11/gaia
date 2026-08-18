<?php
/**
 * services/FavoriteService.php
 * ------------------------------------------------------------
 * Customer favorites / wishlist service for the GAIA platform.
 *
 * DB-ready placeholder supporting tours, hotels and events.
 * Future expansion can add rooms, transfers, etc.
 *
 * SECURITY: all operations are scoped to user_id to prevent IDOR.
 * All queries use PDO prepared statements.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

class FavoriteService
{
    public const TYPE_TOUR  = 'tour';
    public const TYPE_HOTEL = 'hotel';
    public const TYPE_EVENT = 'event';

    /**
     * Allowed item types.
     */
    public static function types(): array
    {
        return [self::TYPE_TOUR, self::TYPE_HOTEL, self::TYPE_EVENT];
    }

    /**
     * Add a favorite (ownership-scoped). Returns true if added.
     */
    public static function add(int $userId, string $type, int $itemId): bool
    {
        $type = self::normalizeType($type);
        if ($userId <= 0 || $itemId <= 0) {
            return false;
        }
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO favorites (user_id, item_type, item_id)
                 VALUES (:uid, :type, :iid)"
            );
            return $stmt->execute([':uid' => $userId, ':type' => $type, ':iid' => $itemId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Remove a favorite (ownership-scoped).
     */
    public static function remove(int $userId, string $type, int $itemId): bool
    {
        $type = self::normalizeType($type);
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "DELETE FROM favorites
                 WHERE user_id = :uid AND item_type = :type AND item_id = :iid"
            );
            return $stmt->execute([':uid' => $userId, ':type' => $type, ':iid' => $itemId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Check whether a user has favorited a given item.
     */
    public static function has(int $userId, string $type, int $itemId): bool
    {
        $type = self::normalizeType($type);
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM favorites
                 WHERE user_id = :uid AND item_type = :type AND item_id = :iid"
            );
            $stmt->execute([':uid' => $userId, ':type' => $type, ':iid' => $itemId]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * List a user's favorites, optionally filtered by type.
     * Order: most recently added first.
     */
    public static function list(int $userId, ?string $type = null): array
    {
        if ($userId <= 0) {
            return [];
        }
        try {
            $pdo = getPDO();
            if ($type !== null && $type !== '') {
                $type = self::normalizeType($type);
                $stmt = $pdo->prepare(
                    "SELECT * FROM favorites
                     WHERE user_id = :uid AND item_type = :type
                     ORDER BY id DESC"
                );
                $stmt->execute([':uid' => $userId, ':type' => $type]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT * FROM favorites WHERE user_id = :uid ORDER BY id DESC"
                );
                $stmt->execute([':uid' => $userId]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Count a user's favorites.
     */
    public static function count(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = :uid");
            $stmt->execute([':uid' => $userId]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Normalise an item type to the allowed set.
     */
    private static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        return in_array($type, self::types(), true) ? $type : self::TYPE_TOUR;
    }
}
