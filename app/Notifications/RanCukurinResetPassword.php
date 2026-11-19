<?php

namespace App\Notifications;

use App\Services\EmailNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RanCukurinResetPassword extends Notification
{
    use Queueable;

    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via($notifiable): array
    {
        return ['custom_email'];
    }

    /**
     * Kirim notifikasi reset password via EmailNotificationService
     */
    public function toCustomEmail($notifiable)
    {
        $url = $this->resetUrl($notifiable);
        $nama = $notifiable->nama ?? $notifiable->name ?? 'Pelanggan Ran CukurIn';
        $html = EmailNotificationService::buildResetPasswordHtml($nama, $url);

        return EmailNotificationService::send(
            $notifiable->getEmailForPasswordReset(),
            'Atur Ulang Kata Sandi Akun - Ran CukurIn',
            $html
        );
    }

    /**
     * Fallback standard mail message
     */
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi Akun - Ran CukurIn')
            ->greeting('Halo, ' . ($notifiable->nama ?? 'Pelanggan Ran CukurIn') . '!')
            ->line('Anda menerima email ini karena kami menerima permintaan reset kata sandi untuk akun Anda.')
            ->action('Atur Ulang Kata Sandi', $url)
            ->line('Tautan reset kata sandi ini akan kedaluwarsa dalam 60 menit.')
            ->line('Jika Anda tidak meminta reset kata sandi, tidak ada tindakan lebih lanjut yang diperlukan.');
    }

    /**
     * Buat URL reset kata sandi
     */
    protected function resetUrl($notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
