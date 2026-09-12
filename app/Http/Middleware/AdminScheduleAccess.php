<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminScheduleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Petugas IT / QA Tester (dan aktif impersonasi admin_tu via active_role)
        // diizinkan mengakses group route admin sebagai peninjau/penguji.
        abort_unless(
            $user && ($user->isPetugasIt()
                || (($user->role === 'admin' && in_array($user->sub_role, [null, 'petugas_tu', 'admin_tu'], true)) || $user->role === 'admin_tu')),
            403
        );

        return $next($request);
    }
}
