<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>403 - Akses Terlarang | Ran CukurIn</title>
    @vite(['resources/css/app.css'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@700;800;900&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body class="bg-[#121316] font-['Inter',sans-serif] text-[#e2e2e2] min-h-screen flex flex-col justify-center items-center relative overflow-hidden select-none m-0 p-4">
    <!-- Subtle Ambient Glow -->
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-rose-500/5 rounded-full blur-[140px]"></div>
    </div>

    <div class="relative z-10 w-full max-w-lg mx-auto">
        <!-- Main Card -->
        <div class="bg-[#212328] border border-white/5 rounded-[28px] shadow-2xl shadow-black/80 p-8 sm:p-12 w-full flex flex-col items-center text-center">
            
            <!-- Access Denied Shield Icon -->
            <div class="mb-6 flex items-center justify-center">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-[#2a2d35] border border-white/10 flex items-center justify-center shadow-lg relative group transition-transform duration-300 hover:scale-105">
                    <svg class="w-9 h-9 sm:w-11 sm:h-11 text-[#ff8a80]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <!-- Shield Outline -->
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="rgba(255, 138, 128, 0.08)"/>
                        <!-- Lock Padlock -->
                        <rect x="9" y="11" width="6" height="5" rx="1" fill="currentColor"/>
                        <path d="M10 11V9a2 2 0 1 1 4 0v2" stroke="currentColor"/>
                    </svg>
                </div>
            </div>

            <!-- Title -->
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mb-3 font-['Montserrat',sans-serif]">
                Akses Terlarang!
            </h1>

            <!-- Description -->
            <p class="text-[#9ca3af] text-sm sm:text-base leading-relaxed max-w-sm mx-auto mb-6">
                Maaf, Anda tidak memiliki tingkat otorisasi atau peran (role) yang cukup untuk melihat direktori atau halaman ini.
            </p>

            <!-- Terminal System Log Box -->
            <div class="w-full bg-[#131417] rounded-xl p-4 sm:p-5 mb-8 text-left font-mono text-xs sm:text-sm text-[#fca5a5] border border-white/5 flex items-start gap-2.5 shadow-inner">
                <span class="text-neutral-500 font-bold select-none">&gt;</span>
                <span class="flex-1 leading-snug">
                    SYSTEM_LOG: Unauthorized role permission blocked by RBAC middleware.
                    <span class="inline-block w-2 h-4 bg-[#f87171] ml-1 animate-pulse align-middle"></span>
                </span>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center gap-3.5 w-full">
                <!-- Button 1: Kembali ke Sebelumnya -->
                <button type="button" onclick="history.back()" class="w-full sm:flex-1 inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-[#3a4050] hover:bg-[#454c60] text-[#dae2fd] rounded-xl font-semibold text-xs sm:text-sm transition-all duration-200 shadow-md active:scale-[0.98] cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    <span>Kembali ke Sebelumnya</span>
                </button>

                <!-- Button 2: Keluar & Ganti Akun -->
                @auth
                <form method="POST" action="{{ route('logout') }}" class="w-full sm:flex-1">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-transparent hover:bg-white/5 text-[#ffc174] border border-[#ffc174]/40 hover:border-[#ffc174] rounded-xl font-semibold text-xs sm:text-sm transition-all duration-200 active:scale-[0.98] cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        <span>Keluar &amp; Ganti Akun</span>
                    </button>
                </form>
                @else
                <a href="{{ route('login') }}" class="w-full sm:flex-1 inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-transparent hover:bg-white/5 text-[#ffc174] border border-[#ffc174]/40 hover:border-[#ffc174] rounded-xl font-semibold text-xs sm:text-sm transition-all duration-200 active:scale-[0.98]">
                    <span class="material-symbols-outlined text-[18px]">login</span>
                    <span>Keluar &amp; Ganti Akun</span>
                </a>
                @endauth
            </div>
        </div>
    </div>
</body>
</html>
