import { initSidebar } from './modules/sidebar.js';
import { initModals } from './modules/modals.js';
import { initJadwalMonitoring } from './pages/jadwal-monitoring.js';
import { initJadwalPiket } from './pages/jadwal-piket.js';

document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    initModals();
    initJadwalMonitoring();
    initJadwalPiket();
});
