@include('template.sidebar')

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
    </style>

</head>

<body>

    <div class="container">

        <div class="card">

            <div class="title">
                👥 Tambah User
            </div>

            @if ($errors->any())

            <div class="alert">

                <ul>

                    @foreach ($errors->all() as $error)

                    <li>{{ $error }}</li>

                    @endforeach

                </ul>

            </div>

            @endif

            <form action="{{ url('/users') }}" method="POST">

                @csrf

                <div class="form-group">

                    <label>Nama User</label>

                    <input type="text"
                        name="name"
                        placeholder="Masukkan nama user">

                </div>

                <div class="form-group">

                    <label>Username</label>

                    <input type="text"
                        name="username"
                        placeholder="Masukkan username">

                </div>

                <div class="form-group">

                    <label>Password</label>

                    <input type="password"
                        name="password"
                        placeholder="Masukkan password">

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
    <label>Dist Channel</label>

    <select name="dist_channel" id="dist_channel" style="width:100%">
        <option value="">-- Pilih Dist Channel --</option>

        @foreach($distChannels as $channel)
            <option value="{{ $channel }}">
                {{ $channel }}
            </option>
        @endforeach
    </select>
</div>

                <button type="submit">
                    💾 Simpan User
                </button>

            </form>

        </div>

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