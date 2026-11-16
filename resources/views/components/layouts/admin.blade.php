<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta content="width=device-width, initial-scale=1.0" name="viewport">
 <title>{{ $title ?? 'Admin Dashboard - Ran Cukurin' }}</title>
 @vite(['resources/css/app.css', 'resources/js/app.js'])
 <style>@layer base{html,body{margin:0;padding:0;}body{overscroll-behavior:none;}main>:first-child{margin-top:0!important;}main>:last-child{margin-bottom:0!important;}}::-webkit-scrollbar{display:none;}</style>
 <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
 <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
 @livewireStyles
</head>
<body class="bg-background font-body-md text-on-background" x-data="{ sidebarOpen: false }">
 <!-- MOBILE OVERLAY -->
 <div x-show="sidebarOpen" class="fixed inset-0 bg-black/50 z-40 lg:hidden" @click="sidebarOpen = false" x-transition.opacity style="display: none;"></div>

 <!-- REUSABLE SIDEBAR COMPONENT -->
 <x-sidebar />

 <div class="lg:pl-64 flex flex-col min-h-screen transition-all">
 <!-- REUSABLE NAVBAR COMPONENT (ADMIN TYPE) -->
 <x-navbar type="admin" />

 <main class="relative pt-16 flex-grow flex flex-col bg-background p-4 lg:p-margin-desktop overflow-x-hidden">
 {{ $slot }}
 </main>
 </div>
 
 @livewireScripts
</body>
</html>
