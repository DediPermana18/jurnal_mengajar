export function initJadwalMonitoring() {
    document.querySelectorAll('#tableMonitoringKosong tbody tr[data-url]').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (!event.target.closest('a')) {
                window.location.href = row.dataset.url;
            }
        });
    });
}