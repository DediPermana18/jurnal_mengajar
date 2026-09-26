<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\AgendaRutin;
use Illuminate\Http\Request;

class AgendaRutinController extends Controller
{
    /**
     * Simpan / perbarui konfigurasi Agenda Rutin / Upacara Sekolah.
     *
     * Mendukung dua format payload:
     *  - Mode gabungan (dipakai halaman Master Jam Pelajaran):
     *      agenda[Senin][jam_ke]     = 1
     *      agenda[Senin][is_active]  = 1
     *      agenda[Jumat][jam_ke]     = 2
     *      agenda[Jumat][is_active]  = 0
     *    Upacara Bendera (Senin) & Pembiasaan Jumat tersimpan dalam SATU request.
     *  - Mode legacy satu hari per request:
     *      hari = Senin, jam_ke = 1, is_active = 1
     */
    public function upsert(Request $request)
    {
        if ($request->has('agenda') && is_array($request->input('agenda'))) {
            return $this->upsertBulk($request);
        }

        $validated = $request->validate([
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'jam_ke' => 'required|integer|min:1|max:20',
            'is_active' => 'nullable|boolean',
            'redirect_tab' => 'nullable|string|in:Senin-Kamis,Jumat',
            'redirect_shift' => 'nullable|integer',
            'redirect_mode' => 'nullable|string|in:global,shift',
            'redirect_ta' => 'nullable|integer',
        ]);

        $hari = $validated['hari'];
        $isActive = $request->has('is_active') ? (bool) $request->input('is_active') : false;
        $shiftId = $this->requestShiftId($request);

        $this->saveAgendaHari($hari, (int) $validated['jam_ke'], $isActive, $shiftId);

        $redirectTab = $request->input('redirect_tab', ($hari === 'Jumat' ? 'Jumat' : 'Senin-Kamis'));
        $statusText = $isActive ? 'diaktifkan & dikunci' : 'dinonaktifkan';

        return redirect()
            ->route('admin.jam-pelajaran.index', $this->redirectParams($request, $redirectTab))
            ->with('success', "Agenda Rutin \"{$this->agendaName($hari)}\" (Hari {$hari} Jam ke-{$validated['jam_ke']}) berhasil {$statusText}.");
    }

    /**
     * Simpan Upacara Bendera (Senin) & Pembiasaan Jumat dalam satu request.
     */
    protected function upsertBulk(Request $request)
    {
        $request->validate([
            'agenda' => 'required|array',
            'agenda.*.jam_ke' => 'nullable|integer|min:1|max:20',
            'agenda.*.is_active' => 'nullable|boolean',
            'redirect_tab' => 'nullable|string|in:Senin-Kamis,Jumat',
            'redirect_shift' => 'nullable|integer',
            'redirect_mode' => 'nullable|string|in:global,shift',
            'redirect_ta' => 'nullable|integer',
        ]);

        $agenda = $request->input('agenda', []);
        $shiftId = $this->requestShiftId($request);
        $savedHari = [];

        foreach ($agenda as $hari => $data) {
            if (! is_array($data) || ! in_array($hari, ['Senin', 'Jumat'], true)) {
                continue;
            }
            $jamKe = $data['jam_ke'] ?? null;
            if ($jamKe === null || $jamKe === '') {
                continue;
            }
            $this->saveAgendaHari($hari, (int) $jamKe, ! empty($data['is_active']), $shiftId);
            $savedHari[] = $hari;
        }

        $redirectTab = $request->input('redirect_tab', 'Senin-Kamis');

        if (empty($savedHari)) {
            return redirect()
                ->route('admin.jam-pelajaran.index', $this->redirectParams($request, $redirectTab))
                ->with('error', 'Tidak ada pengaturan Upacara/Pembiasaan yang valid untuk disimpan.');
        }

        $label = implode(' & ', array_map(
            fn ($hari) => $hari === 'Jumat' ? 'Pembiasaan Jumat' : 'Upacara Bendera',
            $savedHari
        ));

        return redirect()
            ->route('admin.jam-pelajaran.index', $this->redirectParams($request, $redirectTab))
            ->with('success', "Pengaturan Kegiatan Khusus ({$label}) berhasil disimpan.");
    }

    /**
     * Simpan / update satu hari agenda rutin per konteks shift
     * (updateOrCreate per hari+jam_ke+shift_id+is_testing_data).
     *
     * @param  int  $shiftId  0 = Global, > 0 = konfigurasi khusus shift tsb.
     */
    protected function saveAgendaHari(string $hari, int $jamKe, bool $isActive, int $shiftId = 0): void
    {
        // Auto-label data testing: Petugas IT / QA Tester (termasuk saat
        // impersonation) menyimpan is_testing_data = 1, user lain = 0.
        $isTesting = auth()->user()?->isPetugasIt() ? 1 : 0;

        // Guard: simpan ulang tidak boleh menimpa data testing (kecuali IT/QA).
        // Guard diberi batas shift juga — record testing pada satu shift tidak
        // boleh memblokir shift lain dengan kombinasi hari+jam_ke yang sama.
        $existing = AgendaRutin::where('hari', $hari)
            ->where('jam_ke', $jamKe)
            ->ofShift($shiftId)
            ->first();
        $this->authorizeTestingMutation($existing);

        // updateOrCreate mencegah error duplicate entry: kombinasi
        // (hari, jam_ke, shift_id, is_testing_data) kini unik.
        AgendaRutin::updateOrCreate(
            [
                'hari' => $hari,
                'jam_ke' => $jamKe,
                'shift_id' => $shiftId,
                'is_testing_data' => $isTesting,
            ],
            [
                'nama_agenda' => $this->agendaName($hari),
                'is_active' => $isActive,
                'shift_id' => $shiftId,
                'is_testing_data' => $isTesting,
            ]
        );
    }

    /**
     * Konteks shift yang dikirim form agenda (redirect_shift; kosong = Global).
     */
    protected function requestShiftId(Request $request): int
    {
        $value = $request->input('redirect_shift');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : 0;
    }

    protected function agendaName(string $hari): string
    {
        return match ($hari) {
            'Senin' => 'Upacara Bendera',
            'Jumat' => 'Pembiasaan Jumat',
            default => 'Agenda Rutin',
        };
    }

    protected function redirectParams(Request $request, string $defaultTab): array
    {
        $redirectParams = ['tab' => $request->input('redirect_tab', $defaultTab)];

        $redirectShift = $request->input('redirect_shift');
        if ($redirectShift !== null && $redirectShift !== '' && (int) $redirectShift > 0) {
            $redirectParams['shift'] = (int) $redirectShift;
        }
        if ($request->input('redirect_mode') === 'shift') {
            $redirectParams['mode'] = 'shift';
        }
        $redirectTa = $request->input('redirect_ta');
        if ($redirectTa !== null && $redirectTa !== '' && (int) $redirectTa > 0) {
            $redirectParams['ta'] = (int) $redirectTa;
        }

        return $redirectParams;
    }
}