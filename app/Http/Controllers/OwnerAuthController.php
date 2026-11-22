<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OwnerAuthController extends Controller
{
    public function create()
    {
        return Inertia::render('Login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:72']);
        $credentials['email'] = strtolower($credentials['email']);
        $key = 'owner-login:'.hash('sha256', $credentials['email'].'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in a minute.']);
        }

        if (strlen($credentials['password']) > 72 || ! Auth::attempt([...$credentials, 'owner_slot' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        AuditLog::record('Signed in', 'Owner account');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        AuditLog::record('Signed out', 'Owner account');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();

        return to_route('admin.login');
    }
}
