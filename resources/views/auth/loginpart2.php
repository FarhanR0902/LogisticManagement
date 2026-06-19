<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login Sistem Logistik</title>

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{

            font-family: Arial, Helvetica, sans-serif;

            height:100vh;

            display:flex;
            justify-content:center;
            align-items:center;

            overflow:hidden;

            background:
                linear-gradient(
                    rgba(0,0,0,0.55),
                    rgba(0,0,0,0.55)
                ),
                url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?q=80&w=1974&auto=format&fit=crop');

            background-size:cover;
            background-position:center;
            background-repeat:no-repeat;
        }

        /* LOGIN BOX */

        .login-box{

            width:380px;

            padding:35px;

            border-radius:22px;

            background:rgba(255,255,255,0.12);

            backdrop-filter:blur(14px);

            border:1px solid rgba(255,255,255,0.2);

            box-shadow:
                0 10px 35px rgba(0,0,0,0.35);

            animation:fadeIn .5s ease;
        }

        @keyframes fadeIn{

            from{
                opacity:0;
                transform:translateY(20px);
            }

            to{
                opacity:1;
                transform:translateY(0);
            }
        }

        /* LOGO */

        .logo{

            display:flex;
            flex-direction:column;
            align-items:center;

            margin-bottom:25px;
        }

        .logo img{

            width:95px;
            height:95px;

            border-radius:50%;

            object-fit:cover;

            background:white;

            padding:5px;

            border:4px solid #22c55e;

            margin-bottom:15px;

            box-shadow:
                0 6px 18px rgba(0,0,0,0.25);
        }

        .logo h1{

            color:white;

            font-size:24px;

            margin-bottom:6px;

            letter-spacing:1px;
        }

        .logo p{

            color:rgba(255,255,255,0.7);

            font-size:13px;
        }

        /* ERROR */

        .error{

            background:rgba(239,68,68,0.18);

            border:1px solid rgba(239,68,68,0.35);

            color:white;

            padding:12px;

            border-radius:10px;

            margin-bottom:16px;

            text-align:center;

            font-size:14px;
        }

        /* INPUT */

        .input-group{

            margin-bottom:18px;
        }

        .input-group label{

            display:block;

            margin-bottom:8px;

            color:white;

            font-size:14px;
        }

        .input-group input{

            width:100%;

            padding:13px 15px;

            border-radius:12px;

            border:1px solid rgba(255,255,255,0.18);

            background:rgba(255,255,255,0.1);

            color:white;

            outline:none;

            transition:.3s;
        }

        .input-group input::placeholder{

            color:rgba(255,255,255,0.6);
        }

        .input-group input:focus{

            border-color:#22c55e;

            box-shadow:
                0 0 0 4px rgba(34,197,94,0.18);
        }

        /* BUTTON */

        .login-btn{

            width:100%;

            padding:13px;

            border:none;

            border-radius:12px;

            background:
                linear-gradient(
                    135deg,
                    #22c55e,
                    #16a34a
                );

            color:white;

            font-weight:bold;

            font-size:15px;

            cursor:pointer;

            transition:.3s;
        }

        .login-btn:hover{

            transform:translateY(-2px);

            box-shadow:
                0 10px 20px rgba(34,197,94,0.35);
        }

        /* FOOTER */

        .footer{

            margin-top:22px;

            text-align:center;

            color:rgba(255,255,255,0.6);

            font-size:12px;
        }

        /* RESPONSIVE */

        @media(max-width:480px){

            .login-box{

                width:92%;

                padding:28px;
            }
        }

    </style>
</head>

<body>

<div class="login-box">

    <!-- LOGO -->

    <div class="logo">

        <img
            src="http://tridayaomega.com/logo/cimory.png"
            alt="Logo Cimory"
        >

        <h1>LOGISTIK SYSTEM</h1>

        <p>PT Macroprima Panganutama</p>

    </div>

    <!-- ERROR -->

    @if(session('error'))

        <div class="error">
            {{ session('error') }}
        </div>

    @endif

    @if ($errors->any())

        <div class="error">
            {{ $errors->first() }}
        </div>

    @endif

    <!-- FORM LOGIN -->

    <form method="POST" action="{{ route('login') }}">

        @csrf

        <div class="input-group">

            <label>Username</label>

            <input
                type="text"
                name="username"
                placeholder="Masukkan username"
                required
            >

        </div>

        <div class="input-group">

            <label>Password</label>

            <input
                type="password"
                name="password"
                placeholder="Masukkan password"
                required
            >

        </div>

        <button type="submit" class="login-btn">
            LOGIN
        </button>

    </form>

    <!-- FOOTER -->

    <div class="footer">
        © 2026 Logistic Monitoring System
    </div>

</div>

</body>
</html>