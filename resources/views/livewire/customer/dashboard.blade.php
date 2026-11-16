<?php

use App\Models\AntreanPemesanan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Dashboard Pelanggan - Ran CukurIn')] class extends Component {
    public function with()
    {
        $userId = auth()->id();
        $today = \Carbon\Carbon::today()->toDateString();
        
        $activeBooking = AntreanPemesanan::where('pelanggan_id', $userId)
            ->whereIn('status', ['Menunggu', 'Diproses'])
            ->with(['layanan', 'kapster'])
            ->orderBy('created_at', 'desc')
            ->first();

        // Antrean toko yang sedang diproses saat ini (sama persis dengan Homepage)
        $antreanSaatIni = AntreanPemesanan::where('status', 'Diproses')
            ->whereDate('tanggal', $today)
            ->with(['layanan', 'kapster'])
            ->orderBy('waktu_mulai_aktual', 'desc')
            ->first();
        
        if (!$antreanSaatIni) {
            $antreanSaatIni = AntreanPemesanan::where('status', 'Diproses')
                ->with(['layanan', 'kapster'])
                ->orderBy('id', 'desc')
                ->first();
        }

        $menungguCount = AntreanPemesanan::where('status', 'Menunggu')
            ->whereDate('tanggal', $today)
            ->count();
        if ($menungguCount === 0) {
            $menungguCount = AntreanPemesanan::where('status', 'Menunggu')->count();
        }

        $ewt = AntreanPemesanan::hitungEwtToko();

        $sisaDiDepan = 0;
        $userEwt = $ewt;

        if ($activeBooking) {
            $sisaDiDepan = $activeBooking->sisa_antrean_di_depan;
            $userEwt = $activeBooking->estimasi_tunggu_menit;
        }

        $riwayatTerakhir = AntreanPemesanan::where('pelanggan_id', $userId)
            ->whereIn('status', ['Selesai', 'Batal'])
            ->with(['layanan', 'kapster', 'detailTransaksi'])
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();

        return [
            'activeBooking' => $activeBooking,
            'antreanSaatIni' => $antreanSaatIni,
            'menungguCount' => $menungguCount,
            'ewt' => $ewt,
            'sisaDiDepan' => $sisaDiDepan,
            'userEwt' => $userEwt,
            'riwayatTerakhir' => $riwayatTerakhir,
        ];
    }
};
?>

<div class="flex-grow flex flex-col relative bg-background" wire:poll.5s>
    <div class="absolute inset-0 pointer-events-none z-0">
        <div class="absolute -top-40 -left-40 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-secondary/5 rounded-full blur-[100px]"></div>
    </div>

    <!-- Header -->
    <header class="flex items-center justify-between p-margin-mobile lg:px-10 lg:py-6 bg-surface/80 backdrop-blur-md border-b border-surface-container-high shadow-sm relative z-10">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-primary-container text-on-primary-container flex items-center justify-center font-headline-md shadow-inner">
                {{ substr(auth()->user()->nama, 0, 2) }}
            </div>
            <div>
                <p class="font-label-sm text-on-surface-variant uppercase tracking-widest">Selamat Datang</p>
                <h1 class="font-headline-md text-on-background">{{ explode(' ', auth()->user()->nama)[0] }}</h1>
            </div>
        </div>
        <a href="{{ route('customer.profil') }}" class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center text-on-surface hover:bg-surface-container-high transition-colors">
            <span class="material-symbols-outlined">person</span>
        </a>
    </header>

    <main class="flex-1 relative z-10 flex flex-col gap-10 lg:gap-14 w-full max-w-5xl mx-auto px-4 lg:px-8 mt-8 lg:mt-12 mb-16">
                <!-- Active Booking -->
        <section>
            <div class="flex items-center justify-between mb-6">
                <h2 class="font-headline-md text-on-background flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-[28px]">notifications_active</span>
                    Status Saat Ini
                </h2>
                <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>Live Sinkron</span>
                </div>
            </div>
            
            @if($activeBooking)
            <div class="bg-surface-container-high rounded-[2rem] p-6 lg:p-8 shadow-2xl border border-primary/20 relative overflow-hidden group">
                <div class="absolute right-0 top-0 w-40 h-40 bg-primary/10 rounded-bl-full -z-10 group-hover:scale-110 transition-transform duration-500"></div>
                
                <div class="flex flex-col sm:flex-row justify-between items-start gap-6 mb-6">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full {{ $activeBooking->status === 'Diproses' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-primary/10 text-primary border border-primary/30' }} font-label-md uppercase tracking-widest text-xs font-bold">
                                <span class="w-2 h-2 rounded-full {{ $activeBooking->status === 'Diproses' ? 'bg-emerald-400' : 'bg-primary' }} animate-pulse"></span>
                                {{ $activeBooking->status === 'Diproses' ? 'Sedang Diproses' : 'Menunggu Giliran' }}
                            </span>
                            <span class="text-xs text-on-surface-variant font-medium">
                                {{ \Carbon\Carbon::parse($activeBooking->tanggal)->translatedFormat('d M Y') }} &bull; {{ substr($activeBooking->waktu_estimasi_mulai, 0, 5) }} WIB
                            </span>
                        </div>
                        <h3 class="font-headline-xl text-on-surface mb-2 text-2xl lg:text-3xl font-bold">{{ $activeBooking->layanan->nama_layanan ?? 'Layanan' }}</h3>
                        <p class="font-body-lg text-on-surface-variant flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">content_cut</span>
                            Kapster: <span class="text-on-surface font-medium">{{ $activeBooking->kapster->nama ?? 'Bebas / Tersedia' }}</span>
                        </p>
                    </div>
                    <div class="sm:text-right bg-surface-container rounded-2xl p-4 lg:p-5 border border-outline-variant/30 min-w-[160px] flex flex-col items-start sm:items-end justify-center">
                        <p class="font-label-sm text-on-surface-variant uppercase tracking-widest mb-1 text-xs">Nomor Antrean Anda</p>
                        <p class="font-headline-xl text-primary text-3xl font-bold">{{ $activeBooking->nomor_antrean }}</p>
                    </div>
                </div>

                <!-- Live Queue Synchronization Banner (Sync with Homepage!) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 rounded-2xl bg-surface-container/90 border border-outline-variant/40 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                            <span class="material-symbols-outlined text-[20px]">chair</span>
                        </div>
                        <div>
                            <p class="text-xs text-on-surface-variant uppercase tracking-wider">Sedang Diproses di Toko</p>
                            <p class="text-lg font-bold text-on-surface">
                                {{ $antreanSaatIni ? $antreanSaatIni->nomor_antrean : 'Belum Ada' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-secondary/10 flex items-center justify-center text-secondary shrink-0">
                            <span class="material-symbols-outlined text-[20px]">group</span>
                        </div>
                        <div>
                            <p class="text-xs text-on-surface-variant uppercase tracking-wider">Posisi Antrean Anda</p>
                            <p class="text-lg font-bold {{ $activeBooking->status === 'Diproses' ? 'text-emerald-400' : 'text-primary' }}">
                                @if($activeBooking->status === 'Diproses')
                                    Giliran Anda Sekarang!
                                @else
                                    {{ $sisaDiDepan > 0 ? "Sisa $sisaDiDepan orang di depan" : "Giliran Anda berikutnya!" }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 shrink-0">
                            <span class="material-symbols-outlined text-[20px]">schedule</span>
                        </div>
                        <div>
                            <p class="text-xs text-on-surface-variant uppercase tracking-wider">Estimasi Tunggu (EWT)</p>
                            <p class="text-lg font-bold text-amber-400">
                                {{ $activeBooking->status === 'Diproses' ? '0 Menit (Sedang Dilayani)' : "~$userEwt Menit" }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-outline-variant/30 flex gap-4">
                    <a href="{{ route('customer.tiket', ['id' => $activeBooking->id]) }}" class="flex-1 bg-primary text-on-primary py-3.5 rounded-2xl font-label-md text-center hover:bg-primary-fixed transition-all duration-300 shadow-lg shadow-primary/30 flex items-center justify-center gap-2 hover:-translate-y-0.5">
                        <span class="material-symbols-outlined">confirmation_number</span>
                        Lihat Tiket Antrean Lengkap
                    </a>
                </div>
            </div>
            @else
            <div class="bg-surface-container/30 rounded-3xl p-6 lg:p-8 flex flex-col md:flex-row items-center justify-between gap-6 border border-dashed border-outline-variant/40 hover:border-primary/40 transition-colors">
                <div class="flex flex-col md:flex-row items-center text-center md:text-left gap-5">
                    <div class="w-16 h-16 rounded-full bg-surface-container-highest flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-[32px] text-on-surface-variant">event_available</span>
                    </div>
                    <div>
                        <h3 class="font-body-lg font-semibold text-on-surface mb-1">Belum ada antrean aktif Anda</h3>
                        <p class="font-body-md text-on-surface-variant max-w-md text-sm mb-3">Pesan jadwal cukurmu sekarang dan rasakan pengalaman premium tanpa antre lama!</p>
                        <div class="inline-flex items-center gap-3 bg-surface-container/80 px-4 py-2 rounded-xl text-xs text-on-surface border border-outline-variant/30">
                            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                            <span>Live Toko: Sedang Diproses <strong>{{ $antreanSaatIni ? $antreanSaatIni->nomor_antrean : 'Belum Ada' }}</strong> &bull; Menunggu: <strong>{{ $menungguCount }} orang</strong> (EWT: ~{{ $ewt }} mnt)</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('booking.wizard') }}" class="flex-shrink-0 inline-flex items-center gap-2 bg-primary text-on-primary px-6 py-3 rounded-xl font-label-md hover:bg-primary-fixed transition-all duration-300 shadow-md shadow-primary/20 hover:-translate-y-1">
                    <span class="material-symbols-outlined text-[20px]">add_circle</span>
                    Booking Sekarang
                </a>
            </div>
            @endif
        </section>

        <!-- Quick Actions -->
        <section class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
            <a href="{{ route('booking.wizard') }}" class="group bg-surface-container hover:bg-surface-container-high p-5 lg:p-6 rounded-3xl flex flex-col gap-4 transition-all duration-300 border border-transparent hover:border-primary/20 relative overflow-hidden shadow-sm hover:shadow-md">
                <div class="absolute right-0 top-0 w-24 h-24 bg-primary/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
                <div class="w-12 h-12 rounded-2xl bg-primary/20 text-primary flex items-center justify-center group-hover:bg-primary group-hover:text-on-primary transition-colors">
                    <span class="material-symbols-outlined text-[24px]">add</span>
                </div>
                <div>
                    <h3 class="font-body-lg font-semibold text-on-surface mb-1">Buat Booking</h3>
                    <p class="font-body-sm text-on-surface-variant">Pesan jadwal cukur baru</p>
                </div>
            </a>
            <a href="{{ route('customer.riwayat') }}" class="group bg-surface-container hover:bg-surface-container-high p-5 lg:p-6 rounded-3xl flex flex-col gap-4 transition-all duration-300 border border-transparent hover:border-outline-variant/30 relative overflow-hidden shadow-sm hover:shadow-md">
                <div class="absolute right-0 top-0 w-24 h-24 bg-surface-variant/20 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
                <div class="w-12 h-12 rounded-2xl bg-surface-container-highest text-on-surface-variant flex items-center justify-center group-hover:text-on-surface transition-colors">
                    <span class="material-symbols-outlined text-[24px]">history</span>
                </div>
                <div>
                    <h3 class="font-body-lg font-semibold text-on-surface mb-1">Riwayat Transaksi</h3>
                    <p class="font-body-sm text-on-surface-variant">Lihat daftar kunjungan Anda</p>
                </div>
            </a>
        </section>

        <!-- Recent History -->
        <section>
            <div class="flex items-center justify-between mb-6">
                <h2 class="font-headline-md text-on-background flex items-center gap-3">
                    <span class="material-symbols-outlined text-on-surface-variant">receipt_long</span>
                    Riwayat Terakhir
                </h2>
                <a href="{{ route('customer.riwayat') }}" class="text-primary font-label-md hover:underline flex items-center gap-1">
                    Lihat Semua <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            </div>
            
            <div class="flex flex-col gap-4">
                @forelse($riwayatTerakhir as $riwayat)
                <div class="bg-surface-container p-5 lg:p-6 rounded-[1.5rem] flex items-center gap-5 hover:bg-surface-container-high transition-all duration-300 cursor-pointer hover:shadow-lg border border-transparent hover:border-outline-variant/30" onclick="window.location.href='{{ route('customer.tiket', ['id' => $riwayat->id]) }}'">
                    <div class="w-14 h-14 rounded-xl {{ $riwayat->status === 'Selesai' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-surface-variant text-on-surface-variant' }} flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-[28px]">{{ $riwayat->status === 'Selesai' ? 'check_circle' : 'cancel' }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-headline-sm text-on-surface truncate mb-1">{{ $riwayat->layanan->nama_layanan ?? '-' }}</h3>
                        <p class="font-body-md text-on-surface-variant flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px]">calendar_today</span>
                            {{ \Carbon\Carbon::parse($riwayat->tanggal)->translatedFormat('d M Y') }}
                        </p>
                    </div>
                    <div class="text-right flex flex-col items-end gap-1">
                        <span class="px-3 py-1 rounded-full font-label-sm tracking-wider uppercase {{ $riwayat->status === 'Selesai' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-error/10 text-error' }}">{{ $riwayat->status }}</span>
                    </div>
                </div>
                @empty
                <div class="bg-surface-container-lowest rounded-[1.5rem] text-center py-10 lg:py-12 border border-dashed border-outline-variant/50">
                    <span class="material-symbols-outlined text-[32px] text-surface-variant mb-3">inbox</span>
                    <p class="text-on-surface-variant font-body-lg">Belum ada riwayat cukur.</p>
                </div>
                @endforelse
            </div>
        </section>
    </main>
</div>
