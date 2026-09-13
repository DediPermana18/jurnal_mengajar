<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class PetugasItController extends Controller
{
    /**
     * Petugas IT berpindah ke mode impersonasi (active_role).
     * Authorization (Middleware/Gate/Policy) dan navigasi akan mengikuti
     * role yang dipilih tanpa perlu login ulang.
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

        // Reset ke halaman awal (root) agar sidebar/permission baru langsung diterapkan.
        return redirect()->route('home')->with('success', 'View: '.User::PREVIEW_ROLES[$role]);
    }

    /**
     * Petugas IT kembali ke mode aslinya (menghapus active_role).
     */
    public function resetView(Request $request)
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isPetugasIt(), 403);

        $request->session()->forget('active_role');

        return redirect()->route('home')->with('success', 'Kembali ke Mode IT. Impersonasi dinonaktifkan.');
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
}
