<?php

namespace App\Notifications;

use App\Services\EmailNotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class RanCukurinVerifyEmail extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['custom_email'];
    }

    /**
     * Kirim notifikasi menggunakan EmailNotificationService
     */
    public function toCustomEmail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $nama = $notifiable->nama ?? $notifiable->name ?? 'Pelanggan Ran CukurIn';
        $html = EmailNotificationService::buildVerificationHtml($nama, $verificationUrl);

        // Simpan ke session dan cache agar bisa diakses langsung di layar (Mode Cepat)
        session()->put('direct_verification_url', $verificationUrl);
        try {
            cache()->put('direct_verification_url_' . $notifiable->getEmailForVerification(), $verificationUrl, now()->addMinutes(60));
        } catch (\Throwable $e) {}

        return EmailNotificationService::send(
            $notifiable->getEmailForVerification(),
            'Verifikasi Alamat Email Anda - Ran CukurIn',
            $html
        );
    }

    /**
     * Fallback standard mail message jika via mail
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifikasi Alamat Email Anda - Ran CukurIn')
            ->greeting('Halo, ' . ($notifiable->nama ?? 'Pelanggan Ran CukurIn') . '!')
            ->line('Terima kasih telah mendaftar di Ran CukurIn. Silakan klik tombol di bawah untuk memverifikasi alamat email Anda:')
            ->action('Verifikasi Alamat Email', $verificationUrl)
            ->line('Tautan verifikasi ini akan kedaluwarsa dalam 60 menit.')
            ->line('Jika Anda tidak membuat akun, abaikan email ini.');
    }

    /**
     * Buat URL verifikasi bertanda tangan (signed URL)
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
