<?php

namespace App\Services;

use App\Models\AntreanPemesanan;
use App\Models\Barang;
use App\Models\Notifikasi;
use App\Models\Ulasan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Dapatkan daftar notifikasi cerdas terpisah sesuai Role dan User ID spesifik.
     */
    public static function getForUser(?User $user): array
    {
        if (!$user) {
            return [];
        }

        $notifications = [];
        $readAt = session('notifications_read_at_' . $user->id, Carbon::createFromTimestamp(0));

        try {
            if ($user->role === 'Pelanggan') {
                // ==========================================
                // 1. NOTIFIKASI KHUSUS PELANGGAN INI SAJA
                // ==========================================
                $antreans = AntreanPemesanan::with(['layanan', 'kapster', 'ulasan'])
                    ->where('pelanggan_id', $user->id)
                    ->orderBy('updated_at', 'desc')
                    ->take(6)
                    ->get();

                foreach ($antreans as $a) {
                    $time = $a->updated_at ?? $a->created_at ?? now();
                    $isUnread = $time->greaterThan($readAt);
                    $layananNama = $a->layanan->nama_layanan ?? 'Layanan Grooming';
                    $kapsterNama = $a->kapster->nama ?? 'Bebas / Tersedia';

                    if ($a->status === 'Menunggu') {
                        $sisa = $a->sisa_antrean_di_depan;
                        $pesanTunggu = $sisa > 0 ? "Sisa $sisa antrean di depan Anda." : "Siap-siap, giliran Anda berikutnya!";
                        $notifications[] = [
                            'id' => 'pelanggan_antrean_' . $a->id,
                            'judul' => "Antrean #RNC-" . str_pad($a->id, 5, '0', STR_PAD_LEFT) . " Menunggu",
                            'pesan' => "$layananNama dengan Kapster $kapsterNama. $pesanTunggu",
                            'url' => route('customer.tiket', $a->id),
                            'waktu' => $time->diffForHumans(),
                            'icon' => 'schedule',
                            'color' => 'text-amber-400',
                            'bg' => 'bg-amber-500/10',
                            'is_unread' => $isUnread,
                            'timestamp' => $time->timestamp,
                        ];
                    } elseif ($a->status === 'Diproses') {
                        $notifications[] = [
                            'id' => 'pelanggan_antrean_' . $a->id,
                            'judul' => "Giliran Anda Sedang Berlangsung!",
                            'pesan' => "Layanan $layananNama sedang dikerjakan oleh $kapsterNama.",
                            'url' => route('customer.tiket', $a->id),
                            'waktu' => $time->diffForHumans(),
                            'icon' => 'content_cut',
                            'color' => 'text-blue-400',
                            'bg' => 'bg-blue-500/10',
                            'is_unread' => $isUnread,
                            'timestamp' => $time->timestamp,
                        ];
                    } elseif ($a->status === 'Selesai') {
                        $hasReviewed = (bool) $a->ulasan;
                        $notifications[] = [
                            'id' => 'pelanggan_antrean_' . $a->id,
                            'judul' => "Cukur Selesai - #RNC-" . str_pad($a->id, 5, '0', STR_PAD_LEFT),
                            'pesan' => $hasReviewed 
                                ? "Terima kasih! Anda telah memberi ulasan $layananNama." 
                                : "Layanan selesai. Beri ulasan pengalaman Anda bersama $kapsterNama!",
                            'url' => $hasReviewed ? route('customer.riwayat') : route('customer.ulasan', $a->id),
                            'waktu' => $time->diffForHumans(),
                            'icon' => 'check_circle',
                            'color' => 'text-emerald-400',
                            'bg' => 'bg-emerald-500/10',
                            'is_unread' => $isUnread,
                            'timestamp' => $time->timestamp,
                        ];
                    } elseif ($a->status === 'Batal') {
                        $notifications[] = [
                            'id' => 'pelanggan_antrean_' . $a->id,
                            'judul' => "Booking Dibatalkan",
                            'pesan' => "Pemesanan antrean #RNC-" . str_pad($a->id, 5, '0', STR_PAD_LEFT) . " telah dibatalkan.",
                            'url' => route('customer.riwayat'),
                            'waktu' => $time->diffForHumans(),
                            'icon' => 'cancel',
                            'color' => 'text-rose-400',
                            'bg' => 'bg-rose-500/10',
                            'is_unread' => $isUnread,
                            'timestamp' => $time->timestamp,
                        ];
                    }
                }

                // Notifikasi custom pesan promo/sistem
                $customNotifs = Notifikasi::where('user_id', $user->id)->latest()->take(3)->get();
                foreach ($customNotifs as $cn) {
                    $notifications[] = [
                        'id' => 'custom_' . $cn->id,
                        'judul' => $cn->judul,
                        'pesan' => $cn->pesan,
                        'url' => route('customer.dashboard'),
                        'waktu' => $cn->created_at->diffForHumans(),
                        'icon' => 'notifications',
                        'color' => 'text-primary',
                        'bg' => 'bg-primary/10',
                        'is_unread' => !$cn->is_read && $cn->created_at->greaterThan($readAt),
                        'timestamp' => $cn->created_at->timestamp,
                    ];
                }

            } elseif ($user->role === 'Kapster') {
                // ==========================================
                // 2. NOTIFIKASI KHUSUS KAPSTER INI SAJA
                // ==========================================
                $antreans = AntreanPemesanan::with(['pelanggan', 'layanan'])
                    ->where(function($q) use ($user) {
                        $q->where('kapster_id', $user->id)
                          ->orWhereNull('kapster_id');
                    })
                    ->orderBy('updated_at', 'desc')
                    ->take(6)
                    ->get();

                foreach ($antreans as $a) {
                    $time = $a->updated_at ?? $a->created_at ?? now();
                    $isUnread = $time->greaterThan($readAt);
                    $namaPelanggan = $a->pelanggan->nama ?? 'Pelanggan Walk-In';
                    $layananNama = $a->layanan->nama_layanan ?? 'Layanan';

                    if ($a->status === 'Menunggu') {
                        $notifications[] = [
                            'id' => 'kapster_antrean_' . $a->id,
                            'judul' => "Order Antrean: $namaPelanggan",
                            'pesan' => "#RNC-" . str_pad($a->id, 5, '0', STR_PAD_LEFT) . " - $layananNama (" . substr($a->waktu_estimasi_mulai ?? '09:00', 0, 5) . " WIB)",
                            'url' => route('staff.dashboard'),
                            'waktu' => $time->diffForHumans(),
                            'icon' => 'assignment_ind',
                            'color' => 'text-amber-400',
                            'bg' => 'bg-amber-500/10',
                            'is_unread' => $isUnread,
                            'timestamp' => $time->timestamp,
                        ];
                    } elseif ($a->status === 'Diproses') {
                        $notifications[] = [
                            'id' => 'kapster_antrean_' . $a->id,
                            'judul' => "Sedang Dikerjakan: $namaPelanggan",
                            'pesan' => "Layanan $layananNama sedang Anda proses.",
                            'url' => route('staff.dashboard'),
                            'waktu' => $time->diffForHumans(),
                            'icon' => 'content_cut',
                            'color' => 'text-blue-400',
                            'bg' => 'bg-blue-500/10',
                            'is_unread' => $isUnread,
                            'timestamp' => $time->timestamp,
                        ];
                    } elseif ($a->status === 'Selesai') {
                        $notifications[] = [
                            'id' => 'kapster_antrean_' . $a->id,
                            'judul' => "Pekerjaan Selesai: $namaPelanggan",
                            'pesan' => "#RNC-" . str_pad($a->id, 5, '0', STR_PAD_LEFT) . " - $layananNama telah selesai.",
                            'url' => route('staff.dashboard'),
                            'waktu' => $time->diffForHumans(),
                            'icon' => 'check_circle',
                            'color' => 'text-emerald-400',
                            'bg' => 'bg-emerald-500/10',
                            'is_unread' => $isUnread,
                            'timestamp' => $time->timestamp,
                        ];
                    }
                }

                // Ulasan dari pelanggan khusus untuk kapster ini
                $ulasans = Ulasan::with('pelanggan')
                    ->where('kapster_id', $user->id)
                    ->latest()
                    ->take(3)
                    ->get();

                foreach ($ulasans as $u) {
                    $time = $u->created_at ?? now();
                    $isUnread = $time->greaterThan($readAt);
                    $notifications[] = [
                        'id' => 'kapster_ulasan_' . $u->id,
                        'judul' => "Ulasan Baru: {$u->rating}⭐",
                        'pesan' => "Dari " . ($u->pelanggan->nama ?? 'Pelanggan') . ": \"" . Str::limit($u->komentar ?: 'Memberikan rating ' . $u->rating . ' bintang', 40) . "\"",
                        'url' => route('staff.dashboard'),
                        'waktu' => $time->diffForHumans(),
                        'icon' => 'star',
                        'color' => 'text-primary',
                        'bg' => 'bg-primary/10',
                        'is_unread' => $isUnread,
                        'timestamp' => $time->timestamp,
                    ];
                }

            } elseif ($user->role === 'Pemilik') {
                // ==========================================
                // 3. NOTIFIKASI KHUSUS PEMILIK (OWNER)
                // ==========================================
                // Booking terbaru barbershop
                $latestBookings = AntreanPemesanan::with(['pelanggan', 'kapster', 'layanan'])
                    ->latest()
                    ->take(3)
                    ->get();

                foreach ($latestBookings as $b) {
                    $time = $b->created_at ?? now();
                    $isUnread = $time->greaterThan($readAt);
                    $notifications[] = [
                        'id' => 'owner_booking_' . $b->id,
                        'judul' => "Booking: " . ($b->pelanggan->nama ?? 'Pelanggan Walk-In'),
                        'pesan' => ($b->layanan->nama_layanan ?? 'Layanan') . " &bull; Kapster: " . ($b->kapster->nama ?? 'Bebas') . " (" . $b->status . ")",
                        'url' => route('admin.antrean'),
                        'waktu' => $time->diffForHumans(),
                        'icon' => 'book_online',
                        'color' => 'text-blue-400',
                        'bg' => 'bg-blue-500/10',
                        'is_unread' => $isUnread,
                        'timestamp' => $time->timestamp,
                    ];
                }

                // Peringatan stok menipis
                $lowStocks = Barang::whereColumn('stok_sekarang', '<=', 'batas_minimum')->take(3)->get();
                foreach ($lowStocks as $s) {
                    $time = $s->updated_at ?? now();
                    $isUnread = $time->greaterThan($readAt);
                    $notifications[] = [
                        'id' => 'owner_stock_' . $s->id,
                        'judul' => "Stok Menipis: {$s->nama_barang}",
                        'pesan' => "Sisa stok {$s->stok_sekarang} unit (Batas minimum: {$s->batas_minimum})",
                        'url' => route('admin.barang'),
                        'waktu' => $time->diffForHumans(),
                        'icon' => 'warning',
                        'color' => 'text-amber-400',
                        'bg' => 'bg-amber-500/10',
                        'is_unread' => $isUnread,
                        'timestamp' => $time->timestamp,
                    ];
                }

                // Ulasan pelanggan terbaru
                $latestReviews = Ulasan::with(['pelanggan', 'kapster'])->latest()->take(2)->get();
                foreach ($latestReviews as $r) {
                    $time = $r->created_at ?? now();
                    $isUnread = $time->greaterThan($readAt);
                    $notifications[] = [
                        'id' => 'owner_review_' . $r->id,
                        'judul' => "Ulasan Masuk: {$r->rating}⭐",
                        'pesan' => ($r->pelanggan->nama ?? 'Pelanggan') . " untuk " . ($r->kapster->nama ?? 'Kapster') . ": \"" . Str::limit($r->komentar ?: 'Rating ' . $r->rating . ' bintang', 35) . "\"",
                        'url' => route('admin.kinerja'),
                        'waktu' => $time->diffForHumans(),
                        'icon' => 'rate_review',
                        'color' => 'text-emerald-400',
                        'bg' => 'bg-emerald-500/10',
                        'is_unread' => $isUnread,
                        'timestamp' => $time->timestamp,
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Error generating notifications: ' . $e->getMessage());
        }

        // Urutkan berdasarkan waktu paling baru
        usort($notifications, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return array_slice($notifications, 0, 8);
    }
}
