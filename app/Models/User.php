<?php
namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles;

    protected $table = 'pengguna';

    protected $fillable = [
        'nama',
        'email',
        'email_verified_at',
        'password',
        'no_hp',
        'role',
        'foto',
    ];

    public function getFotoUrlAttribute(): string
    {
        if (!empty($this->foto)) {
            if (str_starts_with($this->foto, 'http://') || str_starts_with($this->foto, 'https://')) {
                return $this->foto;
            }
            return asset(ltrim($this->foto, '/'));
        }

        return asset('images/kapster/default.jpg');
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Kirim notifikasi verifikasi email dengan template kustom Ran CukurIn
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\RanCukurinVerifyEmail);
    }

    /**
     * Kirim notifikasi reset password dengan template kustom Ran CukurIn
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\RanCukurinResetPassword($token));
    }
}
