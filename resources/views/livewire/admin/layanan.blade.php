<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Layanan;

new #[Layout('components.layouts.admin')] #[Title('Kelola Layanan - Ran Cukurin')] class extends Component {
 use WithPagination;

 public $search = '';
 public $filterStatus = 'all';

 public $showModal = false;
 public $isEdit = false;
 public $layananId;

 public $nama_layanan;
 public $deskripsi;
 public $harga;
 public $estimasi_durasi_menit;
 public $gambar;
 public $is_active = true;
    public $barang_id = null;
    public $jumlah_pemakaian_barang = 1;

    public function mount() {
    }

 public function syncFromSheets() {
 try {
 $service = app(\App\Services\GoogleSheetsService::class);
 $service->pullTable('layanan');
 cache()->forget('sheets_synced_layanan_admin');
 session()->flash('message', 'Data layanan berhasil disinkronkan langsung dari Google Spreadsheet!');
 } catch (\Throwable $e) {
 session()->flash('error', 'Gagal menyinkronkan: ' . $e->getMessage());
 }
 }

 public function rules() {
        return [
 'nama_layanan' => 'required|string|max:255',
 'deskripsi' => 'nullable|string',
 'harga' => 'required|numeric|min:0',
 'estimasi_durasi_menit' => 'required|integer|min:1',
 'gambar' => 'nullable|string|max:255',
 'is_active' => 'boolean',
        'barang_id' => 'nullable|exists:barang,id',
        'jumlah_pemakaian_barang' => 'required|integer|min:1',
 ];
    }

 public function updatedSearch() {
 $this->resetPage();
 }

 public function updatedFilterStatus() {
 $this->resetPage();
 }

 public function create() {
 $this->resetValidation();
 $this->reset(['nama_layanan', 'deskripsi', 'harga', 'estimasi_durasi_menit', 'gambar', 'is_active', 'layananId', 'barang_id']);
        $this->jumlah_pemakaian_barang = 1;
 $this->isEdit = false;
 $this->showModal = true;
 }

 public function edit($id) {
 $this->resetValidation();
 $layanan = Layanan::findOrFail($id);
 $this->layananId = $layanan->id;
 $this->nama_layanan = $layanan->nama_layanan;
 $this->deskripsi = $layanan->deskripsi;
 $this->harga = $layanan->harga;
 $this->estimasi_durasi_menit = $layanan->estimasi_durasi_menit;
 $this->gambar = $layanan->gambar;
 $this->is_active = $layanan->is_active;
        $this->barang_id = $layanan->barang_id;
        $this->jumlah_pemakaian_barang = $layanan->jumlah_pemakaian_barang ?? 1;
 $this->isEdit = true;
 $this->showModal = true;
 }

 public function save() {
 $this->validate();

 $data = [
            'nama_layanan' => $this->nama_layanan,
            'deskripsi' => $this->deskripsi,
            'harga' => $this->harga,
            'estimasi_durasi_menit' => $this->estimasi_durasi_menit,
            'gambar' => $this->gambar,
            'is_active' => $this->is_active,
            'barang_id' => $this->barang_id ?: null,
            'jumlah_pemakaian_barang' => $this->barang_id ? ($this->jumlah_pemakaian_barang ?? 1) : 1,
        ];

        if ($this->isEdit) {
            Layanan::where('id', $this->layananId)->update($data);
        } else {
            Layanan::create($data);
        }

 $this->showModal = false;
 session()->flash('message', 'Layanan berhasil disimpan.');
 }

 public function delete($id) {
 Layanan::findOrFail($id)->delete();
 session()->flash('message', 'Layanan berhasil dihapus.');
 }

 public function toggleStatus($id) {
 $layanan = Layanan::findOrFail($id);
 $layanan->is_active = !$layanan->is_active;
 $layanan->save();
 }

 public function with() {
 $query = Layanan::with('barang')
 ->when($this->search, function($q) {
 $q->where('nama_layanan', 'like', '%'.$this->search.'%');
 })
 ->when($this->filterStatus !== 'all', function($q) {
 $q->where('is_active', $this->filterStatus === 'active');
 });
 
 return [
 'layanans' => $query->latest()->paginate(10),
            'barangs' => \App\Models\Barang::orderBy('nama_barang')->get()
 ];
 }
};
?>

<div class="flex flex-col w-full text-on-surface">
 @if(session()->has('message'))
 <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl flex items-center justify-between">
 <span>{{ session('message') }}</span>
 </div>
 @endif
 
 <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
 <div>
 <h1 class="font-headline-xl text-primary mb-2 text-headline-xl">Kelola Data Layanan &amp; Harga</h1>
 <p class="font-body-md text-body-md text-on-surface-variant max-w-2xl">
 Atur dan perbarui daftar layanan, durasi pengerjaan, dan harga standar yang berlaku di seluruh cabang barbershop.
 </p>
 </div>
 <div class="flex items-center gap-3">
 <button wire:click="syncFromSheets" type="button" class="bg-surface-container-high hover:bg-surface-container-highest text-primary border border-primary/30 font-label-md text-label-md px-4 py-3 rounded-full flex items-center gap-2 transition-all shadow-md">
 <span class="material-symbols-outlined text-[20px] animate-spin" wire:loading wire:target="syncFromSheets">sync</span>
 <span class="material-symbols-outlined text-[20px]" wire:loading.remove wire:target="syncFromSheets">sync</span>
 <span>Sinkron Spreadsheet</span>
 </button>
 <button wire:click="create" class="bg-primary text-on-primary hover:scale-105 hover:bg-primary-container transition-all duration-300 font-label-md text-label-md px-4 lg:px-5 py-3 rounded-full flex items-center gap-2 shadow-lg shadow-primary/20">
 <span class="material-symbols-outlined text-[20px]">add</span>
 Tambah Layanan Baru
 </button>
 </div>
 </div>

 <!-- Toolbar -->
 <div class="bg-surface-container rounded-2xl p-4 lg:p-5 shadow-xl shadow-black/40 mb-8 relative overflow-hidden group">
 <div class="absolute -right-20 -top-20 w-12 h-12 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-colors duration-700"></div>
 <div class="flex flex-col md:flex-row gap-4 justify-between items-center relative z-10">
 <div class="relative w-full md:w-56">
 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
 <input wire:model.live.debounce.300ms="search" class="w-full bg-background rounded-xl py-3 pl-12 pr-4 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant border border-transparent focus:border-primary outline-none transition-colors duration-300" placeholder="Cari nama layanan..." type="text">
 </div>
 <div class="flex items-center gap-4 w-full md:w-auto">
 <div class="relative w-full md:w-12">
 <select wire:model.live="filterStatus" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-transparent focus:border-primary outline-none appearance-none transition-colors duration-300 cursor-pointer">
 <option value="all">Semua Status</option>
 <option value="active">Aktif</option>
 <option value="inactive">Nonaktif</option>
 </select>
 <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">expand_more</span>
 </div>
 </div>
 </div>
 </div>

 <!-- Table -->
 <div class="bg-surface-container rounded-2xl shadow-xl shadow-black/40 overflow-hidden relative">
 <div class="absolute inset-0 pointer-events-none border border-white/5 rounded-2xl"></div>
 <div class="overflow-x-auto">
 <table class="w-full text-left font-body-md text-body-md">
 <thead class="bg-surface-container-high text-on-surface-variant font-label-md text-label-md uppercase tracking-wider">
 <tr>
 <th class="px-4 lg:px-5 py-4 font-medium">Nama Layanan</th>
                    <th class="px-4 lg:px-5 py-4 font-medium">Bahan Terpakai</th>
 <th class="px-4 lg:px-5 py-4 font-medium">Durasi</th>
 <th class="px-4 lg:px-5 py-4 font-medium">Harga</th>
 <th class="px-4 lg:px-5 py-4 font-medium">Status</th>
 <th class="px-4 lg:px-5 py-4 font-medium text-right rounded-tr-2xl">Aksi</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-white/5">
 @forelse($layanans as $layanan)
 <tr class="hover:bg-white/5 transition-colors duration-200 group">
 <td class="px-4 lg:px-5 py-4">
 <div class="flex items-center gap-3">
 <div class="w-12 h-12 rounded-xl bg-surface-dim overflow-hidden shrink-0 border border-white/10 flex items-center justify-center">
 @if($layanan->gambar_url)
 <img src="{{ $layanan->gambar_url }}" alt="{{ $layanan->nama_layanan }}" class="w-full h-full object-cover">
 @else
 <span class="material-symbols-outlined text-primary text-[24px]">content_cut</span>
 @endif
 </div>
 <div>
 <p class="font-headline-md text-on-surface text-[18px] mb-0.5 text-headline-md">{{ $layanan->nama_layanan }}</p>
 <p class="text-on-surface-variant text-label-sm font-label-sm">{{ $layanan->deskripsi }}</p>
 </div>
 </div>
 </td>
 <td class="px-4 lg:px-5 py-4">
 @if($layanan->barang)
 <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-primary/10 border border-primary/20 text-primary text-xs">
 <span class="material-symbols-outlined text-[15px]">inventory_2</span>
 <span>{{ $layanan->jumlah_pemakaian_barang }}x {{ $layanan->barang->nama_barang }}</span>
 </div>
 <div class="text-[11px] text-on-surface-variant mt-0.5">
 Sisa: <span class="font-mono font-medium {{ $layanan->barang->stok_sekarang <= 5 ? 'text-amber-400' : 'text-on-surface' }}">{{ $layanan->barang->stok_sekarang }} {{ $layanan->barang->satuan }}</span>
 </div>
 @else
 <span class="text-on-surface-variant text-xs italic">-</span>
 @endif
 </td>
 <td class="px-4 lg:px-5 py-4 text-on-surface-variant font-mono">{{ $layanan->estimasi_durasi_menit }} Menit</td>
 <td class="px-4 lg:px-5 py-4 font-label-md text-label-md text-primary">Rp {{ number_format($layanan->harga, 0, ',', '.') }}</td>
 <td class="px-4 lg:px-5 py-4">
 @if($layanan->is_active)
 <button wire:click="toggleStatus({{ $layanan->id }})" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 font-label-sm text-label-sm cursor-pointer hover:bg-emerald-500/20">
 <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Aktif
 </button>
 @else
 <button wire:click="toggleStatus({{ $layanan->id }})" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-highest text-on-surface-variant font-label-sm text-label-sm cursor-pointer hover:bg-surface-container-highest/80">
 <span class="w-1.5 h-1.5 rounded-full bg-on-surface-variant"></span> Nonaktif
 </button>
 @endif
 </td>
 <td class="px-4 lg:px-5 py-4">
 <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
 <button wire:click="edit({{ $layanan->id }})" class="w-10 h-10 rounded-full bg-surface-container-highest flex items-center justify-center text-on-surface hover:text-primary hover:bg-surface-bright transition-colors"><span class="material-symbols-outlined text-[20px]">edit</span></button>
 <button wire:click="delete({{ $layanan->id }})" onclick="confirm('Yakin ingin menghapus layanan ini?') || event.stopImmediatePropagation()" class="w-10 h-10 rounded-full bg-surface-container-highest flex items-center justify-center text-on-surface hover:text-error hover:bg-surface-bright transition-colors"><span class="material-symbols-outlined text-[20px]">delete</span></button>
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="6" class="px-4 lg:px-5 py-5 lg:py-5 text-center text-on-surface-variant">Belum ada data layanan.</td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>
 <div class="px-4 lg:px-5 py-4 bg-surface-container-high border-t border-white/5">
 {{ $layanans->links() }}
 </div>
 </div>

 <!-- Modal Form -->
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4 sm:p-6 overflow-y-auto">
        <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden border border-white/10 animate-[scale-up_0.2s_ease-out]">
            <!-- Modal Header (Always Visible / Sticky) -->
            <div class="px-5 py-4 border-b border-white/10 flex justify-between items-center bg-surface-container-lowest shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[20px]">{{ $isEdit ? 'edit_note' : 'add_circle' }}</span>
                    </div>
                    <h3 class="font-headline-md text-on-surface text-lg font-bold">{{ $isEdit ? 'Edit Layanan' : 'Tambah Layanan Baru' }}</h3>
                </div>
                <button type="button" wire:click="$set('showModal', false)" class="w-8 h-8 rounded-lg flex items-center justify-center text-on-surface-variant hover:text-error hover:bg-white/5 transition-colors focus:outline-none">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <!-- Form Container with scrollable content & pinned footer -->
            <form wire:submit="save" class="flex flex-col flex-1 overflow-hidden min-h-0">
                <!-- Scrollable Form Body -->
                <div class="p-5 overflow-y-auto space-y-4 flex-1">
                    <!-- Row 1: Nama Layanan & Gambar URL -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block font-label-md text-xs font-semibold text-on-surface">Nama Layanan <span class="text-error">*</span></label>
                            <input wire:model="nama_layanan" type="text" class="w-full bg-background rounded-xl py-2.5 px-3.5 font-body-md text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" placeholder="e.g. Basic Haircut" required>
                            @error('nama_layanan') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="space-y-1.5">
                            <label class="block font-label-md text-xs font-semibold text-on-surface">Path / URL Gambar (Opsional)</label>
                            <input wire:model="gambar" type="text" class="w-full bg-background rounded-xl py-2.5 px-3.5 font-body-md text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" placeholder="e.g. images/layanan/basic-haircut.jpg">
                            @error('gambar') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Row 2: Deskripsi -->
                    <div class="space-y-1.5">
                        <label class="block font-label-md text-xs font-semibold text-on-surface">Deskripsi / Rincian Layanan</label>
                        <textarea wire:model="deskripsi" class="w-full bg-background rounded-xl py-2.5 px-3.5 font-body-md text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" rows="2" placeholder="Jelaskan detail perawatan / pengerjaan..."></textarea>
                        @error('deskripsi') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Row 3: Durasi & Harga -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block font-label-md text-xs font-semibold text-on-surface">Durasi (Menit) <span class="text-error">*</span></label>
                            <div class="relative">
                                <input wire:model="estimasi_durasi_menit" type="number" min="1" class="w-full bg-background rounded-xl py-2.5 pl-3.5 pr-14 font-body-md text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" placeholder="30" required>
                                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs text-on-surface-variant font-mono">Menit</span>
                            </div>
                            @error('estimasi_durasi_menit') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label class="block font-label-md text-xs font-semibold text-on-surface">Harga Standar (Rp) <span class="text-error">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-primary font-mono font-bold">Rp</span>
                                <input wire:model="harga" type="number" min="0" class="w-full bg-background rounded-xl py-2.5 pl-10 pr-3.5 font-body-md text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" placeholder="50000" required>
                            </div>
                            @error('harga') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Row 4: Pemakaian Bahan / Barang (Otomatis Terpotong) -->
                    <div class="p-3.5 bg-surface-container-highest/40 rounded-xl border border-white/5 space-y-2.5">
                        <div class="flex items-center gap-2 text-primary font-medium text-xs">
                            <span class="material-symbols-outlined text-[16px]">inventory_2</span>
                            <span class="font-bold">Pemakaian Bahan Baku / Produk (Otomatis Terpotong)</span>
                        </div>
                        <p class="text-[11px] text-on-surface-variant leading-relaxed">
                            Pilih bahan baku/produk yang otomatis berkurang stoknya saat layanan ini dipesan (misal: obat keriting/perming, cat semir, bleaching, shampoo khusus).
                        </p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-0.5">
                            <div class="md:col-span-2 space-y-1">
                                <label class="block text-[11px] font-label-md text-on-surface-variant font-medium">Pilih Bahan / Barang</label>
                                <select wire:model.live="barang_id" class="w-full bg-background rounded-xl py-2 px-3 text-xs text-on-surface border border-outline-variant focus:border-primary outline-none">
                                    <option value="">-- Tanpa Pemotongan Stok --</option>
                                    @foreach($barangs as $b)
                                        <option value="{{ $b->id }}">
                                            {{ $b->nama_barang }} (Stok: {{ $b->stok_sekarang }} {{ $b->satuan }}) - [{{ $b->tipe }}]
                                        </option>
                                    @endforeach
                                </select>
                                @error('barang_id') <span class="text-error text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="space-y-1">
                                <label class="block text-[11px] font-label-md text-on-surface-variant font-medium">Qty Terpotong</label>
                                <input wire:model="jumlah_pemakaian_barang" type="number" min="1" {{ !$barang_id ? 'disabled' : '' }} class="w-full bg-background rounded-xl py-2 px-3 text-xs text-on-surface border border-outline-variant focus:border-primary outline-none {{ !$barang_id ? 'opacity-40 cursor-not-allowed' : '' }}" required>
                                @error('jumlah_pemakaian_barang') <span class="text-error text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        @if($barang_id)
                            @php
                                $selectedBarang = $barangs->firstWhere('id', $barang_id);
                            @endphp
                            @if($selectedBarang)
                                <div class="text-[11px] flex items-center gap-1.5 text-emerald-400 bg-emerald-500/10 px-2.5 py-1.5 rounded-lg border border-emerald-500/20">
                                    <span class="material-symbols-outlined text-[15px]">check_circle</span>
                                    <span>Saat dipesan, stok <strong>{{ $selectedBarang->nama_barang }}</strong> otomatis dipotong <strong>{{ $jumlah_pemakaian_barang }} {{ $selectedBarang->satuan }}</strong> (Sisa saat ini: {{ $selectedBarang->stok_sekarang }} {{ $selectedBarang->satuan }}).</span>
                                </div>
                            @endif
                        @endif
                    </div>

                    <!-- Row 5: Status Aktif -->
                    <div class="flex items-center gap-2.5 pt-1">
                        <input wire:model="is_active" id="is_active" type="checkbox" class="w-4 h-4 rounded border-outline-variant bg-background text-primary focus:ring-primary accent-primary cursor-pointer">
                        <label for="is_active" class="font-label-md text-xs font-medium text-on-surface cursor-pointer select-none">Status Layanan Aktif (Ditampilkan ke pelanggan untuk booking)</label>
                    </div>
                </div>
                
                <!-- Modal Footer (Always Visible / Sticky at Bottom) -->
                <div class="px-5 py-3.5 border-t border-white/10 bg-surface-container-lowest shrink-0 flex justify-end items-center gap-3">
                    <button type="button" wire:click="$set('showModal', false)" class="px-5 py-2.5 rounded-xl font-label-md text-xs font-semibold text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest transition-colors">Batal</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl font-label-md text-xs font-semibold bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-all shadow-md shadow-primary/20 flex items-center gap-2">
                        <span wire:loading.remove wire:target="save" class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            <span>Simpan Layanan</span>
                        </span>
                        <span wire:loading wire:target="save" class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                            <span>Menyimpan...</span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
