<!DOCTYPE html>

<html>

<head>

```
<meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Logistik App</title>

{{-- Bootstrap 5 --}}
<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

{{-- DataTables Bootstrap 5 --}}
<link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"
>

<style>

    body {
        margin: 0;
        font-family: Segoe UI, sans-serif;
        background: #eef2f7;
    }

    main {
        margin-left: 250px;
        padding: 20px;
    }

</style>

@stack('styles')
```

</head>

<body>

```
{{-- SIDEBAR --}}
@include('template.sidebar')

{{-- CONTENT --}}
<main>

    @yield('content')

</main>


{{-- jQuery --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

{{-- Bootstrap --}}
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

{{-- DataTables --}}
<script
    src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js">
</script>

{{-- DataTables Bootstrap 5 --}}
<script
    src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js">
</script>

{{-- Script dari masing-masing halaman --}}
@stack('scripts')
```

</body>

</html>
