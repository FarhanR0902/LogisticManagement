<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit User</title>

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;

            background: url("https://cimory.com/uploads/banner/image_vFnyWP1700150109.jpg")
                no-repeat center center fixed;

            background-size: cover;

            display: flex;
            justify-content: center;
            align-items: center;

            min-height: 100vh;
            margin: 0;
        }

        /* BOX EDIT */
        .register-box {
            background: url("https://i.pinimg.com/1200x/ef/ee/51/efee5115073880a3555e00bf6bc88253.jpg")
                no-repeat center center;

            background-size: cover;

            padding: 60px;
            border-radius: 20px;
            width: 650px;

            box-shadow: 0 20px 40px rgba(0,0,0,0.25);

            position: relative;
            overflow: hidden;
        }

        /* LOGO */
        .logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo img {
            width: 180px;
            height: auto;
            max-height: 180px;
            object-fit: contain;
            border-radius: 15px;
            padding: 10px;
            background: #fff;
            margin-bottom: 20px;
        }

        .logo h3 {
            margin: 0;
            font-size: 32px;
            font-weight: 800;
            color: #111827;
            letter-spacing: 3px;
        }

        /* TITLE */
        h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 50px;
            font-weight: 800;
            color: #111827;
        }

        /* LABEL */
        label {
            font-size: 20px;
            font-weight: 600;
            color: #374151;
        }

        /* INPUT & SELECT */
        input,
        select {
            width: 100%;
            padding: 20px;
            margin: 15px 0;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            font-size: 22px;
            box-sizing: border-box;
        }

        input:focus,
        select:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 4px rgba(34,197,94,.2);
            outline: none;
        }

        /* BUTTON */
        button {
            width: 100%;
            padding: 20px;
            font-size: 22px;
            font-weight: bold;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg,#22c55e,#16a34a);
            color: white;
            cursor: pointer;
            margin-top: 15px;
        }

        button:hover {
            transform: translateY(-2px);
        }

        /* BACK BUTTON */
        .back-button {
            display: block;
            width: 100%;
            padding: 20px;
            margin-top: 15px;
            box-sizing: border-box;

            text-align: center;
            font-size: 22px;
            font-weight: bold;

            border-radius: 12px;
            background: #6b7280;
            color: white;
            text-decoration: none;
        }

        .back-button:hover {
            background: #4b5563;
        }

        /* ERROR */
        .alert {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert ul {
            margin: 0;
            padding-left: 20px;
        }

        /* SELECT2 */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            display: flex;
            align-items: center;
        }

        .select2-container--default
        .select2-selection--single
        .select2-selection__rendered {
            font-size: 14px;
            color: #111827;
            padding-left: 10px;
        }

        .select2-container--default
        .select2-selection--single
        .select2-selection__arrow {
            height: 42px;
        }

        .select2-dropdown {
            border-radius: 10px;
            overflow: hidden;
        }

        .select2-search--dropdown .select2-search__field {
            border-radius: 8px;
            padding: 8px;
        }
    </style>
</head>

<body>

<div class="register-box">

    <div class="logo">
        <img src="https://yt3.ggpht.com/a/AATXAJzSu0_dWz3o0wVQBeVNEvS90WOnkRTh678MpQ=s900-c-k-c0xffffffff-no-rj-mo">
        <h3>LOGISTIK SYSTEM</h3>
    </div>

    <h2>Edit Akun</h2>

    @if ($errors->any())
        <div class="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('users.update', $user->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label>Nama Lengkap</label>

            <input
                type="text"
                name="name"
                placeholder="Masukkan nama"
                value="{{ old('name', $user->name) }}"
                required
            >
        </div>

        <div class="form-group">
            <label>Username</label>

            <input
                type="text"
                name="username"
                placeholder="Masukkan username"
                value="{{ old('username', $user->username) }}"
                required
            >
        </div>

        <div class="form-group">
            <label>Email</label>

            <input
                type="email"
                name="email"
                placeholder="Masukkan email"
                value="{{ old('email', $user->email) }}"
                required
            >
        </div>

        <div class="form-group">
            <label>Password</label>

            <input
                type="password"
                name="password"
                placeholder="Kosongkan jika tidak ingin mengubah password"
            >
        </div>

        <div class="form-group">
            <label>Role</label>

            <select name="role" id="role" required>

                <option value="">-- Pilih Role --</option>

                <option value="planner"
                    {{ old('role', $user->role) == 'planner' ? 'selected' : '' }}>
                    Planner Jakarta
                </option>

                <option value="admin_pasuruan"
                    {{ old('role', $user->role) == 'admin_pasuruan' ? 'selected' : '' }}>
                    Pasuruan
                </option>

                <option value="monitoring"
                    {{ old('role', $user->role) == 'monitoring' ? 'selected' : '' }}>
                    Monitoring Jakarta
                </option>

                <option value="manager"
                    {{ old('role', $user->role) == 'manager' ? 'selected' : '' }}>
                    Manager
                </option>

                <option value="spvplanner"
                    {{ old('role', $user->role) == 'spvplanner' ? 'selected' : '' }}>
                    SPV Planner Jakarta
                </option>

                <option value="spvmonitoring"
                    {{ old('role', $user->role) == 'spvmonitoring' ? 'selected' : '' }}>
                    SPV Monitoring Jakarta
                </option>

            </select>
        </div>

        <div class="form-group" id="dist_channel_group" style="display:none;">

            <label>Dist Channel</label>

            <select
                name="dist_channel"
                id="dist_channel"
                class="form-control select2"
            >

                <option value="">-- Pilih Dist Channel --</option>

                @foreach($distChannels as $channel)

                    <option value="{{ $channel }}"
                        {{ old('dist_channel', $user->dist_channel ?? '') == $channel ? 'selected' : '' }}>
                        {{ $channel }}
                    </option>

                @endforeach

            </select>

        </div>

        <button type="submit">
            Simpan Perubahan
        </button>

        <a href="{{ route('users.index') }}" class="back-button">
            Kembali
        </a>

    </form>

</div>

<script>

$(document).ready(function () {

    $('#dist_channel').select2({
        placeholder: "Cari Dist Channel...",
        allowClear: true,
        width: '100%'
    });

    function toggleDistChannel() {

        let role = $('#role').val();

        if (role === 'sales') {

            $('#dist_channel_group').slideDown(200);

        } else {

            $('#dist_channel_group').slideUp(200);

            $('#dist_channel')
                .val(null)
                .trigger('change');
        }
    }

    // cek saat halaman dibuka
    toggleDistChannel();

    // cek saat role berubah
    $('#role').on('change', function () {
        toggleDistChannel();
    });

});

</script>

</body>
</html>