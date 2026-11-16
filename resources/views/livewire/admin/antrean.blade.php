<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use App\Models\AntreanPemesanan;
use App\Models\User;
use Carbon\Carbon;

use App\Models\Layanan;
use Illuminate\Support\Str;

new #[Layout('components.layouts.admin')] #[Title('Antrean Terpadu - Ran Cukurin')] class extends Component {
 public $filterKapster = 'all';

 // Walk-In Modal
 public $showWalkInModal = false;
 public $walkInName = '';
 public $walkInLayanan = null;
 public $walkInKapster = null;
 public $walkInTime = null;

 public function mount() {
 // Initialization if needed
 }

 public function startProcess($id) {
 $antrean = AntreanPemesanan::find($id);
 if ($antrean && $antrean->status === 'Menunggu') {
 $antrean->status = 'Diproses';
 $antrean->waktu_mulai_aktual = Carbon::now()->format('H:i:s');
 $antrean->save();
 }
 }

 public function finishProcess($id) {
 $antrean = AntreanPemesanan::find($id);
 if ($antrean && $antrean->status === 'Diproses') {
 $antrean->status = 'Selesai';
 $antrean->waktu_selesai_aktual = Carbon::now()->format('H:i:s');
 $antrean->save();
 }
 }

 public function moveToKasir($id) {
 // Just redirect to POS with antrean_id
 return redirect()->route('admin.pos', ['antrean_id' => $id]);
 }

 public function saveWalkIn() {
 $this->validate([
 'walkInName' => 'required|string|max:255',
 'walkInLayanan' => 'required',
 'walkInKapster' => 'required',
 'walkInTime' => 'required'
 ]);

 // Create dummy user for Walk-in
 $dummyEmail = 'walkin_' . time() . '_' . Str::random(5) . '@rancukurin.local';
 $user = User::create([
 'nama' => $this->walkInName . ' (Walk-In)',
 'email' => $dummyEmail,
 'password' => bcrypt('password123'),
 ]);
 $user->assignRole('pelanggan');

 $kapsterId = $this->walkInKapster;
 if ($kapsterId === 'any') {
 $kapster = User::whereHas('roles', function($q){$q->where('name', 'Kapster');})->inRandomOrder()->first();
 if (!$kapster) {
 $this->addError('walkInKapster', 'Mohon maaf, saat ini tidak ada kapster yang tersedia.');
 return;
 }
 $kapsterId = $kapster->id;
 }

        $antrean = AntreanPemesanan::create([
            'pelanggan_id' => $user->id,
            'kapster_id' => $kapsterId,
            'layanan_id' => $this->walkInLayanan,
            'tipe_antrean' => 'WalkIn',
            'nomor_antrean' => 'WLK-' . strtoupper(Str::random(5)),
            'tanggal' => Carbon::today()->format('Y-m-d'),
            'waktu_estimasi_mulai' => $this->walkInTime,
            'status' => 'Menunggu'
        ]);

        try {
            app(\App\Services\GoogleSheetsService::class)->appendRow('antrean_pemesanan', [
                'id' => $antrean->id,
                'pelanggan_id' => $antrean->pelanggan_id,
                'kapster_id' => $antrean->kapster_id,
                'layanan_id' => $antrean->layanan_id,
                'tipe_antrean' => $antrean->tipe_antrean,
                'nomor_antrean' => $antrean->nomor_antrean,
                'tanggal' => $antrean->tanggal,
                'waktu_estimasi_mulai' => $antrean->waktu_estimasi_mulai,
                'status' => $antrean->status,
                'created_at' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {}

 $this->showWalkInModal = false;
 $this->reset(['walkInName', 'walkInLayanan', 'walkInKapster', 'walkInTime']);
 }

 public function with() {
 $query = AntreanPemesanan::with(['pelanggan', 'kapster', 'layanan'])
 ->whereDate('tanggal', Carbon::today());

 if ($this->filterKapster !== 'all') {
 $query->where('kapster_id', $this->filterKapster);
 }

 $semuaAntrean = $query->orderBy('waktu_estimasi_mulai', 'asc')->get();

 return [
 'kapsters' => User::whereHas('roles', function($q){$q->where('name', 'Kapster');})->get(),
 'layanans' => Layanan::where('is_active', true)->get(),
 'menunggu' => $semuaAntrean->where('status', 'Menunggu'),
 'diproses' => $semuaAntrean->where('status', 'Diproses'),
 'selesai' => $semuaAntrean->where('status', 'Selesai'),
 'timeSlots' => [
 '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', 
 '12:00', '12:30', '13:00', '13:30', '14:00', '14:30',
 '15:00', '15:30', '16:00', '16:30', '17:00', '17:30',
 '18:00', '18:30', '19:00', '19:30', '20:00'
 ]
 ];
 }
};
?>

<div class="flex flex-col w-full h-full min-h-full" wire:poll.5s>
 <!-- Top Control Bar -->
 <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
 <div>
 <h1 class="font-headline-lg text-on-surface mb-1 text-headline-lg">Papan Kerja Antrean</h1>
 <p class="font-body-md text-body-md text-on-surface-variant">Manajemen antrean pelanggan real-time (Hari ini).</p>
 </div>
 <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 w-full md:w-auto">
 <!-- Filter -->
 <div class="relative min-w-[200px]">
 <select wire:model.live="filterKapster" class="w-full appearance-none bg-surface-container-high text-on-surface font-label-md text-label-md rounded-lg pl-4 pr-10 py-3 outline-none hover:bg-surface-container-highest transition-colors cursor-pointer">
 <option value="all">Semua Kapster</option>
 @foreach($kapsters as $k)
 <option value="{{ $k->id }}">{{ $k->nama }}</option>
 @endforeach
 </select>
 <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-on-surface-variant">
 <span class="material-symbols-outlined text-[20px]">expand_more</span>
 </div>
 </div>
 <!-- Action Button -->
 <button wire:click="$set('showWalkInModal', true)" class="bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md rounded-lg px-4 lg:px-5 py-3 flex items-center justify-center gap-2 transition-all transform hover:scale-[1.02] active:scale-95 shadow-md shadow-primary/20">
 <span class="material-symbols-outlined text-[20px]">add</span>
 Input Walk-In
 </button>
 </div>
 </div>

 <!-- Kanban Board -->
 <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-5 flex-1 min-h-[500px]">
 <!-- Column 1: Menunggu -->
 <div class="flex flex-col h-full bg-surface-container-low rounded-xl shadow-lg overflow-hidden">
 <div class="p-4 bg-surface-container/50 border-b border-surface/5 flex items-center justify-between shrink-0">
 <div class="flex items-center gap-3">
 <div class="w-3 h-3 rounded-full bg-secondary-fixed"></div>
 <h2 class="font-headline-md text-[18px] text-on-surface text-headline-md">Menunggu</h2>
 </div>
 <span class="bg-surface-container-highest text-on-surface font-label-sm px-2.5 py-1 rounded-full">{{ $menunggu->count() }}</span>
 </div>
 <div class="p-4 overflow-y-auto space-y-4 flex-1">
 @forelse($menunggu as $a)
 <div class="bg-surface-container rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow relative">
 <div class="flex justify-between items-start mb-3">
 <div>
 <h3 class="font-headline-md text-[16px] text-on-surface leading-tight mb-1 text-headline-md">{{ optional($a->pelanggan)->nama ?? 'Pelanggan Umum' }}</h3>
 <p class="font-body-md text-label-sm text-on-surface-variant">{{ $a->layanan->nama_layanan }}</p>
 </div>
 @if($a->tipe_antrean === 'Online')
 <span class="bg-[#1E3A8A] text-[#93C5FD] font-label-sm px-2 py-1 rounded text-[10px] uppercase tracking-wider">Online</span>
 @else
 <span class="bg-[#4C1D95] text-[#C4B5FD] font-label-sm px-2 py-1 rounded text-[10px] uppercase tracking-wider">Walk-In</span>
 @endif
 </div>
 <div class="flex items-center gap-2 mb-4 text-on-surface-variant font-label-sm">
 <span class="material-symbols-outlined text-[16px]">schedule</span>
 <span>{{ substr($a->waktu_estimasi_mulai, 0, 5) }} WIB</span>
 </div>
 <button wire:click="startProcess({{ $a->id }})" class="w-full bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-label-md text-label-sm rounded-lg py-2 transition-colors border border-surface/10 hover:border-primary/50">
 Mulai Pengerjaan
 </button>
 </div>
 @empty
 <div class="text-center text-on-surface-variant py-5 lg:py-5 opacity-50">Tidak ada antrean menunggu</div>
 @endforelse
 </div>
 </div>

 <!-- Column 2: Sedang Diproses -->
 <div class="flex flex-col h-full bg-surface-container-low rounded-xl shadow-lg overflow-hidden relative">
 <div class="absolute -inset-1 bg-gradient-to-b from-primary/10 to-transparent opacity-50 pointer-events-none"></div>
 <div class="relative p-4 bg-surface-container/50 border-b border-surface/5 flex items-center justify-between shrink-0 z-10">
 <div class="flex items-center gap-3">
 <div class="w-3 h-3 rounded-full bg-primary animate-pulse shadow-[0_0_8px_rgba(245,158,11,0.6)]"></div>
 <h2 class="font-headline-md text-[18px] text-on-surface text-headline-md">Sedang Diproses</h2>
 </div>
 <span class="bg-primary/20 text-primary font-label-sm px-2.5 py-1 rounded-full">{{ $diproses->count() }}</span>
 </div>
 <div class="relative p-4 overflow-y-auto space-y-4 flex-1 z-10">
 @forelse($diproses as $a)
 <div class="bg-surface-container rounded-xl p-4 shadow-xl border-l-[3px] border-primary relative group">
 <div class="flex justify-between items-start mb-3">
 <div>
 <h3 class="font-headline-md text-[16px] text-on-surface leading-tight mb-1 text-headline-md">{{ optional($a->pelanggan)->nama ?? 'Pelanggan Umum' }}</h3>
 <p class="font-body-md text-label-sm text-on-surface-variant">In Progress with <strong class="text-on-surface">{{ $a->kapster->nama }}</strong></p>
 </div>
 @if($a->tipe_antrean === 'Online')
 <span class="bg-[#1E3A8A] text-[#93C5FD] font-label-sm px-2 py-1 rounded text-[10px] uppercase tracking-wider">Online</span>
 @else
 <span class="bg-[#4C1D95] text-[#C4B5FD] font-label-sm px-2 py-1 rounded text-[10px] uppercase tracking-wider">Walk-In</span>
 @endif
 </div>
 <div class="flex items-center gap-4 mb-4 font-label-sm">
 <div class="flex items-center gap-2 text-primary">
 <span class="material-symbols-outlined text-[16px] animate-spin">sync</span>
 <span>Memotong...</span>
 </div>
 <div class="flex items-center gap-2 text-on-surface-variant">
 <span class="material-symbols-outlined text-[16px]">schedule</span>
 <span>{{ substr($a->waktu_mulai_aktual, 0, 5) }} WIB</span>
 </div>
 </div>
 <button wire:click="finishProcess({{ $a->id }})" class="w-full bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-sm rounded-lg py-2 transition-all transform hover:scale-[1.02] shadow-sm shadow-primary/20">
 Selesai
 </button>
 </div>
 @empty
 <div class="text-center text-on-surface-variant py-5 lg:py-5 opacity-50">Tidak ada pengerjaan saat ini</div>
 @endforelse
 </div>
 </div>

 <!-- Column 3: Selesai -->
 <div class="flex flex-col h-full bg-surface-container-lowest rounded-xl shadow-inner overflow-hidden opacity-90">
 <div class="p-4 bg-surface-container/30 border-b border-surface/5 flex items-center justify-between shrink-0">
 <div class="flex items-center gap-3">
 <div class="w-3 h-3 rounded-full bg-[#065F46]"></div>
 <h2 class="font-headline-md text-[18px] text-on-surface-variant text-headline-md">Selesai</h2>
 </div>
 <span class="bg-surface-container-highest text-on-surface-variant font-label-sm px-2.5 py-1 rounded-full">{{ $selesai->count() }}</span>
 </div>
 <div class="p-4 overflow-y-auto space-y-4 flex-1">
 @forelse($selesai as $a)
 <div class="bg-surface-container-low rounded-xl p-4 shadow-sm opacity-75 hover:opacity-100 transition-opacity relative group">
 <div class="flex justify-between items-start mb-3">
 <div>
 <h3 class="font-headline-md text-[16px] text-on-surface-variant leading-tight mb-1 line-through decoration-on-surface-variant/50 text-headline-md">{{ optional($a->pelanggan)->nama ?? 'Pelanggan Umum' }}</h3>
 <p class="font-body-md text-label-sm text-outline-variant">{{ $a->layanan->nama_layanan }}</p>
 </div>
 <span class="bg-[#064E3B] text-[#34D399] font-label-sm px-2 py-1 rounded text-[10px] uppercase tracking-wider">Selesai</span>
 </div>
 <button wire:click="moveToKasir({{ $a->id }})" class="w-full bg-surface-container hover:bg-surface-container-high text-on-surface font-label-md text-label-sm rounded-lg py-2 mt-2 transition-colors border border-surface-variant hover:text-primary">
 Proses ke Kasir / POS
 </button>
 </div>
 @empty
 <div class="text-center text-on-surface-variant py-5 lg:py-5 opacity-50">Belum ada pelanggan selesai</div>
 @endforelse
 </div>
 </div>
 </div>

 <!-- Walk-In Modal -->
 @if($showWalkInModal)
 <div class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm p-4">
 <!-- Modal Container -->
 <div class="w-full max-w-lg bg-surface-container-high rounded-2xl shadow-2xl overflow-hidden animate-[scale-up_0.2s_ease-out]">
 <!-- Header -->
 <div class="flex items-center justify-between p-4 lg:p-5 bg-surface-container-highest border-b border-surface-variant">
 <h2 class="font-headline-md text-on-surface text-headline-md">Tambah Antrean Walk-In</h2>
 <button wire:click="$set('showWalkInModal', false)" class="text-on-surface-variant hover:text-primary transition-colors focus:outline-none">
 <span class="material-symbols-outlined">close</span>
 </button>
 </div>
 <!-- Body -->
 <div class="p-4 lg:p-5 space-y-6">
 <!-- Customer Name -->
 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Nama Pelanggan</label>
 <input wire:model="walkInName" class="w-full bg-surface-container text-on-surface font-body-md px-4 py-3 rounded-lg border border-surface-variant focus:outline-none focus:border-primary transition-colors" placeholder="Masukkan nama..." type="text">
 @error('walkInName') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 <!-- Service & Barber Row -->
 <div class="grid grid-cols-2 gap-4">
 <div class="space-y-2 relative group">
 <label class="block font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Layanan</label>
 <select wire:model="walkInLayanan" class="w-full bg-surface-container text-on-surface font-body-md px-4 py-3 rounded-lg border border-surface-variant focus:outline-none focus:border-primary transition-colors appearance-none">
 <option value="">Pilih Layanan</option>
 @foreach($layanans as $l)
 <option value="{{ $l->id }}">{{ $l->nama_layanan }}</option>
 @endforeach
 </select>
 @error('walkInLayanan') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 <div class="space-y-2 relative group">
 <label class="block font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Barber</label>
 <select wire:model="walkInKapster" class="w-full bg-surface-container text-on-surface font-body-md px-4 py-3 rounded-lg border border-surface-variant focus:outline-none focus:border-primary transition-colors appearance-none">
 <option value="">Pilih Kapster</option>
 <option value="any">Siapa Saja</option>
 @foreach($kapsters as $k)
 <option value="{{ $k->id }}">{{ $k->nama }}</option>
 @endforeach
 </select>
 @error('walkInKapster') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 </div>
 <!-- Time Slot Grid -->
 <div class="space-y-3 pt-2">
 <div class="flex items-center justify-between">
 <label class="block font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Pilih Waktu (Hari Ini)</label>
 </div>
 <div class="grid grid-cols-4 gap-3">
 @foreach($timeSlots as $time)
 <label class="cursor-pointer">
 <input wire:model="walkInTime" value="{{ $time }}" class="peer sr-only" type="radio">
 <div class="py-2.5 rounded-lg border border-surface-variant bg-surface text-on-surface text-center hover:bg-surface-variant hover:text-on-surface transition-colors font-label-md peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary peer-checked:shadow-[0_0_15px_rgba(255,193,116,0.15)] relative">
 {{ $time }}
 </div>
 </label>
 @endforeach
 </div>
 @error('walkInTime') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 </div>
 <!-- Footer -->
 <div class="p-4 lg:p-5 bg-surface-container-highest border-t border-surface-variant flex items-center justify-end gap-4">
 <button wire:click="$set('showWalkInModal', false)" class="px-4 lg:px-5 py-2.5 text-on-surface-variant font-label-md hover:text-on-surface transition-colors focus:outline-none">
 Batal
 </button>
 <button wire:click="saveWalkIn" class="px-4 lg:px-5 py-2.5 bg-primary text-on-primary font-label-md rounded-lg hover:bg-primary-fixed transition-colors hover:scale-105 active:scale-95 focus:outline-none flex items-center gap-2 shadow-lg shadow-primary/20">
 Simpan ke Antrean
 <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
 </button>
 </div>
 </div>
 </div>
 @endif
 <style>
 @keyframes scale-up {
 from { opacity: 0; transform: scale(0.95); }
 to { opacity: 1; transform: scale(1); }
 }
 </style>
</div>

