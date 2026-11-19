<?php

use App\Models\Absensi;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Kelola Absensi - Ran CukurIn')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterTanggal;

    public $showModal = false;
    public $isEdit = false;
    public $absensiId;
    public $user_id;
    public $tanggal;
    public $status = 'Hadir';
    public $keterangan;
    
    public $staffList = [];

    public function rules() {
        return [
            'tanggal' => 'required|date',
            'status' => 'required|in:Hadir,Sakit,Izin,Alpa',
            'keterangan' => 'nullable|string',
        ];
    }

    public function mount()
    {
        $this->filterTanggal = date('Y-m-d');
        $this->user_id = auth()->id();
    }

    public function create()
    {
        $this->resetValidation();
        $this->reset(['absensiId', 'keterangan']);
        $this->user_id = auth()->id(); // Ensure it is always set
        $this->tanggal = $this->filterTanggal ?? date('Y-m-d');
        $this->status = 'Hadir';
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetValidation();
        $absensi = Absensi::findOrFail($id);
            
        $this->absensiId = $absensi->id;
        $this->user_id = $absensi->user_id;
        $this->tanggal = $absensi->tanggal;
        $this->status = $absensi->status;
        $this->keterangan = $absensi->keterangan;
        
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->user_id = auth()->id(); // Ensure it is always set during save
        $this->validate();

        $data = [
            'user_id' => $this->user_id,
            'tanggal' => $this->tanggal,
            'status' => $this->status,
            'keterangan' => $this->keterangan,
        ];

        // Check for duplicates if creating
        if (!$this->isEdit) {
            $exists = Absensi::where('user_id', $this->user_id)
                ->where('tanggal', $this->tanggal)
                ->exists();
            if ($exists) {
                $this->addError('user_id', 'Staf ini sudah memiliki catatan absensi pada tanggal tersebut.');
                return;
            }
        }

        if ($this->isEdit) {
            $absensi = Absensi::findOrFail($this->absensiId);
            $absensi->update($data);
            session()->flash('message', 'Catatan absensi berhasil diperbarui.');
        } else {
            Absensi::create($data);
            session()->flash('message', 'Catatan absensi baru berhasil ditambahkan.');
        }
        $this->showModal = false;
    }

    public function deleteAbsensi($id)
    {
        $absensi = Absensi::findOrFail($id);
        $absensi->delete();
        session()->flash('message', 'Catatan absensi berhasil dihapus.');
    }

    public function with()
    {
        $model = Absensi::with('user')->where('user_id', auth()->id());

        if ($this->filterTanggal) {
            $model->where('tanggal', $this->filterTanggal);
        }

        // No staff search needed

        return [
            'absensis' => $model->orderBy('tanggal', 'desc')->paginate(10)
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
    
    public function updatedFilterTanggal()
    {
        $this->resetPage();
    }
};
?>

<div class="flex-grow flex flex-col relative bg-background">
    <div class="flex flex-col w-full h-full p-4 lg:p-5 space-y-8 text-on-surface">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="font-headline-lg text-primary tracking-tight text-headline-lg">Kelola Absensi</h1>
                <p class="text-body-md font-body-md text-on-surface-variant mt-1">Pantau dan kelola kehadiran staf / kapster.</p>
            </div>
            <button wire:click="create" class="flex items-center gap-2 px-4 lg:px-5 py-3 bg-primary text-on-primary rounded-lg font-label-md hover:scale-105 transition-transform duration-200 shadow-md">
                <span class="material-symbols-outlined text-[20px]">how_to_reg</span>
                <span>Lapor Kehadiran</span>
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
                <input wire:model.live="search" class="w-full pl-12 pr-4 py-3 bg-background text-on-surface rounded-lg focus:outline-none focus:ring-1 focus:ring-primary placeholder-on-surface-variant transition-shadow" placeholder="Cari nama staf..." type="text">
            </div>
            <div class="relative w-full sm:w-auto">
                <input wire:model.live="filterTanggal" class="w-full sm:w-auto px-4 py-3 bg-background text-on-surface rounded-lg focus:outline-none focus:ring-1 focus:ring-primary transition-shadow" type="date">
            </div>
        </section>

        @if($absensis->count() > 0)
            <section class="bg-surface-container rounded-xl overflow-hidden shadow-md">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-surface-container-high text-on-surface-variant text-label-md font-label-md uppercase tracking-wider">
                                <th class="px-4 lg:px-5 py-4">Nama Staf</th>
                                <th class="px-4 lg:px-5 py-4">Tanggal</th>
                                <th class="px-4 lg:px-5 py-4">Status</th>
                                <th class="px-4 lg:px-5 py-4">Keterangan</th>
                                <th class="px-4 lg:px-5 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-body-md font-body-md text-on-surface">
                            @foreach($absensis as $absensi)
                                <tr class="hover:bg-surface-container-highest transition-colors group">
                                    <td class="px-4 lg:px-5 py-4 font-medium">{{ $absensi->user->nama ?? 'N/A' }}</td>
                                    <td class="px-4 lg:px-5 py-4">{{ \Carbon\Carbon::parse($absensi->tanggal)->translatedFormat('d M Y') }}</td>
                                    <td class="px-4 lg:px-5 py-4">
                                        @if($absensi->status === 'Hadir')
                                            <span class="px-3 py-1 bg-emerald-500/10 text-emerald-500 rounded-full text-label-sm font-label-sm">Hadir</span>
                                        @elseif($absensi->status === 'Sakit' || $absensi->status === 'Izin')
                                            <span class="px-3 py-1 bg-amber-500/10 text-amber-500 rounded-full text-label-sm font-label-sm">{{ $absensi->status }}</span>
                                        @else
                                            <span class="px-3 py-1 bg-error/10 text-error rounded-full text-label-sm font-label-sm">Alpa</span>
                                        @endif
                                    </td>
                                    <td class="px-4 lg:px-5 py-4 text-on-surface-variant">{{ $absensi->keterangan ?? '-' }}</td>
                                    <td class="px-4 lg:px-5 py-4 text-right">
                                        <div class="flex justify-end items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button wire:click="edit({{ $absensi->id }})" class="p-2 text-on-surface-variant hover:text-[#3b82f6] transition-colors rounded-full hover:bg-surface-variant">
                                                <span class="material-symbols-outlined text-[20px]">edit</span>
                                            </button>
                                            <button wire:click="deleteAbsensi({{ $absensi->id }})" wire:confirm="Yakin ingin menghapus catatan ini?" class="p-2 text-on-surface-variant hover:text-error transition-colors rounded-full hover:bg-error-container">
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
                    {{ $absensis->links() }}
                </div>
            </section>
        @else
            <section class="mt-12">
                <div class="flex flex-col items-center justify-center p-12 bg-transparent rounded-2xl border-2 border-dashed border-surface-variant text-center">
                    <div class="w-14 h-14 bg-surface-container rounded-full flex items-center justify-center mb-6 shadow-inner">
                        <span class="material-symbols-outlined text-lg text-on-surface-variant">history_toggle_off</span>
                    </div>
                    <h3 class="font-headline-md text-on-surface mb-2 text-headline-md">Belum Ada Absensi</h3>
                    <p class="text-body-md text-on-surface-variant mb-6 max-w-md">Tidak ada catatan absensi untuk kriteria pencarian ini.</p>
                </div>
            </section>
        @endif
    </div>
    
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-3 sm:p-4 md:p-6 overflow-y-auto">
            <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] sm:max-h-[90vh] flex flex-col overflow-hidden border border-white/10 animate-[scale-up_0.2s_ease-out]">
                <!-- Sticky Header -->
                <div class="px-4 py-3 sm:px-5 sm:py-4 border-b border-white/10 flex justify-between items-center bg-surface-container-lowest shrink-0">
                    <h3 class="font-headline-md text-base sm:text-lg font-bold text-on-surface">{{ $isEdit ? 'Edit Absensi' : 'Catat Absensi' }}</h3>
                    <button wire:click="$set('showModal', false)" class="w-8 h-8 rounded-lg flex items-center justify-center text-on-surface-variant hover:text-error hover:bg-white/5 transition-colors">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form wire:submit="save" class="flex flex-col flex-1 overflow-hidden min-h-0">
                    <!-- Scrollable Body -->
                    <div class="p-4 sm:p-5 overflow-y-auto space-y-4 flex-1">
                        <div class="space-y-1.5">
                            <label class="block font-label-md text-xs font-semibold text-on-surface">Tanggal</label>
                            <input wire:model="tanggal" type="date" class="w-full bg-background rounded-xl py-2.5 px-3.5 font-body-md text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
                            @error('tanggal') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="space-y-1.5">
                            <label class="block font-label-md text-xs font-semibold text-on-surface">Status Kehadiran</label>
                            <select wire:model="status" class="w-full bg-background rounded-xl py-2.5 px-3.5 font-body-md text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors cursor-pointer" required>
                                <option value="Hadir">Hadir</option>
                                <option value="Sakit">Sakit</option>
                                <option value="Izin">Izin</option>
                                <option value="Alpa">Alpa</option>
                            </select>
                            @error('status') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="block font-label-md text-xs font-semibold text-on-surface">Keterangan Tambahan</label>
                            <input wire:model="keterangan" type="text" class="w-full bg-background rounded-xl py-2.5 px-3.5 font-body-md text-sm text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" placeholder="(Opsional)">
                            @error('keterangan') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    
                    <!-- Sticky Footer -->
                    <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-t border-white/10 bg-surface-container-lowest shrink-0 flex justify-end gap-2 sm:gap-3">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl font-label-md text-xs font-semibold text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest transition-colors">Batal</button>
                        <button type="submit" class="px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl font-label-md text-xs font-semibold bg-primary text-on-primary hover:bg-primary-container transition-all flex items-center gap-1.5 shadow-md">
                            <span wire:loading.remove wire:target="save">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Absensi' }}</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
