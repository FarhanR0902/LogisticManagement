<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function login(Request $request)
{
    $request->validate([
        'username' => 'required',
        'password' => 'required',
    ]);

    $user = User::where('username', $request->username)->first();
 
    // cek user ditemukan atau tidak
    if (!$user) {
        return back()->with('error', 'Username tidak ditemukan');
    }

    // cek password
    if (!Hash::check($request->password, $user->password)) {
        return back()->with('error', 'Password salah');
    }

    Auth::login($user);

    $role = strtolower(trim($user->role ?? ''));

    // rapikan role


    session([
        'name' => $user->name,
        'username' => $user->username,
        'role' => $role,
        'dist_channel' => strtolower(trim($user->dist_channel)),
    ]);

    return match ($role) {

        'planner' => redirect('/planner/dashboard'),

        'monitoring' => redirect('/monitoring/dashboard'),

        'spv' => redirect('/spv/dashboard'),

        'manager' => redirect('/manager/dashboard'),

        'sales' => redirect('/sales/dashboard'),

        'spvplanner' => redirect('/spvplanner/dashboard'),

        'spvmonitoring' => redirect('/spvmonitoring/dashboard'),

        'developer' => redirect('/developer/dashboard'),

         'cmd' => redirect()->route('cmd.dashboard'),
          'jess' => redirect()->route('jess.dashboard'),
          'admin_pasuruan' => redirect()->route('pasuruan.dashboard'),
          'spv_pasuruan' => redirect()->route('spvpasuruan.admin'),
        // 'jess' => redirect('/jess/dashboard'),

        default => redirect('/dashboard'),
    };
}


public function logout()
{
    Auth::logout();
    session()->flush();

    return redirect('/login');
}
}