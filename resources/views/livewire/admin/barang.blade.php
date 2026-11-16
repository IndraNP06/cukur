<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Barang;

new #[Layout('components.layouts.admin')] #[Title('Kelola Barang - Ran Cukurin')] class extends Component {
 use WithPagination;

 public $search = '';
 public $filterKategori = 'all';

 public $showModal = false;
 public $isEdit = false;
 public $barangId;

 public $nama_barang;
 public $tipe = 'Produk Jual';
 public $stok_sekarang = 0;
 public $batas_minimum = 0;
 public $harga_beli = 0;
 public $harga_jual = 0;
 public $gambar;

 public function rules() {
        return [
 'nama_barang' => 'required|string|max:255',
 'tipe' => 'required|string|in:Produk Jual,Material Ops,Aset',
 'stok_sekarang' => 'required|integer|min:0',
 'batas_minimum' => 'required|integer|min:0',
 'harga_beli' => 'required|numeric|min:0',
 'harga_jual' => 'required|numeric|min:0',
 'gambar' => 'nullable|string|max:255',
 ];
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
 $this->isEdit = false;
 $this->showModal = true;
 }

 public function edit($id) {
 $this->resetValidation();
 $barang = Barang::findOrFail($id);
 $this->barangId = $barang->id;
 $this->nama_barang = $barang->nama_barang;
 $this->tipe = $barang->tipe;
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

 if ($this->isEdit) {
 Barang::where('id', $this->barangId)->update([
 'nama_barang' => $this->nama_barang,
 'tipe' => $this->tipe,
 'stok_sekarang' => $this->stok_sekarang,
 'batas_minimum' => $this->batas_minimum,
 'harga_beli' => $this->harga_beli,
 'harga_jual' => $this->harga_jual,
 'gambar' => $this->gambar,
 ]);
 } else {
 Barang::create([
 'nama_barang' => $this->nama_barang,
 'tipe' => $this->tipe,
 'stok_sekarang' => $this->stok_sekarang,
 'batas_minimum' => $this->batas_minimum,
 'harga_beli' => $this->harga_beli,
 'harga_jual' => $this->harga_jual,
 'gambar' => $this->gambar,
 ]);
 }

 $this->showModal = false;
 session()->flash('message', 'Data barang berhasil disimpan.');
 }

 public function delete($id) {
 Barang::findOrFail($id)->delete();
 session()->flash('message', 'Data barang berhasil dihapus.');
 }

 public function with() {
 $query = Barang::query()
 ->when($this->search, function($q) {
 $q->where('nama_barang', 'like', '%'.$this->search.'%');
 })
 ->when($this->filterKategori !== 'all', function($q) {
 $q->where('tipe', $this->filterKategori);
 });
 
 return [
 'barangs' => $query->latest()->paginate(10)
 ];
 }
};
?>

<div class="flex flex-col w-full space-y-8 relative text-on-surface">
 @if(session()->has('message'))
 <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl flex items-center justify-between">
 <span>{{ session('message') }}</span>
 </div>
 @endif

 <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 lg:gap-5 mb-8">
 <div>
 <h1 class="font-headline-xl text-primary-fixed mb-2 tracking-tight text-headline-xl">Kelola Data Barang &amp; Inventaris</h1>
 <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl">Atur stok produk retail dan material operasional barbershop.</p>
 </div>
 <button wire:click="create" class="bg-primary text-on-primary hover:bg-primary-fixed hover:scale-105 active:scale-95 transition-all duration-300 font-label-md text-label-md px-4 lg:px-5 py-3 rounded-full flex items-center gap-2 shadow-lg shadow-primary/20">
 <span class="material-symbols-outlined text-[20px]">add</span>
 Tambah Barang Baru
 </button>
 </div>
 
 <div class="bg-surface-container-high rounded-3xl p-4 lg:p-5 shadow-xl flex flex-col gap-4 lg:gap-5 relative overflow-hidden group">
 <div class="absolute -top-32 -right-32 w-56 h-56 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-colors duration-700 pointer-events-none"></div>
 <div class="flex flex-col md:flex-row justify-between gap-4 items-center z-10">
 <div class="flex-1 w-full max-w-md relative group/search">
 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within/search:text-primary transition-colors">search</span>
 <input wire:model.live.debounce.300ms="search" class="w-full bg-surface-container text-on-surface placeholder:text-on-surface-variant rounded-xl pl-12 pr-4 py-3 outline-none border border-transparent focus:border-primary transition-colors font-body-md shadow-inner" placeholder="Cari nama barang..." type="text">
 </div>
 <div class="flex items-center gap-4 w-full md:w-auto">
 <div class="relative">
 <select wire:model.live="filterKategori" class="flex items-center gap-2 bg-surface-container hover:bg-surface-container-highest text-on-surface font-label-md px-5 py-3 rounded-xl transition-colors border border-transparent hover:border-outline-variant outline-none cursor-pointer appearance-none">
 <option value="all">Semua Kategori</option>
 <option value="Produk Jual">Produk Jual</option>
 <option value="Material Ops">Material Ops</option>
 </select>
 </div>
 </div>
 </div>
 
 <div class="overflow-x-auto rounded-2xl bg-surface-container/50 border-none z-10 backdrop-blur-sm">
 <table class="w-full text-left border-collapse whitespace-nowrap">
 <thead>
 <tr class="text-on-surface-variant font-label-md uppercase tracking-wider text-[11px] bg-surface-container/80">
 <th class="px-4 lg:px-5 py-5 rounded-tl-2xl font-semibold">Kode</th>
 <th class="px-4 lg:px-5 py-5 font-semibold">Nama Barang</th>
 <th class="px-4 lg:px-5 py-5 font-semibold">Kategori</th>
 <th class="px-4 lg:px-5 py-5 font-semibold">Stok Saat Ini</th>
 <th class="px-4 lg:px-5 py-5 font-semibold">Harga Jual</th>
 <th class="px-4 lg:px-5 py-5 text-right rounded-tr-2xl font-semibold">Aksi</th>
 </tr>
 </thead>
 <tbody class="text-on-surface font-body-md divide-y divide-surface-container/30">
 @forelse($barangs as $barang)
 <tr class="hover:bg-surface-container-highest/50 transition-colors group/row {{ $barang->stok_sekarang <= $barang->batas_minimum ? 'bg-error/5' : '' }}">
 <td class="px-4 lg:px-5 py-5 font-mono text-primary-fixed/80">#BRG-{{ str_pad($barang->id, 3, '0', STR_PAD_LEFT) }}</td>
 <td class="px-4 lg:px-5 py-5 font-medium flex items-center gap-3">
 <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary shrink-0 overflow-hidden relative border border-white/10">
 @if($barang->gambar_url)
 <img src="{{ $barang->gambar_url }}" alt="{{ $barang->nama_barang }}" class="w-full h-full object-cover">
 @else
 <span class="material-symbols-outlined text-primary/50">inventory_2</span>
 @endif
 </div>
 {{ $barang->nama_barang }}
 </td>
 <td class="px-4 lg:px-5 py-5">
 @if($barang->tipe === 'Produk Jual')
 <span class="px-3 py-1 bg-secondary-container/40 text-secondary-fixed rounded-full text-[11px] font-label-md inline-block border border-secondary-container">Produk Jual</span>
 @else
 <span class="px-3 py-1 bg-tertiary-container/30 text-tertiary-fixed rounded-full text-[11px] font-label-md inline-block border border-tertiary-container/50">Material Ops</span>
 @endif
 </td>
 <td class="px-4 lg:px-5 py-5 font-mono flex items-center gap-2 {{ $barang->stok_sekarang <= $barang->batas_minimum ? 'text-error font-semibold' : '' }}">
 {{ $barang->stok_sekarang }} <span class="text-on-surface-variant text-sm">Pcs</span>
 @if($barang->stok_sekarang <= $barang->batas_minimum)
 <span class="material-symbols-outlined text-[16px] animate-pulse">warning</span>
 @endif
 </td>
 <td class="px-4 lg:px-5 py-5 font-mono text-tertiary-fixed">
 @if($barang->harga_jual > 0)
 Rp {{ number_format($barang->harga_jual, 0, ',', '.') }}
 @else
 <span class="text-on-surface-variant/50">-</span>
 @endif
 </td>
 <td class="px-4 lg:px-5 py-5 text-right">
 <div class="flex items-center justify-end gap-2 opacity-0 group-hover/row:opacity-100 transition-opacity">
 <button wire:click="edit({{ $barang->id }})" class="p-2 text-secondary hover:text-secondary-fixed hover:bg-secondary-container/50 rounded-lg transition-colors" title="Edit">
 <span class="material-symbols-outlined text-[20px]">edit</span>
 </button>
 <button wire:click="delete({{ $barang->id }})" onclick="confirm('Yakin ingin menghapus barang ini?') || event.stopImmediatePropagation()" class="p-2 text-error hover:text-error-container hover:bg-error-container/20 rounded-lg transition-colors" title="Hapus">
 <span class="material-symbols-outlined text-[20px]">delete</span>
 </button>
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="6" class="px-4 lg:px-5 py-5 lg:py-5 text-center text-on-surface-variant">Belum ada data barang.</td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>
 <div class="flex flex-col sm:flex-row justify-between items-center mt-2 pt-4 border-t border-surface-container z-10 gap-4">
 {{ $barangs->links() }}
 </div>
 </div>

 <!-- Modal Form -->
 @if($showModal)
 <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
 <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-white/10 max-h-[90vh] overflow-y-auto">
 <div class="px-4 lg:px-5 py-4 border-b border-white/10 flex justify-between items-center bg-surface-container-lowest sticky top-0 z-10">
 <h3 class="font-headline-md text-on-surface text-headline-md">{{ $isEdit ? 'Edit Barang' : 'Tambah Barang Baru' }}</h3>
 <button wire:click="$set('showModal', false)" class="text-on-surface-variant hover:text-error transition-colors">
 <span class="material-symbols-outlined">close</span>
 </button>
 </div>
 <form wire:submit="save" class="p-4 lg:p-5 space-y-4">
 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Nama Barang</label>
 <input wire:model="nama_barang" type="text" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
 @error('nama_barang') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Path / URL Gambar (Opsional)</label>
 <input wire:model="gambar" type="text" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" placeholder="e.g. images/barang/pomade-oil.jpg">
 @error('gambar') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>

 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Kategori / Tipe</label>
 <select wire:model="tipe" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors cursor-pointer" required>
 <option value="Produk Jual">Produk Jual (Eceran)</option>
 <option value="Material Ops">Material Operasional</option>
 </select>
 @error('tipe') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>

 <div class="flex gap-4">
 <div class="space-y-2 flex-1">
 <label class="block font-label-md text-label-md text-on-surface">Stok Awal</label>
 <input wire:model="stok_sekarang" type="number" min="0" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
 @error('stok_sekarang') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 <div class="space-y-2 flex-1">
 <label class="block font-label-md text-label-md text-on-surface">Batas Minimum (Alert)</label>
 <input wire:model="batas_minimum" type="number" min="0" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
 @error('batas_minimum') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 </div>

 <div class="flex gap-4">
 <div class="space-y-2 flex-1">
 <label class="block font-label-md text-label-md text-on-surface">Harga Beli / Modal</label>
 <input wire:model="harga_beli" type="number" min="0" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
 @error('harga_beli') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 <div class="space-y-2 flex-1">
 <label class="block font-label-md text-label-md text-on-surface">Harga Jual</label>
 <input wire:model="harga_jual" type="number" min="0" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
 <p class="text-label-sm text-on-surface-variant mt-1">Kosongkan/0 jika hanya material ops.</p>
 @error('harga_jual') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 </div>
 
 <div class="pt-6 flex justify-end gap-3 border-t border-white/10 mt-6">
 <button type="button" wire:click="$set('showModal', false)" class="px-4 lg:px-5 py-2.5 rounded-full font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-highest transition-colors">Batal</button>
 <button type="submit" class="px-4 lg:px-5 py-2.5 rounded-full font-label-md text-label-md bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors shadow-lg shadow-primary/20">
 <span wire:loading.remove wire:target="save">Simpan Barang</span>
 <span wire:loading wire:target="save">Menyimpan...</span>
 </button>
 </div>
 </form>
 </div>
 </div>
 @endif
</div>

