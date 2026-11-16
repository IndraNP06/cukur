<!DOCTYPE html>
<html lang="id">
<head>
 <meta charset="utf-8">
 <meta content="width=device-width, initial-scale=1.0" name="viewport">
 <title>{{ $title ?? 'Ran CukurIn' }}</title>
 
 @vite(['resources/css/app.css', 'resources/js/app.js'])
 
 <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
 <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
 @livewireStyles
</head>
<body class="bg-background font-body-md text-on-surface antialiased flex flex-col min-h-screen">
 <!-- REUSABLE NAVBAR COMPONENT (CUSTOMER TYPE) -->
 <x-navbar type="customer" />

 <main class="pt-20 lg:pt-24 flex-grow flex flex-col">
 {{ $slot }}
 </main>

 <!-- REUSABLE FOOTER COMPONENT -->
 <x-footer />
 
 @livewireScripts
</body>
</html>
