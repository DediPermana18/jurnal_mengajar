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

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (isMobile()) {
                if (appSidebar.classList.contains('show')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            } else {
                appSidebar.classList.toggle('show');
            }
        });
    }

    sidebarBackdrop.addEventListener('click', function () {
        closeSidebar();
    });

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
