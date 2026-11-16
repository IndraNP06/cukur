<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\LogStok;
use App\Models\Barang;
use Carbon\Carbon;

new #[Layout('components.layouts.admin')] #[Title('Riwayat Mutasi Stok - Ran Cukurin')] class extends Component {
 use WithPagination;

 public $filterJenis = 'all';
 public $filterBarang = 'all';

 public $showModal = false;
 
 // Form fields for Stok Masuk/Keluar
 public $barang_id;
 public $jenis_mutasi = 'masuk';
 public $jumlah;
 public $keterangan = '';

 public function rules() {
        return [
 'barang_id' => 'required|exists:barang,id',
 'jenis_mutasi' => 'required|in:masuk,keluar,penyesuaian',
 'jumlah' => 'required|integer|min:1',
 'keterangan' => 'nullable|string',
 ];
    }

 public function updatedFilterJenis() {
 $this->resetPage();
 }

 public function updatedFilterBarang() {
 $this->resetPage();
 }

 public function create() {
 $this->resetValidation();
 $this->reset(['barang_id', 'jenis_mutasi', 'jumlah', 'keterangan']);
 $this->showModal = true;
 }

 public function save() {
 $this->validate();

 $barang = Barang::findOrFail($this->barang_id);

 if ($this->jenis_mutasi === 'masuk') {
 $barang->stok_sekarang += $this->jumlah;
 } elseif ($this->jenis_mutasi === 'keluar') {
 if ($barang->stok_sekarang < $this->jumlah) {
 $this->addError('jumlah', 'Stok tidak mencukupi. Sisa stok: ' . $barang->stok_sekarang);
 return;
 }
 $barang->stok_sekarang -= $this->jumlah;
 } elseif ($this->jenis_mutasi === 'penyesuaian') {
 // For penyesuaian, 'jumlah' in form means new total stock.
 // Let's assume penyesuaian sets the stock to exact amount.
 // Wait, for simplicity, 'jumlah' will just be the diff, or let's just stick to 'masuk' and 'keluar'.
 }

 $barang->save();

 LogStok::create([
 'barang_id' => $this->barang_id,
 'pencatat_id' => auth()->id(),
 'tipe_log' => $this->jenis_mutasi === 'masuk' ? 'BarangMasuk' : 'BarangKeluar',
 'qty_perubahan' => $this->jumlah,
 'stok_akhir' => $barang->stok_sekarang,
 'keterangan' => $this->keterangan ?: 'Mutasi manual',
 ]);

 $this->showModal = false;
 session()->flash('message', 'Mutasi stok berhasil dicatat.');
 }

 public function with() {
 $query = LogStok::with('barang')
 ->when($this->filterJenis !== 'all', function($q) {
 $q->where('tipe_log', $this->filterJenis === 'masuk' ? 'BarangMasuk' : 'BarangKeluar');
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

<div class="flex flex-col w-full h-full gap-5 lg:gap-5">
 @if(session()->has('message'))
 <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl flex items-center justify-between">
 <span>{{ session('message') }}</span>
 </div>
 @endif

 <div class="flex items-center justify-between mb-2 text-on-surface">
 <div>
 <h1 class="font-headline-xl text-on-background mb-2 text-headline-xl">Riwayat Mutasi Stok</h1>
 <p class="font-body-md text-body-md text-on-surface-variant uppercase tracking-widest">Buku Besar Inventaris</p>
 </div>
 <div class="flex items-center gap-4 bg-surface-container rounded-xl p-2 shadow-sm">
 <button wire:click="create" class="bg-primary text-on-primary px-4 lg:px-5 py-2 rounded-lg font-label-md text-label-md hover:bg-primary-fixed-dim transition-all shadow-md flex items-center gap-2">
 <span class="material-symbols-outlined text-[20px]">add</span>
 Catat Mutasi Manual
 </button>
 </div>
 </div>
 
 <div class="bg-surface-container rounded-2xl p-4 lg:p-5 shadow-lg relative overflow-hidden">
 <div class="absolute -top-24 -right-24 w-12 h-12 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-5 relative z-10">
 <div class="flex flex-col gap-2">
 <label class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Barang</label>
 <select wire:model.live="filterBarang" class="w-full bg-surface-container-highest text-on-surface font-body-md text-body-md rounded-xl py-3 px-4 outline-none cursor-pointer">
 <option value="all">Semua Barang</option>
 @foreach($barangs as $b)
 <option value="{{ $b->id }}">{{ $b->nama_barang }}</option>
 @endforeach
 </select>
 </div>
 <div class="flex flex-col gap-2">
 <label class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Jenis Aktivitas</label>
 <select wire:model.live="filterJenis" class="w-full bg-surface-container-highest text-on-surface font-body-md text-body-md rounded-xl py-3 px-4 outline-none cursor-pointer">
 <option value="all">Semua Aktivitas</option>
 <option value="masuk">Masuk (Restok)</option>
 <option value="keluar">Keluar (POS/Rusak)</option>
 </select>
 </div>
 </div>
 </div>
 
 <div class="bg-surface-container rounded-2xl shadow-xl overflow-hidden flex-1 flex flex-col">
 <div class="overflow-x-auto flex-1">
 <table class="w-full text-left border-collapse min-w-[1000px]">
 <thead>
 <tr class="bg-surface-container-highest text-on-surface-variant font-label-sm text-label-sm uppercase tracking-widest border-b-2 border-surface-container-lowest">
 <th class="px-4 lg:px-5 py-5 whitespace-nowrap">Tanggal &amp; Waktu</th>
 <th class="px-4 lg:px-5 py-5 whitespace-nowrap">Nama Barang</th>
 <th class="px-4 lg:px-5 py-5 whitespace-nowrap">Jenis Aktivitas</th>
 <th class="px-4 lg:px-5 py-5 text-right whitespace-nowrap">Perubahan</th>
 <th class="px-4 lg:px-5 py-5 whitespace-nowrap">Referensi / Pengguna</th>
 </tr>
 </thead>
 <tbody class="font-body-md text-on-surface">
 @forelse($logs as $log)
 <tr class="hover:bg-surface-container-highest/50 transition-colors border-b border-surface-container-highest/30">
 <td class="px-4 lg:px-5 py-5 whitespace-nowrap text-on-surface-variant">{{ $log->created_at->format('d/m/Y') }} <span class="ml-2 font-mono text-sm opacity-70">{{ $log->created_at->format('H:i') }}</span></td>
 <td class="px-4 lg:px-5 py-5 font-label-md">{{ $log->barang->nama_barang }}</td>
 <td class="px-4 lg:px-5 py-5">
 @if($log->tipe_log === 'BarangMasuk')
 <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-tertiary-container/20 text-tertiary-fixed-dim font-label-sm text-label-sm">
 <span class="w-1.5 h-1.5 rounded-full bg-tertiary-fixed-dim"></span> Masuk
 </span>
 @else
 <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-error-container/20 text-error font-label-sm text-label-sm">
 <span class="w-1.5 h-1.5 rounded-full bg-error"></span> Keluar
 </span>
 @endif
 </td>
 <td class="px-4 lg:px-5 py-5 text-right font-headline-md {{ $log->tipe_log === 'BarangMasuk' ? 'text-tertiary-fixed-dim' : 'text-error' }} text-headline-md">
 {{ $log->tipe_log === 'BarangMasuk' ? '+' : '-' }}{{ $log->qty_perubahan }}
 </td>
 <td class="px-4 lg:px-5 py-5">
 <div class="flex flex-col">
 <span class="text-sm font-mono text-on-surface-variant">{{ $log->pencatat->nama ?? 'Sistem' }}</span>
 <span class="text-xs text-on-surface-variant/70 uppercase">{{ $log->keterangan }}</span>
 </div>
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="5" class="px-4 lg:px-5 py-5 lg:py-5 text-center text-on-surface-variant">Belum ada riwayat mutasi stok.</td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>
 <div class="bg-surface-container-lowest p-4 flex flex-col md:flex-row items-center justify-between gap-4 mt-auto border-t border-surface-container">
 {{ $logs->links() }}
 </div>
 </div>

 <!-- Modal Form -->
 @if($showModal)
 <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
 <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-white/10 max-h-[90vh] overflow-y-auto">
 <div class="px-4 lg:px-5 py-4 border-b border-white/10 flex justify-between items-center bg-surface-container-lowest sticky top-0 z-10">
 <h3 class="font-headline-md text-on-surface text-headline-md">Pencatatan Mutasi Manual</h3>
 <button wire:click="$set('showModal', false)" class="text-on-surface-variant hover:text-error transition-colors">
 <span class="material-symbols-outlined">close</span>
 </button>
 </div>
 <form wire:submit="save" class="p-4 lg:p-5 space-y-4">
 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Pilih Barang</label>
 <select wire:model="barang_id" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors cursor-pointer" required>
 <option value="">-- Pilih Barang --</option>
 @foreach($barangs as $b)
 <option value="{{ $b->id }}">{{ $b->nama_barang }} (Sisa: {{ $b->stok_sekarang }})</option>
 @endforeach
 </select>
 @error('barang_id') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Jenis Mutasi</label>
 <select wire:model="jenis_mutasi" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors cursor-pointer" required>
 <option value="masuk">Barang Masuk (Restok)</option>
 <option value="keluar">Barang Keluar (Rusak/Terpakai)</option>
 </select>
 @error('jenis_mutasi') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>

 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Jumlah (Pcs/Satuan)</label>
 <input wire:model="jumlah" type="number" min="1" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
 @error('jumlah') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Keterangan / Alasan</label>
 <textarea wire:model="keterangan" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" rows="2" placeholder="Cth: Barang rusak, atau Pembelian baru"></textarea>
 @error('keterangan') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <div class="pt-6 flex justify-end gap-3 border-t border-white/10 mt-6">
 <button type="button" wire:click="$set('showModal', false)" class="px-4 lg:px-5 py-2.5 rounded-full font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-highest transition-colors">Batal</button>
 <button type="submit" class="px-4 lg:px-5 py-2.5 rounded-full font-label-md text-label-md bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors shadow-lg shadow-primary/20">
 <span wire:loading.remove wire:target="save">Simpan Mutasi</span>
 <span wire:loading wire:target="save">Menyimpan...</span>
 </button>
 </div>
 </form>
 </div>
 </div>
 @endif
</div>

