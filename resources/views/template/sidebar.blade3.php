@php
use Illuminate\Support\Facades\Auth;

$user = Auth::user();

/*
|--------------------------------------------------------------------------
| ROLE STABLE FIX
|--------------------------------------------------------------------------
| pakai session biar tidak berubah saat pindah view/route
*/
$role = session('role', $user->role ?? 'guest');

/*
|--------------------------------------------------------------------------
| DASHBOARD ROUTE MAP
|--------------------------------------------------------------------------
*/
$dashboard_url = match($role) {
    'planner' => route('planner.dashboard'),
    'monitoring' => route('monitoring.dashboard'),
    'manager' => route('manager.dashboard'),
    'sales' => route('sales.dashboard'),
    'spvplanner' => route('spvplanner.dashboard'),
    'spvmonitoring' => route('spvmonitoring.dashboard'),
    'developer' => route('developer.dashboard'),
    default => url('/dashboard'),
};
@endphp

<div class="sidebar">

    <div class="logo">
        🚚 LOGISTIK SYSTEM
    </div>

    <ul class="menu">

        {{-- ================= DASHBOARD ================= --}}
        <li>
            <a href="{{ $dashboard_url }}">
                📊 Dashboard
            </a>
        </li>

        {{-- ================= PLANNER ================= --}}
        @if($role === 'planner')
            <li class="title">PLANNER MENU</li>

            <li><a href="{{ route('planner.dashboard') }}">📊 Dashboard</a></li>
            <li><a href="{{ url('/datalogistik') }}">📋 Full Data</a></li>
            <li><a href="{{ route('planner.datalogistik') }}">📦 Data Planner</a></li>
            <li><a href="{{ route('planner.sla.ontime') }}">✅ SLA On Time</a></li>
            <li><a href="{{ route('planner.sla.delay') }}">❌ SLA Delay</a></li>
            <li><a href="{{ route('planner.armada') }}">🚚 Armada Ready</a></li>
            <li><a href="{{ route('planner.belum.armada') }}">⏳ Belum Armada</a></li>
        @endif

        {{-- ================= SPV PLANNER ================= --}}
        @if($role === 'spvplanner')
            <li class="title">SPV PLANNER</li>

            <li><a href="{{ route('spvplanner.dashboard') }}">👨‍💼 Dashboard</a></li>
            <li><a href="{{ url('/datalogistik') }}">📋 Full Data</a></li>
            <li><a href="{{ route('spvplanner.datalogistik') }}">📦 Data</a></li>
            <li><a href="{{ route('spvplanner.sla.ontime') }}">✅ SLA On Time</a></li>
            <li><a href="{{ route('spvplanner.sla.delay') }}">❌ SLA Delay</a></li>
            <li><a href="{{ route('spvplanner.armada') }}">🚚 Armada</a></li>
            <li><a href="{{ route('spvplanner.belum.armada') }}">⏳ Pending</a></li>
        @endif

        {{-- ================= MONITORING ================= --}}
        @if($role === 'monitoring')
            <li class="title">MONITORING</li>

            <li><a href="{{ route('monitoring.dashboard') }}">📊 Dashboard</a></li>
            <li><a href="{{ url('/datalogistik') }}">📋 Full Data</a></li>
            <li><a href="{{ route('monitoring.datalogistik') }}">🚚 Data</a></li>
            <li><a href="{{ route('monitoring.sla.ontime') }}">✅ SLA On Time</a></li>
            <li><a href="{{ route('monitoring.sla.delay') }}">❌ SLA Delay</a></li>
            <li><a href="{{ route('monitoring.bongkar.ontime') }}">📦 Bongkar On Time</a></li>
            <li><a href="{{ route('monitoring.bongkar.delay') }}">📦 Bongkar Delay</a></li>
            <li><a href="{{ route('monitoring.summary.area') }}">🌍 Summary Area</a></li>
        @endif

        {{-- ================= SPV MONITORING ================= --}}
        @if($role === 'spvmonitoring')
            <li class="title">SPV MONITORING</li>

            <li><a href="{{ route('spvmonitoring.dashboard') }}">📊 Dashboard</a></li>
            <li><a href="{{ route('spvmonitoring.datalogistik') }}">🚚 Data</a></li>
            <li><a href="{{ route('spvmonitoring.sla.ontime') }}">✅ SLA On Time</a></li>
            <li><a href="{{ route('spvmonitoring.sla.delay') }}">❌ SLA Delay</a></li>
            <li><a href="{{ route('spvmonitoring.bongkar.ontime') }}">📦 Bongkar On Time</a></li>
            <li><a href="{{ route('spvmonitoring.bongkar.delay') }}">📦 Bongkar Delay</a></li>
            <li><a href="{{ route('spvmonitoring.summary.area') }}">🌍 Summary Area</a></li>
        @endif

        {{-- ================= MANAGER ================= --}}
        @if($role === 'manager')
            <li class="title">MANAGER</li>

            <li><a href="{{ route('manager.dashboard') }}">📊 Dashboard</a></li>
            <li><a href="{{ url('/datalogistik') }}">📋 Full Data</a></li>

            <li class="title">SUMMARY</li>
            <li><a href="{{ route('manager.summary.total') }}">📈 Summary Total</a></li>
            <li><a href="{{ route('manager.summary.area') }}">🌍 Summary Area</a></li>

            <li class="title">GUDANG</li>
            <li><a href="{{ route('manager.gudang.ontime') }}">✅ On Time</a></li>
            <li><a href="{{ route('manager.gudang.delay') }}">❌ Delay</a></li>

            <li class="title">CUSTOMER</li>
            <li><a href="{{ route('manager.customer.ontime') }}">🚚 On Time</a></li>
            <li><a href="{{ route('manager.customer.delay') }}">🚚 Delay</a></li>

            <li class="title">BONGKAR</li>
            <li><a href="{{ route('manager.bongkar.ontime') }}">📦 On Time</a></li>
            <li><a href="{{ route('manager.bongkar.delay') }}">📦 Delay</a></li>

            <li class="title">ACCESS VIEW</li>
            <li><a href="{{ route('planner.datalogistik') }}">📦 Planner</a></li>
            <li><a href="{{ route('monitoring.datalogistik') }}">🚚 Monitoring</a></li>
        @endif

        {{-- ================= SALES ================= --}}
        @if($role === 'sales')
            <li class="title">SALES</li>

            <li><a href="{{ route('sales.dashboard') }}">📊 Dashboard</a></li>
            <li><a href="{{ route('sales.customer.ontime') }}">✅ On Time</a></li>
            <li><a href="{{ route('sales.customer.delay') }}">❌ Delay</a></li>
            <li><a href="{{ route('sales.summary.area') }}">🌍 Area</a></li>
        @endif

        {{-- ================= DEVELOPER ================= --}}
        @if($role === 'developer')
            <li class="title">DEVELOPER</li>

            <li><a href="{{ route('developer.dashboard') }}">🧠 Dashboard</a></li>
            <li><a href="{{ url('/datalogistik') }}">📋 All Data</a></li>
            <li><a href="{{ route('manager.dashboard') }}">👨‍💼 Manager</a></li>
            <li><a href="{{ route('planner.dashboard') }}">📦 Planner</a></li>
            <li><a href="{{ route('monitoring.dashboard') }}">🚚 Monitoring</a></li>
            <li><a href="{{ route('sales.dashboard') }}">💰 Sales</a></li>
        @endif

        {{-- ================= ACCOUNT ================= --}}
        <li class="title">ACCOUNT</li>

        <li>
            <form method="POST" action="{{ url('/logout') }}">
                @csrf
                <button type="submit" class="logout-btn">
                    🚪 Logout
                </button>
            </form>
        </li>

    </ul>
</div>

<style>

/* ================= RESET ================= */
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

/* ================= SIDEBAR ================= */
.sidebar{
    width:260px;
    height:100vh;
    position:fixed;
    top:0;
    left:0;
    background: linear-gradient(180deg,#0f172a,#111827,#0b1220);
    color:#fff;
    overflow-y:auto;
    padding:18px 12px;
    box-shadow:4px 0 20px rgba(0,0,0,.25);
}

/* scrollbar */
.sidebar::-webkit-scrollbar{
    width:6px;
}
.sidebar::-webkit-scrollbar-thumb{
    background:#22c55e;
    border-radius:10px;
}

/* ================= LOGO ================= */
.logo{
    font-size:18px;
    font-weight:800;
    text-align:center;
    padding:14px;
    margin-bottom:18px;
    background: linear-gradient(135deg,#22c55e,#3b82f6);
    border-radius:12px;
    box-shadow:0 6px 18px rgba(34,197,94,.25);
}

/* ================= MENU ================= */
.menu{
    list-style:none;
}

.menu li{
    margin-bottom:4px;
}

/* ================= TITLE ================= */
.title{
    font-size:11px;
    margin:16px 8px 6px;
    color:#94a3b8;
    letter-spacing:1px;
    text-transform:uppercase;
    font-weight:700;
}

/* ================= LINK ================= */
.menu a{
    display:flex;
    align-items:center;
    gap:10px;
    padding:11px 12px;
    border-radius:10px;
    color:#e5e7eb;
    text-decoration:none;
    font-size:14px;
    font-weight:500;
    transition:.25s ease;
    position:relative;
}

/* hover effect */
.menu a:hover{
    background: linear-gradient(135deg,#22c55e,#3b82f6);
    color:#000;
    transform:translateX(5px);
    box-shadow:0 6px 15px rgba(34,197,94,.25);
}

/* active style (optional kalau kamu mau kasih class active) */
.menu a.active{
    background: linear-gradient(135deg,#f59e0b,#ef4444);
    color:#fff;
    font-weight:600;
}

/* ================= LOGOUT BUTTON ================= */
.logout-btn{
    width:100%;
    border:none;
    padding:12px;
    border-radius:10px;
    background: linear-gradient(135deg,#ef4444,#b91c1c);
    color:#fff;
    font-weight:700;
    cursor:pointer;
    transition:.2s;
    margin-top:8px;
}

.logout-btn:hover{
    transform:scale(1.03);
    box-shadow:0 10px 20px rgba(239,68,68,.25);
}

/* ================= RESPONSIVE ================= */
@media(max-width:768px){
    .sidebar{
        width:220px;
    }

    .menu a{
        font-size:13px;
        padding:10px;
    }
}

/* ================= ROLE COLOR ACCENT (OPTIONAL WOW EFFECT) ================= */
.menu li.title{
    position:relative;
}

.menu li.title::after{
    content:'';
    position:absolute;
    left:8px;
    bottom:-4px;
    width:40px;
    height:2px;
    background: linear-gradient(90deg,#22c55e,#3b82f6);
    border-radius:10px;
}

</style>