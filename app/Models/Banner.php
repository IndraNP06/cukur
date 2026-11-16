<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'judul',
        'gambar_url',
        'link',
        'urutan',
        'is_active',
    ];

    public function getUrlAttribute(): string
    {
        if (!empty($this->gambar_url)) {
            if (str_starts_with($this->gambar_url, 'http://') || str_starts_with($this->gambar_url, 'https://')) {
                return $this->gambar_url;
            }
            return asset(ltrim($this->gambar_url, '/'));
        }

        return asset('images/banners/default.jpg');
    }
}
