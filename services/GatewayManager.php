<?php
/**
 * services/GatewayManager.php
 * ------------------------------------------------------------
 * Payment gateway abstraction layer — CyberSource READY.
 *
 * This is a pure ARCHITECTURE preparation class. It does NOT make
 * any external API calls and contains NO CyberSource implementation.
 *
 * The platform can be extended later by registering a concrete
 * gateway driver (e.g. CyberSource, Stripe, Authorize.Net) that
 * implements the GatewayContract interface. Until then, the manager
 * simply records payment intent locally (pending) and stores any
 * gateway response payloads for future reconciliation.
 *
 * Responsibilities:
 *   - Register / resolve gateway drivers (future)
 *   - Normalise gateway names + transaction types
 *   - Store gateway response payloads securely (JSON)
 *   - Track transaction lifecycle (authorize / capture / sale / refund / void / webhook)
 *
 * SECURITY: gateway payloads are stored via PDO prepared statements
 * and never returned to public-facing pages.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

/**
 * Contract every future gateway driver must implement.
 * (Documentation only — no CyberSource driver is provided.)
 */
interface GatewayContract
{
    public function getName(): string;
    public function authorize(array $params): array;
    public function capture(array $params): array;
    public function sale(array $params): array;
    public function refund(array $params): array;
    public function void(array $params): array;
    public function handleWebhook(array $payload): array;
}

class GatewayManager
{
    /**
     * Registered gateway drivers (name => class). Empty by design —
     * no gateway is implemented yet.
     */
    private static array $drivers = [];

    /**
     * Register a gateway driver for future use.
     */
    public static function register(string $name, string $class): void
    {
        self::$drivers[$name] = $class;
    }

    /**
     * Resolve a gateway driver by name, or null if not registered.
     */
    public static function resolve(string $name): ?GatewayContract
    {
        if (!isset(self::$drivers[$name])) {
            return null;
        }
        $class = self::$drivers[$name];
        return new $class();
    }

    /**
     * List all registered gateways.
     */
    public static function gateways(): array
    {
        return array_keys(self::$drivers);
    }

    /**
     * Normalise a gateway name to a stable slug (lowercase, no spaces).
     */
    public static function normalizeName(?string $gateway): string
    {
        if (!$gateway) {
            return '';
        }
        return strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', trim($gateway)));
    }

    /**
     * Record a gateway transaction in payment_transactions.
     * This is a local, gateway-independent record — no API call.
     *
     * @return int transaction id
     */
    public static function recordTransaction(
        int $paymentId,
        string $bookingType,
        ?string $bookingReference,
        string $transactionType,
        ?string $gateway,
        ?string $transactionReference,
        float $amount,
        string $currency,
        string $status,
        array $requestPayload = [],
        array $responsePayload = [],
        array $webhookPayload = [],
        ?int $userId = null,
        ?string $ip = null
    ): int {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "INSERT INTO payment_transactions
                   (payment_id, user_id, booking_type, booking_reference, gateway,
                    transaction_type, transaction_reference, amount, currency, status,
                    request_payload, response_payload, webhook_payload, ip_address)
                 VALUES
                   (:pid, :uid, :btype, :bref, :gw,
                    :ttype, :tref, :amt, :cur, :status,
                    :req, :resp, :webhook, :ip)"
            );
            $stmt->execute([
                ':pid'     => (int)$paymentId,
                ':uid'     => $userId && $userId > 0 ? (int)$userId : null,
                ':btype'   => $bookingType,
                ':bref'    => $bookingReference ?: null,
                ':gw'      => $gateway ?: null,
                ':ttype'   => $transactionType,
                ':tref'    => $transactionReference ?: null,
                ':amt'     => $amount,
                ':cur'     => $currency,
                ':status'  => $status,
                ':req'     => $requestPayload ? json_encode($requestPayload) : null,
                ':resp'    => $responsePayload ? json_encode($responsePayload) : null,
                ':webhook' => $webhookPayload ? json_encode($webhookPayload) : null,
                ':ip'      => $ip ?: null,
            ]);
            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('GatewayManager::recordTransaction failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch gateway transactions for a payment (used for reconciliation).
     * Never returns raw gateway payloads to public pages.
     */
    public static function getTransactions(int $paymentId): array
    {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "SELECT id, payment_id, gateway, transaction_type, transaction_reference,
                        amount, currency, status, created_at
                 FROM payment_transactions WHERE payment_id = :pid ORDER BY id ASC"
            );
            $stmt->execute([':pid' => $paymentId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}
