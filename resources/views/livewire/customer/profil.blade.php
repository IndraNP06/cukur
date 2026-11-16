<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use App\Models\User;

new #[Layout('components.layouts.admin')] #[Title('Akun Saya - Ran CukurIn')] class extends Component {
 public string $name = '';
 public string $email = '';
 public string $no_hp = '';

 public string $current_password = '';
 public string $password = '';
 public string $password_confirmation = '';

 public function mount()
 {
 $user = auth()->user();
 $this->name = $user->nama;
 $this->email = $user->email;
 $this->no_hp = $user->no_hp ?? '';
 }

 public function updateProfile()
 {
 $user = auth()->user();

 $this->validate([
 'name' => ['required', 'string', 'max:255'],
 'no_hp' => ['nullable', 'string', 'max:20'],
 ]);

 $user->fill([
 'nama' => $this->name,
 'no_hp' => $this->no_hp,
 ]);

 $user->save();

 session()->flash('profile_status', 'Profil berhasil diperbarui.');
 }

 public function updatePassword()
 {
 $this->validate([
 'current_password' => ['required', 'current_password'],
 'password' => ['required', 'confirmed', Rules\Password::defaults()],
 ]);

 auth()->user()->update([
 'password' => Hash::make($this->password),
 ]);

 $this->reset(['current_password', 'password', 'password_confirmation']);
 session()->flash('password_status', 'Kata sandi berhasil diperbarui.');
 }
};
?>

 <div class="flex-grow flex flex-col bg-background font-body-md text-on-surface antialiased w-full">

 <div class="flex flex-col w-full max-w-4xl mx-auto h-full px-4 lg:px-8 py-6 lg:py-10 pb-12 lg:pb-16">
 <div class="flex items-center gap-3 mb-8 lg:mb-10">
 <span class="material-symbols-outlined text-primary text-xl">manage_accounts</span>
 <h1 class="font-headline-lg md:text-headline-md text-on-surface tracking-tight text-headline-lg">Akun Saya</h1>
 </div>
 
 <div class="flex flex-col lg:flex-row gap-5 lg:gap-5 lg:gap-12 relative w-full">
 
 <!-- Right Column: Main Content Area -->
 <div class="flex-1 flex flex-col gap-5 lg:gap-5 min-w-0">
 <!-- Section 1: Informasi Data Diri -->
 <section class="bg-surface-container rounded-xl shadow-md p-4 lg:p-5 md:p-5 lg:p-5 flex flex-col gap-5 lg:gap-5">
 <div>
 <h3 class="font-headline-md text-on-surface mb-2 text-headline-md">Informasi Data Diri</h3>
 <p class="font-body-md text-body-md text-on-surface-variant opacity-80">Perbarui profil dan detail kontak Anda untuk pengalaman yang lebih baik.</p>
 </div>
 
 @if (session('profile_status'))
 <div class="p-4 bg-emerald-500/10 text-emerald-500 rounded-xl">
 {{ session('profile_status') }}
 </div>
 @endif
 
 <!-- Form Grid -->
 <form wire:submit="updateProfile" class="flex flex-col gap-4 lg:gap-5">
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-5">
 <div class="flex flex-col gap-2">
 <label class="font-label-md text-label-md text-on-surface" for="name">Nama Lengkap</label>
 <div class="relative">
 <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">person</span>
 <input wire:model="name" class="w-full bg-surface-container-lowest border border-outline-variant/30 text-on-surface rounded-lg pl-10 pr-4 py-3 focus:outline-none focus:border-primary transition-colors font-body-md text-body-md shadow-sm" id="name" type="text" required>
 </div>
 @error('name') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <div class="flex flex-col gap-2">
 <label class="font-label-md text-label-md text-on-surface" for="no_hp">Nomor Telepon / WhatsApp</label>
 <div class="relative">
 <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">phone_iphone</span>
 <input wire:model="no_hp" class="w-full bg-surface-container-lowest border border-outline-variant/30 text-on-surface rounded-lg pl-10 pr-4 py-3 focus:outline-none focus:border-primary transition-colors font-body-md text-body-md shadow-sm" id="no_hp" type="tel">
 </div>
 @error('no_hp') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 </div>
 
 <div class="flex flex-col gap-2">
 <label class="font-label-md text-label-md text-on-surface" for="email">Alamat Email</label>
 <div class="relative">
 <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">mail</span>
 <input wire:model="email" class="w-full bg-surface-container-highest/50 border border-outline-variant/10 text-on-surface-variant rounded-lg pl-10 pr-12 py-3 cursor-not-allowed font-body-md text-body-md" disabled id="email" type="email">
 <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-primary" title="Email Terverifikasi">check_circle</span>
 </div>
 </div>
 
 <div class="flex justify-end pt-4">
 <button type="submit" class="bg-primary text-on-primary px-5 lg:px-5 py-3 rounded-full font-label-md text-label-md uppercase tracking-wider hover:scale-105 transition-transform shadow-md">Simpan Perubahan</button>
 </div>
 </form>
 </section>
 
 <!-- Section 2: Ubah Kata Sandi -->
 <section class="bg-surface-container rounded-xl shadow-md p-4 lg:p-5 md:p-5 lg:p-5 flex flex-col gap-5 lg:gap-5 relative overflow-hidden">
 <div class="absolute -right-20 -top-20 w-12 h-12 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
 <div class="relative z-10">
 <h3 class="font-headline-md text-on-surface mb-2 text-headline-md">Ubah Kata Sandi</h3>
 <p class="font-body-md text-body-md text-on-surface-variant opacity-80">Pastikan akun Anda tetap aman dengan menggunakan kata sandi yang kuat.</p>
 </div>
 
 @if (session('password_status'))
 <div class="p-4 bg-emerald-500/10 text-emerald-500 rounded-xl relative z-10">
 {{ session('password_status') }}
 </div>
 @endif

 <form wire:submit="updatePassword" class="flex flex-col gap-4 lg:gap-5 relative z-10" x-data="{ show1: false, show2: false, show3: false }">
 <div class="flex flex-col gap-2">
 <label class="font-label-md text-label-md text-on-surface" for="current_password">Kata Sandi Saat Ini</label>
 <div class="relative w-full md:w-2/3">
 <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">lock</span>
 <input wire:model="current_password" x-bind:type="show1 ? 'text' : 'password'" class="w-full bg-surface-container-lowest border border-outline-variant/30 text-on-surface rounded-lg pl-10 pr-10 py-3 focus:outline-none focus:border-primary transition-colors font-body-md text-body-md shadow-sm" id="current_password" placeholder="Masukkan kata sandi saat ini" required>
 <button type="button" @click="show1 = !show1" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-on-surface transition-colors">
 <span class="material-symbols-outlined text-[20px]" x-text="show1 ? 'visibility_off' : 'visibility'">visibility_off</span>
 </button>
 </div>
 @error('current_password') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <div class="flex flex-col gap-2">
 <label class="font-label-md text-label-md text-on-surface" for="password">Kata Sandi Baru</label>
 <div class="relative w-full md:w-2/3">
 <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">key</span>
 <input wire:model="password" x-bind:type="show2 ? 'text' : 'password'" class="w-full bg-surface-container-lowest border border-outline-variant/30 text-on-surface rounded-lg pl-10 pr-10 py-3 focus:outline-none focus:border-primary transition-colors font-body-md text-body-md shadow-sm" id="password" placeholder="Minimal 8 karakter" required>
 <button type="button" @click="show2 = !show2" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-on-surface transition-colors">
 <span class="material-symbols-outlined text-[20px]" x-text="show2 ? 'visibility_off' : 'visibility'">visibility_off</span>
 </button>
 </div>
 @error('password') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <div class="flex flex-col gap-2">
 <label class="font-label-md text-label-md text-on-surface" for="password_confirmation">Konfirmasi Kata Sandi Baru</label>
 <div class="relative w-full md:w-2/3">
 <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">check_circle</span>
 <input wire:model="password_confirmation" x-bind:type="show3 ? 'text' : 'password'" class="w-full bg-surface-container-lowest border border-outline-variant/30 text-on-surface rounded-lg pl-10 pr-10 py-3 focus:outline-none focus:border-primary transition-colors font-body-md text-body-md shadow-sm" id="password_confirmation" placeholder="Ulangi kata sandi baru" required>
 <button type="button" @click="show3 = !show3" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-on-surface transition-colors">
 <span class="material-symbols-outlined text-[20px]" x-text="show3 ? 'visibility_off' : 'visibility'">visibility_off</span>
 </button>
 </div>
 @error('password_confirmation') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <div class="flex justify-start pt-4">
 <button type="submit" class="bg-surface-container-highest text-on-surface px-5 lg:px-5 py-3 rounded-full font-label-md text-label-md uppercase tracking-wider hover:bg-surface-bright transition-colors shadow-sm">Perbarui Kata Sandi</button>
 </div>
 </form>
 </section>
 </div>
 </div>
 </div>
</div>
