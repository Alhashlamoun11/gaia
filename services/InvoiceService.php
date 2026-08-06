<?php
/**
 * services/InvoiceService.php
 * ------------------------------------------------------------
 * Generic, reusable invoice architecture for the GAIA platform.
 *
 * Invoices cover every booking type (transfer, tour, hotel, room,
 * event, taxi). An invoice is linked to a booking via
 * booking_type + booking_id + booking_reference.
 *
 * Invoice numbers are generated automatically in the format:
 *   INV-YYYY-NNNNNN   (e.g. INV-2026-000001)
 *
 * NO PDF generation — this is database + architecture preparation
 * only, ready for future invoice views / PDF export.
 *
 * SECURITY: invoice lookups are scoped to user_id to prevent IDOR.
 * All queries use PDO prepared statements.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

class InvoiceService
{
    /**
     * Valid invoice statuses.
     */
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ISSUED   = 'issued';
    public const STATUS_PAID     = 'paid';
    public const STATUS_VOID     = 'void';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Generate the next unique invoice number (INV-YYYY-NNNNNN).
     *
     * Uses the current year + a zero-padded sequence derived from the
     * highest existing invoice number for that year, +1.
     */
    public static function nextInvoiceNumber(PDO $pdo): string
    {
        $year = (int)date('Y');
        $stmt = $pdo->prepare(
            "SELECT invoice_number FROM invoices
             WHERE invoice_number LIKE :prefix
             ORDER BY id DESC LIMIT 1"
        );
        $prefix = "INV-{$year}-";
        $stmt->execute([':prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        $seq = 1;
        if ($last && preg_match('/-(\d{6})$/', (string)$last, $m)) {
            $seq = (int)$m[1] + 1;
        }
        return sprintf('INV-%04d-%06d', $year, $seq);
    }

    /**
     * Create an invoice for a booking.
     *
     * @param string $bookingType booking type
     * @param int    $bookingId   booking record id
     * @param int    $userId      owning user id
     * @param string $reference   booking reference (GAIA-*)
     * @param float  $subtotal    pre-tax amount
     * @param float  $taxAmount   tax amount
     * @param float  $discountAmount discount amount
     * @param float  $totalAmount final total
     * @param string $currency    ISO currency code
     * @param string $status      invoice status (default 'issued')
     * @param string|null $issuedAt optional issued_at datetime
     *
     * @return int invoice id
     */
    public static function create(
        string $bookingType,
        int $bookingId,
        int $userId,
        string $reference,
        float $subtotal,
        float $taxAmount,
        float $discountAmount,
        float $totalAmount,
        string $currency = 'USD',
        string $status = self::STATUS_ISSUED,
        ?string $issuedAt = null
    ): int {
        try {
            $pdo = getPDO();
            $invoiceNumber = self::nextInvoiceNumber($pdo);
            $issuedAt = $issuedAt ?: date('Y-m-d H:i:s');

            $stmt = $pdo->prepare(
                "INSERT INTO invoices
                   (user_id, booking_type, booking_id, booking_reference, invoice_number,
                    subtotal, tax_amount, discount_amount, total_amount, currency, status, issued_at)
                 VALUES
                   (:uid, :btype, :bid, :bref, :inum,
                    :sub, :tax, :disc, :total, :cur, :status, :issued)"
            );
            $stmt->execute([
                ':uid'    => (int)$userId,
                ':btype'  => $bookingType,
                ':bid'    => (int)$bookingId,
                ':bref'   => $reference ?: null,
                ':inum'   => $invoiceNumber,
                ':sub'    => $subtotal,
                ':tax'    => $taxAmount,
                ':disc'   => $discountAmount,
                ':total'  => $totalAmount,
                ':cur'    => $currency,
                ':status' => $status,
                ':issued' => $issuedAt,
            ]);
            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('InvoiceService::create failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch a single invoice by id, ownership-scoped.
     */
    public static function get(int $invoiceId, ?int $userId = null): ?array
    {
        try {
            $pdo = getPDO();
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id AND user_id = :uid LIMIT 1");
                $stmt->execute([':id' => $invoiceId, ':uid' => $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $invoiceId]);
            }
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Fetch the invoice linked to a booking, ownership-scoped.
     */
    public static function getForBooking(string $bookingType, int $bookingId, ?int $userId = null): ?array
    {
        try {
            $pdo = getPDO();
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT * FROM invoices
                     WHERE booking_type = :btype AND booking_id = :bid AND user_id = :uid
                     ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([':btype' => $bookingType, ':bid' => $bookingId, ':uid' => $userId]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT * FROM invoices
                     WHERE booking_type = :btype AND booking_id = :bid
                     ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([':btype' => $bookingType, ':bid' => $bookingId]);
            }
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Fetch all invoices for a user.
     */
    public static function getForUser(int $userId): array
    {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "SELECT * FROM invoices WHERE user_id = :uid ORDER BY id DESC"
            );
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Update an invoice status, ownership-scoped.
     */
    public static function updateStatus(int $invoiceId, string $status, ?int $userId = null): bool
    {
        try {
            $pdo = getPDO();
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare(
                    "UPDATE invoices SET status = :status WHERE id = :id AND user_id = :uid"
                );
                return $stmt->execute([':status' => $status, ':id' => $invoiceId, ':uid' => $userId]);
            }
            $stmt = $pdo->prepare("UPDATE invoices SET status = :status WHERE id = :id");
            return $stmt->execute([':status' => $status, ':id' => $invoiceId]);
        } catch (PDOException $e) {
            return false;
        }
    }
}
