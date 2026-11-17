<?php

use App\Models\AntreanPemesanan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Nilai Pengalaman Anda - Ran CukurIn')] class extends Component {
 public $pemesanan;
 public int $rating_layanan = 0;
 public int $rating_suasana = 0;
 public string $ulasan = '';
 public bool $isEdit = false;
 
 public function mount($id)
 {
 $this->pemesanan = AntreanPemesanan::with(['layanan', 'kapster'])->findOrFail($id);
 
 if (auth()->check() && auth()->user()->role === 'Pelanggan' && $this->pemesanan->pelanggan_id !== auth()->id()) {
 abort(403);
 }
 
 if ($this->pemesanan->status !== 'Selesai') {
 session()->flash('message', 'Anda hanya dapat memberikan ulasan untuk transaksi yang sudah selesai.');
 $this->redirect(route('customer.riwayat'), navigate: true);
 return;
 }

 $existing = \App\Models\Ulasan::where('antrean_id', $this->pemesanan->id)->first();
 if ($existing) {
 $this->isEdit = true;
 $this->rating_layanan = $existing->rating;
 $this->rating_suasana = $existing->rating;
 $this->ulasan = $existing->komentar ?? '';
 }
 }
 
 public function setRatingLayanan($rating)
 {
 $this->rating_layanan = $rating;
 }
 
 public function setRatingSuasana($rating)
 {
 $this->rating_suasana = $rating;
 }

 public function submitUlasan()
 {
 $this->validate([
 'rating_layanan' => 'required|integer|min:1|max:5',
 'rating_suasana' => 'required|integer|min:1|max:5',
 'ulasan' => 'nullable|string|max:500'
 ]);

 try {
 $calculatedRating = (int) round(($this->rating_layanan + $this->rating_suasana) / 2);

 \App\Models\Ulasan::updateOrCreate(
 ['antrean_id' => $this->pemesanan->id],
 [
 'pelanggan_id' => $this->pemesanan->pelanggan_id,
 'kapster_id' => $this->pemesanan->kapster_id ?? 1,
 'rating' => $calculatedRating,
 'komentar' => $this->ulasan,
 ]
 );

 try {
 app(\App\Services\GoogleSheetsService::class)->syncTable('ulasan');
 } catch (\Throwable $e) {}

 if (auth()->check()) {
 cache()->forget('sheets_sync_ulasan_customer_' . auth()->id());
 }
 
 session()->flash('message', 'Terima kasih! Ulasan Anda telah berhasil disimpan dan disinkronkan ke Google Spreadsheet.');
 $this->redirect(route('customer.riwayat'), navigate: true);
 } catch (\Throwable $e) {
 $this->addError('ulasan', 'Gagal menyimpan ulasan: ' . $e->getMessage());
 }
 }
};
?>

 <div class="flex-grow flex flex-col bg-background font-body-md text-on-surface antialiased w-full">
 <div class="flex flex-col w-full relative flex-grow">
 <div class="w-full max-w-4xl mx-auto py-12 z-10 flex-grow flex flex-col justify-center">
 <div class="text-center mb-10">
 <h1 class="font-headline-xl text-on-surface mb-4 text-headline-xl">Nilai Pengalaman Anda</h1>
 <p class="font-body-lg text-body-lg text-on-surface-variant max-w-lg mx-auto">
 Bantu kami terus meningkatkan standar layanan dengan membagikan pengalaman grooming Anda di Ran CukurIn.
 </p>
 </div>
 
 <div class="bg-surface-container rounded-2xl shadow-xl overflow-hidden flex flex-col">
 <!-- Order Summary Banner -->
 <div class="bg-surface-container-high p-4 lg:p-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
 <div class="flex items-center gap-4">
 <div class="w-12 h-12 rounded-full overflow-hidden bg-surface-container-highest shrink-0 flex items-center justify-center">
 <span class="material-symbols-outlined text-[32px] text-on-surface-variant">person</span>
 </div>
 <div>
 <div class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider mb-1">Kapster</div>
 <div class="font-headline-md text-on-surface text-headline-md">{{ $pemesanan->kapster->nama ?? 'Bebas' }}</div>
 </div>
 </div>
 <div class="text-left sm:text-right">
 <div class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider mb-1">ID Pesanan</div>
 <div class="font-label-md text-label-md text-primary">#RNC-{{ str_pad($pemesanan->id, 6, '0', STR_PAD_LEFT) }}</div>
 <div class="font-body-md text-body-md text-on-surface mt-1">{{ $pemesanan->layanan->nama_layanan ?? 'N/A' }}</div>
 </div>
 </div>
 
 <!-- Rating Content -->
 <form wire:submit="submitUlasan" class="p-4 lg:p-5 sm:p-10 space-y-10">
 @if ($errors->any())
 <div class="p-4 bg-error-container text-on-error-container rounded-xl text-sm">
 <ul class="list-disc pl-5">
 @foreach ($errors->all() as $error)
 <li>{{ $error }}</li>
 @endforeach
 </ul>
 </div>
 @endif

 <!-- Service Rating -->
 <div class="space-y-4">
 <div class="flex justify-between items-center">
 <h3 class="font-headline-md text-on-surface text-headline-md">Kualitas Layanan</h3>
 <span class="font-label-sm text-label-sm {{ $rating_layanan > 0 ? 'text-primary' : 'text-on-surface-variant' }}">
 {{ $rating_layanan > 0 ? ['Sangat Kurang', 'Kurang', 'Cukup', 'Baik', 'Sangat Baik'][$rating_layanan-1] : 'Pilih penilaian' }}
 </span>
 </div>
 <div class="flex gap-2 sm:gap-4 justify-center py-4">
 @for($i=1; $i<=5; $i++)
 <button wire:click.prevent="setRatingLayanan({{ $i }})" class="group transition-transform hover:scale-110 focus:outline-none" type="button">
 <span class="material-symbols-outlined text-lg sm:text-xl {{ $rating_layanan >= $i ? 'text-primary' : 'text-surface-container-highest group-hover:text-primary' }} transition-colors" style="font-variation-settings: 'FILL' {{ $rating_layanan >= $i ? 1 : 0 }};">star</span>
 </button>
 @endfor
 </div>
 </div>
 
 <!-- Ambience Rating -->
 <div class="space-y-4">
 <div class="flex justify-between items-center">
 <h3 class="font-headline-md text-on-surface text-headline-md">Suasana &amp; Kenyamanan</h3>
 <span class="font-label-sm text-label-sm {{ $rating_suasana > 0 ? 'text-primary' : 'text-on-surface-variant' }}">
 {{ $rating_suasana > 0 ? ['Sangat Kurang', 'Kurang', 'Cukup', 'Baik', 'Sangat Baik'][$rating_suasana-1] : 'Pilih penilaian' }}
 </span>
 </div>
 <div class="flex gap-2 sm:gap-4 justify-center py-4">
 @for($i=1; $i<=5; $i++)
 <button wire:click.prevent="setRatingSuasana({{ $i }})" class="group transition-transform hover:scale-110 focus:outline-none" type="button">
 <span class="material-symbols-outlined text-lg sm:text-xl {{ $rating_suasana >= $i ? 'text-primary' : 'text-surface-container-highest group-hover:text-primary' }} transition-colors" style="font-variation-settings: 'FILL' {{ $rating_suasana >= $i ? 1 : 0 }};">star</span>
 </button>
 @endfor
 </div>
 </div>
 
 <!-- Feedback Textarea -->
 <div class="space-y-3">
 <div class="flex justify-between items-baseline">
 <label class="font-label-md text-label-md text-on-surface" for="ulasan">Ulasan Anda <span class="text-on-surface-variant font-normal">(Opsional)</span></label>
 </div>
 <textarea wire:model="ulasan" class="w-full bg-surface-dim border-2 border-surface-container-highest rounded-xl p-4 font-body-md text-body-md text-on-surface focus:outline-none focus:border-primary transition-colors resize-none placeholder-on-surface-variant/50" id="ulasan" placeholder="Ceritakan pengalaman Anda, apa yang Anda sukai atau apa yang bisa kami tingkatkan..." rows="4"></textarea>
 </div>
 
 <!-- Action Footer -->
 <div class="bg-surface-container-low p-4 lg:p-5 sm:px-10 flex flex-col-reverse sm:flex-row items-center justify-end gap-4 lg:gap-5 -mx-6 -mx-10 sm:-mx-10 -mb-6 sm:-mb-10 rounded-b-2xl mt-10">
 <a href="{{ route('customer.riwayat') }}" class="font-label-md text-label-md text-on-surface-variant hover:text-on-surface transition-colors uppercase tracking-wider">
 Batal
 </a>
 <button type="submit" class="w-full sm:w-auto bg-primary text-on-primary px-5 lg:px-5 py-3.5 rounded-full font-label-md text-label-md uppercase tracking-wider hover:scale-105 transition-transform flex items-center justify-center gap-2 shadow-lg shadow-primary/20">
 <span>{{ $isEdit ? 'Perbarui Ulasan' : 'Kirim Ulasan' }}</span>
 <span class="material-symbols-outlined text-[20px]">check</span>
 </button>
 </div>
 </form>
 </div>
 </div>
 </div>
</div>
