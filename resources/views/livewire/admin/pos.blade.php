<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use App\Models\AntreanPemesanan;
use App\Models\Barang;
use App\Models\Voucher;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\LogStok;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.admin')] #[Title('Sistem POS - Ran Cukurin')] class extends Component {
 public $antrean_id;
 public AntreanPemesanan $antrean;
 
 public $searchQuery = '';
 public $searchResults = [];
 
 public $cart = []; // Array of ['type' => 'layanan'|'barang', 'id' => ID, 'name' => string, 'price' => float, 'qty' => int]
 
 public $voucherCode = '';
 public $appliedVoucher = null;
 
 public $paymentMethod = 'Cash'; // Cash, QRIS, Transfer
 public $uangDiterima = null;

 public function mount($antrean_id = null) {
 if (!$antrean_id) {
 abort(404);
 }

 $this->antrean_id = $antrean_id;
 $this->antrean = AntreanPemesanan::with(['pelanggan', 'kapster', 'layanan'])->findOrFail($antrean_id);

 $user = auth()->user();
 $isOwner = $user && (
     $user->role === 'Pemilik' || 
     $user->role === 'Admin' || 
     (method_exists($user, 'hasRole') && ($user->hasRole('Pemilik') || $user->hasRole('Admin'))) ||
     str_contains(strtolower($user->role ?? ''), 'pemilik') ||
     str_contains(strtolower($user->role ?? ''), 'admin') ||
     $user->id === 1 || 
     $user->id === 2
 );
 if (!$isOwner && $this->antrean->kapster_id && (int)$this->antrean->kapster_id !== (int)auth()->id()) {
 abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk memproses transaksi kasir milik kapster lain.');
 }

 // Add service to cart
 $this->cart[] = [
 'type' => 'Layanan',
 'id' => $this->antrean->layanan->id,
 'name' => $this->antrean->layanan->nama_layanan,
 'price' => $this->antrean->layanan->harga,
 'qty' => 1
 ];

 $this->uangDiterima = number_format($this->total, 0, ',', '.');
 }

 public function setUangPas() {
 $this->uangDiterima = number_format($this->total, 0, ',', '.');
 }

 public function setUangCepat($nominal) {
 $this->uangDiterima = number_format($nominal, 0, ',', '.');
 }

 public function updatedSearchQuery() {
 if (strlen($this->searchQuery) > 1) {
 $this->searchResults = Barang::where('tipe', 'ProdukJual')
 ->where('nama_barang', 'like', '%' . $this->searchQuery . '%')
 ->where('stok_sekarang', '>', 0)
 ->take(5)
 ->get();
 } else {
 $this->searchResults = [];
 }
 }

 public function addProduct($barangId) {
 $barang = Barang::find($barangId);
 if (!$barang) return;

 // Check if already in cart
 $exists = false;
 foreach ($this->cart as $key => $item) {
 if ($item['type'] === 'Barang' && $item['id'] === $barangId) {
 if ($this->cart[$key]['qty'] < $barang->stok_sekarang) {
 $this->cart[$key]['qty']++;
 } else {
 session()->flash('error', 'Stok tidak mencukupi.');
 }
 $exists = true;
 break;
 }
 }

 if (!$exists) {
 $this->cart[] = [
 'type' => 'Barang',
 'id' => $barang->id,
 'name' => $barang->nama_barang,
 'price' => $barang->harga_jual,
 'qty' => 1
 ];
 }

 $this->searchQuery = '';
 $this->searchResults = [];
 }

 public function removeProduct($index) {
 if ($this->cart[$index]['type'] === 'Barang') {
 unset($this->cart[$index]);
 $this->cart = array_values($this->cart); // Re-index
 }
 }

 public function increaseQty($index) {
 if ($this->cart[$index]['type'] === 'Barang') {
 $barang = Barang::find($this->cart[$index]['id']);
 if ($this->cart[$index]['qty'] < $barang->stok_sekarang) {
 $this->cart[$index]['qty']++;
 }
 }
 }

 public function decreaseQty($index) {
 if ($this->cart[$index]['type'] === 'Barang') {
 if ($this->cart[$index]['qty'] > 1) {
 $this->cart[$index]['qty']--;
 } else {
 $this->removeProduct($index);
 }
 }
 }

 public function applyVoucher() {
 if (empty($this->voucherCode)) return;

 $today = now()->toDateString();
 $voucher = Voucher::where('kode_voucher', strtoupper(trim($this->voucherCode)))
 ->where('is_active', true)
 ->where(function($q) use ($today) {
 $q->whereNull('tgl_mulai')->orWhere('tgl_mulai', '<=', $today);
 })
 ->where(function($q) use ($today) {
 $q->whereNull('tgl_berakhir')->orWhere('tgl_berakhir', '>=', $today);
 })
 ->first();

 if (!$voucher) {
 session()->flash('voucher_error', 'Voucher tidak valid atau kadaluarsa.');
 $this->appliedVoucher = null;
 return;
 }

 if ($voucher->kuota_maksimal > 0 && $voucher->kuota_terpakai >= $voucher->kuota_maksimal) {
 session()->flash('voucher_error', 'Kuota voucher ini sudah habis.');
 $this->appliedVoucher = null;
 return;
 }

 $this->appliedVoucher = $voucher;
 session()->flash('voucher_success', 'Voucher berhasil diterapkan.');
 }

 public function getSubtotalProperty() {
 $subtotal = 0;
 foreach ($this->cart as $item) {
 $subtotal += ($item['price'] * $item['qty']);
 }
 return $subtotal;
 }

 public function getDiskonProperty() {
 if (!$this->appliedVoucher) return 0;

 if ($this->appliedVoucher->tipe_diskon === 'Persen') {
 return $this->subtotal * ($this->appliedVoucher->nilai_diskon / 100);
 }
 return $this->appliedVoucher->nilai_diskon;
 }

 public function getTotalProperty() {
 return max(0, $this->subtotal - $this->diskon);
 }

 public function getKembalianProperty() {
 if ($this->paymentMethod !== 'Cash' || !$this->uangDiterima) return 0;
 
 $uang = (float) str_replace(['Rp', '.', ' '], '', $this->uangDiterima);
 return max(0, $uang - $this->total);
 }

 public function processPayment() {
 // Validation
 if ($this->paymentMethod === 'Cash') {
     $rawInput = trim((string) $this->uangDiterima);
     if ($rawInput === '' || $rawInput === '0') {
         $uang = $this->total;
         $this->uangDiterima = number_format($this->total, 0, ',', '.');
     } else {
         $uang = (float) str_replace(['Rp', '.', ' ', ','], '', $rawInput);
         if ($uang < $this->total) {
             session()->flash('error', 'Uang diterima (Rp ' . number_format($uang, 0, ',', '.') . ') kurang dari total bayar (Rp ' . number_format($this->total, 0, ',', '.') . '). Silakan gunakan tombol Uang Pas atau input jumlah yang sesuai.');
             return;
         }
     }
 } else {
     $this->uangDiterima = number_format($this->total, 0, ',', '.');
 }

 $user = auth()->user();
 $isOwner = $user && (
     $user->role === 'Pemilik' || 
     $user->role === 'Admin' || 
     (method_exists($user, 'hasRole') && ($user->hasRole('Pemilik') || $user->hasRole('Admin'))) ||
     str_contains(strtolower($user->role ?? ''), 'pemilik') ||
     str_contains(strtolower($user->role ?? ''), 'admin') ||
     $user->id === 1 || 
     $user->id === 2
 );
 if (!$isOwner && $this->antrean->kapster_id && (int)$this->antrean->kapster_id !== (int)auth()->id()) {
     session()->flash('error', 'Akses ditolak: Anda tidak memiliki wewenang untuk memproses transaksi kasir milik kapster lain.');
     return;
 }

 DB::beginTransaction();
 try {
 // Create or update Transaksi
 $transaksi = Transaksi::updateOrCreate(
 ['antrean_id' => $this->antrean->id],
 [
 'kasir_id' => auth()->id() ?? 1,
 'voucher_id' => $this->appliedVoucher ? $this->appliedVoucher->id : null,
 'subtotal' => $this->subtotal,
 'total_diskon' => $this->diskon,
 'total_akhir' => $this->total,
 'metode_pembayaran' => $this->paymentMethod,
 'status_bayar' => 'Lunas',
 'tanggal_bayar' => now()
 ]
 );

 // Create Detail & Update Stok
 DetailTransaksi::where('transaksi_id', $transaksi->id)->delete();
 foreach ($this->cart as $item) {
 DetailTransaksi::create([
 'transaksi_id' => $transaksi->id,
 'tipe_item' => $item['type'],
 'item_id' => $item['id'],
 'nama_item_snapshot' => $item['name'],
 'qty' => $item['qty'],
 'harga_satuan_snapshot' => $item['price'],
 'subtotal' => $item['price'] * $item['qty']
 ]);

 // Update Stock if retail
 if ($item['type'] === 'Barang') {
 $barang = Barang::find($item['id']);
 if ($barang) {
 $stokAkhir = max(0, $barang->stok_sekarang - $item['qty']);
 $barang->update(['stok_sekarang' => $stokAkhir]);

 LogStok::create([
 'barang_id' => $barang->id,
 'pencatat_id' => auth()->id() ?? 1,
 'referensi_transaksi_id' => $transaksi->id,
 'tipe_log' => 'OtomatisPOS',
 'qty_perubahan' => -$item['qty'],
 'stok_akhir' => $stokAkhir,
 'keterangan' => 'Penjualan via POS Transaksi #' . $transaksi->id
 ]);
 }
 }
 }

 // Update Antrean Status & Selesai Aktual
 $this->antrean->update([
 'status' => 'Selesai',
 'waktu_selesai_aktual' => now()->format('H:i:s')
 ]);

 DB::commit();

 // Sinkronisasi otomatis ke Google Spreadsheet (hanya tabel barang)
 try {
 $sheets = app(\App\Services\GoogleSheetsService::class);
 $sheets->syncTable('barang');
 } catch (\Throwable $e) {}
 
 session()->flash('message', 'Pembayaran transaksi #RNC-' . str_pad($this->antrean->id, 5, '0', STR_PAD_LEFT) . ' berhasil diverifikasi dan diselesaikan!');

 if (auth()->user()?->role === 'Kapster') {
 return redirect()->route('staff.dashboard');
 }
 return redirect()->route('admin.antrean');

 } catch (\Exception $e) {
 DB::rollBack();
 session()->flash('error', 'Terjadi kesalahan: ' . $e->getMessage());
 }
 }
};
?>

<div class="flex flex-col w-full h-full relative">
 <div class="pb-6 pt-2 flex items-center justify-between z-10 sticky top-0 bg-surface-dim/80 backdrop-blur-md">
 <a href="{{ auth()->user()?->role === 'Kapster' ? route('staff.dashboard') : route('admin.antrean') }}" class="flex items-center text-on-surface-variant hover:text-primary transition-colors group">
 <span class="material-symbols-outlined mr-2 group-hover:-translate-x-1 transition-transform">arrow_back</span>
 <span class="font-label-md text-label-md">Kembali ke {{ auth()->user()?->role === 'Kapster' ? 'Dashboard Kapster' : 'Antrean' }}</span>
 </a>
 <div class="flex items-center gap-4">
 <div class="px-4 py-2 bg-surface-container rounded-full shadow-sm flex items-center">
 <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>
 <span class="font-label-sm text-label-sm text-on-surface">Sistem POS Online</span>
 </div>
 </div>
 </div>

 @if(session('error'))
 <div class="bg-error-container text-on-error-container p-4 rounded-lg mb-6">
 {{ session('error') }}
 </div>
 @endif

 <div class="pb-16 flex-1 flex flex-col lg:flex-row gap-5 lg:gap-5">
 <div class="flex-1 flex flex-col gap-4 lg:gap-5">
 <!-- Order Details -->
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl shadow-lg relative overflow-hidden">
 <div class="absolute -right-12 -top-12 w-12 h-12 bg-primary/5 rounded-full blur-3xl"></div>
 <div class="flex justify-between items-start mb-6">
 <div>
 <p class="font-label-sm text-label-sm text-primary tracking-widest uppercase mb-1">Detail Pesanan</p>
 <h2 class="font-headline-md text-on-surface text-headline-md">{{ $antrean->nomor_antrean }}</h2>
 </div>
 <div class="bg-primary-container/20 text-primary-container px-3 py-1 rounded-full flex items-center">
 <span class="material-symbols-outlined text-[16px] mr-1">schedule</span>
 <span class="font-label-sm text-label-sm">Menunggu Pembayaran</span>
 </div>
 </div>
 <div class="grid grid-cols-2 gap-4">
 <div class="flex items-center gap-4">
 <div class="w-12 h-12 rounded-full bg-surface-variant flex items-center justify-center text-on-surface-variant shrink-0">
 <span class="material-symbols-outlined">person</span>
 </div>
 <div>
 <p class="font-label-sm text-label-sm text-on-surface-variant">Pelanggan</p>
 <p class="font-body-md text-body-md text-on-surface font-semibold">{{ optional($antrean->pelanggan)->nama ?? 'Pelanggan Umum' }}</p>
 </div>
 </div>
 <div class="flex items-center gap-4">
 <div class="w-12 h-12 rounded-full bg-surface-variant flex items-center justify-center text-on-surface-variant shrink-0">
 <span class="material-symbols-outlined">content_cut</span>
 </div>
 <div>
 <p class="font-label-sm text-label-sm text-on-surface-variant">Kapster</p>
 <p class="font-body-md text-body-md text-on-surface font-semibold">{{ $antrean->kapster->nama }}</p>
 </div>
 </div>
 </div>
 </div>

 <!-- Cart -->
 <div class="bg-surface-container rounded-xl shadow-lg flex flex-col flex-1 overflow-hidden">
 <div class="p-4 lg:p-5 pb-0 flex justify-between items-center relative z-20">
 <h3 class="font-headline-md text-[20px] text-on-surface text-headline-md">Keranjang Belanja</h3>
 <div class="relative">
 <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
 <input wire:model.live="searchQuery" class="bg-surface-dim text-on-surface font-body-md rounded-lg pl-10 pr-4 py-2 w-12 focus:outline-none focus:ring-1 focus:ring-primary shadow-inner" placeholder="Tambah Produk Retail..." type="text">
 
 <!-- Search Results Dropdown -->
 @if(!empty($searchResults))
 <div class="absolute right-0 top-full mt-2 w-full bg-surface-container-high border border-surface-variant rounded-lg shadow-xl overflow-hidden z-50">
 @foreach($searchResults as $res)
 <button wire:click="addProduct({{ $res->id }})" class="w-full text-left px-4 py-3 hover:bg-surface-variant transition-colors flex justify-between items-center border-b border-surface-variant/50 last:border-0">
 <div>
 <p class="text-on-surface font-label-md">{{ $res->nama_barang }}</p>
 <p class="text-on-surface-variant text-[11px]">Stok: {{ $res->stok_sekarang }}</p>
 </div>
 <span class="text-primary font-label-sm">Rp {{ number_format($res->harga_jual,0,',','.') }}</span>
 </button>
 @endforeach
 </div>
 @endif
 </div>
 </div>
 
 <div class="p-4 lg:p-5 overflow-y-auto">
 <div class="w-full text-left">
 <div class="grid grid-cols-[3fr_1fr_1fr_1fr] gap-4 mb-4 font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">
 <div>Item</div>
 <div class="text-center">Harga</div>
 <div class="text-center">Qty</div>
 <div class="text-right">Subtotal</div>
 </div>
 <div class="space-y-4">
 @foreach($cart as $index => $item)
 @if($item['type'] === 'Layanan')
 <!-- Service Item -->
 <div class="grid grid-cols-[3fr_1fr_1fr_1fr] gap-4 items-center p-4 bg-surface-variant/30 rounded-xl relative overflow-hidden group">
 <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary"></div>
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-lg bg-surface-variant flex items-center justify-center text-on-surface-variant shrink-0">
 <span class="material-symbols-outlined">content_cut</span>
 </div>
 <div>
 <p class="font-body-md text-body-md text-on-surface font-semibold truncate">{{ $item['name'] }}</p>
 <p class="font-label-sm text-label-sm text-on-surface-variant">Jasa</p>
 </div>
 </div>
 <div class="text-center font-body-md text-on-surface whitespace-nowrap">Rp {{ number_format($item['price'],0,',','.') }}</div>
 <div class="text-center">
 <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-surface-variant text-on-surface font-label-md cursor-not-allowed opacity-50">{{ $item['qty'] }}</span>
 </div>
 <div class="text-right font-body-md text-on-surface font-semibold whitespace-nowrap">Rp {{ number_format($item['price']*$item['qty'],0,',','.') }}</div>
 </div>
 @else
 <!-- Retail Item -->
 <div class="grid grid-cols-[3fr_1fr_1fr_1fr] gap-4 items-center p-4 bg-surface-dim rounded-xl shadow-inner hover:bg-surface-variant/50 transition-colors group relative">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center shrink-0">
 <span class="material-symbols-outlined text-on-surface-variant">inventory_2</span>
 </div>
 <div>
 <p class="font-body-md text-body-md text-on-surface font-semibold truncate">{{ $item['name'] }}</p>
 <p class="font-label-sm text-label-sm text-on-surface-variant">Retail</p>
 </div>
 </div>
 <div class="text-center font-body-md text-on-surface whitespace-nowrap">Rp {{ number_format($item['price'],0,',','.') }}</div>
 <div class="flex items-center justify-center gap-2">
 <button wire:click="decreaseQty({{ $index }})" class="w-6 h-6 rounded-full bg-surface-container hover:bg-primary/20 hover:text-primary text-on-surface flex items-center justify-center transition-colors">
 <span class="material-symbols-outlined text-[16px]">remove</span>
 </button>
 <span class="font-label-md text-on-surface w-4 text-center">{{ $item['qty'] }}</span>
 <button wire:click="increaseQty({{ $index }})" class="w-6 h-6 rounded-full bg-surface-container hover:bg-primary/20 hover:text-primary text-on-surface flex items-center justify-center transition-colors">
 <span class="material-symbols-outlined text-[16px]">add</span>
 </button>
 </div>
 <div class="text-right font-body-md text-on-surface font-semibold whitespace-nowrap">Rp {{ number_format($item['price']*$item['qty'],0,',','.') }}</div>
 
 <button wire:click="removeProduct({{ $index }})" class="absolute -right-8 opacity-0 group-hover:opacity-100 group-hover:right-4 top-1/2 -translate-y-1/2 text-error hover:text-error-container transition-all p-2 bg-surface-container rounded-full shadow-lg z-10">
 <span class="material-symbols-outlined text-[20px]">delete</span>
 </button>
 </div>
 @endif
 @endforeach
 </div>
 </div>
 </div>
 </div>
 </div>

 <div class="w-full lg:w-[420px] flex flex-col gap-4 lg:gap-5 shrink-0 relative">
 <div class="absolute inset-y-0 -left-4 w-px bg-gradient-to-b from-transparent via-surface-variant to-transparent hidden lg:block"></div>
 
 <div class="bg-surface-container p-4 lg:p-5 rounded-xl shadow-lg relative overflow-hidden flex flex-col gap-4 lg:gap-5">
 <div>
 <h3 class="font-headline-md text-[20px] text-on-surface mb-4 text-headline-md">Pembayaran</h3>
 <div class="flex flex-col gap-1 mb-6">
 <div class="flex gap-2">
 <input wire:model="voucherCode" class="flex-1 bg-surface-dim text-on-surface font-body-md rounded-lg px-4 py-3 focus:outline-none focus:ring-1 focus:ring-primary shadow-inner uppercase tracking-wider placeholder:normal-case placeholder:tracking-normal" placeholder="Kode Voucher" type="text">
 <button wire:click="applyVoucher" class="px-4 py-3 bg-surface-variant text-on-surface rounded-lg font-label-md hover:bg-surface-bright transition-colors shadow-sm">Terapkan</button>
 </div>
 @if(session('voucher_error')) <span class="text-error text-[12px]">{{ session('voucher_error') }}</span> @endif
 @if(session('voucher_success')) <span class="text-emerald-400 text-[12px]">{{ session('voucher_success') }}</span> @endif
 </div>
 
 <div class="space-y-3 mb-6">
 <div class="flex justify-between font-body-md text-on-surface-variant">
 <span>Subtotal ({{ collect($cart)->sum('qty') }} item)</span>
 <span>Rp {{ number_format($this->subtotal,0,',','.') }}</span>
 </div>
 <div class="flex justify-between font-body-md text-on-surface-variant">
 <span>Diskon</span>
 <span class="text-error">- Rp {{ number_format($this->diskon,0,',','.') }}</span>
 </div>
 </div>
 <div class="pt-4 border-t border-surface-variant/50 border-dashed">
 <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-1 text-center">Total Bayar</p>
 <p class="font-headline-xl text-primary text-center mb-6 drop-shadow-md text-headline-xl">Rp {{ number_format($this->total,0,',','.') }}</p>
 </div>
 </div>

 <div>
 <p class="font-label-md text-label-md text-on-surface mb-3">Metode Pembayaran</p>
 <div class="grid grid-cols-3 gap-3 mb-6">
 <button wire:click="$set('paymentMethod', 'Cash')" class="p-3 {{ $paymentMethod === 'Cash' ? 'bg-primary text-on-primary' : 'bg-surface-dim text-on-surface-variant hover:bg-surface-variant' }} rounded-xl flex flex-col items-center justify-center gap-2 shadow-md relative overflow-hidden transition-colors">
 <span class="material-symbols-outlined">payments</span>
 <span class="font-label-sm text-label-sm font-semibold">Tunai</span>
 </button>
 <button wire:click="$set('paymentMethod', 'Transfer')" class="p-3 {{ $paymentMethod === 'Transfer' ? 'bg-primary text-on-primary' : 'bg-surface-dim text-on-surface-variant hover:bg-surface-variant' }} rounded-xl flex flex-col items-center justify-center gap-2 shadow-md relative overflow-hidden transition-colors">
 <span class="material-symbols-outlined">credit_card</span>
 <span class="font-label-sm text-label-sm">Transfer</span>
 </button>
 <button wire:click="$set('paymentMethod', 'QRIS')" class="p-3 {{ $paymentMethod === 'QRIS' ? 'bg-primary text-on-primary' : 'bg-surface-dim text-on-surface-variant hover:bg-surface-variant' }} rounded-xl flex flex-col items-center justify-center gap-2 shadow-md relative overflow-hidden transition-colors">
 <span class="material-symbols-outlined">qr_code_scanner</span>
 <span class="font-label-sm text-label-sm">QRIS</span>
 </button>
 </div>

 @if($paymentMethod === 'Cash')
 <div class="bg-surface-dim p-4 rounded-xl shadow-inner mb-3 relative group">
 <div class="absolute left-0 top-0 bottom-0 w-1 bg-surface-variant group-focus-within:bg-primary transition-colors"></div>
 <div class="flex items-center justify-between mb-2">
 <label class="block font-label-sm text-label-sm text-on-surface-variant">Uang Diterima</label>
 <button type="button" wire:click="setUangPas" class="text-xs text-primary hover:underline font-semibold flex items-center gap-1 cursor-pointer">
 <span class="material-symbols-outlined text-xs">done_all</span> Uang Pas
 </button>
 </div>
 <div class="flex items-center">
 <span class="text-on-surface-variant font-headline-md mr-2 text-headline-md">Rp</span>
 <input wire:model.live="uangDiterima" class="w-full bg-transparent text-on-surface font-headline-md focus:outline-none text-headline-md" type="text" placeholder="{{ number_format($this->total,0,',','.') }}">
 </div>
 </div>

 <div class="grid grid-cols-4 gap-2 mb-4">
 <button type="button" wire:click="setUangPas" class="py-1.5 px-2 bg-surface-container hover:bg-primary hover:text-on-primary text-xs font-semibold rounded-lg border border-outline-variant/30 transition-colors text-center cursor-pointer">
 Uang Pas
 </button>
 <button type="button" wire:click="setUangCepat(50000)" class="py-1.5 px-2 bg-surface-container hover:bg-primary hover:text-on-primary text-xs font-semibold rounded-lg border border-outline-variant/30 transition-colors text-center cursor-pointer">
 50.000
 </button>
 <button type="button" wire:click="setUangCepat(100000)" class="py-1.5 px-2 bg-surface-container hover:bg-primary hover:text-on-primary text-xs font-semibold rounded-lg border border-outline-variant/30 transition-colors text-center cursor-pointer">
 100.000
 </button>
 <button type="button" wire:click="setUangCepat(200000)" class="py-1.5 px-2 bg-surface-container hover:bg-primary hover:text-on-primary text-xs font-semibold rounded-lg border border-outline-variant/30 transition-colors text-center cursor-pointer">
 200.000
 </button>
 </div>

 <div class="flex justify-between items-center mb-6 px-2">
 <span class="font-label-md text-label-md text-on-surface-variant">Kembalian</span>
 <span class="font-headline-md text-emerald-400 text-headline-md">Rp {{ number_format($this->kembalian,0,',','.') }}</span>
 </div>
 @endif

 <button wire:click="processPayment" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white p-4 rounded-xl shadow-xl hover:shadow-emerald-500/20 transition-all flex flex-col items-center justify-center group relative overflow-hidden">
 <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
 <div class="flex items-center gap-2 mb-1 relative z-10">
 <span class="material-symbols-outlined group-hover:-translate-y-1 transition-transform">check_circle</span>
 <span class="font-label-md text-label-md text-[16px] uppercase tracking-wider">Verifikasi &amp; Simpan</span>
 </div>
 <span class="font-label-sm text-label-sm text-white/70 relative z-10">Transaksi akan disimpan ke sistem</span>
 </button>
 </div>
 </div>
 </div>
 </div>
</div>
