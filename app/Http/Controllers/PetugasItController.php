<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class PetugasItController extends Controller
{
    /**
     * Petugas IT berpindah ke mode preview role tertentu.
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

        session(['preview_role' => $role]);

        // Reset ke halaman awal (root) agar sidebar/permission baru langsung diterapkan.
        return redirect()->route('home')->with('success', 'Mode view: ' . User::PREVIEW_ROLES[$role]);
    }

    /**
     * Petugas IT kembali ke mode aslinya (menghapus preview role).
     */
    public function resetView(Request $request)
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isPetugasIt(), 403);

        $request->session()->forget('preview_role');

        return redirect()->route('home')->with('success', 'Kembali ke Mode IT. Preview dinonaktifkan.');
    }
}
