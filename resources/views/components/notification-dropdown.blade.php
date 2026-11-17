@php
    $user = auth()->user();
    $notifications = $user ? \App\Services\NotificationService::getForUser($user) : [];
    $unreadCount = count(array_filter($notifications, fn($n) => $n['is_unread']));
@endphp

@if($user)
<div x-data="{ 
    open: false, 
    unreadCount: {{ $unreadCount }},
    markAllRead() {
        this.unreadCount = 0;
        fetch('{{ route('notifikasi.mark-read') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        }).catch(() => {});
    }
}" class="relative">
    <button @click="open = !open" @click.away="open = false" class="relative flex items-center justify-center p-2 rounded-full hover:bg-surface-container-highest transition-colors cursor-pointer text-on-surface-variant hover:text-primary">
        <span class="material-symbols-outlined text-[22px]">notifications</span>
        <template x-if="unreadCount > 0">
            <span class="absolute top-1 right-1 flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-error opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-error border-2 border-surface"></span>
            </span>
        </template>
    </button>

    <div x-show="open" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 mt-2 w-80 sm:w-96 bg-surface border border-outline-variant/30 rounded-2xl shadow-2xl z-50 overflow-hidden" 
         style="display: none;">
        
        <!-- Header -->
        <div class="px-4 py-3.5 border-b border-outline-variant/30 flex items-center justify-between bg-surface-container-lowest">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-lg">notifications</span>
                <h3 class="font-headline-md text-sm text-on-surface">Notifikasi</h3>
                <template x-if="unreadCount > 0">
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-error/15 text-error" x-text="unreadCount + ' baru'"></span>
                </template>
            </div>
            <template x-if="unreadCount > 0">
                <button type="button" @click="markAllRead()" class="text-[11px] font-semibold text-primary hover:underline cursor-pointer">
                    Tandai dibaca
                </button>
            </template>
        </div>

        <!-- Notification List -->
        <div class="max-h-80 overflow-y-auto divide-y divide-outline-variant/15">
            @forelse($notifications as $item)
                <a href="{{ $item['url'] }}" @click="open = false" class="block p-3.5 hover:bg-surface-container-high transition-colors {{ $item['is_unread'] ? 'bg-primary/5' : '' }}">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-xl {{ $item['bg'] }} {{ $item['color'] }} flex items-center justify-center shrink-0 mt-0.5">
                            <span class="material-symbols-outlined text-[18px]">{{ $item['icon'] }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 mb-0.5">
                                <p class="font-label-md text-xs font-semibold text-on-surface truncate">{{ $item['judul'] }}</p>
                                @if($item['is_unread'])
                                    <span class="w-2 h-2 rounded-full bg-primary shrink-0"></span>
                                @endif
                            </div>
                            <p class="font-body-sm text-[12px] text-on-surface-variant line-clamp-2 leading-relaxed">{{ $item['pesan'] }}</p>
                            <span class="text-[10px] text-on-surface-variant/70 mt-1 block">{{ $item['waktu'] }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="py-8 px-4 text-center">
                    <div class="w-12 h-12 rounded-full bg-surface-container mx-auto flex items-center justify-center text-on-surface-variant mb-2">
                        <span class="material-symbols-outlined text-2xl">notifications_none</span>
                    </div>
                    <p class="font-label-md text-xs font-semibold text-on-surface">Tidak ada notifikasi baru</p>
                    <p class="font-body-sm text-[11px] text-on-surface-variant mt-0.5">Semua pembaruan aktivitas Anda akan muncul di sini.</p>
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        <div class="p-2.5 bg-surface-container-lowest border-t border-outline-variant/20 text-center">
            @if($user->role === 'Pelanggan')
                <a href="{{ route('customer.riwayat') }}" class="text-[11px] font-semibold text-primary hover:underline">Lihat Riwayat Lengkap &rarr;</a>
            @elseif($user->role === 'Kapster')
                <a href="{{ route('staff.dashboard') }}" class="text-[11px] font-semibold text-primary hover:underline">Buka Dashboard Staff &rarr;</a>
            @else
                <a href="{{ route('admin.antrean') }}" class="text-[11px] font-semibold text-primary hover:underline">Buka Papan Antrean &rarr;</a>
            @endif
        </div>
    </div>
</div>
@endif
