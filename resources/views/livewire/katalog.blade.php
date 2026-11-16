<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use App\Models\Layanan;
use App\Models\User;

new #[Layout('components.layouts.blank')] #[Title('Katalog Layanan & Profil Kapster - Ran CukurIn')] class extends Component {
 public function with() {
 $q = request('q');
 $layanans = Layanan::where('is_active', true);
 $kapsters = User::whereHas('roles', function($q){$q->where('name', 'Kapster');});

 if ($q) {
 $layanans->where(function($query) use ($q) {
 $query->where('nama_layanan', 'like', "%{$q}%")
 ->orWhere('deskripsi', 'like', "%{$q}%");
 });
 $kapsters->where('nama', 'like', "%{$q}%");
 }

 $galeris = \App\Models\Galeri::where('is_active', true)->where('tipe', 'Rambut')->take(6)->get();

 return [
 'layanans' => $layanans->get(),
 'kapsters' => $kapsters->get(),
 'galeris' => $galeris,
 ];
 }
};
?>

<div class="bg-background font-body-md text-on-surface antialiased flex flex-col min-h-screen">
 <header class="fixed top-0 w-full z-50 bg-surface-dim/80 backdrop-blur-xl border-b border-outline-variant/30 transition-all duration-300">
 <div class="h-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-6">
 <a href="{{ url('/') }}" class="flex items-center gap-3">
 <div class="bg-primary p-2 rounded-lg">
 <span class="material-symbols-outlined text-on-primary">content_cut</span>
 </div>
 <span class="font-headline-md tracking-tight text-on-surface text-headline-md">Ran <span class="text-primary">CukurIn</span></span>
 </a>
 
 <nav class="hidden md:flex items-center gap-8 lg:gap-10" data-active-classes="text-primary font-bold">
 <a href="{{ url('/') }}" class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors uppercase">Beranda</a>
 <a aria-current="page" href="{{ route('katalog') }}" class="transition-colors uppercase text-primary font-bold border-b-2 border-primary pb-2">Katalog</a>
 <a href="{{ url('/#portofolio') }}" class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors uppercase">Portofolio</a>
 <a href="{{ url('/#antrean') }}" class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors uppercase">Cek Antrean</a>
 </nav>
 
 <div class="flex items-center gap-4">
 <a href="{{ route('booking.wizard') }}" class="bg-primary text-on-primary px-6 py-2.5 rounded-xl font-bold font-label-md text-label-md uppercase tracking-wider hover:scale-[1.02] transition-all shadow-md shadow-primary/10">Booking Sekarang</a>
 </div>
 </div>
 </header>

 <main class="pt-20 lg:pt-24 flex-grow flex flex-col">
 <div class="flex-grow flex flex-col w-full bg-surface">
 <div class="max-w-container-max mx-auto px-margin-mobile lg:px-margin-desktop w-full py-16 flex flex-col gap-16">
 
 <div class="flex flex-col gap-4 lg:gap-5 items-center text-center max-w-2xl mx-auto mt-8">
 <nav class="flex items-center gap-2 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-widest">
 <a href="{{ url('/') }}" class="hover:text-primary transition-colors">Beranda</a>
 <span class="material-symbols-outlined text-sm">chevron_right</span>
 <span class="text-primary">Katalog &amp; Kapster</span>
 </nav>
 <h1 class="font-headline-xl text-on-surface uppercase tracking-tight text-headline-xl">KATALOG LAYANAN &amp; PROFIL KAPSTER</h1>
 <p class="font-body-lg text-body-lg text-on-surface-variant leading-relaxed">Temukan layanan grooming premium yang dirancang khusus untuk pria modern. Pilih gaya Anda dan kenali para maestro cukur kami yang berdedikasi.</p>
 </div>
 
 <section class="flex flex-col gap-5 lg:gap-5">
 <div class="flex items-center gap-4">
 <h2 class="font-headline-lg text-on-surface uppercase text-headline-lg">Katalog Layanan</h2>
 <div class="flex-grow h-px bg-surface-container-high"></div>
 </div>
 
 <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-5">
 @foreach($layanans as $layanan)
 <div class="bg-surface-container-low rounded-xl overflow-hidden shadow-lg transition-transform hover:-translate-y-2 group flex flex-col h-full relative">
 <div class="relative h-48 overflow-hidden bg-surface-container">
 <img src="{{ $layanan->gambar_url }}" alt="{{ $layanan->nama_layanan }}" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-700">
 <div class="absolute inset-0 bg-gradient-to-t from-surface-container-low via-surface-container-low/30 to-transparent"></div>
 <div class="absolute top-3 right-3 w-8 h-8 rounded-lg bg-surface-container-low/80 backdrop-blur-md flex items-center justify-center text-primary shadow-sm">
 <span class="material-symbols-outlined text-sm">
 @if(str_contains(strtolower($layanan->nama_layanan), 'hair') || str_contains(strtolower($layanan->nama_layanan), 'cukur'))
 content_cut
 @elseif(str_contains(strtolower($layanan->nama_layanan), 'shave') || str_contains(strtolower($layanan->nama_layanan), 'kumis'))
 face
 @else
 spa
 @endif
 </span>
 </div>
 </div>
 
 <div class="p-4 lg:p-5 flex flex-col gap-4 flex-grow relative z-10">
 <h3 class="font-headline-md text-on-surface text-headline-md">{{ $layanan->nama_layanan }}</h3>
 <div class="flex items-center gap-3 flex-wrap">
 <span class="bg-surface-container px-3 py-1 rounded-full text-primary font-label-sm text-label-sm uppercase tracking-wide">Rp {{ number_format($layanan->harga, 0, ',', '.') }}</span>
 <span class="bg-surface-container px-3 py-1 rounded-full text-on-surface-variant font-label-sm text-label-sm uppercase tracking-wide flex items-center gap-1">
 <span class="material-symbols-outlined text-[14px]">schedule</span> {{ $layanan->estimasi_durasi_menit }} Menit
 </span>
 </div>
 <p class="text-on-surface-variant font-body-md text-body-md flex-grow">{{ $layanan->deskripsi }}</p>
 <a href="{{ route('booking.wizard') }}" class="w-full text-center py-3 rounded-lg border border-surface-container-high text-on-surface font-label-md text-label-md uppercase tracking-wider hover:border-primary hover:text-primary transition-colors mt-auto block">Pilih Layanan</a>
 </div>
 </div>
 @endforeach
 </div>
 </section>
 
 <section class="flex flex-col gap-5 lg:gap-5">
 <div class="flex items-center gap-4">
 <h2 class="font-headline-lg text-on-surface uppercase text-headline-lg">Profil Kapster</h2>
 <div class="flex-grow h-px bg-surface-container-high"></div>
 </div>
 
 <div class="flex flex-col gap-5 lg:gap-5">
 @foreach($kapsters as $kapster)
 <div class="bg-surface-container-low rounded-xl overflow-hidden shadow-lg flex flex-col lg:flex-row">
 <div class="lg:w-1/3 p-5 lg:p-5 flex flex-col gap-4 lg:gap-5 border-b lg:border-b-0 lg:border-r border-surface-container-high">
 <div class="flex items-start gap-4">
 <div class="w-16 h-16 rounded-full overflow-hidden shadow-md flex items-center justify-center shrink-0 border-2 border-primary/30 bg-surface-container">
 <img src="{{ $kapster->foto_url }}" alt="{{ $kapster->nama }}" class="w-full h-full object-cover">
 </div>
 <div>
 <h3 class="font-headline-md text-on-surface text-headline-md">{{ $kapster->nama }}</h3>
 <p class="text-primary font-label-sm text-label-sm uppercase tracking-wide">Professional Barber</p>
 <div class="flex items-center gap-1 mt-2 text-primary">
 <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">star</span>
 <span class="font-label-sm text-label-sm">5.0</span>
 </div>
 </div>
 </div>
 <p class="text-on-surface-variant font-body-md text-body-md flex-grow">Siap melayani Anda dengan teknik terbaik dan dedikasi penuh pada kepuasan pelanggan.</p>
 <a href="{{ route('booking.wizard') }}" class="text-center block w-full bg-surface-container text-on-surface px-4 lg:px-5 py-3 rounded-xl font-label-md text-label-md uppercase tracking-wider hover:bg-primary hover:text-on-primary transition-colors mt-auto">Pilih Kapster</a>
 </div>
 
 <div class="lg:w-2/3 p-5 lg:p-5 bg-surface-container/30">
 <h4 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-4">Portofolio Khas</h4>
 <div class="grid grid-cols-3 gap-4">
 @forelse($galeris->take(3) as $item)
 <div class="relative h-28 sm:h-32 rounded-lg overflow-hidden shadow-sm group/item border border-outline-variant/30 bg-surface-container">
 <img src="{{ $item->url }}" alt="{{ $item->judul }}" class="w-full h-full object-cover group-hover/item:scale-110 transition-transform duration-500">
 <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover/item:opacity-100 transition-opacity flex items-end p-2">
 <span class="text-xs text-white truncate">{{ $item->judul }}</span>
 </div>
 </div>
 @empty
 <div class="col-span-3 text-center text-on-surface-variant text-sm py-4">Belum ada portofolio.</div>
 @endforelse
 </div>
 </div>
 </div>
 @endforeach
 </div>
 </section>
 
 </div>
 </div>
 </main>
 
 <footer class="bg-surface-container-low border-t border-outline-variant/20 pt-20 pb-12">
 <div class="max-w-[1200px] mx-auto px-margin-mobile lg:px-margin-desktop">
 <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-10 lg:mb-10">
 <div class="space-y-6">
 <div class="flex items-center gap-2">
 <span class="material-symbols-outlined text-primary">content_cut</span>
 <span class="font-headline-md text-on-surface text-headline-md">Ran CukurIn</span>
 </div>
 <p class="text-on-surface-variant font-body-md text-body-md opacity-80 leading-relaxed">Premium grooming experience for the modern gentleman. Precision cuts, heritage traditions, and luxury service.</p>
 </div>
 
 <div class="space-y-6">
 <h4 class="font-label-md text-label-md text-primary uppercase tracking-widest">Quick Links</h4>
 <nav class="flex flex-col gap-4">
 <a href="{{ route('katalog') }}" class="text-on-surface-variant hover:text-primary transition-colors">Katalog Layanan</a>
 <a href="{{ url('/#portofolio') }}" class="text-on-surface-variant hover:text-primary transition-colors">Tim Kapster</a>
 <a href="{{ route('booking.wizard') }}" class="text-on-surface-variant hover:text-primary transition-colors">Booking</a>
 </nav>
 </div>
 
 <div class="space-y-6">
 <h4 class="font-label-md text-label-md text-primary uppercase tracking-widest">Contact Details</h4>
 <div class="space-y-4">
 <div class="flex items-start gap-3">
 <span class="material-symbols-outlined text-on-surface-variant">location_on</span>
 <span class="text-on-surface-variant">Jl. Senopati No. 42, Jakarta Selatan</span>
 </div>
 </div>
 </div>
 
 <div class="space-y-6">
 <h4 class="font-label-md text-label-md text-primary uppercase tracking-widest">Social Media</h4>
 <div class="flex gap-4">
 <a href="https://instagram.com" target="_blank" class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center hover:bg-primary transition-all group">
 <span class="material-symbols-outlined text-on-surface group-hover:text-on-primary">share</span>
 </a>
 </div>
 </div>
 </div>
 
 <div class="border-t border-outline-variant/10 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
 <p class="text-on-surface-variant font-label-sm text-label-sm">&copy; {{ date('Y') }} Ran CukurIn. All Rights Reserved.</p>
 </div>
 </div>
 </footer>
</div>

