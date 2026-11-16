<?php

use App\Models\JadwalShift;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Kelola Jadwal - Ran CukurIn')] class extends Component {
 public $weekStart;
 public $kapsters;
 public $selectedDate;
 public $selectedKapster;
 
 // Form fields
 public $status = 'Hadir';
 public $jam_mulai = '10:00';
 public $jam_selesai = '21:00';
 public $showSlideover = false;
 public $showHolidayModal = false;
 public $holidayDate;

 public function mount()
 {
 $this->weekStart = Carbon::now()->startOfWeek();
 $this->loadData();
 }
 
 public function loadData()
 {
 $this->kapsters = User::where('role', 'Kapster')->get();
 }

 public function previousWeek()
 {
 $this->weekStart->subWeek();
 }

 public function nextWeek()
 {
 $this->weekStart->addWeek();
 }

 public function currentWeek()
 {
 $this->weekStart = Carbon::now()->startOfWeek();
 }

 public function editShift($kapsterId, $date)
 {
 $this->selectedKapster = User::find($kapsterId);
 $this->selectedDate = $date;
 
 $shift = JadwalShift::where('kapster_id', $kapsterId)->where('tanggal', $date)->first();
 if ($shift) {
 $this->status = $shift->status;
 $this->jam_mulai = Carbon::parse($shift->jam_mulai)->format('H:i');
 $this->jam_selesai = Carbon::parse($shift->jam_selesai)->format('H:i');
 } else {
 $this->status = 'Hadir';
 $this->jam_mulai = '10:00';
 $this->jam_selesai = '21:00';
 }
 
 $this->showSlideover = true;
 }

 public function saveShift()
 {
 JadwalShift::updateOrCreate(
 ['kapster_id' => $this->selectedKapster->id, 'tanggal' => $this->selectedDate],
 [
 'jam_mulai' => $this->status === 'Hadir' ? $this->jam_mulai : null,
 'jam_selesai' => $this->status === 'Hadir' ? $this->jam_selesai : null,
 'status' => $this->status
 ]
 );
 
 $this->showSlideover = false;
 session()->flash('message', 'Jadwal shift berhasil disimpan.');
 }
 
 public function getShift($kapsterId, $date)
 {
 return JadwalShift::where('kapster_id', $kapsterId)->where('tanggal', $date)->first();
 }

 public function markHoliday()
 {
 if ($this->holidayDate) {
 foreach ($this->kapsters as $kapster) {
 JadwalShift::updateOrCreate(
 ['kapster_id' => $kapster->id, 'tanggal' => $this->holidayDate],
 [
 'jam_mulai' => null,
 'jam_selesai' => null,
 'status' => 'Libur'
 ]
 );
 }
 $this->showHolidayModal = false;
 session()->flash('message', 'Libur Nasional berhasil ditandai.');
 }
 }
};
?>

<div class="relative bg-background">
 <div class="flex flex-col w-full flex-grow p-4 lg:p-5 pb-12 relative overflow-hidden bg-background text-on-surface">
 <div class="absolute -top-40 -right-40 w-56 h-56 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
 
 <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 lg:gap-5 mb-8 lg:mb-10 relative z-10">
 <div class="flex flex-col">
 <div class="flex items-center gap-3 mb-2">
 <h1 class="font-headline-xl text-on-background tracking-tight text-headline-xl">Kelola Jadwal Staf</h1>
 </div>
 <p class="font-body-md text-body-md text-on-surface-variant max-w-2xl">Atur shift harian, tetapkan hari libur, dan pastikan ketersediaan kapster untuk pelayanan maksimal.</p>
 </div>
 <div class="flex flex-wrap items-center gap-3">
 <button wire:click="$set('showHolidayModal', true)" class="flex items-center gap-2 px-4 py-2.5 bg-surface-container-high hover:bg-surface-container-highest text-on-surface transition-all rounded-xl font-label-md text-label-md shadow-sm">
 <span class="material-symbols-outlined text-[20px] text-error">event_busy</span>
 Tandai Libur Nasional
 </button>
 </div>
 </header>
 
 @if (session()->has('message'))
 <div class="mb-4 p-4 bg-emerald-500/10 text-emerald-500 rounded-xl relative z-10">
 {{ session('message') }}
 </div>
 @endif

 <section class="mb-8 relative z-10">
 <div class="flex flex-col lg:flex-row justify-between items-center gap-4 lg:gap-5 bg-surface-container-low p-2 rounded-2xl shadow-sm">
 <div class="flex items-center gap-2 bg-surface p-1 rounded-xl w-full lg:w-auto overflow-x-auto">
 <button wire:click="previousWeek" class="p-2 text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-colors rounded-lg flex-shrink-0">
 <span class="material-symbols-outlined text-[20px]">chevron_left</span>
 </button>
 <div class="px-4 py-2 bg-surface-container rounded-lg flex items-center gap-3 min-w-[200px] justify-center flex-shrink-0">
 <span class="material-symbols-outlined text-[18px] text-primary">calendar_month</span>
 <span class="font-label-md text-label-md text-on-surface whitespace-nowrap">
 {{ $weekStart->copy()->translatedFormat('d M') }} - {{ $weekStart->copy()->endOfWeek()->translatedFormat('d M Y') }}
 </span>
 </div>
 <button wire:click="nextWeek" class="p-2 text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-colors rounded-lg flex-shrink-0">
 <span class="material-symbols-outlined text-[20px]">chevron_right</span>
 </button>
 <button wire:click="currentWeek" class="ml-2 px-4 py-2 text-on-surface-variant hover:text-on-surface font-label-md text-label-md rounded-lg flex-shrink-0">
 Minggu Ini
 </button>
 </div>
 </div>
 </section>

 <section class="bg-surface-container-lowest rounded-2xl shadow-xl overflow-hidden relative z-10 flex-1 flex flex-col">
 <div class="overflow-x-auto flex-1">
 <table class="w-full text-left border-collapse min-w-[1000px]">
 <thead>
 <tr class="bg-surface-container-low text-on-surface-variant font-label-md text-label-md uppercase tracking-wider">
 <th class="p-5 font-semibold w-12 sticky left-0 bg-surface-container-low z-20 shadow-[4px_0_12px_rgba(0,0,0,0.1)]">Nama Kapster</th>
 @for ($i = 0; $i < 7; $i++)
 @php $date = $weekStart->copy()->addDays($i); @endphp
 <th class="p-5 font-semibold text-center w-12 border-l border-surface-container-high/30 {{ $date->isToday() ? 'bg-surface-container/50' : '' }}">
 {{ $date->translatedFormat('D') }}
 <span class="block {{ $date->isToday() ? 'text-primary' : ($date->isWeekend() ? 'text-error' : 'text-on-surface') }} text-[24px] font-headline-md mt-1 text-headline-md">{{ $date->format('d') }}</span>
 </th>
 @endfor
 </tr>
 </thead>
 <tbody class="font-body-md text-body-md">
 @foreach($kapsters as $kapster)
 <tr class="group hover:bg-surface-container/30 transition-colors border-t border-surface-container-high/20">
 <td class="p-5 sticky left-0 bg-surface-container-lowest group-hover:bg-surface-container-low transition-colors z-20 shadow-[4px_0_12px_rgba(0,0,0,0.1)]">
 <div class="flex items-center gap-4">
 <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-surface-container-high text-primary font-bold text-lg uppercase">
 {{ substr($kapster->nama, 0, 2) }}
 </div>
 <div>
 <p class="font-headline-md text-[16px] text-on-surface font-semibold mb-0.5 text-headline-md">{{ $kapster->nama }}</p>
 <p class="text-on-surface-variant text-[12px] uppercase tracking-wider flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Kapster</p>
 </div>
 </div>
 </td>
 @for ($i = 0; $i < 7; $i++)
 @php 
 $dateStr = $weekStart->copy()->addDays($i)->format('Y-m-d');
 $shift = $this->getShift($kapster->id, $dateStr);
 @endphp
 <td class="p-3 border-l border-surface-container-high/20 {{ $weekStart->copy()->addDays($i)->isToday() ? 'bg-surface-container/20' : '' }}">
 @if($shift && $shift->status === 'Hadir')
 <button wire:click="editShift({{ $kapster->id }}, '{{ $dateStr }}')" class="w-full py-3 px-2 bg-primary-container/20 hover:bg-primary-container/40 border border-primary/30 rounded-xl flex flex-col items-center justify-center gap-1 transition-all group/btn">
 <span class="text-primary font-label-md">{{ \Carbon\Carbon::parse($shift->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($shift->jam_selesai)->format('H:i') }}</span>
 <span class="text-primary/70 text-[11px] opacity-0 group-hover/btn:opacity-100 transition-opacity">Edit</span>
 </button>
 @elseif($shift && $shift->status !== 'Hadir')
 <button wire:click="editShift({{ $kapster->id }}, '{{ $dateStr }}')" class="w-full py-3 px-2 border-2 border-dashed border-error/50 bg-error-container/20 hover:bg-error-container/40 rounded-xl flex flex-col items-center justify-center gap-1 transition-all relative overflow-hidden group/btn">
 <span class="text-error font-headline-md text-[14px] uppercase tracking-widest text-headline-md">{{ $shift->status }}</span>
 <span class="text-error text-[11px] opacity-0 group-hover/btn:opacity-100 transition-opacity absolute bottom-1">Edit</span>
 </button>
 @else
 <button wire:click="editShift({{ $kapster->id }}, '{{ $dateStr }}')" class="w-full py-3 px-2 border-2 border-dashed border-on-surface-variant/30 bg-surface hover:bg-surface-container rounded-xl flex flex-col items-center justify-center gap-1 transition-all group/btn relative">
 <span class="text-on-surface-variant font-headline-md text-[14px] uppercase tracking-widest text-headline-md">OFF</span>
 <span class="text-on-surface-variant text-[11px] opacity-0 group-hover/btn:opacity-100 transition-opacity absolute bottom-1">Set</span>
 </button>
 @endif
 </td>
 @endfor
 </tr>
 @endforeach
 </tbody>
 </table>
 </div>
 </section>

 <!-- Slide-over -->
 <div class="fixed inset-y-0 right-0 w-full max-w-md bg-surface-container-lowest shadow-[-8px_0_32px_rgba(0,0,0,0.5)] z-[100] transform {{ $showSlideover ? 'translate-x-0' : 'translate-x-full' }} transition-transform duration-300 ease-in-out flex flex-col border-l border-surface-container-high">
 <div class="px-5 lg:px-5 py-4 lg:py-5 border-b border-surface-container-high flex items-center justify-between bg-surface-container-low">
 <div>
 <h2 class="font-headline-md text-on-surface mb-1 text-headline-md">Edit Shift: {{ optional($selectedKapster)->nama }}</h2>
 <p class="font-body-md text-body-md text-primary">{{ $selectedDate ? \Carbon\Carbon::parse($selectedDate)->translatedFormat('l, d M Y') : '' }}</p>
 </div>
 <button wire:click="$set('showSlideover', false)" class="p-2 rounded-full hover:bg-surface-container text-on-surface-variant transition-colors">
 <span class="material-symbols-outlined">close</span>
 </button>
 </div>
 
 <form wire:submit="saveShift" class="flex-1 flex flex-col">
 <div class="p-5 lg:p-5 flex-1 overflow-y-auto space-y-8 bg-surface-container-lowest">
 <div class="space-y-4">
 <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Status Kehadiran</p>
 <div class="grid grid-cols-2 gap-4">
 <label class="cursor-pointer relative">
 <input wire:model="status" value="Hadir" name="status" type="radio" class="peer sr-only">
 <div class="p-4 rounded-xl border-2 border-surface-container bg-surface text-center peer-checked:border-primary peer-checked:bg-primary-container/10 transition-all hover:bg-surface-container-low">
 <span class="material-symbols-outlined block mb-2 text-on-surface peer-checked:text-primary">work</span>
 <span class="font-label-md text-label-md text-on-surface peer-checked:text-primary">Shift Kerja</span>
 </div>
 <div class="absolute top-3 right-3 w-4 h-4 rounded-full border-2 border-surface-container peer-checked:border-primary flex items-center justify-center">
 <div class="w-2 h-2 rounded-full bg-primary opacity-0 peer-checked:opacity-100 transition-opacity"></div>
 </div>
 </label>
 
 <label class="cursor-pointer relative">
 <input wire:model="status" value="Libur" name="status" type="radio" class="peer sr-only">
 <div class="p-4 rounded-xl border-2 border-surface-container bg-surface text-center peer-checked:border-error peer-checked:bg-error-container/10 transition-all hover:bg-surface-container-low">
 <span class="material-symbols-outlined block mb-2 text-on-surface peer-checked:text-error">event_busy</span>
 <span class="font-label-md text-label-md text-on-surface peer-checked:text-error">Libur / Cuti</span>
 </div>
 <div class="absolute top-3 right-3 w-4 h-4 rounded-full border-2 border-surface-container peer-checked:border-error flex items-center justify-center">
 <div class="w-2 h-2 rounded-full bg-error opacity-0 peer-checked:opacity-100 transition-opacity"></div>
 </div>
 </label>
 </div>
 </div>
 
 @if($status === 'Hadir')
 <div class="space-y-4">
 <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Jam Operasional</p>
 <div class="flex items-center gap-4">
 <div class="flex-1">
 <label class="block font-label-sm text-label-sm text-on-surface-variant mb-2">Mulai</label>
 <div class="relative">
 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">schedule</span>
 <input wire:model="jam_mulai" class="w-full bg-surface border border-surface-container-high rounded-xl py-3 pl-11 pr-4 text-on-surface font-body-md focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors [color-scheme:dark]" type="time" required>
 </div>
 </div>
 <div class="w-4 h-[1px] bg-surface-container-high mt-6"></div>
 <div class="flex-1">
 <label class="block font-label-sm text-label-sm text-on-surface-variant mb-2">Selesai</label>
 <div class="relative">
 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">schedule</span>
 <input wire:model="jam_selesai" class="w-full bg-surface border border-surface-container-high rounded-xl py-3 pl-11 pr-4 text-on-surface font-body-md focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors [color-scheme:dark]" type="time" required>
 </div>
 </div>
 </div>
 </div>
 @endif
 </div>
 
 <div class="p-5 lg:p-5 border-t border-surface-container-high bg-surface-container-lowest">
 <button type="submit" class="w-full py-4 bg-primary hover:bg-primary-fixed text-on-primary font-headline-md text-[16px] rounded-xl transition-all shadow-lg shadow-primary/20 hover:shadow-primary/40 flex items-center justify-center gap-2 text-headline-md">
 <span class="material-symbols-outlined text-[20px]">save</span>
 Simpan Jadwal
 </button>
 </div>
 </form>
 </div>
 
 <!-- Overlay for slideover -->
 <div class="fixed inset-0 bg-background/80 backdrop-blur-sm z-[90] {{ $showSlideover ? 'block opacity-100' : 'hidden opacity-0' }} transition-opacity duration-300" wire:click="$set('showSlideover', false)"></div>
 
 <!-- Holiday Modal -->
 @if($showHolidayModal)
 <div class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
 <div class="bg-surface-container rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden border border-white/10">
 <div class="px-4 py-4 border-b border-surface-container-high flex justify-between items-center bg-surface-container-lowest">
 <h3 class="font-headline-md text-on-surface">Tandai Libur Nasional</h3>
 <button wire:click="$set('showHolidayModal', false)" class="text-on-surface-variant hover:text-error transition-colors">
 <span class="material-symbols-outlined">close</span>
 </button>
 </div>
 <div class="p-4 space-y-4">
 <div class="space-y-2">
 <label class="block font-label-md text-on-surface">Pilih Tanggal</label>
 <input wire:model="holidayDate" type="date" class="w-full bg-background rounded-xl py-3 px-4 font-body-md text-on-surface border border-outline-variant focus:border-primary outline-none" required>
 </div>
 </div>
 <div class="p-4 flex justify-end gap-3 border-t border-surface-container-high">
 <button wire:click="$set('showHolidayModal', false)" class="px-4 py-2 rounded-full font-label-md text-on-surface-variant hover:bg-surface-container-highest">Batal</button>
 <button wire:click="markHoliday" class="px-4 py-2 rounded-full font-label-md bg-error text-on-error hover:bg-error/80 transition-colors">Simpan Libur</button>
 </div>
 </div>
 </div>
 @endif
 </div>
</div>
