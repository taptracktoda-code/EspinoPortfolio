<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnerOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((int) $request->user()?->owner_slot === 1, 403);

        return $next($request);
    }
}
