<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Barang;
use App\Models\LogStok;

new #[Layout('components.layouts.admin')] #[Title('Kelola Barang & Inventaris - Ran Cukurin')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterKategori = 'all';

    public $showModal = false;
    public $isEdit = false;
    public $barangId;

    public $nama_barang;
    public $tipe = 'ProdukJual';
    public $stok_sekarang = 0;
    public $batas_minimum = 0;
    public $harga_beli = 0;
    public $harga_jual = 0;
    public $gambar;

    // Quick Mutasi Masuk/Keluar Modal
    public $showMutasiModal = false;
    public $mutasiBarangId;
    public $mutasiBarangNama;
    public $mutasiJenis = 'masuk'; // 'masuk', 'keluar'
    public $mutasiJumlah = 1;
    public $mutasiKeterangan = '';

    public function rules() {
        return [
            'nama_barang' => 'required|string|max:255',
            'tipe' => 'required|string|in:ProdukJual,BahanBaku,Produk Jual,Material Ops',
            'stok_sekarang' => 'required|integer|min:0',
            'batas_minimum' => 'required|integer|min:0',
            'harga_beli' => 'required|numeric|min:0',
            'harga_jual' => 'required|numeric|min:0',
            'gambar' => 'nullable|string|max:255',
        ];
    }

    public function mount() {
    }

    public function syncFromSheets() {
        try {
            app(\App\Services\GoogleSheetsService::class)->pullTable('barang');
            cache()->forget('sheets_sync_barang_master');
            session()->flash('message', 'Data master barang berhasil disinkronkan langsung dari Google Spreadsheet!');
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal menyinkronkan data: ' . $e->getMessage());
        }
    }

    public function updatedSearch() {
        $this->resetPage();
    }

    public function updatedFilterKategori() {
        $this->resetPage();
    }

    public function create() {
        $this->resetValidation();
        $this->reset(['nama_barang', 'tipe', 'stok_sekarang', 'batas_minimum', 'harga_beli', 'harga_jual', 'gambar', 'barangId']);
        $this->tipe = 'ProdukJual';
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function edit($id) {
        $this->resetValidation();
        $barang = Barang::findOrFail($id);
        $this->barangId = $barang->id;
        $this->nama_barang = $barang->nama_barang;
        $this->tipe = ($barang->tipe === 'BahanBaku' || $barang->tipe === 'Material Ops') ? 'BahanBaku' : 'ProdukJual';
        $this->stok_sekarang = $barang->stok_sekarang;
        $this->batas_minimum = $barang->batas_minimum;
        $this->harga_beli = $barang->harga_beli;
        $this->harga_jual = $barang->harga_jual;
        $this->gambar = $barang->gambar;
        
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save() {
        $this->validate();

        try {
            $normalizedTipe = ($this->tipe === 'BahanBaku' || $this->tipe === 'Material Ops') ? 'BahanBaku' : 'ProdukJual';

            if ($this->isEdit) {
                $barang = Barang::findOrFail($this->barangId);
                $oldStok = $barang->stok_sekarang;
                $barang->update([
                    'nama_barang' => $this->nama_barang,
                    'tipe' => $normalizedTipe,
                    'stok_sekarang' => $this->stok_sekarang,
                    'batas_minimum' => $this->batas_minimum,
                    'harga_beli' => $this->harga_beli,
                    'harga_jual' => $this->harga_jual,
                    'gambar' => $this->gambar,
                ]);

                // Jika stok disesuaikan saat edit, catat mutasi otomatis
                if ($this->stok_sekarang != $oldStok) {
                    $diff = $this->stok_sekarang - $oldStok;
                    LogStok::create([
                        'barang_id' => $barang->id,
                        'pencatat_id' => auth()->id() ?? 1,
                        'tipe_log' => $diff > 0 ? 'Masuk' : 'KeluarManual',
                        'qty_perubahan' => abs($diff),
                        'stok_akhir' => $this->stok_sekarang,
                        'keterangan' => 'Penyesuaian stok saat edit barang (' . ($diff > 0 ? '+' : '-') . abs($diff) . ')',
                    ]);
                }
            } else {
                $barang = Barang::create([
                    'nama_barang' => $this->nama_barang,
                    'tipe' => $normalizedTipe,
                    'stok_sekarang' => $this->stok_sekarang,
                    'batas_minimum' => $this->batas_minimum,
                    'harga_beli' => $this->harga_beli,
                    'harga_jual' => $this->harga_jual,
                    'gambar' => $this->gambar,
                ]);

                if ($this->stok_sekarang > 0) {
                    LogStok::create([
                        'barang_id' => $barang->id,
                        'pencatat_id' => auth()->id() ?? 1,
                        'tipe_log' => 'Masuk',
                        'qty_perubahan' => $this->stok_sekarang,
                        'stok_akhir' => $this->stok_sekarang,
                        'keterangan' => 'Stok awal barang baru',
                    ]);
                }
            }

            try {
                app(\App\Services\GoogleSheetsService::class)->syncTable('barang');
            } catch (\Throwable $e) {}

            $this->showModal = false;
            session()->flash('message', 'Data barang "' . $this->nama_barang . '" berhasil disimpan dan disinkronkan ke Google Spreadsheet.');
        } catch (\Throwable $e) {
            $this->addError('nama_barang', 'Gagal menyimpan barang: ' . $e->getMessage());
        }
    }

    public function openMutasiModal($barangId, $jenis = 'masuk') {
        $this->resetValidation();
        $barang = Barang::findOrFail($barangId);
        $this->mutasiBarangId = $barang->id;
        $this->mutasiBarangNama = $barang->nama_barang;
        $this->mutasiJenis = $jenis;
        $this->mutasiJumlah = 1;
        $this->mutasiKeterangan = '';
        $this->showMutasiModal = true;
    }

    public function saveMutasi() {
        $this->validate([
            'mutasiBarangId' => 'required|exists:barang,id',
            'mutasiJenis' => 'required|in:masuk,keluar',
            'mutasiJumlah' => 'required|integer|min:1',
            'mutasiKeterangan' => 'nullable|string|max:255',
        ]);

        try {
            $barang = Barang::findOrFail($this->mutasiBarangId);

            if ($this->mutasiJenis === 'masuk') {
                $barang->stok_sekarang += $this->mutasiJumlah;
                $tipeLog = 'Masuk';
            } else {
                if ($barang->stok_sekarang < $this->mutasiJumlah) {
                    $this->addError('mutasiJumlah', 'Stok tidak mencukupi! Sisa stok ' . $barang->nama_barang . ' saat ini: ' . $barang->stok_sekarang . ' pcs.');
                    return;
                }
                $barang->stok_sekarang -= $this->mutasiJumlah;
                $tipeLog = 'KeluarManual';
            }

            $barang->save();

            LogStok::create([
                'barang_id' => $barang->id,
                'pencatat_id' => auth()->id() ?? 1,
                'tipe_log' => $tipeLog,
                'qty_perubahan' => $this->mutasiJumlah,
                'stok_akhir' => $barang->stok_sekarang,
                'keterangan' => $this->mutasiKeterangan ?: ($tipeLog === 'Masuk' ? 'Restok barang masuk oleh owner' : 'Pengeluaran inventaris oleh owner'),
            ]);

            try {
                app(\App\Services\GoogleSheetsService::class)->syncTable('barang');
            } catch (\Throwable $e) {}

            $this->showMutasiModal = false;
            session()->flash('message', 'Mutasi ' . ($tipeLog === 'Masuk' ? 'pemasukan (+)' : 'pengeluaran (-)') . ' barang ' . $barang->nama_barang . ' sebanyak ' . $this->mutasiJumlah . ' pcs berhasil dicatat!');
        } catch (\Throwable $e) {
            $this->addError('mutasiJumlah', 'Gagal mencatat mutasi: ' . $e->getMessage());
        }
    }

    public function delete($id) {
        try {
            $barang = Barang::findOrFail($id);
            $nama = $barang->nama_barang;
            $barang->delete();

            try {
                app(\App\Services\GoogleSheetsService::class)->syncTable('barang');
            } catch (\Throwable $e) {}

            session()->flash('message', 'Data barang "' . $nama . '" berhasil dihapus.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal menghapus barang: ' . $e->getMessage());
        }
    }

    public function with() {
        $query = Barang::query()
            ->when($this->search, function($q) {
                $q->where('nama_barang', 'like', '%'.$this->search.'%');
            })
            ->when($this->filterKategori !== 'all', function($q) {
                $norm = ($this->filterKategori === 'BahanBaku' || $this->filterKategori === 'Material Ops') ? 'BahanBaku' : 'ProdukJual';
                $q->where('tipe', $norm);
            });
        
        return [
            'barangs' => $query->latest()->paginate(10)
        ];
    }
};
?>

<div class="flex flex-col w-full space-y-6 relative text-on-surface" wire:poll.10s>
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
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-2xl">inventory_2</span>
                <h1 class="font-headline-xl text-on-background text-2xl lg:text-3xl font-bold tracking-tight">Kelola Data Barang &amp; Inventaris</h1>
            </div>
            <p class="font-body-md text-sm text-on-surface-variant mt-1">Atur stok produk retail dan material operasional barbershop.</p>
        </div>
        <div class="flex items-center gap-3">
            <button wire:click="syncFromSheets" wire:loading.attr="disabled"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-surface-container-high hover:bg-surface-bright text-primary border border-primary/30 transition shadow-sm text-xs font-semibold cursor-pointer">
                <span wire:loading.remove wire:target="syncFromSheets" class="material-symbols-outlined text-base">sync</span>
                <span wire:loading wire:target="syncFromSheets" class="material-symbols-outlined text-base animate-spin">refresh</span>
                <span>Sinkron Dari Sheets</span>
            </button>
            <button wire:click="create" class="bg-primary text-on-primary hover:bg-primary-fixed hover:scale-105 active:scale-95 transition-all font-semibold text-xs px-4 lg:px-5 py-2.5 rounded-xl flex items-center gap-2 shadow-md shadow-primary/20 cursor-pointer">
                <span class="material-symbols-outlined text-base">add</span>
                <span>Tambah Barang Baru</span>
            </button>
        </div>
    </div>
    
    <!-- Filter & Search Bar -->
    <div class="bg-surface-container-high rounded-2xl p-4 shadow-sm flex flex-col md:flex-row justify-between gap-4 items-center border border-outline-variant/30">
        <div class="flex-1 w-full max-w-md relative group/search">
            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">search</span>
            <input wire:model.live.debounce.300ms="search" class="w-full bg-surface-container text-on-surface placeholder:text-on-surface-variant rounded-xl pl-10 pr-4 py-2.5 outline-none border border-outline-variant/30 focus:border-primary transition-colors text-sm" placeholder="Cari nama barang..." type="text">
        </div>
        <div class="flex items-center gap-3 w-full md:w-auto">
            <select wire:model.live="filterKategori" class="bg-surface-container hover:bg-surface-container-highest text-on-surface text-xs font-semibold px-4 py-2.5 rounded-xl transition-colors border border-outline-variant/30 outline-none cursor-pointer">
                <option value="all">Semua Kategori</option>
                <option value="ProdukJual">Produk Jual (Retail)</option>
                <option value="BahanBaku">Material Operasional</option>
            </select>
        </div>
    </div>
    
    <!-- Table Barang -->
    <div class="overflow-x-auto rounded-2xl bg-surface-container shadow-md border border-outline-variant/30">
        <table class="w-full text-left border-collapse whitespace-nowrap min-w-[900px]">
            <thead>
                <tr class="text-on-surface-variant text-xs uppercase tracking-wider bg-surface-container-highest font-semibold border-b border-outline-variant/20">
                    <th class="px-5 py-4">Kode</th>
                    <th class="px-5 py-4">Nama Barang</th>
                    <th class="px-5 py-4">Kategori</th>
                    <th class="px-5 py-4">Stok Saat Ini</th>
                    <th class="px-5 py-4">Harga Beli</th>
                    <th class="px-5 py-4">Harga Jual</th>
                    <th class="px-5 py-4 text-center">Aksi Cepat Mutasi</th>
                    <th class="px-5 py-4 text-right">Kelola</th>
                </tr>
            </thead>
            <tbody class="text-on-surface text-sm divide-y divide-outline-variant/10">
                @forelse($barangs as $barang)
                    @php
                        $isLow = $barang->stok_sekarang <= $barang->batas_minimum;
                    @endphp
                    <tr class="hover:bg-surface-container-highest/40 transition-colors {{ $isLow ? 'bg-amber-500/5' : '' }}">
                        <td class="px-5 py-4 font-mono text-primary text-xs">#BRG-{{ str_pad($barang->id, 3, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-5 py-4 font-medium flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-highest flex items-center justify-center text-primary shrink-0 overflow-hidden border border-outline-variant/30">
                                @if($barang->gambar_url)
                                    <img src="{{ $barang->gambar_url }}" alt="{{ $barang->nama_barang }}" class="w-full h-full object-cover">
                                @else
                                    <span class="material-symbols-outlined text-primary/60 text-lg">inventory_2</span>
                                @endif
                            </div>
                            <span class="font-semibold">{{ $barang->nama_barang }}</span>
                        </td>
                        <td class="px-5 py-4">
                            @if($barang->tipe === 'ProdukJual' || $barang->tipe === 'Produk Jual')
                                <span class="px-2.5 py-0.5 bg-blue-500/15 text-blue-400 rounded-full text-xs font-semibold border border-blue-500/30">Produk Jual</span>
                            @else
                                <span class="px-2.5 py-0.5 bg-purple-500/15 text-purple-400 rounded-full text-xs font-semibold border border-purple-500/30">Material Ops</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 font-mono">
                            <div class="flex items-center gap-1.5 {{ $isLow ? 'text-amber-400 font-bold' : '' }}">
                                <span class="text-base">{{ $barang->stok_sekarang }}</span>
                                <span class="text-xs text-on-surface-variant font-sans">Pcs (Min: {{ $barang->batas_minimum }})</span>
                                @if($isLow)
                                    <span class="material-symbols-outlined text-sm text-amber-400" title="Stok Menipis!">warning</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-4 font-mono text-xs text-on-surface-variant">
                            Rp {{ number_format($barang->harga_beli, 0, ',', '.') }}
                        </td>
                        <td class="px-5 py-4 font-mono text-xs font-semibold text-primary">
                            @if($barang->harga_jual > 0)
                                Rp {{ number_format($barang->harga_jual, 0, ',', '.') }}
                            @else
                                <span class="text-on-surface-variant/50">-</span>
                            @endif
                        </td>
                        <!-- Aksi Cepat Mutasi (Masuk & Keluar Langsung) -->
                        <td class="px-5 py-4 text-center">
                            <div class="inline-flex items-center gap-1.5">
                                <button wire:click="openMutasiModal({{ $barang->id }}, 'masuk')" title="Input Barang Masuk (Restok)"
                                    class="px-2.5 py-1 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-400 text-xs font-semibold flex items-center gap-1 transition cursor-pointer">
                                    <span class="material-symbols-outlined text-sm">add_circle</span>
                                    <span>Masuk</span>
                                </button>
                                <button wire:click="openMutasiModal({{ $barang->id }}, 'keluar')" title="Input Barang Keluar (Pengeluaran)"
                                    class="px-2.5 py-1 rounded-lg bg-rose-500/15 hover:bg-rose-500/25 text-rose-400 text-xs font-semibold flex items-center gap-1 transition cursor-pointer">
                                    <span class="material-symbols-outlined text-sm">remove_circle</span>
                                    <span>Keluar</span>
                                </button>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button wire:click="edit({{ $barang->id }})" class="p-1.5 text-secondary hover:text-primary hover:bg-surface-container-highest rounded-lg transition-colors cursor-pointer" title="Edit Barang">
                                    <span class="material-symbols-outlined text-lg">edit</span>
                                </button>
                                <button wire:click="delete({{ $barang->id }})" onclick="confirm('Yakin ingin menghapus barang ini?') || event.stopImmediatePropagation()" class="p-1.5 text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 rounded-lg transition-colors cursor-pointer" title="Hapus Barang">
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-10 text-center text-on-surface-variant text-sm">Belum ada data barang.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $barangs->links() }}
    </div>

    <!-- Modal Form Tambah / Edit Barang -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-3 sm:p-4 md:p-6 overflow-y-auto">
            <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] sm:max-h-[90vh] flex flex-col overflow-hidden border border-outline-variant/30 animate-[scale-up_0.2s_ease-out]">
                <!-- Sticky Header -->
                <div class="px-4 py-3 sm:px-5 sm:py-4 border-b border-outline-variant/20 flex justify-between items-center bg-surface-container-lowest shrink-0">
                    <h3 class="font-bold text-base text-on-surface">{{ $isEdit ? 'Edit Data Barang' : 'Tambah Barang Baru' }}</h3>
                    <button wire:click="$set('showModal', false)" class="w-8 h-8 rounded-lg flex items-center justify-center text-on-surface-variant hover:text-error hover:bg-white/5 transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>

                <form wire:submit="save" class="flex flex-col flex-1 overflow-hidden min-h-0">
                    <!-- Scrollable Body -->
                    <div class="p-4 sm:p-5 overflow-y-auto space-y-4 flex-1">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Nama Barang</label>
                            <input wire:model="nama_barang" type="text" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                            @error('nama_barang') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Path / URL Gambar (Opsional)</label>
                            <input wire:model="gambar" type="text" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" placeholder="e.g. images/barang/pomade-oil.jpg">
                            @error('gambar') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Kategori / Tipe Barang</label>
                            <select wire:model="tipe" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors cursor-pointer" required>
                                <option value="ProdukJual">Produk Jual (Retail / Eceran)</option>
                                <option value="BahanBaku">Material Operasional (Bahan Baku / Ekstra Salon)</option>
                            </select>
                            @error('tipe') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Stok Barang</label>
                                <input wire:model="stok_sekarang" type="number" min="0" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                                @error('stok_sekarang') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Batas Minimum (Alert)</label>
                                <input wire:model="batas_minimum" type="number" min="0" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                                @error('batas_minimum') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Harga Beli / Modal (Rp)</label>
                                <input wire:model="harga_beli" type="number" min="0" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                                @error('harga_beli') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Harga Jual (Rp)</label>
                                <input wire:model="harga_jual" type="number" min="0" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                                @error('harga_jual') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Satuan Unit</label>
                            <input wire:model="satuan" type="text" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" placeholder="e.g. Pcs, Botol, Tube, Box" required>
                            @error('satuan') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Sticky Footer -->
                    <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-t border-outline-variant/20 bg-surface-container-lowest shrink-0 flex justify-end gap-2 sm:gap-3">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl font-label-md text-xs font-semibold text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest transition-colors">Batal</button>
                        <button type="submit" class="px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl font-label-md text-xs font-semibold bg-primary text-on-primary hover:bg-primary-container transition-all flex items-center gap-1.5 shadow-md">
                            <span wire:loading.remove wire:target="save">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Barang' }}</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Form Cepat Mutasi Masuk / Keluar Barang -->
    @if($showMutasiModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-3 sm:p-4 md:p-6 overflow-y-auto">
            <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-md max-h-[92vh] sm:max-h-[90vh] flex flex-col overflow-hidden border border-outline-variant/30 animate-[scale-up_0.2s_ease-out]">
                <!-- Sticky Header -->
                <div class="px-4 py-3 sm:px-5 sm:py-4 border-b border-outline-variant/20 flex justify-between items-center bg-surface-container-lowest shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-xl">swap_horiz</span>
                        <h3 class="font-bold text-sm sm:text-base text-on-surface truncate">Input Mutasi: {{ $mutasiBarangNama }}</h3>
                    </div>
                    <button wire:click="$set('showMutasiModal', false)" class="w-8 h-8 rounded-lg flex items-center justify-center text-on-surface-variant hover:text-error hover:bg-white/5 transition-colors cursor-pointer shrink-0">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>

                <form wire:submit="saveMutasi" class="flex flex-col flex-1 overflow-hidden min-h-0">
                    <!-- Scrollable Body -->
                    <div class="p-4 sm:p-5 overflow-y-auto space-y-4 flex-1">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Jenis Mutasi</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="cursor-pointer">
                                    <input wire:model.live="mutasiJenis" value="masuk" class="peer sr-only" type="radio">
                                    <div class="py-2.5 px-3 rounded-xl border border-outline-variant/30 bg-surface-container text-on-surface text-center hover:bg-surface-container-high transition-colors text-xs font-semibold peer-checked:border-emerald-500 peer-checked:bg-emerald-500/15 peer-checked:text-emerald-400 flex items-center justify-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">add_circle</span>
                                        <span>Masuk (+)</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input wire:model.live="mutasiJenis" value="keluar" class="peer sr-only" type="radio">
                                    <div class="py-2.5 px-3 rounded-xl border border-outline-variant/30 bg-surface-container text-on-surface text-center hover:bg-surface-container-high transition-colors text-xs font-semibold peer-checked:border-rose-500 peer-checked:bg-rose-500/15 peer-checked:text-rose-400 flex items-center justify-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">remove_circle</span>
                                        <span>Keluar (-)</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Jumlah (Qty / Pcs)</label>
                            <input wire:model="mutasiJumlah" type="number" min="1" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                            @error('mutasiJumlah') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-on-surface uppercase tracking-wider">Keterangan / Alasan (Opsional)</label>
                            <textarea wire:model="mutasiKeterangan" class="w-full bg-background rounded-xl py-2.5 px-3.5 text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" rows="2" placeholder="Cth: Restok dari suplier, pemakaian salon, barang rusak, dll."></textarea>
                        </div>
                    </div>

                    <!-- Sticky Footer -->
                    <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-t border-outline-variant/20 bg-surface-container-lowest shrink-0 flex justify-end gap-2 sm:gap-3">
                        <button type="button" wire:click="$set('showMutasiModal', false)" class="px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl font-label-md text-xs font-semibold text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest transition-colors">Batal</button>
                        <button type="submit" class="px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl font-label-md text-xs font-semibold bg-primary text-on-primary hover:bg-primary-container transition-all flex items-center gap-1.5 shadow-md">
                            <span wire:loading.remove wire:target="saveMutasi">Simpan Mutasi</span>
                            <span wire:loading wire:target="saveMutasi">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
