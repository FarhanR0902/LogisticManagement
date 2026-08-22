<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

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

    public function registerStore(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'username' => 'required|unique:users',
            'password' => 'required|min:6',
            'role' => 'required',
            'dist_channel' => 'nullable',
        ]);

        User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user',
            'dist_channel' => $request->dist_channel,
        ]);

        return redirect('/login')->with('success', 'Registrasi berhasil, silakan login');
    }

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

    public function destroy($id)
    {
        User::findOrFail($id)->delete();

        return redirect('/users')->with('success', 'User berhasil dihapus');
    }
}