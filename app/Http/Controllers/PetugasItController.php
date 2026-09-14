<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\LaporanKendala;
use App\Models\PengaturanJadwal;
use App\Models\Scopes\TestingDataScope;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PetugasItController extends Controller
{
    /**
     * Petugas IT berpindah ke mode impersonasi (active_role).
     * Authorization (Middleware/Gate/Policy) dan navigasi akan mengikuti
     * role yang dipilih tanpa perlu login ulang.
     *
     * Auth tetap milik Petugas IT / QA. Data guru/jurnal pada portal Guru
     * mengikuti "context target guru" yang tersimpan di session
     * (impersonate_target_id) — lihat trait ResolvesTargetGuru pada
     * controller portal guru.
     */
    public function switchView(Request $request)
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isPetugasIt(), 403);

        $role = $request->input('role');

        abort_unless(
            array_key_exists($role, User::PREVIEW_ROLES),
            422,
            'Role preview tidak valid.'
        );

        session(['active_role' => $role]);

        if (in_array($role, ['guru_mapel', 'guru_piket', 'wali_kelas'], true)) {
            // Bila target yang tersimpan tidak cocok dengan role yang baru
            // dipilih, set ulang ke guru testing default yang sesuai.
            // Untuk view Wali Kelas, hanya guru yang terdaftar sebagai Wali Kelas.
            if (! $this->targetFitsActiveRole(session('impersonate_target_id'), $role)) {
                $defaultGuruQuery = User::where('role', User::ROLE_GURU)
                    ->where('is_testing_data', true)
                    ->where('is_active', true);

                if ($role === 'wali_kelas') {
                    $defaultGuruQuery = $defaultGuruQuery->where(function ($q) {
                        $q->where('sub_role', 'wali_kelas')
                            ->orWhereHas('kelasWali');
                    });
                }

                $defaultGuru = $defaultGuruQuery->orderBy('id')->first();

                if ($defaultGuru) {
                    session(['impersonate_target_id' => $defaultGuru->id]);
                } else {
                    $request->session()->forget('impersonate_target_id');
                }
            }
        } else {
            // Keluar dari konteks guru: target guru tidak relevan lagi.
            $request->session()->forget('impersonate_target_id');
        }

        $redirect = match ($role) {
            'guru_mapel' => route('guru.dashboard'),
            'guru_piket' => route('piket.dashboard'),
            'wali_kelas' => route('walikelas.dashboard'),
            'satpam' => route('satpam.dashboard'),
            'waka_kurikulum' => route('kurikulum.dashboard'),
            'waka_sdm' => route('waka-sdm.dashboard'),
            'waka_kesiswaan' => route('waka-kesiswaan.dashboard'),
            'kepsek' => route('kepsek.dashboard'),
            default => route('home'), // Admin TU — dashboard Admin TU di halaman utama.
        };

        return redirect($redirect)->with('success', 'View: '.User::PREVIEW_ROLES[$role]);
    }

    /**
     * Apakah id target yang tersimpan masih valid untuk role preview aktif?
     */
    private function targetFitsActiveRole(mixed $targetId, string $role): bool
    {
        if ($targetId === null || $targetId === '') {
            return false;
        }

        $target = User::withoutGlobalScope(TestingDataScope::class)
            ->where('id', $targetId)
            ->where('role', User::ROLE_GURU)
            ->where('is_active', true)
            ->where('is_testing_data', true)
            ->first();

        if (! $target) {
            return false;
        }

        if ($role === 'wali_kelas') {
            return (string) $target->sub_role === 'wali_kelas'
                || Kelas::where('id_wali_kelas', $target->id)->exists();
        }

        return true;
    }

    /**
     * Petugas IT kembali ke mode aslinya (menghapus active_role & target guru).
     */
    public function resetView(Request $request)
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isPetugasIt(), 403);

        $request->session()->forget(['active_role', 'impersonate_target_id']);

        return redirect()->route('home')->with('success', 'Kembali ke Mode IT. Impersonasi dinonaktifkan.');
    }

    /**
     * Set context target guru untuk portal Guru (Switch View As → Guru Mapel).
     *
     * Hanya akun Guru testing (is_testing_data = 1) yang boleh dipilih agar
     * mode QA IT tidak pernah menyentuh data real. Kirim impersonate_target_id
     * kosong untuk kembali ke "akun saya" (tanpa target).
     */
    public function selectImpersonateTarget(Request $request)
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isPetugasIt(), 403);

        $id = $request->input('impersonate_target_id');

        if ($id === '' || $id === null) {
            $request->session()->forget('impersonate_target_id');

            return back()->with('success', 'Context target guru direset ke akun Anda sendiri.');
        }

        $target = User::where('role', User::ROLE_GURU)
            ->where('is_active', true)
            ->find($id);

        abort_unless($target, 422, 'Target guru tidak ditemukan.');

        abort_unless(
            (bool) $target->is_testing_data,
            422,
            'Hanya akun Guru testing (is_testing_data = 1) yang dapat dipilih sebagai target.'
        );

        // Saat preview Wali Kelas, target harus guru yang terdaftar sebagai Wali Kelas.
        if (session('active_role') === 'wali_kelas') {
            $isWaliKelas = (string) $target->sub_role === 'wali_kelas'
                || Kelas::where('id_wali_kelas', $target->id)->exists();

            abort_unless(
                $isWaliKelas,
                422,
                'Target guru bukan Wali Kelas (tidak terdaftar sebagai wali kelas di kelas mana pun).'
            );
        }

        $request->session()->put('impersonate_target_id', (int) $target->id);

        return back()->with('success', 'Context target guru: '.$target->nama);
    }

    /**
     * Simpan preferensi mode pandang data testing ke sesi ('all' / 'real' / 'testing').
     *
     * Catatan: isolasi data pada global scope TestingDataScope bersifat ketat —
     * Petugas IT / QA hanya melihat data testing (is_testing_data = true). Preferensi
     * ini dipertahankan untuk kebutuhan sesi / kompatibilitas dan tidak lagi
     * memengaruhi query pada scope global.
     */
    public function setTestingView(Request $request)
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isPetugasIt(), 403);

        $mode = (string) $request->input('mode', 'all');

        abort_unless(in_array($mode, ['all', 'real', 'testing'], true), 422, 'Mode data tidak valid.');

        session(['testing_view' => $mode]);

        $label = [
            'all' => 'Semua Data',
            'real' => 'Hanya Data Real',
            'testing' => 'Hanya Data Testing',
        ][$mode];

        return back()->with('success', 'Mode Data Testing: '.$label);
    }

    /**
     * Nyalakan / matikan Mode Maintenance (Mode Perbaikan Sistem) secara instan.
     * Khusus Petugas IT / QA Tester — status tersimpan global (database + cache),
     * sehingga seluruh pengguna non-IT/QA langsung dialihkan ke halaman
     * maintenance saat aktif, dan kembali normal saat dimatikan.
     */
    public function toggleMaintenanceMode(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->isPetugasIt(),
            403,
            'Akses ditolak. Hanya Petugas IT / QA Tester yang dapat mengatur Mode Maintenance.'
        );

        $active = $request->boolean('maintenance_mode');

        PengaturanJadwal::setMaintenanceMode($active);

        return back()->with(
            'success',
            $active
                ? 'Mode Maintenance DIAKTIFKAN. Pengguna selain IT/QA akan melihat halaman pemeliharaan.'
                : 'Mode Maintenance dimatikan. Sistem kembali normal.'
        );
    }

    /**
     * Dashboard Petugas IT / QA Tester: helpdesk "Laporan Kendala" + status server.
     *
     * Berbeda dari model transaksional lain, IT perlu melihat SELURUH laporan
     * (real + testing) karena laporan inilah yang harus ditindaklanjuti,
     * sehingga global scope TestingDataScope sengaja dilewati.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->isPetugasIt(),
            403,
            'Akses ditolak. Khusus Petugas IT / QA Tester.'
        );

        $kendala = LaporanKendala::withoutGlobalScope(TestingDataScope::class)
            ->with('pelapor:id,nama,role,sub_role')
            ->latest()
            ->get();

        $pendingCount = $kendala->where('status', LaporanKendala::STATUS_PENDING)->count();
        $prosesCount = $kendala->where('status', LaporanKendala::STATUS_PROSES)->count();
        $selesaiCount = $kendala->where('status', LaporanKendala::STATUS_SELESAI)->count();

        $dbOnline = false;
        try {
            DB::select('select 1');
            $dbOnline = true;
        } catch (\Throwable) {
            $dbOnline = false;
        }

        return view('admin.it.dashboard', [
            'kendala' => $kendala,
            'pendingCount' => $pendingCount,
            'prosesCount' => $prosesCount,
            'selesaiCount' => $selesaiCount,
            'dbOnline' => $dbOnline,
            'maintenanceActive' => PengaturanJadwal::isMaintenanceModeActive(),
            'appEnv' => app()->environment(),
            'appName' => config('app.name'),
            'laravelVersion' => app()->version(),
            'phpVersion' => PHP_VERSION,
            'serverTime' => now(),
        ]);
    }

    /**
     * Ubah status laporan kendala (pending/proses/selesai) oleh Petugas IT.
     */
    public function updateKendalaStatus(Request $request, $id)
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->isPetugasIt(),
            403,
            'Akses ditolak. Khusus Petugas IT / QA Tester.'
        );

        $kendala = LaporanKendala::withoutGlobalScope(TestingDataScope::class)->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:pending,proses,selesai'],
        ]);

        $kendala->update(['status' => $data['status']]);

        return back()->with('success', 'Status laporan kendala diubah menjadi '.$kendala->status_label.'.');
    }
}
