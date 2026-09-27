<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Kanal notifikasi keamanan single-device: hanya pemilik akun yang boleh
// mendengarkan percobaan login dari perangkat lain.
Broadcast::channel('user.{id}.security', function ($user, $id) {
    return (int) $user->id === (int) $id;
});