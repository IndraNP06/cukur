<aside class="fixed left-0 top-0 h-full w-64 bg-surface-container-lowest z-50 flex flex-col p-4 shadow-[4px_0_24px_rgba(0,0,0,0.2)] transform transition-transform duration-300 ease-in-out lg:translate-x-0" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
 <div class="flex items-center justify-between mb-6">
 <div class="text-primary font-headline-md tracking-tight text-headline-md">Ran CukurIn</div>
 <button @click="sidebarOpen = false" class="lg:hidden text-on-surface-variant hover:text-primary transition-colors">
 <span class="material-symbols-outlined">close</span>
 </button>
 </div>
 
 <nav class="flex-1 space-y-1 overflow-y-auto pr-2">
 @php
 $user = auth()->user();
 $role = $user ? $user->role : null;
 @endphp
 
 @if($role === 'Pemilik')
 <!-- Menu Khusus Pemilik -->
 <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('admin.dashboard') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }} transition-all">
 <span class="material-symbols-outlined text-[20px]">dashboard</span>
 <span class="font-label-md text-label-md">Dashboard Eksekutif</span>
 </a>
 
 <div class="space-y-1" x-data="{ open: {{ request()->routeIs('admin.layanan') || request()->routeIs('admin.staf') || request()->routeIs('admin.jadwal') || request()->routeIs('admin.kinerja') ? 'true' : 'false' }} }">
 <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-on-surface hover:bg-surface-container-highest transition-all">
 <div class="flex items-center gap-3">
 <span class="material-symbols-outlined text-[20px]">database</span>
 <span class="font-label-md text-label-md">Data Master</span>
 </div>
 <span class="material-symbols-outlined text-[18px]" :class="{ 'rotate-180': open }">expand_more</span>
 </button>
 <div x-show="open" x-collapse class="pl-11 space-y-1">
 <a href="{{ route('admin.layanan') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.layanan') ? 'bg-secondary-container text-on-secondary-container font-label-md' : 'text-on-surface-variant hover:text-on-surface transition-colors font-label-md text-label-md' }}">Layanan &amp; Harga</a>
 <a href="{{ route('admin.kategori') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.kategori') ? 'bg-secondary-container text-on-secondary-container font-label-md' : 'text-on-surface-variant hover:text-on-surface transition-colors font-label-md text-label-md' }}">Kategori Sistem</a>
 <a href="{{ route('admin.staf') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.staf') ? 'bg-secondary-container text-on-secondary-container font-label-md' : 'text-on-surface-variant hover:text-on-surface transition-colors font-label-md text-label-md' }}">Data Staf</a>
 <a href="{{ route('admin.pelanggan') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.pelanggan') ? 'bg-secondary-container text-on-secondary-container font-label-md' : 'text-on-surface-variant hover:text-on-surface transition-colors font-label-md text-label-md' }}">Pelanggan & Reguler</a>
 <a href="{{ route('admin.jadwal') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.jadwal') ? 'bg-secondary-container text-on-secondary-container font-label-md' : 'text-on-surface-variant hover:text-on-surface transition-colors font-label-md text-label-md' }}">Jadwal &amp; Shift</a>
 <a href="{{ route('admin.absensi') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.absensi') ? 'bg-secondary-container text-on-secondary-container font-label-md' : 'text-on-surface-variant hover:text-on-surface transition-colors font-label-md text-label-md' }}">Absensi Kehadiran</a>
 <a href="{{ route('admin.kinerja') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.kinerja') ? 'bg-secondary-container text-on-secondary-container font-label-md' : 'text-on-surface-variant hover:text-on-surface transition-colors font-label-md text-label-md' }}">Laporan Kinerja</a>
 </div>
 </div>
 
 <a href="{{ route('admin.antrean') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('admin.antrean') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }} transition-all">
 <span class="material-symbols-outlined text-[20px]">group</span>
 <span class="font-label-md text-label-md">Papan Antrean</span>
 </a>
 
 <div class="space-y-1" x-data="{ open: {{ request()->routeIs('admin.barang') || request()->routeIs('admin.stok') ? 'true' : 'false' }} }">
 <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-on-surface hover:bg-surface-container-highest transition-all">
 <div class="flex items-center gap-3">
 <span class="material-symbols-outlined text-[20px]">inventory_2</span>
 <span class="font-label-md text-label-md">Inventaris &amp; Stok</span>
 </div>
 <span class="material-symbols-outlined text-[18px]" :class="{ 'rotate-180': open }">expand_more</span>
 </button>
 <div x-show="open" x-collapse class="pl-11 space-y-1">
 <a href="{{ route('admin.barang') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.barang') ? 'bg-secondary-container text-on-secondary-container font-label-md' : 'text-on-surface-variant hover:text-on-surface transition-colors font-label-md text-label-md' }}">Master Barang</a>
 <a href="{{ route('admin.stok') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.stok') ? 'bg-secondary-container text-on-secondary-container font-label-md' : 'text-on-surface-variant hover:text-on-surface transition-colors font-label-md text-label-md' }}">Mutasi Stok</a>
 </div>
 </div>
 
 <a href="{{ route('admin.voucher') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('admin.voucher') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }} transition-all">
 <span class="material-symbols-outlined text-[20px]">local_offer</span>
 <span class="font-label-md text-label-md">Promosi &amp; Voucher</span>
 </a>
 
 <div class="space-y-1" x-data="{ open: {{ request()->routeIs('admin.laporan') || request()->routeIs('admin.pengeluaran') ? 'true' : 'false' }} }">
 <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-on-surface hover:bg-surface-container-highest transition-all">
 <div class="flex items-center gap-3">
 <span class="material-symbols-outlined text-[20px]">payments</span>
 <span class="font-label-md text-label-md">Keuangan</span>
 </div>
 <span class="material-symbols-outlined text-[18px]" :class="{ 'rotate-180': open }">expand_more</span>
 </button>
 <div x-show="open" x-collapse class="pl-11 space-y-1">
 <a href="{{ route('admin.laporan') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.laporan') ? 'bg-secondary-container text-on-secondary-container font-label-md' : 'text-on-surface-variant hover:text-on-surface transition-colors font-label-md text-label-md' }}">Laporan Pendapatan</a>
 <a href="{{ route('admin.pengeluaran') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.pengeluaran') ? 'bg-secondary-container text-on-secondary-container font-label-md' : 'text-on-surface-variant hover:text-on-surface transition-colors font-label-md text-label-md' }}">Pengeluaran Kas</a>
 </div>
 </div>
 
 <a href="{{ route('admin.pengaturan') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('admin.pengaturan') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }} transition-all">
 <span class="material-symbols-outlined text-[20px]">settings</span>
 <span class="font-label-md text-label-md">Pengaturan Sistem</span>
 </a>

 @elseif($role === 'Kapster')
 <!-- Menu Khusus Kapster -->
 <a href="{{ route('staff.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('staff.dashboard') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }} transition-all">
 <span class="material-symbols-outlined text-[20px]">dashboard</span>
 <span class="font-label-md text-label-md">Dashboard Operasional</span>
 </a>
 
 <a href="{{ route('admin.antrean') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('admin.antrean') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }} transition-all">
 <span class="material-symbols-outlined text-[20px]">group</span>
 <span class="font-label-md text-label-md">Antrean Terpadu</span>
 </a>
 
 <a href="{{ route('admin.stok') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('admin.stok') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }} transition-all">
 <span class="material-symbols-outlined text-[20px]">inventory_2</span>
 <span class="font-label-md text-label-md">Lapor Ekstra Barang</span>
 </a>
 
 <a href="{{ route('staff.absensi') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('staff.absensi') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }} transition-all">
 <span class="material-symbols-outlined text-[20px]">how_to_reg</span>
 <span class="font-label-md text-label-md">Absensi Kehadiran</span>
 </a>
 
 @elseif($role === 'Pelanggan')
 <!-- Menu Khusus Pelanggan -->
 <a href="{{ route('customer.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('customer.dashboard') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }} transition-all">
 <span class="material-symbols-outlined text-[20px]">dashboard</span>
 <span class="font-label-md text-label-md">Dashboard Pelanggan</span>
 </a>
 
 <a href="{{ route('customer.riwayat') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('customer.riwayat') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }} transition-all">
 <span class="material-symbols-outlined text-[20px]">history</span>
 <span class="font-label-md text-label-md">Riwayat Transaksi</span>
 </a>
 
 <a href="{{ route('customer.profil') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('customer.profil') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface' }} transition-all">
 <span class="material-symbols-outlined text-[20px]">person</span>
 <span class="font-label-md text-label-md">Profil Saya</span>
 </a>
 
 <div class="mt-4 border-t border-outline-variant/20 pt-4">
 <a href="{{ url('/') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface transition-all">
 <span class="material-symbols-outlined text-[20px]">home</span>
 <span class="font-label-md text-label-md">Kembali ke Beranda</span>
 </a>
 <a href="{{ route('booking.wizard') }}" class="flex items-center gap-3 px-3 py-2.5 mt-1 rounded-xl bg-primary text-on-primary hover:bg-primary-fixed hover:text-on-primary-fixed transition-all shadow-md shadow-primary/20">
 <span class="material-symbols-outlined text-[20px]">add_circle</span>
 <span class="font-label-md text-label-md">Booking Baru</span>
 </a>
 </div>
 @endif
 </nav>
 
 <!-- Profil Bawah Sidebar -->
 <div class="mt-auto flex items-center gap-3 p-4 bg-surface-container rounded-xl">
 <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center">
 <span class="material-symbols-outlined text-on-primary text-[20px]">person</span>
 </div>
 <div class="overflow-hidden">
 <p class="font-label-md text-label-md text-on-surface truncate">{{ auth()->user()->nama ?? 'Admin' }}</p>
 <p class="text-[10px] uppercase tracking-widest text-on-surface-variant">{{ $role ?? 'Guest' }}</p>
 </div>
 </div>
 
 <form method="POST" action="{{ route('logout') }}" class="mt-2">
 @csrf
 <button type="submit" class="w-full flex items-center justify-center gap-3 px-4 py-3 rounded-xl text-error hover:bg-error/10 transition-all">
 <span class="material-symbols-outlined text-[20px]">logout</span>
 <span class="font-label-md text-label-md">Logout</span>
 </button>
 </form>
</aside>
