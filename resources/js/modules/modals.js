export function initModals() {
    // 1. Modal Tolak Izin Guru Handler
    const modalTolak = document.getElementById('modalTolakIzin');
    if (modalTolak) {
        modalTolak.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const izinId = btn.getAttribute('data-izin-id');
            const nama = btn.getAttribute('data-izin-nama');
            const formAction = btn.getAttribute('data-action-url') || '';
            const form = document.getElementById('formTolakIzin');
            if (form && formAction) {
                form.setAttribute('action', formAction);
            }
            const desc = document.getElementById('tolakIzinDesc');
            if (desc && nama) {
                desc.textContent = 'Tolak izin untuk ' + nama + '. Isi catatan penolakan di bawah.';
            }
        });
    }

    // Global helper for opening Edit Ruangan Modal
    window.openEditRuanganModal = function(id, kode, nama, lokasi, pengurusIds) {
        const form = document.getElementById('formEditRuangan');
        if (form) {
            form.action = '/admin/ruangan/' + id;
        }
        const kodeInput = document.getElementById('editKodeRuangan');
        if (kodeInput) kodeInput.value = kode;
        const namaInput = document.getElementById('editNamaRuangan');
        if (namaInput) namaInput.value = nama;
        const lokasiInput = document.getElementById('editLokasiRuangan');
        if (lokasiInput) lokasiInput.value = lokasi;
    };

    // Global helper for opening Edit Tahun Ajaran Modal
    window.openEditTahunAjaranModal = function(id, tahunAjaran, semester) {
        const form = document.getElementById('formEditTahunAjaran');
        if (form) {
            form.action = '/admin/tahun-ajaran/' + id;
        }
        const tahunInput = document.getElementById('editTahunAjaran');
        if (tahunInput) tahunInput.value = tahunAjaran;
        const semesterSelect = document.getElementById('editSemester');
        if (semesterSelect) semesterSelect.value = semester;
    };
}
