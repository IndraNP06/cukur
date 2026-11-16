<?php

use App\Models\AntreanPemesanan;
use App\Models\JadwalShift;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Dashboard Kapster - Ran CukurIn')] class extends Component {
 public $totalPelanggan = 0;
 public $sisaJadwal = 0;
 public $selesaiDikerjakan = 0;
 
 public $jadwalTerdekat = [];
 public $shiftHariIni = null;

 public function mount()
 {
 $user = auth()->user();
 $today = Carbon::today();
 
 $this->shiftHariIni = JadwalShift::where('kapster_id', $user->id)
 ->whereDate('tanggal', $today)
 ->first();

 // Stats
 $pemesanans = AntreanPemesanan::where('kapster_id', $user->id)
 ->whereDate('tanggal', $today)
 ->get();
 
 $this->totalPelanggan = $pemesanans->count();
 $this->selesaiDikerjakan = $pemesanans->where('status', 'Selesai')->count();
 $this->sisaJadwal = $pemesanans->whereIn('status', ['Menunggu', 'Diproses'])->count();
 
 // Jadwal Terdekat
 $this->jadwalTerdekat = AntreanPemesanan::where('kapster_id', $user->id)
 ->whereDate('tanggal', $today)
 ->whereIn('status', ['Menunggu', 'Diproses'])
 ->with('pelanggan', 'layanan')
 ->orderBy('waktu_estimasi_mulai', 'asc')
 ->take(3)
 ->get();
 }
 
 public function mulaiPengerjaan($id)
 {
 $antrean = AntreanPemesanan::find($id);
 if ($antrean && $antrean->status === 'Menunggu') {
 $antrean->status = 'Diproses';
 $antrean->waktu_mulai_aktual = now()->format('H:i:s');
 $antrean->save();
 $this->mount();
 }
 }

 public function selesaiPengerjaan($id)
 {
 return redirect()->route('admin.pos', ['antrean_id' => $id]);
 }
};
?>

<div class="flex-grow flex flex-col relative bg-background">
 <div class="flex flex-col w-full h-full p-4 lg:p-5 pt-16 gap-5 lg:gap-5 relative overflow-hidden bg-background">
 
 <div class="absolute inset-0 pointer-events-none overflow-hidden z-0">
 <div class="absolute -top-40 -right-40 w-56 h-56 bg-primary/5 rounded-full blur-[100px]"></div>
 <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-secondary/5 rounded-full blur-[120px]"></div>
 </div>

 <!-- Top Header & Shift Status -->
 <header class="flex flex-col md:flex-row md:items-end justify-between gap-4 lg:gap-5 relative z-10 text-on-surface">
 <div class="flex flex-col gap-2">
 <span class="text-label-md font-label-md text-primary uppercase tracking-widest opacity-80 mix-blend-plus-lighter">Ran CukurIn Internal</span>
 <h1 class="font-headline-xl text-on-background tracking-tighter leading-none text-headline-xl">Selamat Datang,<br><span class="text-primary">{{ explode(' ', auth()->user()->nama)[0] }}!</span></h1>
 </div>
 <div class="flex items-center gap-3 bg-surface-container-high rounded-full px-4 lg:px-5 py-3 shadow-lg w-fit md:mb-2 transform transition-transform hover:scale-105">
 <div class="w-3 h-3 rounded-full {{ $shiftHariIni && $shiftHariIni->status === 'Hadir' ? 'bg-primary shadow-[0_0_12px_rgba(255,193,116,0.6)] animate-pulse' : 'bg-surface-variant' }}"></div>
 <span class="text-label-md font-label-md text-on-surface-variant">
 @if($shiftHariIni && $shiftHariIni->status === 'Hadir')
 Shift {{ \Carbon\Carbon::parse($shiftHariIni->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($shiftHariIni->jam_selesai)->format('H:i') }}
 @else
 Off Shift
 @endif
 <span class="mx-2 opacity-50">|</span> {{ \Carbon\Carbon::now()->translatedFormat('l, d M Y') }}
 </span>
 </div>
 </header>

 <!-- Summary Widgets (3-column grid) -->
 <section class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5 relative z-10 text-on-surface">
 <!-- Widget 1 -->
 <div class="bg-surface-container shadow-md rounded-xl p-4 lg:p-5 relative overflow-hidden group">
 <div class="absolute -right-8 -top-8 w-12 h-12 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-colors duration-500"></div>
 <div class="flex flex-col justify-between h-full gap-4 relative z-10">
 <div class="flex items-center justify-between">
 <span class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wide">Total Pelanggan</span>
 <span class="material-symbols-outlined text-primary opacity-80" style="font-variation-settings: 'FILL' 1;">groups</span>
 </div>
 <div class="flex items-baseline gap-2">
 <span class="font-headline-lg text-on-surface leading-none text-headline-lg">{{ $totalPelanggan }}</span>
 <span class="text-label-sm font-label-sm text-on-surface-variant">Hari Ini</span>
 </div>
 </div>
 </div>
 
 <!-- Widget 2 -->
 <div class="bg-surface-container shadow-md rounded-xl p-4 lg:p-5 relative overflow-hidden group">
 <div class="absolute -left-8 -bottom-8 w-12 h-12 bg-primary/10 rounded-full blur-2xl group-hover:bg-primary/20 transition-colors duration-500"></div>
 <div class="flex flex-col justify-between h-full gap-4 relative z-10">
 <div class="flex items-center justify-between">
 <span class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wide">Sisa Jadwal</span>
 <span class="material-symbols-outlined text-primary opacity-80" style="font-variation-settings: 'FILL' 1;">event_upcoming</span>
 </div>
 <div class="flex items-baseline gap-2">
 <span class="font-headline-lg text-primary leading-none text-headline-lg">{{ $sisaJadwal }}</span>
 <span class="text-label-sm font-label-sm text-on-surface-variant">Booking</span>
 </div>
 </div>
 </div>
 
 <!-- Widget 3 -->
 <div class="bg-surface-container shadow-md rounded-xl p-4 lg:p-5 relative overflow-hidden group">
 <div class="absolute right-0 top-1/2 -translate-y-1/2 w-12 h-12 bg-secondary/10 rounded-full blur-xl group-hover:bg-secondary/20 transition-colors duration-500"></div>
 <div class="flex flex-col justify-between h-full gap-4 relative z-10">
 <div class="flex items-center justify-between">
 <span class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wide">Selesai Dikerjakan</span>
 <span class="material-symbols-outlined text-secondary opacity-80" style="font-variation-settings: 'FILL' 1;">check_circle</span>
 </div>
 <div class="flex items-baseline gap-2">
 <span class="font-headline-lg text-secondary leading-none text-headline-lg">{{ $selesaiDikerjakan }}</span>
 <span class="text-label-sm font-label-sm text-on-surface-variant">Sesi</span>
 </div>
 </div>
 </div>
 </section>

 <!-- Main Content Split -->
 <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 lg:gap-5 relative z-10">
 <!-- Left Column: Jadwal Terdekat (col-span-2) -->
 <section class="lg:col-span-2 flex flex-col gap-4 lg:gap-5 text-on-surface">
 <div class="flex items-center justify-between">
 <h2 class="md:text-headline-md font-headline-lg text-on-background flex items-center gap-3 text-headline-lg">
 <span class="material-symbols-outlined text-primary text-xl">schedule</span>
 Jadwal Terdekat Anda
 </h2>
 <a href="{{ route('admin.antrean') }}" wire:navigate class="text-label-md font-label-md text-primary hover:text-primary-fixed transition-colors flex items-center gap-1">Lihat Semua <span class="material-symbols-outlined text-[18px]">arrow_forward</span></a>
 </div>
 
 <div class="flex flex-col gap-4">
 @forelse($jadwalTerdekat as $idx => $jadwal)
 <article class="bg-surface-container shadow-lg rounded-2xl p-4 md:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 lg:gap-5 group hover:-translate-y-1 transition-transform duration-300 relative overflow-hidden">
 <div class="absolute left-0 top-0 bottom-0 w-1 {{ $idx === 0 ? 'bg-primary scale-y-100' : 'bg-surface-variant group-hover:bg-primary/50' }} transform origin-bottom transition-all duration-300"></div>
 <div class="flex items-center gap-4 lg:gap-5 {{ $idx > 0 ? 'opacity-75 group-hover:opacity-100' : '' }} transition-opacity">
 <div class="bg-surface-container-highest px-4 py-3 rounded-xl flex flex-col items-center justify-center min-w-[80px]">
 <span class="text-label-sm font-label-sm text-on-surface-variant uppercase mb-1">Pukul</span>
 <span class="font-headline-md {{ $idx === 0 ? 'text-primary' : 'text-on-surface' }} leading-none text-headline-md">{{ \Carbon\Carbon::parse($jadwal->waktu_estimasi_mulai)->format('H:i') }}</span>
 </div>
 <div class="flex items-center gap-4">
 <div class="w-14 h-14 rounded-full bg-surface-container-low overflow-hidden shadow-inner shrink-0 relative flex items-center justify-center text-on-surface font-headline-md text-headline-md">
 {{ substr(optional($jadwal->pelanggan)->nama ?? 'A', 0, 2) }}
 </div>
 <div class="flex flex-col min-w-0">
 <h3 class="text-body-lg font-body-lg text-on-surface font-semibold truncate">{{ optional($jadwal->pelanggan)->nama ?? 'Anonim' }}</h3>
 <div class="flex items-center gap-2 mt-1">
 <span class="w-2 h-2 rounded-full bg-surface-variant"></span>
 <span class="text-label-sm font-label-sm text-on-surface-variant truncate">{{ optional($jadwal->layanan)->nama_layanan ?? '-' }}</span>
 </div>
 </div>
 </div>
 </div>
 @if($jadwal->status === 'Diproses')
 <button wire:click="selesaiPengerjaan({{ $jadwal->id }})" class="w-full md:w-auto bg-[#10b981] text-white px-4 lg:px-5 py-3 rounded-xl font-label-md text-label-md shadow-md shadow-[#10b981]/20 hover:bg-[#059669] hover:scale-105 transition-all duration-200 flex items-center justify-center gap-2 group/btn">
 Selesai & Ke Pembayaran
 <span class="material-symbols-outlined text-[20px] group-hover/btn:translate-x-1 transition-transform">point_of_sale</span>
 </button>
 @elseif($idx === 0)
 <button wire:click="mulaiPengerjaan({{ $jadwal->id }})" class="w-full md:w-auto bg-primary text-on-primary px-4 lg:px-5 py-3 rounded-xl font-label-md text-label-md shadow-md shadow-primary/20 hover:bg-primary-fixed hover:scale-105 transition-all duration-200 flex items-center justify-center gap-2 group/btn">
 Mulai Pengerjaan
 <span class="material-symbols-outlined text-[20px] group-hover/btn:translate-x-1 transition-transform">arrow_forward</span>
 </button>
 @else
 <button class="w-full md:w-auto bg-transparent border border-outline-variant text-on-surface px-4 lg:px-5 py-3 rounded-xl font-label-md text-label-md hover:bg-surface-container-high hover:border-primary hover:text-primary transition-all duration-200 flex items-center justify-center gap-2 opacity-50 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto">
 Menunggu...
 </button>
 @endif
 </article>
 @empty
 <div class="bg-surface-container shadow-lg rounded-2xl p-5 lg:p-5 text-center border-2 border-dashed border-surface-variant">
 <span class="material-symbols-outlined text-lg text-on-surface-variant mb-4">event_available</span>
 <p class="text-body-lg text-on-surface">Tidak ada jadwal antrean untuk saat ini.</p>
 </div>
 @endforelse
 </div>
 </section>

 <!-- Right Column: Aksi Cepat (col-span-1) -->
 <aside class="lg:col-span-1 flex flex-col gap-4 lg:gap-5 text-on-surface">
 <h2 class="font-headline-md text-on-background flex items-center gap-3 text-headline-md">
 <span class="material-symbols-outlined text-primary">bolt</span>
 Aksi Cepat
 </h2>
 <div class="bg-surface-container shadow-xl rounded-2xl p-4 lg:p-5 flex flex-col gap-4 relative overflow-hidden">
 <div class="absolute -right-12 -top-12 w-12 h-12 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
 <div class="absolute -left-12 -bottom-12 w-12 h-12 bg-secondary/5 rounded-full blur-3xl pointer-events-none"></div>
 
 <a href="{{ route('admin.antrean') }}" class="w-full bg-primary/10 border-2 border-dashed border-primary/50 text-primary px-4 py-4 lg:py-5 rounded-xl font-label-md text-label-md hover:bg-primary/20 hover:border-primary transition-all duration-300 flex flex-col items-center justify-center gap-3 group relative z-10">
 <span class="material-symbols-outlined text-[32px] group-hover:scale-110 transition-transform">person_add</span>
 <span>+ Input Pelanggan Walk-In</span>
 </a>
 
 <div class="w-full h-px bg-surface-variant my-2 relative z-10"></div>
 
 <a href="{{ route('admin.antrean') }}" class="w-full bg-surface-container-high text-on-surface px-4 py-4 rounded-xl font-label-md text-label-md hover:bg-surface-container-highest hover:text-primary transition-all duration-200 flex items-center justify-between group relative z-10 shadow-sm">
 <div class="flex items-center gap-3">
 <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors">view_list</span>
 Buka Antrean Terpadu
 </div>
 <span class="material-symbols-outlined opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all text-primary">chevron_right</span>
 </a>
 
 <a href="{{ route('admin.stok') }}" class="w-full bg-surface-container-high text-on-surface px-4 py-4 rounded-xl font-label-md text-label-md hover:bg-surface-container-highest hover:text-error transition-all duration-200 flex items-center justify-between group relative z-10 shadow-sm">
 <div class="flex items-center gap-3">
 <span class="material-symbols-outlined text-on-surface-variant group-hover:text-error transition-colors">inventory_2</span>
 Lapor Ekstra (Silet/Tisu)
 </div>
 <span class="material-symbols-outlined opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all text-error">chevron_right</span>
 </a>
 </div>
 </aside>
 </div>
 </div>
</div>
