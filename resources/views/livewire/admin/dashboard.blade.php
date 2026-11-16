<?php

use App\Models\AntreanPemesanan;
use App\Models\Barang;
use App\Models\Transaksi;
use App\Models\Ulasan;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Dashboard Eksekutif - Ran CukurIn')] class extends Component {
 public $totalPendapatan = 0;
 public $totalPelanggan = 0;
 public $transaksiSelesai = 0;
 public $stokMenipis = [];
 public $topKapsters = [];
 
 // Percentages
 public $pendapatanGrowth = 0;
 public $pelangganGrowth = 0;

 public $selectedMonth;

 public function mount()
 {
 $this->selectedMonth = Carbon::now()->format('Y-m');
 $this->loadMetrics();
 }

 public function updatedSelectedMonth()
 {
 $this->loadMetrics();
 }
 
 public function loadMetrics()
 {
 $date = Carbon::parse($this->selectedMonth . '-01');
 $startOfMonth = $date->copy()->startOfMonth();
 $endOfMonth = $date->copy()->endOfMonth();
 
 $lastMonthStart = $date->copy()->subMonth()->startOfMonth();
 $lastMonthEnd = $date->copy()->subMonth()->endOfMonth();
 
 // Total Pendapatan
 $this->totalPendapatan = Transaksi::whereBetween('tanggal_bayar', [$startOfMonth, $endOfMonth])
 ->where('status_bayar', 'Lunas')
 ->sum('total_akhir');
 
 $lastMonthPendapatan = Transaksi::whereBetween('tanggal_bayar', [$lastMonthStart, $lastMonthEnd])
 ->where('status_bayar', 'Lunas')
 ->sum('total_akhir');
 
 if ($lastMonthPendapatan > 0) {
 $this->pendapatanGrowth = round((($this->totalPendapatan - $lastMonthPendapatan) / $lastMonthPendapatan) * 100, 1);
 } else {
 $this->pendapatanGrowth = $this->totalPendapatan > 0 ? 100 : 0;
 }

 // Total Pelanggan (Unique)
 $this->totalPelanggan = AntreanPemesanan::whereBetween('tanggal', [$startOfMonth, $endOfMonth])
 ->distinct('pelanggan_id')
 ->count('pelanggan_id');
 
 $lastMonthPelanggan = AntreanPemesanan::whereBetween('tanggal', [$lastMonthStart, $lastMonthEnd])
 ->distinct('pelanggan_id')
 ->count('pelanggan_id');
 
 if ($lastMonthPelanggan > 0) {
 $this->pelangganGrowth = round((($this->totalPelanggan - $lastMonthPelanggan) / $lastMonthPelanggan) * 100, 1);
 } else {
 $this->pelangganGrowth = $this->totalPelanggan > 0 ? 100 : 0;
 }

 // Transaksi Selesai
 $this->transaksiSelesai = AntreanPemesanan::whereBetween('tanggal', [$startOfMonth, $endOfMonth])
 ->where('status', 'Selesai')
 ->count();
 
 // Stok Menipis
 $this->stokMenipis = Barang::whereColumn('stok_sekarang', '<=', 'batas_minimum')->get();
 
 // Top Kapsters
 $kapsters = User::where('role', 'Kapster')->get();
 foreach ($kapsters as $kapster) {
 $volume = AntreanPemesanan::where('kapster_id', $kapster->id)
 ->where('status', 'Selesai')
 ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
 ->count();
 
 $rating = Ulasan::where('kapster_id', $kapster->id)
 ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
 ->avg('rating') ?? 0;
 
 if ($volume > 0) {
 $this->topKapsters[] = [
 'nama' => $kapster->nama,
 'volume' => $volume,
 'rating' => round($rating, 1)
 ];
 }
 }
 
 usort($this->topKapsters, function($a, $b) {
 return $b['volume'] <=> $a['volume'];
 });
 
 $this->topKapsters = array_slice($this->topKapsters, 0, 5);
 }
};
?>

<div class="flex-grow flex flex-col relative bg-background">
 <div class="absolute inset-0 pointer-events-none overflow-hidden z-0">
 <div class="absolute -top-40 -right-40 w-56 h-56 bg-primary/5 rounded-full blur-[100px]"></div>
 <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-secondary/5 rounded-full blur-[120px]"></div>
 </div>
 <div class="flex flex-col w-full gap-5 lg:gap-5 p-4 lg:p-5 pt-16 text-on-surface relative z-10">
 <!-- Header -->
 <header class="flex flex-col md:flex-row md:items-center justify-between gap-4">
 <div>
 <h1 class="font-headline-xl text-on-surface text-headline-xl">Tinjauan Eksekutif</h1>
 <p class="font-body-md text-body-md text-on-surface-variant mt-2">Ringkasan performa bisnis dan operasional Ran CukurIn.</p>
 </div>
 <div class="flex items-center bg-surface-container rounded-lg p-1 shadow-sm">
 <label class="px-4 py-2 font-label-md text-label-md text-on-surface hover:text-primary transition-colors flex items-center gap-2 cursor-pointer">
 <span class="material-symbols-outlined text-on-surface-variant">calendar_month</span>
 <input type="month" wire:model.live="selectedMonth" class="bg-transparent border-none outline-none text-on-surface cursor-pointer">
 </label>
 </div>
 </header>

 <!-- Alert Banner -->
 @if($stokMenipis->count() > 0)
 <div class="bg-error-container text-on-error-container p-4 lg:p-5 rounded-xl flex flex-col md:flex-row items-center justify-between gap-4 shadow-md relative overflow-hidden group">
 <div class="absolute inset-0 bg-error/10 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000 ease-in-out"></div>
 <div class="flex items-center gap-4 relative z-10">
 <span class="material-symbols-outlined text-xl">warning</span>
 <div>
 <h3 class="font-headline-md text-headline-md">Peringatan Stok Menipis</h3>
 <p class="font-body-md text-body-md mt-1">
 @foreach($stokMenipis as $idx => $stok)
 {{ $stok->nama_barang }} (Sisa {{ $stok->stok_sekarang }}){{ $idx < $stokMenipis->count() - 1 ? ', ' : '' }}
 @endforeach
 perlu segera diisi ulang.
 </p>
 </div>
 </div>
 <a href="{{ route('admin.stok') }}" wire:navigate class="bg-on-error-container text-error-container px-4 lg:px-5 py-2.5 rounded-full font-label-md text-label-md uppercase tracking-wider hover:scale-105 transition-transform whitespace-nowrap shadow-sm relative z-10">
 Restok Sekarang
 </a>
 </div>
 @endif

 <!-- KPI Grid -->
 <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-2">
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
 <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
 <span class="material-symbols-outlined text-xl text-primary">payments</span>
 </div>
 <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Total Pendapatan</p>
 <p class="font-headline-md text-[#10b981] text-headline-md">Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</p>
 <div class="flex items-center gap-1 mt-3 {{ $pendapatanGrowth >= 0 ? 'text-[#10b981]' : 'text-error' }}">
 <span class="material-symbols-outlined text-sm">{{ $pendapatanGrowth >= 0 ? 'trending_up' : 'trending_down' }}</span>
 <span class="font-label-sm text-[11px]">{{ $pendapatanGrowth >= 0 ? '+' : '' }}{{ $pendapatanGrowth }}% vs bulan lalu</span>
 </div>
 </div>
 
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
 <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
 <span class="material-symbols-outlined text-xl text-primary">group</span>
 </div>
 <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Total Pelanggan</p>
 <p class="font-headline-md text-on-surface text-headline-md">{{ $totalPelanggan }}</p>
 <div class="flex items-center gap-1 mt-3 {{ $pelangganGrowth >= 0 ? 'text-[#10b981]' : 'text-error' }}">
 <span class="material-symbols-outlined text-sm">{{ $pelangganGrowth >= 0 ? 'trending_up' : 'trending_down' }}</span>
 <span class="font-label-sm text-[11px]">{{ $pelangganGrowth >= 0 ? '+' : '' }}{{ $pelangganGrowth }}% vs bulan lalu</span>
 </div>
 </div>
 
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
 <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
 <span class="material-symbols-outlined text-xl text-primary">check_circle</span>
 </div>
 <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Transaksi Selesai</p>
 <p class="font-headline-md text-on-surface text-headline-md">{{ $transaksiSelesai }}</p>
 <div class="flex items-center gap-1 mt-3 text-on-surface-variant">
 <span class="material-symbols-outlined text-sm">horizontal_rule</span>
 <span class="font-label-sm text-[11px]">Bulan Ini</span>
 </div>
 </div>
 
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
 <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
 <span class="material-symbols-outlined text-xl text-primary">local_activity</span>
 </div>
 <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Voucher Aktif</p>
 <p class="font-headline-md text-on-surface text-headline-md">{{ App\Models\Voucher::where('is_active', true)->where('tgl_berakhir', '>=', now())->count() }}</p>
 <div class="flex items-center gap-1 mt-3 text-on-surface-variant">
 <span class="material-symbols-outlined text-sm">horizontal_rule</span>
 <span class="font-label-sm text-[11px]">Bulan Ini</span>
 </div>
 </div>
 </div>

 <!-- Table: Top Barbers -->
 <div class="bg-surface-container rounded-xl shadow-md overflow-hidden mt-6">
 <div class="p-4 lg:p-5 border-b border-outline-variant/20 flex justify-between items-center">
 <h3 class="font-headline-md text-on-surface text-headline-md">Peringkat Kapster Terbaik (Bulan Ini)</h3>
 <a href="{{ route('admin.kinerja') }}" wire:navigate class="text-primary hover:text-primary-container font-label-md text-label-md transition-colors">Lihat Kinerja Detail</a>
 </div>
 <div class="overflow-x-auto">
 <table class="w-full text-left">
 <thead class="bg-surface-container-high/50 font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">
 <tr>
 <th class="px-4 lg:px-5 py-4 font-medium">Peringkat</th>
 <th class="px-4 lg:px-5 py-4 font-medium">Nama Kapster</th>
 <th class="px-4 lg:px-5 py-4 font-medium text-right">Volume (Selesai)</th>
 <th class="px-4 lg:px-5 py-4 font-medium">Kualitas Layanan</th>
 </tr>
 </thead>
 <tbody class="font-body-md text-body-md text-on-surface divide-y divide-outline-variant/10">
 @forelse($topKapsters as $idx => $kapster)
 <tr class="hover:bg-surface-container-high/30 transition-colors group">
 <td class="px-4 lg:px-5 py-4 whitespace-nowrap">
 <div class="w-8 h-8 rounded-full {{ $idx === 0 ? 'bg-primary/20 text-primary' : 'bg-surface-container-high text-on-surface-variant' }} flex items-center justify-center font-headline-md group-hover:{{ $idx === 0 ? 'bg-primary text-on-primary' : '' }} transition-colors text-headline-md">{{ $idx + 1 }}</div>
 </td>
 <td class="px-4 lg:px-5 py-4 whitespace-nowrap">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center font-label-md uppercase shadow-inner">{{ substr($kapster['nama'], 0, 2) }}</div>
 <div>
 <p class="font-medium">{{ $kapster['nama'] }}</p>
 </div>
 </div>
 </td>
 <td class="px-4 lg:px-5 py-4 whitespace-nowrap text-right font-medium">{{ $kapster['volume'] }}</td>
 <td class="px-4 lg:px-5 py-4 whitespace-nowrap">
 <div class="flex items-center gap-1 text-primary">
 @for($i=1; $i<=5; $i++)
 <span class="material-symbols-outlined text-sm {{ $idx === 0 ? 'drop-shadow-[0_0_2px_rgba(245,158,11,0.8)]' : '' }}" style="font-variation-settings: 'FILL' {{ $kapster['rating'] >= $i ? 1 : ($kapster['rating'] >= $i - 0.5 ? 0.5 : 0) }};">{{ $kapster['rating'] >= $i ? 'star' : ($kapster['rating'] >= $i - 0.5 ? 'star_half' : 'star_outline') }}</span>
 @endfor
 <span class="text-on-surface-variant text-label-sm ml-2">{{ $kapster['rating'] }}</span>
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="4" class="px-4 lg:px-5 py-5 lg:py-5 text-center text-on-surface-variant">Belum ada data kinerja untuk bulan ini.</td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>
 </div>
 </div>
</div>
