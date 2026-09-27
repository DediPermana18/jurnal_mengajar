/**
 * Smart Idle-Aware Single Device Session — idleTracker.js
 *
 * Modul global yang berjalan di setiap halaman setelah user terautentikasi:
 *  - Memantau aktivitas user (mousemove, keydown, click, scroll, touchstart).
 *  - Threshold AFK 3 menit → kirim heartbeat { is_idle: true }.
 *  - User kembali berinteraksi → reset timer → heartbeat { is_idle: false }.
 *  - Heartbeat periodik (60 detik) menjaga last_active_at tetap segar dan
 *    mendeteksi sesi "kicked" (di-revoke perangkat lain).
 *  - Polling permintaan login interaktif (push prompt): saat Device B mencoba
 *    login ke akun yang sedang AKTIF di device ini, muncul MODAL keamanan
 *    dengan pilihan [Izinkan] / [Tolak] — tanpa websocket sekalipun.
 *  - Jika konfigurasi Echo/Pusher tersedia, dengarkan kanal privat
 *    user.{id}.security untuk event LoginApprovalRequested / LoginApprovalDecision
 *    agar modal muncul real-time (polling tetap dipakai sebagai fallback).
 *  - Aksi [Tolak & Amankan Akun] menonaktifkan AFK auto-logout untuk sesi
 *    berjalan: timer idle dihentikan total & heartbeat selalu is_idle=false,
 *    sehingga sesi tak bisa di-take-over karena AFK — sampai Logout/Login baru.
 */

const IDLE_THRESHOLD_MS = 3 * 60 * 1000; // 3 menit AFK
const KEEPALIVE_INTERVAL_MS = 60 * 1000; // 60 detik
const APPROVAL_POLL_MS = 4000; // 4 detik — polling request approval Device B
const ALERT_STORAGE_KEY = 'user.security.alert_at';

/**
 * AFK auto-logout untuk SESI BERJALAN.
 *
 * Dinonaktifkan sejak aksi [Tolak & Amankan Akun] dipilih (atau password
 * selesai diganti): timer idle dihentikan total, heartbeat SELALU mengirim
 * is_idle=false, dan sesi tidak akan di-take-over oleh perangkat lain karena
 * AFK — sampai user Logout & Login kembali (flag di-reset saat login baru).
 */
let afkProtectionDisabled = false;
let activeAfkTracker = null;

/** Nonaktifkan AFK protection + hentikan timer idle tracker yang sedang jalan. */
function disableAfkProtection() {
    afkProtectionDisabled = true;
    if (activeAfkTracker) {
        activeAfkTracker.disable();
    }
}

export function initIdleTracker() {
    const cfg = window.__userSession;
    if (!cfg || !cfg.userId || !cfg.deviceLock || !cfg.heartbeatUrl) {
        return; // user belum login / halaman publik
    }

    let idle = false;
    let idleTimer = null;
    let keepAliveTimer = null;
    let lastAlertSeen = localStorage.getItem(ALERT_STORAGE_KEY) || '';
    let pingInFlight = false;

    // AFK protection nonaktif sejak aksi "Tolak & Amankan Akun" (per sesi) —
    // nilai awal dibaca dari konfigurasi sesi server (blade __userSession).
    afkProtectionDisabled = Boolean(cfg.disableAfkProtection);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function ping(isIdle, onDone) {
        if (pingInFlight) {
            return;
        }
        if (afkProtectionDisabled) {
            isIdle = false; // AFK off — jangan pernah laporkan idle ke backend.
        }
        pingInFlight = true;

        fetch(cfg.heartbeatUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                is_idle: Boolean(isIdle),
                session_id: cfg.deviceLock,
            }),
        })
            .then((res) => (res.ok ? res.json() : Promise.reject(res)))
            .then((data) => {
                // Sinkron antar-tab: bila server sudah menonaktifkan AFK
                // protection untuk sesi ini, hentikan juga timer di tab lain
                // yang mungkin masih tersisa dari sebelum aksi amankan akun.
                if (data && data.afk_protection_disabled && ! afkProtectionDisabled) {
                    disableAfkProtection();
                }

                if (data && data.kicked) {
                    // Sesi ini sudah di-revoke oleh perangkat lain saat AFK.
                    showSecurityToast(data.message || 'Sesi Anda telah dihentikan otomatis.');
                    setTimeout(() => {
                        window.location.href = '/login';
                    }, 2500);
                    return;
                }

                // Polling notifikasi keamanan (fallback real-time tanpa websocket).
                const alertAt = data && data.security_alert_at;
                if (alertAt && alertAt !== lastAlertSeen) {
                    lastAlertSeen = alertAt;
                    localStorage.setItem(ALERT_STORAGE_KEY, alertAt);
                    showSecurityToast(
                        `⚠️ Peringatan Keamanan: Terdeteksi percobaan login ke akun Anda dari perangkat/IP lain pada ${formatWib(alertAt)}.`
                    );
                }
            })
            .catch(() => {
                // Offline / transient error — abaikan; heartbeat berikutnya menggantikan.
            })
            .finally(() => {
                pingInFlight = false;
                if (typeof onDone === 'function') {
                    onDone();
                }
            });
    }

    function pollApprovalRequests() {
        if (!cfg.approvalPendingUrl) {
            return;
        }

        fetch(cfg.approvalPendingUrl, {
            headers: { Accept: 'application/json' },
        })
            .then((res) => (res.ok ? res.json() : Promise.reject(res)))
            .then((data) => {
                const items = (data && Array.isArray(data.requests)) ? data.requests : [];
                const pending = items.find((item) => item && item.status === 'pending');

                if (pending && pending.request_id) {
                    // Ada permintaan login yang menunggu keputusan → tampilkan modal.
                    showApprovalModal(pending, cfg, csrfToken);
                } else if (getActiveApprovalRequest()) {
                    // Permintaan sudah diputuskan/dihapus dari sisi lain → tutup modal.
                    closeApprovalModal();
                }
            })
            .catch(() => {
                // Offline / ter-kick (middleware redirect ke /login) → abaikan.
            });
    }

    function markActive() {
        if (afkProtectionDisabled) {
            // AFK protection off — timer idle tidak dipasang ulang; keepalive
            // tetap berjalan mengirim is_idle=false.
            return;
        }
        if (idle) {
            idle = false;
            ping(false);
        }
        resetTimers();
    }

    function resetTimers() {
        window.clearTimeout(idleTimer);
        window.clearInterval(keepAliveTimer);

        if (!afkProtectionDisabled) {
            // AFK threshold: tanpa aktivitas selama N menit → status idle.
            idleTimer = window.setTimeout(() => {
                idle = true;
                ping(true);
            }, IDLE_THRESHOLD_MS);
        }

        // Heartbeat periodik: jaga last_active_at segar + deteksi kicked/alert.
        keepAliveTimer = window.setInterval(() => {
            ping(idle);
        }, KEEPALIVE_INTERVAL_MS);
    }

    // 1. Pasang event listener aktivitas global.
    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach((eventName) => {
        window.addEventListener(eventName, markActive, { passive: true });
    });

    // 2. Mulai timer idle + heartbeat periodik.
    resetTimers();

    // 3. Polling permintaan login interaktif (modal [Izinkan] / [Tolak]).
    window.setInterval(pollApprovalRequests, APPROVAL_POLL_MS);
    pollApprovalRequests();

    // 4. Real-time channel (bila Echo/Pusher/Reverb tersedia & terauth).
    if (window.Echo && typeof window.Echo.private === 'function') {
        try {
            window.Echo.private(`user.${cfg.userId}.security`)
                .listen('ConcurrentLoginAttempt', (e) => {
                    const waktu = e?.attempted_at || e?.attemptedAt || '';
                    showSecurityToast(
                        `⚠️ Peringatan Keamanan: Terdeteksi percobaan login ke akun Anda dari perangkat/IP lain${waktu ? ' pada '+formatWib(waktu) : ''}.`
                    );
                })
                .listen('LoginApprovalRequested', (e) => {
                    // Modal tampil real-time tanpa menunggu polling berikutnya.
                    const payload = {
                        request_id: e?.request_id || '',
                        device_info: e?.device_info || {},
                        status: 'pending',
                    };
                    if (payload.request_id) {
                        showApprovalModal(payload, cfg, csrfToken);
                    }
                })
                .listen('LoginApprovalDecision', (e) => {
                    // Keputusan sudah dibuat di perangkat ini / perangkat lain.
                    if (e?.request_id && e?.request_id === getActiveApprovalRequest()?.request_id) {
                        closeApprovalModal();
                    }
                });
        } catch (err) {
            // Kanal gagal disubscribe — polling modal tetap menangani.
        }
    }

    // Kontrol untuk modul luar (modal approval) agar bisa menghentikan timer
    // AFK saat aksi [Tolak & Amankan Akun] dipilih di tengah sesi.
    activeAfkTracker = {
        disable() {
            const wasIdle = idle;
            idle = false;
            resetTimers();
            if (wasIdle) {
                ping(false); // koreksi status idle backend bila sebelumnya AFK
            }
        },
    };
}

/**
 * State modal persetujuan login (push prompt) — hanya satu modal aktif.
 */
let activeApprovalRequest = null;

/** Pesan yang dilihat Device B saat pemilik memilih "Tolak & Amankan Akun". */
const OWNER_DENY_MESSAGE = 'Akses ditolak oleh pemilik akun';

function getActiveApprovalRequest() {
    return activeApprovalRequest;
}

function closeApprovalModal() {
    activeApprovalRequest = null;
    document.querySelectorAll('.approval-modal-root').forEach((el) => el.remove());
}

/**
 * Tampilkan modal interaktif keamanan di Device A:
 * "Ada perangkat lain (IP: {ip}, Device: {device}) mencoba login ke akun Anda.
 *  Apakah ini Anda?" → [Tolak] / [Izinkan Login].
 */
function showApprovalModal(request, cfg, csrfToken) {
    // Jangan tumpuk — modal lama ditutup dulu (polling bisa ganda sekaligus).
    closeApprovalModal();
    activeApprovalRequest = request;

    const ip = String(request.device_info?.ip ?? request.device_info?.ip_address ?? '-');
    const userAgent = String(request.device_info?.user_agent ?? '-');
    // Ringkas User-Agent agar tidak meluber di modal.
    const deviceLabel = userAgent.length > 90 ? `${userAgent.slice(0, 90)}…` : userAgent;

    const root = document.createElement('div');
    root.className = 'approval-modal-root';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.style.cssText = [
        'position: fixed',
        'inset: 0',
        'z-index: 100000',
        'display: flex',
        'align-items: center',
        'justify-content: center',
        'padding: 20px',
        'background: rgba(15, 23, 42, 0.6)',
        'backdrop-filter: blur(3px)',
    ].join(';');

    const card = document.createElement('div');
    card.style.cssText = [
        'width: min(460px, 100%)',
        'background: #ffffff',
        'border-radius: 18px',
        'box-shadow: 0 24px 60px rgba(15, 23, 42, 0.35)',
        'padding: 24px',
        'font: 600 14px/1.55 "Plus Jakarta Sans", system-ui, sans-serif',
        'color: #0f172a',
    ].join(';');

    const iconRow = document.createElement('div');
    iconRow.style.cssText = 'display:flex; gap:12px; align-items:flex-start; margin-bottom:14px;';
    const icon = document.createElement('div');
    icon.textContent = '⚠️';
    icon.style.cssText = 'font-size:30px; line-height:1;';
    const title = document.createElement('div');
    title.style.cssText = 'font-size:17px; font-weight:800; line-height:1.3;';
    title.textContent = 'Peringatan Keamanan — Login dari Perangkat Lain';
    iconRow.appendChild(icon);
    iconRow.appendChild(title);
    card.appendChild(iconRow);

    const body = document.createElement('p');
    body.style.cssText = 'margin:0 0 6px; color:#334155;';
    body.textContent = `Ada perangkat lain (IP: ${ip}, Device: ${deviceLabel}) mencoba login ke akun Anda. Apakah ini Anda?`;
    card.appendChild(body);

    const hint = document.createElement('p');
    hint.style.cssText = 'margin:0 0 18px; font-size:12.5px; font-weight:500; color:#64748b;';
    hint.textContent = 'Jika Anda tidak mengenali perangkat tersebut, sebaiknya pilih [Tolak]. Permintaan kedaluwarsa otomatis dalam 60 detik.';
    card.appendChild(hint);

    const actions = document.createElement('div');
    actions.style.cssText = 'display:flex; flex-direction:column; gap:10px;';

    const primaryRow = document.createElement('div');
    primaryRow.style.cssText = 'display:flex; gap:10px;';

    const rejectBtn = document.createElement('button');
    rejectBtn.type = 'button';
    rejectBtn.textContent = 'Tolak';
    rejectBtn.style.cssText = [
        'flex:1',
        'padding:11px 18px',
        'border-radius:12px',
        'border:1px solid #e2e8f0',
        'background:#f8fafc',
        'color:#475569',
        'font:700 14px "Plus Jakarta Sans", system-ui, sans-serif',
        'cursor:pointer',
    ].join(';');

    const approveBtn = document.createElement('button');
    approveBtn.type = 'button';
    approveBtn.textContent = 'Izinkan Login';
    approveBtn.style.cssText = [
        'flex:1',
        'padding:11px 18px',
        'border-radius:12px',
        'border:0',
        'background:#1d4ed8',
        'color:#ffffff',
        'font:700 14px "Plus Jakarta Sans", system-ui, sans-serif',
        'cursor:pointer',
    ].join(';');

    // [Tolak & Amankan Akun] — danger (outline merah), baris terpisah full-width.
    const secureBtn = document.createElement('button');
    secureBtn.type = 'button';
    secureBtn.textContent = 'Tolak & Amankan Akun / Ganti Password';
    secureBtn.style.cssText = [
        'padding:11px 18px',
        'border-radius:12px',
        'border:1.5px solid #dc2626',
        'background:#ffffff',
        'color:#dc2626',
        'font:700 14px "Plus Jakarta Sans", system-ui, sans-serif',
        'cursor:pointer',
    ].join(';');

    function setBusy(button, busy) {
        button.disabled = busy;
        button.style.opacity = busy ? '0.6' : '1';
        if (busy) {
            button.insertAdjacentText('beforeend', ' …');
        }
    }

    // Tolak login Device B (opsional dengan pesan khusus untuk aksi amankan).
    function rejectDeviceLogin(message) {
        setBusy(rejectBtn, true);
        setBusy(secureBtn, true);
        fetch(cfg.approvalRejectUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            // amankan_akun=true menandakan aksi [Tolak & Amankan Akun] →
            // server menonaktifkan AFK auto-logout untuk sesi berjalan ini.
            body: JSON.stringify({
                request_id: request.request_id,
                message: message || undefined,
                amankan_akun: message ? true : undefined,
            }),
        })
            .then((res) => (res.ok ? res.json() : Promise.reject(res)))
            .then(() => {
                closeApprovalModal();
                if (message) {
                    // Aksi [Tolak & Amankan Akun] → hentikan timer AFK di browser
                    // ini juga (jangan auto-logout saat mengisi form), lalu buka
                    // modal Ganti Password Quick-Reset tanpa navigasi menu profil.
                    disableAfkProtection();
                    showSecurePasswordModal(cfg, csrfToken);
                } else {
                    showSecurityToast('Permintaan login ditolak.');
                }
            })
            .catch(() => {
                setBusy(rejectBtn, false);
                setBusy(secureBtn, false);
                showSecurityToast('Gagal mengirim keputusan. Coba lagi.');
            });
    }

    // ===== [Tolak] → POST /api/login-approval/reject =====
    rejectBtn.addEventListener('click', () => rejectDeviceLogin(''));

    // ===== [Tolak & Amankan Akun] → reject + modal ganti password =====
    secureBtn.addEventListener('click', () => rejectDeviceLogin(OWNER_DENY_MESSAGE));

    // ===== [Izinkan Login] → POST /api/login-approval/approve =====
    approveBtn.addEventListener('click', () => {
        setBusy(approveBtn, true);
        fetch(cfg.approvalApproveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ request_id: request.request_id }),
        })
            .then((res) => (res.ok ? res.json() : Promise.reject(res)))
            .then(() => {
                closeApprovalModal();
                showSecurityToast('Anda telah menyetujui login dari perangkat lain. Sesi Anda akan diakhiri.');
                setTimeout(() => {
                    window.location.href = '/login';
                }, 1800);
            })
            .catch(() => {
                setBusy(approveBtn, false);
                showSecurityToast('Gagal mengirim keputusan. Coba lagi.');
            });
    });

    primaryRow.appendChild(rejectBtn);
    primaryRow.appendChild(approveBtn);
    actions.appendChild(primaryRow);
    actions.appendChild(secureBtn);
    card.appendChild(actions);
    root.appendChild(card);

    // Klik backdrop = abaikan (bukan keputusan; timeout 60s tetap berjalan).
    root.addEventListener('click', (e) => {
        if (e.target === root) {
            root.remove();
        }
    });

    document.body.appendChild(root);
}

/**
 * Modal "Ganti Password Quick-Reset" — dibuka langsung setelah aksi
 * [Tolak & Amankan Akun], tanpa navigasi ke menu Pengaturan Profil.
 * Meminta: password lama, password baru, konfirmasi password baru.
 * Sukses → toast "Password berhasil diperbarui. Akun Anda kini aman."
 */
function showSecurePasswordModal(cfg, csrfToken) {
    closeApprovalModal(); // pastikan modal approval sudah tertutup

    const root = document.createElement('div');
    root.className = 'approval-modal-root';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.style.cssText = [
        'position: fixed',
        'inset: 0',
        'z-index: 100000',
        'display: flex',
        'align-items: center',
        'justify-content: center',
        'padding: 20px',
        'background: rgba(15, 23, 42, 0.6)',
        'backdrop-filter: blur(3px)',
    ].join(';');

    const card = document.createElement('div');
    card.style.cssText = [
        'width: min(460px, 100%)',
        'background: #ffffff',
        'border-radius: 18px',
        'box-shadow: 0 24px 60px rgba(15, 23, 42, 0.35)',
        'padding: 24px',
        'font: 600 14px/1.55 "Plus Jakarta Sans", system-ui, sans-serif',
        'color: #0f172a',
    ].join(';');

    const iconRow = document.createElement('div');
    iconRow.style.cssText = 'display:flex; gap:12px; align-items:flex-start; margin-bottom:12px;';
    const icon = document.createElement('div');
    icon.textContent = '🔒';
    icon.style.cssText = 'font-size:28px; line-height:1;';
    const title = document.createElement('div');
    title.style.cssText = 'font-size:17px; font-weight:800; line-height:1.3;';
    title.textContent = 'Amankan Akun — Ganti Password';
    iconRow.appendChild(icon);
    iconRow.appendChild(title);
    card.appendChild(iconRow);

    const body = document.createElement('p');
    body.style.cssText = 'margin:0 0 16px; color:#334155; font-size:13px;';
    body.textContent = 'Anda mendeteksi kemungkinan penyusupan. Ganti password sekarang — seluruh sesi lain akan dihentikan dan permintaan login yang tertunda dibatalkan.';
    card.appendChild(body);

    // Kotak error inline.
    const errorBox = document.createElement('div');
    errorBox.hidden = true;
    errorBox.setAttribute('role', 'alert');
    errorBox.style.cssText = [
        'margin-bottom:14px',
        'padding:10px 14px',
        'border-radius:10px',
        'background:#fef2f2',
        'color:#b91c1c',
        'border:1px solid #fecaca',
        'font:600 12.5px/1.45 "Plus Jakarta Sans", system-ui, sans-serif',
        'white-space: pre-line',
    ].join(';');
    card.appendChild(errorBox);

    function field(label, name, type) {
        const wrap = document.createElement('div');
        wrap.style.cssText = 'margin-bottom:12px;';

        const lab = document.createElement('label');
        lab.textContent = label;
        lab.style.cssText = 'display:block; font-weight:800; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:#475569; margin-bottom:6px;';

        const input = document.createElement('input');
        input.type = type || 'password';
        input.name = name;
        input.autocomplete = name === 'current_password' ? 'current-password' : 'new-password';
        input.style.cssText = [
            'width:100%',
            'padding:11px 14px',
            'border:1px solid #cbd5e1',
            'border-radius:12px',
            'background:#ffffff',
            'color:#0f172a',
            'font:600 14px "Plus Jakarta Sans", system-ui, sans-serif',
            'outline:none',
        ].join(';');
        input.addEventListener('input', () => { errorBox.hidden = true; });

        wrap.appendChild(lab);
        wrap.appendChild(input);

        return { wrap, input };
    }

    const currentField = field('Password Lama', 'current_password');
    const newField = field('Password Baru', 'password');
    const confirmField = field('Konfirmasi Password Baru', 'password_confirmation');

    card.appendChild(currentField.wrap);
    card.appendChild(newField.wrap);
    card.appendChild(confirmField.wrap);

    const actions = document.createElement('div');
    actions.style.cssText = 'display:flex; gap:10px; margin-top:6px;';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.textContent = 'Batal';
    cancelBtn.style.cssText = [
        'flex:1',
        'padding:11px 18px',
        'border-radius:12px',
        'border:1px solid #e2e8f0',
        'background:#f8fafc',
        'color:#475569',
        'font:700 14px "Plus Jakarta Sans", system-ui, sans-serif',
        'cursor:pointer',
    ].join(';');

    const saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.textContent = 'Ganti Password & Amankan';
    saveBtn.style.cssText = [
        'flex:1.4',
        'padding:11px 18px',
        'border-radius:12px',
        'border:0',
        'background:#dc2626',
        'color:#ffffff',
        'font:700 14px "Plus Jakarta Sans", system-ui, sans-serif',
        'cursor:pointer',
    ].join(';');

    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = false;
    }

    function setBusy(busy) {
        saveBtn.disabled = busy;
        saveBtn.style.opacity = busy ? '0.6' : '1';
        cancelBtn.disabled = busy;
    }

    // ===== Kirim quick-reset password =====
    saveBtn.addEventListener('click', () => {
        const body = {
            current_password: currentField.input.value,
            password: newField.input.value,
            password_confirmation: confirmField.input.value,
        };

        if (!body.current_password || !body.password || !body.password_confirmation) {
            showError('Semua kolom wajib diisi.');
            return;
        }
        if (body.password !== body.password_confirmation) {
            showError('Konfirmasi password baru tidak cocok.');
            return;
        }

        setBusy(true);
        fetch(cfg.securePasswordUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        })
            .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if (ok && data && data.success) {
                    showSecurityToast(data.message || 'Password berhasil diperbarui. Akun Anda kini aman.');
                    root.remove();
                    return;
                }

                // 422 validation / Hash::check gagal → tampilkan pesan backend.
                const firstError = data && data.errors
                    ? Object.values(data.errors).flat()[0]
                    : (data && data.message) || 'Gagal memperbarui password. Coba lagi.';
                showError(firstError);
                setBusy(false);
            })
            .catch(() => {
                showError('Tidak dapat menghubungi server. Coba lagi.');
                setBusy(false);
            });
    });

    cancelBtn.addEventListener('click', () => root.remove());
    root.addEventListener('click', (e) => {
        if (e.target === root) {
            root.remove();
        }
    });

    actions.appendChild(cancelBtn);
    actions.appendChild(saveBtn);
    card.appendChild(actions);
    root.appendChild(card);
    document.body.appendChild(root);

    currentField.input.focus();
}

/**
 * Toast peringatan keamanan global (tanpa dependensi UI library).
 */
function showSecurityToast(message) {
    // Hindari toast bertumpuk: buang toast keamanan lama.
    document.querySelectorAll('.security-toast').forEach((el) => el.remove());

    const toast = document.createElement('div');
    toast.className = 'security-toast';
    toast.setAttribute('role', 'alert');
    toast.style.cssText = [
        'position: fixed',
        'top: 20px',
        'right: 20px',
        'z-index: 99999',
        'max-width: 380px',
        'padding: 14px 38px 14px 16px',
        'border-radius: 14px',
        'background: #7f1d1d',
        'color: #fef2f2',
        'border: 1px solid #fca5a5',
        'box-shadow: 0 10px 30px rgba(127,29,29,0.35)',
        'font: 600 13px/1.45 "Plus Jakarta Sans", system-ui, sans-serif',
        'cursor: pointer',
    ].join(';');

    const text = document.createElement('span');
    text.textContent = message;

    const close = document.createElement('button');
    close.setAttribute('aria-label', 'Tutup');
    close.innerHTML = '&times;';
    close.style.cssText = [
        'position: absolute',
        'top: 8px',
        'right: 12px',
        'border: 0',
        'background: transparent',
        'color: #fecaca',
        'font-size: 18px',
        'line-height: 1',
        'cursor: pointer',
    ].join(';');
    close.addEventListener('click', () => toast.remove());

    toast.appendChild(text);
    toast.appendChild(close);

    // Klik pada body toast juga menutup.
    toast.addEventListener('click', () => toast.remove());
    document.body.appendChild(toast);

    // Auto-dismiss setelah 10 detik.
    window.setTimeout(() => toast.remove(), 10 * 1000);
}

function formatWib(isoString) {
    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) {
        return isoString;
    }
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(date);
}