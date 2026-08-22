<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verifikasi Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .verify-box {
            background: #fff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        .verify-box h4 {
            font-weight: 700;
            margin-bottom: 16px;
        }
        .verify-box p {
            color: #475569;
            margin-bottom: 24px;
        }
        form {
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

    <div class="verify-box">
        <h4>📧 Verifikasi Email Kamu</h4>

        <p>
            Kami sudah mengirim link verifikasi ke email kamu.
            Silakan cek inbox (atau folder spam) dan klik link tersebut
            untuk mengaktifkan akun sebelum bisa login.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if (session('message'))
            <div class="alert alert-info">{{ session('message') }}</div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary w-100">
                Kirim Ulang Email Verifikasi
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary w-100">
                Logout
            </button>
        </form>
    </div>

</body>
</html>