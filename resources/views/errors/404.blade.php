<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Halaman Tidak Ditemukan (404) - Ran CukurIn</title>
    @vite(['resources/css/app.css'])
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;family=Montserrat:wght@100..900&amp;display=swap" rel="stylesheet">
</head>
<body class="bg-background font-body-md text-on-background">
    <div class="flex flex-col w-full items-center justify-center min-h-screen relative overflow-hidden bg-background">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-primary/5 rounded-full blur-[100px]"></div>
        </div>
        
        <div class="relative z-10 flex flex-col items-center text-center px-4 max-w-2xl mx-auto">
            <div class="relative mb-8 flex justify-center">
                <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-[150px] md:text-[200px] font-headline-xl font-black text-surface-container-highest opacity-80 leading-none select-none tracking-tighter mix-blend-screen">404</span>
                <div class="relative z-10 w-12 h-12 md:w-40 md:h-40 bg-surface-container-high rounded-full flex items-center justify-center shadow-2xl shadow-black/50 animate-[bounce_3s_ease-in-out_infinite]">
                    <span class="material-symbols-outlined text-[64px] md:text-[80px] text-primary" style="font-variation-settings: 'FILL' 1;">content_cut</span>
                </div>
            </div>
            
            <h1 class="font-headline-xl text-headline-md text-on-background mb-4">Waduh! Kursi Ini Kosong.</h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant mb-10 max-w-lg mx-auto">
                Halaman yang Anda cari sepertinya sedang dicukur habis, dipindah, atau memang tidak pernah ada di sistem kami.
            </p>
            
            <div class="flex flex-col sm:flex-row items-center gap-4 w-full sm:w-auto">
                <a href="{{ url('/') }}" class="group w-full sm:w-auto flex items-center justify-center gap-2 px-8 py-4 bg-primary text-on-primary rounded-xl font-label-md text-label-md transition-all duration-300 hover:scale-105 hover:bg-primary/90 hover:shadow-[0_0_20px_rgba(245,158,11,0.2)]">
                    <span class="material-symbols-outlined text-[20px] transition-transform group-hover:-translate-x-1">arrow_back</span>
                    Kembali ke Beranda
                </a>
                <button onclick="history.back()" class="w-full sm:w-auto px-8 py-4 rounded-xl font-label-md text-label-md text-primary hover:bg-primary/10 transition-colors">
                    Kembali ke Sebelumnya
                </button>
            </div>
        </div>
    </div>
</body>
</html>
