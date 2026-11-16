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

 public function mount() {
 try {
 if (!cache()->has('sheets_synced_layanan_admin')) {
 app(\App\Services\GoogleSheetsService::class)->pullTable('layanan');
 cache()->put('sheets_synced_layanan_admin', true, now()->addSeconds(15));
 }
 } catch (\Throwable $e) {}
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
 $this->reset(['nama_layanan', 'deskripsi', 'harga', 'estimasi_durasi_menit', 'gambar', 'is_active', 'layananId']);
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
 $this->isEdit = true;
 $this->showModal = true;
 }

 public function save() {
 $this->validate();

 if ($this->isEdit) {
 Layanan::where('id', $this->layananId)->update([
 'nama_layanan' => $this->nama_layanan,
 'deskripsi' => $this->deskripsi,
 'harga' => $this->harga,
 'estimasi_durasi_menit' => $this->estimasi_durasi_menit,
 'gambar' => $this->gambar,
 'is_active' => $this->is_active,
 ]);
 } else {
 Layanan::create([
 'nama_layanan' => $this->nama_layanan,
 'deskripsi' => $this->deskripsi,
 'harga' => $this->harga,
 'estimasi_durasi_menit' => $this->estimasi_durasi_menit,
 'gambar' => $this->gambar,
 'is_active' => $this->is_active,
 ]);
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
 $query = Layanan::query()
 ->when($this->search, function($q) {
 $q->where('nama_layanan', 'like', '%'.$this->search.'%');
 })
 ->when($this->filterStatus !== 'all', function($q) {
 $q->where('is_active', $this->filterStatus === 'active');
 });
 
 return [
 'layanans' => $query->latest()->paginate(10)
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
 <td colspan="5" class="px-4 lg:px-5 py-5 lg:py-5 text-center text-on-surface-variant">Belum ada data layanan.</td>
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
 <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
 <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-white/10">
 <div class="px-4 lg:px-5 py-4 border-b border-white/10 flex justify-between items-center bg-surface-container-lowest">
 <h3 class="font-headline-md text-on-surface text-headline-md">{{ $isEdit ? 'Edit Layanan' : 'Tambah Layanan Baru' }}</h3>
 <button wire:click="$set('showModal', false)" class="text-on-surface-variant hover:text-error transition-colors">
 <span class="material-symbols-outlined">close</span>
 </button>
 </div>
 <form wire:submit="save" class="p-4 lg:p-5 space-y-4">
 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Nama Layanan</label>
 <input wire:model="nama_layanan" type="text" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
 @error('nama_layanan') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Path / URL Gambar (Opsional)</label>
 <input wire:model="gambar" type="text" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" placeholder="e.g. images/layanan/basic-haircut.jpg">
 @error('gambar') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>

 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Deskripsi / Rincian</label>
 <textarea wire:model="deskripsi" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" rows="3"></textarea>
 @error('deskripsi') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>

 <div class="flex gap-4">
 <div class="space-y-2 flex-1">
 <label class="block font-label-md text-label-md text-on-surface">Durasi (Menit)</label>
 <input wire:model="estimasi_durasi_menit" type="number" min="1" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
 @error('estimasi_durasi_menit') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 <div class="space-y-2 flex-1">
 <label class="block font-label-md text-label-md text-on-surface">Harga (Rp)</label>
 <input wire:model="harga" type="number" min="0" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
 @error('harga') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 </div>

 <div class="flex items-center gap-3 pt-2">
 <input wire:model="is_active" id="is_active" type="checkbox" class="w-5 h-5 rounded border-outline-variant bg-background text-primary focus:ring-primary accent-primary">
 <label for="is_active" class="font-label-md text-label-md text-on-surface cursor-pointer">Status Aktif</label>
 </div>
 
 <div class="pt-6 flex justify-end gap-3 border-t border-white/10 mt-6">
 <button type="button" wire:click="$set('showModal', false)" class="px-4 lg:px-5 py-2.5 rounded-full font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-highest transition-colors">Batal</button>
 <button type="submit" class="px-4 lg:px-5 py-2.5 rounded-full font-label-md text-label-md bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors shadow-lg shadow-primary/20">
 <span wire:loading.remove wire:target="save">Simpan Layanan</span>
 <span wire:loading wire:target="save">Menyimpan...</span>
 </button>
 </div>
 </form>
 </div>
 </div>
 @endif
</div>

