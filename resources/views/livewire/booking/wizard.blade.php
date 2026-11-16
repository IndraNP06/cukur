<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use App\Models\Layanan;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;

new #[Layout('components.layouts.customer')] #[Title('Booking Reservasi - Ran Cukurin')] class extends Component {
 public $step = 1;

 // Step 1: Layanan & Kapster
 public $selectedLayanan = null;
 public $selectedKapster = null;

 // Step 2: Tanggal
 public $selectedDate = null;
 
 // Step 3: Waktu
 public $selectedTime = null;

 // Step 4: Konfirmasi & Voucher
 public $promoCode = '';
 public $appliedVoucher = null;

 public function mount() {
 $this->selectedDate = Carbon::now()->format('Y-m-d');
 }

 public function nextStep() {
 if ($this->step === 1) {
 $this->validate([
 'selectedLayanan' => 'required',
 'selectedKapster' => 'required'
 ], [
 'selectedLayanan.required' => 'Pilih 1 layanan.',
 'selectedKapster.required' => 'Pilih kapster.'
 ]);
 } elseif ($this->step === 2) {
 $this->validate([
 'selectedDate' => 'required|date'
 ]);
 } elseif ($this->step === 3) {
 $this->validate([
 'selectedTime' => 'required'
 ]);
 }

 if ($this->step < 4) {
 $this->step++;
 }
 }

 public function previousStep() {
 if ($this->step > 1) {
 $this->step--;
 }
 }

    public function applyPromo() {
        if (empty($this->promoCode)) return;

        $today = now()->toDateString();
        $voucher = Voucher::where('kode_voucher', strtoupper(trim($this->promoCode)))
            ->where('is_active', true)
            ->where(function($q) use ($today) {
                $q->whereNull('tgl_mulai')->orWhere('tgl_mulai', '<=', $today);
            })
            ->where(function($q) use ($today) {
                $q->whereNull('tgl_berakhir')->orWhere('tgl_berakhir', '>=', $today);
            })
            ->first();

        if (!$voucher) {
            $this->addError('promoCode', 'Voucher tidak valid atau sudah kadaluarsa.');
            $this->appliedVoucher = null;
            return;
        }

        if ($voucher->kuota_maksimal > 0 && $voucher->kuota_terpakai >= $voucher->kuota_maksimal) {
            $this->addError('promoCode', 'Kuota voucher ini sudah habis.');
            $this->appliedVoucher = null;
            return;
        }

        $this->appliedVoucher = $voucher;
        $this->resetErrorBag('promoCode');
        session()->flash('promo_success', "Voucher '{$voucher->kode_voucher}' berhasil diterapkan.");
    }

 public function submitBooking() {
 if (!auth()->check()) {
 return redirect()->route('login');
 }

 // We will create the Antrean (Booking) here
 // Using kapster_id logic: if 'any', we assign an available one (for now random)
 $kapsterId = $this->selectedKapster;
 if ($kapsterId === 'any') {
 $kapster = User::whereHas('roles', function($q){$q->where('name', 'Kapster');})->inRandomOrder()->first();
 if (!$kapster) {
 $this->addError('selectedKapster', 'Mohon maaf, saat ini tidak ada kapster yang tersedia.');
 return;
 }
 $kapsterId = $kapster->id;
 }

        $booking = \App\Models\AntreanPemesanan::create([
            'pelanggan_id' => auth()->id(),
            'kapster_id' => $kapsterId,
            'layanan_id' => $this->selectedLayanan,
            'tipe_antrean' => 'Online',
            'nomor_antrean' => 'ONL-' . strtoupper(\Illuminate\Support\Str::random(5)),
            'tanggal' => $this->selectedDate,
            'waktu_estimasi_mulai' => $this->selectedTime,
            'status' => 'Menunggu'
        ]);

        try {
            app(\App\Services\GoogleSheetsService::class)->appendRow('antrean_pemesanan', [
                'id' => $booking->id,
                'nomor_antrean' => $booking->nomor_antrean,
                'pelanggan_id' => $booking->pelanggan_id,
                'kapster_id' => $booking->kapster_id,
                'layanan_id' => $booking->layanan_id,
                'tipe_antrean' => $booking->tipe_antrean,
                'tanggal' => $booking->tanggal,
                'waktu_estimasi_mulai' => $booking->waktu_estimasi_mulai,
                'status' => $booking->status,
                'created_at' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {}

        if ($this->appliedVoucher) {
            try {
                $this->appliedVoucher->increment('kuota_terpakai');
                app(\App\Services\GoogleSheetsService::class)->syncTable('voucher');
            } catch (\Throwable $e) {}
        }
 
 session()->flash('booking_success', 'Reservasi berhasil dibuat! Silakan datang tepat waktu.');
 return redirect('/');
 }

 public function with() {
 $layanans = Layanan::all();
 $kapsters = User::whereHas('roles', function($q){$q->where('name', 'Kapster');})->get();
 
 $subtotal = 0;
 $selectedLayananModel = null;
 if ($this->selectedLayanan) {
 $selectedLayananModel = Layanan::find($this->selectedLayanan);
 if ($selectedLayananModel) {
 $subtotal = $selectedLayananModel->harga;
 }
 }

 $diskon = 0;
 if ($this->appliedVoucher) {
 if (in_array(strtolower($this->appliedVoucher->tipe_diskon), ['persen', 'persentase'])) {
 $diskon = $subtotal * ($this->appliedVoucher->nilai_diskon / 100);
 } else {
 $diskon = $this->appliedVoucher->nilai_diskon;
 }
 }
 
 $total = max(0, $subtotal - $diskon);

 $selectedKapsterModel = null;
 if ($this->selectedKapster && $this->selectedKapster !== 'any') {
 $selectedKapsterModel = User::find($this->selectedKapster);
 }

 return [
 'layanans' => $layanans,
 'kapsters' => $kapsters,
 'subtotal' => $subtotal,
 'diskon' => $diskon,
 'total' => $total,
 'selectedLayananModel' => $selectedLayananModel,
 'selectedKapsterModel' => $selectedKapsterModel,
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

<div class="flex flex-col w-full max-w-[1200px] mx-auto px-margin-mobile lg:px-margin-desktop py-12">
 <!-- Progress Indicator -->
 <div class="mb-8 lg:mb-10 relative">
 <div class="absolute top-1/2 left-0 w-full h-1 bg-surface-container-highest -translate-y-1/2 rounded-full z-0"></div>
 <div class="absolute top-1/2 left-0 h-1 bg-primary -translate-y-1/2 rounded-full z-0 shadow-[0_0_12px_rgba(255,193,116,0.4)] transition-all" style="width: {{ ($step - 1) * 33.33 }}%;"></div>
 
 <div class="relative z-10 flex justify-between">
 <!-- Step 1 -->
 <div class="flex flex-col items-center gap-2">
 <div class="w-10 h-10 rounded-full {{ $step >= 1 ? 'bg-primary text-on-primary shadow-[0_0_16px_rgba(255,193,116,0.3)]' : 'bg-surface-container-highest text-on-surface-variant' }} flex items-center justify-center font-label-md text-label-md">1</div>
 <span class="font-label-sm text-label-sm {{ $step >= 1 ? 'text-primary' : 'text-on-surface-variant' }} uppercase tracking-wider hidden sm:block">Layanan</span>
 </div>
 <!-- Step 2 -->
 <div class="flex flex-col items-center gap-2">
 <div class="w-10 h-10 rounded-full {{ $step >= 2 ? 'bg-primary text-on-primary shadow-[0_0_16px_rgba(255,193,116,0.3)]' : 'bg-surface-container-highest text-on-surface-variant' }} flex items-center justify-center font-label-md text-label-md">2</div>
 <span class="font-label-sm text-label-sm {{ $step >= 2 ? 'text-primary' : 'text-on-surface-variant' }} uppercase tracking-wider hidden sm:block">Tanggal</span>
 </div>
 <!-- Step 3 -->
 <div class="flex flex-col items-center gap-2">
 <div class="w-10 h-10 rounded-full {{ $step >= 3 ? 'bg-primary text-on-primary shadow-[0_0_16px_rgba(255,193,116,0.3)]' : 'bg-surface-container-highest text-on-surface-variant' }} flex items-center justify-center font-label-md text-label-md">3</div>
 <span class="font-label-sm text-label-sm {{ $step >= 3 ? 'text-primary' : 'text-on-surface-variant' }} uppercase tracking-wider hidden sm:block">Waktu</span>
 </div>
 <!-- Step 4 -->
 <div class="flex flex-col items-center gap-2">
 <div class="w-10 h-10 rounded-full {{ $step >= 4 ? 'bg-primary text-on-primary shadow-[0_0_16px_rgba(255,193,116,0.3)]' : 'bg-surface-container-highest text-on-surface-variant' }} flex items-center justify-center font-label-md text-label-md">4</div>
 <span class="font-label-sm text-label-sm {{ $step >= 4 ? 'text-primary' : 'text-on-surface-variant' }} uppercase tracking-wider hidden sm:block">Konfirmasi</span>
 </div>
 </div>
 </div>

 <div class="bg-surface-container-low rounded-2xl p-5 lg:p-5 lg:p-12 shadow-2xl relative overflow-hidden min-h-[500px]">
 <!-- Decorative Ambient Light -->
 <div class="absolute top-0 right-0 w-56 h-56 bg-primary/5 rounded-full blur-[100px] pointer-events-none mix-blend-screen"></div>
 
 <div class="relative z-10">
 <!-- STEP 1: LAYANAN & KAPSTER -->
 @if($step === 1)
 <div class="space-y-16 animate-[fadeIn_0.3s_ease-out]">
 <!-- Service Selection -->
 <section>
 <div class="mb-8 flex items-end justify-between">
 <div>
 <h2 class="font-headline-lg-mobile lg:font-headline-lg text-on-surface mb-2 text-headline-lg-mobile">Pilih Layanan Anda</h2>
 <p class="font-body-md text-on-surface-variant">Pilih satu layanan premium untuk sesi grooming Anda.</p>
 @error('selectedLayanan') <span class="text-error text-sm block mt-2">{{ $message }}</span> @enderror
 </div>
 <span class="font-label-sm text-label-sm text-primary uppercase tracking-widest">[ Step 01 ]</span>
 </div>
 <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5">
 @foreach($layanans as $layanan)
 <label class="relative cursor-pointer group">
 <input wire:model="selectedLayanan" value="{{ $layanan->id }}" class="peer sr-only" type="radio" name="layanan_select">
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl transition-all duration-300 peer-checked:bg-surface-bright peer-checked:shadow-[0_0_0_2px_theme(colors.primary)] peer-checked:shadow-[0_8px_30px_rgba(255,193,116,0.15)] group-hover:-translate-y-1 h-full flex flex-col">
 <div class="flex justify-between items-start mb-4">
 <div class="w-16 h-16 rounded-xl overflow-hidden bg-surface-dim border border-outline-variant/30 flex items-center justify-center shrink-0 shadow-sm">
 <img src="{{ $layanan->gambar_url }}" alt="{{ $layanan->nama_layanan }}" class="w-full h-full object-cover">
 </div>
 <div class="w-6 h-6 rounded-full bg-primary text-on-primary flex items-center justify-center opacity-0 scale-50 transition-all peer-checked:opacity-100 peer-checked:scale-100">
 <span class="material-symbols-outlined text-[16px]">check</span>
 </div>
 </div>
 <h3 class="font-headline-md text-on-surface mb-2 text-headline-md">{{ $layanan->nama_layanan }}</h3>
 <div class="flex items-center gap-2 mb-6">
 <span class="material-symbols-outlined text-primary text-[18px]">schedule</span>
 <span class="font-label-sm text-label-sm text-on-surface-variant">{{ $layanan->estimasi_durasi_menit }} Menit</span>
 </div>
 <div class="mt-auto pt-4 border-t border-outline-variant/20 flex justify-between items-center">
 <span class="font-label-sm text-label-sm text-on-surface-variant uppercase">Harga</span>
 <span class="font-label-md text-label-md text-primary">Rp {{ number_format($layanan->harga, 0, ',', '.') }}</span>
 </div>
 </div>
 </label>
 @endforeach
 </div>
 </section>

 <!-- Barber Selection -->
 <section>
 <div class="mb-8">
 <h2 class="font-headline-lg-mobile lg:font-headline-lg text-on-surface mb-2 text-headline-lg-mobile">Pilih Kapster Anda</h2>
 <p class="font-body-md text-on-surface-variant">Tentukan kapster favorit Anda atau pilih acak untuk ketersediaan tercepat.</p>
 @error('selectedKapster') <span class="text-error text-sm block mt-2">{{ $message }}</span> @enderror
 </div>
 <div class="grid grid-cols-2 md:grid-cols-4 gap-4 lg:gap-5">
 @foreach($kapsters as $kapster)
 <label class="relative cursor-pointer group">
 <input wire:model="selectedKapster" value="{{ $kapster->id }}" class="peer sr-only" name="barber" type="radio">
 <div class="bg-surface-container rounded-xl p-4 text-center transition-all duration-300 peer-checked:bg-surface-bright peer-checked:shadow-[0_0_0_2px_theme(colors.primary)] hover:bg-surface-bright h-full">
 <div class="w-14 h-14 mx-auto rounded-full bg-surface-dim flex items-center justify-center mb-4 text-primary overflow-hidden shadow-inner">
 @if($kapster->foto_url)
 <img src="{{ $kapster->foto_url }}" alt="{{ $kapster->nama }}" class="w-full h-full object-cover">
 @else
 <span class="material-symbols-outlined text-[32px]">person</span>
 @endif
 </div>
 <h4 class="font-label-md text-label-md text-on-surface">{{ $kapster->nama }}</h4>
 <p class="font-label-sm text-[11px] text-primary mt-1 uppercase tracking-wider">Barber</p>
 </div>
 </label>
 @endforeach
 <label class="relative cursor-pointer group">
 <input wire:model="selectedKapster" value="any" class="peer sr-only" name="barber" type="radio">
 <div class="bg-surface-container rounded-xl p-4 text-center transition-all duration-300 peer-checked:bg-surface-bright peer-checked:shadow-[0_0_0_2px_theme(colors.primary)] hover:bg-surface-bright h-full flex flex-col justify-center items-center min-h-[160px]">
 <div class="w-12 h-12 rounded-full bg-surface-dim flex items-center justify-center mb-4 text-on-surface-variant group-hover:text-primary transition-colors">
 <span class="material-symbols-outlined text-lg">shuffle</span>
 </div>
 <h4 class="font-label-md text-label-md text-on-surface">Siapa Saja</h4>
 <p class="font-label-sm text-[11px] text-primary mt-1 uppercase tracking-wider">Tersedia</p>
 </div>
 </label>
 </div>
 </section>
 </div>
 @endif

 <!-- STEP 2: TANGGAL -->
 @if($step === 2)
 <div class="flex flex-col items-center justify-center py-10 animate-[fadeIn_0.3s_ease-out]">
 <div class="text-center mb-8 lg:mb-10">
 <h2 class="font-headline-xl text-on-surface mb-4 text-headline-xl">Tentukan Tanggal Kedatangan</h2>
 <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">Pilih jadwal yang sesuai dengan agenda Anda.</p>
 @error('selectedDate') <span class="text-error text-sm block mt-2">{{ $message }}</span> @enderror
 </div>
 
 <div class="bg-surface rounded-2xl p-5 lg:p-5 border border-outline-variant/20 max-w-md w-full mx-auto">
 <div class="space-y-4">
 <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Tanggal</label>
 <div class="relative">
 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-primary">calendar_month</span>
 <input wire:model="selectedDate" type="date" min="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" class="w-full bg-surface-container border border-outline-variant rounded-xl py-4 pl-12 pr-4 text-on-surface font-body-lg focus:outline-none focus:border-primary [color-scheme:dark]">
 </div>
 </div>
 </div>
 </div>
 @endif

 <!-- STEP 3: WAKTU -->
 @if($step === 3)
 <div class="flex flex-col gap-5 lg:gap-5 py-4 animate-[fadeIn_0.3s_ease-out]">
 <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-4 gap-4">
 <div>
 <h2 class="font-headline-xl text-on-surface tracking-tight text-headline-xl">Pilih Jam Reservasi</h2>
 <p class="font-body-lg text-body-lg text-on-surface-variant mt-2 flex items-center gap-2">
 <span class="material-symbols-outlined text-primary">calendar_today</span>
 Jadwal untuk: <span class="text-primary font-semibold">{{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('l, d F Y') }}</span>
 </p>
 @error('selectedTime') <span class="text-error text-sm block mt-2">{{ $message }}</span> @enderror
 </div>
 <div class="flex items-center gap-2 bg-surface-container-lowest px-4 py-2 rounded-lg">
 <span class="material-symbols-outlined text-on-surface-variant text-[20px]">schedule</span>
 <span class="font-label-md text-label-md text-on-surface-variant">Durasi: ~{{ $selectedLayananModel ? $selectedLayananModel->estimasi_durasi_menit : 0 }} Min</span>
 </div>
 </div>

 <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
 @foreach($timeSlots as $time)
 <label class="cursor-pointer">
 <input wire:model="selectedTime" value="{{ $time }}" class="peer sr-only" type="radio" name="timeSlot">
 <div class="bg-surface-container-high hover:bg-surface-variant text-on-surface font-headline-md py-4 rounded-xl transition-all duration-200 flex flex-col items-center justify-center gap-1 group peer-checked:bg-primary peer-checked:text-on-primary peer-checked:scale-105 peer-checked:shadow-lg peer-checked:shadow-primary/30 relative text-headline-md">
 {{ $time }}
 <span class="font-label-sm text-label-sm opacity-0 group-hover:opacity-100 peer-checked:opacity-100 peer-checked:font-bold transition-opacity">
 Pilih
 </span>
 </div>
 </label>
 @endforeach
 </div>
 </div>
 @endif

 <!-- STEP 4: KONFIRMASI -->
 @if($step === 4)
 <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-5 lg:gap-12 items-start animate-[fadeIn_0.3s_ease-out]">
 <!-- Left Column: Details -->
 <div class="lg:col-span-7 flex flex-col gap-4 lg:gap-5">
 <div class="bg-surface-container rounded-xl p-4 lg:p-5 flex flex-col gap-5 lg:gap-5 relative overflow-hidden group shadow-lg">
 <h2 class="font-headline-md text-on-surface flex items-center gap-3 text-headline-md">
 <span class="material-symbols-outlined text-primary">receipt_long</span>
 Rincian Reservasi
 </h2>
 <div class="flex flex-col gap-4 lg:gap-5">
 <!-- Service Detail -->
 <div class="flex items-start gap-4 p-4 rounded-lg bg-surface-container-high">
 <div class="w-12 h-12 rounded-lg bg-surface flex items-center justify-center text-primary shrink-0 overflow-hidden">
 @if($selectedLayananModel && $selectedLayananModel->gambar_url)
 <img src="{{ $selectedLayananModel->gambar_url }}" alt="{{ $selectedLayananModel->nama_layanan }}" class="w-full h-full object-cover">
 @else
 <span class="material-symbols-outlined">content_cut</span>
 @endif
 </div>
 <div class="flex flex-col gap-1 w-full">
 <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Layanan Terpilih</span>
 @if($selectedLayananModel)
 <div class="flex justify-between items-center mt-1">
 <span class="font-headline-sm text-[18px] font-semibold text-on-surface">{{ $selectedLayananModel->nama_layanan }}</span>
 <span class="font-label-sm text-on-surface-variant">Rp {{ number_format($selectedLayananModel->harga,0,',','.') }}</span>
 </div>
 @endif
 </div>
 </div>
 <!-- Barber Detail -->
 <div class="flex items-start gap-4 p-4 rounded-lg bg-surface-container-high">
 <div class="w-12 h-12 rounded-lg bg-surface flex items-center justify-center text-primary shrink-0 overflow-hidden">
 @if($selectedKapsterModel && $selectedKapsterModel->foto_url)
 <img src="{{ $selectedKapsterModel->foto_url }}" alt="{{ $selectedKapsterModel->nama }}" class="w-full h-full object-cover">
 @else
 <span class="material-symbols-outlined">person</span>
 @endif
 </div>
 <div class="flex flex-col gap-1 w-full justify-center">
 <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Tukang Cukur</span>
 <span class="font-headline-sm text-[20px] font-semibold text-on-surface">
 {{ $selectedKapsterModel ? $selectedKapsterModel->nama : 'Siapa Saja (Tersedia)' }}
 </span>
 </div>
 </div>
 <!-- Schedule Detail -->
 <div class="flex items-start gap-4 p-4 rounded-lg bg-surface-container-high">
 <div class="w-12 h-12 rounded-lg bg-surface flex items-center justify-center text-primary shrink-0">
 <span class="material-symbols-outlined">calendar_month</span>
 </div>
 <div class="flex flex-col gap-1 w-full">
 <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Jadwal</span>
 <div class="flex items-center gap-3 mt-1">
 <span class="font-headline-sm text-[18px] font-semibold text-on-surface">{{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('l, d M Y') }}</span>
 <span class="w-1 h-1 rounded-full bg-on-surface-variant"></span>
 <span class="font-headline-sm text-[18px] font-bold text-primary bg-primary/10 px-3 py-1 rounded-md">{{ $selectedTime }} WIB</span>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 
 <!-- Right Column: Billing -->
 <div class="lg:col-span-5 flex flex-col gap-4 lg:gap-5">
 <div class="bg-surface-container rounded-xl p-4 lg:p-5 flex flex-col gap-4 lg:gap-5 shadow-xl">
 <h2 class="font-headline-md text-[22px] font-semibold text-on-surface text-headline-md">Rincian Biaya</h2>
 
 <!-- Promo Code -->
 <div class="flex flex-col gap-2">
 <label class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Kode Promo / Voucher</label>
 <div class="flex gap-2">
 <div class="relative flex-1">
 <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">local_activity</span>
 <input wire:model="promoCode" class="w-full bg-surface border-2 border-surface-container-high rounded-lg pl-10 pr-4 py-3 text-on-surface font-body-md focus:outline-none focus:border-primary transition-colors" placeholder="Masukkan kode">
 </div>
 <button wire:click="applyPromo" type="button" class="bg-surface-container-high text-on-surface px-4 py-3 rounded-lg font-label-md hover:bg-surface-container-highest transition-colors">Terapkan</button>
 </div>
 @error('promoCode') <span class="text-error text-sm">{{ $message }}</span> @enderror
 @if(session('promo_success'))
 <div class="flex items-center gap-1 text-[13px] text-emerald-400 mt-1">
 <span class="material-symbols-outlined text-[16px]">check_circle</span>
 {{ session('promo_success') }}
 </div>
 @endif
 </div>
 
 <div class="h-[1px] w-full bg-surface-variant/50 my-2"></div>
 
 <!-- Breakdown -->
 <div class="flex flex-col gap-4">
 <div class="flex items-center justify-between">
 <span class="font-body-md text-on-surface-variant">Subtotal</span>
 <span class="font-body-md text-on-surface">Rp {{ number_format($subtotal,0,',','.') }}</span>
 </div>
 @if($diskon > 0)
 <div class="flex items-center justify-between">
 <span class="font-body-md text-on-surface-variant">Diskon Voucher</span>
 <span class="font-body-md text-emerald-400 font-medium">- Rp {{ number_format($diskon,0,',','.') }}</span>
 </div>
 @endif
 </div>
 
 <div class="h-[1px] w-full bg-surface-variant my-2 border-dashed border-b-2 border-surface-variant"></div>
 
 <!-- Grand Total -->
 <div class="flex items-center justify-between pt-2">
 <span class="font-headline-sm text-[20px] font-semibold text-on-surface">Total Bayar</span>
 <span class="font-headline-xl text-[32px] font-bold text-primary tracking-tight text-headline-xl">Rp {{ number_format($total,0,',','.') }}</span>
 </div>
 </div>
 </div>
 </div>
 @endif

 <!-- Wizard Footer Navigation -->
 <div class="mt-8 flex justify-between items-center border-t border-outline-variant/10 pt-6">
 @if($step > 1)
 <button wire:click="previousStep" class="bg-surface-container-high text-on-surface px-4 lg:px-5 py-3 rounded-full font-label-md text-label-md uppercase tracking-wider hover:bg-surface-variant transition-all flex items-center gap-3">
 <span class="material-symbols-outlined text-[20px]">arrow_back</span>
 Kembali
 </button>
 @else
 <div></div>
 @endif
 
 @if($step < 4)
 <button wire:click="nextStep" class="bg-primary text-on-primary px-5 lg:px-5 py-3 rounded-full font-label-md text-label-md uppercase tracking-widest hover:scale-105 hover:bg-primary-container transition-all flex items-center gap-3 shadow-md shadow-primary/20">
 Selanjutnya
 <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
 </button>
 @else
 <button wire:click="submitBooking" class="bg-primary text-on-primary px-5 lg:px-5 py-4 rounded-full font-label-md text-label-md uppercase tracking-widest hover:scale-105 hover:bg-primary-container transition-all flex items-center gap-3 shadow-[0_4px_20px_rgba(255,193,116,0.3)]">
 Selesaikan Reservasi
 <span class="material-symbols-outlined text-[24px]">check_circle</span>
 </button>
 @endif
 </div>
 </div>
 </div>
</div>

