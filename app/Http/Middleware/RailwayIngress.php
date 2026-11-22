<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RailwayIngress
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->headers->get('X-Real-IP');
        $request->headers->remove('X-Forwarded-For');

        if ($request->headers->has('X-Railway-Edge') && filter_var($client, FILTER_VALIDATE_IP)) {
            $request->headers->set('X-Forwarded-For', $client);
        }

        return $next($request);
    }
}
