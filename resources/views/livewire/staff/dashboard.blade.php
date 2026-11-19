<?php

use App\Models\AntreanPemesanan;
use App\Models\JadwalShift;
use App\Models\User;
use App\Models\Layanan;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')]
    #[Title('Dashboard Kapster - Ran CukurIn')]
    class extends Component {
    public $filterTanggal = 'upcoming'; // 'upcoming', 'today', 'tomorrow', 'all', 'custom'
    public $customTanggal = '';
    public $filterStatus = 'active'; // 'active', 'Menunggu', 'Diproses', 'Selesai', 'all'

    // Walk-In Modal
    public $showWalkInModal = false;
    public $walkInName = '';
    public $walkInLayanan = null;
    public $walkInTime = null;

    public function mount()
    {
    }

    public function syncFromSheets()
    {
        try {
            app(\App\Services\GoogleSheetsService::class)->pullTable('antrean_pemesanan');
            cache()->forget('sheets_sync_kapster_' . auth()->id());
            session()->flash('sync_success', 'Data antrean job Anda berhasil diperbarui langsung dari Google Spreadsheet!');
        } catch (\Throwable $e) {
            session()->flash('sync_error', 'Gagal menyinkronkan data: ' . $e->getMessage());
        }
    }

    public function isOwner(): bool
    {
        if (!auth()->check()) return false;
        $user = auth()->user();
        if (in_array((int)$user->id, [1, 2])) return true;
        $role = strtolower($user->role ?? '');
        if (str_contains($role, 'pemilik') || str_contains($role, 'owner') || str_contains($role, 'admin')) {
            return true;
        }
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('Pemilik') || $user->hasRole('Admin') || $user->hasRole('Owner');
        }
        return false;
    }

    public function mulaiPengerjaan($id)
    {
        $antrean = AntreanPemesanan::find($id);
        $canManage = $this->isOwner() || ((int)($antrean->kapster_id ?? 0) === (int)auth()->id());
        if ($antrean && $canManage && $antrean->status === 'Menunggu') {
            $antrean->status = 'Diproses';
            $antrean->waktu_mulai_aktual = now()->format('H:i:s');
            $antrean->save();
        }
    }

    public function selesaiPengerjaanLangsung($id)
    {
        $antrean = AntreanPemesanan::find($id);
        $canManage = $this->isOwner() || ((int)($antrean->kapster_id ?? 0) === (int)auth()->id());
        if ($antrean && $canManage && $antrean->status === 'Diproses') {
            $antrean->status = 'Selesai';
            $antrean->waktu_selesai_aktual = now()->format('H:i:s');
            $antrean->save();
        }
    }

    public function selesaiPengerjaan($id)
    {
        return redirect()->route('admin.pos', ['antrean_id' => $id]);
    }

    public function openWalkInModal()
    {
        $this->resetValidation();
        $this->reset(['walkInName', 'walkInLayanan']);
        $this->walkInTime = now()->format('H:i:s');
        $this->showWalkInModal = true;
    }

        public function setWalkInTime($time)
    {
        $this->walkInTime = $time;
        $this->resetErrorBag('walkInTime');
    }

    public function setWalkInTimeNow()
    {
        $this->walkInTime = now()->format('H:i');
        $this->resetErrorBag('walkInTime');
    }

    public function saveWalkIn()
    {
        if (empty($this->walkInTime)) {
            $this->walkInTime = now()->format('H:i:s');
        }

        $this->validate([
            'walkInName' => 'required|string|max:255',
            'walkInLayanan' => 'required|exists:layanan,id',
            'walkInTime' => 'required'
        ]);

        try {
            $dummyEmail = 'walkin_' . time() . '_' . Str::random(5) . '@rancukurin.local';
            $user = User::create([
                'nama' => $this->walkInName . ' (Walk-In)',
                'email' => $dummyEmail,
                'password' => bcrypt('password123'),
                'role' => 'Pelanggan',
            ]);
            try {
                $user->assignRole('Pelanggan');
            } catch (\Throwable $e) {
            }

            try {
                app(\App\Services\GoogleSheetsService::class)->appendRow('pengguna', [
                    'id' => $user->id,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'role' => 'Pelanggan',
                    'created_at' => now()->toDateTimeString(),
                ]);
            } catch (\Throwable $e) {
            }

            $tanggalWalkIn = now()->format('Y-m-d');
            if ($this->filterTanggal === 'tomorrow') {
                $tanggalWalkIn = now()->addDay()->format('Y-m-d');
            } elseif ($this->filterTanggal === 'custom' && !empty($this->customTanggal)) {
                $tanggalWalkIn = $this->customTanggal;
            }

            $antrean = AntreanPemesanan::create([
                'pelanggan_id' => $user->id,
                'kapster_id' => auth()->id(),
                'layanan_id' => $this->walkInLayanan,
                'tipe_antrean' => 'WalkIn',
                'nomor_antrean' => 'WLK-' . strtoupper(Str::random(5)),
                'tanggal' => $tanggalWalkIn,
                'waktu_estimasi_mulai' => $this->walkInTime,
                'status' => 'Menunggu'
            ]);

            try {
                $antrean->potongStokBahan();
            } catch (\Throwable $e) {}

            try {
                app(\App\Services\GoogleSheetsService::class)->appendRow('antrean_pemesanan', [
                    'id' => $antrean->id,
                    'nomor_antrean' => $antrean->nomor_antrean,
                    'pelanggan_id' => $antrean->pelanggan_id,
                    'kapster_id' => $antrean->kapster_id,
                    'layanan_id' => $antrean->layanan_id,
                    'tipe_antrean' => $antrean->tipe_antrean,
                    'tanggal' => $antrean->tanggal,
                    'waktu_estimasi_mulai' => $antrean->waktu_estimasi_mulai,
                    'status' => $antrean->status,
                    'created_at' => now()->toDateTimeString(),
                ]);
            } catch (\Throwable $e) {
            }

            $this->showWalkInModal = false;
            $this->reset(['walkInName', 'walkInLayanan', 'walkInTime']);

            session()->flash('sync_success', 'Antrean Walk-In berhasil ditambahkan ke daftar job Anda! Nomor Antrean: ' . $antrean->nomor_antrean);
        } catch (\Throwable $e) {
            $this->addError('walkInName', 'Gagal menambahkan antrean: ' . $e->getMessage());
        }
    }

    public function with()
    {
        $user = auth()->user();
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();

        $shiftHariIni = JadwalShift::firstOrCreate(
            ['kapster_id' => $user->id, 'tanggal' => $today],
            [
                'jam_mulai' => '10:00',
                'jam_selesai' => '21:00',
                'status' => 'Hadir'
            ]
        );

        // Base Query untuk kapster ini
        $baseKapsterQuery = AntreanPemesanan::where('kapster_id', $user->id);

        // Stats
        $totalPelanggan = (clone $baseKapsterQuery)->whereDate('tanggal', $today)->count();
        $totalMendatang = (clone $baseKapsterQuery)->whereDate('tanggal', '>=', $today)->whereIn('status', ['Menunggu', 'Diproses'])->count();
        $selesaiHariIni = (clone $baseKapsterQuery)->whereDate('tanggal', $today)->where('status', 'Selesai')->count();
        $sedangDiproses = (clone $baseKapsterQuery)->where('status', 'Diproses')->count();

        // Orders Query
        $ordersQuery = (clone $baseKapsterQuery)->with(['pelanggan', 'layanan']);

        // Date Filter
        if ($this->filterTanggal === 'today') {
            $ordersQuery->whereDate('tanggal', $today);
        } elseif ($this->filterTanggal === 'tomorrow') {
            $ordersQuery->whereDate('tanggal', $tomorrow);
        } elseif ($this->filterTanggal === 'upcoming') {
            $ordersQuery->whereDate('tanggal', '>=', $today);
        } elseif ($this->filterTanggal === 'custom' && !empty($this->customTanggal)) {
            $ordersQuery->whereDate('tanggal', $this->customTanggal);
        }

        // Status Filter
        if ($this->filterStatus === 'active') {
            $ordersQuery->whereIn('status', ['Menunggu', 'Diproses']);
        } elseif ($this->filterStatus !== 'all') {
            $ordersQuery->where('status', $this->filterStatus);
        }

        $orders = $ordersQuery
            ->orderBy('tanggal', 'asc')
            ->orderBy('waktu_estimasi_mulai', 'asc')
            ->get();

        return [
            'shiftHariIni' => $shiftHariIni,
            'totalPelanggan' => $totalPelanggan,
            'totalMendatang' => $totalMendatang,
            'selesaiHariIni' => $selesaiHariIni,
            'sedangDiproses' => $sedangDiproses,
            'orders' => $orders,
            'today' => $today,
            'tomorrow' => $tomorrow,
            'layanans' => Layanan::where('is_active', true)->get(),
            'timeSlots' => [
                '09:00',
                '09:30',
                '10:00',
                '10:30',
                '11:00',
                '11:30',
                '12:00',
                '12:30',
                '13:00',
                '13:30',
                '14:00',
                '14:30',
                '15:00',
                '15:30',
                '16:00',
                '16:30',
                '17:00',
                '17:30',
                '18:00',
                '18:30',
                '19:00',
                '19:30',
                '20:00'
            ],
        ];
    }
};
?>

<div class="flex-grow flex flex-col relative bg-background" wire:poll.5s>
    <div class="flex flex-col w-full h-full p-4 lg:p-6 pt-6 gap-6 relative overflow-hidden bg-background">

        <div class="absolute inset-0 pointer-events-none overflow-hidden z-0">
            <div class="absolute -top-40 -right-40 w-56 h-56 bg-primary/5 rounded-full blur-[100px]"></div>
            <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-secondary/5 rounded-full blur-[120px]"></div>
        </div>

        <!-- Top Header & Shift Status -->
        <header class="flex flex-col md:flex-row md:items-end justify-between gap-4 relative z-10 text-on-surface">
            <div class="flex flex-col gap-1">
                <span class="text-label-md font-label-md text-primary uppercase tracking-widest opacity-80">Dashboard
                    Kapster</span>
                <h1 class="font-headline-xl text-on-background tracking-tight text-3xl lg:text-4xl font-bold">
                    Halo, <span class="text-primary">{{ explode(' ', auth()->user()->nama)[0] }}!</span>
                </h1>
                <p class="text-sm text-on-surface-variant">Berikut adalah daftar pesanan &amp; giliran potong rambut
                    khusus untuk Anda.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button wire:click="syncFromSheets" wire:loading.attr="disabled"
                    class="flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-full bg-surface-container-high hover:bg-surface-bright text-primary border border-primary/30 transition shadow-sm cursor-pointer">
                    <span wire:loading.remove wire:target="syncFromSheets"
                        class="material-symbols-outlined text-sm">sync</span>
                    <span wire:loading wire:target="syncFromSheets"
                        class="material-symbols-outlined text-sm animate-spin">refresh</span>
                    <span>Sinkron Sheets</span>
                </button>
                <div class="flex items-center gap-3 bg-surface-container-high rounded-full px-5 py-2.5 shadow-md w-fit">
                    <div
                        class="w-3 h-3 rounded-full {{ $shiftHariIni && $shiftHariIni->status === 'Hadir' ? 'bg-primary shadow-[0_0_12px_rgba(255,193,116,0.6)] animate-pulse' : ($shiftHariIni && $shiftHariIni->status !== 'Hadir' ? 'bg-rose-500' : 'bg-surface-variant') }}">
                    </div>
                    <span class="text-label-md font-label-md text-on-surface-variant text-sm">
                        @if($shiftHariIni && $shiftHariIni->status === 'Hadir' && $shiftHariIni->jam_mulai && $shiftHariIni->jam_selesai)
                            Shift {{ \Carbon\Carbon::parse($shiftHariIni->jam_mulai)->format('H:i') }} -
                            {{ \Carbon\Carbon::parse($shiftHariIni->jam_selesai)->format('H:i') }}
                        @elseif($shiftHariIni && $shiftHariIni->status !== 'Hadir')
                            {{ $shiftHariIni->status }}
                        @else
                            Off Shift
                        @endif
                        <span class="mx-2 opacity-50">|</span> {{ \Carbon\Carbon::now()->translatedFormat('l, d M Y') }}
                    </span>
                </div>
            </div>
        </header>

        @if (session()->has('message') || session()->has('success'))
            <div
                class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-base">check_circle</span>
                <span>{{ session('message') ?? session('success') }}</span>
            </div>
        @endif
        @if (session()->has('sync_success'))
            <div
                class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-base">check_circle</span>
                <span>{{ session('sync_success') }}</span>
            </div>
        @endif
        @if (session()->has('sync_error'))
            <div
                class="p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-400 text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-base">error</span>
                <span>{{ session('sync_error') }}</span>
            </div>
        @endif

        <!-- Summary Widgets (3-column grid) -->
        <section class="grid grid-cols-1 md:grid-cols-4 gap-4 relative z-10 text-on-surface">
            <!-- Widget 1: Orderan Aktif / Mendatang -->
            <div
                class="bg-surface-container shadow-md rounded-2xl p-5 relative overflow-hidden group border border-outline-variant/30">
                <div class="flex items-center justify-between mb-2">
                    <span
                        class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wide text-xs">Job
                        Mendatang</span>
                    <span class="material-symbols-outlined text-primary text-xl"
                        style="font-variation-settings: 'FILL' 1;">event_upcoming</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-primary leading-none">{{ $totalMendatang }}</span>
                    <span class="text-xs text-on-surface-variant">Booking Aktif</span>
                </div>
            </div>

            <!-- Widget 2: Sedang Diproses -->
            <div
                class="bg-surface-container shadow-md rounded-2xl p-5 relative overflow-hidden group border border-outline-variant/30">
                <div class="flex items-center justify-between mb-2">
                    <span
                        class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wide text-xs">Sedang
                        Dicukur</span>
                    <span class="material-symbols-outlined text-amber-400 text-xl animate-spin">sync</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-amber-400 leading-none">{{ $sedangDiproses }}</span>
                    <span class="text-xs text-on-surface-variant">Pelanggan Aktif</span>
                </div>
            </div>

            <!-- Widget 3: Pelanggan Hari Ini -->
            <div
                class="bg-surface-container shadow-md rounded-2xl p-5 relative overflow-hidden group border border-outline-variant/30">
                <div class="flex items-center justify-between mb-2">
                    <span
                        class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wide text-xs">Total
                        Hari Ini</span>
                    <span class="material-symbols-outlined text-primary opacity-80 text-xl"
                        style="font-variation-settings: 'FILL' 1;">groups</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-on-surface leading-none">{{ $totalPelanggan }}</span>
                    <span class="text-xs text-on-surface-variant">Total Booking</span>
                </div>
            </div>

            <!-- Widget 4: Selesai Hari Ini -->
            <div
                class="bg-surface-container shadow-md rounded-2xl p-5 relative overflow-hidden group border border-outline-variant/30">
                <div class="flex items-center justify-between mb-2">
                    <span
                        class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wide text-xs">Selesai
                        Hari Ini</span>
                    <span class="material-symbols-outlined text-emerald-400 opacity-80 text-xl"
                        style="font-variation-settings: 'FILL' 1;">check_circle</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-emerald-400 leading-none">{{ $selesaiHariIni }}</span>
                    <span class="text-xs text-on-surface-variant">Selesai</span>
                </div>
            </div>
        </section>

        <!-- Main Content Split -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 relative z-10">
            <!-- Left Column: Orderan & Job Antrean Anda (col-span-2) -->
            <section class="lg:col-span-2 flex flex-col gap-4 text-on-surface">
                <!-- Control & Filter Bar -->
                <div class="bg-surface-container p-4 rounded-2xl border border-outline-variant/40 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-xl">content_cut</span>
                            <h2 class="text-lg font-bold text-on-surface">Job &amp; Orderan Saya</h2>
                            <span
                                class="text-xs bg-primary/20 text-primary px-2.5 py-0.5 rounded-full font-bold font-mono">{{ $orders->count() }}
                                Job</span>
                        </div>

                        <!-- Date Filter Pills -->
                        <div class="flex items-center gap-1 overflow-x-auto pb-1 scrollbar-none text-xs">
                            <button wire:click="$set('filterTanggal', 'upcoming')" type="button"
                                class="px-3 py-1.5 rounded-lg font-semibold transition-all whitespace-nowrap {{ $filterTanggal === 'upcoming' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface' }}">
                                Semua Mendatang
                            </button>
                            <button wire:click="$set('filterTanggal', 'today')" type="button"
                                class="px-3 py-1.5 rounded-lg font-semibold transition-all whitespace-nowrap {{ $filterTanggal === 'today' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface' }}">
                                Hari Ini
                            </button>
                            <button wire:click="$set('filterTanggal', 'tomorrow')" type="button"
                                class="px-3 py-1.5 rounded-lg font-semibold transition-all whitespace-nowrap {{ $filterTanggal === 'tomorrow' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface' }}">
                                Besok
                            </button>
                            <button wire:click="$set('filterTanggal', 'all')" type="button"
                                class="px-3 py-1.5 rounded-lg font-semibold transition-all whitespace-nowrap {{ $filterTanggal === 'all' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface' }}">
                                Semua Riwayat
                            </button>
                        </div>
                    </div>

                    <!-- Status Filter Tabs -->
                    <div class="flex items-center justify-between border-t border-outline-variant/30 pt-3 text-xs">
                        <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-none">
                            <span
                                class="text-on-surface-variant text-[11px] font-medium mr-1 hidden sm:inline">Status:</span>
                            <button wire:click="$set('filterStatus', 'active')" type="button"
                                class="px-2.5 py-1 rounded-md font-medium transition-all {{ $filterStatus === 'active' ? 'bg-primary/20 text-primary border border-primary/40' : 'text-on-surface-variant hover:text-on-surface' }}">
                                Aktif (Menunggu &amp; Diproses)
                            </button>
                            <button wire:click="$set('filterStatus', 'Menunggu')" type="button"
                                class="px-2.5 py-1 rounded-md font-medium transition-all {{ $filterStatus === 'Menunggu' ? 'bg-primary/20 text-primary border border-primary/40' : 'text-on-surface-variant hover:text-on-surface' }}">
                                Menunggu
                            </button>
                            <button wire:click="$set('filterStatus', 'Diproses')" type="button"
                                class="px-2.5 py-1 rounded-md font-medium transition-all {{ $filterStatus === 'Diproses' ? 'bg-primary/20 text-primary border border-primary/40' : 'text-on-surface-variant hover:text-on-surface' }}">
                                Sedang Diproses
                            </button>
                            <button wire:click="$set('filterStatus', 'Selesai')" type="button"
                                class="px-2.5 py-1 rounded-md font-medium transition-all {{ $filterStatus === 'Selesai' ? 'bg-primary/20 text-primary border border-primary/40' : 'text-on-surface-variant hover:text-on-surface' }}">
                                Selesai
                            </button>
                            <button wire:click="$set('filterStatus', 'all')" type="button"
                                class="px-2.5 py-1 rounded-md font-medium transition-all {{ $filterStatus === 'all' ? 'bg-primary/20 text-primary border border-primary/40' : 'text-on-surface-variant hover:text-on-surface' }}">
                                Semua
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="date" wire:model.live="customTanggal"
                                wire:change="$set('filterTanggal', 'custom')"
                                class="bg-surface-container-high text-on-surface text-xs rounded-lg px-2.5 py-1 border border-outline-variant/50 outline-none cursor-pointer">
                        </div>
                    </div>
                </div>

                <!-- Orders List -->
                <div class="flex flex-col gap-3">
                    @forelse($orders as $idx => $order)
                        @php
                            $isToday = $order->tanggal === $today;
                            $isTomorrow = $order->tanggal === $tomorrow;
                        @endphp
                        <article
                            class="bg-surface-container shadow-md rounded-2xl p-4 md:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 border border-outline-variant/30 hover:border-primary/50 transition-all relative overflow-hidden group">
                            <!-- Left Accent Border -->
                            <div
                                class="absolute left-0 top-0 bottom-0 w-1.5 {{ $order->status === 'Diproses' ? 'bg-emerald-500' : ($order->status === 'Menunggu' ? 'bg-primary' : 'bg-surface-variant') }}">
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center gap-4 flex-1">
                                <!-- Time & Date Box -->
                                <div
                                    class="bg-surface-container-highest px-4 py-3 rounded-xl flex flex-col items-center justify-center min-w-[95px] shrink-0 border border-outline-variant/20">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider {{ $isToday ? 'text-emerald-400' : ($isTomorrow ? 'text-primary' : 'text-on-surface-variant') }}">
                                        @if($isToday)
                                            Hari Ini
                                        @elseif($isTomorrow)
                                            Besok
                                        @else
                                            {{ \Carbon\Carbon::parse($order->tanggal)->translatedFormat('d M') }}
                                        @endif
                                    </span>
                                    <span class="text-xl font-bold font-mono text-on-surface mt-0.5">
                                        {{ substr($order->waktu_estimasi_mulai, 0, 5) }}
                                    </span>
                                    <span class="text-[10px] text-on-surface-variant">WIB</span>
                                </div>

                                <!-- Customer & Service Info -->
                                <div class="flex items-center gap-3.5 flex-1 min-w-0">
                                    <div
                                        class="w-12 h-12 rounded-xl bg-surface-container-high border border-outline-variant/30 overflow-hidden shrink-0 flex items-center justify-center text-primary font-bold text-base shadow-sm">
                                        {{ substr(optional($order->pelanggan)->nama ?? 'P', 0, 2) }}
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="text-base font-bold text-on-surface truncate">
                                                {{ optional($order->pelanggan)->nama ?? 'Pelanggan Umum' }}</h3>
                                            <span
                                                class="font-mono text-xs px-2 py-0.5 rounded font-bold {{ $order->tipe_antrean === 'Online' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'bg-purple-500/20 text-purple-400 border border-purple-500/30' }}">
                                                {{ $order->nomor_antrean }}
                                            </span>
                                            <span
                                                class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full font-semibold {{ $order->status === 'Diproses' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 animate-pulse' : ($order->status === 'Menunggu' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-surface-container-highest text-on-surface-variant') }}">
                                                {{ $order->status }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-3 text-xs text-on-surface-variant mt-1">
                                            <span class="flex items-center gap-1 font-medium text-on-surface">
                                                <span class="material-symbols-outlined text-[14px] text-primary">cut</span>
                                                {{ optional($order->layanan)->nama_layanan ?? '-' }}
                                            </span>
                                            <span>&bull;</span>
                                            <span>Rp
                                                {{ number_format(optional($order->layanan)->harga ?? 0, 0, ',', '.') }}</span>
                                            @if(optional($order->pelanggan)->no_telp)
                                                <span>&bull;</span>
                                                <span class="flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-[14px]">call</span>
                                                    {{ $order->pelanggan->no_telp }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div
                                class="flex items-center gap-2 shrink-0 border-t sm:border-t-0 pt-3 sm:pt-0 border-outline-variant/30">
                                @if($order->status === 'Diproses')
                                    <button wire:click="selesaiPengerjaanLangsung({{ $order->id }})" type="button"
                                        class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-md shadow-emerald-600/20">
                                        <span class="material-symbols-outlined text-[18px]">check</span>
                                        <span>Selesai</span>
                                    </button>
                                    <button wire:click="selesaiPengerjaan({{ $order->id }})" type="button"
                                        class="bg-primary hover:bg-primary-fixed text-on-primary px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-md shadow-primary/20">
                                        <span class="material-symbols-outlined text-[18px]">point_of_sale</span>
                                        <span>Kasir/POS</span>
                                    </button>
                                @elseif($order->status === 'Menunggu')
                                    <button wire:click="mulaiPengerjaan({{ $order->id }})" type="button"
                                        class="bg-primary hover:bg-primary-fixed text-on-primary px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-md shadow-primary/20">
                                        <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                                        <span>Mulai Pengerjaan</span>
                                    </button>
                                @else
                                    <span
                                        class="text-xs text-on-surface-variant px-3 py-1.5 bg-surface-container-high rounded-lg flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px] text-emerald-400">check_circle</span>
                                        <span>Job Selesai</span>
                                    </span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div
                            class="bg-surface-container shadow-md rounded-2xl p-8 text-center border-2 border-dashed border-outline-variant/50">
                            <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-2">event_busy</span>
                            <h4 class="text-base font-bold text-on-surface">Tidak ada orderan untuk kriteria ini</h4>
                            <p class="text-xs text-on-surface-variant mt-1">Coba ganti filter tanggal atau status untuk
                                melihat jadwal lainnya.</p>
                            <button wire:click="$set('filterTanggal', 'all'); $set('filterStatus', 'all');" type="button"
                                class="mt-4 px-4 py-2 bg-primary/20 text-primary hover:bg-primary/30 rounded-lg text-xs font-bold transition-all inline-flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">visibility</span>
                                <span>Tampilkan Semua Orderan</span>
                            </button>
                        </div>
                    @endforelse
                </div>
            </section>

            <!-- Right Column: Aksi Cepat & Info Kapster (col-span-1) -->
            <aside class="lg:col-span-1 flex flex-col gap-4 text-on-surface">
                <div
                    class="bg-surface-container shadow-md rounded-2xl p-5 flex flex-col gap-4 border border-outline-variant/30">
                    <h3 class="font-bold text-base text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-xl">bolt</span>
                        <span>Aksi Cepat Kapster</span>
                    </h3>

                    <button type="button" wire:click="openWalkInModal"
                        class="w-full bg-primary/10 border-2 border-dashed border-primary/50 text-primary px-4 py-3.5 rounded-xl font-bold text-xs hover:bg-primary/20 hover:border-primary transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <span class="material-symbols-outlined text-lg">person_add</span>
                        <span>Input Pelanggan Walk-In</span>
                    </button>

                    <div class="w-full h-px bg-outline-variant/30 my-1"></div>

                    <a href="{{ route('admin.antrean') }}"
                        class="w-full bg-surface-container-high text-on-surface px-4 py-3 rounded-xl text-xs font-semibold hover:bg-surface-container-highest hover:text-primary transition-all flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-primary text-[18px]">view_kanban</span>
                            <span>Papan Antrean Terpadu</span>
                        </div>
                        <span class="material-symbols-outlined text-primary text-[16px]">chevron_right</span>
                    </a>

                    <a href="{{ route('admin.stok') }}"
                        class="w-full bg-surface-container-high text-on-surface px-4 py-3 rounded-xl text-xs font-semibold hover:bg-surface-container-highest hover:text-primary transition-all flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-primary text-[18px]">inventory_2</span>
                            <span>Lapor Ekstra (Silet / Tisu)</span>
                        </div>
                        <span class="material-symbols-outlined text-primary text-[16px]">chevron_right</span>
                    </a>

                    <a href="{{ route('staff.absensi') }}"
                        class="w-full bg-surface-container-high text-on-surface px-4 py-3 rounded-xl text-xs font-semibold hover:bg-surface-container-highest hover:text-primary transition-all flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-primary text-[18px]">how_to_reg</span>
                            <span>Absensi Kehadiran</span>
                        </div>
                        <span class="material-symbols-outlined text-primary text-[16px]">chevron_right</span>
                    </a>
                </div>

                <!-- Info Pelayanan Hari Ini -->
                <div
                    class="bg-surface-container/60 rounded-2xl p-5 border border-outline-variant/30 text-xs text-on-surface-variant space-y-2.5">
                    <span class="font-bold text-on-surface block text-sm">Petunjuk Operasional</span>
                    <p class="leading-relaxed">
                        1. Klik <strong>Mulai Pengerjaan</strong> saat pelanggan sudah duduk di kursi Anda. Status akan
                        otomatis berubah menjadi "Sedang Diproses" di papan antrean toko dan homepage pelanggan.
                    </p>
                    <p class="leading-relaxed">
                        2. Setelah selesai mencukur, klik <strong>Selesai</strong> jika tanpa transaksi kasir tambahan,
                        atau klik <strong>Kasir/POS</strong> untuk menyelesaikan pembayaran.
                    </p>
                    <p class="leading-relaxed">
                        3. Seluruh perubahan status langsung tersinkronisasi otomatis ke Google Spreadsheet secara
                        real-time.
                    </p>
                </div>
            </aside>
        </div>
    </div>

    <!-- Walk-In Modal untuk Kapster -->
    @if($showWalkInModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-3 sm:p-4 md:p-6 overflow-y-auto">
            <div class="w-full max-w-xl bg-surface-container-high rounded-2xl shadow-2xl overflow-hidden border border-outline-variant/40 animate-[scale-up_0.2s_ease-out] max-h-[92vh] sm:max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3.5 sm:px-5 sm:py-4 bg-surface-container-highest border-b border-outline-variant/30 shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-xl">person_add</span>
                        <h2 class="font-bold text-lg text-on-surface">Tambah Antrean Walk-In (Job Anda)</h2>
                    </div>
                    <button wire:click="$set('showWalkInModal', false)"
                        class="text-on-surface-variant hover:text-primary transition-colors focus:outline-none cursor-pointer">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <!-- Body -->
                <div class="p-4 sm:p-5 space-y-4 overflow-y-auto flex-1 min-h-0">
                    <!-- Customer Name -->
                    <div class="space-y-1.5">
                        <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Nama
                            Pelanggan Walk-In</label>
                        <input wire:model="walkInName"
                            class="w-full bg-surface-container text-on-surface text-sm px-4 py-2.5 rounded-xl border border-outline-variant/40 focus:outline-none focus:border-primary transition-colors"
                            placeholder="Masukkan nama pelanggan..." type="text">
                        @error('walkInName') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <!-- Service & Barber Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Layanan</label>
                            <select wire:model="walkInLayanan"
                                class="w-full bg-surface-container text-on-surface text-sm px-3.5 py-2.5 rounded-xl border border-outline-variant/40 focus:outline-none focus:border-primary transition-colors">
                                <option value="">Pilih Layanan</option>
                                @foreach($layanans as $l)
                                    <option value="{{ $l->id }}">{{ $l->nama_layanan }}</option>
                                @endforeach
                            </select>
                            @error('walkInLayanan') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Kapster
                                Bertugas</label>
                            <div
                                class="w-full bg-surface-container text-on-surface text-sm px-4 py-2.5 rounded-xl border border-outline-variant/40 flex items-center justify-between">
                                <span class="truncate">{{ auth()->user()->nama }}</span>
                                <span class="text-xs text-primary font-bold shrink-0">(Anda)</span>
                            </div>
                        </div>
                    </div>
                    <!-- Manual Time Selection -->
                    <div class="p-4 rounded-xl bg-surface-container border border-outline-variant/30 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Jam Mulai (Manual)</label>
                                <p class="text-[11px] text-on-surface-variant">Atur jam mulai pengerjaan job Anda.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative">
                                    <input type="time" wire:model.live.debounce.300ms="walkInTime" class="bg-surface-container-high border {{ $walkInConflict ? 'border-rose-500 text-rose-400' : 'border-outline-variant/50 text-primary' }} focus:border-primary rounded-xl px-3 py-2 text-center text-base font-mono font-bold outline-none transition-all">
                                </div>
                                @if($walkInEstimasiSelesai)
                                    <div class="text-[11px] font-mono text-on-surface-variant bg-surface-container-highest px-2.5 py-2 rounded-xl whitespace-nowrap">
                                        s/d <strong class="text-on-surface">{{ $walkInEstimasiSelesai }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Quick Helper Buttons -->
                        <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-outline-variant/20">
                            <span class="text-[11px] text-on-surface-variant font-medium mr-1">Cepat:</span>
                            <button type="button" wire:click="setWalkInTimeNow" class="px-2.5 py-1 rounded-lg bg-primary/15 hover:bg-primary/25 border border-primary/30 text-primary text-xs font-semibold flex items-center gap-1 transition-all">
                                <span class="material-symbols-outlined text-[13px]">bolt</span> Sekarang ({{ now()->format('H:i') }})
                            </button>
                            @foreach(['10:00', '12:00', '13:30', '15:00', '16:30', '18:00', '19:30'] as $preset)
                                <button type="button" wire:click="setWalkInTime('{{ $preset }}')" class="px-2.5 py-1 rounded-lg {{ $walkInTime === $preset ? 'bg-primary text-on-primary font-bold' : 'bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-mono' }} text-xs transition-all">
                                    {{ $preset }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Status Alert -->
                    @if($walkInConflict)
                        <div class="p-3 rounded-xl bg-rose-500/15 border border-rose-500/40 text-rose-200 text-xs flex items-start gap-2.5 animate-[fadeIn_0.2s_ease-out]">
                            <span class="material-symbols-outlined text-rose-400 text-[18px] shrink-0 mt-0.5">event_busy</span>
                            <div class="space-y-0.5">
                                <strong class="text-rose-300 font-bold block">Jadwal Bertabrakan!</strong>
                                <p class="text-rose-200/90 leading-tight">{{ $walkInConflict['message'] }}</p>
                            </div>
                        </div>
                    @elseif(!empty($walkInTime) && $walkInEstimasiSelesai)
                        <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-emerald-400 text-[16px]">check_circle</span>
                                <span>Pukul <strong>{{ substr($walkInTime, 0, 5) }} - {{ $walkInEstimasiSelesai }} WIB</strong> tersedia</span>
                            </span>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono font-bold">Tersedia</span>
                        </div>
                    @endif
                    @error('walkInTime') <span class="text-rose-400 text-xs block font-semibold">{{ $message }}</span> @enderror

                    <!-- Penanda Jadwal yang Sudah Ter-booking -->
                    <div class="space-y-1.5 pt-1">
                        <div class="flex items-center justify-between text-xs text-on-surface-variant font-bold uppercase tracking-wider">
                            <span class="flex items-center gap-1 text-[11px]">
                                <span class="material-symbols-outlined text-rose-400 text-[15px]">lock_clock</span>
                                <span>Jadwal Job Anda Hari Ini ({{ count($walkInBookedSlots) }})</span>
                            </span>
                        </div>
                        @if(count($walkInBookedSlots) > 0)
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 max-h-36 overflow-y-auto pr-1">
                                @foreach($walkInBookedSlots as $slot)
                                    @php
                                        $isCurrent = $walkInConflict && isset($walkInConflict['slot']) && $walkInConflict['slot']['id'] === $slot['id'];
                                    @endphp
                                    <div class="p-2 rounded-lg {{ $isCurrent ? 'bg-rose-500/25 border border-rose-500 text-rose-200' : 'bg-surface-container border border-rose-500/20 text-on-surface' }} text-xs font-mono">
                                        <div class="font-bold text-rose-300">{{ $slot['start'] }} - {{ $slot['end'] }}</div>
                                        <div class="text-[10px] text-on-surface-variant truncate">{{ $slot['layanan'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-2.5 rounded-lg bg-surface-container/60 text-xs text-on-surface-variant text-center">
                                Anda belum memiliki antrean lain di jam ini.
                            </div>
                        @endif
                    </div>
                </div>
                <!-- Footer -->
                <div class="px-4 py-3 sm:px-5 sm:py-3.5 bg-surface-container-highest border-t border-outline-variant/30 flex items-center justify-end gap-2 sm:gap-3 shrink-0">
                    <button wire:click="$set('showWalkInModal', false)" type="button"
                        class="px-4 py-2 text-xs font-semibold text-on-surface-variant hover:text-on-surface transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button wire:click="saveWalkIn" wire:loading.attr="disabled" type="button"
                        class="px-5 py-2.5 bg-primary text-on-primary text-xs font-bold rounded-xl hover:bg-primary-fixed transition-all flex items-center gap-1.5 shadow-md shadow-primary/20 cursor-pointer">
                        <span wire:loading.remove wire:target="saveWalkIn"
                            class="material-symbols-outlined text-[16px]">check</span>
                        <span wire:loading wire:target="saveWalkIn"
                            class="material-symbols-outlined text-[16px] animate-spin">refresh</span>
                        <span>Simpan Antrean</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>