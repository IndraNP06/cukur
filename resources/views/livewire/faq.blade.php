<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.blank')] #[Title('Pusat Bantuan & FAQ - Ran CukurIn')] class extends Component {
    public $searchQuery = '';
    public $selectedCategory = 'all'; // 'all', 'reservasi', 'pembayaran', 'layanan'
    public $openQuestionId = 1; // Default question 1 open
    public $feedbackGiven = []; // [questionId => 'yes'|'no']

    public function toggleQuestion($id) {
        if ($this->openQuestionId === $id) {
            $this->openQuestionId = null;
        } else {
            $this->openQuestionId = $id;
        }
    }

    public function setCategory($category) {
        $this->selectedCategory = $category;
    }

    public function giveFeedback($questionId, $type) {
        $this->feedbackGiven[$questionId] = $type;
    }

    public function getFaqItems() {
        return [
            [
                'id' => 1,
                'category' => 'reservasi',
                'category_label' => 'RESERVASI & ANTREAN',
                'question' => 'Bagaimana sistem antrean bekerja jika saya datang langsung (Walk-In)?',
                'answer' => 'Sistem antrean terpadu kami akan secara otomatis menyisipkan pelanggan Walk-In ke dalam slot waktu kosong terdekat tanpa menabrak jadwal pelanggan yang sudah melakukan Booking Online.'
            ],
            [
                'id' => 2,
                'category' => 'reservasi',
                'category_label' => 'RESERVASI & ANTREAN',
                'question' => 'Apa yang terjadi jika saya terlambat dari jadwal booking?',
                'answer' => 'Toleransi keterlambatan maksimal adalah 15 menit. Jika melewati batas tersebut, sistem akan membatalkan antrean Anda atau menggesernya ke slot kosong berikutnya (bergantung ketersediaan kapster).'
            ],
            [
                'id' => 3,
                'category' => 'pembayaran',
                'category_label' => 'PEMBAYARAN & VOUCHER',
                'question' => 'Bagaimana cara menggunakan kode Voucher Diskon?',
                'answer' => 'Masukkan kode voucher promo pada halaman Konfirmasi Reservasi sebelum Anda menyelesaikan pemesanan. Tagihan akan otomatis terpotong jika kuota voucher masih tersedia.'
            ],
            [
                'id' => 4,
                'category' => 'reservasi',
                'category_label' => 'RESERVASI & ANTREAN',
                'question' => 'Apakah saya wajib membuat akun untuk memesan jadwal?',
                'answer' => 'Ya, demi keamanan transaksi dan pencegahan pemesanan palsu (spam), pelanggan diwajibkan mendaftar dan memverifikasi kontak sebelum melakukan Booking Online.'
            ],
            [
                'id' => 5,
                'category' => 'reservasi',
                'category_label' => 'RESERVASI & ANTREAN',
                'question' => 'Apakah saya bisa memilih kapster favorit saat booking online?',
                'answer' => 'Tentu saja! Pada langkah pemilihan kapster, Anda dapat memilih kapster langganan Anda berdasarkan jadwal kerja dan keahliannya, atau memilih opsi "Siapa Saja (Tersedia)" untuk mendapatkan antrean tercepat.'
            ],
            [
                'id' => 6,
                'category' => 'reservasi',
                'category_label' => 'RESERVASI & ANTREAN',
                'question' => 'Bagaimana cara membatalkan atau mengubah jadwal booking yang sudah dibuat?',
                'answer' => 'Buka menu Riwayat Transaksi di Dashboard Pelanggan Anda. Anda dapat mengajukan pembatalan atau perubahan jadwal (reschedule) maksimal 1 jam sebelum sesi pengerjaan dimulai.'
            ],
            [
                'id' => 7,
                'category' => 'pembayaran',
                'category_label' => 'PEMBAYARAN & VOUCHER',
                'question' => 'Metode pembayaran apa saja yang diterima di Ran CukurIn?',
                'answer' => 'Kami menerima metode pembayaran fleksibel: Tunai (Cash) langsung di kasir kasir POS, QRIS (mendukung GoPay, OVO, Dana, ShopeePay, LinkAja, BCA, Mandiri), dan Transfer Bank.'
            ],
            [
                'id' => 8,
                'category' => 'pembayaran',
                'category_label' => 'PEMBAYARAN & VOUCHER',
                'question' => 'Mengapa kode voucher promo saya tidak dapat digunakan?',
                'answer' => 'Kode voucher mungkin telah melewati batas tanggal masa berlaku, kuota pemakaian kupon telah habis, atau total nominal belanja belum memenuhi syarat minimum transaksi.'
            ],
            [
                'id' => 9,
                'category' => 'layanan',
                'category_label' => 'LAYANAN & OPERASIONAL',
                'question' => 'Jam berapa operasional Ran CukurIn buka setiap hari?',
                'answer' => 'Ran CukurIn buka setiap hari dari hari Senin hingga Minggu mulai pukul 09:00 WIB sampai 21:00 WIB, melayani baik reservasi online maupun antrean langsung (walk-in).'
            ],
            [
                'id' => 10,
                'category' => 'layanan',
                'category_label' => 'LAYANAN & OPERASIONAL',
                'question' => 'Apakah ada garansi atau penyesuaian jika model rambut belum sesuai?',
                'answer' => 'Kepuasan Anda adalah prioritas utama kami. Anda dapat meminta kapster kami untuk merapikan atau menyesuaikan potongan rambut kembali selama Anda masih berada di kursi barbershop tanpa biaya tambahan.'
            ],
        ];
    }

    public function with() {
        $allItems = $this->getFaqItems();
        $q = trim(strtolower($this->searchQuery));

        $filteredItems = array_filter($allItems, function($item) use ($q) {
            $matchCategory = ($this->selectedCategory === 'all' || $item['category'] === $this->selectedCategory);
            $matchSearch = true;
            if ($q !== '') {
                $matchSearch = (
                    str_contains(strtolower($item['question']), $q) || 
                    str_contains(strtolower($item['answer']), $q) ||
                    str_contains(strtolower($item['category_label']), $q)
                );
            }
            return $matchCategory && $matchSearch;
        });

        // Hitung total per kategori
        $countAll = count($allItems);
        $countReservasi = count(array_filter($allItems, fn($i) => $i['category'] === 'reservasi'));
        $countPembayaran = count(array_filter($allItems, fn($i) => $i['category'] === 'pembayaran'));
        $countLayanan = count(array_filter($allItems, fn($i) => $i['category'] === 'layanan'));

        return [
            'faqItems' => $filteredItems,
            'countAll' => $countAll,
            'countReservasi' => $countReservasi,
            'countPembayaran' => $countPembayaran,
            'countLayanan' => $countLayanan,
        ];
    }
};
?>

<div class="bg-[#111215] font-['Inter',sans-serif] text-[#e2e2e2] min-h-screen flex flex-col selection:bg-[#ffc174]/30 selection:text-[#ffc174]">
    <!-- Navbar Reusable / Sticky Header -->
    <header x-data="{ mobileMenuOpen: false }" class="sticky top-0 w-full z-50 bg-[#111215]/90 backdrop-blur-xl border-b border-white/5 transition-all duration-300">
        <div class="h-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-6">
            <!-- Brand Logo -->
            <a href="{{ url('/') }}" class="flex items-center gap-3 group shrink-0">
                <div class="bg-[#ffc174] p-2 rounded-xl group-hover:scale-105 transition-transform shadow-md shadow-[#ffc174]/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[#2a1700] text-[22px]">content_cut</span>
                </div>
                <span class="font-['Montserrat',sans-serif] font-bold text-xl tracking-tight text-white">
                    Ran <span class="text-[#ffc174]">CukurIn</span>
                </span>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="hidden md:flex items-center gap-8 lg:gap-10">
                <a href="{{ route('home') }}" class="font-semibold text-sm text-[#9ca3af] hover:text-[#ffc174] transition-colors">Beranda</a>
                <a href="{{ route('katalog') }}" class="font-semibold text-sm text-[#9ca3af] hover:text-[#ffc174] transition-colors">Layanan</a>
                <a href="{{ route('booking.wizard') }}" class="font-semibold text-sm text-[#9ca3af] hover:text-[#ffc174] transition-colors">Booking</a>
                <a href="{{ route('faq') }}" class="font-bold text-sm text-[#ffc174] border-b-2 border-[#ffc174] pb-1 transition-colors">FAQ</a>
                
                @auth
                    @if(auth()->user()->role === 'Pemilik')
                        <a href="{{ route('admin.dashboard') }}" class="font-semibold text-sm text-[#9ca3af] hover:text-[#ffc174] transition-colors">Dashboard Owner</a>
                    @elseif(auth()->user()->role === 'Kapster')
                        <a href="{{ route('staff.dashboard') }}" class="font-semibold text-sm text-[#9ca3af] hover:text-[#ffc174] transition-colors">Dashboard Staff</a>
                    @else
                        <a href="{{ route('customer.dashboard') }}" class="font-semibold text-sm text-[#9ca3af] hover:text-[#ffc174] transition-colors">Dashboard Saya</a>
                    @endif
                @endauth
            </nav>

            <!-- Right CTA Button -->
            <div class="hidden md:flex items-center gap-4 shrink-0">
                @auth
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-[#9ca3af]">Hai, <strong class="text-white">{{ auth()->user()->nama }}</strong></span>
                        <a href="{{ route('booking.wizard') }}" class="bg-[#ffc174] hover:bg-[#ffb356] text-[#2a1700] px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider hover:scale-[1.02] transition-all shadow-md shadow-[#ffc174]/20">
                            Booking Sekarang
                        </a>
                    </div>
                @else
                    <a href="{{ route('booking.wizard') }}" class="bg-[#ffc174] hover:bg-[#ffb356] text-[#2a1700] px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider hover:scale-[1.02] transition-all shadow-md shadow-[#ffc174]/20">
                        Booking Sekarang
                    </a>
                @endauth
            </div>

            <!-- Mobile Hamburger Toggle -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-white hover:bg-white/10 rounded-xl transition-colors">
                <span class="material-symbols-outlined text-2xl" x-text="mobileMenuOpen ? 'close' : 'menu'">menu</span>
            </button>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div x-show="mobileMenuOpen" x-transition class="md:hidden bg-[#16181d] border-t border-white/5 absolute w-full px-6 py-5 space-y-3" style="display: none;">
            <a href="{{ route('home') }}" class="block font-semibold text-sm text-[#9ca3af] hover:text-[#ffc174] py-1.5">Beranda</a>
            <a href="{{ route('katalog') }}" class="block font-semibold text-sm text-[#9ca3af] hover:text-[#ffc174] py-1.5">Layanan</a>
            <a href="{{ route('booking.wizard') }}" class="block font-semibold text-sm text-[#9ca3af] hover:text-[#ffc174] py-1.5">Booking</a>
            <a href="{{ route('faq') }}" class="block font-bold text-sm text-[#ffc174] py-1.5">FAQ</a>
            <hr class="border-white/5 my-2">
            <a href="{{ route('booking.wizard') }}" class="block text-center bg-[#ffc174] text-[#2a1700] py-2.5 rounded-xl font-bold text-xs uppercase shadow-sm">Booking Sekarang</a>
        </div>
    </header>

    <!-- Main Content Body -->
    <main class="flex-1 w-full max-w-4xl mx-auto px-4 sm:px-6 py-10 md:py-16">
        
        <!-- Breadcrumb & Tag Badge -->
        <div class="flex flex-col items-center text-center space-y-3 mb-6">
            <div class="text-xs text-[#9ca3af] flex items-center gap-1.5 font-medium">
                <a href="{{ url('/') }}" class="hover:text-[#ffc174] transition-colors">Beranda</a>
                <span>/</span>
                <span class="text-[#ffc174]">Pusat Bantuan &amp; FAQ</span>
            </div>

            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#1b1d22] border border-[#ffc174]/30 text-xs font-semibold text-[#ffc174] shadow-sm">
                <span class="material-symbols-outlined text-[16px]">chat</span>
                <span>Pusat Bantuan/FAQ Cukurin</span>
            </div>
        </div>

        <!-- Title & Subtitle Header -->
        <div class="text-center max-w-2xl mx-auto mb-10">
            <h1 class="font-['Montserrat',sans-serif] font-black text-3xl sm:text-4xl md:text-5xl text-white tracking-tight mb-4">
                Frequently Asked <span class="text-[#ffc174]">Questions</span> (FAQ)
            </h1>
            <p class="text-[#9ca3af] text-sm sm:text-base leading-relaxed">
                Temukan jawaban untuk pertanyaan seputar pemesanan, antrean, dan layanan kami.
            </p>
        </div>

        <!-- Search Bar -->
        <div class="relative w-full max-w-2xl mx-auto mb-8">
            <div class="relative flex items-center">
                <span class="material-symbols-outlined absolute left-4 text-[#9ca3af] text-[20px]">search</span>
                <input 
                    type="text" 
                    wire:model.live.debounce.250ms="searchQuery"
                    placeholder="Cari pertanyaan / kata kunci..." 
                    class="w-full bg-[#181a1f] border border-white/10 focus:border-[#ffc174] text-white text-sm rounded-2xl pl-12 pr-10 py-3.5 outline-none transition-all placeholder:text-[#9ca3af]/60 shadow-lg shadow-black/20 focus:ring-1 focus:ring-[#ffc174]">
                @if(!empty($searchQuery))
                    <button 
                        type="button" 
                        wire:click="$set('searchQuery', '')" 
                        class="absolute right-3.5 text-[#9ca3af] hover:text-white transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                @endif
            </div>
        </div>

        <!-- Category Filter Tabs -->
        <div class="flex items-center justify-center gap-2.5 sm:gap-3 flex-wrap mb-10">
            <!-- Tab: Semua -->
            <button 
                type="button" 
                wire:click="setCategory('all')" 
                class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 cursor-pointer {{ $selectedCategory === 'all' ? 'bg-[#ffc174] text-[#2a1700] shadow-md shadow-[#ffc174]/20' : 'bg-[#181a1f] text-[#9ca3af] border border-white/5 hover:border-white/20 hover:text-white' }}">
                <span>Semua</span>
                <span class="px-1.5 py-0.2 rounded-md text-[11px] {{ $selectedCategory === 'all' ? 'bg-[#2a1700]/15 text-[#2a1700]' : 'bg-white/10 text-white' }} font-bold">
                    {{ $countAll }}
                </span>
            </button>

            <!-- Tab: Reservasi & Antrean -->
            <button 
                type="button" 
                wire:click="setCategory('reservasi')" 
                class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 cursor-pointer {{ $selectedCategory === 'reservasi' ? 'bg-[#ffc174] text-[#2a1700] shadow-md shadow-[#ffc174]/20' : 'bg-[#181a1f] text-[#9ca3af] border border-white/5 hover:border-white/20 hover:text-white' }}">
                <span class="material-symbols-outlined text-[18px]">calendar_month</span>
                <span>Reservasi &amp; Antrean</span>
                <span class="px-1.5 py-0.2 rounded-md text-[11px] {{ $selectedCategory === 'reservasi' ? 'bg-[#2a1700]/15 text-[#2a1700]' : 'bg-white/10 text-white' }} font-bold">
                    {{ $countReservasi }}
                </span>
            </button>

            <!-- Tab: Pembayaran & Voucher -->
            <button 
                type="button" 
                wire:click="setCategory('pembayaran')" 
                class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 cursor-pointer {{ $selectedCategory === 'pembayaran' ? 'bg-[#ffc174] text-[#2a1700] shadow-md shadow-[#ffc174]/20' : 'bg-[#181a1f] text-[#9ca3af] border border-white/5 hover:border-white/20 hover:text-white' }}">
                <span class="material-symbols-outlined text-[18px]">payments</span>
                <span>Pembayaran &amp; Voucher</span>
                <span class="px-1.5 py-0.2 rounded-md text-[11px] {{ $selectedCategory === 'pembayaran' ? 'bg-[#2a1700]/15 text-[#2a1700]' : 'bg-white/10 text-white' }} font-bold">
                    {{ $countPembayaran }}
                </span>
            </button>

            <!-- Tab: Layanan & Operasional -->
            <button 
                type="button" 
                wire:click="setCategory('layanan')" 
                class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 cursor-pointer {{ $selectedCategory === 'layanan' ? 'bg-[#ffc174] text-[#2a1700] shadow-md shadow-[#ffc174]/20' : 'bg-[#181a1f] text-[#9ca3af] border border-white/5 hover:border-white/20 hover:text-white' }}">
                <span class="material-symbols-outlined text-[18px]">content_cut</span>
                <span>Layanan &amp; Operasional</span>
                <span class="px-1.5 py-0.2 rounded-md text-[11px] {{ $selectedCategory === 'layanan' ? 'bg-[#2a1700]/15 text-[#2a1700]' : 'bg-white/10 text-white' }} font-bold">
                    {{ $countLayanan }}
                </span>
            </button>
        </div>

        <!-- FAQ Accordion List -->
        <div class="space-y-4 mb-16">
            @forelse($faqItems as $item)
                @php
                    $isOpen = ($openQuestionId === $item['id']);
                    $hasFeedback = isset($feedbackGiven[$item['id']]);
                @endphp
                <div class="bg-[#181a20] rounded-2xl border transition-all duration-200 overflow-hidden {{ $isOpen ? 'border-[#ffc174]/50 shadow-xl shadow-black/40' : 'border-white/5 hover:border-white/15' }}">
                    <!-- Accordion Trigger Button -->
                    <button 
                        type="button" 
                        wire:click="toggleQuestion({{ $item['id'] }})"
                        class="w-full p-5 sm:p-6 text-left flex items-start sm:items-center justify-between gap-4 cursor-pointer">
                        <div class="flex items-start sm:items-center gap-3.5 sm:gap-4 flex-1">
                            <!-- Question Number Badge -->
                            <div class="w-8 h-8 rounded-lg shrink-0 flex items-center justify-center font-bold text-xs {{ $isOpen ? 'bg-[#ffc174] text-[#2a1700]' : 'bg-[#252830] text-[#ffc174]' }} transition-colors">
                                Q{{ $item['id'] }}
                            </div>

                            <div class="space-y-1 flex-1">
                                <!-- Category Tag -->
                                <span class="text-[10px] font-bold tracking-wider text-[#ffc174] uppercase block">
                                    {{ $item['category_label'] }}
                                </span>
                                <!-- Question Title -->
                                <h3 class="font-['Montserrat',sans-serif] font-bold text-sm sm:text-base text-white leading-snug">
                                    {{ $item['question'] }}
                                </h3>
                            </div>
                        </div>

                        <!-- Expand Chevron Icon -->
                        <div class="p-1 rounded-lg bg-[#252830] text-[#9ca3af] shrink-0 transition-transform duration-200 {{ $isOpen ? 'rotate-180 text-[#ffc174]' : '' }}">
                            <span class="material-symbols-outlined text-[20px] block">expand_more</span>
                        </div>
                    </button>

                    <!-- Accordion Collapsible Content -->
                    @if($isOpen)
                        <div class="px-5 pb-6 sm:px-6 sm:pb-6 pt-1 text-sm text-[#9ca3af] leading-relaxed border-t border-white/5">
                            <div class="flex items-start gap-3 mt-3">
                                <span class="text-[#ffc174] text-base leading-tight mt-0.5">•</span>
                                <p class="flex-1 text-neutral-300">
                                    {{ $item['answer'] }}
                                </p>
                            </div>

                            <!-- Helpful Feedback Section -->
                            <div class="mt-6 pt-4 border-t border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                <span class="text-[#9ca3af]">Apakah jawaban ini membantu?</span>
                                
                                @if(!$hasFeedback)
                                    <div class="flex items-center gap-2">
                                        <button 
                                            type="button" 
                                            wire:click="giveFeedback({{ $item['id'] }}, 'yes')" 
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#252830] hover:bg-[#303440] text-neutral-300 hover:text-white transition-colors cursor-pointer border border-white/5">
                                            <span>👍</span>
                                            <span>Ya</span>
                                        </button>
                                        <button 
                                            type="button" 
                                            wire:click="giveFeedback({{ $item['id'] }}, 'no')" 
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#252830] hover:bg-[#303440] text-neutral-300 hover:text-white transition-colors cursor-pointer border border-white/5">
                                            <span>👎</span>
                                            <span>Tidak</span>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-emerald-400 font-medium flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">check_circle</span>
                                        <span>Terima kasih atas tanggapan Anda!</span>
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center py-16 px-4 bg-[#181a20] rounded-2xl border border-white/5">
                    <span class="material-symbols-outlined text-5xl text-[#9ca3af]/40 mb-3">search_off</span>
                    <h3 class="font-['Montserrat',sans-serif] font-bold text-base text-white mb-1">Pertanyaan tidak ditemukan</h3>
                    <p class="text-xs text-[#9ca3af] max-w-sm mx-auto mb-4">
                        Maaf, kami tidak menemukan jawaban yang cocok dengan kata kunci "{{ $searchQuery }}".
                    </p>
                    <button 
                        type="button" 
                        wire:click="$set('searchQuery', ''); $set('selectedCategory', 'all');" 
                        class="px-4 py-2 bg-[#ffc174] text-[#2a1700] rounded-xl text-xs font-bold hover:bg-[#ffb356] transition-colors cursor-pointer">
                        Reset Pencarian
                    </button>
                </div>
            @endforelse
        </div>

        <!-- Masih punya pertanyaan yang belum terjawab? (Support Card CTA) -->
        <div class="bg-[#181a20] rounded-3xl border border-white/5 p-8 sm:p-12 text-center shadow-2xl relative overflow-hidden">
            <!-- Glow background -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-80 h-80 bg-[#ffc174]/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 max-w-lg mx-auto flex flex-col items-center">
                <!-- Icon badge -->
                <div class="w-12 h-12 rounded-2xl bg-[#252830] border border-white/10 flex items-center justify-center text-[#ffc174] mb-5 shadow-inner">
                    <span class="material-symbols-outlined text-[24px]">chat_bubble</span>
                </div>

                <!-- Title -->
                <h2 class="font-['Montserrat',sans-serif] font-black text-xl sm:text-2xl text-white tracking-tight mb-2">
                    Masih punya pertanyaan yang belum terjawab?
                </h2>

                <!-- Subtitle -->
                <p class="text-xs sm:text-sm text-[#9ca3af] mb-6">
                    Masih bingung? Tim layanan pelanggan kami siap membantu Anda.
                </p>

                <!-- WhatsApp Button -->
                <a 
                    href="https://wa.me/6281234567890?text=Halo%20Admin%20Ran%20Cukurin,%20saya%20ingin%20bertanya%20seputar%20layanan%20atau%20antrean." 
                    target="_blank" 
                    class="inline-flex items-center justify-center gap-2.5 px-6 py-3.5 bg-[#10b981] hover:bg-[#059669] text-white rounded-xl font-bold text-xs sm:text-sm transition-all duration-200 shadow-lg shadow-[#10b981]/25 hover:scale-[1.02] active:scale-[0.98]">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.058.376-.058c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.1.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.186 1.564 5.945l-1.664 6.084 6.223-1.633c1.704.928 3.659 1.454 5.74 1.454 6.627 0 12-5.373 12-12 0-6.627-5.373-12-12-12z"/>
                    </svg>
                    <span>Chat via WhatsApp</span>
                </a>

                <!-- Timing Subtext -->
                <p class="text-[11px] text-[#9ca3af]/80 mt-3.5">
                    Respon cepat: Senin - Minggu (09:00 - 21:00 WIB)
                </p>
            </div>
        </div>

    </main>

    <!-- Footer Simple Clean -->
    <footer class="w-full border-t border-white/5 bg-[#0f1013] py-8 text-xs text-[#9ca3af]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
            <div class="flex items-center gap-2">
                <span class="font-['Montserrat',sans-serif] font-bold text-white">Ran CukurIn</span>
                <span class="text-neutral-600">—</span>
                <span>Precision Grooming &amp; Smart Booking System</span>
            </div>
            <div>
                © {{ date('Y') }} Ran Cukurin. Seluruh hak cipta dilindungi undang-undang.
            </div>
        </div>
    </footer>
</div>
