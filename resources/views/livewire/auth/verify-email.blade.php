<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Verifikasi Email - Ran CukurIn')] class extends Component {
    public string $status = '';

    public function mount()
    {
        if (Auth::user()?->hasVerifiedEmail()) {
            $this->redirectIntended(route('dashboard', absolute: false));
        }
    }

    public function sendVerification(): void
    {
        if (Auth::user()?->hasVerifiedEmail()) {
            $this->redirectIntended(route('dashboard', absolute: false));
            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        $this->status = 'verification-link-sent';
    }

    public function logout(): void
    {
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();

        $this->redirect('/');
    }
};
?>

<div class="flex-grow flex flex-col bg-background font-body-md text-on-surface antialiased">
    <div class="flex flex-col w-full h-full flex-grow min-h-[calc(100vh-5rem)]">
        <div class="flex flex-col w-full h-full flex-grow md:flex-row">
            <!-- Left Branding Column -->
            <div class="relative hidden w-full md:block md:w-1/2 overflow-hidden bg-surface-container-lowest">
                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1599351431202-1e0f0137899a?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-background/90 via-background/50 to-transparent"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-background/80 to-transparent"></div>
                <div class="absolute inset-0 flex flex-col justify-between p-12 lg:p-16">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-primary shadow-lg shadow-primary/20">
                            <span class="material-symbols-outlined text-on-primary">content_cut</span>
                        </div>
                        <span class="tracking-tight font-headline-md text-on-surface text-headline-md">Ran <span class="text-primary">CukurIn</span></span>
                    </div>
                    <div class="max-w-md space-y-4">
                        <p class="font-headline-lg text-on-surface leading-tight text-headline-lg">Satu Langkah Terakhir.</p>
                        <p class="font-body-lg text-body-lg text-on-surface-variant/80">Verifikasi email Anda untuk menikmati layanan booking cukur instan tanpa antre.</p>
                        <div class="w-12 h-1 mt-6 rounded-full bg-primary"></div>
                    </div>
                </div>
            </div>

            <!-- Right Verification Card Column -->
            <div class="flex items-center justify-center w-full px-4 lg:px-5 py-12 md:w-1/2 md:px-12 lg:px-24 bg-surface">
                <div class="w-full max-w-md space-y-8">
                    <div class="text-center space-y-4">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/10 text-primary border border-primary/20 shadow-lg shadow-primary/5 animate-pulse">
                            <span class="material-symbols-outlined text-[36px]">mark_email_unread</span>
                        </div>
                        <h1 class="tracking-tight font-headline-xl text-on-surface text-headline-xl">Verifikasi Email Anda</h1>
                        <p class="font-body-md text-sm text-on-surface-variant leading-relaxed">
                            Terima kasih telah bergabung di <strong>Ran CukurIn</strong>! Kami telah mengirimkan tautan aktivasi ke email Anda:
                        </p>
                        <div class="inline-block px-4 py-2 rounded-xl bg-surface-container-high border border-outline-variant/30 text-primary font-mono text-xs font-bold">
                            {{ auth()->user()->email ?? '' }}
                        </div>
                        <p class="text-xs text-on-surface-variant/80">
                            Silakan buka kotak masuk (atau folder spam) email Anda dan klik tombol verifikasi untuk mengaktifkan akun.
                        </p>
                    </div>

                    @if($status === 'verification-link-sent')
                        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-start gap-2.5 animate-[fadeIn_0.2s_ease-out]">
                            <span class="material-symbols-outlined text-emerald-400 text-[20px] shrink-0">check_circle</span>
                            <div>
                                <strong class="font-bold block text-emerald-200">Email Berhasil Dikirim Ulang!</strong>
                                <span>Tautan aktivasi baru telah dikirimkan ke alamat email Anda.</span>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-3 pt-2">
                        <button wire:click="sendVerification" wire:loading.attr="disabled" type="button" class="w-full py-3.5 transition-all shadow-xl rounded-xl bg-primary text-on-primary font-label-md text-xs font-bold uppercase tracking-wider hover:bg-primary-fixed-dim hover:shadow-primary/20 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50">
                            <span wire:loading.remove wire:target="sendVerification" class="material-symbols-outlined text-[18px]">send</span>
                            <span wire:loading wire:target="sendVerification" class="material-symbols-outlined text-[18px] animate-spin">refresh</span>
                            <span>Kirim Ulang Email Verifikasi</span>
                        </button>

                        <button wire:click="logout" type="button" class="w-full py-3 transition-all rounded-xl border border-outline-variant/30 text-on-surface-variant hover:text-rose-400 hover:border-rose-400/30 font-label-md text-xs font-semibold flex items-center justify-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">logout</span>
                            <span>Keluar &amp; Gunakan Akun Lain</span>
                        </button>
                    </div>

                    <div class="text-center pt-6 border-t border-outline-variant/10 text-xs text-on-surface-variant">
                        <span>Butuh bantuan? Hubungi Admin Ran CukurIn di WhatsApp kami.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
