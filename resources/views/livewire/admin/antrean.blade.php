<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use App\Models\AntreanPemesanan;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Layanan;
use Illuminate\Support\Str;

new #[Layout('components.layouts.admin')] #[Title('Antrean Terpadu - Ran Cukurin')] class extends Component {
    public $filterKapster = 'all';
    public $filterTanggal = 'all'; // 'all' (Semua Aktif), 'today', 'tomorrow', 'history', 'custom'
    public $customTanggal = '';

    // Walk-In Modal
    public $showWalkInModal = false;
    public $walkInName = '';
    public $walkInLayanan = null;
    public $walkInKapster = null;
    public $walkInTime = null;

    public function mount() {
        if (auth()->check() && (auth()->user()->role === 'Kapster' || (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('Kapster')))) {
            $this->filterKapster = (string)auth()->id();
            $this->walkInKapster = auth()->id();
        }

        if (request()->query('action') === 'walkin') {
            $this->openWalkInModal();
        }

        // Sinkronkan data antrean terbaru dari Google Spreadsheet saat pertama kali dibuka
        try {
            if (!cache()->has('sheets_sync_antrean_board')) {
                app(\App\Services\GoogleSheetsService::class)->pullTable('antrean_pemesanan');
                cache()->put('sheets_sync_antrean_board', true, now()->addSeconds(10));
            }
        } catch (\Throwable $e) {}
    }

    public function openWalkInModal() {
        $this->resetValidation();
        $this->reset(['walkInName', 'walkInLayanan', 'walkInTime']);
        if (auth()->check() && (auth()->user()->role === 'Kapster' || (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('Kapster')))) {
            $this->walkInKapster = auth()->id();
        } else {
            $this->walkInKapster = 'any';
        }
        $this->walkInTime = Carbon::now()->format('H:i:s');
        $this->showWalkInModal = true;
    }

    public function syncFromSheets() {
        try {
            app(\App\Services\GoogleSheetsService::class)->pullTable('antrean_pemesanan');
            cache()->forget('sheets_sync_antrean_board');
            session()->flash('sync_success', 'Data antrean terpadu berhasil disinkronkan langsung dari Google Spreadsheet!');
        } catch (\Throwable $e) {
            session()->flash('sync_error', 'Gagal menyinkronkan data: ' . $e->getMessage());
        }
    }

    public function startProcess($id) {
        $antrean = AntreanPemesanan::find($id);
        if ($antrean && $antrean->status === 'Menunggu') {
            $antrean->status = 'Diproses';
            $antrean->waktu_mulai_aktual = Carbon::now()->format('H:i:s');
            $antrean->save();

            try {
                app(\App\Services\GoogleSheetsService::class)->syncTable('antrean_pemesanan');
            } catch (\Throwable $e) {}
        }
    }

    public function finishProcess($id) {
        $antrean = AntreanPemesanan::find($id);
        if ($antrean && $antrean->status === 'Diproses') {
            $antrean->status = 'Selesai';
            $antrean->waktu_selesai_aktual = Carbon::now()->format('H:i:s');
            $antrean->save();

            try {
                app(\App\Services\GoogleSheetsService::class)->syncTable('antrean_pemesanan');
            } catch (\Throwable $e) {}
        }
    }

    public function moveToKasir($id) {
        return redirect()->route('admin.pos', ['antrean_id' => $id]);
    }

    public function saveWalkIn() {
        if (auth()->check() && (auth()->user()->role === 'Kapster' || (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('Kapster')))) {
            $this->walkInKapster = auth()->id();
        }

        if (empty($this->walkInTime)) {
            $this->walkInTime = Carbon::now()->format('H:i:s');
        }

        $this->validate([
            'walkInName' => 'required|string|max:255',
            'walkInLayanan' => 'required|exists:layanan,id',
            'walkInKapster' => 'required',
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
            } catch (\Throwable $e) {}

            $kapsterId = $this->walkInKapster;
            if ($kapsterId === 'any' || empty($kapsterId)) {
                $kapster = User::whereHas('roles', function($q){$q->where('name', 'Kapster');})->inRandomOrder()->first();
                if (!$kapster) {
                    $this->addError('walkInKapster', 'Mohon maaf, saat ini tidak ada kapster yang tersedia.');
                    return;
                }
                $kapsterId = $kapster->id;
            }

            $tanggalWalkIn = Carbon::today()->format('Y-m-d');
            if ($this->filterTanggal === 'tomorrow') {
                $tanggalWalkIn = Carbon::tomorrow()->format('Y-m-d');
            } elseif ($this->filterTanggal === 'custom' && !empty($this->customTanggal)) {
                $tanggalWalkIn = $this->customTanggal;
            }

            $antrean = AntreanPemesanan::create([
                'pelanggan_id' => $user->id,
                'kapster_id' => $kapsterId,
                'layanan_id' => $this->walkInLayanan,
                'tipe_antrean' => 'WalkIn',
                'nomor_antrean' => 'WLK-' . strtoupper(Str::random(5)),
                'tanggal' => $tanggalWalkIn,
                'waktu_estimasi_mulai' => $this->walkInTime,
                'status' => 'Menunggu'
            ]);

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
            } catch (\Throwable $e) {}

            $this->showWalkInModal = false;
            $this->reset(['walkInName', 'walkInLayanan', 'walkInTime']);
            if (auth()->check() && (auth()->user()->role === 'Kapster' || (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('Kapster')))) {
                $this->walkInKapster = auth()->id();
            } else {
                $this->reset('walkInKapster');
            }

            session()->flash('sync_success', 'Antrean Walk-In berhasil ditambahkan! Nomor Antrean: ' . $antrean->nomor_antrean);
        } catch (\Throwable $e) {
            $this->addError('walkInName', 'Gagal menambahkan antrean: ' . $e->getMessage());
        }
    }

    public function with() {
        // Auto-pull dari Google Sheets jika cache habis (setiap 10 detik saat wire:poll)
        try {
            if (!cache()->has('sheets_sync_antrean_board')) {
                app(\App\Services\GoogleSheetsService::class)->pullTable('antrean_pemesanan');
                cache()->put('sheets_sync_antrean_board', true, now()->addSeconds(10));
            }
        } catch (\Throwable $e) {}

        $isKapster = auth()->check() && auth()->user()->role === 'Kapster';
        if ($isKapster && $this->filterKapster === 'all') {
            $this->filterKapster = (string)auth()->id();
        }

        $kapsters = User::whereHas('roles', function($q){$q->where('name', 'Kapster');})->get();
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();

        // Ringkasan status kursi per kapster (untuk monitoring kerja setiap kapster)
        $kapsterSummary = [];
        foreach ($kapsters as $k) {
            $curr = AntreanPemesanan::where('kapster_id', $k->id)
                ->where('status', 'Diproses')
                ->with(['layanan', 'pelanggan'])
                ->orderBy('waktu_mulai_aktual', 'desc')
                ->first();

            $wait = AntreanPemesanan::where('kapster_id', $k->id)
                ->where('status', 'Menunggu')
                ->where(function($q) use ($today) {
                    $q->whereDate('tanggal', '>=', $today)
                      ->orWhereNull('tanggal');
                })
                ->count();

            $kapsterSummary[$k->id] = [
                'kapster' => $k,
                'current' => $curr,
                'waiting_count' => $wait,
            ];
        }

        $query = AntreanPemesanan::with(['pelanggan', 'kapster', 'layanan']);

        // Filter Tanggal
        if ($this->filterTanggal === 'today') {
            $query->whereDate('tanggal', $today);
        } elseif ($this->filterTanggal === 'tomorrow') {
            $query->whereDate('tanggal', $tomorrow);
        } elseif ($this->filterTanggal === 'all') {
            // Tampilkan semua antrean aktif (Menunggu & Diproses) atau tanggal hari ini ke depan
            $query->where(function($q) use ($today) {
                $q->whereIn('status', ['Menunggu', 'Diproses'])
                  ->orWhereDate('tanggal', $today);
            });
        } elseif ($this->filterTanggal === 'history') {
            // Semua tanpa filter tanggal
        } elseif ($this->filterTanggal === 'custom' && !empty($this->customTanggal)) {
            $query->whereDate('tanggal', $this->customTanggal);
        }

        // Filter Kapster
        if ($this->filterKapster !== 'all') {
            $query->where('kapster_id', $this->filterKapster);
        }

        $semuaAntrean = $query->orderBy('tanggal', 'asc')->orderBy('waktu_estimasi_mulai', 'asc')->get();

        return [
            'isKapster' => $isKapster,
            'kapsters' => $kapsters,
            'kapsterSummary' => $kapsterSummary,
            'layanans' => Layanan::where('is_active', true)->get(),
            'menunggu' => $semuaAntrean->where('status', 'Menunggu'),
            'diproses' => $semuaAntrean->where('status', 'Diproses'),
            'selesai' => $semuaAntrean->where('status', 'Selesai'),
            'today' => $today,
            'tomorrow' => $tomorrow,
            'timeSlots' => [
                '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', 
                '12:00', '12:30', '13:00', '13:30', '14:00', '14:30',
                '15:00', '15:30', '16:00', '16:30', '17:00', '17:30',
                '18:00', '18:30', '19:00', '19:30', '20:00'
            ]
        ];
    }
};
?>

<div class="flex flex-col w-full h-full min-h-full space-y-6" wire:poll.5s>
    <!-- Top Control Bar -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <h1 class="font-headline-lg text-on-surface text-2xl lg:text-3xl font-bold">
                    @if($isKapster)
                        Papan Antrean &amp; Job Saya ({{ auth()->user()->nama }})
                    @else
                        Papan Kerja Antrean Terpadu
                    @endif
                </h1>
                <span class="text-xs bg-emerald-500/10 text-emerald-400 px-2.5 py-0.5 rounded-full font-medium border border-emerald-500/20 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Live Sync
                </span>
            </div>
            <p class="font-body-md text-sm text-on-surface-variant">
                Manajemen antrean real-time. Setiap kapster mengerjakan pekerjaannya masing-masing.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
            <!-- Date Filter Pills -->
            <div class="flex items-center gap-1 bg-surface-container-high p-1 rounded-xl border border-outline-variant/30 text-xs">
                <button 
                    wire:click="$set('filterTanggal', 'all')" 
                    type="button" 
                    class="px-3 py-1.5 rounded-lg font-semibold transition-all {{ $filterTanggal === 'all' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Semua Aktif
                </button>
                <button 
                    wire:click="$set('filterTanggal', 'today')" 
                    type="button" 
                    class="px-3 py-1.5 rounded-lg font-semibold transition-all {{ $filterTanggal === 'today' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Hari Ini
                </button>
                <button 
                    wire:click="$set('filterTanggal', 'tomorrow')" 
                    type="button" 
                    class="px-3 py-1.5 rounded-lg font-semibold transition-all {{ $filterTanggal === 'tomorrow' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Besok
                </button>
                <input 
                    type="date" 
                    wire:model.live="customTanggal" 
                    wire:change="$set('filterTanggal', 'custom')"
                    class="bg-surface-container text-on-surface text-xs rounded-lg px-2 py-1 border border-outline-variant/50 outline-none cursor-pointer">
            </div>

            <!-- Manual Sync Button -->
            <button wire:click="syncFromSheets" type="button" class="bg-surface-container-high hover:bg-surface-container-highest text-primary border border-primary/30 text-xs font-bold rounded-xl px-3.5 py-2.5 flex items-center justify-center gap-1.5 transition-all shadow-sm">
                <span class="material-symbols-outlined text-[18px] animate-spin" wire:loading wire:target="syncFromSheets">sync</span>
                <span class="material-symbols-outlined text-[18px]" wire:loading.remove wire:target="syncFromSheets">sync</span>
                <span>Sinkron Spreadsheet</span>
            </button>

            <!-- Action Button -->
            <button wire:click="openWalkInModal" class="bg-primary hover:bg-primary-fixed text-on-primary text-xs font-bold rounded-xl px-4 py-2.5 flex items-center justify-center gap-1.5 transition-all shadow-md shadow-primary/20 shrink-0 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>Input Walk-In</span>
            </button>
        </div>
    </div>

    @if(session('sync_success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-3 rounded-xl text-xs flex items-center gap-2">
            <span class="material-symbols-outlined text-base">check_circle</span>
            <span>{{ session('sync_success') }}</span>
        </div>
    @endif
    @if(session('sync_error'))
        <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 p-3 rounded-xl text-xs flex items-center gap-2">
            <span class="material-symbols-outlined text-base">error</span>
            <span>{{ session('sync_error') }}</span>
        </div>
    @endif

    <!-- Kursi & Job Setiap Kapster (Card Lane Selector) -->
    <div class="bg-surface-container/60 rounded-2xl p-4 border border-outline-variant/30 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs uppercase font-bold tracking-wider text-on-surface-variant flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[18px] text-primary">airline_seat_recline_normal</span>
                Status Kursi &amp; Pekerjaan Setiap Kapster
            </span>
            <span class="text-[11px] text-on-surface-variant">Klik kartu kapster untuk melihat job spesifiknya</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Tab Semua Kapster -->
            <button 
                wire:click="$set('filterKapster', 'all')"
                type="button"
                class="p-3.5 rounded-xl border text-left transition-all flex flex-col justify-between gap-2 {{ $filterKapster === 'all' ? 'bg-primary/10 border-primary shadow-sm' : 'bg-surface-container-high/60 border-outline-variant/30 hover:border-outline-variant hover:bg-surface-container-high' }}">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-xs {{ $filterKapster === 'all' ? 'text-primary' : 'text-on-surface' }}">Semua Kapster</span>
                    <span class="material-symbols-outlined text-[18px] {{ $filterKapster === 'all' ? 'text-primary' : 'text-on-surface-variant' }}">groups</span>
                </div>
                <div class="text-[11px] text-on-surface-variant">
                    Tampilkan antrean seluruh staf terpadu
                </div>
            </button>

            <!-- Cards Per-Kapster -->
            @foreach($kapsters as $k)
                @php
                    $kStat = $kapsterSummary[$k->id] ?? null;
                    $isBusy = $kStat && $kStat['current'];
                    $isSelected = (string)$filterKapster === (string)$k->id;
                @endphp
                <button 
                    wire:click="$set('filterKapster', '{{ $k->id }}')"
                    type="button"
                    class="p-3.5 rounded-xl border text-left transition-all flex flex-col justify-between gap-2 relative {{ $isSelected ? 'bg-primary/15 border-primary shadow-md' : 'bg-surface-container-high/60 border-outline-variant/30 hover:border-outline-variant hover:bg-surface-container-high' }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-primary/20 text-primary flex items-center justify-center font-bold text-[10px]">
                                {{ substr($k->nama, 0, 2) }}
                            </div>
                            <span class="font-bold text-xs text-on-surface truncate">{{ $k->nama }}</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ ($kStat['waiting_count'] ?? 0) > 0 ? 'bg-primary/20 text-primary' : 'bg-surface-container-highest text-on-surface-variant' }}">
                            {{ $kStat['waiting_count'] ?? 0 }} Antre
                        </span>
                    </div>

                    <div class="text-[11px] mt-1">
                        @if($isBusy)
                            <div class="text-amber-400 font-semibold flex items-center gap-1.5 truncate">
                                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                                <span class="truncate">Mencukur: #{{ $kStat['current']->nomor_antrean }}</span>
                            </div>
                            <span class="text-[10px] text-on-surface-variant block truncate">
                                {{ optional($kStat['current']->layanan)->nama_layanan ?? '-' }}
                            </span>
                        @else
                            <div class="text-emerald-400 font-medium flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                                <span>Standby / Siap Melayani</span>
                            </div>
                            <span class="text-[10px] text-on-surface-variant block">Kursi Kosong</span>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
    </div>

    <!-- Kanban Board (3 Kolom: Menunggu, Sedang Diproses, Selesai) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 flex-1 min-h-[550px]">
        <!-- Column 1: Menunggu -->
        <div class="flex flex-col h-full bg-surface-container-low rounded-2xl shadow-lg border border-outline-variant/30 overflow-hidden">
            <div class="p-4 bg-surface-container/60 border-b border-outline-variant/30 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                    <h2 class="font-bold text-base text-on-surface">Menunggu Giliran</h2>
                </div>
                <span class="bg-amber-400/10 text-amber-400 border border-amber-400/20 font-mono text-xs px-2.5 py-0.5 rounded-full font-bold">
                    {{ $menunggu->count() }}
                </span>
            </div>
            <div class="p-4 overflow-y-auto space-y-3.5 flex-1 max-h-[700px]">
                @forelse($menunggu as $a)
                    @php
                        $isToday = $a->tanggal === $today;
                        $isTomorrow = $a->tanggal === $tomorrow;
                    @endphp
                    <div class="bg-surface-container rounded-xl p-4 shadow-sm hover:shadow-md transition-all border border-outline-variant/30 relative">
                        <div class="flex justify-between items-start mb-2.5">
                            <div>
                                <h3 class="font-bold text-sm text-on-surface leading-tight">{{ optional($a->pelanggan)->nama ?? 'Pelanggan Umum' }}</h3>
                                <p class="text-xs text-primary font-medium mt-0.5">{{ optional($a->layanan)->nama_layanan ?? '-' }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="font-mono text-xs px-2 py-0.5 rounded font-bold {{ $a->tipe_antrean === 'Online' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'bg-purple-500/20 text-purple-400 border border-purple-500/30' }}">
                                    {{ $a->nomor_antrean }}
                                </span>
                            </div>
                        </div>

                        <!-- Info Jadwal & Kapster -->
                        <div class="flex items-center justify-between text-xs text-on-surface-variant my-2.5 py-2 border-y border-outline-variant/20">
                            <span class="flex items-center gap-1 font-mono">
                                <span class="material-symbols-outlined text-[14px]">schedule</span>
                                <strong class="{{ $isTomorrow ? 'text-primary' : ($isToday ? 'text-emerald-400' : 'text-on-surface') }}">
                                    @if($isToday)
                                        Hari Ini {{ substr($a->waktu_estimasi_mulai, 0, 5) }}
                                    @elseif($isTomorrow)
                                        Besok {{ substr($a->waktu_estimasi_mulai, 0, 5) }}
                                    @else
                                        {{ $a->tanggal }} {{ substr($a->waktu_estimasi_mulai, 0, 5) }}
                                    @endif
                                </strong>
                            </span>
                            <span class="flex items-center gap-1 text-on-surface font-semibold">
                                <span class="material-symbols-outlined text-[14px] text-primary">person</span>
                                {{ optional($a->kapster)->nama ?? 'Semua' }}
                            </span>
                        </div>

                        <button wire:click="startProcess({{ $a->id }})" class="w-full bg-primary hover:bg-primary-fixed text-on-primary font-bold text-xs rounded-lg py-2.5 transition-all flex items-center justify-center gap-1.5 shadow-sm shadow-primary/20">
                            <span class="material-symbols-outlined text-[16px]">play_arrow</span>
                            <span>Mulai Pengerjaan</span>
                        </button>
                    </div>
                @empty
                    <div class="text-center text-on-surface-variant py-10 opacity-60 text-xs">
                        Tidak ada antrean menunggu untuk kriteria ini.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Column 2: Sedang Diproses -->
        <div class="flex flex-col h-full bg-surface-container-low rounded-2xl shadow-lg border border-outline-variant/30 overflow-hidden relative">
            <div class="p-4 bg-surface-container/60 border-b border-outline-variant/30 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-3 h-3 rounded-full bg-emerald-400 animate-pulse shadow-[0_0_8px_rgba(52,211,153,0.6)]"></div>
                    <h2 class="font-bold text-base text-on-surface">Sedang Diproses</h2>
                </div>
                <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono text-xs px-2.5 py-0.5 rounded-full font-bold">
                    {{ $diproses->count() }}
                </span>
            </div>
            <div class="p-4 overflow-y-auto space-y-3.5 flex-1 max-h-[700px]">
                @forelse($diproses as $a)
                    @php
                        $isToday = $a->tanggal === $today;
                        $isTomorrow = $a->tanggal === $tomorrow;
                    @endphp
                    <div class="bg-surface-container rounded-xl p-4 shadow-xl border-l-[4px] border-emerald-500 relative group border-t border-r border-b border-outline-variant/30">
                        <div class="flex justify-between items-start mb-2.5">
                            <div>
                                <h3 class="font-bold text-sm text-on-surface leading-tight">{{ optional($a->pelanggan)->nama ?? 'Pelanggan Umum' }}</h3>
                                <p class="text-xs text-primary font-medium mt-0.5">{{ optional($a->layanan)->nama_layanan ?? '-' }}</p>
                            </div>
                            <span class="font-mono text-xs px-2 py-0.5 rounded font-bold {{ $a->tipe_antrean === 'Online' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'bg-purple-500/20 text-purple-400 border border-purple-500/30' }}">
                                {{ $a->nomor_antrean }}
                            </span>
                        </div>

                        <!-- Kapster yang menangani -->
                        <div class="bg-surface-container-high/80 rounded-lg p-2.5 text-xs space-y-1 my-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-on-surface-variant">Kapster Bertugas:</span>
                                <strong class="text-primary font-bold">{{ optional($a->kapster)->nama ?? '-' }}</strong>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-on-surface-variant">
                                <span>Mulai: {{ substr($a->waktu_mulai_aktual ?? $a->waktu_estimasi_mulai, 0, 5) }} WIB</span>
                                <span class="text-emerald-400 flex items-center gap-1 font-medium">
                                    <span class="material-symbols-outlined text-[13px] animate-spin">sync</span>
                                    Sedang Memotong
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <button wire:click="finishProcess({{ $a->id }})" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-lg py-2 transition-all flex items-center justify-center gap-1 shadow-sm">
                                <span class="material-symbols-outlined text-[16px]">check</span>
                                <span>Selesai</span>
                            </button>
                            <button wire:click="moveToKasir({{ $a->id }})" class="bg-primary hover:bg-primary-fixed text-on-primary font-bold text-xs rounded-lg py-2 transition-all flex items-center justify-center gap-1 shadow-sm shadow-primary/20">
                                <span class="material-symbols-outlined text-[16px]">point_of_sale</span>
                                <span>Ke Kasir</span>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-on-surface-variant py-10 opacity-60 text-xs">
                        Tidak ada pengerjaan saat ini.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Column 3: Selesai -->
        <div class="flex flex-col h-full bg-surface-container-low rounded-2xl shadow-inner border border-outline-variant/30 overflow-hidden opacity-95">
            <div class="p-4 bg-surface-container/60 border-b border-outline-variant/30 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-3 h-3 rounded-full bg-surface-variant"></div>
                    <h2 class="font-bold text-base text-on-surface-variant">Selesai</h2>
                </div>
                <span class="bg-surface-container-highest text-on-surface-variant font-mono text-xs px-2.5 py-0.5 rounded-full font-bold">
                    {{ $selesai->count() }}
                </span>
            </div>
            <div class="p-4 overflow-y-auto space-y-3.5 flex-1 max-h-[700px]">
                @forelse($selesai as $a)
                    <div class="bg-surface-container/70 rounded-xl p-4 shadow-sm border border-outline-variant/20 relative group hover:border-outline-variant/50 transition-all">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h3 class="font-bold text-xs text-on-surface-variant leading-tight">{{ optional($a->pelanggan)->nama ?? 'Pelanggan Umum' }}</h3>
                                <p class="text-[11px] text-on-surface-variant/70">{{ optional($a->layanan)->nama_layanan ?? '-' }}</p>
                            </div>
                            <span class="font-mono text-[10px] px-2 py-0.5 rounded font-bold bg-surface-container-highest text-on-surface-variant">
                                {{ $a->nomor_antrean }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-on-surface-variant pt-2 border-t border-outline-variant/20">
                            <span>Kapster: <strong>{{ optional($a->kapster)->nama ?? '-' }}</strong></span>
                            <span>{{ $a->tanggal }}</span>
                        </div>
                        <button wire:click="moveToKasir({{ $a->id }})" class="w-full bg-surface-container-high hover:bg-surface-container-highest text-on-surface text-xs font-semibold rounded-lg py-1.5 mt-2.5 transition-colors border border-outline-variant/30 flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">receipt</span>
                            <span>Detail / Nota Kasir</span>
                        </button>
                    </div>
                @empty
                    <div class="text-center text-on-surface-variant py-10 opacity-60 text-xs">
                        Belum ada antrean selesai untuk filter ini.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Walk-In Modal -->
    @if($showWalkInModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg bg-surface-container-high rounded-2xl shadow-2xl overflow-hidden border border-outline-variant/40 animate-[scale-up_0.2s_ease-out]">
                <!-- Header -->
                <div class="flex items-center justify-between p-5 bg-surface-container-highest border-b border-outline-variant/30">
                    <h2 class="font-bold text-lg text-on-surface">Tambah Antrean Walk-In</h2>
                    <button wire:click="$set('showWalkInModal', false)" class="text-on-surface-variant hover:text-primary transition-colors focus:outline-none">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <!-- Body -->
                <div class="p-5 space-y-4">
                    <!-- Customer Name -->
                    <div class="space-y-1.5">
                        <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Nama Pelanggan</label>
                        <input wire:model="walkInName" class="w-full bg-surface-container text-on-surface text-sm px-4 py-2.5 rounded-xl border border-outline-variant/40 focus:outline-none focus:border-primary transition-colors" placeholder="Masukkan nama pelanggan..." type="text">
                        @error('walkInName') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <!-- Service & Barber Row -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Layanan</label>
                            <select wire:model="walkInLayanan" class="w-full bg-surface-container text-on-surface text-sm px-3.5 py-2.5 rounded-xl border border-outline-variant/40 focus:outline-none focus:border-primary transition-colors">
                                <option value="">Pilih Layanan</option>
                                @foreach($layanans as $l)
                                    <option value="{{ $l->id }}">{{ $l->nama_layanan }}</option>
                                @endforeach
                            </select>
                            @error('walkInLayanan') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Kapster Bertugas</label>
                            @if($isKapster)
                                <div class="w-full bg-surface-container text-on-surface text-sm px-4 py-2.5 rounded-xl border border-outline-variant/40 flex items-center justify-between">
                                    <span>{{ auth()->user()->nama }}</span>
                                    <span class="text-xs text-primary font-bold">(Anda)</span>
                                </div>
                            @else
                                <select wire:model="walkInKapster" class="w-full bg-surface-container text-on-surface text-sm px-3.5 py-2.5 rounded-xl border border-outline-variant/40 focus:outline-none focus:border-primary transition-colors">
                                    <option value="">Pilih Kapster</option>
                                    <option value="any">Siapa Saja (Tersedia)</option>
                                    @foreach($kapsters as $k)
                                        <option value="{{ $k->id }}">{{ $k->nama }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('walkInKapster') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <!-- Time Slot Grid -->
                    <div class="space-y-2 pt-2">
                        <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Pilih Jam</label>
                        <div class="grid grid-cols-4 gap-2">
                            <label class="cursor-pointer col-span-2">
                                <input wire:model="walkInTime" value="{{ Carbon\Carbon::now()->format('H:i:s') }}" class="peer sr-only" type="radio">
                                <div class="py-2 rounded-lg border border-outline-variant/30 bg-surface-container text-on-surface text-center hover:bg-surface-container-high transition-colors text-xs font-mono peer-checked:border-primary peer-checked:bg-primary/15 peer-checked:text-primary font-bold flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-sm">schedule</span>
                                    <span>Sekarang ({{ Carbon\Carbon::now()->format('H:i') }})</span>
                                </div>
                            </label>
                            @foreach($timeSlots as $time)
                                <label class="cursor-pointer">
                                    <input wire:model="walkInTime" value="{{ $time }}" class="peer sr-only" type="radio">
                                    <div class="py-2 rounded-lg border border-outline-variant/30 bg-surface-container text-on-surface text-center hover:bg-surface-container-high transition-colors text-xs font-mono peer-checked:border-primary peer-checked:bg-primary/15 peer-checked:text-primary font-bold">
                                        {{ $time }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('walkInTime') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- Footer -->
                <div class="p-5 bg-surface-container-highest border-t border-outline-variant/30 flex items-center justify-end gap-3">
                    <button wire:click="$set('showWalkInModal', false)" type="button" class="px-4 py-2 text-xs font-semibold text-on-surface-variant hover:text-on-surface transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button wire:click="saveWalkIn" wire:loading.attr="disabled" type="button" class="px-5 py-2.5 bg-primary text-on-primary text-xs font-bold rounded-xl hover:bg-primary-fixed transition-all flex items-center gap-1.5 shadow-md shadow-primary/20 cursor-pointer">
                        <span wire:loading.remove wire:target="saveWalkIn" class="material-symbols-outlined text-[16px]">check</span>
                        <span wire:loading wire:target="saveWalkIn" class="material-symbols-outlined text-[16px] animate-spin">refresh</span>
                        <span>Simpan Antrean</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
