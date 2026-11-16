<?php

use App\Models\AntreanPemesanan;
use App\Models\Ulasan;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Laporan Kinerja - Ran CukurIn')] class extends Component {
 public $month;
 public $year;
 public $kapstersData = [];
 public $selectedKapster = null;
 public $ulasans = [];
 
 // Top Highlights
 public $topProductive = null;
 public $topRating = null;
 public $topRevenue = null;

 public function mount()
 {
 $this->month = Carbon::now()->month;
 $this->year = Carbon::now()->year;
 $this->loadData();
 }
 
 public function loadData()
 {
 $kapsters = User::where('role', 'Kapster')->get();
 $this->kapstersData = [];
 
 $maxCukuran = 0;
 $maxRating = 0;
 $maxRevenue = 0;
 
 foreach ($kapsters as $kapster) {
 $pemesanans = AntreanPemesanan::where('kapster_id', $kapster->id)
 ->where('status', 'Selesai')
 ->whereMonth('tanggal', $this->month)
 ->whereYear('tanggal', $this->year)
 ->with('detailTransaksi')
 ->get();
 
 $totalPelanggan = $pemesanans->count();
 
 $revenue = $pemesanans->sum(function($p) {
 return $p->detailTransaksi ? $p->detailTransaksi->total_akhir : 0;
 });
 
 $rating = Ulasan::where('kapster_id', $kapster->id)
 ->whereMonth('created_at', $this->month)
 ->whereYear('created_at', $this->year)
 ->avg('rating') ?? 0;
 
 $data = [
 'id' => $kapster->id,
 'nama' => $kapster->nama,
 'total_pelanggan' => $totalPelanggan,
 'revenue' => $revenue,
 'rating' => round($rating, 1),
 ];
 
 $this->kapstersData[] = $data;
 
 // Highlights check
 if ($totalPelanggan > $maxCukuran) {
 $maxCukuran = $totalPelanggan;
 $this->topProductive = $data;
 }
 if ($rating > $maxRating) {
 $maxRating = $rating;
 $this->topRating = $data;
 }
 if ($revenue > $maxRevenue) {
 $maxRevenue = $revenue;
 $this->topRevenue = $data;
 }
 }
 
 // Load default ulasan if any data exists
 if (count($this->kapstersData) > 0 && !$this->selectedKapster) {
 $this->selectKapster($this->kapstersData[0]['id']);
 }
 }
 
 public function selectKapster($id)
 {
 $this->selectedKapster = collect($this->kapstersData)->firstWhere('id', $id);
 $this->ulasans = Ulasan::where('kapster_id', $id)
 ->whereMonth('created_at', $this->month)
 ->whereYear('created_at', $this->year)
 ->with('pelanggan')
 ->latest()
 ->take(5)
 ->get();
 }

 public function updatedMonth()
 {
 $this->loadData();
 }
 
 public function updatedYear()
 {
 $this->loadData();
 }
};
?>

<div class="flex-grow flex flex-col relative bg-background">
 <div class="flex flex-col w-full gap-5 lg:gap-5 p-4 lg:p-5 pt-16 text-on-surface">
 <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-4 gap-4">
 <div class="flex flex-col gap-1">
 <h1 class="font-headline-xl text-on-background tracking-tight text-headline-xl">Laporan Kinerja &amp; Rating Kapster</h1>
 <p class="font-body-lg text-body-lg text-on-surface-variant">Evaluasi performa bulanan dan ulasan pelanggan.</p>
 </div>
 
 <div class="flex items-center gap-4 bg-surface-container-low p-2 rounded-xl shadow-md">
 <div class="flex items-center gap-2 px-4 py-2 bg-surface-container rounded-lg">
 <span class="material-symbols-outlined text-on-surface-variant text-[20px]">calendar_month</span>
 <select wire:model.live="month" class="bg-transparent border-none outline-none text-on-surface font-label-md text-label-md appearance-none cursor-pointer">
 @for($i=1; $i<=12; $i++)
 <option value="{{ $i }}">{{ \Carbon\Carbon::create()->month($i)->translatedFormat('F') }}</option>
 @endfor
 </select>
 </div>
 <div class="flex items-center gap-2 px-4 py-2 bg-surface-container rounded-lg">
 <select wire:model.live="year" class="bg-transparent border-none outline-none text-on-surface font-label-md text-label-md appearance-none cursor-pointer">
 @for($i=date('Y'); $i>=date('Y')-2; $i--)
 <option value="{{ $i }}">{{ $i }}</option>
 @endfor
 </select>
 </div>
 </div>
 </div>

 <!-- Performance Highlights -->
 <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5">
 <!-- Card 1 -->
 <div class="relative bg-surface-container-high rounded-xl p-4 lg:p-5 shadow-xl overflow-hidden group">
 <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-surface-tint"></div>
 <div class="flex items-start justify-between mb-4">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center">
 <span class="material-symbols-outlined text-on-primary-container">content_cut</span>
 </div>
 <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Terproduktif</span>
 </div>
 </div>
 <div class="flex flex-col gap-1">
 <h3 class="font-headline-md text-on-surface text-headline-md">{{ $topProductive ? $topProductive['nama'] : '-' }}</h3>
 <div class="flex items-baseline gap-2">
 <span class="font-headline-lg text-primary text-headline-lg">{{ $topProductive ? $topProductive['total_pelanggan'] : 0 }}</span>
 <span class="font-body-md text-body-md text-on-surface-variant">Cukuran</span>
 </div>
 </div>
 <div class="absolute -bottom-6 -right-6 w-12 h-12 bg-primary/5 rounded-full blur-2xl"></div>
 </div>
 
 <!-- Card 2 -->
 <div class="relative bg-surface-container-high rounded-xl p-4 lg:p-5 shadow-xl overflow-hidden group">
 <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-surface-tint"></div>
 <div class="flex items-start justify-between mb-4">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center">
 <span class="material-symbols-outlined text-on-primary-container" style="font-variation-settings: 'FILL' 1;">star</span>
 </div>
 <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Rating Tertinggi</span>
 </div>
 </div>
 <div class="flex flex-col gap-1">
 <h3 class="font-headline-md text-on-surface text-headline-md">{{ $topRating ? $topRating['nama'] : '-' }}</h3>
 <div class="flex items-baseline gap-2">
 <span class="font-headline-lg text-primary text-headline-lg">{{ $topRating ? $topRating['rating'] : '0.0' }}</span>
 <span class="font-body-md text-body-md text-on-surface-variant">/ 5.0</span>
 </div>
 </div>
 <div class="absolute -bottom-6 -right-6 w-12 h-12 bg-primary/5 rounded-full blur-2xl"></div>
 </div>
 
 <!-- Card 3 -->
 <div class="relative bg-surface-container-high rounded-xl p-4 lg:p-5 shadow-xl overflow-hidden group">
 <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-surface-tint"></div>
 <div class="flex items-start justify-between mb-4">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center">
 <span class="material-symbols-outlined text-on-primary-container">payments</span>
 </div>
 <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Pendapatan</span>
 </div>
 </div>
 <div class="flex flex-col gap-1">
 <h3 class="font-headline-md text-on-surface text-headline-md">{{ $topRevenue ? $topRevenue['nama'] : '-' }}</h3>
 <div class="flex items-baseline gap-2">
 <span class="font-headline-md text-tertiary-container text-headline-md">Rp {{ number_format($topRevenue ? $topRevenue['revenue'] : 0, 0, ',', '.') }}</span>
 </div>
 </div>
 <div class="absolute -bottom-6 -right-6 w-12 h-12 bg-primary/5 rounded-full blur-2xl"></div>
 </div>
 </div>

 <!-- Main Table -->
 <div class="bg-surface-container rounded-xl shadow-2xl overflow-hidden mt-4">
 <div class="p-4 lg:p-5 bg-surface-container-high flex justify-between items-center border-b border-surface">
 <h2 class="font-headline-md text-on-surface text-headline-md">Detail Kinerja Kapster</h2>
 <button onclick="window.print()" class="flex items-center gap-2 text-primary font-label-md text-label-md hover:text-primary-fixed transition-colors">
 <span class="material-symbols-outlined text-[20px]">download</span>
 Unduh Laporan
 </button>
 </div>
 <div class="overflow-x-auto">
 <table class="w-full text-left border-collapse">
 <thead>
 <tr class="bg-surface-container-low text-on-surface-variant font-label-sm text-label-sm uppercase tracking-wider">
 <th class="p-4 pl-6 font-medium">Profil Kapster</th>
 <th class="p-4 font-medium">Total Pelanggan</th>
 <th class="p-4 font-medium">Kontribusi Layanan</th>
 <th class="p-4 font-medium">Rata-rata Rating</th>
 <th class="p-4 pr-6 font-medium text-right">Aksi</th>
 </tr>
 </thead>
 <tbody class="font-body-md text-body-md text-on-surface">
 @forelse($kapstersData as $data)
 <tr class="bg-surface-container hover:bg-surface-container-high transition-colors group cursor-pointer relative border-t border-surface-container-highest" wire:click="selectKapster({{ $data['id'] }})">
 <td class="p-4 pl-6 flex items-center gap-4">
 <div class="w-10 h-10 rounded-full flex items-center justify-center bg-surface-container-highest text-primary font-bold shadow-inner">
 {{ substr($data['nama'], 0, 2) }}
 </div>
 <span class="font-label-md text-label-md">{{ $data['nama'] }}</span>
 <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary scale-y-0 {{ $selectedKapster && $selectedKapster['id'] === $data['id'] ? 'scale-y-100' : 'group-hover:scale-y-100' }} transition-transform"></div>
 </td>
 <td class="p-4 text-on-surface-variant">{{ $data['total_pelanggan'] }} <span class="text-[12px]">Pelanggan</span></td>
 <td class="p-4 text-tertiary-container font-label-md">Rp {{ number_format($data['revenue'], 0, ',', '.') }}</td>
 <td class="p-4">
 <div class="flex items-center gap-2">
 <span class="font-label-md text-label-md">{{ $data['rating'] }}</span>
 <span class="material-symbols-outlined text-primary text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
 </div>
 </td>
 <td class="p-4 pr-6 text-right">
 <button wire:click.stop="selectKapster({{ $data['id'] }})" class="px-4 py-2 rounded-lg border {{ $selectedKapster && $selectedKapster['id'] === $data['id'] ? 'border-primary text-primary' : 'border-outline-variant text-on-surface' }} hover:border-primary hover:text-primary transition-all font-label-md text-label-md">
 Lihat Ulasan
 </button>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="5" class="p-5 lg:p-5 text-center text-on-surface-variant">Belum ada data kinerja pada bulan ini.</td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>
 </div>

 @if($selectedKapster)
 <!-- Reviews Panel -->
 <div class="bg-surface-container-lowest p-5 lg:p-5 rounded-xl shadow-2xl relative overflow-hidden mt-4">
 <div class="absolute top-0 right-0 w-12 h-12 bg-primary/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
 
 <div class="flex items-center gap-4 mb-6">
 <span class="material-symbols-outlined text-primary text-[32px]">reviews</span>
 <h3 class="font-headline-lg text-on-background text-headline-lg">Ulasan Terbaru: {{ $selectedKapster['nama'] }}</h3>
 </div>
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-5 relative z-10">
 @forelse($ulasans as $ulasan)
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl border border-surface-container-highest flex flex-col gap-4 hover:shadow-lg transition-shadow">
 <div class="flex justify-between items-start">
 <div class="flex gap-1">
 @for($i=1; $i<=5; $i++)
 <span class="material-symbols-outlined {{ $ulasan->rating >= $i ? 'text-primary' : 'text-surface-variant' }} text-[20px]" style="font-variation-settings: 'FILL' {{ $ulasan->rating >= $i ? 1 : 0 }};">star</span>
 @endfor
 </div>
 <span class="font-label-sm text-label-sm text-on-surface-variant">{{ $ulasan->created_at->diffForHumans() }}</span>
 </div>
 <p class="font-body-md text-body-md text-on-surface italic">"{{ $ulasan->ulasan ?? 'Tidak ada komentar.' }}"</p>
 <div class="flex items-center gap-3 mt-auto pt-4 border-t border-surface-container-highest">
 <div class="w-8 h-8 rounded-full bg-secondary-container flex items-center justify-center font-label-sm text-label-sm text-on-secondary-container">
 {{ substr(optional($ulasan->pelanggan)->nama ?? 'A', 0, 1) }}
 </div>
 <span class="font-label-sm text-label-sm text-on-surface-variant">{{ optional($ulasan->pelanggan)->nama ?? 'Anonim' }}</span>
 </div>
 </div>
 @empty
 <div class="col-span-full p-5 lg:p-5 text-center text-on-surface-variant">Belum ada ulasan untuk kapster ini pada bulan yang dipilih.</div>
 @endforelse
 </div>
 </div>
 @endif
 </div>
</div>
