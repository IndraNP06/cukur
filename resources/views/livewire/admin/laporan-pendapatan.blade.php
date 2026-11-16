<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.admin')] #[Title('Laporan Keuangan - Ran Cukurin')] class extends Component {
 public $searchQuery = '';

 public function with() {
 $transaksisQuery = Transaksi::with(['detailTransaksi', 'antrean'])
 ->orderBy('tanggal_bayar', 'desc');

 if ($this->searchQuery) {
 $transaksisQuery->where('id', 'like', '%' . $this->searchQuery . '%');
 }

 $transaksis = $transaksisQuery->get();

 $totalPendapatanKotor = $transaksis->sum('subtotal'); // or total_akhir if gross = after discount, but usually gross = subtotal
 
 // Calculate Layanan vs Produk
 $pendapatanLayanan = 0;
 $pendapatanProduk = 0;

 foreach ($transaksis as $trx) {
 foreach ($trx->detailTransaksi as $dt) {
 if ($dt->tipe_item === 'Layanan') {
 $pendapatanLayanan += $dt->subtotal;
 } elseif ($dt->tipe_item === 'Barang') {
 $pendapatanProduk += $dt->subtotal;
 }
 }
 }

 $totalTransaksi = $transaksis->count();
 $rataRataTrx = $totalTransaksi > 0 ? $totalPendapatanKotor / $totalTransaksi : 0;

 return [
 'transaksis' => $transaksis,
 'totalPendapatanKotor' => $totalPendapatanKotor,
 'pendapatanLayanan' => $pendapatanLayanan,
 'pendapatanProduk' => $pendapatanProduk,
 'totalTransaksi' => $totalTransaksi,
 'rataRataTrx' => $rataRataTrx
 ];
 }
};
?>

<div class="flex flex-col w-full gap-5 lg:gap-5">
 <!-- Header Actions & Title -->
 <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
 <div>
 <h1 class="font-headline-xl text-on-surface text-headline-xl">Laporan Pendapatan</h1>
 <p class="text-body-md font-body-md text-on-surface-variant mt-1">Ringkasan finansial dan riwayat transaksi keseluruhan</p>
 </div>
 </div>

 <!-- Summary Cards -->
 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
 <!-- Card 1 -->
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl relative overflow-hidden group">
 <div class="absolute -right-6 -top-6 w-12 h-12 bg-[#10b981]/5 rounded-full blur-xl group-hover:bg-[#10b981]/10 transition-colors"></div>
 <div class="flex justify-between items-start mb-4">
 <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Total Pendapatan Kotor</p>
 <div class="w-10 h-10 rounded-full bg-[#10b981]/10 flex items-center justify-center text-[#10b981]">
 <span class="material-symbols-outlined">account_balance_wallet</span>
 </div>
 </div>
 <h2 class="font-headline-lg text-[28px] text-[#10b981] text-headline-lg">Rp {{ number_format($totalPendapatanKotor, 0, ',', '.') }}</h2>
 </div>

 <!-- Card 2 -->
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl relative overflow-hidden group">
 <div class="absolute -right-6 -top-6 w-12 h-12 bg-primary/5 rounded-full blur-xl group-hover:bg-primary/10 transition-colors"></div>
 <div class="flex justify-between items-start mb-4">
 <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Pendapatan Layanan</p>
 <div class="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center text-primary">
 <span class="material-symbols-outlined">content_cut</span>
 </div>
 </div>
 <h2 class="font-headline-lg text-[28px] text-on-surface text-headline-lg">Rp {{ number_format($pendapatanLayanan, 0, ',', '.') }}</h2>
 <div class="mt-2 flex items-center gap-1 text-on-surface-variant text-label-sm font-label-sm">
 <span>{{ $totalPendapatanKotor > 0 ? round(($pendapatanLayanan / $totalPendapatanKotor) * 100) : 0 }}% dari total</span>
 </div>
 </div>

 <!-- Card 3 -->
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl relative overflow-hidden group">
 <div class="absolute -right-6 -top-6 w-12 h-12 bg-secondary/5 rounded-full blur-xl group-hover:bg-secondary/10 transition-colors"></div>
 <div class="flex justify-between items-start mb-4">
 <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Pendapatan Produk</p>
 <div class="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center text-secondary">
 <span class="material-symbols-outlined">inventory_2</span>
 </div>
 </div>
 <h2 class="font-headline-lg text-[28px] text-on-surface text-headline-lg">Rp {{ number_format($pendapatanProduk, 0, ',', '.') }}</h2>
 <div class="mt-2 flex items-center gap-1 text-on-surface-variant text-label-sm font-label-sm">
 <span>{{ $totalPendapatanKotor > 0 ? round(($pendapatanProduk / $totalPendapatanKotor) * 100) : 0 }}% dari total</span>
 </div>
 </div>

 <!-- Card 4 -->
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl relative overflow-hidden group">
 <div class="absolute -right-6 -top-6 w-12 h-12 bg-primary-container/5 rounded-full blur-xl group-hover:bg-primary-container/10 transition-colors"></div>
 <div class="flex justify-between items-start mb-4">
 <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Total Transaksi</p>
 <div class="w-10 h-10 rounded-full bg-primary-container/10 flex items-center justify-center text-primary-container">
 <span class="material-symbols-outlined">receipt_long</span>
 </div>
 </div>
 <h2 class="font-headline-lg text-[28px] text-primary-container text-headline-lg">{{ $totalTransaksi }}</h2>
 <div class="mt-2 flex items-center gap-1 text-on-surface-variant text-label-sm font-label-sm">
 <span>Rata-rata Rp {{ number_format($rataRataTrx, 0, ',', '.') }}/trx</span>
 </div>
 </div>
 </div>

 <!-- Detailed Ledger Table -->
 <div class="bg-surface-container rounded-xl overflow-hidden flex flex-col">
 <div class="p-4 lg:p-5 flex justify-between items-center bg-surface-container-low">
 <h3 class="font-headline-md text-on-surface text-headline-md">Rincian Transaksi</h3>
 <div class="flex items-center gap-2 bg-background px-3 py-1.5 rounded-full text-on-surface-variant border border-surface-variant">
 <span class="material-symbols-outlined text-[18px]">search</span>
 <input wire:model.live="searchQuery" class="bg-transparent border-none focus:outline-none text-label-sm font-label-sm placeholder:text-on-surface-variant w-12" placeholder="Cari ID Pesanan..." type="text">
 </div>
 </div>
 <div class="overflow-x-auto w-full">
 <table class="w-full text-left whitespace-nowrap">
 <thead>
 <tr class="bg-surface-container-high text-on-surface-variant font-label-md text-label-md uppercase tracking-wider">
 <th class="px-4 lg:px-5 py-4 font-semibold">Tanggal</th>
 <th class="px-4 lg:px-5 py-4 font-semibold">ID Transaksi</th>
 <th class="px-4 lg:px-5 py-4 font-semibold">Metode</th>
 <th class="px-4 lg:px-5 py-4 font-semibold text-right">Subtotal</th>
 <th class="px-4 lg:px-5 py-4 font-semibold text-right">Diskon</th>
 <th class="px-4 lg:px-5 py-4 font-semibold text-right">Total Bayar</th>
 </tr>
 </thead>
 <tbody class="text-body-md font-body-md text-on-surface">
 @forelse($transaksis as $trx)
 <tr class="hover:bg-surface-container-highest transition-colors border-b border-surface-variant/20 last:border-0 group">
 <td class="px-4 lg:px-5 py-4">
 <div class="flex flex-col">
 <span>{{ \Carbon\Carbon::parse($trx->tanggal_bayar)->format('d/m/Y') }}</span>
 <span class="text-label-sm font-label-sm text-on-surface-variant">{{ \Carbon\Carbon::parse($trx->tanggal_bayar)->format('H:i') }}</span>
 </div>
 </td>
 <td class="px-4 lg:px-5 py-4 font-mono text-primary">#TRX-{{ $trx->id }}</td>
 <td class="px-4 lg:px-5 py-4">{{ $trx->metode_pembayaran }}</td>
 <td class="px-4 lg:px-5 py-4 text-right">Rp {{ number_format($trx->subtotal,0,',','.') }}</td>
 <td class="px-4 lg:px-5 py-4 text-right text-error">- Rp {{ number_format($trx->total_diskon,0,',','.') }}</td>
 <td class="px-4 lg:px-5 py-4 text-right text-[#10b981] font-semibold">Rp {{ number_format($trx->total_akhir,0,',','.') }}</td>
 </tr>
 @empty
 <tr>
 <td colspan="6" class="px-4 lg:px-5 py-5 lg:py-5 text-center text-on-surface-variant">Belum ada transaksi</td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>
 </div>
</div>
