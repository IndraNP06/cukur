<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.admin')] #[Title('Laporan Pendapatan - Ran CukurIn')] class extends Component {
    use WithPagination;

    public $month;
    public $year;
    public $metodeBayar = 'all';
    public $searchQuery = '';
    public $selectedTransaksiId = null;
    public $showDetailModal = false;

    public function mount()
    {
        // Default filter ke bulan & tahun saat ini
        $this->month = (string) Carbon::now()->month;
        $this->year = (string) Carbon::now()->year;
    }

    public function updatedSearchQuery() { $this->resetPage(); }
    public function updatedMonth() { $this->resetPage(); }
    public function updatedYear() { $this->resetPage(); }
    public function updatedMetodeBayar() { $this->resetPage(); }

    public function syncFromSheets()
    {
        try {
            $sheetsService = app(\App\Services\GoogleSheetsService::class);
            $sheetsService->pullTable('transaksi');
            cache()->forget('sheets_sync_laporan_pendapatan');
            session()->flash('sync_success', 'Data transaksi berhasil disinkronkan langsung dari Google Spreadsheet!');
        } catch (\Throwable $e) {
            session()->flash('sync_error', 'Gagal menyinkronkan data: ' . $e->getMessage());
        }
    }

    public function setFilterThisMonth()
    {
        $this->month = (string) Carbon::now()->month;
        $this->year = (string) Carbon::now()->year;
        $this->resetPage();
    }

    public function setFilterLastMonth()
    {
        $last = Carbon::now()->subMonth();
        $this->month = (string) $last->month;
        $this->year = (string) $last->year;
        $this->resetPage();
    }

    public function setFilterThisYear()
    {
        $this->month = 'all';
        $this->year = (string) Carbon::now()->year;
        $this->resetPage();
    }

    public function setFilterAll()
    {
        $this->month = 'all';
        $this->year = 'all';
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->month = (string) Carbon::now()->month;
        $this->year = (string) Carbon::now()->year;
        $this->metodeBayar = 'all';
        $this->searchQuery = '';
        $this->resetPage();
    }

    public function openDetailModal($id)
    {
        $this->selectedTransaksiId = $id;
        $this->showDetailModal = true;
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedTransaksiId = null;
    }

    public function with()
    {
        $transaksisQuery = Transaksi::query();

        // Filter berdasarkan Bulan dan Tahun yang dipilih
        if ($this->month !== 'all' && $this->year !== 'all') {
            $yearMonth = sprintf('%04d-%02d', (int)$this->year, (int)$this->month);
            $transaksisQuery->where(function($q) use ($yearMonth) {
                $q->where('tanggal_bayar', 'like', "$yearMonth%")
                  ->orWhere(function($sub) use ($yearMonth) {
                      $sub->whereNull('tanggal_bayar')
                          ->where('created_at', 'like', "$yearMonth%");
                  });
            });
        } elseif ($this->year !== 'all' && $this->month === 'all') {
            $yearStr = sprintf('%04d', (int)$this->year);
            $transaksisQuery->where(function($q) use ($yearStr) {
                $q->where('tanggal_bayar', 'like', "$yearStr%")
                  ->orWhere(function($sub) use ($yearStr) {
                      $sub->whereNull('tanggal_bayar')
                          ->where('created_at', 'like', "$yearStr%");
                  });
            });
        } elseif ($this->month !== 'all' && $this->year === 'all') {
            $monthStr = sprintf('-%02d-', (int)$this->month);
            $transaksisQuery->where(function($q) use ($monthStr) {
                $q->where('tanggal_bayar', 'like', "%$monthStr%")
                  ->orWhere(function($sub) use ($monthStr) {
                      $sub->whereNull('tanggal_bayar')
                          ->where('created_at', 'like', "%$monthStr%");
                  });
            });
        }

        // Filter Metode Pembayaran
        if ($this->metodeBayar !== 'all') {
            $transaksisQuery->where('metode_pembayaran', $this->metodeBayar);
        }

        // Search Query (ID Transaksi, Metode, Pelanggan, atau Kasir)
        if (!empty($this->searchQuery)) {
            $term = '%' . trim($this->searchQuery) . '%';
            $transaksisQuery->where(function($q) use ($term) {
                $q->where('id', 'like', $term)
                  ->orWhere('metode_pembayaran', 'like', $term)
                  ->orWhereHas('antrean.pelanggan', function($sub) use ($term) {
                      $sub->where('nama', 'like', $term);
                  })
                  ->orWhereHas('kasir', function($sub) use ($term) {
                      $sub->where('nama', 'like', $term);
                  });
            });
        }

        // Hitung ringkasan finansial langsung via SQL Aggregates (super cepat, tidak membebani memory)
        $totalPendapatanKotor = (float) (clone $transaksisQuery)->sum('subtotal');
        $totalPendapatanBersih = (float) (clone $transaksisQuery)->sum('total_akhir');
        $totalDiskon = (float) (clone $transaksisQuery)->sum('total_diskon');
        $totalTransaksi = (clone $transaksisQuery)->count();
        $rataRataTrx = $totalTransaksi > 0 ? $totalPendapatanBersih / $totalTransaksi : 0;

        // Breakdown Layanan vs Produk via query aggregate detail_transaksi
        $trxIds = (clone $transaksisQuery)->select('id');
        $pendapatanProduk = (float) DetailTransaksi::whereIn('transaksi_id', $trxIds)
            ->where('tipe_item', 'Barang')
            ->sum('subtotal');
        $pendapatanLayanan = $totalPendapatanKotor - $pendapatanProduk;

        // Paginate daftar transaksi untuk tabel (25 per halaman)
        $transaksis = (clone $transaksisQuery)
            ->with(['detailTransaksi', 'antrean.pelanggan', 'antrean.layanan', 'antrean.kapster', 'kasir'])
            ->orderBy('tanggal_bayar', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        // Selected Transaksi untuk modal struk
        $selectedTransaksi = null;
        if ($this->selectedTransaksiId) {
            $selectedTransaksi = Transaksi::with(['detailTransaksi', 'antrean.pelanggan', 'antrean.layanan', 'antrean.kapster', 'kasir'])
                ->find($this->selectedTransaksiId);
        }

        // List tahun yang tersedia di database
        $distinctYears = Transaksi::whereNotNull('tanggal_bayar')
            ->pluck('tanggal_bayar')
            ->map(fn($t) => substr($t, 0, 4))
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        $curYear = (string) date('Y');
        if (!in_array($curYear, $distinctYears)) {
            array_unshift($distinctYears, $curYear);
        }
        $availableYears = array_unique($distinctYears);

        return [
            'transaksis' => $transaksis,
            'totalPendapatanKotor' => $totalPendapatanKotor,
            'totalPendapatanBersih' => $totalPendapatanBersih,
            'totalDiskon' => $totalDiskon,
            'pendapatanLayanan' => $pendapatanLayanan,
            'pendapatanProduk' => $pendapatanProduk,
            'totalTransaksi' => $totalTransaksi,
            'rataRataTrx' => $rataRataTrx,
            'selectedTransaksi' => $selectedTransaksi,
            'availableYears' => $availableYears,
        ];
    }
};
?>

<div class="flex flex-col w-full gap-5 lg:gap-6">
    <!-- Header Actions & Title -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="font-headline-xl text-on-surface text-headline-xl font-bold">Laporan Pendapatan</h1>
                <span class="text-xs bg-emerald-500/10 text-emerald-400 px-2.5 py-0.5 rounded-full font-medium border border-emerald-500/20 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Live Finance
                </span>
            </div>
            <p class="text-body-md font-body-md text-on-surface-variant mt-1">
                Ringkasan finansial, filter omset bulanan, dan riwayat mutasi transaksi pelanggan.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Tombol Sinkron Spreadsheet -->
            <button 
                wire:click="syncFromSheets" 
                type="button" 
                class="bg-surface-container-high hover:bg-surface-container-highest text-primary border border-primary/40 text-xs font-bold rounded-xl px-4 py-2.5 flex items-center gap-2 transition-all shadow-sm cursor-pointer">
                <span class="material-symbols-outlined text-[18px] animate-spin" wire:loading wire:target="syncFromSheets">sync</span>
                <span class="material-symbols-outlined text-[18px]" wire:loading.remove wire:target="syncFromSheets">sync</span>
                <span>Sinkron Spreadsheet</span>
            </button>

            <!-- Tombol Unduh / Cetak Laporan -->
            <button 
                onclick="window.print()" 
                type="button" 
                class="bg-primary hover:bg-primary-fixed text-on-primary text-xs font-bold rounded-xl px-4 py-2.5 flex items-center gap-2 transition-all shadow-md shadow-primary/20 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">download</span>
                <span>Unduh Laporan</span>
            </button>
        </div>
    </div>

    <!-- Flash Alert Notifications -->
    @if(session('sync_success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-xl text-sm flex items-center gap-3 shadow-lg">
            <span class="material-symbols-outlined text-xl">check_circle</span>
            <span class="font-medium">{{ session('sync_success') }}</span>
        </div>
    @endif
    @if(session('sync_error'))
        <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 p-4 rounded-xl text-sm flex items-center gap-3 shadow-lg">
            <span class="material-symbols-outlined text-xl">error</span>
            <span class="font-medium">{{ session('sync_error') }}</span>
        </div>
    @endif

    <!-- Toolbar Filter Fleksibel Berdasarkan Bulan & Tahun -->
    <div class="bg-surface-container p-4 lg:p-5 rounded-2xl border border-outline-variant/30 flex flex-col gap-4 shadow-xl">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            
            <!-- Quick Preset Filter Buttons -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold text-on-surface-variant flex items-center gap-1 mr-1">
                    <span class="material-symbols-outlined text-sm">tune</span>
                    Filter Cepat:
                </span>
                <button 
                    wire:click="setFilterThisMonth" 
                    type="button" 
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all {{ $month == date('n') && $year == date('Y') ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest border border-outline-variant/20' }}">
                    Bulan Ini ({{ \Carbon\Carbon::now()->translatedFormat('M') }})
                </button>
                <button 
                    wire:click="setFilterLastMonth" 
                    type="button" 
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all {{ $month == \Carbon\Carbon::now()->subMonth()->month && $year == \Carbon\Carbon::now()->subMonth()->year ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest border border-outline-variant/20' }}">
                    Bulan Lalu ({{ \Carbon\Carbon::now()->subMonth()->translatedFormat('M') }})
                </button>
                <button 
                    wire:click="setFilterThisYear" 
                    type="button" 
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all {{ $month === 'all' && $year == date('Y') ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest border border-outline-variant/20' }}">
                    Tahun Ini ({{ date('Y') }})
                </button>
                <button 
                    wire:click="setFilterAll" 
                    type="button" 
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all {{ $month === 'all' && $year === 'all' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest border border-outline-variant/20' }}">
                    Semua Waktu
                </button>
            </div>

            <!-- Current Period Indicator Badge -->
            <div class="text-xs text-on-surface-variant flex items-center gap-2 self-start lg:self-center bg-surface-container-high px-3.5 py-1.5 rounded-xl border border-outline-variant/30">
                <span class="material-symbols-outlined text-[16px] text-primary">calendar_month</span>
                <span>Periode: 
                    <strong class="text-primary font-bold">
                        @if($month !== 'all')
                            {{ \Carbon\Carbon::create()->month((int)$month)->translatedFormat('F') }}
                        @else
                            Semua Bulan
                        @endif
                        @if($year !== 'all')
                            {{ $year }}
                        @else
                            (Semua Tahun)
                        @endif
                    </strong>
                </span>
                <span class="text-on-surface-variant">•</span>
                <span class="font-semibold text-on-surface">{{ $totalTransaksi }} transaksi</span>
            </div>
        </div>

        <!-- Dropdowns Bar: Bulan, Tahun, Metode Pembayaran, & Search -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-3 border-t border-surface-container-highest">
            
            <!-- Dropdown Filter Bulan -->
            <div class="flex items-center gap-3 px-3.5 py-2 bg-surface-container-high rounded-xl border border-outline-variant/30 shadow-inner">
                <span class="material-symbols-outlined text-primary text-[20px]">calendar_month</span>
                <div class="flex flex-col flex-grow">
                    <label class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Pilih Bulan</label>
                    <select wire:model.live="month" class="bg-transparent border-none outline-none text-on-surface font-semibold text-xs cursor-pointer">
                        <option value="all" class="bg-surface-container-high text-on-surface">Semua Bulan</option>
                        @for($i=1; $i<=12; $i++)
                            <option value="{{ $i }}" class="bg-surface-container-high text-on-surface">
                                {{ \Carbon\Carbon::create()->month($i)->translatedFormat('F') }}
                            </option>
                        @endfor
                    </select>
                </div>
            </div>

            <!-- Dropdown Filter Tahun -->
            <div class="flex items-center gap-3 px-3.5 py-2 bg-surface-container-high rounded-xl border border-outline-variant/30 shadow-inner">
                <span class="material-symbols-outlined text-primary text-[20px]">event</span>
                <div class="flex flex-col flex-grow">
                    <label class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Pilih Tahun</label>
                    <select wire:model.live="year" class="bg-transparent border-none outline-none text-on-surface font-semibold text-xs cursor-pointer">
                        <option value="all" class="bg-surface-container-high text-on-surface">Semua Tahun</option>
                        @foreach($availableYears as $thn)
                            <option value="{{ $thn }}" class="bg-surface-container-high text-on-surface">{{ $thn }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Dropdown Metode Pembayaran -->
            <div class="flex items-center gap-3 px-3.5 py-2 bg-surface-container-high rounded-xl border border-outline-variant/30 shadow-inner">
                <span class="material-symbols-outlined text-primary text-[20px]">payments</span>
                <div class="flex flex-col flex-grow">
                    <label class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Metode Pembayaran</label>
                    <select wire:model.live="metodeBayar" class="bg-transparent border-none outline-none text-on-surface font-semibold text-xs cursor-pointer">
                        <option value="all" class="bg-surface-container-high text-on-surface">Semua Metode</option>
                        <option value="Cash" class="bg-surface-container-high text-on-surface">Cash (Tunai)</option>
                        <option value="QRIS" class="bg-surface-container-high text-on-surface">QRIS</option>
                        <option value="Transfer" class="bg-surface-container-high text-on-surface">Transfer</option>
                    </select>
                </div>
            </div>

            <!-- Input Pencarian Transaksi -->
            <div class="flex items-center gap-3 px-3.5 py-2 bg-surface-container-high rounded-xl border border-outline-variant/30 shadow-inner">
                <span class="material-symbols-outlined text-on-surface-variant text-[20px]">search</span>
                <div class="flex flex-col flex-grow">
                    <label class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Cari Transaksi</label>
                    <input 
                        wire:model.live.debounce.300ms="searchQuery" 
                        type="text" 
                        placeholder="ID / Pelanggan / Kasir..." 
                        class="bg-transparent border-none outline-none text-on-surface font-semibold text-xs placeholder:text-on-surface-variant/50">
                </div>
                @if($searchQuery || $month !== (string)date('n') || $year !== (string)date('Y') || $metodeBayar !== 'all')
                    <button 
                        wire:click="resetFilters" 
                        type="button" 
                        title="Reset Filter ke Bulan Ini"
                        class="p-1 rounded-lg hover:bg-surface-container text-on-surface-variant hover:text-on-surface transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                    </button>
                @endif
            </div>

        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Pendapatan Bersih -->
        <div class="bg-surface-container p-4 lg:p-5 rounded-2xl relative overflow-hidden group border border-outline-variant/20 shadow-xl">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-500 to-teal-400"></div>
            <div class="flex justify-between items-start mb-3">
                <p class="font-label-md text-xs text-on-surface-variant uppercase tracking-wider font-semibold">Total Pendapatan Bersih</p>
                <div class="w-10 h-10 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-400 shadow-inner">
                    <span class="material-symbols-outlined">account_balance_wallet</span>
                </div>
            </div>
            <h2 class="font-headline-lg text-2xl lg:text-[28px] text-emerald-400 font-extrabold tracking-tight">
                Rp {{ number_format($totalPendapatanBersih, 0, ',', '.') }}
            </h2>
            <div class="mt-2 flex items-center justify-between text-xs text-on-surface-variant pt-2 border-t border-surface-container-highest">
                <span>Kotor: Rp {{ number_format($totalPendapatanKotor, 0, ',', '.') }}</span>
                @if($totalDiskon > 0)
                    <span class="text-rose-400 font-medium">Diskon: -{{ number_format($totalDiskon, 0, ',', '.') }}</span>
                @endif
            </div>
        </div>

        <!-- Card 2: Pendapatan Layanan Pangkas -->
        <div class="bg-surface-container p-4 lg:p-5 rounded-2xl relative overflow-hidden group border border-outline-variant/20 shadow-xl">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-surface-tint"></div>
            <div class="flex justify-between items-start mb-3">
                <p class="font-label-md text-xs text-on-surface-variant uppercase tracking-wider font-semibold">Pendapatan Layanan</p>
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary shadow-inner">
                    <span class="material-symbols-outlined">content_cut</span>
                </div>
            </div>
            <h2 class="font-headline-lg text-2xl lg:text-[28px] text-on-surface font-extrabold tracking-tight">
                Rp {{ number_format($pendapatanLayanan, 0, ',', '.') }}
            </h2>
            <div class="mt-2 flex items-center gap-1.5 text-xs text-on-surface-variant pt-2 border-t border-surface-container-highest">
                <span class="text-primary font-bold">{{ $totalPendapatanKotor > 0 ? round(($pendapatanLayanan / $totalPendapatanKotor) * 100) : 0 }}%</span>
                <span>kontribusi dari total omset</span>
            </div>
        </div>

        <!-- Card 3: Pendapatan Penjualan Produk -->
        <div class="bg-surface-container p-4 lg:p-5 rounded-2xl relative overflow-hidden group border border-outline-variant/20 shadow-xl">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-amber-500 to-orange-400"></div>
            <div class="flex justify-between items-start mb-3">
                <p class="font-label-md text-xs text-on-surface-variant uppercase tracking-wider font-semibold">Pendapatan Produk</p>
                <div class="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-400 shadow-inner">
                    <span class="material-symbols-outlined">inventory_2</span>
                </div>
            </div>
            <h2 class="font-headline-lg text-2xl lg:text-[28px] text-on-surface font-extrabold tracking-tight">
                Rp {{ number_format($pendapatanProduk, 0, ',', '.') }}
            </h2>
            <div class="mt-2 flex items-center gap-1.5 text-xs text-on-surface-variant pt-2 border-t border-surface-container-highest">
                <span class="text-amber-400 font-bold">{{ $totalPendapatanKotor > 0 ? round(($pendapatanProduk / $totalPendapatanKotor) * 100) : 0 }}%</span>
                <span>kontribusi dari total omset</span>
            </div>
        </div>

        <!-- Card 4: Total & Rata-rata Transaksi -->
        <div class="bg-surface-container p-4 lg:p-5 rounded-2xl relative overflow-hidden group border border-outline-variant/20 shadow-xl">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-indigo-400"></div>
            <div class="flex justify-between items-start mb-3">
                <p class="font-label-md text-xs text-on-surface-variant uppercase tracking-wider font-semibold">Total Transaksi</p>
                <div class="w-10 h-10 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-400 shadow-inner">
                    <span class="material-symbols-outlined">receipt_long</span>
                </div>
            </div>
            <h2 class="font-headline-lg text-2xl lg:text-[28px] text-on-surface font-extrabold tracking-tight">
                {{ $totalTransaksi }} <span class="text-sm font-medium text-on-surface-variant">Trx</span>
            </h2>
            <div class="mt-2 flex items-center gap-1 text-xs text-on-surface-variant pt-2 border-t border-surface-container-highest">
                <span>Rata-rata:</span>
                <span class="font-bold text-primary">Rp {{ number_format($rataRataTrx, 0, ',', '.') }}</span>
                <span>/ transaksi</span>
            </div>
        </div>
    </div>

    <!-- Detailed Ledger Table -->
    <div class="bg-surface-container rounded-2xl overflow-hidden flex flex-col shadow-2xl border border-outline-variant/20">
        <div class="p-4 lg:p-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-surface-container-high border-b border-surface-container-highest">
            <div>
                <h3 class="font-headline-md text-on-surface text-lg font-bold">Rincian Transaksi Masuk</h3>
                <p class="text-xs text-on-surface-variant">Catatan pembukuan pembayaran kasir barbershop.</p>
            </div>
            <div class="text-xs text-on-surface-variant bg-surface-container px-3 py-1.5 rounded-lg border border-outline-variant/30">
                Menampilkan <strong class="text-primary">{{ $transaksis->firstItem() ?? 0 }}-{{ $transaksis->lastItem() ?? 0 }}</strong> dari total <strong class="text-primary">{{ $totalTransaksi }}</strong> data transaksi
            </div>
        </div>

        <div class="overflow-x-auto w-full">
            <table class="w-full text-left whitespace-nowrap border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-on-surface-variant font-label-sm text-label-sm uppercase tracking-wider border-b border-surface-container-highest">
                        <th class="px-5 py-3.5 font-semibold">Tanggal &amp; Waktu</th>
                        <th class="px-5 py-3.5 font-semibold">ID Transaksi</th>
                        <th class="px-5 py-3.5 font-semibold">Pelanggan &amp; Kasir</th>
                        <th class="px-5 py-3.5 font-semibold">Layanan / Item</th>
                        <th class="px-5 py-3.5 font-semibold">Metode</th>
                        <th class="px-5 py-3.5 font-semibold text-right">Subtotal</th>
                        <th class="px-5 py-3.5 font-semibold text-right">Diskon</th>
                        <th class="px-5 py-3.5 font-semibold text-right">Total Akhir</th>
                        <th class="px-5 py-3.5 font-semibold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-body-md font-body-md text-on-surface divide-y divide-surface-container-highest/60">
                    @forelse($transaksis as $trx)
                        @php
                            $tglBayar = $trx->tanggal_bayar ? \Carbon\Carbon::parse($trx->tanggal_bayar) : $trx->created_at;
                            $pelangganNama = optional(optional($trx->antrean)->pelanggan)->nama ?? 'Pelanggan Umum';
                            $kasirNama = optional($trx->kasir)->nama ?? 'Kasir Barbershop';
                            $layananUtama = optional(optional($trx->antrean)->layanan)->nama_layanan ?? 'Potong Rambut';
                        @endphp
                        <tr class="hover:bg-surface-container-highest/70 transition-colors group cursor-pointer" wire:click="openDetailModal({{ $trx->id }})">
                            <td class="px-5 py-4">
                                <div class="flex flex-col">
                                    <span class="font-semibold text-xs text-on-surface">{{ $tglBayar->format('d/m/Y') }}</span>
                                    <span class="text-[11px] text-on-surface-variant">{{ $tglBayar->format('H:i') }} WIB</span>
                                </div>
                            </td>
                            <td class="px-5 py-4 font-mono text-primary font-bold text-xs">
                                #TRX-{{ str_pad($trx->id, 4, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-col">
                                    <span class="font-bold text-xs text-on-surface">{{ $pelangganNama }}</span>
                                    <span class="text-[11px] text-on-surface-variant">Kasir: {{ $kasirNama }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-primary text-[16px]">content_cut</span>
                                    <span class="text-xs text-on-surface font-medium truncate max-w-[150px]">
                                        {{ $layananUtama }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $trx->metode_pembayaran === 'QRIS' ? 'bg-blue-500/10 text-blue-400 border-blue-500/30' : ($trx->metode_pembayaran === 'Cash' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 'bg-purple-500/10 text-purple-400 border-purple-500/30') }}">
                                    {{ $trx->metode_pembayaran }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right text-xs">
                                Rp {{ number_format($trx->subtotal, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-4 text-right text-xs {{ $trx->total_diskon > 0 ? 'text-rose-400 font-semibold' : 'text-on-surface-variant' }}">
                                {{ $trx->total_diskon > 0 ? '- Rp ' . number_format($trx->total_diskon, 0, ',', '.') : 'Rp 0' }}
                            </td>
                            <td class="px-5 py-4 text-right text-emerald-400 font-bold text-sm">
                                Rp {{ number_format($trx->total_akhir, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-4 text-center">
                                <button 
                                    wire:click.stop="openDetailModal({{ $trx->id }})" 
                                    type="button" 
                                    class="p-1.5 rounded-lg bg-surface-container-high hover:bg-primary hover:text-on-primary text-primary transition-colors border border-primary/30 cursor-pointer"
                                    title="Lihat Rincian Struk">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-12 text-center text-on-surface-variant">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-4xl text-on-surface-variant/40">receipt_long</span>
                                    <p class="font-semibold text-sm">Tidak ada transaksi yang cocok pada filter periode ini.</p>
                                    <p class="text-xs text-on-surface-variant">Coba ganti filter bulan, tahun, atau klik "Semua Waktu".</p>
                                    <button 
                                        wire:click="setFilterAll" 
                                        type="button" 
                                        class="mt-2 text-xs font-bold text-primary hover:underline flex items-center gap-1">
                                        <span>Tampilkan Semua Transaksi</span>
                                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transaksis->hasPages())
            <div class="p-4 bg-surface-container-high border-t border-surface-container-highest">
                {{ $transaksis->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL RINCIAN STRUK TRANSAKSI -->
    @if($showDetailModal && $selectedTransaksi)
        @php
            $mTgl = $selectedTransaksi->tanggal_bayar ? \Carbon\Carbon::parse($selectedTransaksi->tanggal_bayar) : $selectedTransaksi->created_at;
            $mPelanggan = optional(optional($selectedTransaksi->antrean)->pelanggan)->nama ?? 'Pelanggan Umum';
            $mKapster = optional(optional($selectedTransaksi->antrean)->kapster)->nama ?? '-';
            $mKasir = optional($selectedTransaksi->kasir)->nama ?? 'Kasir Barbershop';
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 md:p-6 bg-black/75 backdrop-blur-sm animate-in fade-in duration-200 overflow-y-auto">
            <div class="bg-surface-container rounded-2xl border border-outline-variant/40 shadow-2xl max-w-lg w-full max-h-[92vh] sm:max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
                
                <!-- Modal Header -->
                <div class="px-4 py-3.5 sm:px-5 sm:py-4 bg-surface-container-high flex items-center justify-between border-b border-surface-container-highest shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary/20 text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[22px]">receipt</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-on-surface">Struk Transaksi #TRX-{{ str_pad($selectedTransaksi->id, 4, '0', STR_PAD_LEFT) }}</h3>
                            <span class="text-xs text-on-surface-variant">{{ $mTgl->format('d F Y, H:i') }} WIB</span>
                        </div>
                    </div>
                    <button 
                        wire:click="closeDetailModal" 
                        type="button" 
                        class="p-2 rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-5 space-y-4 text-xs">
                    
                    <!-- Metadata Info Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-xl bg-surface-container-high/60 border border-outline-variant/30">
                        <div>
                            <span class="text-on-surface-variant text-[11px] block">Pelanggan:</span>
                            <span class="font-bold text-on-surface text-sm">{{ $mPelanggan }}</span>
                        </div>
                        <div>
                            <span class="text-on-surface-variant text-[11px] block">Kapster:</span>
                            <span class="font-bold text-on-surface text-sm">{{ $mKapster }}</span>
                        </div>
                        <div>
                            <span class="text-on-surface-variant text-[11px] block">Kasir Bertugas:</span>
                            <span class="font-semibold text-on-surface">{{ $mKasir }}</span>
                        </div>
                        <div>
                            <span class="text-on-surface-variant text-[11px] block">Metode Pembayaran:</span>
                            <span class="font-bold text-primary">{{ $selectedTransaksi->metode_pembayaran }}</span>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-2">
                        <span class="font-bold text-xs uppercase tracking-wider text-on-surface-variant">Rincian Item &amp; Layanan</span>
                        <div class="border border-surface-container-highest rounded-xl overflow-hidden divide-y divide-surface-container-highest">
                            @if($selectedTransaksi->detailTransaksi && $selectedTransaksi->detailTransaksi->count() > 0)
                                @foreach($selectedTransaksi->detailTransaksi as $dt)
                                    <div class="p-3 flex justify-between items-center bg-surface-container">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-on-surface text-xs">{{ $dt->nama_item_snapshot }}</span>
                                            <span class="text-[11px] text-on-surface-variant">
                                                {{ $dt->qty }}x @ Rp {{ number_format($dt->harga_satuan_snapshot, 0, ',', '.') }} ({{ $dt->tipe_item }})
                                            </span>
                                        </div>
                                        <span class="font-bold text-on-surface text-xs">
                                            Rp {{ number_format($dt->subtotal, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @endforeach
                            @elseif($selectedTransaksi->antrean && $selectedTransaksi->antrean->layanan)
                                <div class="p-3 flex justify-between items-center bg-surface-container">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-on-surface text-xs">{{ $selectedTransaksi->antrean->layanan->nama_layanan }}</span>
                                        <span class="text-[11px] text-on-surface-variant">1x Layanan Pangkas Rambut</span>
                                    </div>
                                    <span class="font-bold text-on-surface text-xs">
                                        Rp {{ number_format($selectedTransaksi->subtotal, 0, ',', '.') }}
                                    </span>
                                </div>
                            @else
                                <div class="p-3 flex justify-between items-center bg-surface-container">
                                    <span class="text-on-surface">Layanan Ran CukurIn</span>
                                    <span class="font-bold text-on-surface">Rp {{ number_format($selectedTransaksi->subtotal, 0, ',', '.') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Total Calculations -->
                    <div class="p-4 bg-surface-container-high rounded-xl space-y-2 border border-outline-variant/30">
                        <div class="flex justify-between items-center text-on-surface-variant">
                            <span>Subtotal</span>
                            <span class="font-semibold text-on-surface">Rp {{ number_format($selectedTransaksi->subtotal, 0, ',', '.') }}</span>
                        </div>
                        @if($selectedTransaksi->total_diskon > 0)
                            <div class="flex justify-between items-center text-rose-400">
                                <span>Potongan Diskon</span>
                                <span class="font-semibold">- Rp {{ number_format($selectedTransaksi->total_diskon, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between items-center pt-2 border-t border-surface-container-highest text-sm">
                            <span class="font-bold text-on-surface">Total Akhir Bayar</span>
                            <span class="font-extrabold text-emerald-400 text-base">
                                Rp {{ number_format($selectedTransaksi->total_akhir, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="px-4 py-3 sm:px-5 sm:py-3.5 bg-surface-container-high border-t border-surface-container-highest flex items-center justify-between shrink-0">
                    <button 
                        onclick="window.print()" 
                        type="button" 
                        class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold hover:bg-primary-fixed transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">print</span>
                        <span>Cetak Struk</span>
                    </button>
                    <button 
                        wire:click="closeDetailModal" 
                        type="button" 
                        class="px-5 py-2 rounded-xl bg-surface-container-highest hover:bg-outline-variant/30 text-on-surface text-xs font-bold transition-all cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
