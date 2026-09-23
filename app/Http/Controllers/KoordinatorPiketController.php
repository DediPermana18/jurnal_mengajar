<?php

namespace App\Http\Controllers;

use App\Models\JadwalPiket;
use App\Models\RekapPiketHarian;
use App\Models\ShiftPiket;
use App\Models\StatusKehadiranGuru;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Panel Koordinator Piket — "Kelola Piket Shift (Koordinator)".
 *
 * Koordinator Piket adalah tugas dinamis dari jadwal piket (bukan role tetap):
 * guru yang hari ini tercantum sebagai koordinator pagi/siang mendapat panel
 * ringkasan khusus shift-nya:
 *  - Daftar petugas piket (anggota) shift yang dipimpin hari ini;
 *  - Monitoring / input status kehadiran guru anggota shift;
 *  - Penyusunan Rekap Piket Shift (catatan_pagi / catatan_siang) yang
 *    dikirim ke Waka Piket untuk validasi.
 *
 * Akses dibatasi middleware 'koordinator-piket' (EnsureKoordinatorPiket).
 */
class KoordinatorPiketController extends Controller
{
    /**
     * Map hari Inggris -> Indonesia (pola WakaSdm/WakaPiket).
     */
    protected function getHariIndonesia(Carbon $date): string
    {
        $map = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        return $map[$date->format('l')] ?? 'Senin';
    }

    /**
     * Label & informasi jam untuk sebuah shift (dari master ShiftPiket bila ada).
     */
    protected function infoShift(string $shift): array
    {
        $shiftPiket = ShiftPiket::where('is_active', true)
            ->whereRaw('LOWER(nama) LIKE ?', [$shift === 'pagi' ? 'pagi%' : 'siang%'])
            ->orderBy('urutan')
            ->first();

        return [
            'label' => $shift === 'pagi' ? 'Shift Pagi' : 'Shift Siang',
            'ikon' => $shift === 'pagi' ? 'bi-sun' : 'bi-moon-stars',
            'jam' => $shiftPiket ? $shiftPiket->jam_label : null,
        ];
    }

    /**
     * Panel Koordinator Piket — ringkasan shift yang dipimpin hari ini.
     */
    public function panel(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $now = Carbon::now();
        $todayStr = $now->toDateString();
        $hariIniStr = $this->getHariIndonesia($now);

        $shiftsSaya = $user->koordinatorShiftHariIni();

        $tim = collect();
        $kehadiran = collect();
        $infoShift = [];
        foreach ($shiftsSaya as $shift) {
            $anggota = JadwalPiket::petugasShiftBertugas($now, $shift);
            $tim[$shift] = $anggota;
            $infoShift[$shift] = $this->infoShift($shift);

            $kehadiran[$shift] = StatusKehadiranGuru::whereDate('tanggal', $todayStr)
                ->whereIn('user_id', $anggota->pluck('id'))
                ->get()
                ->keyBy('user_id');
        }

        // Rekap hari ini (untuk prefill catatan shift & status validasi Waka Piket).
        $rekap = RekapPiketHarian::untukTanggal($todayStr);

        return view('koordinator.piket', compact(
            'now',
            'todayStr',
            'hariIniStr',
            'shiftsSaya',
            'tim',
            'kehadiran',
            'infoShift',
            'rekap'
        ));
    }

    /**
     * Simpan monitoring kehadiran guru anggota shift (bulk update per anggota).
     */
    public function simpanKehadiran(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'shift' => 'required|in:pagi,siang',
            'status' => 'required|array',
            'status.*' => 'required|in:'.implode(',', StatusKehadiranGuru::STATUSES),
            'keterangan' => 'nullable|array',
            'keterangan.*' => 'nullable|string|max:1000',
        ], [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'shift.required' => 'Shift wajib diisi.',
            'shift.in' => 'Shift tidak valid.',
            'status.required' => 'Status kehadiran wajib diisi.',
            'status.*.in' => 'Status kehadiran tidak valid.',
        ]);

        $user = $request->user();
        $carbonTanggal = Carbon::parse($validated['tanggal']);

        // Guard: user harus memimpin shift tsb pada tanggal (berdasarkan jadwal).
        $shifts = JadwalPiket::koordinatorShiftBertugas($carbonTanggal, (int) $user->id);
        abort_unless(
            $shifts[$validated['shift']],
            403,
            'Anda tidak bertugas sebagai Koordinator pada shift tersebut di tanggal ini.'
        );

        // Hanya anggota shift yang sah yang boleh diubah statusnya.
        $timIds = JadwalPiket::petugasShiftBertugas($carbonTanggal, $validated['shift'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $submittedIds = array_map('intval', array_keys($validated['status']));

        abort_unless(
            empty(array_diff($submittedIds, $timIds)),
            422,
            'Terdapat guru di luar anggota shift Anda pada daftar kehadiran.'
        );

        $keteranganList = $validated['keterangan'] ?? [];

        foreach ($validated['status'] as $rawUserId => $status) {
            $userId = (int) $rawUserId;

            $record = StatusKehadiranGuru::where('user_id', $userId)
                ->whereDate('tanggal', $validated['tanggal'])
                ->first();

            // Guard: data testing hanya boleh dimutasi oleh Petugas IT / QA.
            $this->authorizeTestingMutation($record);

            StatusKehadiranGuru::updateOrCreate(
                [
                    'user_id' => $userId,
                    'tanggal' => $validated['tanggal'],
                ],
                [
                    'status' => $status,
                    'keterangan' => trim((string) ($keteranganList[$rawUserId] ?? '')) ?: null,
                    'updated_by' => $user->id,
                ]
            );
        }

        $labelShift = $validated['shift'] === 'pagi' ? 'Pagi' : 'Siang';
        $labelTanggal = $carbonTanggal->translatedFormat('d F Y');

        return back()->with(
            'success',
            "Monitoring kehadiran guru Shift {$labelShift} pada {$labelTanggal} berhasil disimpan."
        );
    }

    /**
     * Kirim Rekap Piket Shift ke Waka Piket (catatan_* pada rekap_piket_harian).
     *
     * Membuat baris rekap bila belum ada, mengisi catatan shift yang dipimpin,
     * dan tetap berstatus draft menunggu validasi Waka Piket. Setelah status
     * 'validated' catatan terkunci (integritas dokumen).
     */
    public function simpanRekap(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'shift' => 'required|in:pagi,siang',
            'catatan' => 'required|string|max:2000',
        ], [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'shift.required' => 'Shift wajib diisi.',
            'shift.in' => 'Shift tidak valid.',
            'catatan.required' => 'Catatan rekap shift wajib diisi.',
            'catatan.max' => 'Catatan rekap shift maksimal :max karakter.',
        ]);

        $user = $request->user();
        $carbonTanggal = Carbon::parse($validated['tanggal']);

        // Guard: user harus memimpin shift tsb pada tanggal (berdasarkan jadwal).
        $shifts = JadwalPiket::koordinatorShiftBertugas($carbonTanggal, (int) $user->id);
        abort_unless(
            $shifts[$validated['shift']],
            403,
            'Anda tidak bertugas sebagai Koordinator pada shift tersebut di tanggal ini.'
        );

        $rekap = RekapPiketHarian::untukTanggal($validated['tanggal']);

        // Guard: rekap data testing hanya dapat diubah oleh Petugas IT / QA.
        $this->authorizeTestingMutation($rekap);

        abort_if(
            $rekap->exists && $rekap->isValidated(),
            422,
            'Rekap Piket pada tanggal tersebut sudah divalidasi Waka Piket dan terkunci.'
        );

        $kolomKoordinator = 'koordinator_'.$validated['shift'].'_user_id';
        $kolomCatatan = 'catatan_'.$validated['shift'];

        if (! $rekap->exists) {
            $rekap->status = RekapPiketHarian::STATUS_DRAFT;
            $rekap->{$kolomKoordinator} = $user->id;
        } elseif (empty($rekap->{$kolomKoordinator})) {
            $rekap->{$kolomKoordinator} = $user->id;
        }

        $rekap->{$kolomCatatan} = trim((string) $validated['catatan']);
        $rekap->save();

        $labelShift = $validated['shift'] === 'pagi' ? 'Pagi' : 'Siang';
        $labelTanggal = $carbonTanggal->translatedFormat('d F Y');

        return back()->with(
            'success',
            "Rekap Piket Shift {$labelShift} {$labelTanggal} berhasil dikirim. Menunggu validasi Waka Piket."
        );
    }
}