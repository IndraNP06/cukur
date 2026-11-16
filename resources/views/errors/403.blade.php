<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Akses Terlarang (403) - Ran CukurIn</title>
    @vite(['resources/css/app.css'])
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;family=Montserrat:wght@100..900&amp;display=swap" rel="stylesheet">
</head>
<body class="bg-background font-body-md text-on-background">
    <div class="flex flex-col w-full min-h-screen justify-center items-center relative overflow-hidden bg-background">
        <!-- Subtle Red Glow -->
        <div class="absolute w-[800px] h-[800px] bg-error/5 rounded-full blur-[120px] pointer-events-none mix-blend-screen"></div>
        
        <div class="relative w-full max-w-2xl px-6 z-10 flex flex-col items-center">
            <!-- Background 403 Text -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-[15rem] md:text-[20rem] font-headline-xl font-bold text-surface-container-low/50 pointer-events-none select-none z-0 tracking-tighter">
                403
            </div>
            
            <div class="relative z-10 bg-surface-container-high rounded-2xl shadow-2xl p-10 md:p-14 w-full flex flex-col items-center text-center">
<span class="material-symbols-outlined text-[64px] text-error/80 filter drop-shadow-[0_0_15px_rgba(255,180,171,0.2)]">gpp_bad</span>
                
                <h1 class="text-headline-md font-headline-xl text-on-surface mb-4">Akses Terlarang!</h1>
                <p class="text-body-lg font-body-lg text-on-surface-variant max-w-md mb-8">
                    Maaf, {{ $exception->getMessage() ?: 'Anda tidak memiliki tingkat otorisasi atau peran (role) yang cukup untuk melihat direktori atau halaman ini.' }}
                </p>
                
                <div class="w-full bg-surface-container-lowest rounded-xl p-4 mb-10 text-left font-mono text-sm text-error/80 shadow-inner">
                    <span class="text-on-surface-variant/50 mr-2">&gt;</span>
                    SYSTEM_LOG: Unauthorized role permission blocked by RBAC middleware.
                    <span class="inline-block w-2 h-4 bg-error/50 ml-1 animate-pulse align-middle"></span>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-4 w-full justify-center">
                    <button class="px-8 py-4 bg-secondary-container hover:bg-secondary-container/80 text-on-secondary-container font-label-md text-label-md rounded-xl transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2" onclick="history.back()">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        Kembali ke Sebelumnya
                    </button>
                    @auth
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="px-8 py-4 w-full bg-transparent hover:bg-surface-container-highest text-primary font-label-md text-label-md rounded-xl transition-all flex items-center justify-center gap-2 ring-1 ring-outline-variant hover:ring-outline">
                            <span class="material-symbols-outlined text-[18px]">logout</span>
                            Keluar &amp; Ganti Akun
                        </button>
                    </form>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</body>
</html>
