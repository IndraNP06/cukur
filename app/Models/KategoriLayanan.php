<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriLayanan extends Model
{
    use SoftDeletes;
    protected $fillable = ['nama_kategori', 'deskripsi', 'is_active'];
}

