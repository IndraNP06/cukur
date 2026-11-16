<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use App\Models\Voucher;
use Carbon\Carbon;

new #[Layout('components.layouts.admin')] #[Title('Kelola Promosi & Voucher - Ran Cukurin')] class extends Component {
 public $kode_voucher = '';
 public $tipe_diskon = 'Persen';
 public $nilai_diskon = '';
 public $kuota_penggunaan = null;
 public $tanggal_mulai = '';
 public $tanggal_berakhir = '';

 public function rules() {
     return [
         'kode_voucher' => 'required|string|max:50|unique:voucher,kode_voucher',
         'tipe_diskon' => 'required|in:Persen,Nominal',
         'nilai_diskon' => 'required|numeric|min:1',
         'kuota_penggunaan' => 'nullable|integer|min:1',
         'tanggal_mulai' => 'nullable|date',
         'tanggal_berakhir' => 'nullable|date|after_or_equal:tanggal_mulai',
     ];
 }

 public function mount() {
 $this->tanggal_mulai = Carbon::now()->format('Y-m-d');
 $this->tanggal_berakhir = Carbon::now()->addDays(30)->format('Y-m-d');
 }

 public function createVoucher() {
 $this->validate();

 Voucher::create([
 'kode_voucher' => strtoupper($this->kode_voucher),
 'tipe_diskon' => $this->tipe_diskon,
 'nilai_diskon' => $this->nilai_diskon,
 'kuota_maksimal' => $this->kuota_penggunaan ?: null,
 'tgl_mulai' => $this->tanggal_mulai ?: null,
 'tgl_berakhir' => $this->tanggal_berakhir ?: null,
 'is_active' => true,
 ]);

 $this->reset(['kode_voucher', 'nilai_diskon', 'kuota_penggunaan']);
 session()->flash('message', 'Voucher berhasil diaktifkan.');
 }

 public function toggleStatus($id) {
 $v = Voucher::findOrFail($id);
 $v->is_active = !$v->is_active;
 $v->save();
 }

 public function with() {
 return [
 'vouchers' => Voucher::latest()->get()
 ];
 }
};
?>

<div class="flex flex-col w-full h-full relative space-y-8 animate-[fadeIn_0.5s_ease-out]">
 @if(session()->has('message'))
 <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl flex items-center justify-between">
 <span>{{ session('message') }}</span>
 </div>
 @endif

 <!-- Header Section -->
 <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 lg:gap-5 mb-8">
 <div>
 <h1 class="font-headline-xl text-on-background mb-1 text-headline-xl">Promosi &amp; Voucher</h1>
 <p class="font-body-md text-body-md text-on-surface-variant max-w-xl">Kelola kampanye diskon, atur kuota, dan pantau efektivitas voucher untuk meningkatkan retensi pelanggan.</p>
 </div>
 </div>

 <!-- Summary Widgets -->
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-5 w-full">
 <!-- Widget 1 -->
 <div class="bg-surface-container hover:bg-surface-container-high transition-colors rounded-3xl p-4 lg:p-5 relative overflow-hidden group shadow-md flex items-center justify-between">
 <div class="absolute inset-0 bg-gradient-to-br from-primary/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
 <div class="relative z-10 flex flex-col gap-1">
 <div class="flex items-center gap-2 text-on-surface-variant font-label-md text-label-md uppercase tracking-wider mb-2">
 <span class="material-symbols-outlined text-[18px] text-primary">local_offer</span>
 Voucher Aktif
 </div>
 <div class="font-headline-xl text-[56px] leading-none font-bold text-on-surface text-headline-xl">{{ $vouchers->where('is_active', true)->count() }}</div>
 </div>
 <svg class="w-12 h-14 relative z-10 text-primary/20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 100 40">
 <path d="M0,35 Q10,25 20,30 T40,20 T60,25 T80,10 T100,5" fill="none" stroke-linecap="round"></path>
 </svg>
 </div>
 <!-- Widget 2 -->
 <div class="bg-surface-container hover:bg-surface-container-high transition-colors rounded-3xl p-4 lg:p-5 relative overflow-hidden group shadow-md flex items-center justify-between">
 <div class="absolute inset-0 bg-gradient-to-br from-secondary/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
 <div class="relative z-10 flex flex-col gap-1">
 <div class="flex items-center gap-2 text-on-surface-variant font-label-md text-label-md uppercase tracking-wider mb-2">
 <span class="material-symbols-outlined text-[18px] text-secondary">confirmation_number</span>
 Total Voucher Terdaftar
 </div>
 <div class="font-headline-xl text-[56px] leading-none font-bold text-on-surface text-headline-xl">{{ $vouchers->count() }}</div>
 </div>
 <svg class="w-12 h-12 relative z-10 text-secondary/20" viewBox="0 0 36 36">
 <path class="text-surface-container-highest" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-dasharray="100, 100" stroke-width="4"></path>
 </svg>
 </div>
 </div>

 <!-- Main Content Area: Split View -->
 <div class="flex flex-col lg:flex-row gap-5 lg:gap-5 w-full">
 <!-- Voucher Creation Form (Left Side - Wider) -->
 <div class="lg:w-7/12 w-full flex flex-col gap-4 lg:gap-5">
 <div class="bg-surface-container-low backdrop-blur-sm shadow-xl rounded-3xl p-5 lg:p-5 relative">
 <div class="absolute top-0 right-0 w-12 h-12 bg-primary/5 rounded-full blur-[80px] -mr-32 -mt-32 pointer-events-none"></div>
 <div class="flex items-center justify-between mb-8">
 <h2 class="font-headline-md text-on-surface flex items-center gap-3 text-headline-md">
 <span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">tune</span>
 Parameter Voucher Baru
 </h2>
 </div>
 
 <form wire:submit="createVoucher" class="space-y-6 relative z-10">
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-5">
 <!-- Kode Voucher -->
 <div class="space-y-2">
 <label class="font-label-sm text-label-sm text-on-surface-variant">Kode Voucher</label>
 <div class="relative group">
 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors">tag</span>
 <input wire:model="kode_voucher" class="w-full bg-surface-container-highest text-on-surface font-label-md text-label-md tracking-[0.2em] uppercase rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all placeholder:text-on-surface-variant/50" placeholder="MISAL: GANTENG24" type="text" required>
 </div>
 @error('kode_voucher') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <!-- Tipe Diskon -->
 <div class="space-y-2">
 <label class="font-label-sm text-label-sm text-on-surface-variant">Tipe Diskon</label>
 <div class="relative group">
 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors">percent</span>
 <select wire:model="tipe_diskon" class="w-full bg-surface-container-highest text-on-surface font-body-md text-body-md rounded-xl py-3 pl-12 pr-10 appearance-none focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all cursor-pointer">
 <option value="Persen">Persentase (%)</option>
 <option value="Nominal">Nominal (Rp)</option>
 </select>
 <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">expand_more</span>
 </div>
 </div>
 </div>
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-5">
 <!-- Nilai -->
 <div class="space-y-2">
 <label class="font-label-sm text-label-sm text-on-surface-variant">Nilai Potongan</label>
 <div class="relative group">
 <input wire:model="nilai_diskon" class="w-full bg-surface-container-highest text-on-surface font-headline-md rounded-xl py-3 px-4 pr-16 focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all placeholder:text-on-surface-variant/50 text-headline-md" placeholder="Contoh: 15" type="number" required>
 <div class="absolute right-4 top-1/2 -translate-y-1/2 font-label-sm text-label-sm text-on-surface-variant bg-surface-container px-2 py-1 rounded">IDR / %</div>
 </div>
 @error('nilai_diskon') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <!-- Kuota -->
 <div class="space-y-2">
 <label class="font-label-sm text-label-sm text-on-surface-variant">Batas Penggunaan (Kuota)</label>
 <div class="relative group">
 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors">group</span>
 <input wire:model="kuota_penggunaan" class="w-full bg-surface-container-highest text-on-surface font-body-md text-body-md rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all placeholder:text-on-surface-variant/50" placeholder="Tak Terbatas biarkan kosong" type="number">
 </div>
 @error('kuota_penggunaan') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 </div>

 <!-- Date Range -->
 <div class="p-5 bg-surface-container-highest rounded-2xl">
 <label class="font-label-sm text-label-sm text-on-surface-variant mb-4 block">Periode Validitas</label>
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center relative">
 <div class="relative">
 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">calendar_today</span>
 <input wire:model="tanggal_mulai" class="w-full bg-surface text-on-surface font-body-md text-body-md rounded-lg py-2.5 pl-11 pr-3 focus:outline-none focus:ring-2 focus:ring-primary/30 [color-scheme:dark]" type="date">
 </div>
 <div class="hidden md:flex absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-surface-container rounded-full items-center justify-center shadow-sm">
 <span class="material-symbols-outlined text-on-surface-variant text-[14px]">arrow_forward</span>
 </div>
 <div class="relative">
 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">event</span>
 <input wire:model="tanggal_berakhir" class="w-full bg-surface text-on-surface font-body-md text-body-md rounded-lg py-2.5 pl-11 pr-3 focus:outline-none focus:ring-2 focus:ring-primary/30 [color-scheme:dark]" type="date">
 </div>
 </div>
 </div>
 
 <div class="pt-4 flex justify-end gap-3 border-t border-surface-container-highest">
 <button type="submit" class="px-4 lg:px-5 py-2.5 font-label-md text-label-md bg-primary text-on-primary hover:bg-primary-container rounded-full transition-all shadow-md shadow-primary/20">
 <span wire:loading.remove wire:target="createVoucher">Aktifkan Voucher</span>
 <span wire:loading wire:target="createVoucher">Menyimpan...</span>
 </button>
 </div>
 </form>
 </div>
 </div>

 <!-- Database Table (Right Side - Narrower) -->
 <div class="lg:w-5/12 w-full flex flex-col h-full min-h-[600px]">
 <div class="bg-surface-container shadow-lg rounded-3xl overflow-hidden flex flex-col flex-1 relative">
 <div class="p-4 lg:p-5 border-b border-surface-container-highest flex items-center justify-between bg-surface-container-low/50 backdrop-blur-md relative z-20">
 <h3 class="font-headline-md text-on-surface text-headline-md">Daftar Voucher</h3>
 </div>
 
 <div class="flex-1 overflow-y-auto overflow-x-hidden relative p-2 space-y-1">
 @forelse($vouchers as $voucher)
 <div class="group bg-transparent hover:bg-surface-container-highest p-4 rounded-2xl transition-all flex items-center justify-between border border-transparent hover:border-surface-variant/30 {{ !$voucher->is_active ? 'opacity-60' : '' }}">
 <div class="flex flex-col gap-2">
 <div class="flex items-center gap-3">
 <span class="font-mono text-label-md tracking-widest {{ $voucher->is_active ? 'bg-primary/10 text-primary' : 'bg-surface-variant text-on-surface-variant' }} px-3 py-1 rounded-md">{{ $voucher->kode_voucher }}</span>
 @if($voucher->is_active)
 <span class="flex items-center gap-1 bg-[#064E3B] text-[#34D399] px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">
 <span class="w-1.5 h-1.5 rounded-full bg-[#34D399] animate-pulse"></span>
 Aktif
 </span>
 @else
 <span class="flex items-center gap-1 bg-surface-variant text-on-surface-variant px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">
 Habis/Nonaktif
 </span>
 @endif
 </div>
 <div class="font-body-md text-body-md text-on-surface-variant flex items-center gap-4">
 <span>{{ $voucher->tipe_diskon == 'Persen' ? 'Diskon '.$voucher->nilai_diskon.'%' : 'Potong Rp '.number_format($voucher->nilai_diskon,0,',','.') }}</span>
 <span class="text-surface-variant text-[10px]">•</span>
 <span class="flex items-center gap-1">
 <span class="material-symbols-outlined text-[14px]">group</span>
 {{ $voucher->kuota_maksimal ?? 'Unlimited' }}
 </span>
 </div>
 </div>
 <div class="flex flex-col items-end gap-1">
 <button wire:click="toggleStatus({{ $voucher->id }})" class="p-2 text-on-surface-variant hover:text-primary transition-colors" title="Toggle Status">
 <span class="material-symbols-outlined text-[20px]">{{ $voucher->is_active ? 'block' : 'check_circle' }}</span>
 </button>
 </div>
 </div>
 <div class="h-px bg-gradient-to-r from-transparent via-surface-container-highest to-transparent mx-4"></div>
 @empty
 <div class="p-4 lg:p-5 text-center text-on-surface-variant">Belum ada voucher terdaftar.</div>
 @endforelse
 </div>
 </div>
 </div>
 </div>
</div>
