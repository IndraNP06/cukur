<?php

use App\Models\AntreanPemesanan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Riwayat Transaksi - Ran CukurIn')] class extends Component {
 use WithPagination;
 
 public $filter = 'Semua';

 public function mount()
 {
     try {
         if (auth()->check() && !cache()->has('sheets_sync_ulasan_customer_' . auth()->id())) {
             app(\App\Services\GoogleSheetsService::class)->pullTable('ulasan');
             cache()->put('sheets_sync_ulasan_customer_' . auth()->id(), true, now()->addSeconds(15));
         }
     } catch (\Throwable $e) {}
 }

 public function setFilter($filter)
 {
 $this->filter = $filter;
 $this->resetPage();
 }

 public function with()
 {
 $query = AntreanPemesanan::with(['layanan', 'kapster', 'detailTransaksi', 'ulasan'])
 ->where('pelanggan_id', auth()->id())
 ->orderBy('tanggal', 'desc')
 ->orderBy('created_at', 'desc');

 if ($this->filter !== 'Semua') {
 $query->where('status', $this->filter);
 }

 $totalKunjungan = AntreanPemesanan::where('pelanggan_id', auth()->id())
 ->where('status', 'Selesai')
 ->count();

 return [
 'transaksi' => $query->paginate(5),
 'totalKunjungan' => $totalKunjungan
 ];
 }
};
?>

 <div class="flex-grow flex flex-col bg-background font-body-md text-on-background w-full">
 <div class="flex flex-col w-full max-w-5xl mx-auto px-4 lg:px-8 py-6 lg:py-10">
 <div class="w-full">
 <!-- Page Header & Summary -->
 <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 lg:gap-5 mb-8 lg:mb-10 relative z-10">
 <div>
 <div class="flex items-center gap-3 mb-2">
 <span class="material-symbols-outlined text-primary text-lg">history</span>
 <h1 class="font-headline-xl text-on-background tracking-tighter text-headline-xl">Riwayat Transaksi</h1>
 </div>
 <p class="font-body-lg text-body-lg text-on-surface-variant max-w-lg">
 Lacak setiap detail pengalaman grooming premium Anda. Kelola reservasi mendatang dan beri ulasan pada kunjungan sebelumnya.
 </p>
 </div>
 
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl flex items-center gap-4 lg:gap-5 shadow-xl relative overflow-hidden group">
 <div class="absolute inset-0 bg-gradient-to-r from-primary/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
 <div class="relative z-10">
 <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-1">Total Kunjungan</p>
 <p class="font-headline-lg text-on-surface text-headline-lg">{{ $totalKunjungan }} <span class="text-primary text-xl">x</span></p>
 </div>
 <div class="w-px h-12 bg-surface-variant"></div>
 <div class="relative z-10">
 <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-1">Status Member</p>
 <div class="flex items-center gap-2">
 <span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">workspace_premium</span>
 <p class="font-headline-md text-primary text-headline-md">Reguler</p>
 </div>
 </div>
 </div>
 </div>
 
 @if(session()->has('message'))
 <div class="mb-6 p-4 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 flex items-center gap-3">
     <span class="material-symbols-outlined text-xl">check_circle</span>
     <span class="font-body-md text-sm font-medium">{{ session('message') }}</span>
 </div>
 @endif

 <!-- Filter Tabs -->
 <div class="flex overflow-x-auto gap-3 pb-4 mb-8 -mx-margin-mobile px-margin-mobile lg:mx-0 lg:px-0 scrollbar-hide">
 @foreach(['Semua', 'Menunggu', 'Diproses', 'Selesai', 'Batal'] as $f)
 <button wire:click="setFilter('{{ $f }}')" class="whitespace-nowrap px-4 lg:px-5 py-3 rounded-full font-label-md text-label-md transition-colors {{ $filter === $f ? 'bg-primary text-on-primary shadow-lg shadow-primary/20 hover:scale-105' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' }}">
 {{ $f }}
 </button>
 @endforeach
 </div>
 
 <!-- Transaction List Stack -->
 <div class="flex flex-col gap-4 lg:gap-5 relative z-10">
 @forelse($transaksi as $item)
 @if($item->status === 'Selesai')
 <!-- Card: Selesai -->
 <div class="bg-surface-container rounded-2xl p-4 lg:p-5 md:p-5 lg:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 lg:gap-5 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 relative overflow-hidden group">
 <div class="absolute -right-20 -top-20 w-40 h-40 bg-emerald-500/5 rounded-full blur-3xl group-hover:bg-emerald-500/10 transition-colors"></div>
 <div class="flex flex-col md:flex-row gap-4 lg:gap-5 items-start md:items-center relative z-10 w-full">
 <div class="flex-shrink-0 md:w-12 flex flex-col">
 <p class="font-headline-md text-on-surface mb-1 text-headline-md">{{ \Carbon\Carbon::parse($item->tanggal)->translatedFormat('d M') }}</p>
 <p class="font-body-md text-body-md text-on-surface-variant">{{ \Carbon\Carbon::parse($item->tanggal)->format('Y') }}</p>
 <div class="mt-4 px-3 py-1 bg-surface-variant/50 rounded-md inline-block w-fit">
 <p class="font-label-sm text-label-sm text-on-surface-variant tracking-wider">#RNC-{{ str_pad($item->id, 6, '0', STR_PAD_LEFT) }}</p>
 </div>
 </div>
 <div class="flex-grow flex flex-col justify-center">
 <div class="flex items-center gap-3 mb-2">
 <h3 class="font-headline-lg-mobile text-on-surface text-headline-lg-mobile">{{ $item->layanan->nama_layanan ?? 'Custom Layanan' }}</h3>
 <div class="px-3 py-1 rounded-full bg-emerald-500/10 flex items-center gap-1.5">
 <span class="material-symbols-outlined text-[14px] text-emerald-400" style="font-variation-settings: 'FILL' 1;">check_circle</span>
 <span class="font-label-sm text-label-sm text-emerald-400">Selesai</span>
 </div>
 </div>
 <div class="flex flex-wrap items-center gap-4 mt-2">
 <div class="flex items-center gap-2">
 <div class="w-8 h-8 rounded-full bg-surface-container-high flex items-center justify-center overflow-hidden">
 <span class="material-symbols-outlined text-on-surface-variant text-[16px]">content_cut</span>
 </div>
 <div>
 <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider text-[10px]">Barber</p>
 <p class="font-body-md text-body-md text-on-surface">{{ $item->kapster->nama ?? 'Bebas' }}</p>
 </div>
 </div>
 <div class="w-px h-8 bg-surface-variant hidden md:block"></div>
 <div>
 <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider text-[10px]">Total</p>
 <p class="font-label-md text-label-md text-on-surface">Rp {{ number_format($item->detailTransaksi?->total_akhir ?? 0, 0, ',', '.') }}</p>
 </div>
 </div>
 </div>
 </div>
 <div class="flex-shrink-0 flex flex-row md:flex-col items-center md:items-end justify-between w-full md:w-auto mt-4 md:mt-0 relative z-10 border-t border-surface-variant/50 pt-4 md:border-t-0 md:pt-0">
 @if($item->ulasan)
 <div class="flex flex-col items-start md:items-end gap-1.5 w-full md:w-auto">
     <div class="flex items-center gap-1.5 bg-amber-500/10 border border-amber-500/20 px-3 py-1.5 rounded-xl">
         <div class="flex items-center text-amber-400">
             @for($i = 1; $i <= 5; $i++)
                 <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' {{ $item->ulasan->rating >= $i ? 1 : 0 }};">star</span>
             @endfor
         </div>
         <span class="font-label-sm text-xs font-bold text-amber-400">{{ $item->ulasan->rating }}.0</span>
     </div>
     @if($item->ulasan->komentar)
         <p class="font-body-sm text-xs text-on-surface-variant italic max-w-xs text-left md:text-right line-clamp-2">"{{ $item->ulasan->komentar }}"</p>
     @endif
     <a href="{{ route('customer.ulasan', $item->id) }}" class="text-[11px] font-semibold text-primary hover:underline flex items-center gap-1 mt-0.5">
         <span class="material-symbols-outlined text-[13px]">edit</span>
         Ubah Ulasan
     </a>
 </div>
 @else
 <a href="{{ route('customer.ulasan', $item->id) }}" class="px-4 lg:px-5 py-2.5 rounded-lg font-label-md text-label-md text-primary bg-primary/10 hover:bg-primary hover:text-on-primary transition-colors flex items-center gap-2">
 <span class="material-symbols-outlined text-[18px]">star_rate</span>
 Beri Ulasan
 </a>
 @endif
 </div>
 </div>
 @elseif($item->status === 'Menunggu' || $item->status === 'Diproses')
 <!-- Card: Menunggu / Diproses -->
 <div class="bg-surface-container-high rounded-2xl p-4 lg:p-5 md:p-5 lg:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 lg:gap-5 shadow-2xl relative overflow-hidden ring-1 ring-primary/20 transform hover:-translate-y-1 transition-all duration-300">
 <div class="absolute inset-0 bg-primary/5 animate-pulse rounded-2xl pointer-events-none"></div>
 <div class="absolute -left-20 -top-20 w-40 h-40 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
 <div class="flex flex-col md:flex-row gap-4 lg:gap-5 items-start md:items-center relative z-10 w-full">
 <div class="flex-shrink-0 md:w-12 flex flex-col">
 <p class="font-headline-md text-on-surface mb-1 text-headline-md">{{ \Carbon\Carbon::parse($item->tanggal)->translatedFormat('d M') }}</p>
 <p class="font-body-md text-body-md text-primary">{{ \Carbon\Carbon::parse($item->tanggal)->isToday() ? 'Hari Ini' : \Carbon\Carbon::parse($item->tanggal)->format('Y') }}</p>
 <div class="mt-4 px-3 py-1 bg-surface-variant/50 rounded-md inline-block w-fit">
 <p class="font-label-sm text-label-sm text-on-surface-variant tracking-wider">#RNC-{{ str_pad($item->id, 6, '0', STR_PAD_LEFT) }}</p>
 </div>
 </div>
 <div class="flex-grow flex flex-col justify-center">
 <div class="flex items-center gap-3 mb-2">
 <h3 class="font-headline-lg-mobile text-on-surface text-headline-lg-mobile">{{ $item->layanan->nama_layanan ?? 'Custom Layanan' }}</h3>
 <div class="px-3 py-1 rounded-full bg-blue-500/10 flex items-center gap-1.5 animate-pulse">
 <span class="material-symbols-outlined text-[14px] text-blue-400">schedule</span>
 <span class="font-label-sm text-label-sm text-blue-400">{{ $item->status }}</span>
 </div>
 </div>
 <div class="flex flex-wrap items-center gap-4 mt-2">
 <div class="flex items-center gap-2">
 <div class="w-8 h-8 rounded-full bg-surface-container flex items-center justify-center overflow-hidden">
 <span class="material-symbols-outlined text-on-surface-variant text-[16px]">face</span>
 </div>
 <div>
 <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider text-[10px]">Barber</p>
 <p class="font-body-md text-body-md text-on-surface">{{ $item->kapster->nama ?? 'Bebas' }}</p>
 </div>
 </div>
 <div class="w-px h-8 bg-surface-variant hidden md:block"></div>
 <div class="flex items-center gap-2">
 <div class="w-8 h-8 rounded-full bg-surface-container flex items-center justify-center overflow-hidden">
 <span class="material-symbols-outlined text-primary text-[16px]">pin_drop</span>
 </div>
 <div>
 <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider text-[10px]">Waktu</p>
 <p class="font-body-md text-body-md text-on-surface">{{ \Carbon\Carbon::parse($item->waktu_estimasi_mulai)->format('H:i') }}</p>
 </div>
 </div>
 </div>
 </div>
 </div>
 <div class="flex-shrink-0 flex flex-col items-end gap-3 w-full md:w-auto mt-4 md:mt-0 relative z-10 border-t border-surface-variant/50 pt-4 md:border-t-0 md:pt-0">
 <a href="{{ route('customer.tiket', $item->id) }}" class="w-full md:w-auto px-4 lg:px-5 py-3 rounded-lg font-label-md text-label-md bg-primary text-on-primary shadow-lg shadow-primary/30 hover:shadow-primary/50 hover:bg-primary-fixed transition-all flex items-center justify-center gap-2 group">
 <span class="material-symbols-outlined text-[18px] group-hover:animate-spin">radar</span>
 Lacak Antrean Live
 </a>
 </div>
 </div>
 @elseif($item->status === 'Batal')
 <!-- Card: Batal -->
 <div class="bg-surface-container/50 rounded-2xl p-4 lg:p-5 md:p-5 lg:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 lg:gap-5 opacity-75 grayscale-[20%] hover:grayscale-0 hover:opacity-100 transition-all duration-300">
 <div class="flex flex-col md:flex-row gap-4 lg:gap-5 items-start md:items-center w-full">
 <div class="flex-shrink-0 md:w-12 flex flex-col">
 <p class="font-headline-md text-on-surface-variant mb-1 text-headline-md">{{ \Carbon\Carbon::parse($item->tanggal)->translatedFormat('d M') }}</p>
 <p class="font-body-md text-body-md text-on-surface-variant/70">{{ \Carbon\Carbon::parse($item->tanggal)->format('Y') }}</p>
 <div class="mt-4 px-3 py-1 bg-surface-container-lowest rounded-md inline-block w-fit">
 <p class="font-label-sm text-label-sm text-on-surface-variant/50 tracking-wider">#RNC-{{ str_pad($item->id, 6, '0', STR_PAD_LEFT) }}</p>
 </div>
 </div>
 <div class="flex-grow flex flex-col justify-center">
 <div class="flex items-center gap-3 mb-2">
 <h3 class="font-headline-lg-mobile text-on-surface-variant line-through decoration-on-surface-variant/30 text-headline-lg-mobile">{{ $item->layanan->nama_layanan ?? 'Custom Layanan' }}</h3>
 <div class="px-3 py-1 rounded-full bg-surface-container-lowest flex items-center gap-1.5">
 <span class="material-symbols-outlined text-[14px] text-on-surface-variant">cancel</span>
 <span class="font-label-sm text-label-sm text-on-surface-variant">Batal</span>
 </div>
 </div>
 </div>
 </div>
 <div class="flex-shrink-0 flex items-center justify-end w-full md:w-auto mt-4 md:mt-0 border-t border-surface-variant/30 pt-4 md:border-t-0 md:pt-0">
 <a href="{{ route('booking.wizard') }}" class="px-4 lg:px-5 py-2.5 rounded-lg font-label-md text-label-md bg-surface-variant text-on-surface hover:bg-surface-container-high transition-colors flex items-center gap-2">
 <span class="material-symbols-outlined text-[18px]">replay</span>
 Pesan Ulang
 </a>
 </div>
 </div>
 @endif
 @empty
 <div class="text-center py-12">
 <span class="material-symbols-outlined text-lg text-surface-variant mb-4">inbox</span>
 <p class="text-on-surface-variant">Tidak ada transaksi ditemukan.</p>
 </div>
 @endforelse
 </div>
 
 <!-- Pagination -->
 <div class="mt-12 pt-8 border-t border-surface-variant/50">
 {{ $transaksi->links() }}
 </div>
 </div>
 </div>
</div>
