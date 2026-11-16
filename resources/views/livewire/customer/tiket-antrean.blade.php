<?php

use App\Models\AntreanPemesanan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.blank')] #[Title('Tiket Antrean - Ran CukurIn')] class extends Component {
 public $pemesanan;
 public $antreanSaatIni;
 public $menungguCount;
 public $ewt;

 public function mount($id)
 {
 $this->pemesanan = AntreanPemesanan::with(['pelanggan', 'layanan', 'kapster', 'detailTransaksi'])->findOrFail($id);
 
 // Ensure only the owner can view
 if (auth()->check() && auth()->user()->role === 'Pelanggan' && $this->pemesanan->pelanggan_id !== auth()->id()) {
 abort(403);
 }
 
 $this->loadData();
 }
 
     public function loadData()
    {
        $this->antreanSaatIni = AntreanPemesanan::where('status', 'Diproses')
            ->whereDate('tanggal', $this->pemesanan->tanggal)
            ->orderBy('waktu_mulai_aktual', 'desc')
            ->first();

        if (!$this->antreanSaatIni) {
            $this->antreanSaatIni = AntreanPemesanan::where('status', 'Diproses')
                ->orderBy('id', 'desc')
                ->first();
        }

        $this->menungguCount = $this->pemesanan->sisa_antrean_di_depan;
        $this->ewt = $this->pemesanan->estimasi_tunggu_menit;
    }

 public function refreshStatus()
 {
 $this->pemesanan->refresh();
 $this->loadData();
 }

 public function cancelReservation()
 {
 if ($this->pemesanan->status === 'Menunggu') {
 $this->pemesanan->update(['status' => 'Batal']);
 session()->flash('message', 'Reservasi berhasil dibatalkan.');
 $this->redirect(route('customer.riwayat'), navigate: true);
 }
 }
};
?>

<div class="bg-surface text-on-surface font-body-md flex items-center justify-center flex-col flex-grow" wire:poll.5s="refreshStatus">
 <div class="w-full max-w-md">
 <div class="flex flex-col w-full flex-grow bg-surface text-on-surface">
 <header class="sticky top-0 z-50 flex items-center justify-between p-margin-mobile bg-surface/80 backdrop-blur-md">
 <a href="{{ url('/') }}" aria-label="Kembali" class="flex items-center justify-center w-10 h-10 rounded-full bg-surface-container hover:bg-surface-container-high transition-colors text-on-surface">
 <span class="material-symbols-outlined text-[20px]">arrow_back</span>
 </a>
 <h1 class="font-headline-md text-headline-md">Detail Reservasi</h1>
 <div class="w-10 h-10"></div>
 </header>
 
 <div class="flex-1 px-margin-mobile py-5 lg:py-5 flex flex-col gap-4 lg:gap-5">
 @if (session()->has('message'))
 <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
 {{ session('message') }}
 </div>
 @endif
 
 <div class="relative bg-surface-container-high rounded-2xl p-4 lg:p-5 shadow-xl overflow-hidden group hover:scale-[1.01] transition-transform duration-300">
 <div class="absolute -top-10 -right-10 w-12 h-12 bg-primary/10 rounded-full blur-2xl group-hover:bg-primary/20 transition-colors"></div>
 
 <div class="flex items-center justify-between mb-4 relative z-10">
 <div>
 @if($pemesanan->status === 'Selesai')
 <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-green-500/20 text-green-500 font-label-sm text-label-sm uppercase tracking-wider">
 <span class="material-symbols-outlined text-[14px]">check_circle</span> Selesai
 </span>
 @elseif($pemesanan->status === 'Batal')
 <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-error/20 text-error font-label-sm text-label-sm uppercase tracking-wider">
 <span class="material-symbols-outlined text-[14px]">cancel</span> Dibatalkan
 </span>
 @else
 <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-primary/10 text-primary font-label-sm text-label-sm uppercase tracking-wider">
 <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span> Real-time
 </span>
 @endif
 </div>
 <button wire:click="refreshStatus" class="text-on-surface-variant hover:text-on-surface transition-colors">
 <span class="material-symbols-outlined text-[20px]">refresh</span>
 </button>
 </div>
 
 <div class="flex flex-col items-center justify-center py-4 lg:py-5 text-center border-b border-surface-container relative z-10">
 @if($pemesanan->status === 'Menunggu' || $pemesanan->status === 'Diproses')
 <p class="font-body-md text-body-md text-on-surface-variant mb-2">Sedang Diproses</p>
 <div class="flex items-baseline gap-2 mb-4">
 <span class="font-headline-md text-on-surface-variant text-headline-md">No.</span>
 <span class="font-headline-xl text-[64px] leading-none font-bold text-on-surface text-headline-xl">
 {{ $antreanSaatIni ? $antreanSaatIni->nomor_antrean : '-' }}
 </span>
 </div>
 <div class="w-full h-[1px] bg-gradient-to-r from-transparent via-surface-container-highest to-transparent my-4"></div>
 @endif
 
 <p class="font-body-md text-body-md text-on-surface-variant mb-1">Nomor Anda</p>
 <div class="flex items-baseline gap-2">
 <span class="font-headline-md text-primary text-headline-md">No.</span>
 <span class="font-headline-xl text-[48px] leading-none font-bold text-primary text-headline-xl">{{ $pemesanan->nomor_antrean }}</span>
 </div>
 </div>
 
 @if($pemesanan->status === 'Menunggu')
 <div class="py-4 relative z-10">
 <div class="flex items-center justify-between p-3 rounded-lg bg-surface-container-lowest">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant">
 <span class="material-symbols-outlined">schedule</span>
 </div>
 <div>
 <p class="font-label-sm text-label-sm text-on-surface-variant">Estimasi Waktu Tunggu (EWT)</p>
 <p class="font-label-md text-label-md text-on-surface">~{{ $ewt }} Menit</p>
 </div>
 </div>
 </div>
 </div>
 @endif
 
 <div class="relative pt-6 z-10">
 <div class="flex items-center justify-between relative">
 <div class="absolute left-[10%] right-[10%] top-[10px] h-0.5 bg-surface-container-lowest -z-10">
 @php
 $progress = 10;
 if ($pemesanan->status === 'Diproses') $progress = 50;
 if ($pemesanan->status === 'Selesai') $progress = 100;
 @endphp
 <div class="h-full bg-primary transition-all duration-700 ease-in-out" style="width: {{ $progress }}%"></div>
 </div>
 
 <div class="flex flex-col items-center gap-2">
 <div class="w-6 h-6 rounded-full bg-primary ring-4 ring-surface-container-high flex items-center justify-center">
 <div class="w-2 h-2 rounded-full bg-on-primary"></div>
 </div>
 <span class="font-label-sm text-label-sm text-primary">Menunggu</span>
 </div>
 
 <div class="flex flex-col items-center gap-2 {{ $pemesanan->status === 'Menunggu' ? 'opacity-50' : '' }}">
 <div class="w-6 h-6 rounded-full {{ $pemesanan->status !== 'Menunggu' ? 'bg-primary' : 'bg-surface-container-lowest' }} ring-4 ring-surface-container-high flex items-center justify-center">
 @if($pemesanan->status !== 'Menunggu')
 <div class="w-2 h-2 rounded-full bg-on-primary"></div>
 @endif
 </div>
 <span class="font-label-sm text-label-sm {{ $pemesanan->status !== 'Menunggu' ? 'text-primary' : 'text-on-surface-variant' }}">Diproses</span>
 </div>
 
 <div class="flex flex-col items-center gap-2 {{ $pemesanan->status !== 'Selesai' ? 'opacity-50' : '' }}">
 <div class="w-6 h-6 rounded-full {{ $pemesanan->status === 'Selesai' ? 'bg-primary' : 'bg-surface-container-lowest' }} ring-4 ring-surface-container-high flex items-center justify-center">
 @if($pemesanan->status === 'Selesai')
 <div class="w-2 h-2 rounded-full bg-on-primary"></div>
 @endif
 </div>
 <span class="font-label-sm text-label-sm {{ $pemesanan->status === 'Selesai' ? 'text-primary' : 'text-on-surface-variant' }}">Selesai</span>
 </div>
 </div>
 </div>
 </div>
 
 <div class="relative w-full drop-shadow-xl my-4">
 <div class="bg-surface-container rounded-t-xl p-4 lg:p-5 relative overflow-hidden">
 <div class="absolute top-0 right-0 p-4 opacity-5 pointer-events-none">
 <span class="material-symbols-outlined text-[120px]">content_cut</span>
 </div>
 <h2 class="font-headline-md text-center mb-6 tracking-tight text-headline-md">Tiket Antrean</h2>
 
 <div class="space-y-4">
 <div class="flex justify-between items-end">
 <div>
 <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Layanan</p>
 <p class="font-body-md text-body-md font-medium text-on-surface">{{ $pemesanan->layanan->nama_layanan ?? 'N/A' }}</p>
 </div>
 <div class="text-right">
 <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Barber</p>
 <p class="font-body-md text-body-md font-medium text-on-surface">{{ $pemesanan->kapster->nama ?? 'Bebas' }}</p>
 </div>
 </div>
 
 <div class="flex justify-between items-end">
 <div>
 <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Tanggal</p>
 <p class="font-body-md text-body-md font-medium text-on-surface">{{ \Carbon\Carbon::parse($pemesanan->tanggal)->translatedFormat('d M Y') }}</p>
 </div>
 <div class="text-right">
 <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Waktu</p>
 <p class="font-body-md text-body-md font-medium text-on-surface">{{ \Carbon\Carbon::parse($pemesanan->waktu_estimasi_mulai)->format('H:i') }} WIB</p>
 </div>
 </div>
 </div>
 </div>
 
 <div class="relative h-6 bg-surface-container overflow-hidden flex items-center justify-center w-full">
 <div class="absolute -left-3 top-0 w-6 h-6 bg-surface rounded-full shadow-inner"></div>
 <div class="w-full h-[2px] border-t-2 border-dashed border-surface-container-highest mx-6 opacity-30"></div>
 <div class="absolute -right-3 top-0 w-6 h-6 bg-surface rounded-full shadow-inner"></div>
 </div>
 
 <div class="bg-surface-container rounded-b-xl p-4 lg:p-5 flex flex-col items-center justify-center relative">
 <!-- We can replace QR with a generic placeholder or dynamic QR -->
 <div class="bg-white p-3 rounded-xl shadow-sm mb-4">
 <span class="material-symbols-outlined text-[64px] text-surface-container">qr_code_2</span>
 </div>
 <p class="font-label-sm text-label-sm text-on-surface-variant tracking-[0.2em] uppercase">ID Pesanan</p>
 <p class="font-body-md text-body-md font-mono mt-1 text-on-surface">#RNC-{{ str_pad($pemesanan->id, 6, '0', STR_PAD_LEFT) }}</p>
 </div>
 </div>
 </div>
 
 <div class="px-margin-mobile pb-8 pt-4 flex flex-col gap-3 sticky bottom-0 bg-gradient-to-t from-surface via-surface/90 to-transparent">
 <button wire:click="refreshStatus" class="w-full py-4 rounded-lg border border-surface-container-highest bg-surface-container hover:bg-surface-container-high text-on-surface font-label-md text-label-md transition-all flex items-center justify-center gap-2">
 <span class="material-symbols-outlined text-[18px]">refresh</span>
 Segarkan Status
 </button>
 @if($pemesanan->status === 'Menunggu')
 <button wire:confirm="Apakah Anda yakin ingin membatalkan reservasi ini?" wire:click="cancelReservation" class="w-full py-4 rounded-lg bg-transparent hover:bg-error-container/10 text-error font-label-md text-label-md transition-all">
 Batalkan Reservasi
 </button>
 @endif
 </div>
 </div>
 </div>
</div>
