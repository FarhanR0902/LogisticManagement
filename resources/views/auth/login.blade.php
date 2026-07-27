<!DOCTYPE html>
<html>
<head>
    <title>Login Sistem Logistik</title>

    <style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;

    background: url("https://cimory.com/uploads/banner/image_vFnyWP1700150109.jpg")
        no-repeat center center fixed;

    background-size: cover;

    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

/* BOX LOGIN */
.box {
    background: url("https://i.pinimg.com/1200x/ef/ee/51/efee5115073880a3555e00bf6bc88253.jpg")
      /* background: url("https://cimory.com/uploads/products/Product-Banner-(Squeeze-Yogurt-120)---1440x633px_Artboard-4-copy-3.jpg") */
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
.logo{
    display:flex;
    flex-direction:column;
    align-items:center;
    margin-bottom:30px;
}

.logo img{
    width:180px;
    height:auto; /* biar tidak gepeng/miring */
    max-height:180px;
    object-fit:contain;
    border-radius:15px;
    padding:10px;
    background:#fff;
    margin-bottom:20px;
}

/* NAMA APLIKASI */
.logo h3{
    margin:0;
    font-size:32px;
    font-weight:800;
    color:#111827;
    letter-spacing:3px;
}

/* JUDUL LOGIN */
h2{
    text-align:center;
    margin-bottom:30px;
    font-size:50px;
    font-weight:800;
    color:#111827;
}

/* INPUT */
input{
    width:100%;
    padding:20px;
    margin:15px 0;
    border:1px solid #d1d5db;
    border-radius:12px;
    font-size:22px;
    box-sizing:border-box;
}

input:focus{
    border-color:#22c55e;
    box-shadow:0 0 0 4px rgba(34,197,94,.2);
    outline:none;
}

/* BUTTON */
button{
    width:100%;
    padding:20px;
    font-size:22px;
    font-weight:bold;
    border:none;
    border-radius:12px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:white;
    cursor:pointer;
}

button:hover{
    transform:translateY(-2px);
}

/* ERROR */
.error{
    text-align:center;
    color:red;
    font-size:18px;
    margin-bottom:15px;
}

/* REGISTER */
.info-akun{
    margin-top:25px;
    text-align:center;
    font-size:25px;
}

.info-akun a{
    color:#22c55e;
    text-decoration:none;
    font-weight:bold;
}

.info-akun a:hover{
    text-decoration:underline;
}
    </style>
</head>

<body>

<div class="box">

    <!-- LOGO -->
    <div class="logo">
        <img src="https://yt3.ggpht.com/a/AATXAJzSu0_dWz3o0wVQBeVNEvS90WOnkRTh678MpQ=s900-c-k-c0xffffffff-no-rj-mo"
             alt="Logo">

        <h3>LOGISTIK SYSTEM</h3>
    </div>

    <h2>LOGIN</h2>

    @if(session('error'))
        <p class="error">{{ session('error') }}</p>
    @endif

    <form method="POST" action="/login">
        @csrf

        <input type="text" name="username" placeholder="Username" required>

        <input type="password" name="password" placeholder="Password" required>

        <button type="submit">LOGIN</button>

<div class="info-akun">
    Belum memiliki akun?
    <a href="{{ url('/register') }}">Daftar di sini</a>
</div>
</div>
    </form>

</div>

</body>
</html>