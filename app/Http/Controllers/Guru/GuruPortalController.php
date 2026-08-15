<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GuruPortalController extends Controller
{
    /**
     * Halaman Dashboard Guru
     */
    public function dashboard()
    {
        return view('guru.dashboard');
    }

}
