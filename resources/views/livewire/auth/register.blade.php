<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Daftar Akun Baru - Ran CukurIn')] class extends Component {
 public string $name = '';
 public string $email = '';
 public string $password = '';
 public string $password_confirmation = '';
 public bool $terms = false;

 public function register()
 {
 $validated = $this->validate([
 'name' => ['required', 'string', 'max:255'],
 'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
 'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
 'terms' => ['accepted']
 ], [
 'terms.accepted' => 'Anda harus menyetujui syarat & ketentuan.'
 ]);

 $user = User::create([
 'nama' => $this->name,
 'email' => $this->email,
 'password' => Hash::make($this->password),
 'role' => 'Pelanggan',
 ]);
 
 try {
     $user->assignRole('Pelanggan');
 } catch (\Throwable $e) {}

 try {
     app(\App\Services\GoogleSheetsService::class)->appendRow('pengguna', [
         'id' => $user->id,
         'nama' => $user->nama,
         'email' => $user->email,
         'password' => $user->password,
         'role' => 'Pelanggan',
         'created_at' => now()->toDateTimeString(),
     ]);
 } catch (\Throwable $e) {}

 event(new Registered($user));

 Auth::login($user);

 $this->redirect(route('verification.notice', absolute: false));
 }
};
?>

<div class="flex-grow flex flex-col bg-background font-body-md text-on-surface antialiased">
 <div class="flex flex-col w-full h-full flex-grow min-h-[calc(100vh-5rem)]">
 <div class="flex flex-col w-full h-full flex-grow">
 <div class="flex-1 flex flex-col lg:flex-row w-full h-full overflow-hidden">
 <!-- Left Column (Visual Branding) -->
 <div class="hidden lg:flex lg:w-1/2 relative bg-surface-container-lowest">
 <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1622286342621-4bd786c2447c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');"></div>
 <div class="absolute inset-0 bg-gradient-to-t from-surface-dim via-surface-dim/80 to-transparent"></div>
 <div class="relative z-10 flex flex-col justify-end p-margin-desktop w-full h-full">
 <div class="max-w-md">
 <div class="flex items-center gap-3 mb-6">
 <div class="bg-primary p-3 rounded-xl shadow-lg">
 <span class="material-symbols-outlined text-on-primary text-xl" style="font-variation-settings: 'FILL' 1;">content_cut</span>
 </div>
 <span class="font-headline-xl tracking-tight text-on-surface text-headline-xl">Ran <span class="text-primary">CukurIn</span></span>
 </div>
 <p class="font-body-lg text-body-lg text-on-surface-variant leading-relaxed">
 "Langkah pertama menuju gaya rambut sempurna. Pesan jadwal Anda dalam hitungan detik."
 </p>
 <div class="mt-12 flex gap-4">
 <div class="w-12 h-1 bg-primary rounded-full"></div>
 <div class="w-2 h-1 bg-surface-variant rounded-full"></div>
 <div class="w-2 h-1 bg-surface-variant rounded-full"></div>
 </div>
 </div>
 </div>
 </div>
 
 <!-- Right Column (Registration Form) -->
 <div class="w-full lg:w-1/2 flex items-center justify-center p-margin-mobile lg:p-margin-desktop bg-surface-dim relative overflow-y-auto py-12">
 <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[100px] pointer-events-none"></div>
 <div class="w-full max-w-md relative z-10">
 <div class="flex lg:hidden items-center justify-center gap-3 mb-10">
 <div class="bg-primary p-2 rounded-lg shadow-md">
 <span class="material-symbols-outlined text-on-primary text-xl" style="font-variation-settings: 'FILL' 1;">content_cut</span>
 </div>
 <span class="font-headline-lg-mobile tracking-tight text-on-surface text-headline-lg-mobile">Ran <span class="text-primary">CukurIn</span></span>
 </div>
 
 <div class="mb-10 text-center lg:text-left">
 <h1 class="font-headline-lg text-on-surface mb-3 text-headline-lg">Daftar Akun Baru</h1>
 <p class="font-body-md text-body-md text-on-surface-variant">
 Bergabunglah dengan Ran CukurIn untuk pengalaman reservasi cukur yang cepat dan bebas antre.
 </p>
 </div>
 
 <form wire:submit="register" class="space-y-6">
 @if ($errors->any())
 <div class="p-4 bg-error-container text-on-error-container rounded-xl text-sm">
 <ul class="list-disc pl-5">
 @foreach ($errors->all() as $error)
 <li>{{ $error }}</li>
 @endforeach
 </ul>
 </div>
 @endif

 <!-- Nama Lengkap -->
 <div class="space-y-2 group">
 <label class="block font-label-md text-label-md text-on-surface group-focus-within:text-primary transition-colors" for="name">Nama Lengkap</label>
 <div class="relative">
 <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
 <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-primary transition-colors">person</span>
 </span>
 <input wire:model="name" class="w-full pl-12 pr-4 py-3 bg-surface-container-low text-on-surface font-body-md text-body-md rounded-xl border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none transition-all placeholder:text-on-surface-variant/50 shadow-sm" id="name" type="text" placeholder="Masukkan nama lengkap Anda" required autofocus>
 </div>
 </div>
 
 <!-- Alamat Email -->
 <div class="space-y-2 group">
 <label class="block font-label-md text-label-md text-on-surface group-focus-within:text-primary transition-colors" for="email">Alamat Email</label>
 <div class="relative">
 <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
 <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-primary transition-colors">mail</span>
 </span>
 <input wire:model="email" class="w-full pl-12 pr-4 py-3 bg-surface-container-low text-on-surface font-body-md text-body-md rounded-xl border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none transition-all placeholder:text-on-surface-variant/50 shadow-sm" id="email" type="email" placeholder="nama@email.com" required>
 </div>
 <p class="font-label-sm text-label-sm text-on-surface-variant/70 pl-1">* Pastikan email aktif dan belum terdaftar.</p>
 </div>
 
 <!-- Kata Sandi -->
 <div class="space-y-2 group" x-data="{ show: false }">
 <label class="block font-label-md text-label-md text-on-surface group-focus-within:text-primary transition-colors" for="password">Kata Sandi</label>
 <div class="relative">
 <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
 <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-primary transition-colors">lock</span>
 </span>
 <input wire:model="password" x-bind:type="show ? 'text' : 'password'" class="w-full pl-12 pr-12 py-3 bg-surface-container-low text-on-surface font-body-md text-body-md rounded-xl border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none transition-all placeholder:text-on-surface-variant/50 shadow-sm" id="password" type="password" placeholder="••••••••" required>
 <button @click="show = !show" type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-on-surface-variant hover:text-on-surface focus:outline-none">
 <span class="material-symbols-outlined" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
 </button>
 </div>
 <p class="font-label-sm text-label-sm text-on-surface-variant/70 pl-1">* Minimal 8 karakter.</p>
 </div>
 
 <!-- Konfirmasi Kata Sandi -->
 <div class="space-y-2 group" x-data="{ show: false }">
 <label class="block font-label-md text-label-md text-on-surface group-focus-within:text-primary transition-colors" for="password_confirmation">Konfirmasi Kata Sandi</label>
 <div class="relative">
 <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
 <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-primary transition-colors">lock_clock</span>
 </span>
 <input wire:model="password_confirmation" x-bind:type="show ? 'text' : 'password'" class="w-full pl-12 pr-12 py-3 bg-surface-container-low text-on-surface font-body-md text-body-md rounded-xl border border-outline-variant focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none transition-all placeholder:text-on-surface-variant/50 shadow-sm" id="password_confirmation" type="password" placeholder="••••••••" required>
 <button @click="show = !show" type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-on-surface-variant hover:text-on-surface focus:outline-none">
 <span class="material-symbols-outlined" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
 </button>
 </div>
 </div>
 
 <!-- Terms & Conditions -->
 <div class="flex items-start gap-3 pt-2">
 <div class="flex items-center h-5 mt-0.5">
 <input wire:model="terms" class="w-5 h-5 rounded border-outline-variant bg-surface-container-low text-primary focus:ring-primary focus:ring-offset-surface-dim transition-colors cursor-pointer accent-primary" id="terms" type="checkbox" required>
 </div>
 <label class="font-label-md text-label-md text-on-surface-variant cursor-pointer" for="terms">
 Saya setuju dengan <a class="text-primary hover:underline hover:text-primary-fixed transition-colors" href="javascript:alert('Halaman sedang dalam pengembangan')" >Syarat &amp; Ketentuan</a> serta <a class="text-primary hover:underline hover:text-primary-fixed transition-colors" href="javascript:alert('Halaman sedang dalam pengembangan')" >Kebijakan Privasi</a> yang berlaku.
 </label>
 </div>
 
 <!-- Submit Button -->
 <button type="submit" class="w-full py-4 mt-4 bg-primary hover:bg-primary-fixed text-on-primary font-headline-md rounded-xl shadow-[0_4px_14px_0_rgba(245,158,11,0.39)] hover:shadow-[0_6px_20px_rgba(245,158,11,0.23)] hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center gap-2 group text-headline-md">
 Daftar Sekarang
 <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
 </button>
 </form>
 
 <div class="mt-8 text-center">
 <p class="font-body-md text-body-md text-on-surface-variant">
 Sudah punya akun? 
 <a href="{{ route('login') }}" class="font-label-md text-label-md text-primary hover:text-primary-fixed hover:underline transition-colors ml-1">Masuk di sini</a>
 </p>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
</div>