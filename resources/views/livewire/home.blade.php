<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use App\Models\Layanan;
use App\Models\User;
use App\Models\AntreanPemesanan;

new #[Layout('components.layouts.blank')] #[Title('Ran CukurIn - Precision in Every Cut')] class extends Component {
    public $selectedKapsterId = 'all';

    public function with() {
        $today = \Carbon\Carbon::today()->toDateString();
        $kapsters = User::whereHas('roles', function($q){ $q->where('name', 'Kapster'); })->get();
        if ($kapsters->isEmpty()) {
            $kapsters = User::where('role', 'Kapster')->get();
        }

        // Status per Kapster untuk ringkasan HARI INI SAJA (tanpa fallback ke hari lain)
        $kapsterStatuses = [];
        foreach ($kapsters as $kap) {
            $curr = AntreanPemesanan::where('kapster_id', $kap->id)
                ->where('status', 'Diproses')
                ->whereDate('tanggal', $today)
                ->with('layanan')
                ->orderBy('waktu_mulai_aktual', 'desc')
                ->first();

            $waitCount = AntreanPemesanan::where('kapster_id', $kap->id)
                ->where('status', 'Menunggu')
                ->whereDate('tanggal', $today)
                ->count();

            $kapsterStatuses[$kap->id] = [
                'kapster' => $kap,
                'current' => $curr,
                'waiting_count' => $waitCount,
                'ewt' => AntreanPemesanan::hitungEwtKapster($kap->id, $today),
            ];
        }

        // Data berdasarkan kapster yang dipilih
        $activeKapster = null;
        $daftarMenunggu = collect();

        if ($this->selectedKapsterId !== 'all') {
            $selectedId = (int)$this->selectedKapsterId;
            $activeKapster = User::find($selectedId);

            $antreanSaatIni = AntreanPemesanan::where('kapster_id', $selectedId)
                ->where('status', 'Diproses')
                ->whereDate('tanggal', $today)
                ->with(['layanan', 'kapster'])
                ->orderBy('waktu_mulai_aktual', 'desc')
                ->first();

            $menungguList = AntreanPemesanan::where('kapster_id', $selectedId)
                ->where('status', 'Menunggu')
                ->whereDate('tanggal', $today)
                ->with('layanan')
                ->orderBy('waktu_estimasi_mulai', 'asc')
                ->take(4)
                ->get();

            $menungguCount = AntreanPemesanan::where('kapster_id', $selectedId)
                ->where('status', 'Menunggu')
                ->whereDate('tanggal', $today)
                ->count();

            $daftarMenunggu = $menungguList;
            $ewt = AntreanPemesanan::hitungEwtKapster($selectedId, $today);
        } else {
            // Live Antrean Data Global HARI INI SAJA (tanpa fallback ke hari lain)
            $antreanSaatIni = AntreanPemesanan::where('status', 'Diproses')
                ->whereDate('tanggal', $today)
                ->with(['layanan', 'kapster'])
                ->orderBy('waktu_mulai_aktual', 'desc')
                ->first();

            $menungguList = AntreanPemesanan::where('status', 'Menunggu')
                ->whereDate('tanggal', $today)
                ->with(['layanan', 'kapster'])
                ->orderBy('waktu_estimasi_mulai', 'asc')
                ->take(4)
                ->get();

            $menungguCount = AntreanPemesanan::where('status', 'Menunggu')
                ->whereDate('tanggal', $today)
                ->count();

            $daftarMenunggu = $menungguList;
            $ewt = AntreanPemesanan::hitungEwtToko($today);
        }

        $myActiveBooking = null;
        $mySisaDiDepan = 0;
        if (auth()->check()) {
            $myActiveBooking = AntreanPemesanan::where('pelanggan_id', auth()->id())
                ->whereIn('status', ['Menunggu', 'Diproses'])
                ->whereDate('tanggal', $today)
                ->with(['layanan', 'kapster'])
                ->orderBy('created_at', 'desc')
                ->first();

            if ($myActiveBooking) {
                $mySisaDiDepan = $myActiveBooking->sisa_antrean_di_depan;
                $ewt = $myActiveBooking->estimasi_tunggu_menit;
            }
        }

        $layanans = Layanan::where('is_active', true)->orderBy('id', 'asc')->get();
        if ($layanans->isEmpty()) {
            $layanans = Layanan::orderBy('id', 'asc')->get();
        }
        $banners = \App\Models\Banner::where('is_active', true)->orderBy('urutan')->get();
        $galeris = \App\Models\Galeri::where('is_active', true)->take(6)->get();

        return [
            'antreanSaatIni' => $antreanSaatIni,
            'menungguCount' => $menungguCount,
            'daftarMenunggu' => $daftarMenunggu,
            'ewt' => $ewt,
            'myActiveBooking' => $myActiveBooking,
            'mySisaDiDepan' => $mySisaDiDepan,
            'layanans' => $layanans,
            'kapsters' => $kapsters,
            'activeKapster' => $activeKapster,
            'kapsterStatuses' => $kapsterStatuses,
            'banners' => $banners,
            'galeris' => $galeris,
        ];
    }
};
?>

<div class="bg-background text-on-background font-body-md antialiased selection:bg-primary/30 selection:text-primary flex flex-col min-h-screen">
    <!-- TopNavBar -->
    <nav class="fixed top-0 w-full z-50 bg-background/85 backdrop-blur-md border-b border-outline-variant/40 shadow-sm transition-all duration-300" id="main-nav" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full h-20 flex items-center justify-between gap-6">
            <!-- Brand -->
            <a class="font-headline-md text-2xl lg:text-3xl font-bold text-primary tracking-tight text-headline-md shrink-0 flex items-center hover:opacity-90 transition-opacity" href="{{ url('/') }}">
                Ran CukurIn
            </a>

            <!-- Desktop Nav -->
            <ul class="hidden md:flex items-center gap-8 lg:gap-10">
                <li>
                    <a class="text-primary font-bold border-b-2 border-primary pb-2 font-label-md text-label-md hover:border-primary-fixed transition-colors" href="{{ route('home') }}">
                        Beranda
                    </a>
                </li>
                <li>
                    <a class="text-on-surface-variant font-medium hover:text-primary border-b-2 border-transparent hover:border-primary/50 pb-2 transition-all duration-200 font-label-md text-label-md" href="#katalog">
                        Katalog
                    </a>
                </li>
                <li>
                    <a class="text-on-surface-variant font-medium hover:text-primary border-b-2 border-transparent hover:border-primary/50 pb-2 transition-all duration-200 font-label-md text-label-md" href="#portofolio">
                        Portofolio
                    </a>
                </li>
                <li>
                    <a class="text-on-surface-variant font-medium hover:text-primary border-b-2 border-transparent hover:border-primary/50 pb-2 transition-all duration-200 font-label-md text-label-md" href="#galeri">
                        Galeri
                    </a>
                </li>
                <li>
                    <a class="text-on-surface-variant font-medium hover:text-primary border-b-2 border-transparent hover:border-primary/50 pb-2 transition-all duration-200 font-label-md text-label-md" href="#antrean">
                        Cek Antrean
                    </a>
                </li>
            </ul>

            <!-- Actions -->
            <div class="flex items-center gap-4 shrink-0">
                @auth
                    <a href="{{ auth()->user()->role === 'Pelanggan' ? route('customer.dashboard') : route('admin.dashboard') }}" class="hidden md:inline-flex items-center justify-center bg-primary hover:bg-primary-fixed text-on-primary font-bold font-label-md text-label-md px-6 py-2.5 rounded-xl transition-all duration-200 shadow-md shadow-primary/10 hover:shadow-primary/20 hover:scale-[1.02]">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="hidden md:inline-flex items-center justify-center bg-primary hover:bg-primary-fixed text-on-primary font-bold font-label-md text-label-md px-6 py-2.5 rounded-xl transition-all duration-200 shadow-md shadow-primary/10 hover:shadow-primary/20 hover:scale-[1.02]">
                        Masuk / Daftar
                    </a>
                @endauth
                <!-- Mobile Menu Toggle -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden text-on-background p-2 rounded-lg hover:bg-surface-container-highest transition-colors focus:outline-none">
                    <span class="material-symbols-outlined text-2xl" x-text="mobileMenuOpen ? 'close' : 'menu'">menu</span>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Drawer -->
        <div x-show="mobileMenuOpen" x-transition.opacity.duration.200ms class="md:hidden border-t border-outline-variant/30 bg-background/95 backdrop-blur-xl px-6 py-5 space-y-4" style="display: none;">
            <ul class="flex flex-col space-y-3">
                <li><a @click="mobileMenuOpen = false" class="block text-primary font-bold py-2 font-label-md text-label-md" href="{{ route('home') }}">Beranda</a></li>
                <li><a @click="mobileMenuOpen = false" class="block text-on-surface-variant hover:text-primary py-2 font-label-md text-label-md" href="#katalog">Katalog</a></li>
                <li><a @click="mobileMenuOpen = false" class="block text-on-surface-variant hover:text-primary py-2 font-label-md text-label-md" href="#portofolio">Portofolio</a></li>
                <li><a @click="mobileMenuOpen = false" class="block text-on-surface-variant hover:text-primary py-2 font-label-md text-label-md" href="#galeri">Galeri</a></li>
                <li><a @click="mobileMenuOpen = false" class="block text-on-surface-variant hover:text-primary py-2 font-label-md text-label-md" href="#antrean">Cek Antrean</a></li>
            </ul>
            <div class="pt-4 border-t border-outline-variant/30">
                @auth
                    <a href="{{ auth()->user()->role === 'Pelanggan' ? route('customer.dashboard') : route('admin.dashboard') }}" class="w-full text-center block bg-primary text-on-primary font-bold font-label-md text-label-md px-5 py-3 rounded-xl shadow-sm">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="w-full text-center block bg-primary text-on-primary font-bold font-label-md text-label-md px-5 py-3 rounded-xl shadow-sm">
                        Masuk / Daftar
                    </a>
                @endauth
            </div>
        </div>
    </nav>

 <!-- Main Content -->
 <main class="pt-20 lg:pt-24 flex-grow">
 <!-- Hero Section -->
 <section class="relative min-h-[90vh] flex items-center justify-center overflow-hidden py-16 md:py-32 w-full">
 <!-- Background Image -->
 <div class="absolute inset-0 z-0">
 <img alt="Premium barbershop interior" class="w-full h-full object-cover object-center opacity-30" src="https://images.unsplash.com/photo-1585747860715-2ba37e788b70?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80">
 <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/80 to-slate-900/30"></div>
 <div class="absolute inset-0 bg-gradient-to-r from-slate-900/90 via-slate-900/50 to-transparent"></div>
 </div>

 <div class="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
 <!-- Hero Content -->
 <div class="flex flex-col items-start space-y-8">
 <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-surface-container/80 border border-outline backdrop-blur-sm">
 <span class="relative flex h-3 w-3">
 <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-500 opacity-75"></span>
 <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
 </span>
 <span class="font-label-sm text-label-sm text-green-500/90 uppercase tracking-wider">Booking Online Aktif</span>
 </div>
 
 <h1 class="font-headline-xl text-4xl lg:text-6xl text-on-background leading-tight font-bold text-headline-xl">
 Gaya Rambut Terbaik, <br>
 <span class="text-primary">Tanpa Perlu Antre Lama.</span>
 </h1>
 
 <p class="font-body-lg text-lg text-on-surface max-w-xl leading-relaxed">
 Rasakan pengalaman grooming premium dengan sistem antrean real-time. Booking jadwalmu sekarang, pantau dari mana saja, dan datang tepat waktu.
 </p>
 
 <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto mt-4">
 <a href="{{ route('booking.wizard') }}" class="bg-primary text-on-primary font-bold font-label-md text-label-md px-5 lg:px-5 py-4 rounded-xl hover:bg-primary-fixed transition-all duration-300 shadow-lg flex items-center justify-center gap-2">
 <span>Booking Sekarang</span>
 <span class="material-symbols-outlined text-lg" style="font-variation-settings: 'FILL' 1;">calendar_month</span>
 </a>
 <a href="#katalog" class="bg-surface-container/50 backdrop-blur-sm border border-outline text-on-background font-label-md text-label-md px-5 lg:px-5 py-4 rounded-xl hover:bg-surface-container-high transition-colors duration-300 flex items-center justify-center gap-2">
 <span class="material-symbols-outlined text-lg">cut</span>
 <span>Lihat Katalog</span>
 </a>
 </div>
 </div>
 
  <!-- Live Queue Widget -->
 <div class="lg:justify-self-end w-full max-w-md" id="antrean" wire:poll.5s>
 <div class="p-5 lg:p-5 rounded-2xl shadow-2xl relative overflow-hidden group border border-outline/50 backdrop-blur-md bg-background/80">
 <div class="absolute top-0 right-0 w-12 h-12 bg-primary/10 rounded-full blur-3xl -mr-10 -mt-10"></div>
 
 <div class="flex items-center justify-between mb-6 relative z-10">
 <div class="flex items-center gap-2">
 <h3 class="font-headline-md text-2xl text-on-background font-semibold text-headline-md">Live Antrean</h3>
 <span class="text-xs bg-emerald-500/10 text-emerald-400 px-2 py-0.5 rounded-full font-medium border border-emerald-500/20">Live Sync</span>
 </div>
 <div class="relative flex items-center justify-center w-4 h-4">
 <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-500 opacity-75"></span>
 <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
 </div>
 </div>
 
            <div class="space-y-4 relative z-10">
                <!-- Kapster Selector Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                    <button 
                        wire:click="$set('selectedKapsterId', 'all')"
                        type="button"
                        class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all whitespace-nowrap {{ $selectedKapsterId === 'all' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container/90 text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}">
                        Semua Kapster
                    </button>
                    @foreach($kapsters as $k)
                        @php
                            $kWait = $kapsterStatuses[$k->id]['waiting_count'] ?? 0;
                        @endphp
                        <button 
                            wire:click="$set('selectedKapsterId', '{{ $k->id }}')"
                            type="button"
                            class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all whitespace-nowrap flex items-center gap-1.5 {{ (string)$selectedKapsterId === (string)$k->id ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container/90 text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}">
                            <span>{{ $k->nama }}</span>
                            @if($kWait > 0)
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ (string)$selectedKapsterId === (string)$k->id ? 'bg-on-primary/20 text-on-primary' : 'bg-primary/20 text-primary' }}">
                                    {{ $kWait }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>

                @if(isset($myActiveBooking) && $myActiveBooking)
                <!-- Personalized Active Booking Card for Logged In Customer -->
                <div class="bg-primary/10 border border-primary/30 rounded-xl p-4 flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <span class="font-label-sm text-primary uppercase font-bold tracking-wider text-xs flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-primary animate-ping"></span>
                            Tiket Antrean Anda
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $myActiveBooking->status === 'Diproses' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-primary/20 text-primary border border-primary/30' }}">
                            {{ $myActiveBooking->status }}
                        </span>
                    </div>
                    <div class="flex items-baseline justify-between">
                        <div>
                            <span class="font-headline-lg text-2xl font-bold text-on-surface">{{ $myActiveBooking->nomor_antrean }}</span>
                            @if($myActiveBooking->kapster)
                                <span class="text-xs text-on-surface-variant block mt-0.5">Kapster: <strong class="text-on-surface">{{ $myActiveBooking->kapster->nama }}</strong></span>
                            @endif
                        </div>
                        <div class="text-right">
                            @if($myActiveBooking->status === 'Diproses')
                                <span class="text-xs text-emerald-400 font-semibold block">Sedang dilayani sekarang!</span>
                            @else
                                <span class="text-xs text-primary font-medium block">{{ $mySisaDiDepan > 0 ? "Sisa $mySisaDiDepan antrean lagi" : "Giliran Anda berikutnya!" }}</span>
                                <span class="text-[11px] text-on-surface-variant block">~{{ $ewt }} Menit</span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('customer.tiket', ['id' => $myActiveBooking->id]) }}" class="text-xs text-primary hover:underline font-semibold flex items-center gap-1 mt-1">
                        <span>Buka Tiket Antrean</span>
                        <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                    </a>
                </div>
                @endif

                @if($selectedKapsterId !== 'all' && $activeKapster)
                <!-- Tampilan Khusus Kapster Tertentu -->
                <div class="flex items-center justify-between text-xs px-1 text-on-surface-variant">
                    <span>Antrean Khusus: <strong class="text-primary font-semibold">{{ $activeKapster->nama }}</strong></span>
                    <button wire:click="$set('selectedKapsterId', 'all')" type="button" class="text-primary hover:underline text-[11px] font-medium flex items-center gap-0.5">
                        <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                        <span>Lihat Semua</span>
                    </button>
                </div>

                <div class="bg-surface-container/80 rounded-xl p-4 border border-outline flex flex-col items-center justify-center text-center">
                    <span class="font-label-sm text-on-surface-variant uppercase tracking-wider mb-1 text-xs">Sedang Diproses Saat Ini</span>
                    <span class="font-headline-xl text-3xl font-bold leading-none text-headline-xl {{ $antreanSaatIni ? 'text-primary' : 'text-emerald-400' }}">
                        {{ $antreanSaatIni ? $antreanSaatIni->nomor_antrean : 'Standby' }}
                    </span>
                    <span class="text-xs text-on-surface-variant mt-2">
                        @if($antreanSaatIni)
                            Layanan: <strong class="text-on-surface">{{ optional($antreanSaatIni->layanan)->nama_layanan ?? '-' }}</strong>
                        @else
                            {{ $activeKapster->nama }} siap melayani pelanggan berikutnya.
                        @endif
                    </span>
                </div>

                <div class="flex items-center gap-4 bg-background/80 rounded-lg p-4 border border-outline-variant">
                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0">
                        <span class="material-symbols-outlined text-lg">timer</span>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-baseline">
                            <p class="font-label-md text-on-surface-variant text-sm">Menunggu {{ $activeKapster->nama }}:</p>
                            <p class="font-body-md text-sm font-bold text-on-background">{{ $menungguCount }} Orang</p>
                        </div>
                        <div class="flex justify-between items-baseline mt-0.5">
                            <p class="font-label-md text-on-surface-variant text-sm">Estimasi Waktu Tunggu:</p>
                            <p class="font-body-md text-sm font-bold text-primary">
                                @if($menungguCount === 0 && !$antreanSaatIni)
                                    <span class="text-emerald-400">0 Menit (Langsung Masuk)</span>
                                @else
                                    ~{{ $ewt }} Menit
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                @if($daftarMenunggu->isNotEmpty())
                <!-- Upcoming Waiting Tickets for this Kapster -->
                <div class="bg-surface-container/60 rounded-xl p-3 border border-outline/40">
                    <span class="text-[11px] text-on-surface-variant uppercase tracking-wider block mb-2 font-medium">Antrean Berikutnya ({{ $activeKapster->nama }}):</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach($daftarMenunggu as $m)
                            <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-surface-container-high text-on-surface border border-outline-variant/50">
                                {{ $m->nomor_antrean }}
                                @if($m->waktu_estimasi_mulai)
                                    <span class="text-[10px] font-normal text-on-surface-variant ml-1">({{ substr($m->waktu_estimasi_mulai, 0, 5) }})</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
                @endif

                @else
                <!-- Tampilan Semua Kapster (Global Overview) -->
                <div class="bg-surface-container/80 rounded-xl p-4 border border-outline flex flex-col items-center justify-center text-center">
                    <span class="font-label-sm text-on-surface-variant uppercase tracking-wider mb-1 text-xs">Sedang Diproses Toko</span>
                    <span class="font-headline-xl text-3xl font-bold leading-none text-headline-xl {{ $antreanSaatIni ? 'text-primary' : 'text-emerald-400' }}">
                        {{ $antreanSaatIni ? $antreanSaatIni->nomor_antrean : 'Standby' }}
                    </span>
                    @if($antreanSaatIni && $antreanSaatIni->kapster)
                    <span class="text-xs text-on-surface-variant mt-2">
                        Bersama: <strong class="text-on-surface">{{ $antreanSaatIni->kapster->nama }}</strong>
                    </span>
                    @else
                    <span class="text-xs text-on-surface-variant mt-2">
                        Semua kursi standby / siap melayani pelanggan hari ini.
                    </span>
                    @endif
                </div>

                <div class="flex items-center gap-4 bg-background/80 rounded-lg p-4 border border-outline-variant">
                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0">
                        <span class="material-symbols-outlined text-lg">timer</span>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-baseline">
                            <p class="font-label-md text-on-surface-variant text-sm">Total Menunggu:</p>
                            <p class="font-body-md text-sm font-bold text-on-background">{{ $menungguCount }} Orang</p>
                        </div>
                        <div class="flex justify-between items-baseline mt-0.5">
                            <p class="font-label-md text-on-surface-variant text-sm">Estimasi Tunggu:</p>
                            <p class="font-body-md text-sm font-bold text-primary">
                                @if($menungguCount === 0 && !$antreanSaatIni)
                                    <span class="text-emerald-400">0 Menit (Langsung Masuk)</span>
                                @else
                                    ~{{ $ewt }} Menit
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Per-Kapster Quick Status Cards -->
                <div class="space-y-2 pt-1">
                    <span class="text-[11px] text-on-surface-variant uppercase tracking-wider block font-semibold px-1">Status Kapster (Klik untuk filter):</span>
                    <div class="grid grid-cols-1 gap-2">
                        @foreach($kapsters as $k)
                            @php
                                $kStat = $kapsterStatuses[$k->id] ?? null;
                                $isBusy = $kStat && $kStat['current'];
                            @endphp
                            <button 
                                wire:click="$set('selectedKapsterId', '{{ $k->id }}')" 
                                type="button" 
                                class="w-full text-left bg-surface-container/60 hover:bg-surface-container-high border border-outline/40 hover:border-primary/50 rounded-xl p-2.5 flex items-center justify-between transition-all group">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs group-hover:bg-primary group-hover:text-on-primary transition-colors">
                                        {{ substr($k->nama, 0, 2) }}
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-on-surface group-hover:text-primary transition-colors">{{ $k->nama }}</div>
                                        <div class="text-[11px] text-on-surface-variant">
                                            @if($isBusy)
                                                <span class="text-amber-400 font-medium flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                                    Sedang cukur: #{{ $kStat['current']->nomor_antrean }}
                                                </span>
                                            @else
                                                <span class="text-emerald-400 font-medium flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                    Standby / Siap
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ ($kStat['waiting_count'] ?? 0) > 0 ? 'bg-primary/20 text-primary' : 'bg-surface-container-highest text-on-surface-variant' }}">
                                        {{ $kStat['waiting_count'] ?? 0 }} Antre
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif

                <button wire:click="$refresh" type="button" class="w-full text-center font-label-md text-label-md text-primary hover:text-primary-fixed transition-colors py-2.5 border border-primary/30 rounded-lg hover:bg-primary/10 font-semibold text-sm flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">sync</span>
                    <span>Segarkan Data Live</span>
                </button>
            </div>
 </div>
 </div>
 </div>
 </section>

 @if($banners->count() > 0)
 <!-- Promo Banners Section -->
 <section class="py-12 bg-surface-container/30 border-y border-outline-variant/30">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="flex items-center gap-3 mb-6">
 <span class="material-symbols-outlined text-primary text-2xl">campaign</span>
 <h3 class="font-headline-md text-xl lg:text-2xl text-on-background font-bold text-headline-md">Promo &amp; Informasi Terbaru</h3>
 </div>
 <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
 @foreach($banners as $banner)
 <div class="relative rounded-2xl overflow-hidden shadow-lg group h-52 bg-surface-container border border-outline-variant/50">
 <img src="{{ $banner->url }}" alt="{{ $banner->judul }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
 <div class="absolute inset-0 bg-gradient-to-t from-background/95 via-background/40 to-transparent flex flex-col justify-end p-5">
 <span class="bg-primary/90 text-on-primary text-xs uppercase font-bold tracking-wider px-2.5 py-1 rounded-full self-start mb-2">Penawaran Spesial</span>
 <h4 class="font-headline-md text-lg text-white font-bold">{{ $banner->judul }}</h4>
 </div>
 </div>
 @endforeach
 </div>
 </div>
 </section>
 @endif

 <!-- Services (Katalog Layanan) -->
 <section class="py-24 bg-background relative" id="katalog">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="text-center mb-10 lg:mb-10">
 <h2 class="font-headline-lg text-3xl text-on-background mb-4 font-bold text-headline-lg">Layanan Kami</h2>
 <p class="font-body-lg text-lg text-on-surface-variant max-w-2xl mx-auto">Pilih layanan grooming yang sesuai dengan kebutuhanmu, dieksekusi dengan presisi oleh kapster profesional kami.</p>
 </div>
 
 <div class="grid grid-cols-1 md:grid-cols-3 gap-5 lg:gap-5">
 @foreach($layanans as $layanan)
 <div class="bg-background border border-outline-variant rounded-2xl p-5 lg:p-5 hover:border-primary/40 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl hover:shadow-amber-500/5 group flex flex-col h-full">
 <div class="relative w-full h-48 rounded-xl overflow-hidden mb-5 bg-surface-container border border-outline-variant/40 flex items-center justify-center">
 @if(!empty($layanan->gambar_url) && !str_starts_with($layanan->gambar_url, 'http://localhost'))
 <img src="{{ $layanan->gambar_url }}" alt="{{ $layanan->nama_layanan }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
 <div class="w-full h-full items-center justify-center text-primary bg-primary/10 hidden">
 <span class="material-symbols-outlined text-4xl">content_cut</span>
 </div>
 @else
 <div class="w-full h-full flex items-center justify-center text-primary bg-primary/10">
 <span class="material-symbols-outlined text-4xl">content_cut</span>
 </div>
 @endif
 <div class="absolute inset-0 bg-gradient-to-t from-background/90 via-transparent to-transparent pointer-events-none"></div>
 <div class="absolute top-3 left-3 w-9 h-9 rounded-lg bg-background/80 backdrop-blur-md border border-outline-variant/40 flex items-center justify-center text-primary shadow-sm pointer-events-none">
 <span class="material-symbols-outlined text-sm">
 @if(str_contains(strtolower($layanan->nama_layanan), 'hair') || str_contains(strtolower($layanan->nama_layanan), 'cukur'))
 content_cut
 @elseif(str_contains(strtolower($layanan->nama_layanan), 'shave') || str_contains(strtolower($layanan->nama_layanan), 'kumis'))
 face
 @else
 spa
 @endif
 </span>
 </div>
 </div>
 <h3 class="font-headline-md text-2xl text-on-background mb-3 group-hover:text-primary transition-colors font-bold text-headline-md">{{ $layanan->nama_layanan }}</h3>
 <p class="font-body-md text-on-surface-variant mb-8 flex-grow">{{ Str::limit($layanan->deskripsi, 100) }}</p>
 
 <div class="flex items-center justify-between pt-4 border-t border-outline-variant">
 <span class="font-label-md text-lg text-primary font-bold">Rp {{ number_format($layanan->harga, 0, ',', '.') }}</span>
 <div class="flex items-center text-on-surface-variant gap-1">
 <span class="material-symbols-outlined text-[18px]">schedule</span>
 <span class="font-label-sm">{{ $layanan->estimasi_durasi_menit }} Menit</span>
 </div>
 </div>
 </div>
 @endforeach
 </div>
 
 <div class="text-center mt-12">
 <a href="{{ route('katalog') }}" class="inline-flex items-center gap-2 text-primary font-label-md hover:text-primary-fixed transition-colors">
 Lihat Katalog Lengkap <span class="material-symbols-outlined">arrow_forward</span>
 </a>
 </div>
 </div>
 </section>

 <!-- Kapster (Portofolio) -->
 <section class="py-24 bg-background relative" id="portofolio">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="flex flex-col md:flex-row justify-between items-end mb-10 lg:mb-10 gap-4 lg:gap-5">
 <div>
 <h2 class="font-headline-lg text-3xl text-on-background mb-4 font-bold text-headline-lg">Kapster Profesional Kami</h2>
 <p class="font-body-lg text-lg text-on-surface-variant max-w-xl">Kenali seniman di balik kursi kami. Pilih spesialis yang sesuai dengan gaya yang Anda inginkan.</p>
 </div>
 </div>
 
 <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-5">
 @foreach($kapsters as $kapster)
 <div class="group cursor-pointer">
 <div class="relative w-full aspect-[3/4] rounded-xl overflow-hidden mb-4 bg-surface-container border border-outline">
 <img src="{{ $kapster->foto_url }}" alt="{{ $kapster->nama }}" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105 filter grayscale group-hover:grayscale-0">
 
 <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-4">
 <a href="{{ route('booking.wizard') }}" class="w-full border-2 border-primary text-primary hover:bg-primary hover:text-on-primary font-label-md py-2.5 rounded-lg font-bold transition-colors text-center block">
 Pilih Kapster
 </a>
 </div>
 </div>
 <div class="flex justify-between items-start">
 <div>
 <h4 class="font-headline-md text-xl text-on-background font-bold text-headline-md">{{ $kapster->nama }}</h4>
 <p class="font-label-md text-on-surface-variant">Professional Barber</p>
 </div>
 <div class="flex items-center gap-1 bg-surface-container rounded-md px-2 py-1">
 <span class="material-symbols-outlined text-primary text-[14px]" style="font-variation-settings: 'FILL' 1;">star</span>
 <span class="font-label-md text-on-background font-bold">5.0</span>
 </div>
 </div>
 </div>
 @endforeach
 </div>
 </div>
 </section>

 <!-- Barbershop Gallery Section -->
 <section class="py-24 bg-surface-container/20 relative border-t border-outline-variant/30" id="galeri">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="text-center mb-12">
 <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs uppercase font-bold tracking-wider mb-3">
 <span class="material-symbols-outlined text-[16px]">photo_camera</span>
 Portofolio &amp; Suasana
 </div>
 <h2 class="font-headline-lg text-3xl text-on-background mb-4 font-bold text-headline-lg">Galeri Gaya Rambut &amp; Suasana</h2>
 <p class="font-body-lg text-lg text-on-surface-variant max-w-2xl mx-auto">Koleksi hasil karya potongan presisi dari kapster kami serta suasana studio Ran CukurIn yang nyaman dan berkelas.</p>
 </div>
 
 <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
 @foreach($galeris as $galeri)
 <div class="group relative rounded-2xl overflow-hidden shadow-lg border border-outline-variant/40 bg-surface-container h-64 cursor-pointer">
 <img src="{{ $galeri->url }}" alt="{{ $galeri->judul }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
 <div class="absolute inset-0 bg-gradient-to-t from-background/90 via-background/20 to-transparent opacity-80 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-5">
 <span class="font-label-sm text-xs text-primary font-bold uppercase tracking-wider mb-1">{{ $galeri->tipe }}</span>
 <h4 class="font-headline-md text-lg text-white font-semibold">{{ $galeri->judul }}</h4>
 </div>
 </div>
 @endforeach
 </div>
 </div>
 </section>
 </main>

 <!-- Footer -->
 <footer class="bg-background border-t border-outline-variant pt-16 pb-8">
 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10 lg:mb-10">
 <div class="space-y-4">
 <div class="font-headline-md text-xl font-bold text-primary text-headline-md">Ran CukurIn</div>
 <p class="font-body-md text-on-surface-variant">
 Gaya Rambut Terbaik, Tanpa Perlu Antre Lama. Elevating your grooming experience with precision and digital convenience.
 </p>
 </div>
 
 <div class="space-y-4 lg:ml-auto">
 <h4 class="font-headline-md text-xl text-on-background font-semibold mb-4 text-headline-md">Navigasi</h4>
 <ul class="space-y-3">
 <li><a class="text-on-surface-variant hover:text-primary transition-colors font-medium" href="{{ route('home') }}">Beranda</a></li>
 <li><a class="text-on-surface-variant hover:text-primary transition-colors font-medium" href="#katalog">Katalog Layanan</a></li>
 <li><a class="text-on-surface-variant hover:text-primary transition-colors font-medium" href="#portofolio">Tim Kapster</a></li>
 <li><a class="text-on-surface-variant hover:text-primary transition-colors font-medium" href="{{ route('booking.wizard') }}">Booking Online</a></li>
 </ul>
 </div>
 
 <div class="space-y-4 lg:ml-auto">
 <h4 class="font-headline-md text-xl text-on-background font-semibold mb-4 text-headline-md">Lokasi Kami</h4>
 <ul class="space-y-3">
 <li><a class="text-on-surface-variant hover:text-primary transition-colors flex items-start gap-3" href="https://maps.google.com" target="_blank">
 <span class="material-symbols-outlined text-[20px] mt-0.5">location_on</span>
 <span class="leading-relaxed">Jl. Senopati No. 42<br>Jakarta Selatan, 12190</span>
 </a></li>
 </ul>
 </div>
 
 <div class="space-y-4 lg:ml-auto">
 <h4 class="font-headline-md text-xl text-on-background font-semibold mb-4 text-headline-md">Hubungi Kami</h4>
 <ul class="space-y-3">
 <li><a class="text-on-surface-variant hover:text-primary transition-colors flex items-center gap-3" href="https://wa.me/6281234567890" target="_blank">
 <span class="material-symbols-outlined text-[20px]">chat</span> WhatsApp
 </a></li>
 <li><a class="text-on-surface-variant hover:text-primary transition-colors flex items-center gap-3" href="https://instagram.com" target="_blank">
 <span class="material-symbols-outlined text-[20px]">photo_camera</span> Instagram
 </a></li>
 </ul>
 </div>
 </div>
 
 <div class="border-t border-outline-variant pt-8 mt-8 text-center px-4 lg:px-5">
 <p class="font-label-md text-on-surface-variant">
 &copy; {{ date('Y') }} Ran CukurIn. Precision in Every Cut.
 </p>
 </div>
 </footer>
 
 <script>
 window.addEventListener('scroll', () => {
 const nav = document.getElementById('main-nav');
 if (window.scrollY > 20) {
 nav.classList.add('shadow-md');
 } else {
 nav.classList.remove('shadow-md');
 }
 });
 </script>
</div>

