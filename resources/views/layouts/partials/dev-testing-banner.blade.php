{{--
   DEV & TESTING BANNER (Petugas IT / QA & Emergency Super Admin Takeover).
   Posisi: DI DALAM <main id="page-content">, tepat di bawah Topbar dan di atas
   judul halaman — rumah tunggal widget impersonasi/testing (konteks aktif +
   dropdown switch role + tombol exit). Palette soft amber (warning/dev mode).
   Dipakai via @include('layouts.partials.dev-testing-banner').
--}}
            @if(auth()->user() && (auth()->user()->isPetugasIt() || auth()->user()->is_emergency_takeover))
                @php
                    $itPreviewRole   = $previewRole ?? (auth()->user()->hasActiveRole() ? auth()->user()->activeRole() : null);
                    $itPreviewLabel  = $itPreviewRole ? (\App\Models\User::PREVIEW_ROLES[$itPreviewRole] ?? ucfirst($itPreviewRole)) : null;
                    $itMaintenanceOn = \App\Models\PengaturanJadwal::isMaintenanceModeActive();
                    $itIsEmergency   = (bool) auth()->user()->is_emergency_takeover;
                    $itTargetId      = session('impersonate_target_id');
                    $itTargetGuru    = $itTargetId ? \App\Models\User::find($itTargetId) : null;
                    $itIsWaliKelas   = $itPreviewRole === 'wali_kelas';
                    $itHasGuruTarget = ! empty($itPreviewRole) && in_array($itPreviewRole, ['guru_mapel', 'guru_piket', 'wali_kelas'], true);
                    $itTargetList    = $itHasGuruTarget
                        ? \App\Models\User::where('role', \App\Models\User::ROLE_GURU)
                            ->where('is_active', true)
                            ->when($itIsWaliKelas, fn ($q) => $q->where(function ($q2) {
                                $q2->where('sub_role', 'wali_kelas')
                                    ->orWhereHas('kelasWali');
                            }))
                            ->orderBy('nama')
                            ->get()
                        : collect();
                @endphp
                <div class="dev-testing-banner">
                    <!-- Konteks aktif (kiri): status view mode ditampilkan DI SINI (tidak diduplikasi tombol kanan) -->
                    <div class="dev-context">
                        <span class="dev-badge">
                            <i class="bi bi-tools"></i>
                            {{ $itIsEmergency ? 'Emergency Takeover' : 'Mode Dev & Testing' }}
                        </span>
                        <div class="dev-context-text"
                             title="{{ $itIsEmergency ? 'Akun Super Admin darurat aktif' : ($itPreviewRole ? 'View Mode: ' . $itPreviewLabel . ($itTargetGuru ? ' — Menguji sebagai: ' . $itTargetGuru->nama . ($itTargetGuru->username ? ' (' . $itTargetGuru->username . ')' : '') : '') : 'Mode langsung Petugas IT / QA') }}">
                            @if($itIsEmergency)
                                <span class="dev-context-detail">
                                    <i class="bi bi-shield-exclamation me-1"></i>Akun Super Admin darurat aktif
                                </span>
                            @elseif($itPreviewRole)
                                <span class="dev-context-detail">
                                    <i class="bi {{ $itPreviewRole === 'super_admin' ? 'bi-shield-check' : 'bi-person-circle' }} me-1"></i>
                                    View Mode: {{ $itPreviewLabel }}
                                </span>
                                @if($itTargetGuru)
                                    <span class="dev-context-sub">• Menguji sebagai: {{ $itTargetGuru->nama }}</span>
                                    @if($itTargetGuru->username)
                                        <span class="dev-context-sub">({{ $itTargetGuru->username }})</span>
                                    @endif
                                @endif
                            @else
                                <span class="dev-context-detail">
                                    <i class="bi bi-person-check me-1"></i>Mode langsung — Petugas IT / QA
                                </span>
                            @endif
                            @if($itMaintenanceOn)
                                <span class="dev-context-sub" style="color:#b91c1c;">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Maintenance AKTIF
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Aksi (kanan): Switch Role + tombol Exit. Status view TIDAK diduplikasi di sini
                         (sudah tampil di konteks kiri banner). -->
                    <div class="dev-actions">
                        @if($itMaintenanceOn)
                            <form action="{{ route('it.maintenance-mode') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="maintenance_mode" value="0">
                                <button type="submit" class="dev-btn dev-btn-danger" title="Mode Maintenance AKTIF — klik untuk mematikan">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <span>Maintenance ON</span>
                                </button>
                            </form>
                        @endif

                        <!-- Emergency Super Admin Takeover ("Kartu As") — IT/QA ONLY -->
                        <button type="button"
                                class="dev-btn dev-btn-icon dev-btn-danger"
                                data-bs-toggle="modal"
                                data-bs-target="#itEmergencyTakeoverModal"
                                title="Emergency Super Admin Takeover"
                                aria-label="Emergency Super Admin Takeover">
                            <i class="bi bi-shield-exclamation"></i>
                        </button>

                        <!-- SWITCH ROLE — dropdown tunggal (role portal + target pengujian) -->
                        <div class="dropdown">
                            <button type="button"
                                    class="dev-btn dev-btn-main"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                    title="Ganti role portal / view mode & target pengujian">
                                <i class="bi bi-arrows-fullscreen"></i>
                                <span>Switch Role</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2 dev-dropdown-menu" style="max-height: 480px; overflow-y: auto;">
                                <!-- Konteks aktif -->
                                <li class="px-3 py-2 border-bottom">
                                    <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.68rem; letter-spacing: 0.06em;">Konteks Aktif</span>
                                    <div class="mt-1 small text-dark fw-semibold" style="line-height: 1.5;">
                                        @if($itTargetGuru)
                                            <i class="bi bi-flask text-primary me-1"></i>
                                            Menguji sebagai: {{ $itTargetGuru->nama }}
                                            @if($itTargetGuru->username)
                                                <span class="text-muted fw-normal">({{ $itTargetGuru->username }})</span>
                                            @endif
                                        @elseif($itIsEmergency)
                                            Emergency Super Admin Takeover aktif
                                        @elseif($itPreviewRole)
                                            View: {{ $itPreviewLabel }}
                                        @else
                                            Petugas IT / QA langsung
                                        @endif
                                    </div>
                                    @if($itPreviewRole || $itIsEmergency)
                                        <div class="mt-2">
                                            @if($itIsEmergency)
                                                <form action="{{ route('it-emergency.demote-self') }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100 rounded-3 fw-semibold"
                                                            onclick="return confirm('Kembali ke Mode IT / QA?\nStatus Super Admin darurat akan dilepaskan, akun dikembalikan ke petugas_it / qa_tester, lalu diarahkan ke Dashboard IT.')">
                                                        <i class="bi bi-arrow-return-left me-1"></i> Kembali ke Mode IT / QA
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('it.reset-view') }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-warning w-100 rounded-3 fw-semibold"
                                                            title="Kembali ke Mode IT — lepaskan preview role aktif">
                                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Kembali ke Mode IT
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </li>

                                <!-- Switch role portal -->
                                <li class="px-3 py-2 mt-1">
                                    <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.68rem; letter-spacing: 0.06em;">Pilih Role Portal</span>
                                </li>
                                @foreach(\App\Models\User::PREVIEW_ROLES as $previewKey => $previewName)
                                    <li>
                                        <form action="{{ route('it.switch-view') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="role" value="{{ $previewKey }}">
                                            <button type="submit" class="dropdown-item py-2 {{ $itPreviewRole === $previewKey ? 'active' : '' }}">
                                                <i class="bi {{ $previewKey === 'super_admin' ? 'bi-shield-check' : 'bi-person-circle' }} me-2 text-muted"></i>
                                                {{ $previewName }}
                                            </button>
                                        </form>
                                    </li>
                                @endforeach

                                <!-- Target impersonate (portal Guru / Wali Kelas) -->
                                @if($itHasGuruTarget)
                                    <li><hr class="dropdown-divider"></li>
                                    <li class="px-3 py-2">
                                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.68rem; letter-spacing: 0.06em;">
                                            Pilih {{ $itIsWaliKelas ? 'Wali Kelas' : 'Guru' }} Target
                                        </span>
                                    </li>
                                    <li>
                                        <form action="{{ route('it.impersonate-target') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="impersonate_target_id" value="">
                                            <button type="submit" class="dropdown-item py-2 {{ $itTargetId ? '' : 'active' }}">
                                                <i class="bi bi-person me-2 text-muted"></i>
                                                Akun Saya (Tanpa Target)
                                            </button>
                                        </form>
                                    </li>
                                    @forelse($itTargetList as $guruOpt)
                                        <li>
                                            <form action="{{ route('it.impersonate-target') }}" method="POST" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="impersonate_target_id" value="{{ $guruOpt->id }}">
                                                <button type="submit" class="dropdown-item py-2 {{ (int) $itTargetId === (int) $guruOpt->id ? 'active' : '' }}">
                                                    <i class="bi bi-person-check me-2 text-muted"></i>
                                                    {{ $guruOpt->nama }}
                                                    <span class="small text-muted">({{ $guruOpt->username }})</span>
                                                </button>
                                            </form>
                                        </li>
                                    @empty
                                        <li class="px-3 py-2 text-muted small">Belum ada akun Guru testing.</li>
                                    @endforelse
                                @endif
                            </ul>
                        </div>

                        @if($itPreviewRole || $itIsEmergency)
                            <span class="dev-sep"></span>
                            @if($itIsEmergency)
                                <form action="{{ route('it-emergency.demote-self') }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="dev-btn dev-btn-danger"
                                            title="Kembalikan ke Mode IT / QA dan lepaskan status Super Admin darurat."
                                            onclick="return confirm('Kembali ke Mode IT / QA?\nStatus Super Admin darurat akan dilepaskan, akun dikembalikan ke petugas_it / qa_tester, lalu diarahkan ke Dashboard IT.')">
                                        <i class="bi bi-arrow-return-left"></i>
                                        <span>Exit / Kembali ke IT</span>
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('it.reset-view') }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="dev-btn dev-btn-exit"
                                            title="Kembali ke Mode IT — lepaskan preview role aktif">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                        <span>Exit / Kembali ke IT</span>
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
