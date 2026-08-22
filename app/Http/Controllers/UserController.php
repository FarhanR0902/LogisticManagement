<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->get();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $distChannels = DB::table('logistik_pengiriman')
            ->select('dist_channel')
            ->whereNotNull('dist_channel')
            ->distinct()
            ->orderBy('dist_channel')
            ->pluck('dist_channel');
            

        return view('users.create', compact('distChannels'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'username' => 'required|unique:users',
            'password' => 'required',
            'role' => 'required',
            'dist_channel' => 'required',
        ]);

        User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user',
            'dist_channel' => $request->dist_channel,
        ]);

        return redirect('/users')->with('success', 'User berhasil ditambahkan');
    }

public function register()
{
    $distChannels = DB::table('logistik_pengiriman')
        ->select('dist_channel')
        ->whereNotNull('dist_channel')
        ->distinct()
        ->orderBy('dist_channel')
        ->pluck('dist_channel');

    return view('auth.register', compact('distChannels'));
}

    // public function registerStore(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required',
    //         'username' => 'required|unique:users',
    //         // 'email' => 'required|email|unique:users,email',
    //         'password' => 'required|min:6',
    //         'role' => 'required',
    //         'dist_channel' => 'nullable',
    //     ]);

    //     $user = User::create([
    //         'name' => $request->name,
    //         'username' => $request->username,
    //         // 'email' => $request->email,
    //         'password' => Hash::make($request->password),
    //         'role' => $request->role ?? 'user',
    //         'dist_channel' => $request->dist_channel,
    //     ]);

    //     // Trigger otomatis kirim email verifikasi (listener bawaan Laravel)
    //     event(new Registered($user));

    //     // Login sementara supaya user bisa akses halaman "cek email kamu"
    //     Auth::login($user);

    //     return redirect()->route('verification.notice')
    //         ->with('success', 'Registrasi berhasil! Cek email kamu untuk verifikasi sebelum login.');
    // }


//     public function registerStore(Request $request)
// {
//     $request->validate([
//         'name' => 'required',
//         'username' => 'required|unique:users',
//         'password' => 'required|min:6',
//         'role' => 'required',
//         'dist_channel' => 'nullable',
//     ]);

//     $user = User::create([
//         'name' => $request->name,
//         'username' => $request->username,
//         'password' => Hash::make($request->password),
//         'role' => $request->role ?? 'user',
//         'dist_channel' => $request->dist_channel,
//     ]);

//     // Langsung login setelah registrasi
//     Auth::login($user);

//     return redirect()->route('dashboard')
//         ->with('success', 'Registrasi berhasil!');
// }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = [
            'name' => $request->name,
            'username' => $request->username,
            'role' => $request->role,
        ];

        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect('/users')->with('success', 'User berhasil diupdate');
    }
    public function registerStore(Request $request)
{
    $request->validate([
        'name' => 'required',
        'username' => 'required|unique:users',
        'password' => 'required|min:6',
        'role' => 'required',
        'dist_channel' => 'nullable',
    ]);

    $user = User::create([
        'name' => $request->name,
        'username' => $request->username,
        'password' => Hash::make($request->password),
        'role' => $request->role ?? 'user',
        'dist_channel' => $request->dist_channel,
    ]);

    // Langsung login setelah registrasi
    Auth::login($user);

    $role = strtolower(trim($user->role ?? ''));

    session([
        'name' => $user->name,
        'username' => $user->username,
        'role' => $role,
        'dist_channel' => strtolower(trim($user->dist_channel ?? '')),
    ]);

    return match ($role) {
        'planner' => redirect()->route('planner.dashboard'),
        'monitoring' => redirect()->route('monitoring.dashboard'),
        'manager' => redirect()->route('manager.dashboard'),
        'sales' => redirect()->route('sales.dashboard'),
        'spvplanner' => redirect()->route('spvplanner.dashboard'),
        'spvmonitoring' => redirect()->route('spvmonitoring.dashboard'),
        'developer' => redirect()->route('developer.dashboard'),
        'cmd' => redirect()->route('cmd.dashboard'),
        'pasuruan', 'admin_pasuruan' => redirect()->route('pasuruan.dashboard'),
        default => redirect('/login')->with('success', 'Registrasi berhasil! Silakan login.'),
    };
}

    public function destroy($id)
    {
        User::findOrFail($id)->delete();

        return redirect('/users')->with('success', 'User berhasil dihapus');
    }
}