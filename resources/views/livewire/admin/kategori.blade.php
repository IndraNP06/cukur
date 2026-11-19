<?php

use App\Models\KategoriLayanan;
use App\Models\KategoriBarang;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Kelola Kategori - Ran CukurIn')] class extends Component {
    use WithPagination;

    public $activeTab = 'layanan'; // layanan or barang
    public $search = '';

    public $showModal = false;
    public $isEdit = false;
    public $kategoriId;
    public $nama_kategori;
    public $deskripsi;
    public $is_active = true;

    public function rules() {
        return [
        'nama_kategori' => 'required|string|max:255',
        'deskripsi' => 'nullable|string',
        'is_active' => 'boolean',
    ];
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function create()
    {
        $this->resetValidation();
        $this->reset(['kategoriId', 'nama_kategori', 'deskripsi']);
        $this->is_active = true;
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetValidation();
        $kategori = $this->activeTab === 'layanan' 
            ? KategoriLayanan::findOrFail($id) 
            : KategoriBarang::findOrFail($id);
            
        $this->kategoriId = $kategori->id;
        $this->nama_kategori = $kategori->nama_kategori;
        $this->deskripsi = $kategori->deskripsi;
        $this->is_active = $kategori->is_active;
        
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'nama_kategori' => $this->nama_kategori,
            'deskripsi' => $this->deskripsi,
            'is_active' => $this->is_active,
        ];

        if ($this->isEdit) {
            $kategori = $this->activeTab === 'layanan' 
                ? KategoriLayanan::findOrFail($this->kategoriId) 
                : KategoriBarang::findOrFail($this->kategoriId);
            
            $kategori->update($data);
            session()->flash('message', 'Kategori berhasil diperbarui.');
        } else {
            if ($this->activeTab === 'layanan') {
                KategoriLayanan::create($data);
            } else {
                KategoriBarang::create($data);
            }
            session()->flash('message', 'Kategori baru berhasil ditambahkan.');
        }
        $this->showModal = false;
    }

    public function deleteKategori($id)
    {
        $kategori = $this->activeTab === 'layanan' 
            ? KategoriLayanan::findOrFail($id) 
            : KategoriBarang::findOrFail($id);
            
        $kategori->delete();
        session()->flash('message', 'Kategori berhasil dihapus.');
    }

    public function with()
    {
        $model = $this->activeTab === 'layanan' ? KategoriLayanan::query() : KategoriBarang::query();

        if ($this->search) {
            $model->where('nama_kategori', 'like', '%' . $this->search . '%');
        }

        return [
            'kategoris' => $model->latest()->paginate(10)
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
};
?>

<div class="flex-grow flex flex-col relative bg-background">
    <div class="flex flex-col w-full h-full p-4 lg:p-5 space-y-8 text-on-surface">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="font-headline-lg text-primary tracking-tight text-headline-lg">Kelola Kategori</h1>
                <p class="text-body-md font-body-md text-on-surface-variant mt-1">Manajemen kategori layanan dan barang untuk sistem.</p>
            </div>
            <button wire:click="create" class="flex items-center gap-2 px-4 lg:px-5 py-3 bg-primary text-on-primary rounded-lg font-label-md hover:scale-105 transition-transform duration-200 shadow-md">
                <span class="material-symbols-outlined text-[20px]">add</span>
                <span>Tambah Kategori {{ ucfirst($activeTab) }}</span>
            </button>
        </header>

        @if (session()->has('message'))
            <div class="p-4 bg-emerald-500/10 text-emerald-500 rounded-xl">
                {{ session('message') }}
            </div>
        @endif

        <div class="flex gap-4 border-b border-surface-container-high">
            <button wire:click="setTab('layanan')" class="pb-2 font-label-md transition-colors {{ $activeTab === 'layanan' ? 'text-primary border-b-2 border-primary' : 'text-on-surface-variant hover:text-on-surface' }}">
                Kategori Layanan
            </button>
            <button wire:click="setTab('barang')" class="pb-2 font-label-md transition-colors {{ $activeTab === 'barang' ? 'text-primary border-b-2 border-primary' : 'text-on-surface-variant hover:text-on-surface' }}">
                Kategori Barang
            </button>
        </div>

        <section class="flex flex-col sm:flex-row gap-4 p-4 bg-surface-container rounded-xl shadow-sm">
            <div class="relative flex-1">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                <input wire:model.live="search" class="w-full pl-12 pr-4 py-3 bg-background text-on-surface rounded-lg focus:outline-none focus:ring-1 focus:ring-primary placeholder-on-surface-variant transition-shadow" placeholder="Cari nama kategori..." type="text">
            </div>
        </section>

        @if($kategoris->count() > 0)
            <section class="bg-surface-container rounded-xl overflow-hidden shadow-md">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-surface-container-high text-on-surface-variant text-label-md font-label-md uppercase tracking-wider">
                                <th class="px-4 lg:px-5 py-4">Nama Kategori</th>
                                <th class="px-4 lg:px-5 py-4">Deskripsi</th>
                                <th class="px-4 lg:px-5 py-4">Status</th>
                                <th class="px-4 lg:px-5 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-body-md font-body-md text-on-surface">
                            @foreach($kategoris as $kategori)
                                <tr class="hover:bg-surface-container-highest transition-colors group">
                                    <td class="px-4 lg:px-5 py-4 font-medium">{{ $kategori->nama_kategori }}</td>
                                    <td class="px-4 lg:px-5 py-4 text-on-surface-variant">{{ Str::limit($kategori->deskripsi, 50) }}</td>
                                    <td class="px-4 lg:px-5 py-4">
                                        @if($kategori->is_active)
                                            <span class="px-3 py-1 bg-emerald-500/10 text-emerald-500 rounded-full text-label-sm font-label-sm">Aktif</span>
                                        @else
                                            <span class="px-3 py-1 bg-error/10 text-error rounded-full text-label-sm font-label-sm">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="px-4 lg:px-5 py-4 text-right">
                                        <div class="flex justify-end items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button wire:click="edit({{ $kategori->id }})" class="p-2 text-on-surface-variant hover:text-[#3b82f6] transition-colors rounded-full hover:bg-surface-variant">
                                                <span class="material-symbols-outlined text-[20px]">edit</span>
                                            </button>
                                            <button wire:click="deleteKategori({{ $kategori->id }})" wire:confirm="Yakin ingin menghapus kategori ini?" class="p-2 text-on-surface-variant hover:text-error transition-colors rounded-full hover:bg-error-container">
                                                <span class="material-symbols-outlined text-[20px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-surface-container-high">
                    {{ $kategoris->links() }}
                </div>
            </section>
        @else
            <section class="mt-12">
                <div class="flex flex-col items-center justify-center p-12 bg-transparent rounded-2xl border-2 border-dashed border-surface-variant text-center">
                    <div class="w-14 h-14 bg-surface-container rounded-full flex items-center justify-center mb-6 shadow-inner">
                        <span class="material-symbols-outlined text-lg text-on-surface-variant">category</span>
                    </div>
                    <h3 class="font-headline-md text-on-surface mb-2 text-headline-md">Data Kosong</h3>
                    <p class="text-body-md text-on-surface-variant mb-6 max-w-md">Tidak ada kategori yang ditemukan.</p>
                </div>
            </section>
        @endif
    </div>
    
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-3 sm:p-4 md:p-6 overflow-y-auto">
            <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] sm:max-h-[90vh] flex flex-col overflow-hidden border border-white/10 animate-[scale-up_0.2s_ease-out]">
                <!-- Sticky Header -->
                <div class="px-4 py-3 sm:px-5 sm:py-4 border-b border-white/10 flex justify-between items-center bg-surface-container-lowest shrink-0">
                    <h3 class="font-headline-md text-base sm:text-lg font-bold text-on-surface">{{ $isEdit ? 'Edit Kategori' : 'Tambah Kategori' }}</h3>
                    <button wire:click="$set('showModal', false)" class="w-8 h-8 rounded-lg flex items-center justify-center text-on-surface-variant hover:text-error hover:bg-white/5 transition-colors">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form wire:submit="save" class="flex flex-col flex-1 overflow-hidden min-h-0">
                    <!-- Scrollable Body -->
                    <div class="p-4 sm:p-5 overflow-y-auto space-y-4 flex-1">
                        <div class="space-y-1.5">
                            <label class="block font-label-md text-xs font-semibold text-on-surface">Nama Kategori</label>
                            <input wire:model="nama_kategori" type="text" class="w-full bg-background rounded-xl py-2.5 px-3.5 font-body-md text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                            @error('nama_kategori') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="space-y-1.5">
                            <label class="block font-label-md text-xs font-semibold text-on-surface">Deskripsi</label>
                            <textarea wire:model="deskripsi" class="w-full bg-background rounded-xl py-2.5 px-3.5 font-body-md text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" rows="3"></textarea>
                            @error('deskripsi') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center gap-2.5 pt-1">
                            <input wire:model="is_active" type="checkbox" id="is_active" class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary bg-background accent-primary cursor-pointer">
                            <label for="is_active" class="font-label-md text-xs font-medium text-on-surface cursor-pointer select-none">Status Kategori Aktif</label>
                        </div>
                    </div>
                    
                    <!-- Sticky Footer -->
                    <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-t border-white/10 bg-surface-container-lowest shrink-0 flex justify-end gap-2 sm:gap-3">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl font-label-md text-xs font-semibold text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest transition-colors">Batal</button>
                        <button type="submit" class="px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl font-label-md text-xs font-semibold bg-primary text-on-primary hover:bg-primary-container transition-all flex items-center gap-1.5 shadow-md">
                            <span wire:loading.remove wire:target="save">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kategori' }}</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
