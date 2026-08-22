@include('template.sidebar')

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>User Management</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:#f3f4f6;
}

/* CONTAINER */

.container{
    width:calc(100% - 250px);
    margin-left:250px;
    padding:30px;
}

/* CARD */

.card{
    background:#fff;
    border-radius:20px;
    padding:30px;
    box-shadow:0 5px 20px rgba(0,0,0,.08);
}

/* HEADER */

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.title{
    font-size:28px;
    font-weight:bold;
    color:#111827;
}

/* BUTTON */

.btn{
    padding:12px 18px;
    border:none;
    border-radius:10px;
    text-decoration:none;
    color:#fff;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    transition:.2s;
}

.btn-add{
    background:#22c55e;
}

.btn-add:hover{
    background:#16a34a;
    transform:translateY(-2px);
}

.btn-edit{
    background:#3b82f6;
}

.btn-edit:hover{
    background:#2563eb;
}

.btn-delete{
    background:#ef4444;
}

.btn-delete:hover{
    background:#dc2626;
}

/* TABLE */

.table-responsive{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
}

table thead{
    background:#111827;
    color:#fff;
}

table th{
    padding:15px;
    text-align:left;
    font-size:14px;
}

table td{
    padding:15px;
    border-bottom:1px solid #e5e7eb;
    font-size:14px;
    color:#374151;
}

table tr:hover{
    background:#f9fafb;
}

/* ROLE */

.role{
    padding:7px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:bold;
    color:#fff;
    display:inline-block;
}

.role-planner{
    background:#3b82f6;
}

.role-monitoring{
    background:#f59e0b;
}

.role-manager{
    background:#10b981;
}
.role-spvplanner{
    background:#b91048;
}
.role-spvmonitoring{
    background:#1048b9;
}
.role-developer{
    background:#29ccec;
}

.role-sales{
    background:#8b5cf6;
}

.role-spv{
    background:#ef4444;
}

/* ALERT */

.alert{
    background:#dcfce7;
    color:#166534;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
}

/* ACTION */

.action{
    display:flex;
    gap:8px;
}

/* EMPTY */

.empty{
    text-align:center;
    padding:30px;
    color:#6b7280;
}

</style>

</head>
<body>

<div class="container">

    <div class="card">

        <div class="header">

            <div class="title">
                👥 User Management
            </div>

            <a href="{{ url('/users/create') }}"
               class="btn btn-add">

               + Tambah User

            </a>

        </div>

        @if(session('success'))

            <div class="alert">

                {{ session('success') }}

            </div>

        @endif

        <div class="table-responsive">

            <table>

                <thead>

                    <tr>

                        <th>No</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Verifikasi Account</th>
                        <th>Role</th>
                        <th>Dist Channel</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($users as $user)

                    <tr>

                        <td>{{ $loop->iteration }}</td>

                        <td>{{ $user->name }}</td>

                        <td>{{ $user->username }}</td>
                        <td>{{ $user->email }}</td>
                         <td>{{ $user->email_verified_at }}</td>
                        <td>

                            <span class="role role-{{ $user->role }}">

                                {{ strtoupper($user->role) }}

                            </span>

                        </td>
                        <td>{{ $user->dist_channel }}</td>

                        <td>

                            {{ $user->created_at }}

                        </td>

                        <td>

                            <div class="action">

                                <a href="{{ url('/users/'.$user->id.'/edit') }}"
                                   class="btn btn-edit">

                                   Edit

                                </a>

                                <form action="{{ url('/users/'.$user->id) }}"
                                      method="POST">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            class="btn btn-delete"
                                            onclick="return confirm('Hapus user ini?')">

                                            Hapus

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                    @empty

                    <tr>

                        <td colspan="6" class="empty">

                            Belum ada data user

                        </td>

                    </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>