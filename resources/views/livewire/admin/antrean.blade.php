<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use App\Models\AntreanPemesanan;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Layanan;
use Illuminate\Support\Str;

new #[Layout('components.layouts.admin')] #[Title('Antrean Terpadu - Ran Cukurin')] class extends Component {
    public $filterKapster = 'all';
    public $filterTanggal = 'all'; // 'all' (Semua Aktif), 'today', 'tomorrow', 'history', 'custom'
    public $customTanggal = '';

    // Walk-In Modal
    public $showWalkInModal = false;
    public $walkInName = '';
    public $walkInLayanan = null;
    public $walkInKapster = null;
    public $walkInTime = null;

    // Reschedule Modal
    public $showRescheduleModal = false;
    public $rescheduleAntreanId = null;
    public $rescheduleTanggal = '';
    public $rescheduleTime = '';
    public $rescheduleKapsterId = null;

    // Cancel Modal
    public $showCancelModal = false;
    public $cancelAntreanId = null;
    public $cancelAlasan = 'Pelanggan berhalangan hadir / No-Show';
    public $customCancelAlasan = '';

    // Active selected antrean detail for modals
    public $activeSelectedAntrean = null;

    public function mount() {
        if (auth()->check() && (auth()->user()->role === 'Kapster' || (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('Kapster')))) {
            $this->filterKapster = (string)auth()->id();
            $this->walkInKapster = auth()->id();
        }

        if (request()->query('action') === 'walkin') {
            $this->openWalkInModal();
        }
    }

    public function openWalkInModal() {
        $this->resetValidation();
        $this->reset(['walkInName', 'walkInLayanan', 'walkInTime']);
        if (auth()->check() && (auth()->user()->role === 'Kapster' || (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('Kapster')))) {
            $this->walkInKapster = auth()->id();
        } else {
            $this->walkInKapster = 'any';
        }
        $this->walkInTime = now()->format('H:i:s');
        $this->showWalkInModal = true;
    }

        public function setWalkInTime($time) {
        $this->walkInTime = $time;
        $this->resetErrorBag('walkInTime');
    }

    public function setRescheduleTime($time) {
        $this->rescheduleTime = $time;
        $this->resetErrorBag('rescheduleTime');
    }

    public function setWalkInTimeNow() {
        $this->walkInTime = now()->format('H:i');
        $this->resetErrorBag('walkInTime');
    }
    // old setWalkInTimeNow replaced
    private function _dummySetWalkIn() {
        $this->walkInTime = now()->format('H:i:s');
    }

    public function syncFromSheets() {
        try {
            app(\App\Services\GoogleSheetsService::class)->pullTable('antrean_pemesanan');
            cache()->forget('sheets_sync_antrean_board');
            session()->flash('sync_success', 'Data antrean terpadu berhasil disinkronkan langsung dari Google Spreadsheet!');
        } catch (\Throwable $e) {
            session()->flash('sync_error', 'Gagal menyinkronkan data: ' . $e->getMessage());
        }
    }

    public function isOwner(): bool {
        if (!auth()->check()) return false;
        $user = auth()->user();
        if (in_array((int)$user->id, [1, 2])) return true;
        $role = strtolower($user->role ?? '');
        if (str_contains($role, 'pemilik') || str_contains($role, 'owner') || str_contains($role, 'admin')) {
            return true;
        }
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('Pemilik') || $user->hasRole('Admin') || $user->hasRole('Owner');
        }
        return false;
    }

    public function canManageAntrean($antrean): bool {
        if ($this->isOwner()) {
            return true;
        }
        if (!auth()->check()) return false;
        $kapsterId = is_object($antrean) ? $antrean->kapster_id : ($antrean['kapster_id'] ?? null);
        if (!$kapsterId) {
            return true;
        }
        return (int)$kapsterId === (int)auth()->id();
    }

    public function startProcess($id) {
        $antrean = AntreanPemesanan::find($id);
        if (!$antrean) {
            session()->flash('sync_error', 'Antrean tidak ditemukan.');
            return;
        }

        if (!$this->canManageAntrean($antrean)) {
            session()->flash('sync_error', 'Akses ditolak: Anda tidak memiliki wewenang untuk memproses antrean kapster lain.');
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function() use ($antrean) {
            $nowTime = Carbon::now()->format('H:i:s');
            $today = Carbon::today()->toDateString();
            $antrean->status = 'Diproses';
            if (empty($antrean->waktu_mulai_aktual)) {
                $antrean->waktu_mulai_aktual = $nowTime;
            }
            if ($antrean->tanggal < $today) {
                $antrean->tanggal = $today;
            }
            $antrean->touch();
            $antrean->save();
        }, 5);

        session()->flash('sync_success', 'Antrean #' . $antrean->nomor_antrean . ' berhasil mulai diproses!');
    }

    public function finishProcess($id) {
        $antrean = AntreanPemesanan::find($id);
        if (!$antrean) {
            session()->flash('sync_error', 'Antrean tidak ditemukan.');
            return;
        }

        if (!$this->canManageAntrean($antrean)) {
            session()->flash('sync_error', 'Akses ditolak: Anda tidak memiliki wewenang untuk menyelesaikan antrean kapster lain.');
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function() use ($antrean) {
            $nowTime = Carbon::now()->format('H:i:s');
            $today = Carbon::today()->toDateString();
            $antrean->status = 'Selesai';
            if (empty($antrean->waktu_mulai_aktual)) {
                $antrean->waktu_mulai_aktual = $nowTime;
            }
            $antrean->waktu_selesai_aktual = $nowTime;
            if ($antrean->tanggal < $today) {
                $antrean->tanggal = $today;
            }
            $antrean->touch();
            $antrean->save();
        }, 5);

        session()->flash('sync_success', 'Antrean #' . $antrean->nomor_antrean . ' berhasil diselesaikan!');
    }

    public function resetToWaiting($id) {
        $antrean = AntreanPemesanan::find($id);
        if (!$antrean) return;

        if (!$this->canManageAntrean($antrean)) {
            session()->flash('sync_error', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah antrean ini.');
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function() use ($antrean) {
            $antrean->status = 'Menunggu';
            $antrean->waktu_mulai_aktual = null;
            $antrean->touch();
            $antrean->save();
        }, 5);

        session()->flash('sync_success', 'Antrean #' . $antrean->nomor_antrean . ' berhasil dikembalikan ke antrean Menunggu.');
    }

    public function moveToKasir($id) {
        $antrean = AntreanPemesanan::find($id);
        if (!$antrean) return redirect()->route('admin.antrean');

        if (!$this->canManageAntrean($antrean)) {
            session()->flash('sync_error', 'Akses ditolak: Anda tidak memiliki wewenang untuk memproses kasir antrean kapster lain.');
            return;
        }

        return redirect()->route('admin.pos', ['antrean_id' => $id]);
    }

    public function openRescheduleModal($id) {
        $antrean = AntreanPemesanan::with(['pelanggan', 'layanan', 'kapster'])->find($id);
        if (!$antrean) {
            session()->flash('sync_error', 'Antrean tidak ditemukan.');
            return;
        }

        if (!$this->canManageAntrean($antrean)) {
            session()->flash('sync_error', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah jadwal antrean ini.');
            return;
        }

        $this->resetValidation();
        $this->rescheduleAntreanId = $id;
        $this->activeSelectedAntrean = $antrean;
        $this->rescheduleTanggal = $antrean->tanggal ?? Carbon::today()->toDateString();
        $this->rescheduleTime = substr($antrean->waktu_estimasi_mulai ?? '10:00:00', 0, 5);
        $this->rescheduleKapsterId = $antrean->kapster_id;
        $this->showRescheduleModal = true;
    }

    public function setRescheduleToday() {
        $this->rescheduleTanggal = Carbon::today()->toDateString();
    }

    public function setRescheduleTomorrow() {
        $this->rescheduleTanggal = Carbon::tomorrow()->toDateString();
    }

    public function saveReschedule() {
        $this->validate([
            'rescheduleTanggal' => 'required|date',
            'rescheduleTime' => 'required',
            'rescheduleKapsterId' => 'required|exists:users,id',
        ], [
            'rescheduleTanggal.required' => 'Tanggal baru wajib dipilih.',
            'rescheduleTime.required' => 'Jam estimasi wajib dipilih.',
            'rescheduleKapsterId.required' => 'Kapster bertugas wajib dipilih.',
        ]);

        $antreanCheck = AntreanPemesanan::find($this->rescheduleAntreanId);
        $durasiReschedule = ($antreanCheck && $antreanCheck->layanan) ? $antreanCheck->layanan->estimasi_durasi_menit : 30;
        $resConflict = \App\Models\AntreanPemesanan::checkConflict($this->rescheduleTanggal, $this->rescheduleTime, $durasiReschedule, $this->rescheduleKapsterId, $this->rescheduleAntreanId);
        if ($resConflict) {
            $this->addError('rescheduleTime', $resConflict['message']);
            return;
        }

        $antrean = AntreanPemesanan::find($this->rescheduleAntreanId);
        if (!$antrean) {
            session()->flash('sync_error', 'Antrean tidak ditemukan.');
            $this->showRescheduleModal = false;
            return;
        }

        if (!$this->canManageAntrean($antrean)) {
            session()->flash('sync_error', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah jadwal antrean ini.');
            $this->showRescheduleModal = false;
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function() use ($antrean) {
            $antrean->tanggal = $this->rescheduleTanggal;
            $antrean->waktu_estimasi_mulai = $this->rescheduleTime;
            $antrean->kapster_id = $this->rescheduleKapsterId;
            $antrean->status = 'Menunggu';
            $antrean->waktu_mulai_aktual = null;
            $antrean->touch();
            $antrean->save();
        }, 5);

        $this->showRescheduleModal = false;
        $nomor = $antrean->nomor_antrean;
        $this->reset(['rescheduleAntreanId', 'activeSelectedAntrean']);
        session()->flash('sync_success', 'Jadwal antrean #' . $nomor . ' berhasil diubah ke ' . $this->rescheduleTanggal . ' pukul ' . $this->rescheduleTime . ' WIB!');
    }

    public function openCancelModal($id) {
        $antrean = AntreanPemesanan::with(['pelanggan', 'layanan', 'kapster'])->find($id);
        if (!$antrean) {
            session()->flash('sync_error', 'Antrean tidak ditemukan.');
            return;
        }

        if (!$this->canManageAntrean($antrean)) {
            session()->flash('sync_error', 'Akses ditolak: Anda tidak memiliki wewenang untuk membatalkan antrean ini.');
            return;
        }

        $this->resetValidation();
        $this->cancelAntreanId = $id;
        $this->activeSelectedAntrean = $antrean;
        $this->cancelAlasan = 'Pelanggan berhalangan hadir / No-Show';
        $this->customCancelAlasan = '';
        $this->showCancelModal = true;
    }

    public function confirmCancel() {
        if ($this->cancelAlasan === 'Lainnya') {
            $this->validate([
                'customCancelAlasan' => 'required|string|min:3|max:255',
            ], [
                'customCancelAlasan.required' => 'Mohon tuliskan alasan pembatalan.',
            ]);
        }

        $antrean = AntreanPemesanan::find($this->cancelAntreanId);
        if (!$antrean) {
            session()->flash('sync_error', 'Antrean tidak ditemukan.');
            $this->showCancelModal = false;
            return;
        }

        if (!$this->canManageAntrean($antrean)) {
            session()->flash('sync_error', 'Akses ditolak: Anda tidak memiliki wewenang untuk membatalkan antrean ini.');
            $this->showCancelModal = false;
            return;
        }

        $finalReason = $this->cancelAlasan === 'Lainnya' ? $this->customCancelAlasan : $this->cancelAlasan;

        \Illuminate\Support\Facades\DB::transaction(function() use ($antrean, $finalReason) {
            $antrean->status = 'Batal';
            $antrean->alasan_batal = $finalReason;
            $antrean->touch();
            $antrean->save();
        }, 5);

        try {
            $antrean->kembalikanStokBahan();
        } catch (\Throwable $e) {}

        $this->showCancelModal = false;
        $nomor = $antrean->nomor_antrean;
        $this->reset(['cancelAntreanId', 'activeSelectedAntrean']);
        session()->flash('sync_success', 'Antrean #' . $nomor . ' telah dibatalkan (Alasan: ' . $finalReason . ').');
    }

    public function saveWalkIn() {
        if (auth()->check() && (auth()->user()->role === 'Kapster' || (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('Kapster')))) {
            $this->walkInKapster = auth()->id();
        }

        if (empty($this->walkInTime)) {
            $this->walkInTime = now()->format('H:i:s');
        }

        $this->validate([
            'walkInName' => 'required|string|max:255',
            'walkInLayanan' => 'required|exists:layanan,id',
            'walkInKapster' => 'required',
            'walkInTime' => 'required'
        ]);

        $tanggalWalkInCheck = now()->format('Y-m-d');
        if ($this->filterTanggal === 'tomorrow') {
            $tanggalWalkInCheck = now()->addDay()->format('Y-m-d');
        } elseif ($this->filterTanggal === 'custom' && !empty($this->customTanggal)) {
            $tanggalWalkInCheck = $this->customTanggal;
        }

        $layananModel = Layanan::find($this->walkInLayanan);
        $durasi = $layananModel ? $layananModel->estimasi_durasi_menit : 30;
        $conflict = \App\Models\AntreanPemesanan::checkConflict($tanggalWalkInCheck, $this->walkInTime, $durasi, $this->walkInKapster);
        if ($conflict) {
            $this->addError('walkInTime', $conflict['message']);
            return;
        }

        try {
            $dummyEmail = 'walkin_' . time() . '_' . Str::random(5) . '@rancukurin.local';
            $user = User::create([
                'nama' => $this->walkInName . ' (Walk-In)',
                'email' => $dummyEmail,
                'password' => bcrypt('password123'),
                'role' => 'Pelanggan',
            ]);
            try {
                $user->assignRole('Pelanggan');
            } catch (\Throwable $e) {}

            try {
                app(\App\Services\GoogleSheetsService::class)->appendRow('pengguna', [
                    'id' => $user->id,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'role' => 'Pelanggan',
                    'created_at' => now()->toDateTimeString(),
                ]);
            } catch (\Throwable $e) {}

            $kapsterId = $this->walkInKapster;
            if ($kapsterId === 'any' || empty($kapsterId)) {
                $kapster = User::where('role', 'Kapster')->orWhereHas('roles', function($q){$q->where('name', 'Kapster');})->inRandomOrder()->first();
                if (!$kapster) {
                    $this->addError('walkInKapster', 'Mohon maaf, saat ini tidak ada kapster yang tersedia.');
                    return;
                }
                $kapsterId = $kapster->id;
            }

            $tanggalWalkIn = now()->format('Y-m-d');
            if ($this->filterTanggal === 'tomorrow') {
                $tanggalWalkIn = now()->addDay()->format('Y-m-d');
            } elseif ($this->filterTanggal === 'custom' && !empty($this->customTanggal)) {
                $tanggalWalkIn = $this->customTanggal;
            }

            $antrean = AntreanPemesanan::create([
                'pelanggan_id' => $user->id,
                'kapster_id' => $kapsterId,
                'layanan_id' => $this->walkInLayanan,
                'tipe_antrean' => 'WalkIn',
                'nomor_antrean' => 'WLK-' . strtoupper(Str::random(5)),
                'tanggal' => $tanggalWalkIn,
                'waktu_estimasi_mulai' => $this->walkInTime,
                'status' => 'Menunggu'
            ]);

            try {
                $antrean->potongStokBahan();
            } catch (\Throwable $e) {}

            try {
                app(\App\Services\GoogleSheetsService::class)->appendRow('antrean_pemesanan', [
                    'id' => $antrean->id,
                    'nomor_antrean' => $antrean->nomor_antrean,
                    'pelanggan_id' => $antrean->pelanggan_id,
                    'kapster_id' => $antrean->kapster_id,
                    'layanan_id' => $antrean->layanan_id,
                    'tipe_antrean' => $antrean->tipe_antrean,
                    'tanggal' => $antrean->tanggal,
                    'waktu_estimasi_mulai' => $antrean->waktu_estimasi_mulai,
                    'status' => $antrean->status,
                    'created_at' => now()->toDateTimeString(),
                ]);
            } catch (\Throwable $e) {}

            $this->showWalkInModal = false;
            $this->reset(['walkInName', 'walkInLayanan', 'walkInTime']);
            if (auth()->check() && (auth()->user()->role === 'Kapster' || (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('Kapster')))) {
                $this->walkInKapster = auth()->id();
            } else {
                $this->reset('walkInKapster');
            }

            session()->flash('sync_success', 'Antrean Walk-In berhasil ditambahkan! Nomor Antrean: ' . $antrean->nomor_antrean);
        } catch (\Throwable $e) {
            $this->addError('walkInName', 'Gagal menambahkan antrean: ' . $e->getMessage());
        }
    }

    public function with() {
        $user = auth()->user();
        $isKapster = $user && ($user->role === 'Kapster' || (method_exists($user, 'hasRole') && $user->hasRole('Kapster')));
        $isOwner = $this->isOwner();

        $kapsters = User::whereHas('roles', function($q){$q->where('name', 'Kapster');})->get();
        if ($kapsters->isEmpty()) {
            $kapsters = User::where('role', 'Kapster')->get();
        }
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();

        // Sinkronkan status antrean aktif langsung dari Google Sheets agar instan dan bebas rollback di multi-kontainer serverless
        $lastSync = cache()->get('last_active_antrean_sync', 0);
        if (time() - $lastSync >= 5) {
            try {
                app(\App\Services\GoogleSheetsService::class)->pullActiveAntrean();
                cache()->put('last_active_antrean_sync', time(), 10);
            } catch (\Throwable $e) {}
        }

        // Ringkasan status kursi per kapster (untuk monitoring kerja setiap kapster)
        $kapsterSummary = [];
        foreach ($kapsters as $k) {
            $curr = AntreanPemesanan::where('kapster_id', $k->id)
                ->where('status', 'Diproses')
                ->with(['layanan', 'pelanggan'])
                ->orderBy('waktu_mulai_aktual', 'desc')
                ->first();

            $wait = AntreanPemesanan::where('kapster_id', $k->id)
                ->where('status', 'Menunggu')
                ->where(function($q) use ($today) {
                    $q->whereDate('tanggal', '>=', $today)
                      ->orWhereNull('tanggal');
                })
                ->count();

            $kapsterSummary[$k->id] = [
                'kapster' => $k,
                'current' => $curr,
                'waiting_count' => $wait,
            ];
        }

        $baseQuery = AntreanPemesanan::with(['pelanggan', 'kapster', 'layanan']);

        // Filter Kapster
        if ($this->filterKapster !== 'all') {
            $baseQuery->where('kapster_id', $this->filterKapster);
        }

        // 1. Sedang Diproses: selalu tampilkan antrean yang sedang aktif dikerjakan
        $diproses = (clone $baseQuery)
            ->where('status', 'Diproses')
            ->orderByDesc('updated_at')
            ->get();

        // 2. Menunggu Giliran:
        $menungguQuery = (clone $baseQuery)->where('status', 'Menunggu');
        if ($this->filterTanggal === 'today') {
            $menungguQuery->where(function($q) use ($today) {
                $q->whereDate('tanggal', '<=', $today)->orWhereNull('tanggal');
            });
        } elseif ($this->filterTanggal === 'tomorrow') {
            $menungguQuery->whereDate('tanggal', $tomorrow);
        } elseif ($this->filterTanggal === 'custom' && !empty($this->customTanggal)) {
            $menungguQuery->whereDate('tanggal', $this->customTanggal);
        }

        $totalMenungguCount = $menungguQuery->count();
        $menunggu = $menungguQuery->orderBy('tanggal', 'asc')->orderBy('waktu_estimasi_mulai', 'asc')->take(30)->get();

        // 3. Selesai:
        $selesaiQuery = (clone $baseQuery)->where('status', 'Selesai');
        if ($this->filterTanggal === 'today' || $this->filterTanggal === 'all') {
            $selesaiQuery->where(function($q) use ($today) {
                $q->whereDate('tanggal', $today)->orWhereDate('updated_at', $today);
            });
        } elseif ($this->filterTanggal === 'tomorrow') {
            $selesaiQuery->whereDate('tanggal', $tomorrow);
        } elseif ($this->filterTanggal === 'custom' && !empty($this->customTanggal)) {
            $selesaiQuery->whereDate('tanggal', $this->customTanggal);
        }

        $totalSelesaiCount = $selesaiQuery->count();
        $selesai = $selesaiQuery->orderByDesc('updated_at')->take(30)->get();

        $walkInTanggal = now()->format('Y-m-d');
        if ($this->filterTanggal === 'tomorrow') {
            $walkInTanggal = now()->addDay()->format('Y-m-d');
        } elseif ($this->filterTanggal === 'custom' && !empty($this->customTanggal)) {
            $walkInTanggal = $this->customTanggal;
        }
        $walkInBookedSlots = \App\Models\AntreanPemesanan::getBookedSlots($walkInTanggal, $this->walkInKapster);

        $walkInDurasi = 30;
        if ($this->walkInLayanan) {
            $l = Layanan::find($this->walkInLayanan);
            if ($l) $walkInDurasi = $l->estimasi_durasi_menit;
        }
        $walkInConflict = null;
        $walkInEstimasiSelesai = null;
        if ($this->walkInTime) {
            $walkInConflict = \App\Models\AntreanPemesanan::checkConflict($walkInTanggal, $this->walkInTime, $walkInDurasi, $this->walkInKapster);
            try {
                $rawParts = explode(':', trim($this->walkInTime));
                $pStart = \Carbon\Carbon::createFromFormat('H:i', sprintf('%02d:%02d', (int)$rawParts[0], (int)$rawParts[1]));
                $walkInEstimasiSelesai = $pStart->copy()->addMinutes($walkInDurasi)->format('H:i');
            } catch (\Throwable $e) {}
        }

        $rescheduleBookedSlots = [];
        $rescheduleConflict = null;
        $rescheduleEstimasiSelesai = null;
        if ($this->showRescheduleModal && $this->rescheduleAntreanId) {
            $rescheduleBookedSlots = \App\Models\AntreanPemesanan::getBookedSlots($this->rescheduleTanggal, $this->rescheduleKapsterId, $this->rescheduleAntreanId);
            $resDurasi = 30;
            if ($this->activeSelectedAntrean && $this->activeSelectedAntrean->layanan) {
                $resDurasi = $this->activeSelectedAntrean->layanan->estimasi_durasi_menit;
            }
            if ($this->rescheduleTime) {
                $rescheduleConflict = \App\Models\AntreanPemesanan::checkConflict($this->rescheduleTanggal, $this->rescheduleTime, $resDurasi, $this->rescheduleKapsterId, $this->rescheduleAntreanId);
                try {
                    $rawParts = explode(':', trim($this->rescheduleTime));
                    $pStart = \Carbon\Carbon::createFromFormat('H:i', sprintf('%02d:%02d', (int)$rawParts[0], (int)$rawParts[1]));
                    $rescheduleEstimasiSelesai = $pStart->copy()->addMinutes($resDurasi)->format('H:i');
                } catch (\Throwable $e) {}
            }
        }

        return [
            'isKapster' => $isKapster,
            'isOwner' => $isOwner,
            'kapsters' => $kapsters,
            'kapsterSummary' => $kapsterSummary,
            'layanans' => Layanan::where('is_active', true)->get(),
            'menunggu' => $menunggu,
            'totalMenungguCount' => $totalMenungguCount,
            'diproses' => $diproses,
            'selesai' => $selesai,
            'totalSelesaiCount' => $totalSelesaiCount,
            'today' => $today,
            'tomorrow' => $tomorrow,
            'walkInBookedSlots' => $walkInBookedSlots,
            'walkInConflict' => $walkInConflict,
            'walkInEstimasiSelesai' => $walkInEstimasiSelesai,
            'rescheduleBookedSlots' => $rescheduleBookedSlots,
            'rescheduleConflict' => $rescheduleConflict,
            'rescheduleEstimasiSelesai' => $rescheduleEstimasiSelesai,
        ];
    }
};
?>

<div class="flex flex-col w-full h-full min-h-full space-y-6" wire:poll.visible.10s>
    <!-- Top Control Bar -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            @if($isKapster)
                <a href="{{ route('staff.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-on-surface-variant hover:text-primary transition-colors mb-2 group">
                    <span class="material-symbols-outlined text-sm group-hover:-translate-x-1 transition-transform">arrow_back</span>
                    <span>Kembali ke Dashboard Kapster</span>
                </a>
            @endif
            <div class="flex items-center gap-2 mb-1 flex-wrap">
                <h1 class="font-headline-lg text-on-surface text-2xl lg:text-3xl font-bold">
                    @if($isKapster)
                        Papan Antrean &amp; Job Saya ({{ auth()->user()->nama }})
                    @else
                        Papan Kerja Antrean Terpadu
                    @endif
                </h1>
                @if($isOwner)
                    <span class="text-xs bg-amber-500/10 text-amber-400 px-2.5 py-0.5 rounded-full font-medium border border-amber-500/20 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">admin_panel_settings</span>
                        Akses Penuh Owner
                    </span>
                @elseif($isKapster)
                    <span class="text-xs bg-blue-500/10 text-blue-400 px-2.5 py-0.5 rounded-full font-medium border border-blue-500/20 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">shield_person</span>
                        Akses Khusus Kapster
                    </span>
                @endif
                <span class="text-xs bg-emerald-500/10 text-emerald-400 px-2.5 py-0.5 rounded-full font-medium border border-emerald-500/20 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Live Sync
                </span>
            </div>
            <p class="font-body-md text-sm text-on-surface-variant">
                @if($isOwner)
                    Mode Pemilik/Owner: Anda memiliki wewenang penuh untuk melakukan aksi dan mengelola antrean seluruh kapster.
                @elseif($isKapster)
                    Mode Kapster: Anda hanya dapat melakukan aksi (Mulai, Selesai, Kasir) pada orderan yang ditugaskan kepada Anda.
                @else
                    Manajemen antrean real-time. Setiap kapster mengerjakan pekerjaannya masing-masing.
                @endif
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
            <!-- Date Filter Pills -->
            <div class="flex items-center gap-1 bg-surface-container-high p-1 rounded-xl border border-outline-variant/30 text-xs">
                <button 
                    wire:click="$set('filterTanggal', 'all')" 
                    type="button" 
                    class="px-3 py-1.5 rounded-lg font-semibold transition-all {{ $filterTanggal === 'all' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Semua Aktif
                </button>
                <button 
                    wire:click="$set('filterTanggal', 'today')" 
                    type="button" 
                    class="px-3 py-1.5 rounded-lg font-semibold transition-all {{ $filterTanggal === 'today' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Hari Ini
                </button>
                <button 
                    wire:click="$set('filterTanggal', 'tomorrow')" 
                    type="button" 
                    class="px-3 py-1.5 rounded-lg font-semibold transition-all {{ $filterTanggal === 'tomorrow' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Besok
                </button>
                <input 
                    type="date" 
                    wire:model.live="customTanggal" 
                    wire:change="$set('filterTanggal', 'custom')"
                    class="bg-surface-container text-on-surface text-xs rounded-lg px-2 py-1 border border-outline-variant/50 outline-none cursor-pointer">
            </div>

            <!-- Manual Sync Button -->
            <button wire:click="syncFromSheets" type="button" class="bg-surface-container-high hover:bg-surface-container-highest text-primary border border-primary/30 text-xs font-bold rounded-xl px-3.5 py-2.5 flex items-center justify-center gap-1.5 transition-all shadow-sm">
                <span class="material-symbols-outlined text-[18px] animate-spin" wire:loading wire:target="syncFromSheets">sync</span>
                <span class="material-symbols-outlined text-[18px]" wire:loading.remove wire:target="syncFromSheets">sync</span>
                <span>Sinkron Spreadsheet</span>
            </button>

            <!-- Action Button -->
            <button wire:click="openWalkInModal" class="bg-primary hover:bg-primary-fixed text-on-primary text-xs font-bold rounded-xl px-4 py-2.5 flex items-center justify-center gap-1.5 transition-all shadow-md shadow-primary/20 shrink-0 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>Input Walk-In</span>
            </button>
        </div>
    </div>

    @if(session('sync_success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-3 rounded-xl text-xs flex items-center gap-2">
            <span class="material-symbols-outlined text-base">check_circle</span>
            <span>{{ session('sync_success') }}</span>
        </div>
    @endif
    @if(session('sync_error'))
        <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 p-3 rounded-xl text-xs flex items-center gap-2">
            <span class="material-symbols-outlined text-base">error</span>
            <span>{{ session('sync_error') }}</span>
        </div>
    @endif

    <!-- Kursi & Job Setiap Kapster (Card Lane Selector) -->
    <div class="bg-surface-container/60 rounded-2xl p-4 border border-outline-variant/30 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs uppercase font-bold tracking-wider text-on-surface-variant flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[18px] text-primary">airline_seat_recline_normal</span>
                Status Kursi &amp; Pekerjaan Setiap Kapster
            </span>
            <span class="text-[11px] text-on-surface-variant">Klik kartu kapster untuk melihat job spesifiknya</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Tab Semua Kapster -->
            <button 
                wire:click="$set('filterKapster', 'all')"
                type="button"
                class="p-3.5 rounded-xl border text-left transition-all flex flex-col justify-between gap-2 {{ $filterKapster === 'all' ? 'bg-primary/10 border-primary shadow-sm' : 'bg-surface-container-high/60 border-outline-variant/30 hover:border-outline-variant hover:bg-surface-container-high' }}">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-xs {{ $filterKapster === 'all' ? 'text-primary' : 'text-on-surface' }}">Semua Kapster</span>
                    <span class="material-symbols-outlined text-[18px] {{ $filterKapster === 'all' ? 'text-primary' : 'text-on-surface-variant' }}">groups</span>
                </div>
                <div class="text-[11px] text-on-surface-variant">
                    Tampilkan antrean seluruh staf terpadu
                </div>
            </button>

            <!-- Cards Per-Kapster -->
            @foreach($kapsters as $k)
                @php
                    $kStat = $kapsterSummary[$k->id] ?? null;
                    $isBusy = $kStat && $kStat['current'];
                    $isSelected = (string)$filterKapster === (string)$k->id;
                @endphp
                <button 
                    wire:click="$set('filterKapster', '{{ $k->id }}')"
                    type="button"
                    class="p-3.5 rounded-xl border text-left transition-all flex flex-col justify-between gap-2 relative {{ $isSelected ? 'bg-primary/15 border-primary shadow-md' : 'bg-surface-container-high/60 border-outline-variant/30 hover:border-outline-variant hover:bg-surface-container-high' }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-primary/20 text-primary flex items-center justify-center font-bold text-[10px]">
                                {{ substr($k->nama, 0, 2) }}
                            </div>
                            <span class="font-bold text-xs text-on-surface truncate">{{ $k->nama }}</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ ($kStat['waiting_count'] ?? 0) > 0 ? 'bg-primary/20 text-primary' : 'bg-surface-container-highest text-on-surface-variant' }}">
                            {{ $kStat['waiting_count'] ?? 0 }} Antre
                        </span>
                    </div>

                    <div class="text-[11px] mt-1">
                        @if($isBusy)
                            <div class="text-amber-400 font-semibold flex items-center gap-1.5 truncate">
                                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                                <span class="truncate">Mencukur: #{{ $kStat['current']->nomor_antrean }}</span>
                            </div>
                            <span class="text-[10px] text-on-surface-variant block truncate">
                                {{ optional($kStat['current']->layanan)->nama_layanan ?? '-' }}
                            </span>
                        @else
                            <div class="text-emerald-400 font-medium flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                                <span>Standby / Siap Melayani</span>
                            </div>
                            <span class="text-[10px] text-on-surface-variant block">Kursi Kosong</span>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
    </div>

    <!-- Kanban Board (3 Kolom: Menunggu, Sedang Diproses, Selesai) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 flex-1 min-h-[550px]">
        <!-- Column 1: Menunggu -->
        <div class="flex flex-col h-full bg-surface-container-low rounded-2xl shadow-lg border border-outline-variant/30 overflow-hidden">
            <div class="p-4 bg-surface-container/60 border-b border-outline-variant/30 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                    <h2 class="font-bold text-base text-on-surface">Menunggu Giliran</h2>
                </div>
                <span class="bg-amber-400/10 text-amber-400 border border-amber-400/20 font-mono text-xs px-2.5 py-0.5 rounded-full font-bold">
                    {{ $totalMenungguCount }}
                </span>
            </div>
            <div class="p-4 overflow-y-auto space-y-3.5 flex-1 max-h-[700px]">
                @if($totalMenungguCount > count($menunggu))
                    <div class="text-[11px] text-center text-on-surface-variant/70 italic pb-1">
                        Menampilkan {{ count($menunggu) }} antrean terdekat dari total {{ $totalMenungguCount }} antrean
                    </div>
                @endif
                @forelse($menunggu as $a)
                    @php
                        $isToday = $a->tanggal === $today;
                        $isTomorrow = $a->tanggal === $tomorrow;
                    @endphp
                    <div wire:key="antrean-menunggu-{{ $a->id }}" class="bg-surface-container rounded-xl p-4 shadow-sm hover:shadow-md transition-all border border-outline-variant/30 relative">
                        <div class="flex justify-between items-start mb-2.5">
                            <div>
                                <h3 class="font-bold text-sm text-on-surface leading-tight">{{ optional($a->pelanggan)->nama ?? 'Pelanggan Umum' }}</h3>
                                <p class="text-xs text-primary font-medium mt-0.5">{{ optional($a->layanan)->nama_layanan ?? '-' }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="font-mono text-xs px-2 py-0.5 rounded font-bold {{ $a->tipe_antrean === 'Online' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'bg-purple-500/20 text-purple-400 border border-purple-500/30' }}">
                                    {{ $a->nomor_antrean }}
                                </span>
                            </div>
                        </div>

                        <!-- Info Jadwal & Kapster -->
                        <div class="flex items-center justify-between text-xs text-on-surface-variant my-2.5 py-2 border-y border-outline-variant/20">
                            <span class="flex items-center gap-1 font-mono">
                                <span class="material-symbols-outlined text-[14px]">schedule</span>
                                <strong class="{{ $isTomorrow ? 'text-primary' : ($isToday ? 'text-emerald-400' : 'text-on-surface') }}">
                                    @if($isToday)
                                        Hari Ini {{ substr($a->waktu_estimasi_mulai, 0, 5) }}
                                    @elseif($isTomorrow)
                                        Besok {{ substr($a->waktu_estimasi_mulai, 0, 5) }}
                                    @else
                                        {{ $a->tanggal }} {{ substr($a->waktu_estimasi_mulai, 0, 5) }}
                                    @endif
                                </strong>
                            </span>
                            <span class="flex items-center gap-1 text-on-surface font-semibold">
                                <span class="material-symbols-outlined text-[14px] text-primary">person</span>
                                {{ optional($a->kapster)->nama ?? 'Semua' }}
                            </span>
                        </div>

                        @if($isOwner)
                            <!-- Akses Penuh Owner: Mulai Pengerjaan, Selesai Langsung, atau Ke Kasir -->
                            <div class="grid grid-cols-3 gap-1.5 pt-1">
                                <button 
                                    wire:click="startProcess({{ $a->id }})" 
                                    wire:loading.attr="disabled"
                                    wire:target="startProcess({{ $a->id }})"
                                    type="button"
                                    class="bg-primary hover:bg-primary-fixed text-on-primary font-bold text-xs rounded-lg py-2 transition-all flex items-center justify-center gap-1 shadow-sm shadow-primary/20 cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="startProcess({{ $a->id }})" class="material-symbols-outlined text-[15px]">play_arrow</span>
                                    <span wire:loading wire:target="startProcess({{ $a->id }})" class="material-symbols-outlined text-[15px] animate-spin">refresh</span>
                                    <span>Mulai</span>
                                </button>
                                <button 
                                    wire:click="finishProcess({{ $a->id }})" 
                                    wire:loading.attr="disabled"
                                    wire:target="finishProcess({{ $a->id }})"
                                    type="button"
                                    class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-lg py-2 transition-all flex items-center justify-center gap-1 shadow-sm cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="finishProcess({{ $a->id }})" class="material-symbols-outlined text-[15px]">check</span>
                                    <span wire:loading wire:target="finishProcess({{ $a->id }})" class="material-symbols-outlined text-[15px] animate-spin">refresh</span>
                                    <span>Selesai</span>
                                </button>
                                <button 
                                    wire:click="moveToKasir({{ $a->id }})" 
                                    wire:loading.attr="disabled"
                                    wire:target="moveToKasir({{ $a->id }})"
                                    type="button"
                                    class="bg-surface-container-highest hover:bg-surface-bright text-on-surface hover:text-primary font-bold text-xs rounded-lg py-2 transition-all flex items-center justify-center gap-1 border border-outline-variant/40 cursor-pointer shadow-sm disabled:opacity-50">
                                    <span wire:loading.remove wire:target="moveToKasir({{ $a->id }})" class="material-symbols-outlined text-[15px]">point_of_sale</span>
                                    <span wire:loading wire:target="moveToKasir({{ $a->id }})" class="material-symbols-outlined text-[15px] animate-spin">refresh</span>
                                    <span>Kasir</span>
                                </button>
                            </div>
                        @elseif($this->canManageAntrean($a))
                            <div class="grid grid-cols-1 gap-2 pt-1">
                                <button 
                                    wire:click="startProcess({{ $a->id }})" 
                                    wire:loading.attr="disabled"
                                    wire:target="startProcess({{ $a->id }})"
                                    type="button"
                                    class="w-full bg-primary hover:bg-primary-fixed text-on-primary font-bold text-xs rounded-lg py-2.5 transition-all flex items-center justify-center gap-1.5 shadow-sm shadow-primary/20 cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="startProcess({{ $a->id }})" class="material-symbols-outlined text-[16px]">play_arrow</span>
                                    <span wire:loading wire:target="startProcess({{ $a->id }})" class="material-symbols-outlined text-[16px] animate-spin">refresh</span>
                                    <span>Mulai Pengerjaan</span>
                                </button>
                            </div>
                        @else
                            <div class="w-full bg-surface-container-high/60 border border-outline-variant/30 text-on-surface-variant/80 font-medium text-xs rounded-lg py-2.5 flex items-center justify-center gap-1.5 cursor-not-allowed select-none">
                                <span class="material-symbols-outlined text-[16px] text-amber-400">lock</span>
                                <span>Khusus Kapster {{ optional($a->kapster)->nama ?? '-' }}</span>
                            </div>
                        @endif

                        @if($isOwner || $this->canManageAntrean($a))
                            <!-- Opsi Jadwalkan Ulang (Reschedule) & Batalkan Antrean -->
                            <div class="mt-2.5 pt-2 border-t border-outline-variant/20 flex items-center justify-between text-xs">
                                <button 
                                    wire:click="openRescheduleModal({{ $a->id }})" 
                                    type="button" 
                                    class="text-on-surface-variant hover:text-amber-400 transition-colors flex items-center gap-1 font-medium cursor-pointer py-1 px-1.5 rounded hover:bg-surface-container-high/60">
                                    <span class="material-symbols-outlined text-[15px] text-amber-400">edit_calendar</span>
                                    <span>Reschedule</span>
                                </button>
                                <button 
                                    wire:click="openCancelModal({{ $a->id }})" 
                                    type="button" 
                                    class="text-on-surface-variant hover:text-rose-400 transition-colors flex items-center gap-1 font-medium cursor-pointer py-1 px-1.5 rounded hover:bg-surface-container-high/60">
                                    <span class="material-symbols-outlined text-[15px] text-rose-400">cancel</span>
                                    <span>Batalkan</span>
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-on-surface-variant py-10 opacity-60 text-xs">
                        Tidak ada antrean menunggu untuk kriteria ini.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Column 2: Sedang Diproses -->
        <div class="flex flex-col h-full bg-surface-container-low rounded-2xl shadow-lg border border-outline-variant/30 overflow-hidden relative">
            <div class="p-4 bg-surface-container/60 border-b border-outline-variant/30 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-3 h-3 rounded-full bg-emerald-400 animate-pulse shadow-[0_0_8px_rgba(52,211,153,0.6)]"></div>
                    <h2 class="font-bold text-base text-on-surface">Sedang Diproses</h2>
                </div>
                <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono text-xs px-2.5 py-0.5 rounded-full font-bold">
                    {{ $diproses->count() }}
                </span>
            </div>
            <div class="p-4 overflow-y-auto space-y-3.5 flex-1 max-h-[700px]">
                @forelse($diproses as $a)
                    @php
                        $isToday = $a->tanggal === $today;
                        $isTomorrow = $a->tanggal === $tomorrow;
                    @endphp
                    <div wire:key="antrean-diproses-{{ $a->id }}" class="bg-surface-container rounded-xl p-4 shadow-xl border-l-[4px] border-emerald-500 relative group border-t border-r border-b border-outline-variant/30">
                        <div class="flex justify-between items-start mb-2.5">
                            <div>
                                <h3 class="font-bold text-sm text-on-surface leading-tight">{{ optional($a->pelanggan)->nama ?? 'Pelanggan Umum' }}</h3>
                                <p class="text-xs text-primary font-medium mt-0.5">{{ optional($a->layanan)->nama_layanan ?? '-' }}</p>
                            </div>
                            <span class="font-mono text-xs px-2 py-0.5 rounded font-bold {{ $a->tipe_antrean === 'Online' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'bg-purple-500/20 text-purple-400 border border-purple-500/30' }}">
                                {{ $a->nomor_antrean }}
                            </span>
                        </div>

                        <!-- Kapster yang menangani -->
                        <div class="bg-surface-container-high/80 rounded-lg p-2.5 text-xs space-y-1 my-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-on-surface-variant">Kapster Bertugas:</span>
                                <strong class="text-primary font-bold">{{ optional($a->kapster)->nama ?? '-' }}</strong>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-on-surface-variant">
                                <span>Mulai: {{ substr($a->waktu_mulai_aktual ?? $a->waktu_estimasi_mulai, 0, 5) }} WIB</span>
                                <span class="text-emerald-400 flex items-center gap-1 font-medium">
                                    <span class="material-symbols-outlined text-[13px] animate-spin">sync</span>
                                    Sedang Memotong
                                </span>
                            </div>
                        </div>

                        @if($isOwner || $this->canManageAntrean($a))
                            <div class="grid grid-cols-2 gap-2 mt-2">
                                <button 
                                    wire:click="finishProcess({{ $a->id }})" 
                                    wire:loading.attr="disabled"
                                    wire:target="finishProcess({{ $a->id }})"
                                    type="button"
                                    class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-lg py-2 transition-all flex items-center justify-center gap-1 shadow-sm cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="finishProcess({{ $a->id }})" class="material-symbols-outlined text-[16px]">check</span>
                                    <span wire:loading wire:target="finishProcess({{ $a->id }})" class="material-symbols-outlined text-[16px] animate-spin">refresh</span>
                                    <span>Selesai</span>
                                </button>
                                <button 
                                    wire:click="moveToKasir({{ $a->id }})" 
                                    wire:loading.attr="disabled"
                                    wire:target="moveToKasir({{ $a->id }})"
                                    type="button"
                                    class="bg-primary hover:bg-primary-fixed text-on-primary font-bold text-xs rounded-lg py-2 transition-all flex items-center justify-center gap-1 shadow-sm shadow-primary/20 cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="moveToKasir({{ $a->id }})" class="material-symbols-outlined text-[16px]">point_of_sale</span>
                                    <span wire:loading wire:target="moveToKasir({{ $a->id }})" class="material-symbols-outlined text-[16px] animate-spin">refresh</span>
                                    <span>Ke Kasir</span>
                                </button>
                            </div>
                            <div class="mt-2 pt-2 border-t border-outline-variant/20 flex items-center justify-between text-xs">
                                @if($isOwner)
                                    <button 
                                        wire:click="resetToWaiting({{ $a->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="resetToWaiting({{ $a->id }})"
                                        type="button"
                                        class="text-[11px] text-on-surface-variant hover:text-amber-400 transition-colors flex items-center gap-1 cursor-pointer">
                                        <span class="material-symbols-outlined text-[13px]">undo</span>
                                        <span>Kembalikan Menunggu</span>
                                    </button>
                                @else
                                    <div></div>
                                @endif
                                <button 
                                    wire:click="openCancelModal({{ $a->id }})" 
                                    type="button" 
                                    class="text-[11px] text-on-surface-variant hover:text-rose-400 transition-colors flex items-center gap-1 font-medium cursor-pointer">
                                    <span class="material-symbols-outlined text-[13px] text-rose-400">cancel</span>
                                    <span>Batalkan</span>
                                </button>
                            </div>
                        @else
                            <div class="mt-2 w-full bg-surface-container-high/60 border border-outline-variant/30 text-on-surface-variant/80 font-medium text-xs rounded-lg py-2 flex items-center justify-center gap-1.5 cursor-not-allowed select-none">
                                <span class="material-symbols-outlined text-[16px] text-emerald-400">lock</span>
                                <span>Ditangani {{ optional($a->kapster)->nama ?? '-' }}</span>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-on-surface-variant py-10 opacity-60 text-xs">
                        Tidak ada pengerjaan saat ini.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Column 3: Selesai -->
        <div class="flex flex-col h-full bg-surface-container-low rounded-2xl shadow-inner border border-outline-variant/30 overflow-hidden opacity-95">
            <div class="p-4 bg-surface-container/60 border-b border-outline-variant/30 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-3 h-3 rounded-full bg-surface-variant"></div>
                    <h2 class="font-bold text-base text-on-surface-variant">Selesai</h2>
                </div>
                <span class="bg-surface-container-highest text-on-surface-variant font-mono text-xs px-2.5 py-0.5 rounded-full font-bold">
                    {{ $totalSelesaiCount }}
                </span>
            </div>
            <div class="p-4 overflow-y-auto space-y-3.5 flex-1 max-h-[700px]">
                @if($totalSelesaiCount > count($selesai))
                    <div class="text-[11px] text-center text-on-surface-variant/70 italic pb-1">
                        Menampilkan {{ count($selesai) }} riwayat selesai terbaru dari total {{ $totalSelesaiCount }} antrean
                    </div>
                @endif
                @forelse($selesai as $a)
                    <div wire:key="antrean-selesai-{{ $a->id }}" class="bg-surface-container/70 rounded-xl p-4 shadow-sm border border-outline-variant/20 relative group hover:border-outline-variant/50 transition-all">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h3 class="font-bold text-xs text-on-surface-variant leading-tight">{{ optional($a->pelanggan)->nama ?? 'Pelanggan Umum' }}</h3>
                                <p class="text-[11px] text-on-surface-variant/70">{{ optional($a->layanan)->nama_layanan ?? '-' }}</p>
                            </div>
                            <span class="font-mono text-[10px] px-2 py-0.5 rounded font-bold bg-surface-container-highest text-on-surface-variant">
                                {{ $a->nomor_antrean }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-on-surface-variant pt-2 border-t border-outline-variant/20">
                            <span>Kapster: <strong>{{ optional($a->kapster)->nama ?? '-' }}</strong></span>
                            <span>{{ $a->tanggal }}</span>
                        </div>
                        @if($isOwner || $this->canManageAntrean($a))
                            <button 
                                wire:click="moveToKasir({{ $a->id }})" 
                                wire:loading.attr="disabled"
                                wire:target="moveToKasir({{ $a->id }})"
                                type="button"
                                class="w-full bg-surface-container-high hover:bg-surface-container-highest text-on-surface text-xs font-semibold rounded-lg py-1.5 mt-2.5 transition-colors border border-outline-variant/30 flex items-center justify-center gap-1 cursor-pointer disabled:opacity-50">
                                <span wire:loading.remove wire:target="moveToKasir({{ $a->id }})" class="material-symbols-outlined text-[14px]">receipt</span>
                                <span wire:loading wire:target="moveToKasir({{ $a->id }})" class="material-symbols-outlined text-[14px] animate-spin">refresh</span>
                                <span>Ke Kasir / Detail Nota</span>
                            </button>
                        @else
                            <div class="w-full bg-surface-container-high/40 border border-outline-variant/20 text-on-surface-variant/60 font-medium text-xs rounded-lg py-1.5 mt-2.5 flex items-center justify-center gap-1 cursor-not-allowed select-none">
                                <span class="material-symbols-outlined text-[14px] text-on-surface-variant/50">lock</span>
                                <span>Selesai oleh {{ optional($a->kapster)->nama ?? '-' }}</span>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-on-surface-variant py-10 opacity-60 text-xs">
                        Belum ada antrean selesai untuk filter ini.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Walk-In Modal -->
    @if($showWalkInModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-3 sm:p-4 md:p-6 overflow-y-auto">
            <div class="w-full max-w-xl bg-surface-container-high rounded-2xl shadow-2xl overflow-hidden border border-outline-variant/40 animate-[scale-up_0.2s_ease-out] max-h-[92vh] sm:max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3.5 sm:px-5 sm:py-4 bg-surface-container-highest border-b border-outline-variant/30 shrink-0">
                    <h2 class="font-bold text-lg text-on-surface">Tambah Antrean Walk-In</h2>
                    <button wire:click="$set('showWalkInModal', false)" class="text-on-surface-variant hover:text-primary transition-colors focus:outline-none">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <!-- Body -->
                <div class="p-4 sm:p-5 space-y-4 overflow-y-auto flex-1 min-h-0">
                    <!-- Customer Name -->
                    <div class="space-y-1.5">
                        <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Nama Pelanggan</label>
                        <input wire:model="walkInName" class="w-full bg-surface-container text-on-surface text-sm px-4 py-2.5 rounded-xl border border-outline-variant/40 focus:outline-none focus:border-primary transition-colors" placeholder="Masukkan nama pelanggan..." type="text">
                        @error('walkInName') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <!-- Service & Barber Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Layanan</label>
                            <select wire:model="walkInLayanan" class="w-full bg-surface-container text-on-surface text-sm px-3.5 py-2.5 rounded-xl border border-outline-variant/40 focus:outline-none focus:border-primary transition-colors">
                                <option value="">Pilih Layanan</option>
                                @foreach($layanans as $l)
                                    <option value="{{ $l->id }}">{{ $l->nama_layanan }}</option>
                                @endforeach
                            </select>
                            @error('walkInLayanan') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Kapster Bertugas</label>
                            @if($isKapster)
                                <div class="w-full bg-surface-container text-on-surface text-sm px-4 py-2.5 rounded-xl border border-outline-variant/40 flex items-center justify-between">
                                    <span>{{ auth()->user()->nama }}</span>
                                    <span class="text-xs text-primary font-bold">(Anda)</span>
                                </div>
                            @else
                                <select wire:model="walkInKapster" class="w-full bg-surface-container text-on-surface text-sm px-3.5 py-2.5 rounded-xl border border-outline-variant/40 focus:outline-none focus:border-primary transition-colors">
                                    <option value="">Pilih Kapster</option>
                                    <option value="any">Siapa Saja (Tersedia)</option>
                                    @foreach($kapsters as $k)
                                        <option value="{{ $k->id }}">{{ $k->nama }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('walkInKapster') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <!-- Manual Time Selection -->
                    <div class="p-4 rounded-xl bg-surface-container border border-outline-variant/30 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Jam Mulai (Manual)</label>
                                <p class="text-[11px] text-on-surface-variant">Atur jam mulai kedatangan pelanggan secara manual.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative">
                                    <input type="time" wire:model.live.debounce.300ms="walkInTime" class="bg-surface-container-high border {{ $walkInConflict ? 'border-rose-500 text-rose-400' : 'border-outline-variant/50 text-primary' }} focus:border-primary rounded-xl px-3 py-2 text-center text-base font-mono font-bold outline-none transition-all">
                                </div>
                                @if($walkInEstimasiSelesai)
                                    <div class="text-[11px] font-mono text-on-surface-variant bg-surface-container-highest px-2.5 py-2 rounded-xl whitespace-nowrap">
                                        s/d <strong class="text-on-surface">{{ $walkInEstimasiSelesai }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Quick Helper Buttons -->
                        <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-outline-variant/20">
                            <span class="text-[11px] text-on-surface-variant font-medium mr-1">Cepat:</span>
                            <button type="button" wire:click="setWalkInTimeNow" class="px-2.5 py-1 rounded-lg bg-primary/15 hover:bg-primary/25 border border-primary/30 text-primary text-xs font-semibold flex items-center gap-1 transition-all">
                                <span class="material-symbols-outlined text-[13px]">bolt</span> Sekarang ({{ now()->format('H:i') }})
                            </button>
                            @foreach(['10:00', '12:00', '13:30', '15:00', '16:30', '18:00', '19:30'] as $preset)
                                <button type="button" wire:click="setWalkInTime('{{ $preset }}')" class="px-2.5 py-1 rounded-lg {{ $walkInTime === $preset ? 'bg-primary text-on-primary font-bold' : 'bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-mono' }} text-xs transition-all">
                                    {{ $preset }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Status Alert -->
                    @if($walkInConflict)
                        <div class="p-3 rounded-xl bg-rose-500/15 border border-rose-500/40 text-rose-200 text-xs flex items-start gap-2.5 animate-[fadeIn_0.2s_ease-out]">
                            <span class="material-symbols-outlined text-rose-400 text-[18px] shrink-0 mt-0.5">event_busy</span>
                            <div class="space-y-0.5">
                                <strong class="text-rose-300 font-bold block">Jadwal Bertabrakan!</strong>
                                <p class="text-rose-200/90 leading-tight">{{ $walkInConflict['message'] }}</p>
                            </div>
                        </div>
                    @elseif(!empty($walkInTime) && $walkInEstimasiSelesai)
                        <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-emerald-400 text-[16px]">check_circle</span>
                                <span>Pukul <strong>{{ substr($walkInTime, 0, 5) }} - {{ $walkInEstimasiSelesai }} WIB</strong> tersedia</span>
                            </span>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono font-bold">Tersedia</span>
                        </div>
                    @endif
                    @error('walkInTime') <span class="text-rose-400 text-xs block font-semibold">{{ $message }}</span> @enderror

                    <!-- Penanda Jadwal yang Sudah Ter-booking -->
                    <div class="space-y-1.5 pt-1">
                        <div class="flex items-center justify-between text-xs text-on-surface-variant font-bold uppercase tracking-wider">
                            <span class="flex items-center gap-1 text-[11px]">
                                <span class="material-symbols-outlined text-rose-400 text-[15px]">lock_clock</span>
                                <span>Jadwal Ter-booking ({{ count($walkInBookedSlots) }})</span>
                            </span>
                        </div>
                        @if(count($walkInBookedSlots) > 0)
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 max-h-36 overflow-y-auto pr-1">
                                @foreach($walkInBookedSlots as $slot)
                                    @php
                                        $isCurrent = $walkInConflict && isset($walkInConflict['slot']) && $walkInConflict['slot']['id'] === $slot['id'];
                                    @endphp
                                    <div class="p-2 rounded-lg {{ $isCurrent ? 'bg-rose-500/25 border border-rose-500 text-rose-200' : 'bg-surface-container border border-rose-500/20 text-on-surface' }} text-xs font-mono">
                                        <div class="font-bold text-rose-300">{{ $slot['start'] }} - {{ $slot['end'] }}</div>
                                        <div class="text-[10px] text-on-surface-variant truncate">{{ $slot['layanan'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-2.5 rounded-lg bg-surface-container/60 text-xs text-on-surface-variant text-center">
                                Belum ada antrean lain di jam ini. Waktu bebas dipilih.
                            </div>
                        @endif
                    </div>
                </div>
                </div>
                <!-- Footer -->
                <div class="px-4 py-3 sm:px-5 sm:py-3.5 bg-surface-container-highest border-t border-outline-variant/30 flex items-center justify-end gap-2 sm:gap-3 shrink-0">
                    <button wire:click="$set('showWalkInModal', false)" type="button" class="px-4 py-2 text-xs font-semibold text-on-surface-variant hover:text-on-surface transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button wire:click="saveWalkIn" wire:loading.attr="disabled" type="button" class="px-5 py-2.5 bg-primary text-on-primary text-xs font-bold rounded-xl hover:bg-primary-fixed transition-all flex items-center gap-1.5 shadow-md shadow-primary/20 cursor-pointer">
                        <span wire:loading.remove wire:target="saveWalkIn" class="material-symbols-outlined text-[16px]">check</span>
                        <span wire:loading wire:target="saveWalkIn" class="material-symbols-outlined text-[16px] animate-spin">refresh</span>
                        <span>Simpan Antrean</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Reschedule Modal -->
    @if($showRescheduleModal && $activeSelectedAntrean)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-3 sm:p-4 md:p-6 overflow-y-auto">
            <div class="w-full max-w-xl bg-surface-container-high rounded-2xl shadow-2xl overflow-hidden border border-outline-variant/40 animate-[scale-up_0.2s_ease-out] max-h-[92vh] sm:max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3.5 sm:px-5 sm:py-4 bg-surface-container-highest border-b border-outline-variant/30 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-amber-400 text-2xl">edit_calendar</span>
                        <div>
                            <h2 class="font-bold text-base text-on-surface">Jadwalkan Ulang (Reschedule)</h2>
                            <p class="text-xs text-on-surface-variant font-mono">Antrean #{{ $activeSelectedAntrean->nomor_antrean }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('showRescheduleModal', false)" class="text-on-surface-variant hover:text-primary transition-colors focus:outline-none">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <!-- Info Customer & Service -->
                <div class="px-5 pt-4 pb-3 bg-surface-container/50 border-b border-outline-variant/20 flex items-center justify-between text-xs">
                    <div>
                        <div class="text-on-surface font-bold text-sm">{{ optional($activeSelectedAntrean->pelanggan)->nama ?? 'Pelanggan Umum' }}</div>
                        <div class="text-primary font-medium">{{ optional($activeSelectedAntrean->layanan)->nama_layanan ?? '-' }}</div>
                    </div>
                    <div class="text-right text-on-surface-variant">
                        <div>Jadwal Semula:</div>
                        <div class="font-mono text-amber-400 font-semibold">{{ $activeSelectedAntrean->tanggal }} {{ substr($activeSelectedAntrean->waktu_estimasi_mulai, 0, 5) }} WIB</div>
                    </div>
                </div>

                <!-- Body Form -->
                <div class="p-4 sm:p-5 space-y-4 overflow-y-auto flex-1 min-h-0">
                    <!-- Date Picker with Quick Buttons -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Pilih Tanggal Baru</label>
                            <div class="flex items-center gap-1.5">
                                <button wire:click="setRescheduleToday" type="button" class="text-[11px] px-2 py-0.5 rounded bg-surface-container-highest hover:bg-surface-bright text-on-surface transition-colors cursor-pointer {{ $rescheduleTanggal === $today ? 'border border-primary text-primary font-bold' : '' }}">
                                    Hari Ini
                                </button>
                                <button wire:click="setRescheduleTomorrow" type="button" class="text-[11px] px-2 py-0.5 rounded bg-surface-container-highest hover:bg-surface-bright text-on-surface transition-colors cursor-pointer {{ $rescheduleTanggal === $tomorrow ? 'border border-primary text-primary font-bold' : '' }}">
                                    Besok
                                </button>
                            </div>
                        </div>
                        <input wire:model="rescheduleTanggal" min="{{ $today }}" class="w-full bg-surface-container text-on-surface text-sm px-4 py-2.5 rounded-xl border border-outline-variant/40 focus:outline-none focus:border-primary transition-colors font-mono" type="date">
                        @error('rescheduleTanggal') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Kapster Selection -->
                    <div class="space-y-1.5">
                        <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Kapster Bertugas</label>
                        <select wire:model="rescheduleKapsterId" class="w-full bg-surface-container text-on-surface text-sm px-3.5 py-2.5 rounded-xl border border-outline-variant/40 focus:outline-none focus:border-primary transition-colors">
                            @foreach($kapsters as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }}</option>
                            @endforeach
                        </select>
                        @error('rescheduleKapsterId') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Manual Reschedule Time Selection -->
                    <div class="p-4 rounded-xl bg-surface-container border border-outline-variant/30 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Jam Baru (Manual)</label>
                                <p class="text-[11px] text-on-surface-variant">Atur jadwal baru secara manual.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative">
                                    <input type="time" wire:model.live.debounce.300ms="rescheduleTime" class="bg-surface-container-high border {{ $rescheduleConflict ? 'border-rose-500 text-rose-400' : 'border-outline-variant/50 text-primary' }} focus:border-primary rounded-xl px-3 py-2 text-center text-base font-mono font-bold outline-none transition-all">
                                </div>
                                @if($rescheduleEstimasiSelesai)
                                    <div class="text-[11px] font-mono text-on-surface-variant bg-surface-container-highest px-2.5 py-2 rounded-xl whitespace-nowrap">
                                        s/d <strong class="text-on-surface">{{ $rescheduleEstimasiSelesai }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Quick Helper Buttons -->
                        <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-outline-variant/20">
                            <span class="text-[11px] text-on-surface-variant font-medium mr-1">Cepat:</span>
                            @foreach(['09:30', '11:00', '13:00', '14:30', '16:00', '17:30', '19:00'] as $preset)
                                <button type="button" wire:click="setRescheduleTime('{{ $preset }}')" class="px-2.5 py-1 rounded-lg {{ $rescheduleTime === $preset ? 'bg-primary text-on-primary font-bold' : 'bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-mono' }} text-xs transition-all">
                                    {{ $preset }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Status Alert -->
                    @if($rescheduleConflict)
                        <div class="p-3 rounded-xl bg-rose-500/15 border border-rose-500/40 text-rose-200 text-xs flex items-start gap-2.5 animate-[fadeIn_0.2s_ease-out]">
                            <span class="material-symbols-outlined text-rose-400 text-[18px] shrink-0 mt-0.5">event_busy</span>
                            <div class="space-y-0.5">
                                <strong class="text-rose-300 font-bold block">Jadwal Bertabrakan!</strong>
                                <p class="text-rose-200/90 leading-tight">{{ $rescheduleConflict['message'] }}</p>
                            </div>
                        </div>
                    @elseif(!empty($rescheduleTime) && $rescheduleEstimasiSelesai)
                        <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-emerald-400 text-[16px]">check_circle</span>
                                <span>Pukul <strong>{{ substr($rescheduleTime, 0, 5) }} - {{ $rescheduleEstimasiSelesai }} WIB</strong> tersedia</span>
                            </span>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono font-bold">Tersedia</span>
                        </div>
                    @endif
                    @error('rescheduleTime') <span class="text-rose-400 text-xs block font-semibold">{{ $message }}</span> @enderror

                    <!-- Penanda Jadwal yang Sudah Ter-booking -->
                    <div class="space-y-1.5 pt-1">
                        <div class="flex items-center justify-between text-xs text-on-surface-variant font-bold uppercase tracking-wider">
                            <span class="flex items-center gap-1 text-[11px]">
                                <span class="material-symbols-outlined text-rose-400 text-[15px]">lock_clock</span>
                                <span>Jadwal Ter-booking Kapster Ini ({{ count($rescheduleBookedSlots) }})</span>
                            </span>
                        </div>
                        @if(count($rescheduleBookedSlots) > 0)
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 max-h-36 overflow-y-auto pr-1">
                                @foreach($rescheduleBookedSlots as $slot)
                                    @php
                                        $isCurrent = $rescheduleConflict && isset($rescheduleConflict['slot']) && $rescheduleConflict['slot']['id'] === $slot['id'];
                                    @endphp
                                    <div class="p-2 rounded-lg {{ $isCurrent ? 'bg-rose-500/25 border border-rose-500 text-rose-200' : 'bg-surface-container border border-rose-500/20 text-on-surface' }} text-xs font-mono">
                                        <div class="font-bold text-rose-300">{{ $slot['start'] }} - {{ $slot['end'] }}</div>
                                        <div class="text-[10px] text-on-surface-variant truncate">{{ $slot['layanan'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-2.5 rounded-lg bg-surface-container/60 text-xs text-on-surface-variant text-center">
                                Kapster ini belum memiliki antrean lain di tanggal ini.
                            </div>
                        @endif
                    </div>
                </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-3 sm:px-5 sm:py-3.5 bg-surface-container-highest border-t border-outline-variant/30 flex items-center justify-end gap-2 sm:gap-3 shrink-0">
                    <button wire:click="$set('showRescheduleModal', false)" type="button" class="px-4 py-2 text-xs font-semibold text-on-surface-variant hover:text-on-surface transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button wire:click="saveReschedule" wire:loading.attr="disabled" type="button" class="px-5 py-2.5 bg-primary text-on-primary text-xs font-bold rounded-xl hover:bg-primary-fixed transition-all flex items-center gap-1.5 shadow-md shadow-primary/20 cursor-pointer">
                        <span wire:loading.remove wire:target="saveReschedule" class="material-symbols-outlined text-[16px]">check</span>
                        <span wire:loading wire:target="saveReschedule" class="material-symbols-outlined text-[16px] animate-spin">refresh</span>
                        <span>Simpan Jadwal Baru</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Cancel Modal -->
    @if($showCancelModal && $activeSelectedAntrean)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-3 sm:p-4 md:p-6 overflow-y-auto">
            <div class="w-full max-w-md bg-surface-container-high rounded-2xl shadow-2xl overflow-hidden border border-outline-variant/40 animate-[scale-up_0.2s_ease-out] max-h-[92vh] sm:max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3.5 sm:px-5 sm:py-4 bg-surface-container-highest border-b border-outline-variant/30 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-rose-400 text-2xl">cancel</span>
                        <div>
                            <h2 class="font-bold text-base text-on-surface">Batalkan Antrean</h2>
                            <p class="text-xs text-on-surface-variant font-mono">Antrean #{{ $activeSelectedAntrean->nomor_antrean }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('showCancelModal', false)" class="text-on-surface-variant hover:text-primary transition-colors focus:outline-none">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <!-- Info Customer & Service -->
                <div class="px-5 pt-4 pb-3 bg-surface-container/50 border-b border-outline-variant/20 flex items-center justify-between text-xs">
                    <div>
                        <div class="text-on-surface font-bold text-sm">{{ optional($activeSelectedAntrean->pelanggan)->nama ?? 'Pelanggan Umum' }}</div>
                        <div class="text-primary font-medium">{{ optional($activeSelectedAntrean->layanan)->nama_layanan ?? '-' }}</div>
                    </div>
                    <div class="text-right text-on-surface-variant font-mono">
                        {{ $activeSelectedAntrean->tanggal }}<br>
                        {{ substr($activeSelectedAntrean->waktu_estimasi_mulai, 0, 5) }} WIB
                    </div>
                </div>

                <!-- Body Form -->
                <div class="p-4 sm:p-5 space-y-4 overflow-y-auto flex-1 min-h-0">
                    <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs text-rose-300 flex items-start gap-2">
                        <span class="material-symbols-outlined text-base shrink-0 mt-0.5">warning</span>
                        <span>Antrean ini akan dibatalkan dan statusnya diperbarui di database serta Google Spreadsheet.</span>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs uppercase font-bold text-on-surface-variant tracking-wider">Alasan Pembatalan</label>
                        <div class="space-y-1.5">
                            @foreach([
                                'Pelanggan berhalangan hadir / No-Show',
                                'Permintaan langsung dari pelanggan',
                                'Pelanggan tidak dapat dihubungi',
                                'Kendala teknis / Kapster berhalangan',
                                'Lainnya'
                            ] as $reasonOption)
                                <label class="flex items-center gap-2.5 p-2.5 rounded-xl border border-outline-variant/30 hover:bg-surface-container-highest/60 transition-colors cursor-pointer text-xs text-on-surface">
                                    <input type="radio" wire:model.live="cancelAlasan" value="{{ $reasonOption }}" class="text-rose-500 focus:ring-rose-500">
                                    <span>{{ $reasonOption }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    @if($cancelAlasan === 'Lainnya')
                        <div class="space-y-1.5 animate-[fade-in_0.2s_ease-out]">
                            <label class="block text-xs font-bold text-on-surface-variant">Tuliskan Alasan Pembatalan:</label>
                            <textarea wire:model="customCancelAlasan" rows="3" class="w-full bg-surface-container text-on-surface text-xs p-3 rounded-xl border border-outline-variant/40 focus:outline-none focus:border-rose-400 transition-colors" placeholder="Jelaskan alasan pembatalan..."></textarea>
                            @error('customCancelAlasan') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="px-4 py-3 sm:px-5 sm:py-3.5 bg-surface-container-highest border-t border-outline-variant/30 flex items-center justify-end gap-2 sm:gap-3 shrink-0">
                    <button wire:click="$set('showCancelModal', false)" type="button" class="px-4 py-2 text-xs font-semibold text-on-surface-variant hover:text-on-surface transition-colors cursor-pointer">
                        Kembali
                    </button>
                    <button wire:click="confirmCancel" wire:loading.attr="disabled" type="button" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 shadow-md shadow-rose-600/20 cursor-pointer">
                        <span wire:loading.remove wire:target="confirmCancel" class="material-symbols-outlined text-[16px]">cancel</span>
                        <span wire:loading wire:target="confirmCancel" class="material-symbols-outlined text-[16px] animate-spin">refresh</span>
                        <span>Konfirmasi Batalkan</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
