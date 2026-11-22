<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $admin = $request->is('admin', 'admin/*');
        Inertia::encryptHistory($admin);
        if (! $admin) {
            return [];
        }

        return [
            ...parent::share($request),
            'notice' => fn () => $request->session()->get('notice'),
        ];
    }
}
