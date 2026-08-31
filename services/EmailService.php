<?php
/**
 * services/EmailService.php
 * ------------------------------------------------------------
 * Centralised transactional email helper for GAIA.
 *
 * Reads mail configuration from site_setting():
 *   mail_driver        — "php_mail" | "smtp"
 *   mail_from_name     — sender display name
 *   mail_from_email    — sender address
 *   smtp_host          — e.g. smtp.gmail.com
 *   smtp_port          — e.g. 587
 *   smtp_encryption    — "tls" | "ssl" | "none"
 *   smtp_username      — SMTP login
 *   smtp_password      — SMTP password / app password
 *
 * Public API:
 *   EmailService::sendPasswordChanged(string $toEmail, string $firstName, string $lang): bool
 *   EmailService::sendBookingConfirmation(array $booking, array $room, array $hotel, string $lang): bool
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../components/bootstrap.php';

class EmailService
{
    // -------------------------------------------------------
    // 1) Password Changed
    // -------------------------------------------------------
    public static function sendPasswordChanged(
        string $toEmail,
        string $firstName,
        string $lang = 'en'
    ): bool {
        $companyName = site_setting('company_name', 'GAIA TOURS & TRAVEL');

        if ($lang === 'ar') {
            $subject  = 'تم تغيير كلمة المرور — ' . $companyName;
            $greeting = 'مرحباً ' . htmlspecialchars($firstName) . '،';
            $body     = '<p>تم تغيير كلمة مرور حسابك على منصة <strong>' . htmlspecialchars($companyName) . '</strong> بنجاح.</p>';
            $note     = 'إذا لم تكن أنت من أجرى هذا التغيير، يُرجى التواصل معنا فوراً.';
            $closing  = 'مع تحيات فريق ' . htmlspecialchars($companyName);
        } else {
            $subject  = 'Your Password Has Been Changed — ' . $companyName;
            $greeting = 'Hello ' . htmlspecialchars($firstName) . ',';
            $body     = '<p>Your password on <strong>' . htmlspecialchars($companyName) . '</strong> has been changed successfully.</p>';
            $note     = 'If you did not make this change, please contact us immediately.';
            $closing  = 'The ' . htmlspecialchars($companyName) . ' Team';
        }

        $html = self::wrapTemplate($subject, $greeting, $body, $note, $closing, $companyName);
        return self::dispatch($toEmail, $subject, $html);
    }

    // -------------------------------------------------------
    // 1b) Password Reset OTP Code
    // -------------------------------------------------------
    public static function sendPasswordResetOtp(
        string $toEmail,
        string $firstName,
        string $otp,
        string $lang = 'en'
    ): bool {
        $companyName = site_setting('company_name', 'GAIA TOURS & TRAVEL');

        if ($lang === 'ar') {
            $subject  = 'رمز إعادة تعيين كلمة المرور — ' . $companyName;
            $greeting = 'مرحباً ' . htmlspecialchars($firstName) . '،';
            $body     = '<p>تلقينا طلباً لإعادة تعيين كلمة مرور حسابك. أدخل الرمز أدناه لإتمام العملية:</p>'
                      . '<div style="margin:24px 0;text-align:center;">'
                      . '<span style="display:inline-block;font-size:38px;font-weight:800;letter-spacing:12px;'
                      . 'color:#1b4c7a;background:#f0f6ff;border:2px dashed #1b4c7a;border-radius:12px;'
                      . 'padding:16px 32px;">' . htmlspecialchars($otp) . '</span>'
                      . '</div>'
                      . '<p style="text-align:center;color:#888;font-size:13px;">صالح لمدة <strong>10 دقائق</strong></p>';
            $note    = 'إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذا البريد بأمان.';
            $closing = 'فريق ' . htmlspecialchars($companyName);
        } else {
            $subject  = 'Your Password Reset Code — ' . $companyName;
            $greeting = 'Hello ' . htmlspecialchars($firstName) . ',';
            $body     = '<p>We received a request to reset your password. Enter the verification code below:</p>'
                      . '<div style="margin:24px 0;text-align:center;">'
                      . '<span style="display:inline-block;font-size:38px;font-weight:800;letter-spacing:12px;'
                      . 'color:#1b4c7a;background:#f0f6ff;border:2px dashed #1b4c7a;border-radius:12px;'
                      . 'padding:16px 32px;">' . htmlspecialchars($otp) . '</span>'
                      . '</div>'
                      . '<p style="text-align:center;color:#888;font-size:13px;">Valid for <strong>10 minutes</strong></p>';
            $note    = 'If you did not request a password reset, you can safely ignore this email.';
            $closing = 'The ' . htmlspecialchars($companyName) . ' Team';
        }

        $html = self::wrapTemplate($subject, $greeting, $body, $note, $closing, $companyName);
        return self::dispatch($toEmail, $subject, $html);
    }

    // -------------------------------------------------------
    // 2) Booking Confirmation — Hotel
    // -------------------------------------------------------
    public static function sendBookingConfirmation(
        array  $booking,
        array  $room,
        array  $hotel,
        string $lang = 'en'
    ): bool {
        $toEmail = $booking['guest_email'] ?? '';
        if ($toEmail === '') return false;

        $companyName = site_setting('company_name', 'GAIA TOURS & TRAVEL');
        $currency    = site_setting('currency_symbol', '$');

        $reference = htmlspecialchars($booking['booking_reference'] ?? '');
        $hotelName = htmlspecialchars($hotel['name'] ?? '');
        $roomName  = htmlspecialchars($room['name']  ?? '');
        $checkIn   = htmlspecialchars($booking['check_in_date']  ?? '');
        $checkOut  = htmlspecialchars($booking['check_out_date'] ?? '');
        $rooms     = (int)($booking['rooms_count'] ?? 1);
        $guests    = (int)($booking['guests'] ?? 1);
        $total     = number_format((float)($booking['total_price'] ?? 0), 0);
        $firstName = htmlspecialchars($booking['guest_name'] ?? 'Guest');

        if ($lang === 'ar') {
            $subject  = 'تأكيد الحجز ' . $reference . ' — ' . $companyName;
            $greeting = 'مرحباً ' . $firstName . '،';
            $body     = '
                <p>تم تأكيد حجزك بنجاح! فيما يلي تفاصيل الحجز:</p>
                ' . self::detailTable([
                    'رقم الحجز'     => $reference,
                    'الفندق'        => $hotelName,
                    'الغرفة'        => $roomName,
                    'تاريخ الوصول'  => $checkIn,
                    'تاريخ المغادرة'=> $checkOut,
                    'عدد الغرف'     => $rooms,
                    'عدد الضيوف'    => $guests,
                    'المبلغ الإجمالي' => '<strong style="color:#1b4c7a;">' . $currency . $total . '</strong>',
                ]);
            $note    = 'شكراً لاختيارك ' . htmlspecialchars($companyName) . '. نتطلع إلى استقبالك!';
            $closing = 'فريق ' . htmlspecialchars($companyName);
        } else {
            $subject  = 'Booking Confirmation ' . $reference . ' — ' . $companyName;
            $greeting = 'Hello ' . $firstName . ',';
            $body     = '
                <p>Your booking has been confirmed! Here are your reservation details:</p>
                ' . self::detailTable([
                    'Booking Reference' => $reference,
                    'Hotel'             => $hotelName,
                    'Room'              => $roomName,
                    'Check-in'          => $checkIn,
                    'Check-out'         => $checkOut,
                    'Rooms'             => $rooms,
                    'Guests'            => $guests,
                    'Total Amount'      => '<strong style="color:#1b4c7a;">' . $currency . $total . '</strong>',
                ]);
            $note    = 'Thank you for choosing ' . htmlspecialchars($companyName) . '. We look forward to welcoming you!';
            $closing = 'The ' . htmlspecialchars($companyName) . ' Team';
        }

        $html = self::wrapTemplate($subject, $greeting, $body, $note, $closing, $companyName);
        return self::dispatch($toEmail, $subject, $html);
    }

    // -------------------------------------------------------
    // Private: dispatch to correct driver
    // -------------------------------------------------------
    private static function dispatch(string $toEmail, string $subject, string $html): bool
    {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("[EmailService] Invalid recipient address: $toEmail");
            return false;
        }

        $driver    = site_setting('mail_driver', 'php_mail');
        $fromName  = site_setting('mail_from_name',  site_setting('company_name', 'GAIA TOURS'));
        $fromEmail = site_setting('mail_from_email', site_setting('contact_email', 'noreply@gaiatours.com'));

        if ($driver === 'smtp') {
            return self::sendViaSMTP($fromEmail, $fromName, $toEmail, $subject, $html);
        }
        return self::sendViaMail($fromEmail, $fromName, $toEmail, $subject, $html);
    }

    // -------------------------------------------------------
    // Driver A: PHP mail()
    // -------------------------------------------------------
    private static function sendViaMail(
        string $fromEmail, string $fromName,
        string $toEmail,   string $subject, string $html
    ): bool {
        $from     = mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>';
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $from\r\n";
        $headers .= "Reply-To: $fromEmail\r\n";
        $headers .= "X-Mailer: GAIA-Platform/1.0\r\n";
        $subjectEncoded = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");

        try {
            $ok = @mail($toEmail, $subjectEncoded, $html, $headers);
            if (!$ok) error_log("[EmailService] mail() returned false for: $toEmail");
            return (bool)$ok;
        } catch (\Throwable $e) {
            error_log("[EmailService] mail() exception: " . $e->getMessage());
            return false;
        }
    }

    // -------------------------------------------------------
    // Driver B: native PHP SMTP (no external libs)
    // -------------------------------------------------------
    private static function sendViaSMTP(
        string $fromEmail, string $fromName,
        string $toEmail,   string $subject, string $html
    ): bool {
        $host       = site_setting('smtp_host', '');
        $port       = (int) site_setting('smtp_port', '587');
        $enc        = site_setting('smtp_encryption', 'tls');
        $user       = site_setting('smtp_username', '');
        $pass       = site_setting('smtp_password', '');

        if ($host === '') {
            error_log("[EmailService] SMTP host not configured.");
            return false;
        }

        try {
            // Build socket address
            $socketHost = ($enc === 'ssl') ? 'ssl://' . $host : $host;
            $errno = 0; $errstr = '';
            $sock = @stream_socket_client($socketHost . ':' . $port, $errno, $errstr, 15);
            if (!$sock) {
                error_log("[EmailService] SMTP connect failed ($errno): $errstr");
                return false;
            }
            stream_set_timeout($sock, 15);

            $read = fn() => fgets($sock, 1024);
            $write = function(string $cmd) use ($sock): void { fwrite($sock, $cmd . "\r\n"); };
            $expect = function(int $code) use ($read): string {
                $resp = '';
                while (true) {
                    $line = fgets($sock ?? null, 1024);
                    if ($line === false) break;
                    $resp .= $line;
                    // Multi-line responses: "250-..." vs "250 ..." (final line)
                    if (strlen($line) >= 4 && $line[3] === ' ') break;
                }
                if ((int)substr($resp, 0, 3) !== $code) {
                    throw new \RuntimeException("SMTP expected $code, got: " . trim($resp));
                }
                return $resp;
            };

            // Greeting
            $expect(220);
            $write('EHLO ' . gethostname());
            $ehloResp = $expect(250);

            // STARTTLS upgrade
            if ($enc === 'tls') {
                $write('STARTTLS');
                $expect(220);
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException("STARTTLS crypto enable failed");
                }
                $write('EHLO ' . gethostname());
                $ehloResp = $expect(250);
            }

            // AUTH LOGIN
            if ($user !== '') {
                $write('AUTH LOGIN');
                $expect(334);
                $write(base64_encode($user));
                $expect(334);
                $write(base64_encode($pass));
                $expect(235);
            }

            // Build RFC 2822 message
            $boundary = '----=_Part_' . md5(uniqid('', true));
            $fromEncoded = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';
            $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $date = date('r');

            $message = "Date: $date\r\n"
                . "From: $fromEncoded\r\n"
                . "To: $toEmail\r\n"
                . "Subject: $subjectEncoded\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n"
                . "\r\n"
                . "--$boundary\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "\r\n"
                . chunk_split(base64_encode(strip_tags($html))) . "\r\n"
                . "--$boundary\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "\r\n"
                . chunk_split(base64_encode($html)) . "\r\n"
                . "--$boundary--\r\n";

            // Envelope
            $write("MAIL FROM:<$fromEmail>");
            $expect(250);
            $write("RCPT TO:<$toEmail>");
            $expect(250);
            $write('DATA');
            $expect(354);
            $write($message . '.');
            $expect(250);
            $write('QUIT');

            fclose($sock);
            return true;

        } catch (\Throwable $e) {
            error_log("[EmailService] SMTP error: " . $e->getMessage());
            if (isset($sock) && is_resource($sock)) fclose($sock);
            return false;
        }
    }

    // -------------------------------------------------------
    // Private: HTML detail table helper
    // -------------------------------------------------------
    private static function detailTable(array $rows): string
    {
        $html = '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
        foreach ($rows as $label => $value) {
            $html .= '<tr>'
                . '<td style="padding:8px 0;color:#888;white-space:nowrap;padding-right:16px;">' . htmlspecialchars($label) . '</td>'
                . '<td style="padding:8px 0;">' . $value . '</td>'
                . '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    // -------------------------------------------------------
    // Private: branded HTML wrapper
    // -------------------------------------------------------
    private static function wrapTemplate(
        string $title,
        string $greeting,
        string $body,
        string $note,
        string $closing,
        string $companyName
    ): string {
        $logo  = site_setting('logo_image', '');
        $color = '#1b4c7a';
        $logoHtml = $logo
            ? '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($companyName) . '" style="max-height:48px;margin-bottom:16px;">'
            : '<span style="font-size:22px;font-weight:800;letter-spacing:2px;color:#fff;">' . htmlspecialchars($companyName) . '</span>';

        return '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0;padding:0;background:#f4efe6;font-family:\'Helvetica Neue\',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4efe6;padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
        <tr><td align="center" style="background:' . $color . ';padding:32px 40px 24px;">' . $logoHtml . '</td></tr>
        <tr>
          <td style="padding:40px 40px 24px;">
            <p style="font-size:18px;font-weight:700;margin:0 0 16px;">' . $greeting . '</p>
            <div style="font-size:15px;line-height:1.7;color:#333;">' . $body . '</div>
          </td>
        </tr>
        <tr>
          <td style="padding:0 40px 32px;">
            <div style="background:#f4f8fb;border-left:4px solid ' . $color . ';border-radius:6px;padding:14px 18px;font-size:13.5px;color:#555;">' . htmlspecialchars($note) . '</div>
          </td>
        </tr>
        <tr>
          <td style="background:#f9f9f9;padding:24px 40px;border-top:1px solid #eee;text-align:center;">
            <p style="margin:0;font-size:13px;color:#888;">' . htmlspecialchars($closing) . '</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';
    }
}
