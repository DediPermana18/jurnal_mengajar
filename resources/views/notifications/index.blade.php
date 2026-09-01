@extends('layouts.app')

@section('title', 'Notifikasi - WebJournal')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                <i class="bi bi-bell me-2 text-primary"></i> Notifikasi
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Daftar seluruh notifikasi Anda. Klik notifikasi untuk membuka halamannya.
            </p>
        </div>
        <form action="{{ route('notifications.read-all') }}" method="POST">
            @csrf
            <button class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-check2-all me-1"></i> Tandai Semua Dibaca
            </button>
        </form>
    </div>

    @if($notifications->isEmpty())
        <div class="table-card-custom text-center py-5">
            <i class="bi bi-bell-slash fs-1 text-muted d-block mb-3"></i>
            <h5 class="fw-bold text-dark mb-1">Belum Ada Notifikasi</h5>
            <p class="text-muted mb-0 small">Tidak ada notifikasi untuk Anda saat ini.</p>
        </div>
    @else
        <div class="table-card-custom mb-4">
            <ul class="list-group list-group-flush">
                @foreach($notifications as $notif)
                    @php
                        $data   = $notif->data;
                        $isRead = $notif->read_at !== null;
                        $url    = data_get($data, 'url', '#');
                    @endphp
                    <li class="list-group-item px-3 py-3 {{ $isRead ? '' : 'bg-primary-subtle' }}" style="border:0;border-bottom:1px solid #eef1f6;">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <a href="{{ $url }}" class="text-decoration-none text-reset d-block flex-grow-1">
                                <div class="d-flex align-items-center gap-2">
                                    @if(!$isRead)
                                        <span class="badge bg-primary rounded-pill">Baru</span>
                                    @endif
                                    <strong>{{ data_get($data, 'title', 'Notifikasi') }}</strong>
                                    <small class="text-muted ms-auto">{{ $notif->created_at?->diffForHumans() }}</small>
                                </div>
                                <div class="text-muted small mt-1">{{ data_get($data, 'message', '') }}</div>
                            </a>
                            @if(!$isRead)
                                <form action="{{ route('notifications.read', $notif->id) }}" method="POST" class="ms-2">
                                    @csrf
                                    <button class="btn btn-sm btn-light border rounded-3" title="Tandai sudah dibaca">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
