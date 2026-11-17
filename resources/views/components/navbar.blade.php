@props(['type' => 'customer'])

@if($type === 'admin')
 <header class="fixed top-0 left-0 lg:left-64 right-0 h-16 bg-background/80 backdrop-blur-xl z-30 flex items-center justify-between lg:justify-end px-4 sm:px-6 lg:px-8 gap-4 lg:gap-5 border-b lg:border-none border-outline-variant/30">
 <!-- Mobile Sidebar Toggle -->
 <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-lg hover:bg-surface-container-highest transition-colors text-on-surface">
 <span class="material-symbols-outlined">menu</span>
 </button>

 <!-- Global Search -->
 <form action="{{ route('katalog') }}" method="GET" class="hidden md:flex items-center gap-2 px-4 py-2 bg-surface-container rounded-full text-on-surface-variant focus-within:ring-2 focus-within:ring-primary transition-all w-64 lg:w-96">
 <span class="material-symbols-outlined text-[18px]">search</span>
 <input type="text" name="q" placeholder="Cari layanan atau kapster..." value="{{ request('q') }}" class="bg-transparent border-none outline-none text-label-sm font-label-sm w-full placeholder:text-on-surface-variant/70 text-on-surface">
 </form>
 
 <div class="flex items-center gap-2 lg:gap-4">
 <!-- Dynamic Role & User Segregated Notification Dropdown -->
 <x-notification-dropdown />
 
 <!-- User Profile Dropdown -->
 <div x-data="{ open: false }" class="relative">
 <button @click="open = !open" @click.away="open = false" class="w-10 h-10 rounded-full bg-primary flex items-center justify-center cursor-pointer hover:scale-105 transition-transform shadow-md shadow-primary/20">
 <span class="material-symbols-outlined text-on-primary text-[20px]">person</span>
 </button>
 
 <div x-show="open" x-transition.opacity.duration.200ms class="absolute right-0 mt-2 w-56 bg-surface border border-outline-variant/30 rounded-xl shadow-lg z-50 overflow-hidden" style="display: none;">
 <div class="p-4 border-b border-outline-variant/30 bg-surface-container-lowest">
 <p class="font-label-md text-on-surface truncate">{{ auth()->user()->nama ?? 'Admin' }}</p>
 <p class="text-[10px] text-on-surface-variant uppercase tracking-widest">{{ auth()->user()->role ?? 'Role' }}</p>
 </div>
 <a href="{{ route('customer.profil') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-surface-container-lowest transition-colors text-on-surface">
 <span class="material-symbols-outlined text-[18px]">manage_accounts</span>
 <span class="font-label-sm">Pengaturan Profil</span>
 </a>
 <form method="POST" action="{{ route('logout') }}" class="m-0">
 @csrf
 <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 hover:bg-error/10 text-error transition-colors text-left">
 <span class="material-symbols-outlined text-[18px]">logout</span>
 <span class="font-label-sm">Logout</span>
 </button>
 </form>
 </div>
 </div>
 </div>
 </header>
@else
    <header x-data="{ mobileMenuOpen: false }" class="fixed top-0 w-full z-50 bg-background/85 backdrop-blur-md border-b border-outline-variant/40 shadow-sm transition-all duration-300">
        <div class="h-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-6">
            <a href="/" class="flex items-center gap-2 lg:gap-3 shrink-0 hover:opacity-90 transition-opacity">
                <div class="bg-primary p-2 rounded-lg">
                    <span class="material-symbols-outlined text-on-primary text-[20px] lg:text-[24px]">content_cut</span>
                </div>
                <span class="font-headline-md text-lg lg:text-headline-md tracking-tight text-on-surface text-headline-md">Ran <span class="text-primary">CukurIn</span></span>
            </a>
            
            <nav class="hidden lg:flex items-center gap-8 lg:gap-10">
                <a href="{{ route('home') }}" class="font-label-md text-label-md {{ request()->routeIs('home') ? 'text-primary font-bold border-b-2 border-primary pb-2' : 'text-on-surface-variant hover:text-primary' }} transition-colors uppercase">Beranda</a>
                <a href="{{ route('katalog') }}" class="font-label-md text-label-md {{ request()->routeIs('katalog') ? 'text-primary font-bold border-b-2 border-primary pb-2' : 'text-on-surface-variant hover:text-primary' }} transition-colors uppercase">Katalog</a>
                
                @auth
                    @if(auth()->user()->role === 'Pelanggan')
                        <a href="{{ route('customer.dashboard') }}" class="font-label-md text-label-md {{ request()->routeIs('customer.dashboard') ? 'text-primary font-bold border-b-2 border-primary pb-2' : 'text-on-surface-variant hover:text-primary' }} transition-colors uppercase">Dashboard</a>
                        <a href="{{ route('customer.riwayat') }}" class="font-label-md text-label-md {{ request()->routeIs('customer.riwayat') ? 'text-primary font-bold border-b-2 border-primary pb-2' : 'text-on-surface-variant hover:text-primary' }} transition-colors uppercase">Riwayat</a>
                    @endif
                @endauth
            </nav>
            
            <div class="hidden lg:flex items-center gap-4 shrink-0">
                @auth
                    <x-notification-dropdown />
                    @if(auth()->user()->role === 'Pemilik')
                        <a href="{{ route('admin.dashboard') }}" class="bg-primary hover:bg-primary-fixed text-on-primary px-6 py-2.5 rounded-xl font-bold font-label-md text-label-md uppercase tracking-wider hover:scale-[1.02] transition-all shadow-md shadow-primary/10">Admin Panel</a>
                    @elseif(auth()->user()->role === 'Kapster')
                        <a href="{{ route('staff.dashboard') }}" class="bg-primary hover:bg-primary-fixed text-on-primary px-6 py-2.5 rounded-xl font-bold font-label-md text-label-md uppercase tracking-wider hover:scale-[1.02] transition-all shadow-md shadow-primary/10">Staff Panel</a>
                    @else
                        <!-- Customer Profile Dropdown -->
                        <a href="{{ route('booking.wizard') }}" class="bg-primary hover:bg-primary-fixed text-on-primary px-6 py-2.5 rounded-xl font-bold font-label-md text-label-md uppercase tracking-wider hover:scale-[1.02] transition-all shadow-md shadow-primary/10 mr-2">Booking</a>
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" @click.away="open = false" class="w-10 h-10 rounded-full bg-surface-container-high border border-outline-variant/30 flex items-center justify-center hover:bg-surface-container-highest transition-colors">
                                <span class="material-symbols-outlined text-on-surface text-[20px]">person</span>
                            </button>
                            
                            <div x-show="open" x-transition.opacity.duration.200ms class="absolute right-0 mt-2 w-56 bg-surface border border-outline-variant/30 rounded-xl shadow-lg z-50 overflow-hidden" style="display: none;">
                                <div class="p-4 border-b border-outline-variant/30 bg-surface-container-lowest">
                                    <p class="font-label-md text-on-surface truncate">{{ auth()->user()->nama ?? 'Pelanggan' }}</p>
                                    <p class="text-[10px] text-on-surface-variant uppercase tracking-widest">{{ auth()->user()->role ?? 'Pelanggan' }}</p>
                                </div>
                                <a href="{{ route('customer.profil') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-surface-container-lowest transition-colors text-on-surface">
                                    <span class="material-symbols-outlined text-[18px]">manage_accounts</span>
                                    <span class="font-label-sm">Profil Saya</span>
                                </a>
                                <form method="POST" action="{{ route('logout') }}" class="m-0">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 hover:bg-error/10 text-error transition-colors text-left">
                                        <span class="material-symbols-outlined text-[18px]">logout</span>
                                        <span class="font-label-sm">Logout</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="bg-primary hover:bg-primary-fixed text-on-primary px-6 py-2.5 rounded-xl font-bold font-label-md text-label-md uppercase tracking-wider hover:scale-[1.02] transition-all shadow-md shadow-primary/10">Masuk / Daftar</a>
                @endauth
            </div>

            <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden p-2 text-on-surface hover:bg-surface-container-highest rounded-lg transition-colors">
                <span class="material-symbols-outlined text-2xl" x-text="mobileMenuOpen ? 'close' : 'menu'">menu</span>
            </button>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div x-show="mobileMenuOpen" x-transition class="lg:hidden bg-surface-container-lowest border-t border-outline-variant/30 absolute w-full" style="display: none;">
            <div class="px-6 py-5 flex flex-col space-y-3">
                <a href="{{ route('home') }}" class="font-label-md {{ request()->routeIs('home') ? 'text-primary font-bold' : 'text-on-surface-variant' }} uppercase py-2">Beranda</a>
                <a href="{{ route('katalog') }}" class="font-label-md {{ request()->routeIs('katalog') ? 'text-primary font-bold' : 'text-on-surface-variant' }} uppercase py-2">Katalog</a>
                
                @auth
                    @if(auth()->user()->role === 'Pelanggan')
                        <a href="{{ route('customer.dashboard') }}" class="font-label-md {{ request()->routeIs('customer.dashboard') ? 'text-primary font-bold' : 'text-on-surface-variant' }} uppercase py-2">Dashboard</a>
                        <a href="{{ route('customer.riwayat') }}" class="font-label-md {{ request()->routeIs('customer.riwayat') ? 'text-primary font-bold' : 'text-on-surface-variant' }} uppercase py-2">Riwayat</a>
                        <hr class="border-outline-variant/30 my-2">
                        <a href="{{ route('booking.wizard') }}" class="bg-primary text-on-primary px-5 py-2.5 text-center rounded-xl font-bold font-label-md uppercase shadow-sm">Booking Sekarang</a>
                        <a href="{{ route('customer.profil') }}" class="font-label-md text-on-surface-variant py-2">Profil Saya</a>
                        <form method="POST" action="{{ route('logout') }}" class="m-0">
                            @csrf
                            <button type="submit" class="font-label-md text-error py-2 text-left w-full">Logout</button>
                        </form>
                    @elseif(auth()->user()->role === 'Pemilik')
                        <a href="{{ route('admin.dashboard') }}" class="bg-primary text-on-primary px-5 py-2.5 text-center rounded-xl font-bold font-label-md uppercase shadow-sm">Admin Panel</a>
                        <form method="POST" action="{{ route('logout') }}" class="m-0 mt-2">
                            @csrf
                            <button type="submit" class="font-label-md text-error py-2 text-left w-full">Logout</button>
                        </form>
                    @else
                        <a href="{{ route('staff.dashboard') }}" class="bg-primary text-on-primary px-5 py-2.5 text-center rounded-xl font-bold font-label-md uppercase shadow-sm">Staff Panel</a>
                        <form method="POST" action="{{ route('logout') }}" class="m-0 mt-2">
                            @csrf
                            <button type="submit" class="font-label-md text-error py-2 text-left w-full">Logout</button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="bg-primary text-on-primary px-5 py-2.5 text-center rounded-xl font-bold font-label-md uppercase shadow-sm">Masuk / Daftar</a>
                @endauth
            </div>
        </div>
    </header>
@endif
