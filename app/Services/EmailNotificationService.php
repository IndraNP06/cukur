<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    /**
     * Kirim email dengan multi-driver:
     * 1. Coba SMTP Laravel jika terkonfigurasi.
     * 2. Fallback ke Google Apps Script Webhook (MailApp) jika tersedia.
     * 3. Fallback ke default Mailer Laravel (Log/Array).
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $plainText = null): array
    {
        $plainText = $plainText ?? strip_tags($htmlBody);
        $fromAddress = config('mail.from.address', env('MAIL_FROM_ADDRESS', 'no-reply@rancukurin.com'));
        $fromName = config('mail.from.name', env('MAIL_FROM_NAME', 'Ran CukurIn Barbershop'));

        // 1. Coba kirim via SMTP jika dikonfigurasi
        $mailer = config('mail.default', env('MAIL_MAILER', 'log'));
        $smtpUser = config('mail.mailers.smtp.username', env('MAIL_USERNAME'));

        if ($mailer === 'smtp' && !empty($smtpUser)) {
            try {
                Mail::html($htmlBody, function ($message) use ($to, $subject, $fromAddress, $fromName) {
                    $message->to($to)
                        ->from($fromAddress, $fromName)
                        ->subject($subject);
                });

                Log::info("Email berhasil dikirim via SMTP ke: {$to} [Subject: {$subject}]");
                return ['success' => true, 'driver' => 'smtp', 'message' => 'Email berhasil dikirim via SMTP'];
            } catch (\Throwable $e) {
                Log::warning("Pengiriman email via SMTP gagal, mencoba fallback: " . $e->getMessage());
            }
        }

        // 2. Coba kirim via Google Apps Script Webhook (MailApp.sendEmail)
        $webhookUrl = config('services.google_sheets.webhook_url', env('GOOGLE_SHEETS_WEBHOOK_URL'));
        if (!empty($webhookUrl)) {
            try {
                $response = Http::withoutVerifying()->timeout(15)->post($webhookUrl, [
                    'action'  => 'send_email',
                    'to'      => $to,
                    'subject' => $subject,
                    'html'    => $htmlBody,
                    'text'    => $plainText,
                    'from'    => $fromName
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data) && ($data['success'] ?? false)) {
                        Log::info("Email berhasil dikirim via Google Apps Script ke: {$to} [Subject: {$subject}]");
                        return ['success' => true, 'driver' => 'google_apps_script', 'message' => 'Email berhasil dikirim via Google Apps Script'];
                    }
                }
                Log::info("Google Apps Script response: " . $response->body());
            } catch (\Throwable $e) {
                Log::warning("Pengiriman email via Google Apps Script gagal: " . $e->getMessage());
            }
        }

        // 3. Fallback terakhir: default Laravel Mail (misal log driver)
        try {
            Mail::html($htmlBody, function ($message) use ($to, $subject, $fromAddress, $fromName) {
                $message->to($to)
                    ->from($fromAddress, $fromName)
                    ->subject($subject);
            });
            Log::info("Email dicatat via fallback mailer ({$mailer}) ke: {$to}");
            return ['success' => true, 'driver' => $mailer, 'message' => "Email diproses via {$mailer}"];
        } catch (\Throwable $e) {
            Log::error("Gagal mengirim email ke {$to}: " . $e->getMessage());
            return ['success' => false, 'driver' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Template HTML Email Verifikasi Akun Baru
     */
    public static function buildVerificationHtml(string $nama, string $url): string
    {
        $appName = 'Ran CukurIn';
        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email - {$appName}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0f1012; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #0f1012; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" max-width="580" style="max-width: 580px; background-color: #1a1c22; border: 1px solid #2e323b; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background-color: #121316; padding: 32px 24px; border-bottom: 2px solid #e5a842;">
                            <div style="display: inline-block; background-color: #e5a842; color: #121316; padding: 8px 16px; border-radius: 10px; font-weight: 900; font-size: 18px; letter-spacing: 1px;">
                                ✂️ RAN CUKURIN
                            </div>
                            <h1 style="color: #ffffff; font-size: 20px; font-weight: 700; margin: 16px 0 0 0; letter-spacing: 0.5px;">Aktivasi Akun Pelanggan</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px 28px; text-align: left; line-height: 1.6;">
                            <p style="font-size: 16px; color: #ffffff; margin-top: 0;">Halo, <strong>{$nama}</strong>!</p>
                            <p style="font-size: 14px; color: #d1d5db; margin-bottom: 24px;">
                                Terima kasih telah mendaftar di <strong>{$appName} Barbershop</strong>. Untuk menyelesaikan pendaftaran dan memastikan keamanan akun reservasi Anda, silakan lakukan autentikasi dengan menekan tombol verifikasi di bawah ini:
                            </p>
                            <div style="text-align: center; margin: 32px 0;">
                                <a href="{$url}" style="background-color: #e5a842; color: #121316; font-size: 14px; font-weight: 800; text-decoration: none; padding: 14px 32px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 15px rgba(229,168,66,0.35); text-transform: uppercase; letter-spacing: 0.5px;">
                                    ✓ Verifikasi Alamat Email
                                </a>
                            </div>
                            <div style="background-color: #242730; border-left: 4px solid #e5a842; padding: 12px 16px; border-radius: 8px; margin: 24px 0;">
                                <p style="font-size: 12px; color: #9ca3af; margin: 0;">
                                    ⏰ Tautan verifikasi ini akan kedaluwarsa dalam <strong>60 menit</strong>. Jika Anda tidak dapat menekan tombol di atas, salin dan tempel URL berikut ke browser Anda:
                                </p>
                                <p style="font-size: 11px; color: #e5a842; word-break: break-all; margin: 8px 0 0 0;">
                                    {$url}
                                </p>
                            </div>
                            <p style="font-size: 12px; color: #6b7280; margin-top: 24px; margin-bottom: 0;">
                                Jika Anda tidak pernah merasa membuat akun di {$appName}, Anda dapat mengabaikan email ini dengan aman.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #121316; padding: 20px; border-top: 1px solid #2e323b; font-size: 11px; color: #6b7280;">
                            &copy; 2026 {$appName} Barbershop Bekasi. Hak Cipta Dilindungi.<br>
                            Sistem Booking Cukur Bebas Antre &amp; Manajemen Terpadu.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Template HTML Email Lupa Kata Sandi (Reset Password)
     */
    public static function buildResetPasswordHtml(string $nama, string $url): string
    {
        $appName = 'Ran CukurIn';
        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Ulang Kata Sandi - {$appName}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0f1012; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #0f1012; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" max-width="580" style="max-width: 580px; background-color: #1a1c22; border: 1px solid #2e323b; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background-color: #121316; padding: 32px 24px; border-bottom: 2px solid #e5a842;">
                            <div style="display: inline-block; background-color: #e5a842; color: #121316; padding: 8px 16px; border-radius: 10px; font-weight: 900; font-size: 18px; letter-spacing: 1px;">
                                🔑 RAN CUKURIN
                            </div>
                            <h1 style="color: #ffffff; font-size: 20px; font-weight: 700; margin: 16px 0 0 0; letter-spacing: 0.5px;">Permintaan Reset Kata Sandi</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px 28px; text-align: left; line-height: 1.6;">
                            <p style="font-size: 16px; color: #ffffff; margin-top: 0;">Halo, <strong>{$nama}</strong>!</p>
                            <p style="font-size: 14px; color: #d1d5db; margin-bottom: 24px;">
                                Kami menerima permintaan untuk mengatur ulang kata sandi akun <strong>{$appName}</strong> Anda. Silakan tekan tombol di bawah ini untuk membuat kata sandi baru:
                            </p>
                            <div style="text-align: center; margin: 32px 0;">
                                <a href="{$url}" style="background-color: #e5a842; color: #121316; font-size: 14px; font-weight: 800; text-decoration: none; padding: 14px 32px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 15px rgba(229,168,66,0.35); text-transform: uppercase; letter-spacing: 0.5px;">
                                    🔒 Buat Kata Sandi Baru
                                </a>
                            </div>
                            <div style="background-color: #242730; border-left: 4px solid #e5a842; padding: 12px 16px; border-radius: 8px; margin: 24px 0;">
                                <p style="font-size: 12px; color: #9ca3af; margin: 0;">
                                    ⏰ Tautan pemulihan ini aman dan hanya berlaku selama <strong>60 menit</strong>. Jika Anda tidak dapat menekan tombol di atas, buka tautan berikut secara langsung:
                                </p>
                                <p style="font-size: 11px; color: #e5a842; word-break: break-all; margin: 8px 0 0 0;">
                                    {$url}
                                </p>
                            </div>
                            <p style="font-size: 12px; color: #6b7280; margin-top: 24px; margin-bottom: 0;">
                                ⚠️ Jika Anda tidak merasa meminta reset kata sandi, abaikan email ini. Kata sandi akun Anda tetap aman dan tidak akan berubah tanpa konfirmasi Anda.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #121316; padding: 20px; border-top: 1px solid #2e323b; font-size: 11px; color: #6b7280;">
                            &copy; 2026 {$appName} Barbershop Bekasi. Hak Cipta Dilindungi.<br>
                            Sistem Booking Cukur Bebas Antre &amp; Manajemen Terpadu.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
