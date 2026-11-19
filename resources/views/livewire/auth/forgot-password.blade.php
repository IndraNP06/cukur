<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Lupa Kata Sandi - Ran CukurIn')] class extends Component {
    public string $email = '';
    public string $status = '';
    public ?string $directResetUrl = null;

    public function sendPasswordResetLink()
    {
        $this->validate(['email' => ['required', 'email']]);

        $status = Password::broker()->sendResetLink(
            ['email' => $this->email]
        );

        if ($status === Password::RESET_LINK_SENT) {
            $this->status = 'Permintaan reset kata sandi telah diproses!';
            $this->directResetUrl = session('direct_reset_url') ?? cache()->get('direct_reset_url_' . $this->email);
        } else {
            $this->addError('email', 'Alamat email tidak ditemukan dalam catatan kami.');
        }
    }
};
?>

<div class="flex-grow flex flex-col bg-background font-body-md text-on-surface antialiased">
 <div class="flex flex-col w-full h-full flex-grow min-h-[calc(100vh-5rem)]">
 <div class="flex flex-col w-full h-full flex-grow md:flex-row">
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
 <p class="font-headline-lg text-on-surface leading-tight text-headline-lg">Tidak perlu khawatir.</p>
 <p class="font-body-lg text-body-lg text-on-surface-variant/80">Kami akan membantu Anda memulihkan akses ke akun Anda dengan cepat.</p>
 <div class="w-12 h-1 mt-6 rounded-full bg-primary"></div>
 </div>
 </div>
 </div>
 
 <div class="flex items-center justify-center w-full px-4 lg:px-5 py-12 md:w-1/2 md:px-12 lg:px-24 bg-surface">
 <div class="w-full max-w-md space-y-10">
 <div class="text-center space-y-6">
 <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary/10 text-primary mb-2 shadow-inner shadow-primary/5">
 <span class="material-symbols-outlined text-[32px]">lock_reset</span>
 </div>
 <h1 class="tracking-tight font-headline-xl text-on-surface text-headline-xl">Lupa Kata Sandi?</h1>
 <p class="font-body-md text-body-md text-on-surface-variant">Masukkan alamat email yang terdaftar pada akun Anda. Kami akan mengirimkan instruksi dan tautan aman untuk mengatur ulang kata sandi Anda.</p>
 </div>

 @if($status)
 <div class="space-y-4">
     <div class="p-4 lg:p-5 text-center space-y-3 rounded-xl bg-surface-container-high border-l-4 border-primary">
         <span class="material-symbols-outlined text-primary text-[40px]">mark_email_read</span>
         <h3 class="font-headline-md text-on-surface text-headline-md">Permintaan Reset Berhasil</h3>
         <p class="font-body-md text-body-md text-on-surface-variant">{{ $status }}</p>
     </div>

     @if($directResetUrl)
         <div class="p-5 rounded-2xl bg-primary/10 border border-primary/30 text-left space-y-3 animate-[fadeIn_0.2s_ease-out]">
             <div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider">
                 <span class="material-symbols-outlined text-base">bolt</span>
                 <span>Akses Cepat (Tanpa Perlu Buka Email)</span>
             </div>
             <p class="text-xs text-on-surface-variant leading-relaxed">
                 Tautan reset kata sandi berhasil disiapkan. Anda dapat langsung mengklik tombol di bawah untuk membuat kata sandi baru secara instan:
             </p>
             <a href="{{ $directResetUrl }}" class="w-full py-3 px-4 rounded-xl bg-primary text-on-primary text-xs font-bold flex items-center justify-center gap-2 hover:bg-primary-fixed-dim transition-all shadow-md shadow-primary/20">
                 <span class="material-symbols-outlined text-[16px]">lock_open</span>
                 <span>Atur Ulang Kata Sandi Sekarang</span>
             </a>
         </div>
     @endif

     <div class="text-center pt-2">
         <button wire:click="$set('status', '')" type="button" class="text-xs text-on-surface-variant hover:text-primary transition-colors underline cursor-pointer">
             Kirim ulang ke email lain
         </button>
     </div>
 </div>
 @else
 <form wire:submit="sendPasswordResetLink" class="space-y-6">
 @if ($errors->has('email'))
 <div class="p-4 bg-error-container text-on-error-container rounded-xl text-sm">
 {{ $errors->first('email') }}
 </div>
 @endif

 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface-variant uppercase tracking-wider" for="email">Alamat Email</label>
 <div class="relative group">
 <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-on-surface-variant/50 group-focus-within:text-primary transition-colors">
 <span class="material-symbols-outlined">mail</span>
 </span>
 <input wire:model="email" class="w-full py-4 pl-12 pr-4 text-on-surface transition-all rounded-xl bg-surface-container-high focus:outline-none focus:ring-2 focus:ring-primary/50 focus:bg-surface-container-highest placeholder:text-on-surface-variant/30 font-body-md" id="email" placeholder="Masukkan email terdaftar Anda" required type="email" autofocus>
 </div>
 </div>
 
 <button type="submit" class="w-full py-4 transition-all shadow-xl rounded-xl bg-primary text-on-primary font-label-md text-label-md uppercase tracking-wider hover:scale-[1.02] hover:bg-primary-fixed-dim hover:shadow-primary/20 focus:outline-none focus:ring-4 focus:ring-primary/30 flex items-center justify-center gap-2 group">
 <span>Kirim Tautan Reset</span>
 <span class="material-symbols-outlined text-[20px] transition-transform group-hover:translate-x-1">arrow_forward</span>
 </button>
 </form>
 @endif
 
 <div class="text-center pt-8 border-t border-outline-variant/10">
 <a href="{{ route('login') }}" class="inline-flex items-center gap-2 transition-colors font-label-md text-label-md text-on-surface-variant hover:text-primary group">
 <span class="material-symbols-outlined text-[18px] transition-transform group-hover:-translate-x-1">arrow_back</span>
 Kembali ke Halaman Masuk
 </a>
 </div>
 </div>
 </div>
 </div>
 </div>
</div>