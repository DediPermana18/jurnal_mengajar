<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public const SUB_ROLES = [
        'petugas_tu',
        'waka_kurikulum',
        'waka_sdm',
        'satpam',
    ];

    public const SUB_ROLE_LABELS = [
        'petugas_tu' => 'Petugas TU',
        'waka_kurikulum' => 'Waka Kurikulum',
        'waka_sdm' => 'Waka SDM',
        'satpam' => 'Petugas Keamanan / Satpam',
    ];

    protected function authorizePetugasTU(): void
    {
        $role = Auth::check() ? Auth::user()->role : null;

        abort_if(
            !in_array($role, ['admin', 'admin_tu', 'super_admin'], true),
            403,
            'Akses ditolak. Hanya Petugas TU atau Admin yang dapat mengelola user.'
        );
    }

    public function index(Request $request)
    {
        $this->authorizePetugasTU();

        $query = User::query()
            ->where('role', '!=', User::ROLE_GURU)
            ->orderBy('nama')
            ->orderBy('username');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($userQuery) use ($search) {
                $userQuery->where('nama', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sub_role') && in_array($request->input('sub_role'), self::SUB_ROLES, true)) {
            $query->where('sub_role', $request->input('sub_role'));
        }

        if ($request->filled('status') && $request->status !== 'Semua Status') {
            if ($request->status === 'Aktif' || $request->status === '1') {
                $query->where('is_active', true);
            } elseif ($request->status === 'Tidak Aktif' || $request->status === 'Nonaktif' || $request->status === '0') {
                $query->where('is_active', false);
            }
        }

        $dataUsers = $query->paginate(15)->withQueryString();

        return view('admin.users.index', [
            'dataUsers' => $dataUsers,
            'subRoles' => self::SUB_ROLE_LABELS,
        ]);
    }

    public function create()
    {
        $this->authorizePetugasTU();

        return view('admin.users.create', [
            'subRoles' => self::SUB_ROLE_LABELS,
        ]);
    }

    public function edit($id)
    {
        $this->authorizePetugasTU();

        return view('admin.users.edit', [
            'user' => User::where('role', '!=', User::ROLE_GURU)->findOrFail($id),
            'subRoles' => self::SUB_ROLE_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePetugasTU();

        $validated = $this->validateUser($request);
        $kodeAktivasi = $validated['kode_aktivasi'] ?: $this->generateActivationCode();

        User::create([
            'nama' => $validated['name'],
            'username' => $validated['username'],
            'nip' => $validated['nip'] ?? null,
            'sub_role' => $validated['sub_role'],
            'role' => $this->roleForSubRole($validated['sub_role']),
            'kode_aktivasi' => $kodeAktivasi,
            'password' => $validated['username'],
            'is_active' => true,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User berhasil ditambahkan. Password awal menggunakan username.');
    }

    public function update(Request $request, $id)
    {
        $this->authorizePetugasTU();

        $user = $this->findNonGuruUser($id);
        $validated = $this->validateUser($request, $user->id);

        $user->update([
            'nama' => $validated['name'],
            'username' => $validated['username'],
            'nip' => $validated['nip'] ?? null,
            'sub_role' => $validated['sub_role'],
            'role' => $this->roleForSubRole($validated['sub_role']),
            'kode_aktivasi' => $validated['kode_aktivasi'] ?? $user->kode_aktivasi,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Data user berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $this->authorizePetugasTU();

        $user = $this->findNonGuruUser($id);
        abort_if(Auth::id() === $user->id, 422, 'Akun yang sedang digunakan tidak dapat dihapus.');

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User berhasil dihapus.');
    }

    public function resetPassword($id)
    {
        $this->authorizePetugasTU();

        $user = $this->findNonGuruUser($id);
        $user->update(['password' => $user->username]);

        return redirect()->route('admin.users.index')->with('success', 'Password di-reset ke username user.');
    }

    protected function validateUser(Request $request, ?int $ignoreId = null): array
    {
        $uniqueUsername = 'unique:users,username' . ($ignoreId ? ',' . $ignoreId : '');
        $uniqueNip = 'nullable|string|max:50|unique:users,nip' . ($ignoreId ? ',' . $ignoreId : '');
        $uniqueActivation = 'nullable|string|max:100|unique:users,kode_aktivasi' . ($ignoreId ? ',' . $ignoreId : '');

        return $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:100', $uniqueUsername],
            'nip' => $uniqueNip,
            'sub_role' => ['required', 'in:' . implode(',', self::SUB_ROLES)],
            'kode_aktivasi' => $uniqueActivation,
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan.',
            'nip.unique' => 'NIP sudah digunakan.',
            'sub_role.required' => 'Sub-role wajib dipilih.',
            'sub_role.in' => 'Sub-role tidak valid.',
            'kode_aktivasi.unique' => 'Kode aktivasi sudah digunakan.',
        ]);
    }

    protected function roleForSubRole(string $subRole): string
    {
        return 'admin';
    }

    protected function findNonGuruUser($id): User
    {
        return User::where('role', '!=', User::ROLE_GURU)->findOrFail($id);
    }

    public function toggleStatus($id)
    {
        $this->authorizePetugasTU();

        $user = $this->findNonGuruUser($id);
        abort_if(Auth::id() === $user->id, 422, 'Tidak dapat mengubah status akun yang sedang digunakan.');

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        $statusLabel = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('admin.users.index')->with('success', "Status akun {$user->nama} berhasil {$statusLabel}.");
    }

    protected function generateActivationCode(): string
    {
        do {
            $code = 'AKT-' . Str::upper(Str::random(8));
        } while (User::withTrashed()->where('kode_aktivasi', $code)->exists());

        return $code;
    }
}
