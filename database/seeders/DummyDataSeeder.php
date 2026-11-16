<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Layanan;
use App\Models\Barang;
use App\Models\JadwalShift;
use App\Models\Voucher;
use App\Models\AntreanPemesanan;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Ulasan;
use App\Models\LogStok;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DummyDataSeeder extends Seeder
{
    public function run()
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
        // 1. Create Users
        $usersData = [
            [
                'nama' => 'Budi Pemilik',
                'email' => 'pemilik@example.com',
                'password' => Hash::make('password123'),
                'no_hp' => '081234567890',
                'role' => 'Pemilik',
            ],

            [
                'nama' => 'Andi Kapster',
                'email' => 'kapster@example.com',
                'password' => Hash::make('password123'),
                'no_hp' => '081234567892',
                'role' => 'Kapster',
            ],
            [
                'nama' => 'Bowo Kapster 2',
                'email' => 'kapster2@example.com',
                'password' => Hash::make('password123'),
                'no_hp' => '081234567895',
                'role' => 'Kapster',
            ],
            [
                'nama' => 'Dodi Pelanggan',
                'email' => 'pelanggan@example.com',
                'password' => Hash::make('password123'),
                'no_hp' => '081234567893',
                'role' => 'Pelanggan',
            ],
            [
                'nama' => 'Joko Pelanggan 2',
                'email' => 'pelanggan2@example.com',
                'password' => Hash::make('password123'),
                'no_hp' => '081234567894',
                'role' => 'Pelanggan',
            ],
        ];

        $users = [];
        foreach ($usersData as $data) {
            $user = User::create($data);
            $user->assignRole($data['role']);
            $users[$data['role']][] = $user;
        }

        // 2. Create Layanan
        $layanansData = [
            [
                'nama_layanan' => 'Haircut Premium',
                'deskripsi' => 'Potong rambut dengan pijat kepala',
                'harga' => 50000,
                'estimasi_durasi_menit' => 45,
                'is_active' => true,
            ],
            [
                'nama_layanan' => 'Haircut Basic',
                'deskripsi' => 'Potong rambut standar',
                'harga' => 35000,
                'estimasi_durasi_menit' => 30,
                'is_active' => true,
            ],
            [
                'nama_layanan' => 'Shaving',
                'deskripsi' => 'Cukur kumis dan jenggot',
                'harga' => 20000,
                'estimasi_durasi_menit' => 15,
                'is_active' => true,
            ],
        ];
        $layanans = [];
        foreach ($layanansData as $data) {
            $layanans[] = Layanan::create($data);
        }

        // 3. Create Barang
        $barangsData = [
            [
                'nama_barang' => 'Pomade Suavecito',
                'tipe' => 'ProdukJual',
                'stok_sekarang' => 10,
                'batas_minimum' => 2,
                'harga_beli' => 80000,
                'harga_jual' => 120000,
            ],
            [
                'nama_barang' => 'Hair Tonic',
                'tipe' => 'BahanBaku',
                'stok_sekarang' => 5,
                'batas_minimum' => 1,
                'harga_beli' => 45000,
                'harga_jual' => 0,
            ],
            [
                'nama_barang' => 'Sisir',
                'tipe' => 'ProdukJual',
                'stok_sekarang' => 20,
                'batas_minimum' => 5,
                'harga_beli' => 15000,
                'harga_jual' => 25000,
            ],
        ];
        $barangs = [];
        foreach ($barangsData as $data) {
            $barangs[] = Barang::create($data);
        }

        // 4. Create Jadwal Shift for Kapsters
        $today = Carbon::today();
        foreach ($users['Kapster'] as $kapster) {
            JadwalShift::create([
                'kapster_id' => $kapster->id,
                'tanggal' => $today,
                'jam_mulai' => '09:00:00',
                'jam_selesai' => '17:00:00',
                'status' => 'Hadir'
            ]);
            JadwalShift::create([
                'kapster_id' => $kapster->id,
                'tanggal' => $today->copy()->addDay(),
                'jam_mulai' => '10:00:00',
                'jam_selesai' => '18:00:00',
                'status' => 'Hadir'
            ]);
        }

        // 5. Create Vouchers
        $voucher1 = Voucher::create([
            'kode_voucher' => 'DISKON10K',
            'tipe_diskon' => 'Nominal',
            'nilai_diskon' => 10000,
            'kuota_maksimal' => 50,
            'kuota_terpakai' => 1,
            'tgl_mulai' => Carbon::now()->subDays(2),
            'tgl_berakhir' => Carbon::now()->addDays(30),
            'is_active' => true,
        ]);
        
        $voucher2 = Voucher::create([
            'kode_voucher' => 'HEMAT20',
            'tipe_diskon' => 'Persen',
            'nilai_diskon' => 20,
            'kuota_maksimal' => 100,
            'kuota_terpakai' => 0,
            'tgl_mulai' => Carbon::now()->subDays(2),
            'tgl_berakhir' => Carbon::now()->addDays(30),
            'is_active' => true,
        ]);

        // 6. Create AntreanPemesanan & Transaksi (Data yang sudah Selesai)
        $kapster1 = $users['Kapster'][0];
        $pelanggan1 = $users['Pelanggan'][0];

        $layanan1 = $layanans[0]; // Haircut Premium - 50k
        $barang1 = $barangs[0]; // Pomade - 120k
        
        // Selesai Transaction
        $antrean1 = AntreanPemesanan::create([
            'pelanggan_id' => $pelanggan1->id,
            'kapster_id' => $kapster1->id,
            'layanan_id' => $layanan1->id,
            'tipe_antrean' => 'Online',
            'nomor_antrean' => 'A001',
            'tanggal' => $today,
            'waktu_estimasi_mulai' => '10:00:00',
            'waktu_mulai_aktual' => '10:05:00',
            'waktu_selesai_aktual' => '10:50:00',
            'status' => 'Selesai',
        ]);

        $subtotal1 = $layanan1->harga + $barang1->harga_jual; // 50k + 120k = 170k
        $diskon1 = 10000;
        $totalAkhir1 = $subtotal1 - $diskon1;

        $transaksi1 = Transaksi::create([
            'antrean_id' => $antrean1->id,
            'kasir_id' => $kapster1->id,
            'voucher_id' => $voucher1->id,
            'subtotal' => $subtotal1,
            'total_diskon' => $diskon1,
            'total_akhir' => $totalAkhir1,
            'metode_pembayaran' => 'Cash',
            'status_bayar' => 'Lunas',
            'tanggal_bayar' => Carbon::now(),
        ]);

        DetailTransaksi::create([
            'transaksi_id' => $transaksi1->id,
            'tipe_item' => 'Layanan',
            'item_id' => $layanan1->id,
            'nama_item_snapshot' => $layanan1->nama_layanan,
            'qty' => 1,
            'harga_satuan_snapshot' => $layanan1->harga,
            'subtotal' => $layanan1->harga,
        ]);

        DetailTransaksi::create([
            'transaksi_id' => $transaksi1->id,
            'tipe_item' => 'Barang',
            'item_id' => $barang1->id,
            'nama_item_snapshot' => $barang1->nama_barang,
            'qty' => 1,
            'harga_satuan_snapshot' => $barang1->harga_jual,
            'subtotal' => $barang1->harga_jual,
        ]);

        // Reduce Stock
        $barang1->decrement('stok_sekarang', 1);

        // 7. Create Ulasan
        Ulasan::create([
            'antrean_id' => $antrean1->id,
            'pelanggan_id' => $pelanggan1->id,
            'kapster_id' => $kapster1->id,
            'rating' => 5,
            'komentar' => 'Sangat memuaskan, potongannya rapi!',
        ]);

        // 8. Create LogStok
        LogStok::create([
            'barang_id' => $barang1->id,
            'pencatat_id' => $kapster1->id,
            'referensi_transaksi_id' => $transaksi1->id,
            'tipe_log' => 'OtomatisPOS',
            'qty_perubahan' => -1,
            'stok_akhir' => $barang1->stok_sekarang,
            'keterangan' => 'Terjual pada transaksi ' . $transaksi1->id,
        ]);

        // 9. Create Antrean (Menunggu / Diproses)
        $pelanggan2 = $users['Pelanggan'][1];
        $kapster2 = $users['Kapster'][1];
        $layanan2 = $layanans[1]; // Haircut Basic

        AntreanPemesanan::create([
            'pelanggan_id' => $pelanggan2->id,
            'kapster_id' => $kapster2->id,
            'layanan_id' => $layanan2->id,
            'tipe_antrean' => 'WalkIn',
            'nomor_antrean' => 'B001',
            'tanggal' => $today,
            'waktu_estimasi_mulai' => Carbon::now()->addMinutes(30)->format('H:i:s'),
            'status' => 'Menunggu',
        ]);
        
        AntreanPemesanan::create([
            'pelanggan_id' => $pelanggan1->id,
            'kapster_id' => $kapster2->id,
            'layanan_id' => $layanans[2]->id, // Shaving
            'tipe_antrean' => 'Online',
            'nomor_antrean' => 'A002',
            'tanggal' => $today,
            'waktu_estimasi_mulai' => Carbon::now()->format('H:i:s'),
            'waktu_mulai_aktual' => Carbon::now()->format('H:i:s'),
            'status' => 'Diproses',
        ]);
        // 10. Generate Historical Data for Charts (Last 30 Days)
        $faker = \Faker\Factory::create('id_ID');
        for ($i = 0; $i < 40; $i++) {
            $randomDate = Carbon::now()->subDays(rand(1, 30))->setTime(rand(9, 17), rand(0, 59), 0);
            $randomPelanggan = $users['Pelanggan'][rand(0, 1)];
            $randomKapster = $users['Kapster'][rand(0, 1)];
            $randomLayanan = $layanans[rand(0, 2)];
            $randomBarang = rand(1, 100) > 70 ? $barangs[0] : null; // 30% chance to buy pomade

            $antrean = AntreanPemesanan::create([
                'pelanggan_id' => $randomPelanggan->id,
                'kapster_id' => $randomKapster->id,
                'layanan_id' => $randomLayanan->id,
                'tipe_antrean' => rand(0, 1) ? 'Online' : 'WalkIn',
                'nomor_antrean' => 'H' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'tanggal' => $randomDate->toDateString(),
                'waktu_estimasi_mulai' => $randomDate->format('H:i:s'),
                'waktu_mulai_aktual' => $randomDate->copy()->addMinutes(rand(0, 10))->format('H:i:s'),
                'waktu_selesai_aktual' => $randomDate->copy()->addMinutes($randomLayanan->estimasi_durasi_menit)->format('H:i:s'),
                'status' => 'Selesai',
            ]);

            $subtotal = $randomLayanan->harga;
            if ($randomBarang) {
                $subtotal += $randomBarang->harga_jual;
            }
            $diskon = rand(1, 100) > 80 ? 5000 : 0;
            $totalAkhir = $subtotal - $diskon;

            $transaksi = Transaksi::create([
                'antrean_id' => $antrean->id,
                'kasir_id' => $randomKapster->id,
                'subtotal' => $subtotal,
                'total_diskon' => $diskon,
                'total_akhir' => $totalAkhir,
                'metode_pembayaran' => rand(0, 1) ? 'Cash' : 'QRIS',
                'status_bayar' => 'Lunas',
                'tanggal_bayar' => $randomDate,
                'created_at' => $randomDate,
                'updated_at' => $randomDate,
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
            }

            // Random Ulasan
            if (rand(1, 100) > 40) { // 60% chance of review
                Ulasan::create([
                    'antrean_id' => $antrean->id,
                    'pelanggan_id' => $randomPelanggan->id,
                    'kapster_id' => $randomKapster->id,
                    'rating' => rand(4, 5),
                    'komentar' => 'Mantap!',
                    'created_at' => $randomDate,
                    'updated_at' => $randomDate,
                ]);
            }
        }
        });
    }
}
