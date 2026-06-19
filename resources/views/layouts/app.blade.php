<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Logistik App</title>

    <style>
        body{
            margin:0;
            font-family:Segoe UI, sans-serif;
            background:#eef2f7;
        }

        main{
            margin-left:250px;
            padding:20px;
        }
    </style>

</head>
<body>

    {{-- SIDEBAR --}}
    @include('template.sidebar')

    {{-- CONTENT --}}
    <main>
        @yield('content')
    </main>

</body>
</html>