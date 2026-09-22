<?php

namespace App\Http\Controllers;

use App\Exports\MataPelajaranExport;
use App\Imports\MataPelajaranImport;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class MataPelajaranController extends Controller
{
    /**
     * Proteksi akses: Petugas TU/Admin TU dan Waka Kurikulum diizinkan.
     */
    public function __construct()
    {
        $user = request()->user();

        $isPetugasTu = ($user && $user->role === 'admin' && in_array($user->sub_role, [null, 'petugas_tu', 'admin_tu'], true))
            || ($user && $user->role === 'admin_tu');
        $isKurikulum = ($user && $user->role === 'admin' && $user->sub_role === 'waka_kurikulum')
            || ($user && in_array($user->role, ['admin_kurikulum', 'waka_kurikulum', 'kurikulum'], true));

        // Petugas IT / QA Tester: peninjau semua role (impersonasi admin_tu / waka_kurikulum)
        $isAllowed = ($user && $user->isPetugasIt()) || $isPetugasTu || $isKurikulum;

        if (! $isAllowed) {
            abort(403, 'Akses ditolak.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = MataPelajaran::with('jurusan');

        // Search Filter (Cari Nama Mapel / Kode Mapel)
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_mapel', 'like', "%{$search}%")
                    ->orWhere('kode_mapel', 'like', "%{$search}%");
            });
        }

        // Filter Kelompok / Jenis Mapel
        if ($request->filled('kelompok')) {
            $query->where('kelompok', 'LIKE', '%'.$request->kelompok.'%');
        }

        $totalMapel = MataPelajaran::count();

        $dataMapel = $query->orderBy('nama_mapel', 'asc')
            ->paginate(10)
            ->appends($request->query());

        $jenisOptions = [
            'Muatan Umum',
            'Kejuruan',
            'Muatan Lokal',
        ];

        return view('admin.mata-pelajaran.index', compact('dataMapel', 'totalMapel', 'jenisOptions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $jenisOptions = [
            'Muatan Umum',
            'Kejuruan',
            'Muatan Lokal',
        ];

        $dataJurusan = Jurusan::orderBy('nama_jurusan', 'asc')->get();

        return view('admin.mata-pelajaran.create', compact('jenisOptions', 'dataJurusan'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $mapel = MataPelajaran::findOrFail($id);

        $jenisOptions = [
            'Muatan Umum',
            'Kejuruan',
            'Muatan Lokal',
        ];

        $dataJurusan = Jurusan::orderBy('nama_jurusan', 'asc')->get();

        return view('admin.mata-pelajaran.edit', compact('mapel', 'jenisOptions', 'dataJurusan'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! $request->filled('kelompok') && $request->filled('jenis_mapel')) {
            $request->merge(['kelompok' => $request->input('jenis_mapel')]);
        }

        $kelompok = $request->input('kelompok');

        $validated = $request->validate([
            'kode_mapel' => [
                'required',
                'string',
                'max:20',
                Rule::unique('mata_pelajaran', 'kode_mapel')->whereNull('deleted_at'),
            ],
            'nama_mapel' => 'required|string|max:100',
            'kelompok' => 'required|string|max:100',
            'jurusan_id' => [
                'required_if:kelompok,Kejuruan',
                'nullable',
                'exists:jurusan,id',
            ],
        ], [
            'kode_mapel.unique' => 'Kode Mata Pelajaran sudah digunakan.',
            'kode_mapel.required' => 'Kode Mata Pelajaran wajib diisi.',
            'nama_mapel.required' => 'Nama Mata Pelajaran wajib diisi.',
            'kelompok.required' => 'Jenis Mapel wajib dipilih.',
            'jurusan_id.required' => 'Jurusan wajib dipilih untuk Mata Pelajaran Kejuruan.',
            'jurusan_id.required_if' => 'Jurusan wajib dipilih untuk Mata Pelajaran Kejuruan.',
            'jurusan_id.exists' => 'Jurusan yang dipilih tidak valid.',
        ]);

        MataPelajaran::create([
            'kode_mapel' => strtoupper(trim($validated['kode_mapel'])),
            'nama_mapel' => trim($validated['nama_mapel']),
            'kelompok' => $validated['kelompok'],
            'jurusan_id' => $validated['kelompok'] === 'Kejuruan' ? $validated['jurusan_id'] : null,
        ]);

        return redirect()
            ->route('mapel.index')
            ->with('success', 'Mata Pelajaran berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $mapel = MataPelajaran::findOrFail($id);

        if (! $request->filled('kelompok') && $request->filled('jenis_mapel')) {
            $request->merge(['kelompok' => $request->input('jenis_mapel')]);
        }

        $kelompok = $request->input('kelompok');

        $validated = $request->validate([
            'kode_mapel' => [
                'required',
                'string',
                'max:20',
                Rule::unique('mata_pelajaran', 'kode_mapel')->ignore($mapel->id)->whereNull('deleted_at'),
            ],
            'nama_mapel' => 'required|string|max:100',
            'kelompok' => 'required|string|max:100',
            'jurusan_id' => [
                'required_if:kelompok,Kejuruan',
                'nullable',
                'exists:jurusan,id',
            ],
        ], [
            'kode_mapel.unique' => 'Kode Mata Pelajaran sudah digunakan.',
            'kode_mapel.required' => 'Kode Mata Pelajaran wajib diisi.',
            'nama_mapel.required' => 'Nama Mata Pelajaran wajib diisi.',
            'kelompok.required' => 'Jenis Mapel wajib dipilih.',
            'jurusan_id.required' => 'Jurusan wajib dipilih untuk Mata Pelajaran Kejuruan.',
            'jurusan_id.required_if' => 'Jurusan wajib dipilih untuk Mata Pelajaran Kejuruan.',
            'jurusan_id.exists' => 'Jurusan yang dipilih tidak valid.',
        ]);

        $mapel->update([
            'kode_mapel' => strtoupper(trim($validated['kode_mapel'])),
            'nama_mapel' => trim($validated['nama_mapel']),
            'kelompok' => $validated['kelompok'],
            'jurusan_id' => $validated['kelompok'] === 'Kejuruan' ? $validated['jurusan_id'] : null,
        ]);

        return redirect()
            ->route('mapel.index')
            ->with('success', 'Data Mata Pelajaran berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $mapel = MataPelajaran::findOrFail($id);
        $mapel->delete();

        return redirect()
            ->route('mapel.index')
            ->with('success', 'Mata Pelajaran berhasil dihapus.');
    }

    /**
     * Export data mata pelajaran — format xlsx (default) atau csv.
     */
    public function export(Request $request)
    {
        $format = $request->input('format', 'xlsx');
        $filename = 'data_mata_pelajaran_'.date('Y-m-d_His');

        if ($format === 'csv') {
            return Excel::download(new MataPelajaranExport(false), $filename.'.csv', ExcelFormat::CSV, [
                'Content-Type' => 'text/csv',
            ]);
        }

        return Excel::download(new MataPelajaranExport(false), $filename.'.xlsx', ExcelFormat::XLSX);
    }

    /**
     * Unduh template file Excel untuk import mata pelajaran.
     */
    public function downloadTemplate()
    {
        $filename = 'template_import_mata_pelajaran.xlsx';

        return Excel::download(new MataPelajaranExport(true), $filename, ExcelFormat::XLSX);
    }

    /**
     * Import data mata pelajaran dari file Excel / CSV.
     * Menggunakan DB::beginTransaction() & DB::rollBack() untuk menjamin integritas data.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file_mapel' => 'nullable|file|mimes:xlsx,xls,csv,txt|max:10240',
            'file' => 'nullable|file|mimes:xlsx,xls,csv,txt|max:10240',
            'excel_file' => 'nullable|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'file_mapel.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file_mapel.max' => 'Ukuran file maksimal 10 MB.',
            'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
            'excel_file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'excel_file.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        $uploadedFile = $request->file('file_mapel')
            ?? $request->file('file')
            ?? $request->file('excel_file');

        if (! $uploadedFile) {
            return back()->withErrors([
                'file_mapel' => 'File Excel / CSV wajib dipilih.',
            ]);
        }

        DB::beginTransaction();
        try {
            $importer = new MataPelajaranImport;

            $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
            $readerType = in_array($extension, ['csv', 'txt'], true) ? ExcelFormat::CSV : null;

            Excel::import($importer, $uploadedFile, null, $readerType);

            DB::commit();

            $total = $importer->importedCount + $importer->updatedCount;
            if ($importer->updatedCount > 0) {
                $successMsg = "Import mata pelajaran berhasil! {$importer->importedCount} mapel baru ditambahkan, {$importer->updatedCount} mapel diperbarui.";
            } else {
                $successMsg = "{$total} Data Mata Pelajaran berhasil diimport!";
            }

            if ($importer->skippedCount > 0) {
                $successMsg .= " ({$importer->skippedCount} baris kosong dilewati).";
            }

            $session = redirect()->route('mapel.index')->with('success', $successMsg);

            if (! empty($importer->rowErrors)) {
                $session = $session->with('import_warnings', $importer->rowErrors);
            }

            return $session;

        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->route('mapel.index')->with('error', 'Import mata pelajaran gagal: '.$e->getMessage());
        }
    }
}
