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
    public $selectedKapsterId = null;
    public $showUlasanModal = false;
    public $filterUlasanPeriod = 'month'; // 'month' atau 'all'
    
    // Top Highlights
    public $topProductive = null;
    public $topRating = null;
    public $topRevenue = null;

    public function mount()
    {
        $this->month = (int) Carbon::now()->month;
        $this->year = (int) Carbon::now()->year;

        // Auto-pull ulasan dan antrean dari Google Sheets saat halaman pertama kali dibuka (cache 30s)
        try {
            if (!cache()->has('sheets_sync_kinerja')) {
                $sheetsService = app(\App\Services\GoogleSheetsService::class);
                $sheetsService->pullTable('ulasan');
                $sheetsService->pullTable('antrean_pemesanan');
                cache()->put('sheets_sync_kinerja', true, now()->addSeconds(30));
            }
        } catch (\Throwable $e) {}

        $this->loadData();
    }

    public function syncFromSheets()
    {
        try {
            $sheetsService = app(\App\Services\GoogleSheetsService::class);
            $sheetsService->pullTable('ulasan');
            $sheetsService->pullTable('antrean_pemesanan');
            $sheetsService->pullTable('detail_transaksi');
            cache()->forget('sheets_sync_kinerja');
            $this->loadData();
            session()->flash('sync_success', 'Data kinerja dan ulasan berhasil disinkronkan langsung dari Google Spreadsheet!');
        } catch (\Throwable $e) {
            session()->flash('sync_error', 'Gagal menyinkronkan data: ' . $e->getMessage());
        }
    }
    
    public function loadData()
    {
        $kapsters = User::where('role', 'Kapster')->get();
        $this->kapstersData = [];
        
        $maxCukuran = 0;
        $maxRating = 0;
        $maxRevenue = 0;

        $yearMonth = sprintf('%04d-%02d', $this->year, $this->month);
        
        foreach ($kapsters as $kapster) {
            // Filter pemesanan berdasarkan bulan & tahun yang dipilih
            $pemesanans = AntreanPemesanan::where('kapster_id', $kapster->id)
                ->where('status', 'Selesai')
                ->where(function($q) use ($yearMonth) {
                    $q->where('tanggal', 'like', "$yearMonth%")
                      ->orWhere('created_at', 'like', "$yearMonth%");
                })
                ->with('detailTransaksi')
                ->get();
            
            $totalPelanggan = $pemesanans->count();
            
            $revenue = $pemesanans->sum(function($p) {
                return $p->detailTransaksi ? (float)$p->detailTransaksi->total_akhir : 0;
            });
            
            // Ulasan pada bulan yang dipilih (resilient date comparison)
            $ulasanQuery = Ulasan::where('kapster_id', $kapster->id)
                ->where('created_at', 'like', "$yearMonth%");
            
            $ratingCount = (clone $ulasanQuery)->count();
            $rating = (clone $ulasanQuery)->avg('rating') ?? 0;

            // Total ulasan all-time
            $totalUlasanAll = Ulasan::where('kapster_id', $kapster->id)->count();
            $ratingAll = Ulasan::where('kapster_id', $kapster->id)->avg('rating') ?? 0;
            
            $data = [
                'id' => $kapster->id,
                'nama' => $kapster->nama,
                'foto' => $kapster->foto,
                'total_pelanggan' => $totalPelanggan,
                'revenue' => $revenue,
                'rating' => round($rating, 1),
                'rating_count' => $ratingCount,
                'rating_all' => round($ratingAll, 1),
                'total_ulasan_all' => $totalUlasanAll,
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
        
        // Default selected kapster jika belum dipilih atau kapster sebelumnya tidak ada di list
        if (count($this->kapstersData) > 0) {
            $exists = collect($this->kapstersData)->firstWhere('id', $this->selectedKapsterId);
            if (!$exists) {
                $this->selectedKapsterId = $this->kapstersData[0]['id'];
            }
        }
    }
    
    public function selectKapster($id)
    {
        $this->selectedKapsterId = $id;
    }

    public function openUlasanModal($id)
    {
        $this->selectedKapsterId = $id;
        $this->showUlasanModal = true;
    }

    public function closeUlasanModal()
    {
        $this->showUlasanModal = false;
    }

    public function setUlasanPeriod($period)
    {
        $this->filterUlasanPeriod = $period;
    }

    public function updatedMonth()
    {
        $this->loadData();
    }
    
    public function updatedYear()
    {
        $this->loadData();
    }

    public function with()
    {
        $selectedKapster = collect($this->kapstersData)->firstWhere('id', $this->selectedKapsterId);
        $ulasans = collect();

        if ($this->selectedKapsterId) {
            $query = Ulasan::where('kapster_id', $this->selectedKapsterId)
                ->with(['pelanggan', 'antrean.layanan'])
                ->latest();

            if ($this->filterUlasanPeriod === 'month') {
                $yearMonth = sprintf('%04d-%02d', $this->year, $this->month);
                $query->where('created_at', 'like', "$yearMonth%");
            }

            $ulasans = $query->get();
        }

        return [
            'selectedKapster' => $selectedKapster,
            'ulasans' => $ulasans,
        ];
    }
};
?>

<div class="flex-grow flex flex-col relative bg-background">
    <div class="flex flex-col w-full gap-5 lg:gap-5 p-4 lg:p-5 pt-16 text-on-surface">
        
        <!-- Flash Alert Messages -->
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

        <!-- Header Controls -->
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-2 gap-4">
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-3">
                    <h1 class="font-headline-xl text-on-background tracking-tight text-headline-xl font-bold">Laporan Kinerja &amp; Rating Kapster</h1>
                    <span class="text-xs bg-emerald-500/10 text-emerald-400 px-2.5 py-0.5 rounded-full font-medium border border-emerald-500/20 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Sheets Sync
                    </span>
                </div>
                <p class="font-body-lg text-body-lg text-on-surface-variant">Evaluasi performa bulanan, pendapatan pangkas, dan kepuasan ulasan pelanggan.</p>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <!-- Month & Year Filter -->
                <div class="flex items-center gap-2 bg-surface-container-low p-1.5 rounded-xl shadow-md border border-outline-variant/30">
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-surface-container rounded-lg">
                        <span class="material-symbols-outlined text-on-surface-variant text-[18px]">calendar_month</span>
                        <select wire:model.live="month" class="bg-transparent border-none outline-none text-on-surface font-label-md text-label-md appearance-none cursor-pointer">
                            @for($i=1; $i<=12; $i++)
                                <option value="{{ $i }}">{{ \Carbon\Carbon::create()->month($i)->translatedFormat('F') }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-surface-container rounded-lg">
                        <select wire:model.live="year" class="bg-transparent border-none outline-none text-on-surface font-label-md text-label-md appearance-none cursor-pointer">
                            @for($i=date('Y'); $i>=date('Y')-2; $i--)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                <!-- Tombol Sinkron Spreadsheet -->
                <button 
                    wire:click="syncFromSheets" 
                    type="button" 
                    class="bg-surface-container-high hover:bg-surface-container-highest text-primary border border-primary/40 text-xs font-bold rounded-xl px-4 py-2.5 flex items-center gap-2 transition-all shadow-sm cursor-pointer">
                    <span class="material-symbols-outlined text-[18px] animate-spin" wire:loading wire:target="syncFromSheets">sync</span>
                    <span class="material-symbols-outlined text-[18px]" wire:loading.remove wire:target="syncFromSheets">sync</span>
                    <span>Sinkron Spreadsheet</span>
                </button>

                <!-- Tombol Cetak / Unduh -->
                <button 
                    onclick="window.print()" 
                    type="button" 
                    class="bg-primary hover:bg-primary-fixed text-on-primary text-xs font-bold rounded-xl px-4 py-2.5 flex items-center gap-2 transition-all shadow-md shadow-primary/20 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    <span>Unduh Laporan</span>
                </button>
            </div>
        </div>

        <!-- Performance Highlights -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5">
            <!-- Card 1: Terproduktif -->
            <div class="relative bg-surface-container-high rounded-xl p-4 lg:p-5 shadow-xl overflow-hidden group border border-outline-variant/20">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-surface-tint"></div>
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center">
                            <span class="material-symbols-outlined text-on-primary-container">content_cut</span>
                        </div>
                        <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold">Terproduktif</span>
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <h3 class="font-headline-md text-on-surface text-headline-md font-bold">{{ $topProductive ? $topProductive['nama'] : '-' }}</h3>
                    <div class="flex items-baseline gap-2">
                        <span class="font-headline-lg text-primary text-headline-lg font-extrabold">{{ $topProductive ? $topProductive['total_pelanggan'] : 0 }}</span>
                        <span class="font-body-md text-body-md text-on-surface-variant">Cukuran</span>
                    </div>
                </div>
                <div class="absolute -bottom-6 -right-6 w-12 h-12 bg-primary/5 rounded-full blur-2xl"></div>
            </div>
            
            <!-- Card 2: Rating Tertinggi -->
            <div class="relative bg-surface-container-high rounded-xl p-4 lg:p-5 shadow-xl overflow-hidden group border border-outline-variant/20">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-surface-tint"></div>
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center">
                            <span class="material-symbols-outlined text-on-primary-container" style="font-variation-settings: 'FILL' 1;">star</span>
                        </div>
                        <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold">Rating Tertinggi</span>
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <h3 class="font-headline-md text-on-surface text-headline-md font-bold">{{ $topRating ? $topRating['nama'] : '-' }}</h3>
                    <div class="flex items-baseline gap-2">
                        <span class="font-headline-lg text-primary text-headline-lg font-extrabold">{{ $topRating && $topRating['rating'] > 0 ? $topRating['rating'] : ($topRating && $topRating['rating_all'] > 0 ? $topRating['rating_all'] : '0.0') }}</span>
                        <span class="font-body-md text-body-md text-on-surface-variant">/ 5.0</span>
                        @if($topRating && $topRating['rating_count'] > 0)
                            <span class="text-xs text-on-surface-variant">({{ $topRating['rating_count'] }} ulasan)</span>
                        @elseif($topRating && $topRating['total_ulasan_all'] > 0)
                            <span class="text-xs text-on-surface-variant">({{ $topRating['total_ulasan_all'] }} total ulasan)</span>
                        @endif
                    </div>
                </div>
                <div class="absolute -bottom-6 -right-6 w-12 h-12 bg-primary/5 rounded-full blur-2xl"></div>
            </div>
            
            <!-- Card 3: Pendapatan -->
            <div class="relative bg-surface-container-high rounded-xl p-4 lg:p-5 shadow-xl overflow-hidden group border border-outline-variant/20">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-surface-tint"></div>
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center">
                            <span class="material-symbols-outlined text-on-primary-container">payments</span>
                        </div>
                        <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold">Pendapatan</span>
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <h3 class="font-headline-md text-on-surface text-headline-md font-bold">{{ $topRevenue ? $topRevenue['nama'] : '-' }}</h3>
                    <div class="flex items-baseline gap-2">
                        <span class="font-headline-md text-emerald-400 text-headline-md font-bold">Rp {{ number_format($topRevenue ? $topRevenue['revenue'] : 0, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="absolute -bottom-6 -right-6 w-12 h-12 bg-primary/5 rounded-full blur-2xl"></div>
            </div>
        </div>

        <!-- Main Table Detail Kinerja -->
        <div class="bg-surface-container rounded-xl shadow-2xl overflow-hidden mt-2 border border-outline-variant/20">
            <div class="p-4 lg:p-5 bg-surface-container-high flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-surface-container-highest">
                <div>
                    <h2 class="font-headline-md text-on-surface text-headline-md font-bold">Detail Kinerja &amp; Ulasan Kapster</h2>
                    <p class="text-xs text-on-surface-variant">Klik nama atau tombol "Lihat Ulasan" untuk membuka rincian review pelanggan secara lengkap.</p>
                </div>
                <div class="text-xs text-on-surface-variant bg-surface-container px-3 py-1.5 rounded-lg border border-outline-variant/30">
                    Periode: <span class="font-bold text-primary">{{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }} {{ $year }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low text-on-surface-variant font-label-sm text-label-sm uppercase tracking-wider border-b border-surface-container-highest">
                            <th class="p-4 pl-6 font-semibold">Profil Kapster</th>
                            <th class="p-4 font-semibold">Total Pelanggan Selesai</th>
                            <th class="p-4 font-semibold">Kontribusi Layanan</th>
                            <th class="p-4 font-semibold">Rating &amp; Ulasan</th>
                            <th class="p-4 pr-6 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="font-body-md text-body-md text-on-surface divide-y divide-surface-container-highest/60">
                        @forelse($kapstersData as $data)
                            <tr class="bg-surface-container hover:bg-surface-container-high transition-colors group cursor-pointer relative {{ $selectedKapsterId === $data['id'] ? 'bg-surface-container-high/80' : '' }}" wire:click="selectKapster({{ $data['id'] }})">
                                <td class="p-4 pl-6 flex items-center gap-4">
                                    <div class="relative">
                                        @if($data['foto'])
                                            <img src="{{ $data['foto'] }}" alt="{{ $data['nama'] }}" class="w-11 h-11 rounded-full object-cover border border-outline-variant shadow-sm">
                                        @else
                                            <div class="w-11 h-11 rounded-full flex items-center justify-center bg-surface-container-highest text-primary font-bold shadow-inner border border-primary/20">
                                                {{ substr($data['nama'], 0, 2) }}
                                            </div>
                                        @endif
                                        <div class="absolute -bottom-1 -right-1 w-3.5 h-3.5 rounded-full bg-emerald-500 border-2 border-surface-container"></div>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-label-md text-label-md font-bold text-on-surface">{{ $data['nama'] }}</span>
                                        <span class="text-xs text-on-surface-variant">Kapster Profesional</span>
                                    </div>
                                    <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-primary scale-y-0 {{ $selectedKapsterId === $data['id'] ? 'scale-y-100' : 'group-hover:scale-y-100' }} transition-transform"></div>
                                </td>
                                <td class="p-4 text-on-surface">
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="font-bold text-base text-primary">{{ $data['total_pelanggan'] }}</span>
                                        <span class="text-xs text-on-surface-variant">Pelanggan</span>
                                    </div>
                                </td>
                                <td class="p-4 font-semibold text-emerald-400">
                                    Rp {{ number_format($data['revenue'], 0, ',', '.') }}
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-col gap-0.5">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-on-surface">{{ $data['rating'] > 0 ? $data['rating'] : ($data['rating_all'] > 0 ? $data['rating_all'] : '0.0') }}</span>
                                            <span class="material-symbols-outlined text-primary text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                        </div>
                                        <span class="text-[11px] text-on-surface-variant">
                                            @if($data['rating_count'] > 0)
                                                {{ $data['rating_count'] }} ulasan bulan ini
                                            @elseif($data['total_ulasan_all'] > 0)
                                                {{ $data['total_ulasan_all'] }} ulasan total
                                            @else
                                                Belum ada ulasan
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 pr-6 text-right">
                                    <button 
                                        wire:click.stop="openUlasanModal({{ $data['id'] }})" 
                                        type="button"
                                        class="px-4 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-on-primary border border-primary/40 hover:border-primary transition-all font-label-md text-label-md font-semibold inline-flex items-center gap-1.5 shadow-sm cursor-pointer">
                                        <span class="material-symbols-outlined text-[18px]">rate_review</span>
                                        <span>Lihat Ulasan</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-on-surface-variant">Belum ada data kinerja kapster.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section Ulasan Panel Bawah (Inline) -->
        @if($selectedKapster)
            <div class="bg-surface-container-lowest p-5 lg:p-6 rounded-2xl shadow-2xl relative overflow-hidden mt-4 border border-outline-variant/30">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-4 border-b border-surface-container-highest gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary text-[24px]">reviews</span>
                        </div>
                        <div>
                            <h3 class="font-headline-md text-on-background text-headline-md font-bold">Ulasan Pelanggan: {{ $selectedKapster['nama'] }}</h3>
                            <p class="text-xs text-on-surface-variant">Daftar ulasan dan rating langsung dari pelanggan Ran CukurIn.</p>
                        </div>
                    </div>

                    <!-- Filter Tab Periode & Tombol Modal -->
                    <div class="flex items-center gap-2">
                        <div class="flex items-center bg-surface-container-high p-1 rounded-xl text-xs border border-outline-variant/30">
                            <button 
                                wire:click="setUlasanPeriod('month')" 
                                type="button"
                                class="px-3 py-1.5 rounded-lg font-semibold transition-all {{ $filterUlasanPeriod === 'month' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                                Bulan Ini
                            </button>
                            <button 
                                wire:click="setUlasanPeriod('all')" 
                                type="button"
                                class="px-3 py-1.5 rounded-lg font-semibold transition-all {{ $filterUlasanPeriod === 'all' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                                Semua Waktu ({{ $selectedKapster['total_ulasan_all'] ?? 0 }})
                            </button>
                        </div>

                        <button 
                            wire:click="openUlasanModal({{ $selectedKapster['id'] }})" 
                            type="button"
                            class="px-3.5 py-1.5 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-primary border border-primary/30 text-xs font-semibold flex items-center gap-1.5 transition-all">
                            <span class="material-symbols-outlined text-[16px]">open_in_full</span>
                            <span>Modal Lengkap</span>
                        </button>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5 relative z-10">
                    @forelse($ulasans as $ulasan)
                        <div class="bg-surface-container p-4 lg:p-5 rounded-xl border border-surface-container-highest flex flex-col justify-between gap-4 hover:shadow-lg hover:border-primary/40 transition-all">
                            <div>
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center gap-1">
                                        @for($i=1; $i<=5; $i++)
                                            <span class="material-symbols-outlined {{ $ulasan->rating >= $i ? 'text-primary' : 'text-surface-variant/40' }} text-[20px]" style="font-variation-settings: 'FILL' {{ $ulasan->rating >= $i ? 1 : 0 }};">star</span>
                                        @endfor
                                        <span class="ml-1 text-xs font-bold text-primary">{{ $ulasan->rating }}.0</span>
                                    </div>
                                    <span class="font-label-sm text-xs text-on-surface-variant" title="{{ $ulasan->created_at->format('d M Y H:i') }}">
                                        {{ $ulasan->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <p class="font-body-md text-sm text-on-surface italic leading-relaxed">
                                    "{{ $ulasan->komentar ?: ($ulasan->ulasan ?: 'Pelanggan puas dengan layanan dan memberikan bintang ' . $ulasan->rating . '.') }}"
                                </p>
                            </div>

                            <div class="flex items-center justify-between pt-3 border-t border-surface-container-highest">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-secondary-container flex items-center justify-center font-label-sm text-xs font-bold text-on-secondary-container shadow-sm">
                                        {{ substr(optional($ulasan->pelanggan)->nama ?? 'P', 0, 1) }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-label-sm text-xs font-bold text-on-surface">{{ optional($ulasan->pelanggan)->nama ?? 'Pelanggan Ran CukurIn' }}</span>
                                        <span class="text-[11px] text-on-surface-variant">{{ optional(optional($ulasan->antrean)->layanan)->nama_layanan ?? 'Potong Rambut' }}</span>
                                    </div>
                                </div>
                                <span class="text-[11px] text-on-surface-variant bg-surface-container-high px-2 py-0.5 rounded-md">
                                    {{ $ulasan->created_at->format('d/m/Y') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-10 px-4 text-center bg-surface-container/40 rounded-xl border border-dashed border-outline-variant/40 flex flex-col items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-4xl text-on-surface-variant/40">rate_review</span>
                            <p class="text-sm text-on-surface-variant">
                                Belum ada ulasan untuk <span class="font-semibold text-on-surface">{{ $selectedKapster['nama'] }}</span> pada periode ini.
                            </p>
                            @if($filterUlasanPeriod === 'month' && ($selectedKapster['total_ulasan_all'] ?? 0) > 0)
                                <button 
                                    wire:click="setUlasanPeriod('all')" 
                                    type="button" 
                                    class="mt-2 text-xs font-bold text-primary hover:underline flex items-center gap-1">
                                    <span>Lihat {{ $selectedKapster['total_ulasan_all'] }} ulasan dari semua waktu</span>
                                    <span class="material-symbols-outlined text-sm">arrow_forward</span>
                                </button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

    </div>

    <!-- MODAL DETAIL ULASAN KAPSTER -->
    @if($showUlasanModal && $selectedKapster)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm animate-in fade-in duration-200">
            <div class="bg-surface-container rounded-2xl border border-outline-variant/40 shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
                
                <!-- Modal Header -->
                <div class="p-5 bg-surface-container-high flex items-start justify-between border-b border-surface-container-highest gap-4">
                    <div class="flex items-center gap-3.5">
                        @if($selectedKapster['foto'])
                            <img src="{{ $selectedKapster['foto'] }}" alt="{{ $selectedKapster['nama'] }}" class="w-12 h-12 rounded-full object-cover border-2 border-primary shadow-sm">
                        @else
                            <div class="w-12 h-12 rounded-full bg-primary/20 text-primary flex items-center justify-center font-bold text-lg border-2 border-primary shadow-sm">
                                {{ substr($selectedKapster['nama'], 0, 2) }}
                            </div>
                        @endif
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-headline-md text-lg font-bold text-on-surface">{{ $selectedKapster['nama'] }}</h3>
                                <span class="text-[11px] bg-primary/10 text-primary px-2 py-0.5 rounded-full font-semibold border border-primary/30">Kapster</span>
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="flex items-center text-primary">
                                    <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                    <span class="font-bold text-sm ml-1 text-on-surface">{{ $selectedKapster['rating'] > 0 ? $selectedKapster['rating'] : ($selectedKapster['rating_all'] > 0 ? $selectedKapster['rating_all'] : '0.0') }}</span>
                                    <span class="text-xs text-on-surface-variant ml-0.5">/ 5.0</span>
                                </div>
                                <span class="text-on-surface-variant text-xs">•</span>
                                <span class="text-xs text-on-surface-variant">{{ $selectedKapster['total_ulasan_all'] ?? 0 }} total ulasan</span>
                            </div>
                        </div>
                    </div>

                    <button 
                        wire:click="closeUlasanModal" 
                        type="button" 
                        class="p-2 rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[22px]">close</span>
                    </button>
                </div>

                <!-- Modal Subheader Tabs & Highlights -->
                <div class="px-5 py-3 bg-surface-container flex flex-wrap items-center justify-between gap-3 border-b border-surface-container-highest text-xs">
                    <div class="flex items-center gap-2">
                        <span class="text-on-surface-variant font-medium">Filter Ulasan:</span>
                        <div class="flex items-center bg-surface-container-high p-1 rounded-xl border border-outline-variant/30">
                            <button 
                                wire:click="setUlasanPeriod('month')" 
                                type="button"
                                class="px-3 py-1 rounded-lg font-semibold transition-all {{ $filterUlasanPeriod === 'month' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                                Bulan Ini ({{ \Carbon\Carbon::create()->month($month)->translatedFormat('M') }} {{ $year }})
                            </button>
                            <button 
                                wire:click="setUlasanPeriod('all')" 
                                type="button"
                                class="px-3 py-1 rounded-lg font-semibold transition-all {{ $filterUlasanPeriod === 'all' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                                Semua Waktu ({{ $selectedKapster['total_ulasan_all'] ?? 0 }})
                            </button>
                        </div>
                    </div>

                    <div class="text-on-surface-variant flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-primary">verified</span>
                        <span>{{ count($ulasans) }} ulasan ditampilkan</span>
                    </div>
                </div>

                <!-- Modal Content (Reviews List) -->
                <div class="flex-grow overflow-y-auto p-5 space-y-3.5 max-h-[55vh]">
                    @forelse($ulasans as $ulasan)
                        <div class="bg-surface-container-high/70 p-4 rounded-xl border border-surface-container-highest hover:border-primary/40 transition-colors flex flex-col gap-3">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-full bg-secondary-container flex items-center justify-center font-bold text-xs text-on-secondary-container shadow-inner">
                                        {{ substr(optional($ulasan->pelanggan)->nama ?? 'P', 0, 1) }}
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-on-surface">{{ optional($ulasan->pelanggan)->nama ?? 'Pelanggan Ran CukurIn' }}</h4>
                                        <span class="text-xs text-on-surface-variant">{{ optional(optional($ulasan->antrean)->layanan)->nama_layanan ?? 'Layanan Pangkas' }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end">
                                    <div class="flex items-center gap-0.5 text-primary">
                                        @for($i=1; $i<=5; $i++)
                                            <span class="material-symbols-outlined text-[17px] {{ $ulasan->rating >= $i ? 'text-primary' : 'text-surface-variant/40' }}" style="font-variation-settings: 'FILL' {{ $ulasan->rating >= $i ? 1 : 0 }};">star</span>
                                        @endfor
                                    </div>
                                    <span class="text-[11px] text-on-surface-variant mt-0.5" title="{{ $ulasan->created_at->format('d M Y H:i:s') }}">
                                        {{ $ulasan->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </div>

                            <div class="bg-surface-container/60 p-3 rounded-lg border border-outline-variant/20">
                                <p class="text-sm text-on-surface italic leading-relaxed">
                                    "{{ $ulasan->komentar ?: ($ulasan->ulasan ?: 'Pelanggan memberikan rating bintang ' . $ulasan->rating . ' tanpa catatan tertulis.') }}"
                                </p>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-on-surface-variant pt-1">
                                <span>No. Antrean: <strong class="text-on-surface">#{{ optional($ulasan->antrean)->nomor_antrean ?? $ulasan->antrean_id }}</strong></span>
                                <span>Waktu: {{ $ulasan->created_at->format('d F Y, H:i') }} WIB</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 px-4 text-center flex flex-col items-center justify-center gap-3">
                            <div class="w-14 h-14 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-3xl">rate_review</span>
                            </div>
                            <div>
                                <h4 class="font-bold text-base text-on-surface">Belum Ada Ulasan</h4>
                                <p class="text-xs text-on-surface-variant mt-1">
                                    Tidak ada ulasan untuk {{ $selectedKapster['nama'] }} pada filter yang dipilih.
                                </p>
                            </div>
                            @if($filterUlasanPeriod === 'month' && ($selectedKapster['total_ulasan_all'] ?? 0) > 0)
                                <button 
                                    wire:click="setUlasanPeriod('all')" 
                                    type="button" 
                                    class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold hover:bg-primary-fixed transition-all mt-2">
                                    Buka Semua Ulasan ({{ $selectedKapster['total_ulasan_all'] }})
                                </button>
                            @endif
                        </div>
                    @endforelse
                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-surface-container-high border-t border-surface-container-highest flex items-center justify-between">
                    <span class="text-xs text-on-surface-variant">Ran CukurIn • Evaluasi Pelayanan</span>
                    <button 
                        wire:click="closeUlasanModal" 
                        type="button" 
                        class="px-5 py-2 rounded-xl bg-surface-container-highest hover:bg-outline-variant/30 text-on-surface text-xs font-bold transition-all cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
