<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Faker\Factory as Faker;
use App\Models\User;
use App\Models\KategoriLayanan;
use App\Models\Layanan;
use App\Models\KategoriBarang;
use App\Models\Barang;
use App\Models\JadwalShift;
use App\Models\Voucher;
use App\Models\AntreanPemesanan;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Ulasan;
use App\Models\LogStok;
use App\Models\Galeri;
use App\Models\Pengaturan;
use App\Models\Notifikasi;
use App\Models\Pengeluaran;
use App\Models\Absensi;
use App\Models\Banner;
use App\Models\Faq;

class DataDummyBaruSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('id_ID');

        DB::transaction(function () use ($faker) {
            // 1. Users
            $users = [];
            // Pemilik (admin already created in RoleSeeder, but let's add one more just in case)
            $users['Pemilik'] = User::create([
                'nama' => 'Bapak Owner',
                'email' => 'owner2@rancukurin.com',
                'password' => Hash::make('password123'),
                'no_hp' => '081111111111',
                'role' => 'Pemilik',
            ]);
            $users['Pemilik']->assignRole('Pemilik');

            // Kapster
            $kapsterFotos = [
                '/images/kapster/kapster-1.jpg',
                '/images/kapster/kapster-2.jpg',
                '/images/kapster/kapster-3.jpg',
            ];
            for ($i = 1; $i <= 3; $i++) {
                $kapster = User::create([
                    'nama' => "Kapster " . $faker->firstName,
                    'email' => "kapster{$i}@rancukurin.com",
                    'password' => Hash::make('password123'),
                    'no_hp' => "0852" . rand(10000000, 99999999),
                    'role' => 'Kapster',
                    'foto' => $kapsterFotos[$i - 1] ?? '/images/kapster/default.jpg',
                ]);
                $kapster->assignRole('Kapster');
                $users['Kapster'][] = $kapster;
            }

            // Pelanggan
            for ($i = 1; $i <= 10; $i++) {
                $pelanggan = User::create([
                    'nama' => $faker->name,
                    'email' => "pelanggan{$i}@example.com",
                    'password' => Hash::make('password123'),
                    'no_hp' => "0812" . rand(10000000, 99999999),
                    'role' => 'Pelanggan',
                ]);
                $pelanggan->assignRole('Pelanggan');
                $users['Pelanggan'][] = $pelanggan;
            }

            // 2. Kategori Layanan & Layanan
            $katLayanan1 = KategoriLayanan::create(['nama_kategori' => 'Haircut', 'deskripsi' => 'Potong rambut pria dan anak', 'is_active' => true]);
            $katLayanan2 = KategoriLayanan::create(['nama_kategori' => 'Treatment', 'deskripsi' => 'Perawatan rambut dan wajah', 'is_active' => true]);

            $layanans = [];
            $layanans[] = Layanan::create(['nama_layanan' => 'Premium Haircut', 'deskripsi' => 'Potong rambut + Cuci + Styling + Pijat', 'harga' => 70000, 'estimasi_durasi_menit' => 45, 'is_active' => true, 'gambar' => '/images/layanan/premium-haircut.jpg']);
            $layanans[] = Layanan::create(['nama_layanan' => 'Basic Haircut', 'deskripsi' => 'Potong rambut standar', 'harga' => 40000, 'estimasi_durasi_menit' => 30, 'is_active' => true, 'gambar' => '/images/layanan/basic-haircut.jpg']);
            $layanans[] = Layanan::create(['nama_layanan' => 'Kids Haircut', 'deskripsi' => 'Potong rambut anak usia di bawah 12 tahun', 'harga' => 35000, 'estimasi_durasi_menit' => 30, 'is_active' => true, 'gambar' => '/images/layanan/kids-haircut.jpg']);
            $layanans[] = Layanan::create(['nama_layanan' => 'Shaving', 'deskripsi' => 'Cukur kumis dan jenggot rapi', 'harga' => 20000, 'estimasi_durasi_menit' => 15, 'is_active' => true, 'gambar' => '/images/layanan/shaving.jpg']);
            $layanans[] = Layanan::create(['nama_layanan' => 'Creambath', 'deskripsi' => 'Perawatan rambut rontok/ketombe', 'harga' => 50000, 'estimasi_durasi_menit' => 45, 'is_active' => true, 'gambar' => '/images/layanan/creambath.jpg']);
            $layanans[] = Layanan::create(['nama_layanan' => 'Hair Coloring', 'deskripsi' => 'Pewarnaan rambut', 'harga' => 150000, 'estimasi_durasi_menit' => 90, 'is_active' => true, 'gambar' => '/images/layanan/hair-coloring.jpg']);

            // 3. Kategori Barang & Barang
            $katBarang1 = KategoriBarang::create(['nama_kategori' => 'Pomade & Styling', 'deskripsi' => 'Produk styling rambut', 'is_active' => true]);
            $katBarang2 = KategoriBarang::create(['nama_kategori' => 'Perawatan', 'deskripsi' => 'Shampoo, Tonic, dsb', 'is_active' => true]);

            $barangs = [];
            $barangs[] = Barang::create(['nama_barang' => 'Pomade Oil Based', 'tipe' => 'ProdukJual', 'stok_sekarang' => 15, 'batas_minimum' => 5, 'harga_beli' => 50000, 'harga_jual' => 85000, 'gambar' => '/images/barang/pomade-oil.jpg']);
            $barangs[] = Barang::create(['nama_barang' => 'Pomade Water Based', 'tipe' => 'ProdukJual', 'stok_sekarang' => 20, 'batas_minimum' => 5, 'harga_beli' => 60000, 'harga_jual' => 95000, 'gambar' => '/images/barang/pomade-water.jpg']);
            $barangs[] = Barang::create(['nama_barang' => 'Hair Tonic Ginseng', 'tipe' => 'BahanBaku', 'stok_sekarang' => 5, 'batas_minimum' => 2, 'harga_beli' => 45000, 'harga_jual' => 0, 'gambar' => '/images/barang/hair-tonic.jpg']);
            $barangs[] = Barang::create(['nama_barang' => 'Shampoo Menthol', 'tipe' => 'BahanBaku', 'stok_sekarang' => 10, 'batas_minimum' => 3, 'harga_beli' => 30000, 'harga_jual' => 0, 'gambar' => '/images/barang/shampoo.jpg']);
            $barangs[] = Barang::create(['nama_barang' => 'Sisir Saku', 'tipe' => 'ProdukJual', 'stok_sekarang' => 30, 'batas_minimum' => 10, 'harga_beli' => 10000, 'harga_jual' => 20000, 'gambar' => '/images/barang/sisir.jpg']);

            // 4. Jadwal Shift
            $today = Carbon::today();
            foreach ($users['Kapster'] as $index => $kapster) {
                for ($day = 0; $day < 7; $day++) {
                    JadwalShift::create([
                        'kapster_id' => $kapster->id,
                        'tanggal' => $today->copy()->addDays($day),
                        'jam_mulai' => '09:00:00',
                        'jam_selesai' => '21:00:00',
                        'status' => 'Hadir'
                    ]);
                }
            }

            // 5. Voucher
            $vouchers = [];
            $vouchers[] = Voucher::create(['kode_voucher' => 'WELCOME2026', 'tipe_diskon' => 'Nominal', 'nilai_diskon' => 15000, 'kuota_maksimal' => 100, 'kuota_terpakai' => 0, 'tgl_mulai' => $today->copy()->subDays(5), 'tgl_berakhir' => $today->copy()->addDays(30), 'is_active' => true]);
            $vouchers[] = Voucher::create(['kode_voucher' => 'DISC10PERSEN', 'tipe_diskon' => 'Persen', 'nilai_diskon' => 10, 'kuota_maksimal' => 50, 'kuota_terpakai' => 0, 'tgl_mulai' => $today->copy()->subDays(2), 'tgl_berakhir' => $today->copy()->addDays(15), 'is_active' => true]);

            // 6. Transaksi & Antrean
            for ($i = 1; $i <= 30; $i++) {
                $randomPelanggan = $users['Pelanggan'][array_rand($users['Pelanggan'])];
                $randomKapster = $users['Kapster'][array_rand($users['Kapster'])];
                $randomLayanan = $layanans[array_rand($layanans)];
                
                $isPast = $i <= 25; // 25 past, 5 future/current
                
                if ($isPast) {
                    $randomDate = Carbon::now()->subDays(rand(1, 14))->setTime(rand(9, 19), rand(0, 59), 0);
                    $statusAntrean = 'Selesai';
                } else {
                    $randomDate = Carbon::today()->setTime(rand(9, 19), rand(0, 59), 0);
                    $statusAntrean = ($i === 30) ? 'Diproses' : 'Menunggu';
                }

                $antrean = AntreanPemesanan::create([
                    'pelanggan_id' => $randomPelanggan->id,
                    'kapster_id' => $randomKapster->id,
                    'layanan_id' => $randomLayanan->id,
                    'tipe_antrean' => rand(0, 1) ? 'Online' : 'WalkIn',
                    'nomor_antrean' => ($isPast ? 'P' : 'C') . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'tanggal' => $randomDate->toDateString(),
                    'waktu_estimasi_mulai' => $randomDate->format('H:i:s'),
                    'waktu_mulai_aktual' => ($isPast || $statusAntrean === 'Diproses') ? $randomDate->copy()->addMinutes(rand(0, 10))->format('H:i:s') : null,
                    'waktu_selesai_aktual' => $isPast ? $randomDate->copy()->addMinutes($randomLayanan->estimasi_durasi_menit)->format('H:i:s') : null,
                    'status' => $statusAntrean,
                ]);

                if ($isPast) {
                    $buyProduct = rand(1, 100) > 70; // 30% chance buy product
                    $randomBarang = $buyProduct ? $barangs[array_rand($barangs)] : null;
                    if ($randomBarang && $randomBarang->tipe == 'BahanBaku') $randomBarang = null;

                    $subtotal = $randomLayanan->harga + ($randomBarang ? $randomBarang->harga_jual : 0);
                    $useVoucher = rand(1, 100) > 80; // 20% chance use voucher
                    $diskon = 0;
                    $appliedVoucher = null;

                    if ($useVoucher) {
                        $appliedVoucher = $vouchers[array_rand($vouchers)];
                        if ($appliedVoucher->tipe_diskon == 'Nominal') {
                            $diskon = $appliedVoucher->nilai_diskon;
                        } else {
                            $diskon = ($subtotal * $appliedVoucher->nilai_diskon) / 100;
                        }
                    }

                    $totalAkhir = $subtotal - $diskon;

                    $transaksi = Transaksi::create([
                        'antrean_id' => $antrean->id,
                        'kasir_id' => $users['Pemilik']->id,
                        'voucher_id' => $appliedVoucher ? $appliedVoucher->id : null,
                        'subtotal' => $subtotal,
                        'total_diskon' => $diskon,
                        'total_akhir' => $totalAkhir,
                        'metode_pembayaran' => rand(0, 1) ? 'Cash' : 'QRIS',
                        'status_bayar' => 'Lunas',
                        'tanggal_bayar' => $randomDate,
                    ]);

                    DetailTransaksi::create([
                        'transaksi_id' => $transaksi->id,
                        'tipe_item' => 'Layanan',
                        'item_id' => $randomLayanan->id,
                        'nama_item_snapshot' => $randomLayanan->nama_layanan,
                        'qty' => 1,
                        'harga_satuan_snapshot' => $randomLayanan->harga,
                        'subtotal' => $randomLayanan->harga,
                    ]);

                    if ($randomBarang) {
                        DetailTransaksi::create([
                            'transaksi_id' => $transaksi->id,
                            'tipe_item' => 'Barang',
                            'item_id' => $randomBarang->id,
                            'nama_item_snapshot' => $randomBarang->nama_barang,
                            'qty' => 1,
                            'harga_satuan_snapshot' => $randomBarang->harga_jual,
                            'subtotal' => $randomBarang->harga_jual,
                        ]);

                        $randomBarang->decrement('stok_sekarang', 1);

                        LogStok::create([
                            'barang_id' => $randomBarang->id,
                            'pencatat_id' => $users['Pemilik']->id,
                            'referensi_transaksi_id' => $transaksi->id,
                            'tipe_log' => 'OtomatisPOS',
                            'qty_perubahan' => -1,
                            'stok_akhir' => $randomBarang->stok_sekarang,
                            'keterangan' => 'Terjual pada transaksi ' . $transaksi->id,
                        ]);
                    }

                    if (rand(1, 100) > 30) { // 70% rating
                        Ulasan::create([
                            'antrean_id' => $antrean->id,
                            'pelanggan_id' => $randomPelanggan->id,
                            'kapster_id' => $randomKapster->id,
                            'rating' => rand(4, 5),
                            'komentar' => $faker->sentence(),
                        ]);
                    }
                }
            }

            // 7. Pengaturan
            $pengaturans = [
                ['key' => 'nama_toko', 'value' => 'Ran Cukurin Premium Barbershop'],
                ['key' => 'alamat_toko', 'value' => 'Jl. Jendral Sudirman No. 123, Jakarta'],
                ['key' => 'no_telp', 'value' => '081234567890'],
                ['key' => 'jam_buka', 'value' => '09:00 - 21:00'],
            ];
            foreach ($pengaturans as $p) Pengaturan::create($p);

            // 8. Galeri
            Galeri::create(['judul' => 'Potongan Fade Modern', 'gambar_url' => '/images/gallery/fade-modern.jpg', 'tipe' => 'Rambut', 'is_active' => true]);
            Galeri::create(['judul' => 'French Crop Style', 'gambar_url' => '/images/gallery/french-crop.jpg', 'tipe' => 'Rambut', 'is_active' => true]);
            Galeri::create(['judul' => 'Modern Pompadour', 'gambar_url' => '/images/gallery/pompadour.jpg', 'tipe' => 'Rambut', 'is_active' => true]);
            Galeri::create(['judul' => 'Clean Hairline Edge-up', 'gambar_url' => '/images/gallery/edge-up.jpg', 'tipe' => 'Rambut', 'is_active' => true]);
            Galeri::create(['judul' => 'Interior Barbershop Nyaman', 'gambar_url' => '/images/gallery/interior-1.jpg', 'tipe' => 'Interior', 'is_active' => true]);
            Galeri::create(['judul' => 'Ruang Tunggu & Kursi Vintage', 'gambar_url' => '/images/gallery/interior-2.jpg', 'tipe' => 'Interior', 'is_active' => true]);

            // 9. Notifikasi
            foreach ($users['Pelanggan'] as $p) {
                Notifikasi::create([
                    'user_id' => $p->id,
                    'judul' => 'Selamat Datang!',
                    'pesan' => 'Terima kasih telah mendaftar di Ran Cukurin. Dapatkan diskon 10% untuk transaksi pertama Anda.',
                    'is_read' => false,
                ]);
            }

            // 10. Pengeluaran
            Pengeluaran::create(['tanggal' => Carbon::today()->subDays(2), 'keterangan' => 'Bayar Listrik Bulanan', 'nominal' => 500000]);
            Pengeluaran::create(['tanggal' => Carbon::today()->subDays(1), 'keterangan' => 'Beli Air Mineral untuk Tamu', 'nominal' => 50000]);
            Pengeluaran::create(['tanggal' => Carbon::today(), 'keterangan' => 'Gaji Mingguan Kapster', 'nominal' => 1500000]);

            // 11. Absensi
            foreach ($users['Kapster'] as $k) {
                Absensi::create([
                    'user_id' => $k->id,
                    'tanggal' => Carbon::today(),
                    'status' => 'Hadir',
                    'keterangan' => 'Tepat waktu',
                ]);
            }

            // 12. Banner
            Banner::create(['judul' => 'Promo Akhir Tahun', 'gambar_url' => '/images/banners/promo-akhir-tahun.jpg', 'link' => '#', 'urutan' => 1, 'is_active' => true]);
            Banner::create(['judul' => 'New Haircut Styles 2026', 'gambar_url' => '/images/banners/haircut-styles.jpg', 'link' => '#', 'urutan' => 2, 'is_active' => true]);
            Banner::create(['judul' => 'Spesial Grooming Treatment', 'gambar_url' => '/images/banners/promo-lebaran.jpg', 'link' => '#', 'urutan' => 3, 'is_active' => true]);

            // 13. Faq
            Faq::create(['pertanyaan' => 'Apakah bisa booking online?', 'jawaban' => 'Tentu saja! Anda bisa booking jadwal kapster favorit Anda melalui aplikasi ini secara langsung.', 'urutan' => 1, 'is_active' => true]);
            Faq::create(['pertanyaan' => 'Metode pembayaran apa saja yang tersedia?', 'jawaban' => 'Kami menerima Cash, Transfer Bank, dan QRIS.', 'urutan' => 2, 'is_active' => true]);
            Faq::create(['pertanyaan' => 'Apakah ada garansi jika potongan tidak sesuai?', 'jawaban' => 'Kami memberikan jaminan kepuasan. Jika kurang sesuai, sampaikan langsung ke kapster sebelum meninggalkan kursi.', 'urutan' => 3, 'is_active' => true]);
        });
    }
}
