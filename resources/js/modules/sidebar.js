export function initSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const appSidebar = document.getElementById('appSidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    if (!appSidebar || !sidebarBackdrop) return;

    function isMobile() {
        return window.matchMedia('(max-width: 767.98px)').matches;
    }

    function openSidebar() {
        appSidebar.classList.add('show');
        sidebarBackdrop.hidden = false;
        requestAnimationFrame(function () {
            sidebarBackdrop.classList.add('show');
        });
        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', 'true');
        }
    }

    function closeSidebar() {
        appSidebar.classList.remove('show');
        sidebarBackdrop.classList.remove('show');
        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', 'false');
        }
        setTimeout(function () {
            if (!sidebarBackdrop.classList.contains('show')) {
                sidebarBackdrop.hidden = true;
            }
        }, 300);
    }

    function toggleSidebar() {
        if (isMobile()) {
            if (appSidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        } else {
            appSidebar.classList.toggle('show');
        }
    }

    if (sidebarToggle) {
        // iOS/Safari: event 'click' kadang tidak di-trigger dengan andal pada layar
        // sentuh (terutama tombol kecil tanpa cursor:pointer / touch-action).
        // Solusi: tambahkan 'touchstart' (passive) di samping 'click', dengan guard
        // `touchHandled` agar rangkaian touchend -> click tidak men-toggle dua kali
        // (yang membuat tombol tampak "tidak merespons").
        let touchHandled = false;

        sidebarToggle.addEventListener('click', function () {
            if (touchHandled) {
                touchHandled = false;
                return;
            }
            toggleSidebar();
        });

        sidebarToggle.addEventListener('touchstart', function () {
            touchHandled = true;
            toggleSidebar();
        }, { passive: true });
    }

    sidebarBackdrop.addEventListener('click', function () {
        closeSidebar();
    });
    // Backdrop juga harus menutup saat disentuh langsung di iOS (touch tidak selalu
    // menghasilkan 'click' bila elemen bergerak/animasi di belakangnya).
    sidebarBackdrop.addEventListener('touchstart', function () {
        if (!sidebarBackdrop.classList.contains('show')) return;
        closeSidebar();
    }, { passive: true });

    appSidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (isMobile()) {
                closeSidebar();
            }
        });
    });

    window.matchMedia('(max-width: 767.98px)').addEventListener('change', function (e) {
        if (!e.matches && appSidebar.classList.contains('show')) {
            closeSidebar();
        }
    });
}
