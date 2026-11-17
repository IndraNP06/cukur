<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect('/home');
});
Volt::route('/home', 'home')->name('home');
Volt::route('/katalog', 'katalog')->name('katalog');
Volt::route('/faq', 'faq')->name('faq');

Volt::route('/login', 'auth.login')->name('login')->middleware('guest');
Volt::route('/register', 'auth.register')->name('register')->middleware('guest');
Volt::route('/forgot-password', 'auth.forgot-password')->name('password.request')->middleware('guest');
Volt::route('/reset-password/{token}', 'auth.reset-password')->name('password.reset')->middleware('guest');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        if ($user->role === 'Pemilik') {
            return redirect()->route('admin.dashboard');
        } elseif ($user->role === 'Kapster') {
            return redirect()->route('staff.dashboard');
        }
        return redirect()->route('customer.dashboard');
    })->name('dashboard');
    
    Volt::route('/admin/dashboard', 'admin.dashboard')->name('admin.dashboard');
    Volt::route('/staff/dashboard', 'staff.dashboard')->name('staff.dashboard');
    Volt::route('/admin/layanan', 'admin.layanan')->name('admin.layanan');
    Volt::route('/admin/barang', 'admin.barang')->name('admin.barang');
    Volt::route('/admin/voucher', 'admin.voucher')->name('admin.voucher');
    Volt::route('/admin/stok', 'admin.stok')->name('admin.stok');
    Volt::route('/admin/antrean', 'admin.antrean')->name('admin.antrean');
    Volt::route('/admin/staf', 'admin.staf')->name('admin.staf');
    Volt::route('/admin/pelanggan', 'admin.pelanggan')->name('admin.pelanggan');
    Volt::route('/admin/kategori', 'admin.kategori')->name('admin.kategori');
    Volt::route('/admin/jadwal', 'admin.jadwal')->name('admin.jadwal');
    Volt::route('/admin/absensi', 'admin.absensi')->name('admin.absensi');
    Volt::route('/admin/pengeluaran', 'admin.pengeluaran')->name('admin.pengeluaran');
    Volt::route('/admin/konten', 'admin.konten')->name('admin.konten');
    Volt::route('/admin/pengaturan', 'admin.pengaturan')->name('admin.pengaturan');
    Volt::route('/staff/absensi', 'staff.absensi')->name('staff.absensi');
    Volt::route('/admin/jadwal', 'admin.jadwal')->name('admin.jadwal');
    Volt::route('/admin/kinerja', 'admin.kinerja')->name('admin.kinerja');
    Volt::route('/pelanggan/dashboard', 'customer.dashboard')->name('customer.dashboard');
    Volt::route('/pelanggan/riwayat', 'customer.riwayat')->name('customer.riwayat');
    Volt::route('/pelanggan/profil', 'customer.profil')->name('customer.profil');
    Volt::route('/pelanggan/tiket/{id}', 'customer.tiket-antrean')->name('customer.tiket');
    Volt::route('/pelanggan/ulasan/{id}', 'customer.ulasan')->name('customer.ulasan');
    
    // Route::get('/booking', [App\Http\Controllers\BookingController::class, 'index'])->name('booking.old');
    Volt::route('/admin/pos/{antrean_id}', 'admin.pos')->name('admin.pos');
    Volt::route('/admin/laporan-keuangan', 'admin.laporan-pendapatan')->name('admin.laporan');

    Route::post('/notifikasi/mark-all-read', function () {
        if (auth()->check()) {
            session()->put('notifications_read_at_' . auth()->id(), now());
            \App\Models\Notifikasi::where('user_id', auth()->id())->update(['is_read' => true]);
        }
        return response()->json(['success' => true]);
    })->name('notifikasi.mark-read');

    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    })->name('logout');
});

// Public / Customer routes
Volt::route('/booking', 'booking.wizard')->name('booking.wizard');

// Sinkronisasi manual dari Google Spreadsheet
Route::get('/sheets/pull', function (\App\Services\GoogleSheetsService $sheetsService) {
    $results = [];
    foreach ($sheetsService->tables as $table) {
        $results[$table] = $sheetsService->pullTable($table);
    }
    return response()->json([
        'status'  => 'success',
        'message' => 'Sinkronisasi tarik data dari Google Spreadsheet selesai.',
        'details' => $results
    ]);
});

// Sinkronisasi manual kirim database ke Google Spreadsheet
Route::get('/sheets/push', function (\App\Services\GoogleSheetsService $sheetsService) {
    $results = [];
    foreach ($sheetsService->tables as $table) {
        $results[$table] = $sheetsService->syncTable($table);
    }
    return response()->json([
        'status'  => 'success',
        'message' => 'Sinkronisasi kirim seluruh data ke Google Spreadsheet selesai.',
        'details' => $results
    ]);
});

// Auth routes
