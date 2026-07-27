<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
{
    $request->authenticate();

    $request->session()->regenerate();

    $user = Auth::user();





session([
    'name' => $user->name,
    'username' => $user->username,
    'role' => $role,
    'dist_channel' => strtolower(trim($user->dist_channel)),
]);
    
if (!$user || !$user->role) {
    Auth::logout();
    return redirect('/login')->with('error', 'Role tidak ditemukan');
}
$role = strtolower(trim($user->role ?? ''));
    return match($role){

        'planner' => redirect('/planner/dashboard'),

        'monitoring' => redirect('/monitoring/dashboard'),

        'manager' => redirect('/manager/dashboard'),

        'sales' => redirect('/sales/dashboard'),

        'spvplanner' => redirect('/spvplanner/dashboard'),

        'spvmonitoring' => redirect('/spvmonitoring/dashboard'),

        'developer' => redirect('/developer/dashboard'),

        'cmd' => redirect()->route('cmd.dashboard'),

        'mp' => redirect()->route('mp.dashboard'),
        'jess' => redirect()->route('jess.dashboard'),
           'sales' => redirect()->route('sales.dashboard'),
           'admin_pasuruan' => redirect()->route('pasuruan.admin'),
           'spv_pasuruan' => redirect()->route('spvpasuruan.admin'),

   

    default => redirect('/dashboard'),
    };
}

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        

        return redirect('/');
    }
}