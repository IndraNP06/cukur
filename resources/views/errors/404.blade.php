<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>404 - Waduh! Kursi Ini Kosong | Ran CukurIn</title>
    @vite(['resources/css/app.css'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@700;800;900&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body class="bg-[#121316] font-['Inter',sans-serif] text-[#e2e2e2] min-h-screen flex flex-col justify-center items-center relative overflow-hidden select-none m-0 p-4">
    <!-- Subtle Background Glow -->
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[550px] h-[550px] bg-[#ffc174]/5 rounded-full blur-[120px]"></div>
    </div>

    <div class="relative z-10 flex flex-col items-center text-center max-w-xl mx-auto px-4 py-8">
        <!-- 404 Visual Graphic -->
        <div class="flex items-center justify-center select-none mb-6">
            <span class="text-[120px] sm:text-[160px] md:text-[190px] font-black text-[#23252a] leading-none tracking-tight font-['Montserrat',sans-serif] drop-shadow-md">
                4
            </span>

            <!-- Center Circle with Barbershop Scissors -->
            <div class="w-24 h-24 sm:w-32 sm:h-32 md:w-36 md:h-36 rounded-full bg-[#1e2025] border border-white/5 shadow-2xl flex items-center justify-center relative -mx-3 sm:-mx-5 md:-mx-6 z-10 transition-transform duration-300 hover:scale-105">
                <svg class="w-12 h-12 sm:w-16 sm:h-16 text-[#ffb95f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="6" cy="6" r="3"></circle>
                    <circle cx="6" cy="18" r="3"></circle>
                    <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                    <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                    <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
                </svg>
            </div>

            <span class="text-[120px] sm:text-[160px] md:text-[190px] font-black text-[#23252a] leading-none tracking-tight font-['Montserrat',sans-serif] drop-shadow-md">
                4
            </span>
        </div>

        <!-- Typography Heading -->
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-white tracking-tight mb-4 font-['Montserrat',sans-serif]">
            Waduh! Kursi Ini Kosong.
        </h1>

        <!-- Subtitle Description -->
        <p class="text-[#9ca3af] text-sm sm:text-base max-w-md mx-auto leading-relaxed mb-8">
            Halaman yang Anda cari sepertinya sedang dicukur habis, dipindah, atau memang tidak pernah ada di sistem kami.
        </p>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 sm:gap-6 w-full">
            <a href="{{ url('/') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 bg-[#ffc174] hover:bg-[#ffb356] text-[#2a1700] rounded-2xl font-bold text-sm transition-all duration-200 shadow-lg shadow-black/20 hover:scale-[1.02] active:scale-[0.98]">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                <span>Kembali ke Beranda</span>
            </a>

            <button type="button" onclick="openSupportModal()" class="w-full sm:w-auto inline-flex items-center justify-center text-[#ffc174] hover:text-[#ffddb8] font-bold text-sm transition-colors py-3.5 px-4 cursor-pointer hover:underline">
                Hubungi Bantuan Tim IT
            </button>
        </div>
    </div>

    <!-- Modal Hubungi Bantuan Tim IT -->
    <div id="supportModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/75 backdrop-blur-sm p-4 animate-[fade-in_0.2s_ease-out]">
        <div class="bg-[#202227] border border-white/10 rounded-3xl shadow-2xl p-6 sm:p-8 w-full max-w-md text-left relative">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-[#ffc174] text-2xl">support_agent</span>
                    <h3 class="font-['Montserrat',sans-serif] font-bold text-lg text-white">Bantuan Tim IT</h3>
                </div>
                <button type="button" onclick="closeSupportModal()" class="text-neutral-400 hover:text-white transition-colors cursor-pointer">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <p class="text-xs text-neutral-300 mb-5 leading-relaxed">
                Jika Anda menduga ini merupakan kesalahan sistem pada aplikasi <strong>Ran Cukurin</strong>, silakan hubungi tim IT kami:
            </p>

            <div class="space-y-3 mb-6 text-xs sm:text-sm">
                <a href="https://wa.me/6281234567890?text=Halo%20Tim%20IT%20Ran%20Cukurin,%20saya%20menemukan%20kendala%20halaman%20404." target="_blank" class="flex items-center justify-between p-3.5 rounded-xl bg-[#17181c] border border-white/5 hover:border-[#ffc174]/40 transition-colors text-white group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-400">chat</span>
                        <div>
                            <div class="font-bold">WhatsApp Tim IT</div>
                            <div class="text-[11px] text-neutral-400">+62 812-3456-7890</div>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-neutral-400 group-hover:text-[#ffc174] group-hover:translate-x-1 transition-all text-base">arrow_forward</span>
                </a>

                <a href="mailto:support@rancukurin.com?subject=Laporan%20Error%20404%20Ran%20Cukurin" class="flex items-center justify-between p-3.5 rounded-xl bg-[#17181c] border border-white/5 hover:border-[#ffc174]/40 transition-colors text-white group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-blue-400">mail</span>
                        <div>
                            <div class="font-bold">Email Support</div>
                            <div class="text-[11px] text-neutral-400">support@rancukurin.com</div>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-neutral-400 group-hover:text-[#ffc174] group-hover:translate-x-1 transition-all text-base">arrow_forward</span>
                </a>
            </div>

            <button type="button" onclick="closeSupportModal()" class="w-full py-3 bg-[#2a2d34] hover:bg-[#343842] text-white rounded-xl font-semibold text-xs transition-colors cursor-pointer">
                Tutup
            </button>
        </div>
    </div>

    <script>
        function openSupportModal() {
            const modal = document.getElementById('supportModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function closeSupportModal() {
            const modal = document.getElementById('supportModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
</body>
</html>
