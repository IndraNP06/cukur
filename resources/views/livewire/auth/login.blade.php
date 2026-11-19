<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Session;

new #[Layout('components.layouts.app')] #[Title('Masuk - Ran CukurIn')] class extends Component {
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login()
    {
        $credentials = $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $this->remember)) {
            Session::regenerate();

            $user = Auth::user();
            if ($user && $user->role === 'Pelanggan' && ! $user->hasVerifiedEmail()) {
                $this->redirect(route('verification.notice', absolute: false));
                return;
            }
            
            $this->redirectIntended(route('dashboard'));
        } else {
            throw ValidationException::withMessages([
                'email' => 'Kredensial yang diberikan tidak cocok dengan catatan kami.',
            ]);
        }
    }
};
?>

<div class="flex-grow flex flex-col bg-background font-body-md text-on-surface antialiased">
    <div class="flex-grow w-full grid grid-cols-1 md:grid-cols-2 min-h-[calc(100vh-5rem)]">
        <!-- Left Column: Branding & Imagery -->
        <div class="relative hidden md:flex flex-col justify-end p-margin-desktop bg-surface-dim overflow-hidden shadow-2xl z-10" style="background-image: url('https://images.unsplash.com/photo-1585747860715-2ba37e788b70?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center;">
            <div class="absolute inset-0 bg-gradient-to-t from-surface-dim via-surface-dim/50 to-transparent"></div>
            <div class="relative z-20 max-w-lg mb-8 lg:mb-10">
                <div class="flex items-center gap-3 mb-8">
                    <div class="bg-primary p-3 rounded-xl shadow-lg shadow-primary/20">
                        <span class="material-symbols-outlined text-on-primary text-xl">content_cut</span>
                    </div>
                    <h1 class="font-headline-xl text-on-surface tracking-tighter text-headline-xl">Ran <span class="text-primary">CukurIn</span></h1>
                </div>
                <p class="font-body-lg text-body-lg text-on-surface-variant/90 leading-relaxed max-w-md backdrop-blur-sm bg-surface-dim/30 p-4 lg:p-5 rounded-2xl border-l-4 border-primary">
                    "Kembali tampil maksimal. Waktu Anda terlalu berharga untuk dihabiskan dalam antrean."
                </p>
                <div class="mt-12 flex gap-4">
                    <div class="w-12 h-1 bg-primary rounded-full"></div>
                    <div class="w-2 h-1 bg-surface-variant rounded-full opacity-50"></div>
                    <div class="w-2 h-1 bg-surface-variant rounded-full opacity-50"></div>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Login Form -->
        <div class="flex items-center justify-center p-margin-mobile md:p-margin-desktop bg-surface">
            <div class="w-full max-w-md space-y-10">
                <div class="md:hidden flex items-center gap-3 justify-center mb-8 lg:mb-10">
                    <div class="bg-primary p-2 rounded-lg">
                        <span class="material-symbols-outlined text-on-primary">content_cut</span>
                    </div>
                    <span class="font-headline-lg-mobile text-on-surface text-headline-lg-mobile">Ran <span class="text-primary">CukurIn</span></span>
                </div>

                <div class="space-y-3 text-center md:text-left">
                    <h2 class="font-headline-xl text-on-surface text-headline-xl">Selamat Datang Kembali</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">Silakan masuk ke akun Anda untuk melanjutkan.</p>
                </div>

                @if (session('status'))
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-start gap-2.5">
                        <span class="material-symbols-outlined text-emerald-400 text-[20px] shrink-0">check_circle</span>
                        <div class="font-medium">{{ session('status') }}</div>
                    </div>
                @endif

                <form wire:submit="login" class="space-y-6">
                    @if ($errors->has('email'))
                        <div class="p-4 bg-error-container text-on-error-container rounded-xl text-sm">
                            {{ $errors->first('email') }}
                        </div>
                    @endif

                    <div class="space-y-5">
                        <!-- Email Field -->
                        <div class="group relative">
                            <label class="block font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-2 transition-colors group-focus-within:text-primary" for="email">Email Address</label>
                            <div class="relative flex items-center">
                                <span class="material-symbols-outlined absolute left-4 text-on-surface-variant group-focus-within:text-primary transition-colors">mail</span>
                                <input wire:model="email" class="w-full bg-surface-container text-on-surface font-body-md rounded-xl py-4 pl-12 pr-4 outline-none border border-outline-variant/30 focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-sm placeholder:text-on-surface-variant/40" id="email" type="email" placeholder="contoh@email.com" required autofocus>
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="group relative" x-data="{ show: false }">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest transition-colors group-focus-within:text-primary" for="password">Password</label>
                                <a class="font-label-sm text-label-sm text-primary hover:text-primary-fixed transition-colors" href="{{ route('password.request') }}">Lupa Password?</a>
                            </div>
                            <div class="relative flex items-center">
                                <span class="material-symbols-outlined absolute left-4 text-on-surface-variant group-focus-within:text-primary transition-colors">lock</span>
                                <input wire:model="password" x-bind:type="show ? 'text' : 'password'" class="w-full bg-surface-container text-on-surface font-body-md rounded-xl py-4 pl-12 pr-12 outline-none border border-outline-variant/30 focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-sm placeholder:text-on-surface-variant/40" id="password" type="password" placeholder="••••••••" required>
                                <button @click="show = !show" type="button" class="absolute right-4 text-on-surface-variant hover:text-on-surface transition-colors focus:outline-none">
                                    <span class="material-symbols-outlined" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <input wire:model="remember" class="peer sr-only" id="remember" type="checkbox">
                        <label class="flex items-center gap-3 cursor-pointer group" for="remember">
                            <div class="w-5 h-5 rounded border-2 border-outline-variant/50 flex items-center justify-center peer-checked:bg-primary peer-checked:border-primary transition-all group-hover:border-primary/70">
                                <span class="material-symbols-outlined text-[14px] text-on-primary opacity-0 peer-checked:opacity-100 transition-opacity" style="font-variation-settings: 'wght' 700;">check</span>
                            </div>
                            <span class="font-body-md text-body-md text-on-surface-variant group-hover:text-on-surface transition-colors">Ingat saya</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full bg-primary text-on-primary font-headline-md py-4 rounded-xl hover:bg-primary-fixed hover:scale-[1.02] active:scale-95 transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-3 group text-headline-md">
                        <span>Masuk</span>
                        <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </button>
                </form>

                <div class="text-center pt-6 border-t border-outline-variant/20">
                    <p class="font-body-md text-body-md text-on-surface-variant">
                        Belum punya akun? <a href="{{ route('register') }}" class="text-primary font-semibold hover:text-primary-fixed transition-colors border-b border-transparent hover:border-primary-fixed">Daftar Sekarang</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
