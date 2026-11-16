<?php

use App\Models\Pengeluaran;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Kas & Pengeluaran - Ran CukurIn')] class extends Component {
    use WithPagination;

    public $search = '';

    public $showModal = false;
    public $isEdit = false;
    public $pengeluaranId;
    public $tanggal;
    public $keterangan;
    public $nominal;

    public function rules() {
        return [
        'tanggal' => 'required|date',
        'keterangan' => 'required|string|max:255',
        'nominal' => 'required|numeric|min:0',
    ];
    }

    public function create()
    {
        $this->resetValidation();
        $this->reset(['pengeluaranId', 'keterangan', 'nominal']);
        $this->tanggal = date('Y-m-d');
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetValidation();
        $pengeluaran = Pengeluaran::findOrFail($id);
            
        $this->pengeluaranId = $pengeluaran->id;
        $this->tanggal = $pengeluaran->tanggal;
        $this->keterangan = $pengeluaran->keterangan;
        $this->nominal = $pengeluaran->nominal;
        
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tanggal' => $this->tanggal,
            'keterangan' => $this->keterangan,
            'nominal' => $this->nominal,
        ];

        if ($this->isEdit) {
            $pengeluaran = Pengeluaran::findOrFail($this->pengeluaranId);
            $pengeluaran->update($data);
            session()->flash('message', 'Catatan pengeluaran berhasil diperbarui.');
        } else {
            Pengeluaran::create($data);
            session()->flash('message', 'Catatan pengeluaran baru berhasil ditambahkan.');
        }
        $this->showModal = false;
    }

    public function deletePengeluaran($id)
    {
        $pengeluaran = Pengeluaran::findOrFail($id);
        $pengeluaran->delete();
        session()->flash('message', 'Catatan pengeluaran berhasil dihapus.');
    }

    public function with()
    {
        $model = Pengeluaran::query();

        if ($this->search) {
            $model->where('keterangan', 'like', '%' . $this->search . '%');
        }

        return [
            'pengeluarans' => $model->orderBy('tanggal', 'desc')->paginate(10)
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
                <h1 class="font-headline-lg text-primary tracking-tight text-headline-lg">Kelola Pengeluaran Kas</h1>
                <p class="text-body-md font-body-md text-on-surface-variant mt-1">Pencatatan pengeluaran operasional outlet.</p>
            </div>
            <button wire:click="create" class="flex items-center gap-2 px-4 lg:px-5 py-3 bg-primary text-on-primary rounded-lg font-label-md hover:scale-105 transition-transform duration-200 shadow-md">
                <span class="material-symbols-outlined text-[20px]">add</span>
                <span>Tambah Pengeluaran</span>
            </button>
        </header>

        @if (session()->has('message'))
            <div class="p-4 bg-emerald-500/10 text-emerald-500 rounded-xl">
                {{ session('message') }}
            </div>
        @endif

        <section class="flex flex-col sm:flex-row gap-4 p-4 bg-surface-container rounded-xl shadow-sm">
            <div class="relative flex-1">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                <input wire:model.live="search" class="w-full pl-12 pr-4 py-3 bg-background text-on-surface rounded-lg focus:outline-none focus:ring-1 focus:ring-primary placeholder-on-surface-variant transition-shadow" placeholder="Cari keterangan pengeluaran..." type="text">
            </div>
        </section>

        @if($pengeluarans->count() > 0)
            <section class="bg-surface-container rounded-xl overflow-hidden shadow-md">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-surface-container-high text-on-surface-variant text-label-md font-label-md uppercase tracking-wider">
                                <th class="px-4 lg:px-5 py-4">Tanggal</th>
                                <th class="px-4 lg:px-5 py-4">Keterangan</th>
                                <th class="px-4 lg:px-5 py-4">Nominal</th>
                                <th class="px-4 lg:px-5 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-body-md font-body-md text-on-surface">
                            @foreach($pengeluarans as $pengeluaran)
                                <tr class="hover:bg-surface-container-highest transition-colors group">
                                    <td class="px-4 lg:px-5 py-4 font-medium">{{ \Carbon\Carbon::parse($pengeluaran->tanggal)->translatedFormat('d M Y') }}</td>
                                    <td class="px-4 lg:px-5 py-4 text-on-surface-variant">{{ $pengeluaran->keterangan }}</td>
                                    <td class="px-4 lg:px-5 py-4 font-bold text-error">Rp {{ number_format($pengeluaran->nominal, 0, ',', '.') }}</td>
                                    <td class="px-4 lg:px-5 py-4 text-right">
                                        <div class="flex justify-end items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button wire:click="edit({{ $pengeluaran->id }})" class="p-2 text-on-surface-variant hover:text-[#3b82f6] transition-colors rounded-full hover:bg-surface-variant">
                                                <span class="material-symbols-outlined text-[20px]">edit</span>
                                            </button>
                                            <button wire:click="deletePengeluaran({{ $pengeluaran->id }})" wire:confirm="Yakin ingin menghapus catatan pengeluaran ini?" class="p-2 text-on-surface-variant hover:text-error transition-colors rounded-full hover:bg-error-container">
                                                <span class="material-symbols-outlined text-[20px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-surface-container-high flex justify-between items-center">
                    <div class="font-label-md text-label-md">
                        Total Ditampilkan: <span class="font-bold text-error">Rp {{ number_format($pengeluarans->sum('nominal'), 0, ',', '.') }}</span>
                    </div>
                    {{ $pengeluarans->links() }}
                </div>
            </section>
        @else
            <section class="mt-12">
                <div class="flex flex-col items-center justify-center p-12 bg-transparent rounded-2xl border-2 border-dashed border-surface-variant text-center">
                    <div class="w-14 h-14 bg-surface-container rounded-full flex items-center justify-center mb-6 shadow-inner">
                        <span class="material-symbols-outlined text-lg text-on-surface-variant">receipt_long</span>
                    </div>
                    <h3 class="font-headline-md text-on-surface mb-2 text-headline-md">Tidak Ada Pengeluaran</h3>
                    <p class="text-body-md text-on-surface-variant mb-6 max-w-md">Belum ada catatan pengeluaran yang ditambahkan.</p>
                </div>
            </section>
        @endif
    </div>
    
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-white/10 max-h-[90vh] overflow-y-auto">
                <div class="px-4 lg:px-5 py-4 border-b border-white/10 flex justify-between items-center bg-surface-container-lowest sticky top-0 z-10">
                    <h3 class="font-headline-md text-on-surface text-headline-md">{{ $isEdit ? 'Edit Pengeluaran' : 'Catat Pengeluaran' }}</h3>
                    <button wire:click="$set('showModal', false)" class="text-on-surface-variant hover:text-error transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form wire:submit="save" class="p-4 lg:p-5 space-y-4">
                    <div class="space-y-2">
                        <label class="block font-label-md text-label-md text-on-surface">Tanggal</label>
                        <input wire:model="tanggal" type="date" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                        @error('tanggal') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="block font-label-md text-label-md text-on-surface">Keterangan / Tujuan</label>
                        <input wire:model="keterangan" type="text" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                        @error('keterangan') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block font-label-md text-label-md text-on-surface">Nominal (Rp)</label>
                        <input wire:model="nominal" type="number" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                        @error('nominal') <span class="text-error text-sm">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="pt-6 flex justify-end gap-3 border-t border-white/10 mt-6">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 lg:px-5 py-2.5 rounded-full font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-highest transition-colors">Batal</button>
                        <button type="submit" class="px-4 lg:px-5 py-2.5 rounded-full font-label-md text-label-md bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors shadow-lg shadow-primary/20">
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

