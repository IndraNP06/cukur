<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Kelola Staf - Ran CukurIn')] class extends Component {
 use WithPagination;

 public $search = '';
 public $roleFilter = 'all';

 public $showModal = false;
 public $isEdit = false;
 public $staffId;
 public $nama;
 public $email;
 public $no_hp;
 public $role = 'Kapster';
 public $foto;
 public $password;

 public function rules() {
        return [
 'nama' => 'required|string|max:255',
 'email' => 'required|email|unique:pengguna,email',
 'no_hp' => 'nullable|string|max:20',
 'role' => 'required|in:Kapster,Pemilik',
 'foto' => 'nullable|string|max:255',
 ];
    }

 public function create()
 {
 $this->resetValidation();
 $this->reset(['staffId', 'nama', 'email', 'no_hp', 'role', 'foto', 'password']);
 $this->isEdit = false;
 $this->showModal = true;
 }

 public function edit($id)
 {
 $this->resetValidation();
 $staff = User::findOrFail($id);
 $this->staffId = $staff->id;
 $this->nama = $staff->nama;
 $this->email = $staff->email;
 $this->no_hp = $staff->no_hp;
 $this->role = $staff->role;
 $this->foto = $staff->foto;
 $this->isEdit = true;
 $this->showModal = true;
 }

 public function save()
 {
 $rules = $this->rules();
 if ($this->isEdit) {
 $rules['email'] = 'required|email|unique:pengguna,email,' . $this->staffId;
 } else {
 $rules['password'] = 'required|min:8';
 }
 $this->validate($rules);

 if ($this->isEdit) {
 $staff = User::findOrFail($this->staffId);
 $staff->update([
 'nama' => $this->nama,
 'email' => $this->email,
 'no_hp' => $this->no_hp,
 'role' => $this->role,
 'foto' => $this->foto,
 ]);
 if ($this->password) {
 $staff->update(['password' => bcrypt($this->password)]);
 }
 if (!$staff->hasRole($this->role)) {
 $staff->syncRoles([$this->role]);
 }
 session()->flash('message', 'Data staf berhasil diperbarui.');
 } else {
 $staff = User::create([
 'nama' => $this->nama,
 'email' => $this->email,
 'no_hp' => $this->no_hp,
 'role' => $this->role,
 'foto' => $this->foto,
 'password' => bcrypt($this->password),
 ]);
 $staff->assignRole(ucfirst($this->role));
 session()->flash('message', 'Staf baru berhasil ditambahkan.');
 }
 $this->showModal = false;
 }

 public function resetPassword($id)
 {
 $staff = User::findOrFail($id);
 $staff->update(['password' => bcrypt('password123')]);
 session()->flash('message', 'Kata sandi berhasil direset menjadi "password123".');
 }

 public function with()
 {
 $query = User::where('role', '!=', 'Pelanggan');

 if ($this->search) {
 $query->where(function ($q) {
 $q->where('nama', 'like', '%' . $this->search . '%')
 ->orWhere('email', 'like', '%' . $this->search . '%');
 });
 }

 if ($this->roleFilter !== 'all') {
 $query->where('role', $this->roleFilter);
 }

 return [
 'staffs' => $query->latest()->paginate(10)
 ];
 }

 public function updatedSearch()
 {
 $this->resetPage();
 }

 public function updatedRoleFilter()
 {
 $this->resetPage();
 }

 public function deleteStaff($id)
 {
 $staff = User::findOrFail($id);
 
 // Prevent deleting oneself
 if ($staff->id === auth()->id()) {
 session()->flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
 return;
 }

 $staff->delete();
 session()->flash('message', 'Staf berhasil dihapus.');
 }
};
?>

<div class="flex-grow flex flex-col relative bg-background">
 <div class="flex flex-col w-full h-full p-4 lg:p-5 space-y-8 text-on-surface">
 <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
 <div>
 <h1 class="font-headline-lg text-primary tracking-tight text-headline-lg">Kelola Akun Staf &amp; Kapster</h1>
 <p class="text-body-md font-body-md text-on-surface-variant mt-1">Manajemen akses dan profil staf Ran CukurIn.</p>
 </div>
 <button wire:click="create" class="flex items-center gap-2 px-4 lg:px-5 py-3 bg-primary text-on-primary rounded-lg font-label-md hover:scale-105 transition-transform duration-200 shadow-md">
 <span class="material-symbols-outlined text-[20px]">add</span>
 <span>Tambah Staf Baru</span>
 </button>
 </header>

 @if (session()->has('message'))
 <div class="p-4 bg-emerald-500/10 text-emerald-500 rounded-xl">
 {{ session('message') }}
 </div>
 @endif

 @if (session()->has('error'))
 <div class="p-4 bg-error/10 text-error rounded-xl">
 {{ session('error') }}
 </div>
 @endif

 <section class="flex flex-col sm:flex-row gap-4 p-4 bg-surface-container rounded-xl shadow-sm">
 <div class="relative flex-1">
 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
 <input wire:model.live="search" class="w-full pl-12 pr-4 py-3 bg-background text-on-surface rounded-lg focus:outline-none focus:ring-1 focus:ring-primary placeholder-on-surface-variant transition-shadow" placeholder="Cari nama atau email..." type="text">
 </div>
 <div class="relative w-full sm:w-12">
 <select wire:model.live="roleFilter" class="w-full appearance-none pl-4 pr-10 py-3 bg-background text-on-surface rounded-lg focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer transition-shadow">
 <option value="all">Semua Peran</option>
 <option value="Kapster">Kapster</option>
 <option value="Pemilik">Pemilik / Admin</option>
 </select>
 <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">expand_more</span>
 </div>
 </section>

 @if($staffs->count() > 0)
 <section class="bg-surface-container rounded-xl overflow-hidden shadow-md">
 <div class="overflow-x-auto">
 <table class="w-full text-left border-collapse">
 <thead>
 <tr class="bg-surface-container-high text-on-surface-variant text-label-md font-label-md uppercase tracking-wider">
 <th class="px-4 lg:px-5 py-4">Nama Staf</th>
 <th class="px-4 lg:px-5 py-4">Peran</th>
 <th class="px-4 lg:px-5 py-4">Status</th>
 <th class="px-4 lg:px-5 py-4 text-right">Aksi</th>
 </tr>
 </thead>
 <tbody class="text-body-md font-body-md text-on-surface">
 @foreach($staffs as $staff)
 <tr class="hover:bg-surface-container-highest transition-colors group">
 <td class="px-4 lg:px-5 py-4">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-full bg-surface-variant flex items-center justify-center text-primary font-label-md uppercase overflow-hidden border border-white/10 shrink-0">
 @if($staff->foto_url)
 <img src="{{ $staff->foto_url }}" alt="{{ $staff->nama }}" class="w-full h-full object-cover">
 @else
 {{ substr($staff->nama, 0, 2) }}
 @endif
 </div>
 <div>
 <p class="font-medium">{{ $staff->nama }}</p>
 <p class="text-label-sm text-on-surface-variant">{{ $staff->email }}</p>
 </div>
 </div>
 </td>
 <td class="px-4 lg:px-5 py-4">
 <span class="px-3 py-1 bg-surface-variant text-on-surface rounded-full text-label-sm font-label-sm">{{ $staff->role }}</span>
 </td>
 <td class="px-4 lg:px-5 py-4">
 <span class="flex items-center gap-2 text-label-sm font-label-sm">
 <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
 Aktif
 </span>
 </td>
 <td class="px-4 lg:px-5 py-4 text-right">
 <div class="flex justify-end items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
 <button wire:click="resetPassword({{ $staff->id }})" wire:confirm="Reset kata sandi menjadi 'password123'?" class="px-3 py-1.5 text-label-sm font-label-sm text-on-surface-variant hover:text-primary transition-colors bg-surface-variant rounded-md">
 Reset Sandi
 </button>
 <button wire:click="edit({{ $staff->id }})" class="p-2 text-on-surface-variant hover:text-[#3b82f6] transition-colors rounded-full hover:bg-surface-variant">
 <span class="material-symbols-outlined text-[20px]">edit</span>
 </button>
 <button wire:click="deleteStaff({{ $staff->id }})" wire:confirm="Yakin ingin menghapus staf ini?" class="p-2 text-on-surface-variant hover:text-error transition-colors rounded-full hover:bg-error-container">
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
 {{ $staffs->links() }}
 </div>
 </section>
 @else
 <section class="mt-12">
 <div class="flex flex-col items-center justify-center p-12 bg-transparent rounded-2xl border-2 border-dashed border-surface-variant text-center">
 <div class="w-14 h-14 bg-surface-container rounded-full flex items-center justify-center mb-6 shadow-inner">
 <span class="material-symbols-outlined text-lg text-on-surface-variant">person_search</span>
 </div>
 <h3 class="font-headline-md text-on-surface mb-2 text-headline-md">Staf tidak ditemukan</h3>
 <p class="text-body-md text-on-surface-variant mb-6 max-w-md">Tidak ada staf yang cocok dengan pencarian "{{ $search }}". Coba sesuaikan kata kunci atau filter.</p>
 <button wire:click="$set('search', '')" class="text-primary hover:text-primary-fixed transition-colors font-label-md text-label-md flex items-center gap-2">
 <span class="material-symbols-outlined text-[18px]">clear_all</span>
 Bersihkan Pencarian
 </button>
 </div>
 </section>
 @endif
 </div>
 
 @if($showModal)
 <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
 <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-white/10 max-h-[90vh] overflow-y-auto">
 <div class="px-4 lg:px-5 py-4 border-b border-white/10 flex justify-between items-center bg-surface-container-lowest sticky top-0 z-10">
 <h3 class="font-headline-md text-on-surface text-headline-md">{{ $isEdit ? 'Edit Staf' : 'Tambah Staf Baru' }}</h3>
 <button wire:click="$set('showModal', false)" class="text-on-surface-variant hover:text-error transition-colors">
 <span class="material-symbols-outlined">close</span>
 </button>
 </div>
 <form wire:submit="save" class="p-4 lg:p-5 space-y-4">
 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Nama Lengkap</label>
 <input wire:model="nama" type="text" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
 @error('nama') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Email</label>
 <input wire:model="email" type="email" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" required>
 @error('email') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>

 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Foto Profil (URL / Path Gambar)</label>
 <input wire:model="foto" type="text" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" placeholder="e.g. images/kapster/kapster-budi.jpg">
 @error('foto') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>

 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">No HP</label>
 <input wire:model="no_hp" type="text" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors">
 @error('no_hp') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>

 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Peran (Role)</label>
 <select wire:model="role" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors cursor-pointer" required>
 <option value="Kapster">Kapster</option>
 <option value="Pemilik">Pemilik / Admin</option>
 </select>
 @error('role') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>

 <div class="space-y-2">
 <label class="block font-label-md text-label-md text-on-surface">Kata Sandi {{ $isEdit ? '(Kosongkan jika tidak ingin diubah)' : '' }}</label>
 <input wire:model="password" type="password" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-body-md text-on-surface border border-outline-variant focus:border-primary outline-none transition-colors" {{ $isEdit ? '' : 'required' }}>
 @error('password') <span class="text-error text-sm">{{ $message }}</span> @enderror
 </div>
 
 <div class="pt-6 flex justify-end gap-3 border-t border-white/10 mt-6">
 <button type="button" wire:click="$set('showModal', false)" class="px-4 lg:px-5 py-2.5 rounded-full font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-highest transition-colors">Batal</button>
 <button type="submit" class="px-4 lg:px-5 py-2.5 rounded-full font-label-md text-label-md bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors shadow-lg shadow-primary/20">
 <span wire:loading.remove wire:target="save">Simpan Staf</span>
 <span wire:loading wire:target="save">Menyimpan...</span>
 </button>
 </div>
 </form>
 </div>
 </div>
 @endif
</div>

