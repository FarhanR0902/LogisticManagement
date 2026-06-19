

<!DOCTYPE html>
<html>

<head>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <meta charset="utf-8">

    <title>Tambah User</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background: #f3f4f6;
        }

        .container {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 30px;
        }

        .card {
            background: #fff;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
        }

        .title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 25px;
            color: #111827;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }

        input,
        select {
            width: 100%;
            padding: 14px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            outline: none;
            font-size: 14px;
            transition: .2s;
        }

        input:focus,
        select:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, .15);
        }

        button {
            background: #22c55e;
            color: #fff;
            border: none;
            padding: 14px 22px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
            transition: .2s;
        }

        button:hover {
            background: #16a34a;
            transform: translateY(-2px);
        }

        .alert {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        body{
    margin:0;
    font-family:'Segoe UI',sans-serif;
    background:linear-gradient(135deg,#e5e7eb,#f8fafc);
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}

.register-box{
    width:450px;
    background:#fff;
    padding:35px;
    border-radius:20px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}

.logo{
    text-align:center;
    margin-bottom:20px;
}

.logo img{
    width:80px;
    height:80px;
    border-radius:50%;
    border:3px solid #22c55e;
    object-fit:cover;
}

.logo h3{
    margin-top:10px;
    color:#111827;
}

h2{
    text-align:center;
    margin-bottom:25px;
    color:#111827;
}

.form-group{
    margin-bottom:18px;
}

label{
    display:block;
    margin-bottom:7px;
    font-weight:600;
}

input,
select{
    width:100%;
    padding:12px;
    border:1px solid #d1d5db;
    border-radius:10px;
    outline:none;
}

input:focus,
select:focus{
    border-color:#22c55e;
    box-shadow:0 0 0 3px rgba(34,197,94,.15);
}

button{
    width:100%;
    padding:13px;
    border:none;
    border-radius:10px;
    background:#22c55e;
    color:white;
    font-weight:bold;
    cursor:pointer;
    transition:.2s;
}

button:hover{
    background:#16a34a;
}

.alert{
    background:#fee2e2;
    color:#991b1b;
    padding:12px;
    border-radius:10px;
    margin-bottom:20px;
}

.login-link{
    margin-top:18px;
    text-align:center;
    font-size:14px;
}

.login-link a{
    color:#22c55e;
    font-weight:bold;
    text-decoration:none;
}
    </style>

</head>

<body>

<div class="register-box">

    <div class="logo">
        <img src="https://yt3.ggpht.com/a/AATXAJzSu0_dWz3o0wVQBeVNEvS90WOnkRTh678MpQ=s900-c-k-c0xffffffff-no-rj-mo">
        <h3>LOGISTIK SYSTEM</h3>
    </div>

    <h2>Registrasi Akun</h2>

    @if ($errors->any())
    <div class="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('register.store') }}" method="POST">
        @csrf

        <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" name="name" placeholder="Masukkan nama">
        </div>

        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Masukkan username">
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Masukkan password">
        </div>

        <div class="form-group">
            <label>Role</label>

            <select name="role">
                <option value="">-- Pilih Role --</option>
                <option value="planner">Planner</option>
                <option value="monitoring">Monitoring</option>
                <option value="manager">Manager</option>
               
                <option value="developer">Developer</option>
                <option value="spvplanner">SPV Planner</option>
                <option value="spvmonitoring">SPV Monitoring</option>
              
                <option value="jess">Sales</option>
             
            </select>
        </div>

        <div class="form-group">
            <label>Dist Channel (Opsional)</label>

            <select name="dist_channel" id="dist_channel">
                <option value="">-- Tidak Menggunakan Dist Channel --</option>

                @foreach($distChannels as $channel)
                    <option value="{{ $channel }}">
                        {{ $channel }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit">
            Daftar
        </button>

        <div class="login-link">
            Sudah punya akun?
            <a href="{{ url('/login') }}">Login di sini</a>
        </div>

    </form>

</div>

</body>

<script>
$(document).ready(function() {
    $('#dist_channel').select2({
        placeholder: "Cari Dist Channel...",
        allowClear: true
    });
});
</script>

</html>