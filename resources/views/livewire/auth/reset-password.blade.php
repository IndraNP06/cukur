<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Buat Kata Sandi Baru - Ran CukurIn')] class extends Component {
 public string $email = '';
 public string $password = '';
 public string $password_confirmation = '';
 public string $token = '';

 public function mount($token)
 {
 $this->email = request()->query('email', '');
 $this->token = $token;
 }

    public function resetPassword()
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::broker()->reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();

                // Sinkronkan hash kata sandi baru ke Google Spreadsheet
                try {
                    app(\App\Services\GoogleSheetsService::class)->updateRow('pengguna', $user->id, [
                        'password' => $user->password,
                    ]);
                } catch (\Throwable $e) {}
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            $user = \App\Models\User::where('email', $this->email)->first();
            if ($user) {
                \Illuminate\Support\Facades\Auth::login($user, true);
                session()->regenerate();
                session()->flash('status', 'Kata sandi berhasil diatur ulang! Selamat datang kembali.');

                if ($user->role === 'Pelanggan' && ! $user->hasVerifiedEmail()) {
                    $this->redirect(route('verification.notice', absolute: false));
                    return;
                }

                $this->redirectIntended(route('dashboard'));
                return;
            }

            session()->flash('status', 'Kata sandi Anda berhasil diperbarui! Silakan masuk dengan kata sandi baru Anda.');
            $this->redirect(route('login', absolute: false));
        } else {
            $this->addError('email', 'Token reset kata sandi tidak valid atau telah kedaluwarsa.');
        }
    }
};
?>

<div class="flex-grow flex flex-col bg-background font-body-md text-on-surface antialiased">
 <div class="flex flex-col w-full h-full flex-grow min-h-[calc(100vh-5rem)]">
 <div class="flex flex-col w-full flex-grow md:flex-row bg-surface">
 <div class="hidden md:flex relative w-full md:w-1/2 h-full bg-surface-container overflow-hidden group">
 <div class="absolute inset-0 bg-cover bg-center transition-transform duration-1000 group-hover:scale-105" style="background-image: url('https://images.unsplash.com/photo-1503951914875-452162b0f3f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');"></div>
 <div class="absolute inset-0 bg-gradient-to-t from-surface via-surface/80 to-transparent"></div>
 <div class="absolute inset-0 bg-surface/20 mix-blend-multiply"></div>
 <div class="relative z-10 flex flex-col justify-end p-12 lg:p-16 h-full text-left">
 <div class="inline-flex items-center gap-3 mb-6">
 <div class="bg-primary p-2.5 rounded-lg shadow-[0_0_20px_rgba(255,193,116,0.3)]">
 <span class="material-symbols-outlined text-on-primary text-[28px] block">content_cut</span>
 </div>
 <span class="font-headline-md tracking-tight text-on-surface text-headline-md">Ran <span class="text-primary">CukurIn</span></span>
 </div>
 <div class="w-12 h-1 bg-primary mb-8 rounded-full"></div>
 <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md leading-relaxed">
 Satu langkah lagi. Amankan akun Anda dan kembali nikmati kemudahan reservasi tanpa antre.
 </p>
 </div>
 </div>
 
 <div class="flex-1 flex items-center justify-center p-4 lg:p-5 sm:p-12 h-full overflow-y-auto bg-surface relative">
 <div class="absolute top-0 right-0 w-56 h-56 bg-primary/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>
 <div class="absolute bottom-0 left-0 w-12 h-12 bg-primary/5 rounded-full blur-3xl translate-y-1/3 -translate-x-1/4 pointer-events-none md:hidden"></div>
 
 <div class="w-full max-w-[420px] relative z-10">
 <div class="md:hidden flex items-center gap-3 mb-10 justify-center">
 <div class="bg-primary p-2 rounded-lg">
 <span class="material-symbols-outlined text-on-primary">content_cut</span>
 </div>
 <span class="font-headline-md tracking-tight text-on-surface text-headline-md">Ran <span class="text-primary">CukurIn</span></span>
 </div>
 
 <div class="mb-10 text-center md:text-left">
 <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-surface-container border border-outline-variant/30 mb-6 shadow-md text-primary">
 <span class="material-symbols-outlined text-[24px]">lock_reset</span>
 </div>
 <h1 class="font-headline-lg md:text-headline-md text-on-surface mb-3 tracking-tight text-headline-lg">Buat Kata Sandi Baru</h1>
 <p class="font-body-md text-body-md text-on-surface-variant">
 Silakan masukkan kata sandi baru Anda untuk akun <br class="hidden md:block">
 <span class="text-primary font-medium tracking-wide">{{ $email }}</span>
 </p>
 </div>

 <form wire:submit="resetPassword" class="space-y-6">
 @if ($errors->any())
 <div class="p-4 bg-error-container text-on-error-container rounded-xl text-sm">
 <ul class="list-disc pl-5">
 @foreach ($errors->all() as $error)
 <li>{{ $error }}</li>
 @endforeach
 </ul>
 </div>
 @endif

 <input type="hidden" wire:model="token">
 <input type="hidden" wire:model="email">
 
 <div class="space-y-2 group" x-data="{ show: false }">
 <label class="block font-label-md text-label-md text-on-surface-variant uppercase tracking-wider" for="password">Kata Sandi Baru</label>
 <div class="relative">
 <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
 <span class="material-symbols-outlined text-on-surface-variant/50 group-focus-within:text-primary transition-colors">lock</span>
 </div>
 <input wire:model="password" x-bind:type="show ? 'text' : 'password'" class="block w-full pl-11 pr-12 py-3.5 bg-surface-container border border-outline-variant/50 rounded-xl font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/30 focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none transition-all duration-200 shadow-sm" id="password" placeholder="••••••••" required autofocus>
 <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-on-surface-variant/50 hover:text-on-surface focus:outline-none transition-colors">
 <span class="material-symbols-outlined text-[20px]" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
 </button>
 </div>
 </div>
 
 <div class="space-y-2 group" x-data="{ show: false }">
 <label class="block font-label-md text-label-md text-on-surface-variant uppercase tracking-wider" for="password_confirmation">Konfirmasi Kata Sandi Baru</label>
 <div class="relative">
 <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
 <span class="material-symbols-outlined text-on-surface-variant/50 group-focus-within:text-primary transition-colors">password</span>
 </div>
 <input wire:model="password_confirmation" x-bind:type="show ? 'text' : 'password'" class="block w-full pl-11 pr-12 py-3.5 bg-surface-container border border-outline-variant/50 rounded-xl font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/30 focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none transition-all duration-200 shadow-sm" id="password_confirmation" placeholder="••••••••" required>
 <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-on-surface-variant/50 hover:text-on-surface focus:outline-none transition-colors">
 <span class="material-symbols-outlined text-[20px]" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
 </button>
 </div>
 </div>
 
 <div class="pt-4">
 <button type="submit" class="w-full flex items-center justify-center py-4 px-4 lg:px-5 bg-primary text-on-primary rounded-xl font-label-md text-label-md uppercase tracking-widest hover:bg-primary-container focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-surface transition-all duration-300 hover:-translate-y-0.5 shadow-lg shadow-primary/20 group relative overflow-hidden">
 <span class="relative z-10 flex items-center gap-2">
 Simpan Kata Sandi Baru
 <span class="material-symbols-outlined text-[20px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
 </span>
 </button>
 </div>
 </form>
 
 <div class="mt-8 text-center">
 <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors group px-4 py-2 rounded-lg hover:bg-surface-container">
 <span class="material-symbols-outlined text-[20px] group-hover:-translate-x-1 transition-transform">arrow_back</span>
 Kembali ke Halaman Masuk
 </a>
 </div>
 </div>
 </div>
 </div>
 </div>
</div>