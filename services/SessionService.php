<?php
/**
 * services/SessionService.php
 * ------------------------------------------------------------
 * Login-session tracking for the GAIA security page.
 *
 * Records recent login sessions in the `user_sessions` table.
 * The session_hash is a SHA-256 of the PHP session id so we can
 * identify the current device without storing the raw session id.
 *
 * Features used by account/security.php:
 *   - record()     : upsert a session entry on login/activity
 *   - current()    : the current PHPSESSID hash
 *   - listRecent() : recent devices for a user (ownership-scoped)
 *   - revokeAll()  : placeholder hook for "Logout all devices"
 *                    (only clears the reference rows; actual PHP
 *                    session invalidation is out of scope this phase)
 *
 * SECURITY: all lookups are scoped to user_id to prevent IDOR.
 * All queries use PDO prepared statements.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

class SessionService
{
    /**
     * SHA-256 hash of the current PHP session id (or '' if none).
     */
    public static function currentHash(): string
    {
        $sid = session_id();
        return $sid !== '' ? hash('sha256', $sid) : '';
    }

    /**
     * Record / refresh the current session for a user.
     * The unique key is the session_hash, so repeated activity just
     * bumps last_activity + is_current for that device.
     */
    public static function record(int $userId, ?string $userAgent = null, ?string $ip = null): void
    {
        $hash = self::currentHash();
        if ($userId <= 0 || $hash === '') {
            return;
        }
        // Clear the is_current flag on this user's other sessions,
        // then upsert this one as current.
        try {
            $pdo = getPDO();
            $pdo->prepare('UPDATE user_sessions SET is_current = 0 WHERE user_id = :uid')
                ->execute([':uid' => $userId]);

            $stmt = $pdo->prepare(
                "INSERT INTO user_sessions (user_id, session_hash, ip_address, user_agent, device, is_current, last_activity)
                 VALUES (:uid, :hash, :ip, :ua, :device, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                   is_current = 1,
                   last_activity = NOW(),
                   ip_address = COALESCE(VALUES(ip_address), ip_address),
                   user_agent = COALESCE(VALUES(user_agent), user_agent),
                   device = COALESCE(VALUES(device), device)"
            );
            $stmt->execute([
                ':uid'    => $userId,
                ':hash'   => $hash,
                ':ip'     => $ip ?: null,
                ':ua'     => ($userAgent !== null && $userAgent !== '') ? substr($userAgent, 0, 250) : null,
                ':device' => self::deviceLabel($userAgent),
            ]);
        } catch (PDOException $e) {
            // Best-effort — session tracking must never break a page.
            error_log('SessionService::record failed: ' . $e->getMessage());
        }
    }

    /**
     * List recent sessions for a user (newest activity first).
     * Ownership-scoped.
     */
    public static function listRecent(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) {
            return [];
        }
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "SELECT id, ip_address, user_agent, device, is_current, created_at, last_activity
                 FROM user_sessions
                 WHERE user_id = :uid
                 ORDER BY last_activity DESC, id DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Revoke all of a user's session reference rows.
     *
     * NOTE: This phase only clears the tracking rows. Truly
     * invalidating active PHP sessions across devices requires a
     * session-store tier and is intentionally out of scope.
     * The UI shows this as a placeholder.
     */
    public static function revokeAll(int $userId): bool
    {
        try {
            $pdo = getPDO();
            return $pdo->prepare('DELETE FROM user_sessions WHERE user_id = :uid')
                ->execute([':uid' => $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Produce a short human-readable device label from a UA string.
     */
    public static function deviceLabel(?string $userAgent): string
    {
        $ua = strtolower((string)$userAgent);
        if (strpos($ua, 'mobile') !== false || strpos($ua, 'iphone') !== false || strpos($ua, 'android') !== false) {
            $device = 'Mobile';
        } elseif (strpos($ua, 'ipad') !== false || strpos($ua, 'tablet') !== false) {
            $device = 'Tablet';
        } else {
            $device = 'Desktop';
        }
        if (strpos($ua, 'windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($ua, 'mac os') !== false || strpos($ua, 'macintosh') !== false) {
            $os = 'macOS';
        } elseif (strpos($ua, 'android') !== false) {
            $os = 'Android';
        } elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) {
            $os = 'iOS';
        } elseif (strpos($ua, 'linux') !== false) {
            $os = 'Linux';
        } else {
            $os = '';
        }
        return trim($device . ($os !== '' ? ' · ' . $os : '')) ?: $ua;
    }
}
