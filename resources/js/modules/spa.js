/**
 * Navigasi SPA / seamless (tanpa full page reload) pada sidebar.
 *
 * Backend memakai Unpoly (window.up), SPA ringan berbasis pjax.
 *
 * Prinsip kerja:
 * 1. HANYA tautan navigasi sidebar (`.nav-btn[href]` & `.submenu-item-link`
 *    di dalam `#appSidebar`) yang di-follow Unpoly — semua role tercakup.
 * 2. Setiap klik hanya men-swap fragmen `<main id="page-content">`. Sidebar &
 *    header TIDAK pernah di-reload sehingga:
 *       - posisi scroll sidebar tetap bertahan,
 *       - dropdown akordeon yang terbuka (`openMenu` x-data) tidak ter-reset,
 *       - JavaScript global (Alpine, Bootstrap, komponen layout) tetap hidup.
 * 3. Active state menu disinkronkan ulang setiap URL berubah lewat event
 *    `up:location:changed`. Unpoly menerbitkan event ini SETELAH memanggil
 *    `history.pushState()` / `replaceState()` (di-patch-nya), sehingga
 *    `window.location.pathname` sudah final saat handler jalan — mencakup
 *    klik link sidebar maupun tombol back/forward browser.
 *    (Perhatian: Unpoly 3.x TIDAK punya event `up:render`; memakai nama itu
 *    membuat handler tidak pernah terpanggil — sidebar tidak pernah disinkron.)
 * 4. Konten Alpine (x-data) baru yang masuk di-inisialisasi ulang lewat
 *    `up:fragment:inserted` (diterbitkan SETELAH fragmen masuk ke DOM), via
 *    `Alpine.initTree()` (aman — elemen yang sudah ter-init dilewati Alpine).
 * 5. Jika Unpoly gagal dimuat (mis. offline), seluruh tautan kembali berperilaku
 *    sebagai navigasi penuh (graceful degradation).
 */

const PAGE_TARGET = '#page-content';

/** Elemen sidebar aktif (ada di semua branch role). */
function sidebar() {
    return document.getElementById('appSidebar');
}

/** Pathname aktif, dinormalisasi tanpa trailing slash ("/admin/guru/"). */
function currentPath() {
    const path = window.location.pathname || '/';
    const normalized = path.replace(/\/+$/, '');
    return normalized || '/';
}

/**
 * Link sidebar yang paling cocok dengan URL aktif.
 * Kunci: href yang paling PANJANG dan menjadi prefix (per segment) URL aktif.
 * Beranda ("/") hanya cocok jika URL aktif persis "/".
 */
function bestSidebarLink(path) {
    const root = sidebar();
    if (!root) return null;

    const links = root.querySelectorAll('.nav-btn[href], .submenu-item-link');
    let best = null;
    let bestLength = -1;

    links.forEach((link) => {
        let hrefPath;
        try {
            hrefPath = new URL(link.getAttribute('href'), window.location.origin).pathname;
        } catch (e) {
            return; // href tidak valid → abaikan
        }
        hrefPath = hrefPath.replace(/\/+$/, '') || '/';

        const matches = hrefPath === '/'
            ? path === '/'
            : path === hrefPath || path.startsWith(hrefPath + '/');

        if (matches && hrefPath.length > bestLength) {
            best = link;
            bestLength = hrefPath.length;
        }
    });

    return best;
}

/**
 * Sinkronkan highlight menu sidebar sesuai URL aktif.
 * Idempotent: aman dipanggil saat initial load maupun setelah swap SPA.
 */
export function syncSidebarActiveState() {
    const root = sidebar();
    if (!root) return;

    const best = bestSidebarLink(currentPath());

    // Bersihkan highlight lama terlebih dahulu → dijamin hanya SATU yang menyala.
    root.querySelectorAll('.nav-btn.active, .submenu-item-link.active')
        .forEach((el) => el.classList.remove('active'));

    if (!best) return;

    best.classList.add('active');

    // Item submenu (dropdown) ikut "menyalakan" tombol induknya.
    if (best.matches('.submenu-item-link')) {
        const group = best.closest('.nav-item-container');
        const button = group && group.querySelector(':scope > .nav-btn');
        if (button) button.classList.add('active');
    }
}

/** Inisialisasi konten Alpine (x-data) yang baru dimasukkan oleh swap SPA. */
function initNewAlpineContent() {
    if (!window.Alpine || typeof Alpine.initTree !== 'function') return;
    const main = document.querySelector(PAGE_TARGET);
    if (main) Alpine.initTree(main);
}

/** Hook utama SPA; dipanggil dari app.js setelah DOMContentLoaded. */
export function initSpa() {
    // Graceful degradation: tanpa Unpoly, navigasi penuh tetap normal.
    if (!window.up) return;

    // 1) Hanya tautan navigasi sidebar yang ditangani Unpoly.
    const baseFollow = Array.isArray(up.link.config.followSelectors) ? up.link.config.followSelectors : [];
    up.link.config.followSelectors = baseFollow.concat([
        '.sidebar .nav-btn[href]',
        '.sidebar .submenu-item-link',
    ]);

    // Tautan anchor murni (href="#...", mis. tombol buka modal "Switch View As")
    // tidak di-follow oleh Unpoly.
    const baseNoFollow = Array.isArray(up.link.config.noFollowSelectors) ? up.link.config.noFollowSelectors : [];
    up.link.config.noFollowSelectors = baseNoFollow.concat([
        '[href^="#"]',
    ]);

    // 2) Setiap klik link sidebar hanya men-swap <main id="page-content">.
    //    Sidebar & header dibiarkan utuh → state (scroll, dropdown terbuka)
    //    terjaga otomatis. History URL & judul halaman tetap diperbarui.
    up.on('up:link:follow', '.sidebar a', (event) => {
        event.renderOptions.target = PAGE_TARGET;
        event.renderOptions.history = true;
    });

    // 3) Setelah URL berubah (klik link sidebar / back-forward): sinkronkan
    //    highlight menu dari pathname baru. URL sudah final di titik ini,
    //    karena Unpoly mengemisi `up:location:changed` SESUDAH history API
    //    diperbarui (pushState/replaceState di-patch oleh Unpoly).
    up.on('up:location:changed', () => {
        syncSidebarActiveState();
    });

    // 4) Setelah fragmen baru (`#page-content`) masuk ke DOM: inisialisasi ulang
    //    konten Alpine-nya. Event `up:fragment:inserted` pasti dikirim setelah
    //    elemen benar-benar terpasang (bukan `up:render` — event itu TIDAK
    //    ADA di Unpoly 3.x, sehingga versi sebelumnya tidak pernah jalan).
    up.on('up:fragment:inserted', () => {
        initNewAlpineContent();
    });

    // Sinkronkan sekali di awal (hasilnya identik dengan render server).
    syncSidebarActiveState();
}