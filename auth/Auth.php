<?php
/**
 * auth/Auth.php
 * ------------------------------------------------------------
 * Central authentication service for the GAIA platform.
 *
 * Provides:
 *   - password registration + auto-login
 *   - session-based login with password_verify()
 *   - login throttling (login_attempts table)
 *   - remember-me via hashed tokens (remember_tokens table)
 *   - session regeneration on auth state change
 *   - role & permission resolution (roles, role_permissions)
 *   - password reset token issuance
 *
 * All DB access uses PDO prepared statements.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

class Auth
{
    // Throttling limits
    const MAX_ATTEMPTS          = 5;
    const LOCKOUT_WINDOW        = 900;   // 15 minutes (seconds)
    const REMEMBER_TTL          = 30;    // days
    const PASSWORD_MIN_LENGTH   = 8;

/**
     * Start a secure session if one is not already active.
     */
    public static function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            session_start();
        }
    }

    /**
     * Register a new customer account.
     * Returns [bool, array|null] where array is the created user row.
     */
    public static function register(array $data)
    {
        $pdo = getPDO();

        $email = strtolower(trim($data['email'] ?? ''));
        $pass  = $data['password'] ?? '';

        // Unique email check
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return [false, null];
        }

        // Customer role
        $roleId = (int)($data['role_id'] ?? 0);
        if ($roleId === 0) {
            $r = $pdo->query("SELECT id FROM roles WHERE slug = 'customer' LIMIT 1")->fetch();
            $roleId = $r ? (int)$r['id'] : 3;
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            'INSERT INTO users
               (first_name, last_name, email, phone, country, preferred_language, password_hash, role_id, status, email_verified_at)
             VALUES
               (:first_name, :last_name, :email, :phone, :country, :preferred_language, :password_hash, :role_id, :status, :verified)'
        );
        $stmt->execute([
            ':first_name'          => trim($data['first_name'] ?? ''),
            ':last_name'           => trim($data['last_name'] ?? ''),
            ':email'               => $email,
            ':phone'               => trim($data['phone'] ?? '') !== '' ? trim($data['phone']) : null,
            ':country'             => trim($data['country'] ?? '') !== '' ? trim($data['country']) : null,
            ':preferred_language'  => in_array($data['preferred_language'] ?? '', ['en', 'ar'], true) ? $data['preferred_language'] : 'en',
            ':password_hash'       => $hash,
            ':role_id'             => $roleId,
            ':status'              => 'active',
            ':verified'            => date('Y-m-d H:i:s'),
        ]);

        $userId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('SELECT u.*, r.slug AS role_slug, r.name AS role_name
                               FROM users u JOIN roles r ON r.id = u.role_id
                               WHERE u.id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return [true, $stmt->fetch()];
    }

    /**
     * Attempt to log a user in.
     * Returns [bool, ?string] where string is an error message key.
     */
    public static function login(string $email, string $password, bool $remember = false)
    {
        $pdo = getPDO();
        $email = strtolower(trim($email));
        $ip = self::clientIp();

        // 1) Throttle check
        if (self::isThrottled($email, $ip)) {
            return [false, 'auth.too_many_attempts'];
        }

        // 2) Fetch user
        $stmt = $pdo->prepare('SELECT u.*, r.slug AS role_slug, r.name AS role_name
                               FROM users u JOIN roles r ON r.id = u.role_id
                               WHERE u.email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::recordAttempt($email, $ip);
            return [false, 'auth.invalid_credentials'];
        }

        // 3) Status check
        if ($user['status'] !== 'active') {
            return [false, 'auth.account_inactive'];
        }

        // 4) Clear failed attempts, update last_login, regenerate session
        self::clearAttempts($email);
        self::establishLogin($user, $remember);

        return [true, null];
    }

    /**
     * Finalize a login: set session, regenerate id, optional remember-me.
     */
    private static function establishLogin(array $user, bool $remember)
    {
        $pdo = getPDO();

        // Update last login
        $pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s'), (int)$user['id']]);

        // Regenerate session id to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['auth_user_id'] = (int)$user['id'];
        $_SESSION['auth_role']    = $user['role_slug'];
        $_SESSION['auth_name']    = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['auth_email']   = $user['email'];

        // Preferred language from account
        if (!empty($user['preferred_language']) && in_array($user['preferred_language'], ['en', 'ar'], true)) {
            $_SESSION['gaia_lang'] = $user['preferred_language'];
        }

        if ($remember) {
            self::issueRememberToken((int)$user['id']);
        }
    }

    /**
     * Issue a remember-me token (selector:hash) and set a cookie.
     */
    private static function issueRememberToken(int $userId)
    {
        $pdo = getPDO();
        $selector = bin2hex(random_bytes(12));
        $plain    = bin2hex(random_bytes(32));
        $hash     = hash('sha256', $plain);
        $expires  = date('Y-m-d H:i:s', time() + (self::REMEMBER_TTL * 86400));

        $stmt = $pdo->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
             VALUES (:user_id, :selector, :token_hash, :expires_at)'
        );
        $stmt->execute([
            ':user_id'    => $userId,
            ':selector'   => $selector,
            ':token_hash' => $hash,
            ':expires_at' => $expires,
        ]);

        setcookie(
            'gaia_remember',
            $selector . ':' . $plain,
            time() + (self::REMEMBER_TTL * 86400),
            '/',
            '',
            !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            true
        );
    }

    /**
     * Attempt to authenticate via a remember-me cookie.
     */
    public static function loginViaRememberToken()
    {
        if (empty($_COOKIE['gaia_remember'])) {
            return false;
        }
        $parts = explode(':', $_COOKIE['gaia_remember'], 2);
        if (count($parts) !== 2) {
            return false;
        }
        $selector = $parts[0];
        $plain    = $parts[1];
        $hash     = hash('sha256', $plain);

        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT rt.token_hash, rt.expires_at, u.*, r.slug AS role_slug, r.name AS role_name
             FROM remember_tokens rt
             JOIN users u ON u.id = rt.user_id
             JOIN roles r ON r.id = u.role_id
             WHERE rt.selector = ? LIMIT 1'
        );
        $stmt->execute([$selector]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        // Constant-time compare + expiry check
        if (!hash_equals($row['token_hash'], $hash)) {
            return false;
        }
        if (strtotime($row['expires_at']) < time()) {
            return false;
        }
        if ($row['status'] !== 'active') {
            return false;
        }

        // Rotate the token (single-use)
        self::clearRemember($selector);
        self::establishLogin($row, true);
        return true;
    }

    /**
     * Retrieve the currently authenticated user (or null).
     */
    public static function user()
    {
        self::startSession();
        if (empty($_SESSION['auth_user_id'])) {
            return null;
        }
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT u.*, r.slug AS role_slug, r.name AS role_name
                               FROM users u JOIN roles r ON r.id = u.role_id
                               WHERE u.id = ? AND u.status = "active" LIMIT 1');
        $stmt->execute([(int)$_SESSION['auth_user_id']]);
        $user = $stmt->fetch();
        if (!$user) {
            self::logout();
            return null;
        }
        return $user;
    }

    /**
     * True if a user is currently authenticated.
     */
    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION['auth_user_id']);
    }

    /**
     * Resolve the current role slug (guest => 'guest').
     */
    public static function role(): string
    {
        self::startSession();
        return $_SESSION['auth_role'] ?? 'guest';
    }

    /**
     * Check whether the current user has the given permission slug.
     * Super admin bypasses everything.
     */
    public static function hasPermission(string $permission): bool
    {
        self::startSession();
        if (self::role() === 'super_admin') {
            return true;
        }
        if (empty($_SESSION['auth_user_id'])) {
            return false;
        }
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             JOIN users u ON u.role_id = rp.role_id
             WHERE u.id = ? AND p.slug = ?'
        );
        $stmt->execute([(int)$_SESSION['auth_user_id'], $permission]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Log the current user out and clear remember-me.
     */
    public static function logout()
    {
        self::startSession();
        if (!empty($_COOKIE['gaia_remember'])) {
            $selector = explode(':', $_COOKIE['gaia_remember'], 2)[0];
            self::clearRemember($selector);
            setcookie('gaia_remember', '', time() - 3600, '/');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ---------------------------------------------------------
    // Throttling
    // ---------------------------------------------------------
    private static function isThrottled(string $email, string $ip): bool
    {
        $pdo = getPDO();
        $since = date('Y-m-d H:i:s', time() - self::LOCKOUT_WINDOW);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE attempted_at >= ? AND (email = ? OR ip_address = ?)'
        );
        $stmt->execute([$since, $email, $ip]);
        return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    private static function recordAttempt(string $email, string $ip)
    {
        $pdo = getPDO();
        $pdo->prepare('INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, ?)')
            ->execute([$email, $ip, date('Y-m-d H:i:s')]);
        // Keep table from growing unbounded
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }

    private static function clearAttempts(string $email)
    {
        $pdo = getPDO();
        $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
    }

    private static function clearRemember(string $selector)
    {
        $pdo = getPDO();
        $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$selector]);
    }

    private static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    // ---------------------------------------------------------
    // Password reset
    // ---------------------------------------------------------
    /**
     * Issue a password reset token for a user (if the email exists).
     * Returns the raw token (to email) or null if no user.
     */
    public static function issueResetToken(string $email): ?string
    {
        $pdo = getPDO();
        $email = strtolower(trim($email));
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND status = "active" LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            return null;
        }

        $token  = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
            ->execute([(int)$user['id'], hash('sha256', $token), $expiry]);
        return $token;
    }

    /**
     * Validate a reset token. Returns the user id or null.
     */
    public static function validateResetToken(string $token): ?int
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT pr.user_id, pr.expires_at, pr.used
             FROM password_resets pr
             WHERE pr.token_hash = ? ORDER BY pr.id DESC LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['used'] === 1 || strtotime($row['expires_at']) < time()) {
            return null;
        }
        return (int)$row['user_id'];
    }

    /**
     * Set a new password for a user and mark resets used.
     */
    public static function resetPassword(int $userId, string $password, string $token)
    {
        $pdo = getPDO();
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token_hash = ?')
            ->execute([hash('sha256', $token)]);
    }
}
