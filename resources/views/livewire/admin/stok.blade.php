<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\LogStok;
use App\Models\Barang;
use Carbon\Carbon;

new #[Layout('components.layouts.admin')] #[Title('Lapor Ekstra Barang & Mutasi Stok - Ran CukurIn')] class extends Component {
    use WithPagination;

    public $filterJenis = 'all';
    public $filterBarang = 'all';

    public $showModal = false;
    
    // Form fields for Stok Masuk/Keluar
    public $barang_id = '';
    public $jenis_mutasi = 'keluar'; // Default 'keluar' untuk lapor ekstra barang kapster
    public $jumlah = 1;
    public $keterangan = '';

    public function rules() {
        return [
            'barang_id' => 'required|exists:barang,id',
            'jenis_mutasi' => 'required|in:masuk,keluar,penyesuaian',
            'jumlah' => 'required|integer|min:1',
            'keterangan' => 'nullable|string|max:255',
        ];
    }

    public function mount() {
        // Tarik data barang terbaru dari Google Spreadsheet saat dibuka
        try {
            if (!cache()->has('sheets_sync_barang_stok')) {
                app(\App\Services\GoogleSheetsService::class)->pullTable('barang');
                cache()->put('sheets_sync_barang_stok', true, now()->addSeconds(10));
            }
        } catch (\Throwable $e) {}
    }

    public function syncFromSheets() {
        try {
            app(\App\Services\GoogleSheetsService::class)->pullTable('barang');
            cache()->forget('sheets_sync_barang_stok');
            session()->flash('message', 'Data stok barang berhasil disinkronkan langsung dari Google Spreadsheet!');
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal menyinkronkan data: ' . $e->getMessage());
        }
    }

    public function updatedFilterJenis() {
        $this->resetPage();
    }

    public function updatedFilterBarang() {
        $this->resetPage();
    }

    public function create($preselectedBarangId = null, $jenis = 'keluar') {
        $this->resetValidation();
        $this->reset(['barang_id', 'jenis_mutasi', 'jumlah', 'keterangan']);
        if ($preselectedBarangId) {
            $this->barang_id = (string)$preselectedBarangId;
        }
        $this->jenis_mutasi = $jenis;
        $this->jumlah = 1;
        $this->showModal = true;
    }

    public function save() {
        $this->validate();

        try {
            $barang = Barang::findOrFail($this->barang_id);

            if ($this->jenis_mutasi === 'masuk') {
                $barang->stok_sekarang += $this->jumlah;
                $tipeLog = 'Masuk';
            } elseif ($this->jenis_mutasi === 'keluar') {
                if ($barang->stok_sekarang < $this->jumlah) {
                    $this->addError('jumlah', 'Stok tidak mencukupi! Sisa stok ' . $barang->nama_barang . ' saat ini hanya ' . $barang->stok_sekarang . '.');
                    return;
                }
                $barang->stok_sekarang -= $this->jumlah;
                $tipeLog = 'KeluarManual';
            } else {
                $tipeLog = 'Opname';
            }

            $barang->save();

            LogStok::create([
                'barang_id' => $this->barang_id,
                'pencatat_id' => auth()->id() ?? 1,
                'tipe_log' => $tipeLog,
                'qty_perubahan' => $this->jumlah,
                'stok_akhir' => $barang->stok_sekarang,
                'keterangan' => $this->keterangan ?: ($tipeLog === 'Masuk' ? 'Restok / Pemasukan manual' : 'Lapor ekstra / Pengeluaran manual'),
            ]);

            // Sinkronkan ke Google Spreadsheet
            try {
                app(\App\Services\GoogleSheetsService::class)->syncTable('barang');
            } catch (\Throwable $e) {}

            $this->showModal = false;
            $this->reset(['barang_id', 'jenis_mutasi', 'jumlah', 'keterangan']);
            session()->flash('message', 'Laporan mutasi ' . ($tipeLog === 'Masuk' ? 'pemasukan' : 'pengeluaran') . ' barang (' . $barang->nama_barang . ') berhasil dicatat dan disinkronkan ke Google Spreadsheet!');
        } catch (\Throwable $e) {
            $this->addError('jumlah', 'Gagal mencatat mutasi: ' . $e->getMessage());
        }
    }

    public function with() {
        // Auto-pull dari Google Sheets jika cache habis (setiap 10 detik saat wire:poll)
        try {
            if (!cache()->has('sheets_sync_barang_stok')) {
                app(\App\Services\GoogleSheetsService::class)->pullTable('barang');
                cache()->put('sheets_sync_barang_stok', true, now()->addSeconds(10));
            }
        } catch (\Throwable $e) {}

        $query = LogStok::with(['barang', 'pencatat'])
            ->when($this->filterJenis !== 'all', function($q) {
                if ($this->filterJenis === 'masuk') {
                    $q->where('tipe_log', 'Masuk');
                } else {
                    $q->whereIn('tipe_log', ['KeluarManual', 'OtomatisPOS']);
                }
            })
            ->when($this->filterBarang !== 'all', function($q) {
                $q->where('barang_id', $this->filterBarang);
            });
        
        return [
            'logs' => $query->latest()->paginate(15),
            'barangs' => Barang::orderBy('nama_barang')->get()
        ];
    }
};
?>

<div class="flex flex-col w-full h-full gap-5 lg:gap-6" wire:poll.10s>
    <!-- Notifikasi Flash Message -->
    @if(session()->has('message'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-2xl flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-xl">check_circle</span>
                <span class="text-sm font-medium">{{ session('message') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-400/60 hover:text-emerald-400">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 p-4 rounded-2xl flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-xl">error</span>
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-400/60 hover:text-rose-400">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
    @endif

    <!-- Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-on-surface">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-2xl">inventory_2</span>
                <h1 class="font-headline-xl text-on-background text-2xl lg:text-3xl font-bold tracking-tight">Lapor Ekstra Barang &amp; Mutasi Stok</h1>
            </div>
            <p class="font-body-md text-sm text-on-surface-variant mt-1">Pencatatan pemakaian barang ekstra kapster (silet, tisu, pomade) dan restok inventaris.</p>
        </div>
        <div class="flex items-center gap-3">
            <button wire:click="syncFromSheets" wire:loading.attr="disabled"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-surface-container-high hover:bg-surface-bright text-primary border border-primary/30 transition shadow-sm text-xs font-semibold cursor-pointer">
                <span wire:loading.remove wire:target="syncFromSheets" class="material-symbols-outlined text-base">sync</span>
                <span wire:loading wire:target="syncFromSheets" class="material-symbols-outlined text-base animate-spin">refresh</span>
                <span>Sinkron Dari Sheets</span>
            </button>
            <button wire:click="create" class="bg-primary text-on-primary px-4 lg:px-5 py-2.5 rounded-xl font-semibold text-xs hover:bg-primary-fixed-dim transition-all shadow-md flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base">add</span>
                <span>Catat Mutasi / Lapor Ekstra</span>
            </button>
        </div>
    </div>

    <!-- Ringkasan Stok Barang Cepat (Quick Stock Cards) -->
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wider font-semibold text-on-surface-variant">Sisa Stok Barang Saat Ini</span>
            <span class="text-xs text-on-surface-variant font-mono">Live Google Sheets</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            @foreach($barangs as $b)
                @php
                    $isLow = $b->stok_sekarang <= $b->stok_minimum;
                @endphp
                <div class="bg-surface-container border {{ $isLow ? 'border-amber-500/40' : 'border-outline-variant/30' }} rounded-2xl p-3.5 flex flex-col justify-between shadow-sm relative group hover:border-primary/50 transition">
                    <div>
                        <div class="flex items-center justify-between gap-1 mb-1">
                            <span class="text-xs font-semibold text-on-surface line-clamp-1" title="{{ $b->nama_barang }}">{{ $b->nama_barang }}</span>
                            @if($isLow)
                                <span class="material-symbols-outlined text-amber-400 text-sm" title="Stok Menipis!">warning</span>
                            @endif
                        </div>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-2xl font-bold {{ $isLow ? 'text-amber-400' : 'text-primary' }}">{{ $b->stok_sekarang }}</span>
                            <span class="text-[11px] text-on-surface-variant">Pcs (Min: {{ $b->stok_minimum }})</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 mt-2.5 pt-2 border-t border-outline-variant/20">
                        <button wire:click="create({{ $b->id }}, 'keluar')" title="Lapor Pemakaian Ekstra"
                            class="flex-1 py-1 px-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-[11px] font-semibold text-center transition cursor-pointer">
                            - Keluar
                        </button>
                        <button wire:click="create({{ $b->id }}, 'masuk')" title="Catat Restok Pemasukan"
                            class="flex-1 py-1 px-2 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 text-[11px] font-semibold text-center transition cursor-pointer">
                            + Masuk
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    
    <!-- Filter Bar -->
    <div class="bg-surface-container rounded-2xl p-4 lg:p-5 shadow-sm border border-outline-variant/30 relative overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 relative z-10">
            <div class="flex flex-col gap-1.5">
                <label class="font-label-sm text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Filter Barang</label>
                <select wire:model.live="filterBarang" class="w-full bg-surface-container-highest text-on-surface font-body-md text-sm rounded-xl py-2.5 px-3.5 outline-none cursor-pointer border border-outline-variant/30 focus:border-primary">
                    <option value="all">Semua Barang ({{ $barangs->count() }} Item)</option>
                    @foreach($barangs as $b)
                        <option value="{{ $b->id }}">{{ $b->nama_barang }} (Sisa: {{ $b->stok_sekarang }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="font-label-sm text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Filter Jenis Aktivitas</label>
                <select wire:model.live="filterJenis" class="w-full bg-surface-container-highest text-on-surface font-body-md text-sm rounded-xl py-2.5 px-3.5 outline-none cursor-pointer border border-outline-variant/30 focus:border-primary">
                    <option value="all">Semua Jenis Mutasi</option>
                    <option value="masuk">Pemasukan / Restok (+)</option>
                    <option value="keluar">Pengeluaran / Lapor Ekstra (-)</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Tabel Riwayat Mutasi Stok -->
    <div class="bg-surface-container rounded-2xl shadow-md border border-outline-variant/30 overflow-hidden flex-1 flex flex-col">
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse min-w-[850px]">
                <thead>
                    <tr class="bg-surface-container-highest text-on-surface-variant text-xs uppercase tracking-widest border-b border-outline-variant/20 font-semibold">
                        <th class="px-5 py-4 whitespace-nowrap">Tanggal &amp; Waktu</th>
                        <th class="px-5 py-4 whitespace-nowrap">Nama Barang</th>
                        <th class="px-5 py-4 whitespace-nowrap">Jenis Aktivitas</th>
                        <th class="px-5 py-4 text-right whitespace-nowrap">Perubahan</th>
                        <th class="px-5 py-4 text-right whitespace-nowrap">Sisa Stok Akhir</th>
                        <th class="px-5 py-4 whitespace-nowrap">Pencatat / Keterangan</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-on-surface divide-y divide-outline-variant/10">
                    @forelse($logs as $log)
                        @php
                            $isMasuk = $log->tipe_log === 'Masuk';
                        @endphp
                        <tr class="hover:bg-surface-container-highest/40 transition-colors">
                            <td class="px-5 py-4 whitespace-nowrap text-on-surface-variant text-xs">
                                <div>{{ $log->created_at->format('d/m/Y') }}</div>
                                <div class="font-mono text-[11px] opacity-70">{{ $log->created_at->format('H:i:s') }}</div>
                            </td>
                            <td class="px-5 py-4 font-semibold text-on-surface">
                                {{ $log->barang->nama_barang ?? 'Barang Dihapus' }}
                            </td>
                            <td class="px-5 py-4">
                                @if($isMasuk)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-400 text-xs font-semibold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Pemasukan (Restok)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-rose-500/15 text-rose-400 text-xs font-semibold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        {{ $log->tipe_log === 'KeluarManual' ? 'Pengeluaran (Ekstra)' : ($log->tipe_log === 'OtomatisPOS' ? 'Pengeluaran (POS)' : $log->tipe_log) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right font-bold text-base {{ $isMasuk ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $isMasuk ? '+' : '-' }}{{ $log->qty_perubahan }}
                            </td>
                            <td class="px-5 py-4 text-right font-mono font-semibold text-on-surface">
                                {{ $log->stok_akhir }} Pcs
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold text-on-surface flex items-center gap-1">
                                        <span class="material-symbols-outlined text-xs text-primary">person</span>
                                        {{ $log->pencatat->nama ?? 'Sistem' }}
                                    </span>
                                    <span class="text-xs text-on-surface-variant mt-0.5">{{ $log->keterangan }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-on-surface-variant text-sm">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-4xl text-on-surface-variant/40">inventory_2</span>
                                    <span>Belum ada riwayat mutasi atau lapor ekstra barang.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="bg-surface-container-lowest p-4 flex flex-col md:flex-row items-center justify-between gap-4 mt-auto border-t border-outline-variant/20">
            {{ $logs->links() }}
        </div>
    </div>

    <!-- Modal Form Catat Mutasi / Lapor Ekstra -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-outline-variant/30 max-h-[90vh] overflow-y-auto">
                <div class="px-5 py-4 border-b border-outline-variant/20 flex justify-between items-center bg-surface-container-lowest sticky top-0 z-10">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-xl">edit_note</span>
                        <h3 class="font-bold text-base text-on-surface">Lapor Ekstra Barang / Catat Mutasi</h3>
                    </div>
                    <button wire:click="$set('showModal', false)" class="text-on-surface-variant hover:text-error transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>
                <form wire:submit="save" class="p-5 space-y-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Pilih Barang</label>
                        <select wire:model.live="barang_id" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors cursor-pointer" required>
                            <option value="">-- Pilih Barang --</option>
                            @foreach($barangs as $b)
                                <option value="{{ $b->id }}">{{ $b->nama_barang }} (Sisa Stok: {{ $b->stok_sekarang }} Pcs)</option>
                            @endforeach
                        </select>
                        @error('barang_id') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Jenis Aktivitas Mutasi</label>
                        <select wire:model.live="jenis_mutasi" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors cursor-pointer" required>
                            <option value="keluar">Pengeluaran (Lapor Ekstra / Pemakaian Kapster / Rusak)</option>
                            <option value="masuk">Pemasukan (Restok / Pembelian Baru)</option>
                        </select>
                        @error('jenis_mutasi') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Jumlah (Qty / Pcs)</label>
                        <input wire:model="jumlah" type="number" min="1" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                        @error('jumlah') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Keterangan / Alasan (Opsional)</label>
                        <textarea wire:model="keterangan" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" rows="2" placeholder="Cth: Pemakaian silet cukur ekstra pelanggan, tisu leher, atau restok baru"></textarea>
                        @error('keterangan') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="pt-4 flex justify-end gap-3 border-t border-outline-variant/20 mt-6">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 rounded-xl text-xs font-semibold text-on-surface-variant hover:bg-surface-container-highest transition-colors cursor-pointer">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="px-5 py-2 rounded-xl text-xs font-semibold bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors shadow-md shadow-primary/20 cursor-pointer">
                            <span wire:loading.remove wire:target="save">Simpan Mutasi</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
