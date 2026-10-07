@include('template.sidebar')

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Storage Archive</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<style>
body{font-family:'Segoe UI',sans-serif;background:#f3f4f6}
.container{margin-left:250px;padding:20px}
h2{margin-bottom:15px;color:#111827}
.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:15px}
.card,.filter-box,.table-box{background:#fff;padding:15px;border-radius:12px;box-shadow:0 6px 16px rgba(0,0,0,.06);margin-bottom:15px}
.filter{display:grid;grid-template-columns:repeat(3,1fr) auto;gap:10px}
select,input{padding:10px;border:1px solid #ddd;border-radius:8px}
button,.btn{padding:10px 15px;background:#3b82f6;color:#fff;border:0;border-radius:8px;cursor:pointer;text-decoration:none;display:inline-block;font-weight:600}
.btn-green{background:green}.btn-red{background:#ef4444}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px}
table.dataTable thead th{background:#111827;color:#fff;font-size:12px;white-space:nowrap}
table.dataTable tbody td{font-size:12px;text-align:center;white-space:nowrap;max-width:260px;overflow:hidden;text-overflow:ellipsis}
.dataTables_wrapper .dataTables_scrollBody{max-height:70vh}
@media(max-width:768px){.container{margin-left:0}.kpi-row{grid-template-columns:1fr 1fr}.filter{grid-template-columns:1fr}}
</style>
</head>

<body>
<div class="container">

<h2>🗄 STORAGE ARCHIVE LOGISTIK</h2>

@if(session('success'))
<div style="background:#dcfce7;padding:10px;border-radius:8px;margin-bottom:10px">{{ session('success') }}</div>
@endif

<div class="kpi-row">
    <div class="card"><h4>Total Data</h4><h1>{{ number_format($total_data) }}</h1></div>
    <div class="card"><h4>Total Biaya</h4><h1>Rp {{ number_format($total_biaya,0,',','.') }}</h1></div>
    <div class="card"><h4>Total Muatan</h4><h1>Rp {{ number_format($total_muatan,0,',','.') }}</h1></div>
    <div class="card"><h4>Cost Ratio</h4><h1>{{ number_format($cost_ratio,2) }}%</h1></div>
</div>

<div class="filter-box">
    <form method="GET" class="filter">
        <select name="month" id="f_month">
            <option value="">All Month</option>
            @for($m=1;$m<=12;$m++)
                <option value="{{ $m }}" {{ request('month')==$m?'selected':'' }}>
                    {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                </option>
            @endfor
        </select>

        <select name="year" id="f_year">
            <option value="">All Year</option>
            @for($y=date('Y');$y>=2020;$y--)
                <option value="{{ $y }}" {{ request('year')==$y?'selected':'' }}>{{ $y }}</option>
            @endfor
        </select>

        <select name="area" id="f_area">
            <option value="">All Area</option>
            @foreach($list_area as $a)
                <option value="{{ $a }}" {{ request('area')==$a?'selected':'' }}>{{ $a }}</option>
            @endforeach
        </select>

        <button type="submit">Filter</button>
    </form>
</div>

<div class="actions">
    <a class="btn btn-green" href="{{ url('/storage/export?' . http_build_query(request()->all())) }}">Export Excel</a>

    <form action="{{ url('/storage/delete-all') }}" method="POST"
          onsubmit="return confirm('Yakin ingin menghapus SEMUA data archive?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-red">🗑 Hapus Semua Data</button>
    </form>
</div>

<div class="table-box">
    <table id="tblStorage" class="display" style="width:100%"></table>
</div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
// semua kolom database, otomatis dari controller
const DB_COLS = @json($columns);

// id -> "ID", tanggal_naik_logistik -> "TANGGAL NAIK LOGISTIK"
const label = c => c.replace(/_/g, ' ').toUpperCase();

const tableCols = DB_COLS.map(c => ({
    data: c,
    name: c,
    title: label(c),
    defaultContent: '-',
    // render.text() = escape HTML otomatis; kosong tampil "-"
    render: function (v) {
        if (v === null || v === '') return '-';
        return $('<div>').text(v).html();
    }
}));

// default sort: tanggal_naik_logistik terbaru
let defaultIdx = DB_COLS.indexOf('tanggal_naik_logistik');
if (defaultIdx < 0) defaultIdx = 0;

$('#tblStorage').DataTable({
    serverSide: true,
    processing: true,
    deferRender: true,
    scrollX: true,
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    searchDelay: 600,
    order: [[defaultIdx, 'desc']],
    columns: tableCols,
    ajax: {
        url: "{{ route('storage.data') }}",
        type: 'GET',
        // payload ringkas: default DataTables ngirim ~6 param x jumlah kolom, URL bisa kepanjangan
        data: function (d) {
            const o = d.order[0] || { column: defaultIdx, dir: 'desc' };
            return {
                draw: d.draw,
                start: d.start,
                length: d.length,
                search: d.search.value,
                order_col: d.columns[o.column].name,
                order_dir: o.dir,
                month: $('#f_month').val(),
                year:  $('#f_year').val(),
                area:  $('#f_area').val(),
            };
        }
    }
});
</script>
</body>
</html>