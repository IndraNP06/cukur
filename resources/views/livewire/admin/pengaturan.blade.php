<?php

use App\Models\Pengaturan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Pengaturan Sistem - Ran CukurIn')] class extends Component {
    public $pengaturanList = [];
    public $form = [];

    public function mount()
    {
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $settings = Pengaturan::all();
        $this->pengaturanList = $settings;

        foreach ($settings as $setting) {
            $this->form[$setting->key] = $setting->value;
        }

        // Initialize defaults if empty
        if (empty($this->form)) {
            $this->form = [
                'nama_toko' => 'Ran CukurIn',
                'alamat_toko' => 'Jl. Contoh No. 123',
                'no_hp_toko' => '081234567890',
                'jam_buka' => '09:00',
                'jam_tutup' => '21:00',
            ];
        }
    }

    public function save()
    {
        foreach ($this->form as $key => $value) {
            Pengaturan::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        session()->flash('message', 'Pengaturan berhasil disimpan.');
        $this->loadSettings();
    }
};
?>

<div class="flex-grow flex flex-col relative bg-background">
    <div class="flex flex-col w-full h-full p-4 lg:p-5 space-y-8 text-on-surface">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="font-headline-lg text-primary tracking-tight text-headline-lg">Pengaturan Sistem</h1>
                <p class="text-body-md font-body-md text-on-surface-variant mt-1">Konfigurasi umum aplikasi Ran CukurIn.</p>
            </div>
        </header>

        @if (session()->has('message'))
            <div class="p-4 bg-emerald-500/10 text-emerald-500 rounded-xl">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-surface-container rounded-2xl shadow-xl overflow-hidden max-w-4xl">
            <div class="p-4 lg:p-5 border-b border-surface-container-high bg-surface-container-lowest">
                <h3 class="font-headline-md text-on-surface text-headline-md">Informasi Bisnis</h3>
            </div>
            <form wire:submit="save" class="p-4 lg:p-5 sm:p-10 space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block font-label-md text-label-md text-on-surface">Nama Toko/Outlet</label>
                        <input wire:model="form.nama_toko" type="text" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                    </div>

                    <div class="space-y-2">
                        <label class="block font-label-md text-label-md text-on-surface">Nomor HP/WhatsApp Outlet</label>
                        <input wire:model="form.no_hp_toko" type="text" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block font-label-md text-label-md text-on-surface">Alamat Lengkap</label>
                    <textarea wire:model="form.alamat_toko" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors resize-none" rows="3" required></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-surface-container-high">
                    <div class="space-y-2">
                        <label class="block font-label-md text-label-md text-on-surface">Jam Buka Operasional</label>
                        <input wire:model="form.jam_buka" type="time" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                    </div>

                    <div class="space-y-2">
                        <label class="block font-label-md text-label-md text-on-surface">Jam Tutup Operasional</label>
                        <input wire:model="form.jam_tutup" type="time" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                    </div>
                </div>

                <div class="pt-6 flex justify-end gap-3 border-t border-surface-container-high mt-6">
                    <button type="submit" class="px-5 py-3 rounded-full font-label-md text-label-md bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors shadow-lg shadow-primary/20 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">save</span>
                        <span wire:loading.remove wire:target="save">Simpan Pengaturan</span>
                        <span wire:loading wire:target="save">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
