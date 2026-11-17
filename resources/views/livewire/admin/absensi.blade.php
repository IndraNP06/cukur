<?php

use App\Models\Absensi;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Kelola Absensi Kehadiran - Ran CukurIn')] class extends Component {
    use WithPagination;

    // Mode Tampilan: 'calendar' atau 'table'
    public $viewMode = 'calendar';

    // Filters
    public $filterKapster = 'all'; // 'all' atau user_id
    public $filterStatus = 'all';  // 'all', 'Hadir', 'Izin', 'Sakit', 'Alpa'
    public $filterTanggal = '';    // '' = Semua Kehadiran, atau 'YYYY-MM-DD'
    public $search = '';

    // Calendar State
    public $calendarMonth;
    public $calendarYear;

    // Modal Detail Hari (saat tanggal di kalender diklik)
    public $selectedDay = null;
    public $showDayModal = false;

    // Modal Form Catat / Edit
    public $showModal = false;
    public $isEdit = false;
    public $absensiId;
    public $user_id;
    public $tanggal;
    public $status = 'Hadir';
    public $keterangan;
    
    public $staffList = [];

    public function rules()
    {
        return [
            'user_id' => 'required|exists:pengguna,id',
            'tanggal' => 'required|date',
            'status' => 'required|in:Hadir,Sakit,Izin,Alpa',
            'keterangan' => 'nullable|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'user_id.required' => 'Pilih staf terlebih dahulu.',
            'tanggal.required' => 'Tanggal absensi wajib diisi.',
            'status.required' => 'Pilih status kehadiran.',
        ];
    }

    public function mount()
    {
        $this->calendarMonth = (int) date('n');
        $this->calendarYear = (int) date('Y');
        // Kosongkan filterTanggal secara default agar mode tabel bisa menampilkan semua kehadiran
        $this->filterTanggal = '';
        $this->staffList = User::whereIn('role', ['Kapster', 'Pemilik'])->orderBy('role')->orderBy('nama')->get();
    }

    // Navigasi Kalender
    public function prevMonth()
    {
        $c = Carbon::create($this->calendarYear, $this->calendarMonth, 1)->subMonth();
        $this->calendarMonth = (int) $c->month;
        $this->calendarYear = (int) $c->year;
    }

    public function nextMonth()
    {
        $c = Carbon::create($this->calendarYear, $this->calendarMonth, 1)->addMonth();
        $this->calendarMonth = (int) $c->month;
        $this->calendarYear = (int) $c->year;
    }

    public function todayMonth()
    {
        $this->calendarMonth = (int) date('n');
        $this->calendarYear = (int) date('Y');
    }

    // Filter Cepat Tanggal
    public function setFilterToday()
    {
        $this->filterTanggal = date('Y-m-d');
        $this->viewMode = 'table';
        $this->resetPage();
    }

    public function setFilterYesterday()
    {
        $this->filterTanggal = Carbon::yesterday()->toDateString();
        $this->viewMode = 'table';
        $this->resetPage();
    }

    public function setFilterAllDays()
    {
        $this->filterTanggal = '';
        $this->resetPage();
    }

    public function resetAllFilters()
    {
        $this->filterKapster = 'all';
        $this->filterStatus = 'all';
        $this->filterTanggal = '';
        $this->search = '';
        $this->resetPage();
    }

    // Aksi Klik Tanggal di Kalender
    public function openDayModal($date)
    {
        $this->selectedDay = $date;
        $this->showDayModal = true;
    }

    public function closeDayModal()
    {
        $this->showDayModal = false;
        $this->selectedDay = null;
    }

    public function viewDayInTable($date)
    {
        $this->filterTanggal = $date;
        $this->viewMode = 'table';
        $this->showDayModal = false;
        $this->resetPage();
    }

    // Form Modal Actions
    public function create($presetDate = null, $presetUserId = null)
    {
        $this->resetValidation();
        $this->reset(['absensiId', 'keterangan']);
        $this->tanggal = $presetDate ?? ($this->filterTanggal ?: date('Y-m-d'));
        $this->user_id = $presetUserId ?? ($this->filterKapster !== 'all' ? $this->filterKapster : '');
        $this->status = 'Hadir';
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetValidation();
        $absensi = Absensi::findOrFail($id);
            
        $this->absensiId = $absensi->id;
        $this->user_id = $absensi->user_id;
        $this->tanggal = $absensi->tanggal;
        $this->status = $absensi->status;
        $this->keterangan = $absensi->keterangan;
        
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'user_id' => $this->user_id,
            'tanggal' => $this->tanggal,
            'status' => $this->status,
            'keterangan' => $this->keterangan,
        ];

        // Validasi duplikat pada tanggal yang sama
        if (!$this->isEdit) {
            $exists = Absensi::where('user_id', $this->user_id)
                ->where('tanggal', $this->tanggal)
                ->exists();
            if ($exists) {
                $this->addError('user_id', 'Staf ini sudah memiliki catatan absensi pada tanggal tersebut.');
                return;
            }
        }

        if ($this->isEdit) {
            $absensi = Absensi::findOrFail($this->absensiId);
            $absensi->update($data);
            session()->flash('message', 'Catatan absensi berhasil diperbarui.');
        } else {
            Absensi::create($data);
            session()->flash('message', 'Catatan absensi baru berhasil ditambahkan.');
        }

        $this->showModal = false;
        // Refresh jika modal detail hari terbuka
        if ($this->showDayModal && $this->selectedDay) {
            $this->selectedDay = $this->tanggal;
        }
    }

    public function deleteAbsensi($id)
    {
        $absensi = Absensi::findOrFail($id);
        $absensi->delete();
        session()->flash('message', 'Catatan absensi berhasil dihapus.');
    }

    public function with()
    {
        // 1. Data untuk Mode Kalender
        $startOfMonth = Carbon::create($this->calendarYear, $this->calendarMonth, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $startOfCalendar = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $endOfCalendar = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);

        $calendarQuery = Absensi::with('user')
            ->whereBetween('tanggal', [$startOfCalendar->format('Y-m-d'), $endOfCalendar->format('Y-m-d')]);

        if ($this->filterKapster !== 'all') {
            $calendarQuery->where('user_id', $this->filterKapster);
        }
        if ($this->filterStatus !== 'all') {
            $calendarQuery->where('status', $this->filterStatus);
        }
        if (!empty($this->search)) {
            $term = '%' . trim($this->search) . '%';
            $calendarQuery->where(function($q) use ($term) {
                $q->where('keterangan', 'like', $term)
                  ->orWhereHas('user', function($u) use ($term) {
                      $u->where('nama', 'like', $term);
                  });
            });
        }

        $calendarAbsensis = $calendarQuery->get()->groupBy('tanggal');

        $calendarDays = [];
        $curr = $startOfCalendar->copy();
        while ($curr->lte($endOfCalendar)) {
            $dStr = $curr->format('Y-m-d');
            $dayRecords = $calendarAbsensis->get($dStr, collect());

            $calendarDays[] = [
                'date' => $dStr,
                'dayNumber' => $curr->day,
                'isCurrentMonth' => $curr->month === (int) $this->calendarMonth,
                'isToday' => $dStr === date('Y-m-d'),
                'absensis' => $dayRecords,
                'totalHadir' => $dayRecords->where('status', 'Hadir')->count(),
                'totalIzin' => $dayRecords->where('status', 'Izin')->count(),
                'totalSakit' => $dayRecords->where('status', 'Sakit')->count(),
                'totalAlpa' => $dayRecords->where('status', 'Alpa')->count(),
            ];
            $curr->addDay();
        }

        // 2. Data untuk Mode Tabel (Semua Kehadiran / Riwayat)
        $tableQuery = Absensi::with('user');

        if (!empty($this->filterTanggal)) {
            $tableQuery->where('tanggal', $this->filterTanggal);
        }
        if ($this->filterKapster !== 'all') {
            $tableQuery->where('user_id', $this->filterKapster);
        }
        if ($this->filterStatus !== 'all') {
            $tableQuery->where('status', $this->filterStatus);
        }
        if (!empty($this->search)) {
            $term = '%' . trim($this->search) . '%';
            $tableQuery->where(function($q) use ($term) {
                $q->where('keterangan', 'like', $term)
                  ->orWhereHas('user', function($u) use ($term) {
                      $u->where('nama', 'like', $term);
                  });
            });
        }

        $absensis = $tableQuery->orderBy('tanggal', 'desc')->orderBy('id', 'desc')->paginate(12);

        // 3. Statistik / Ringkasan Kehadiran (Berdasarkan Bulan Kalender & Filter Kapster)
        $statsYearMonth = sprintf('%04d-%02d', $this->calendarYear, $this->calendarMonth);
        $statsQuery = Absensi::where('tanggal', 'like', "$statsYearMonth%");
        if ($this->filterKapster !== 'all') {
            $statsQuery->where('user_id', $this->filterKapster);
        }
        $statHadir = (clone $statsQuery)->where('status', 'Hadir')->count();
        $statIzin = (clone $statsQuery)->where('status', 'Izin')->count();
        $statSakit = (clone $statsQuery)->where('status', 'Sakit')->count();
        $statAlpa = (clone $statsQuery)->where('status', 'Alpa')->count();
        $statTotal = $statHadir + $statIzin + $statSakit + $statAlpa;

        // 4. Detail Hari Terpilih (Modal)
        $selectedDayRecords = collect();
        if ($this->showDayModal && $this->selectedDay) {
            $sQuery = Absensi::with('user')->where('tanggal', $this->selectedDay);
            if ($this->filterKapster !== 'all') {
                $sQuery->where('user_id', $this->filterKapster);
            }
            if ($this->filterStatus !== 'all') {
                $sQuery->where('status', $this->filterStatus);
            }
            $selectedDayRecords = $sQuery->get();
        }

        return [
            'calendarDays' => $calendarDays,
            'absensis' => $absensis,
            'statHadir' => $statHadir,
            'statIzin' => $statIzin,
            'statSakit' => $statSakit,
            'statAlpa' => $statAlpa,
            'statTotal' => $statTotal,
            'selectedDayRecords' => $selectedDayRecords,
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
    
    public function updatedFilterTanggal()
    {
        $this->resetPage();
    }

    public function updatedFilterKapster()
    {
        $this->resetPage();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }
};
?>

<div class="flex-grow flex flex-col relative bg-background">
    <div class="flex flex-col w-full h-full p-4 lg:p-6 space-y-6 text-on-surface">
        
        <!-- Header Actions & Title -->
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="font-headline-lg text-primary tracking-tight text-headline-lg font-bold">Kelola Absensi &amp; Kehadiran Staf</h1>
                    <span class="text-xs bg-emerald-500/10 text-emerald-400 px-2.5 py-0.5 rounded-full font-medium border border-emerald-500/20 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Live Attendance
                    </span>
                </div>
                <p class="text-body-md font-body-md text-on-surface-variant mt-1">
                    Pantau kalender kerja kapster, filter kehadiran harian, dan catat absensi staf barbershop.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Toggle View Mode: Kalender vs Tabel -->
                <div class="flex items-center bg-surface-container-high p-1 rounded-xl border border-outline-variant/30 text-xs shadow-sm">
                    <button 
                        wire:click="$set('viewMode', 'calendar')" 
                        type="button" 
                        class="px-3.5 py-2 rounded-lg font-bold flex items-center gap-1.5 transition-all {{ $viewMode === 'calendar' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                        <span class="material-symbols-outlined text-[18px]">calendar_month</span>
                        <span>Kalender Kehadiran</span>
                    </button>
                    <button 
                        wire:click="$set('viewMode', 'table')" 
                        type="button" 
                        class="px-3.5 py-2 rounded-lg font-bold flex items-center gap-1.5 transition-all {{ $viewMode === 'table' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                        <span class="material-symbols-outlined text-[18px]">format_list_bulleted</span>
                        <span>Semua Kehadiran</span>
                    </button>
                </div>

                <!-- Tombol Catat Absensi -->
                <button 
                    wire:click="create()" 
                    type="button"
                    class="flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-fixed text-on-primary rounded-xl font-label-md text-xs font-bold transition-all shadow-md shadow-primary/20 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
                    <span>Catat Absensi</span>
                </button>
            </div>
        </header>

        <!-- Flash Alert Message -->
        @if (session()->has('message'))
            <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-xl text-sm flex items-center gap-3 shadow-lg">
                <span class="material-symbols-outlined text-xl">check_circle</span>
                <span class="font-medium">{{ session('message') }}</span>
            </div>
        @endif

        <!-- Quick Summary Cards (Bulan Ini) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-4">
            <!-- Hadir -->
            <div class="bg-surface-container p-3.5 lg:p-4 rounded-xl border border-outline-variant/20 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] text-on-surface-variant uppercase font-bold tracking-wider">Hadir</span>
                    <h3 class="text-xl lg:text-2xl font-extrabold text-emerald-400 mt-0.5">{{ $statHadir }}</h3>
                    <span class="text-[10px] text-on-surface-variant">{{ $statTotal > 0 ? round(($statHadir / $statTotal) * 100) : 0 }}% kehadiran</span>
                </div>
                <div class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                </div>
            </div>

            <!-- Izin -->
            <div class="bg-surface-container p-3.5 lg:p-4 rounded-xl border border-outline-variant/20 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] text-on-surface-variant uppercase font-bold tracking-wider">Izin</span>
                    <h3 class="text-xl lg:text-2xl font-extrabold text-blue-400 mt-0.5">{{ $statIzin }}</h3>
                    <span class="text-[10px] text-on-surface-variant">Izin terencana</span>
                </div>
                <div class="w-10 h-10 rounded-full bg-blue-500/10 text-blue-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">event_note</span>
                </div>
            </div>

            <!-- Sakit -->
            <div class="bg-surface-container p-3.5 lg:p-4 rounded-xl border border-outline-variant/20 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] text-on-surface-variant uppercase font-bold tracking-wider">Sakit</span>
                    <h3 class="text-xl lg:text-2xl font-extrabold text-amber-400 mt-0.5">{{ $statSakit }}</h3>
                    <span class="text-[10px] text-on-surface-variant">Keterangan sakit</span>
                </div>
                <div class="w-10 h-10 rounded-full bg-amber-500/10 text-amber-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">healing</span>
                </div>
            </div>

            <!-- Alpa -->
            <div class="bg-surface-container p-3.5 lg:p-4 rounded-xl border border-outline-variant/20 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] text-on-surface-variant uppercase font-bold tracking-wider">Alpa</span>
                    <h3 class="text-xl lg:text-2xl font-extrabold text-rose-400 mt-0.5">{{ $statAlpa }}</h3>
                    <span class="text-[10px] text-on-surface-variant">Tanpa keterangan</span>
                </div>
                <div class="w-10 h-10 rounded-full bg-rose-500/10 text-rose-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">warning</span>
                </div>
            </div>
        </div>

        <!-- Toolbar Filter Lengkap: Per Hari, Per Kapster, Status Keterangan, & Pencarian -->
        <section class="p-4 bg-surface-container rounded-2xl border border-outline-variant/30 shadow-md space-y-3">
            <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                
                <!-- Quick Date Filter Presets -->
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="font-semibold text-on-surface-variant mr-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">filter_alt</span>
                        Filter Hari:
                    </span>
                    <button 
                        wire:click="setFilterToday" 
                        type="button"
                        class="px-3 py-1.5 rounded-lg font-bold transition-all {{ $filterTanggal === date('Y-m-d') ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest' }}">
                        Hari Ini
                    </button>
                    <button 
                        wire:click="setFilterYesterday" 
                        type="button"
                        class="px-3 py-1.5 rounded-lg font-bold transition-all {{ $filterTanggal === \Carbon\Carbon::yesterday()->toDateString() ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest' }}">
                        Kemarin
                    </button>
                    <button 
                        wire:click="setFilterAllDays" 
                        type="button"
                        class="px-3 py-1.5 rounded-lg font-bold transition-all {{ empty($filterTanggal) ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest' }}">
                        Semua Hari
                    </button>
                </div>

                <!-- Active Filter Indicator -->
                <div class="text-xs text-on-surface-variant flex items-center gap-2 self-start lg:self-center bg-surface-container-high px-3 py-1 rounded-xl border border-outline-variant/30">
                    <span class="material-symbols-outlined text-[15px] text-primary">info</span>
                    <span>
                        @if($filterTanggal)
                            Hari: <strong class="text-primary font-bold">{{ \Carbon\Carbon::parse($filterTanggal)->translatedFormat('d M Y') }}</strong>
                        @else
                            Menampilkan <strong class="text-primary font-bold">Semua Hari</strong>
                        @endif
                        @if($filterKapster !== 'all')
                            • Kapster: <strong class="text-on-surface font-semibold">{{ optional(collect($staffList)->firstWhere('id', $filterKapster))->nama }}</strong>
                        @endif
                        @if($filterStatus !== 'all')
                            • Status: <strong class="text-on-surface font-semibold">{{ $filterStatus }}</strong>
                        @endif
                    </span>
                </div>
            </div>

            <!-- Dropdown Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2 border-t border-surface-container-highest">
                
                <!-- Filter Staf / Kapster -->
                <div class="flex items-center gap-2.5 px-3 py-2 bg-surface-container-high rounded-xl border border-outline-variant/30 shadow-inner">
                    <span class="material-symbols-outlined text-primary text-[20px]">person</span>
                    <div class="flex flex-col flex-grow">
                        <label class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Pilih Kapster / Staf</label>
                        <select wire:model.live="filterKapster" class="bg-transparent border-none outline-none text-on-surface font-semibold text-xs cursor-pointer">
                            <option value="all" class="bg-surface-container-high text-on-surface">Semua Staf &amp; Kapster</option>
                            @foreach($staffList as $staff)
                                <option value="{{ $staff->id }}" class="bg-surface-container-high text-on-surface">
                                    {{ $staff->nama }} ({{ $staff->role }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Filter Keterangan / Status -->
                <div class="flex items-center gap-2.5 px-3 py-2 bg-surface-container-high rounded-xl border border-outline-variant/30 shadow-inner">
                    <span class="material-symbols-outlined text-primary text-[20px]">assignment_turned_in</span>
                    <div class="flex flex-col flex-grow">
                        <label class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Keterangan Status</label>
                        <select wire:model.live="filterStatus" class="bg-transparent border-none outline-none text-on-surface font-semibold text-xs cursor-pointer">
                            <option value="all" class="bg-surface-container-high text-on-surface">Semua Keterangan</option>
                            <option value="Hadir" class="bg-surface-container-high text-emerald-400">Hadir</option>
                            <option value="Izin" class="bg-surface-container-high text-blue-400">Izin</option>
                            <option value="Sakit" class="bg-surface-container-high text-amber-400">Sakit</option>
                            <option value="Alpa" class="bg-surface-container-high text-rose-400">Alpa</option>
                        </select>
                    </div>
                </div>

                <!-- Filter Tanggal Spesifik (Per Hari) -->
                <div class="flex items-center gap-2.5 px-3 py-2 bg-surface-container-high rounded-xl border border-outline-variant/30 shadow-inner">
                    <span class="material-symbols-outlined text-primary text-[20px]">calendar_today</span>
                    <div class="flex flex-col flex-grow">
                        <label class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Pilih Tanggal Hari</label>
                        <input wire:model.live="filterTanggal" type="date" class="bg-transparent border-none outline-none text-on-surface font-semibold text-xs cursor-pointer">
                    </div>
                    @if($filterTanggal)
                        <button wire:click="$set('filterTanggal', '')" type="button" title="Bersihkan tanggal (Semua Hari)" class="text-on-surface-variant hover:text-on-surface">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    @endif
                </div>

                <!-- Input Pencarian (Nama Staf / Keterangan) -->
                <div class="flex items-center gap-2.5 px-3 py-2 bg-surface-container-high rounded-xl border border-outline-variant/30 shadow-inner">
                    <span class="material-symbols-outlined text-on-surface-variant text-[20px]">search</span>
                    <div class="flex flex-col flex-grow">
                        <label class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Cari Staf / Catatan</label>
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Ketik nama atau alasan..." class="bg-transparent border-none outline-none text-on-surface font-semibold text-xs placeholder:text-on-surface-variant/50">
                    </div>
                    @if($search || $filterKapster !== 'all' || $filterStatus !== 'all' || $filterTanggal !== '')
                        <button wire:click="resetAllFilters" type="button" title="Reset Semua Filter" class="p-1 rounded-lg hover:bg-surface-container text-on-surface-variant hover:text-on-surface transition-colors">
                            <span class="material-symbols-outlined text-[16px]">restart_alt</span>
                        </button>
                    @endif
                </div>

            </div>
        </section>

        <!-- VIEW MODE 1: KALENDER KEHADIRAN KAPSTER -->
        @if($viewMode === 'calendar')
            <section class="bg-surface-container rounded-2xl border border-outline-variant/30 shadow-xl overflow-hidden flex flex-col">
                
                <!-- Calendar Month Header & Navigation -->
                <div class="p-4 lg:p-5 bg-surface-container-high flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-surface-container-highest">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary/20 text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[22px]">calendar_month</span>
                        </div>
                        <div>
                            <h2 class="font-headline-md text-lg font-bold text-on-surface flex items-center gap-2">
                                {{ \Carbon\Carbon::create($calendarYear, $calendarMonth, 1)->translatedFormat('F Y') }}
                            </h2>
                            <p class="text-xs text-on-surface-variant">Klik salah satu tanggal untuk melihat rincian staf atau mencatat kehadiran.</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Navigation Buttons -->
                        <div class="flex items-center bg-surface-container rounded-xl border border-outline-variant/30 p-1">
                            <button 
                                wire:click="prevMonth" 
                                type="button" 
                                title="Bulan Sebelumnya"
                                class="p-1.5 rounded-lg text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors">
                                <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                            </button>
                            <button 
                                wire:click="todayMonth" 
                                type="button" 
                                class="px-3 py-1 rounded-lg text-xs font-bold text-primary hover:bg-surface-container-high transition-colors">
                                Bulan Ini
                            </button>
                            <button 
                                wire:click="nextMonth" 
                                type="button" 
                                title="Bulan Berikutnya"
                                class="p-1.5 rounded-lg text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors">
                                <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                            </button>
                        </div>

                        <!-- Dropdown Jump Month & Year -->
                        <div class="flex items-center gap-1 bg-surface-container px-2 py-1.5 rounded-xl border border-outline-variant/30 text-xs">
                            <select wire:model.live="calendarMonth" class="bg-transparent border-none outline-none text-on-surface font-semibold cursor-pointer">
                                @for($m=1; $m<=12; $m++)
                                    <option value="{{ $m }}" class="bg-surface-container-high text-on-surface">
                                        {{ \Carbon\Carbon::create()->month($m)->translatedFormat('M') }}
                                    </option>
                                @endfor
                            </select>
                            <select wire:model.live="calendarYear" class="bg-transparent border-none outline-none text-on-surface font-semibold cursor-pointer">
                                @for($y=date('Y')-2; $y<=date('Y')+1; $y++)
                                    <option value="{{ $y }}" class="bg-surface-container-high text-on-surface">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Calendar Legend -->
                <div class="px-5 py-2.5 bg-surface-container border-b border-surface-container-highest flex flex-wrap items-center justify-between text-xs gap-3">
                    <div class="flex flex-wrap items-center gap-4 text-[11px]">
                        <span class="text-on-surface-variant font-semibold">Legenda Status:</span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            <span class="text-emerald-400 font-medium">Hadir</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                            <span class="text-blue-400 font-medium">Izin</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            <span class="text-amber-400 font-medium">Sakit</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                            <span class="text-rose-400 font-medium">Alpa</span>
                        </span>
                    </div>

                    <div class="text-[11px] text-on-surface-variant">
                        @if($filterKapster !== 'all')
                            Fokus Kapster: <strong class="text-primary">{{ optional(collect($staffList)->firstWhere('id', $filterKapster))->nama }}</strong>
                        @else
                            Menampilkan seluruh kapster
                        @endif
                    </div>
                </div>

                <!-- Days of Week Headers (Senin s/d Minggu) -->
                <div class="grid grid-cols-7 bg-surface-container-high border-b border-surface-container-highest text-center text-xs font-bold text-on-surface-variant uppercase tracking-wider py-2.5">
                    <div>Senin</div>
                    <div>Selasa</div>
                    <div>Rabu</div>
                    <div>Kamis</div>
                    <div>Jumat</div>
                    <div>Sabtu</div>
                    <div>Minggu</div>
                </div>

                <!-- Calendar Cells Grid -->
                <div class="grid grid-cols-7 divide-x divide-y divide-surface-container-highest/60 bg-surface-container">
                    @foreach($calendarDays as $day)
                        @php
                            $hasAbsensi = $day['absensis']->count() > 0;
                        @endphp
                        <div 
                            wire:click="openDayModal('{{ $day['date'] }}')" 
                            class="min-h-[110px] lg:min-h-[130px] p-2 lg:p-2.5 flex flex-col justify-between transition-all group cursor-pointer relative hover:bg-surface-container-high/80 {{ !$day['isCurrentMonth'] ? 'opacity-40 bg-surface-container-low/40' : 'bg-surface-container' }} {{ $day['isToday'] ? 'ring-2 ring-inset ring-primary/80 bg-primary/5' : '' }}">
                            
                            <!-- Day Number & Badge -->
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center {{ $day['isToday'] ? 'bg-primary text-on-primary shadow-sm' : ($day['isCurrentMonth'] ? 'text-on-surface' : 'text-on-surface-variant') }}">
                                    {{ $day['dayNumber'] }}
                                </span>

                                @if($hasAbsensi)
                                    <span class="text-[10px] text-on-surface-variant font-medium bg-surface-container-high px-1.5 py-0.5 rounded-md">
                                        {{ $day['absensis']->count() }} staf
                                    </span>
                                @else
                                    <button 
                                        wire:click.stop="create('{{ $day['date'] }}')" 
                                        type="button" 
                                        title="Catat kehadiran pada tanggal ini"
                                        class="opacity-0 group-hover:opacity-100 p-1 text-primary hover:bg-surface-container-highest rounded-lg transition-opacity">
                                        <span class="material-symbols-outlined text-[14px]">add</span>
                                    </button>
                                @endif
                            </div>

                            <!-- Attendance Badges in the Cell -->
                            <div class="flex flex-col gap-1 flex-grow overflow-hidden">
                                @forelse($day['absensis']->take(3) as $abs)
                                    @php
                                        $bgClass = match($abs->status) {
                                            'Hadir' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                            'Izin' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                                            'Sakit' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                                            default => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                                        };
                                        $dotClass = match($abs->status) {
                                            'Hadir' => 'bg-emerald-400',
                                            'Izin' => 'bg-blue-400',
                                            'Sakit' => 'bg-amber-400',
                                            default => 'bg-rose-400',
                                        };
                                    @endphp
                                    <div class="px-1.5 py-0.5 rounded-md text-[10px] font-semibold border flex items-center justify-between gap-1 truncate {{ $bgClass }}" title="{{ optional($abs->user)->nama }} ({{ $abs->status }}): {{ $abs->keterangan ?: 'Tanpa keterangan' }}">
                                        <div class="flex items-center gap-1 truncate">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $dotClass }}"></span>
                                            <span class="truncate">{{ optional($abs->user)->nama ?? 'Staf' }}</span>
                                        </div>
                                        <span class="text-[9px] uppercase font-bold shrink-0 opacity-80">{{ substr($abs->status, 0, 3) }}</span>
                                    </div>
                                @empty
                                    <!-- Empty Cell Prompt on Hover -->
                                    <div class="hidden group-hover:flex items-center justify-center flex-grow text-[11px] text-on-surface-variant/50">
                                        <span>+ Catat</span>
                                    </div>
                                @endforelse

                                @if($day['absensis']->count() > 3)
                                    <span class="text-[10px] text-primary font-bold self-end mt-0.5">
                                        +{{ $day['absensis']->count() - 3 }} lainnya
                                    </span>
                                @endif
                            </div>

                        </div>
                    @endforeach
                </div>

            </section>
        @endif

        <!-- VIEW MODE 2: TABEL SEMUA KEHADIRAN / RIWAYAT LENGKAP -->
        @if($viewMode === 'table')
            <section class="bg-surface-container rounded-2xl border border-outline-variant/30 shadow-xl overflow-hidden flex flex-col">
                
                <div class="p-4 lg:p-5 bg-surface-container-high flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-surface-container-highest">
                    <div>
                        <h2 class="font-headline-md text-lg font-bold text-on-surface">Daftar Riwayat Kehadiran Staf</h2>
                        <p class="text-xs text-on-surface-variant">
                            @if($filterTanggal)
                                Menampilkan catatan untuk tanggal: <strong class="text-primary">{{ \Carbon\Carbon::parse($filterTanggal)->translatedFormat('d F Y') }}</strong>
                            @else
                                Menampilkan seluruh riwayat kehadiran kapster &amp; staf barbershop.
                            @endif
                        </p>
                    </div>
                    <div class="text-xs text-on-surface-variant bg-surface-container px-3 py-1.5 rounded-lg border border-outline-variant/30">
                        Total ditemukan: <strong class="text-primary">{{ $absensis->total() }}</strong> catatan
                    </div>
                </div>

                @if($absensis->count() > 0)
                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead>
                                <tr class="bg-surface-container-low text-on-surface-variant text-label-sm font-label-sm uppercase tracking-wider border-b border-surface-container-highest">
                                    <th class="px-5 py-3.5 font-semibold">Tanggal &amp; Hari</th>
                                    <th class="px-5 py-3.5 font-semibold">Profil Staf</th>
                                    <th class="px-5 py-3.5 font-semibold">Status Kehadiran</th>
                                    <th class="px-5 py-3.5 font-semibold">Keterangan / Alasan</th>
                                    <th class="px-5 py-3.5 font-semibold text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-body-md font-body-md text-on-surface divide-y divide-surface-container-highest/60">
                                @foreach($absensis as $absensi)
                                    @php
                                        $tgl = \Carbon\Carbon::parse($absensi->tanggal);
                                        $statusClass = match($absensi->status) {
                                            'Hadir' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                            'Izin' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                                            'Sakit' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                                            default => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                                        };
                                        $iconStatus = match($absensi->status) {
                                            'Hadir' => 'check_circle',
                                            'Izin' => 'event_note',
                                            'Sakit' => 'healing',
                                            default => 'warning',
                                        };
                                    @endphp
                                    <tr class="hover:bg-surface-container-highest/70 transition-colors group">
                                        <!-- Tanggal & Hari -->
                                        <td class="px-5 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-xs text-on-surface">{{ $tgl->translatedFormat('l, d M Y') }}</span>
                                                <span class="text-[11px] text-on-surface-variant">{{ $tgl->diffForHumans() }}</span>
                                            </div>
                                        </td>

                                        <!-- Profil Staf -->
                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-full bg-primary/20 text-primary flex items-center justify-center font-bold text-xs border border-primary/30">
                                                    {{ substr($absensi->user->nama ?? 'S', 0, 2) }}
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-xs text-on-surface">{{ $absensi->user->nama ?? 'Staf Tidak Dikenal' }}</span>
                                                    <span class="text-[11px] text-on-surface-variant">{{ $absensi->user->role ?? 'Kapster' }}</span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Status Kehadiran -->
                                        <td class="px-5 py-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-bold border inline-flex items-center gap-1.5 {{ $statusClass }}">
                                                <span class="material-symbols-outlined text-[15px]">{{ $iconStatus }}</span>
                                                <span>{{ $absensi->status }}</span>
                                            </span>
                                        </td>

                                        <!-- Keterangan Tambahan -->
                                        <td class="px-5 py-4 text-xs text-on-surface-variant max-w-xs truncate">
                                            {{ $absensi->keterangan ?: '-' }}
                                        </td>

                                        <!-- Aksi -->
                                        <td class="px-5 py-4 text-right">
                                            <div class="flex justify-end items-center gap-1.5">
                                                <button 
                                                    wire:click="edit({{ $absensi->id }})" 
                                                    type="button" 
                                                    class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container-highest rounded-lg transition-colors"
                                                    title="Edit Catatan">
                                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                                </button>
                                                <button 
                                                    wire:click="deleteAbsensi({{ $absensi->id }})" 
                                                    wire:confirm="Yakin ingin menghapus catatan absensi {{ $absensi->user->nama ?? 'staf ini' }} pada tanggal {{ $tgl->format('d/m/Y') }}?" 
                                                    type="button" 
                                                    class="p-1.5 text-on-surface-variant hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition-colors"
                                                    title="Hapus Catatan">
                                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-surface-container-highest bg-surface-container-high/40">
                        {{ $absensis->links() }}
                    </div>
                @else
                    <div class="p-12 text-center flex flex-col items-center justify-center gap-3">
                        <div class="w-14 h-14 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-3xl">event_busy</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-on-surface">Tidak Ada Catatan Absensi</h3>
                            <p class="text-xs text-on-surface-variant mt-1 max-w-md">
                                Tidak ditemukan data absensi untuk filter yang sedang aktif. Coba ubah tanggal, pilih semua staf, atau bersihkan pencarian.
                            </p>
                        </div>
                        <button 
                            wire:click="resetAllFilters" 
                            type="button" 
                            class="px-4 py-2 bg-primary text-on-primary text-xs font-bold rounded-xl hover:bg-primary-fixed transition-all mt-2 cursor-pointer">
                            Tampilkan Semua Kehadiran
                        </button>
                    </div>
                @endif

            </section>
        @endif

    </div>

    <!-- MODAL DETAIL HARIAN (KLIK DARI KALENDER) -->
    @if($showDayModal && $selectedDay)
        @php
            $mDayCarbon = \Carbon\Carbon::parse($selectedDay);
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm animate-in fade-in duration-200">
            <div class="bg-surface-container rounded-2xl border border-outline-variant/40 shadow-2xl max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
                
                <!-- Modal Header -->
                <div class="p-5 bg-surface-container-high flex items-center justify-between border-b border-surface-container-highest">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary/20 text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[22px]">event</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-on-surface">{{ $mDayCarbon->translatedFormat('l, d F Y') }}</h3>
                            <span class="text-xs text-on-surface-variant">{{ count($selectedDayRecords) }} catatan kehadiran staf</span>
                        </div>
                    </div>
                    <button 
                        wire:click="closeDayModal" 
                        type="button" 
                        class="p-2 rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- Modal Content -->
                <div class="flex-grow overflow-y-auto p-5 space-y-3">
                    @forelse($selectedDayRecords as $rec)
                        @php
                            $mBadge = match($rec->status) {
                                'Hadir' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                'Izin' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                                'Sakit' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                                default => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                            };
                        @endphp
                        <div class="p-3.5 rounded-xl bg-surface-container-high/60 border border-outline-variant/30 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-surface-container-highest text-primary font-bold flex items-center justify-center text-xs">
                                    {{ substr($rec->user->nama ?? 'S', 0, 2) }}
                                </div>
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs text-on-surface">{{ $rec->user->nama ?? 'Staf' }}</span>
                                        <span class="text-[10px] text-on-surface-variant font-medium">({{ $rec->user->role ?? 'Kapster' }})</span>
                                    </div>
                                    <span class="text-xs text-on-surface-variant mt-0.5">
                                        {{ $rec->keterangan ?: 'Tanpa keterangan khusus' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold border {{ $mBadge }}">
                                    {{ $rec->status }}
                                </span>
                                <button 
                                    wire:click="edit({{ $rec->id }})" 
                                    type="button" 
                                    class="p-1.5 text-on-surface-variant hover:text-primary rounded-lg transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center flex flex-col items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-3xl text-on-surface-variant/40">event_available</span>
                            <p class="text-xs text-on-surface-variant">Belum ada catatan absensi staf pada tanggal ini.</p>
                        </div>
                    @endforelse
                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-surface-container-high border-t border-surface-container-highest flex items-center justify-between">
                    <button 
                        wire:click="viewDayInTable('{{ $selectedDay }}')" 
                        type="button" 
                        class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">open_in_new</span>
                        <span>Buka di Tabel Riwayat</span>
                    </button>

                    <div class="flex items-center gap-2">
                        <button 
                            wire:click="create('{{ $selectedDay }}')" 
                            type="button" 
                            class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold hover:bg-primary-fixed transition-all flex items-center gap-1 cursor-pointer">
                            <span class="material-symbols-outlined text-sm">add</span>
                            <span>Catat Absensi</span>
                        </button>
                        <button 
                            wire:click="closeDayModal" 
                            type="button" 
                            class="px-4 py-2 rounded-xl bg-surface-container-highest text-on-surface text-xs font-bold hover:bg-outline-variant/30 transition-all cursor-pointer">
                            Tutup
                        </button>
                    </div>
                </div>

            </div>
        </div>
    @endif

    <!-- MODAL FORM CATAT / EDIT ABSENSI -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-4 animate-in fade-in duration-200">
            <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-outline-variant/40 max-h-[90vh] overflow-y-auto animate-in zoom-in-95 duration-200">
                
                <div class="px-5 py-4 border-b border-surface-container-highest flex justify-between items-center bg-surface-container-high sticky top-0 z-10">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-primary/20 text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">{{ $isEdit ? 'edit' : 'how_to_reg' }}</span>
                        </div>
                        <h3 class="font-headline-md text-base font-bold text-on-surface">{{ $isEdit ? 'Edit Catatan Absensi' : 'Catat Absensi Baru' }}</h3>
                    </div>
                    <button wire:click="$set('showModal', false)" class="text-on-surface-variant hover:text-on-surface transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form wire:submit="save" class="p-5 space-y-4">
                    <!-- Pilih Staf -->
                    <div class="space-y-1.5">
                        <label class="block font-label-md text-xs font-bold text-on-surface uppercase tracking-wider">Pilih Kapster / Staf</label>
                        <select wire:model="user_id" class="w-full bg-surface-container-high rounded-xl py-2.5 px-3.5 font-body-md text-xs text-on-surface border border-outline-variant/40 focus:border-primary outline-none transition-colors cursor-pointer" {{ $isEdit ? 'disabled' : 'required' }}>
                            <option value="">-- Pilih Staf Barbershop --</option>
                            @foreach($staffList as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->nama }} ({{ $staff->role }})</option>
                            @endforeach
                        </select>
                        @error('user_id') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Tanggal -->
                    <div class="space-y-1.5">
                        <label class="block font-label-md text-xs font-bold text-on-surface uppercase tracking-wider">Tanggal Kehadiran</label>
                        <input wire:model="tanggal" type="date" class="w-full bg-surface-container-high rounded-xl py-2.5 px-3.5 font-body-md text-xs text-on-surface border border-outline-variant/40 focus:border-primary outline-none transition-colors cursor-pointer" required>
                        @error('tanggal') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Status -->
                    <div class="space-y-1.5">
                        <label class="block font-label-md text-xs font-bold text-on-surface uppercase tracking-wider">Status Kehadiran</label>
                        <select wire:model="status" class="w-full bg-surface-container-high rounded-xl py-2.5 px-3.5 font-body-md text-xs text-on-surface border border-outline-variant/40 focus:border-primary outline-none transition-colors cursor-pointer" required>
                            <option value="Hadir">Hadir (Bekerja)</option>
                            <option value="Izin">Izin (Terjadwal)</option>
                            <option value="Sakit">Sakit</option>
                            <option value="Alpa">Alpa (Tanpa Keterangan)</option>
                        </select>
                        @error('status') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Keterangan Tambahan -->
                    <div class="space-y-1.5">
                        <label class="block font-label-md text-xs font-bold text-on-surface uppercase tracking-wider">Keterangan / Catatan Alasan</label>
                        <input wire:model="keterangan" type="text" class="w-full bg-surface-container-high rounded-xl py-2.5 px-3.5 font-body-md text-xs text-on-surface border border-outline-variant/40 focus:border-primary outline-none transition-colors" placeholder="Misal: Izin keperluan keluarga, Surat dokter, dll (Opsional)">
                        @error('keterangan') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="pt-4 flex justify-end gap-2.5 border-t border-surface-container-highest mt-6">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-on-surface-variant hover:bg-surface-container-highest transition-colors cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-primary text-on-primary hover:bg-primary-fixed transition-colors shadow-md shadow-primary/20 cursor-pointer">
                            <span wire:loading.remove wire:target="save">Simpan Catatan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
