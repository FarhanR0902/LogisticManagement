@php
use Illuminate\Support\Facades\Auth;

$user = Auth::user();

/*
|-------------------------------------------------
| FIX ROLE STABLE (TIDAK BERUBAH LAGI)
|-------------------------------------------------
*/
$role = $user?->role;

/*
|-------------------------------------------------
| DASHBOARD MAP
|-------------------------------------------------
*/
$dashboard_url = match($role) {
'planner' => route('planner.dashboard'),
'monitoring' => route('monitoring.dashboard'),
'manager' => route('manager.dashboard'),
'sales' => route('sales.dashboard'),
'spvplanner' => route('spvplanner.dashboard'),
'spvmonitoring' => route('spvmonitoring.dashboard'),
'cmd' => route('cmd.dashboard'),
'jess' => route('jess.dashboard'),
'developer' => route('developer.dashboard'),
default => url('/dashboard'),
};

/*
|-------------------------------------------------
| ACTIVE MENU HELPER
|-------------------------------------------------
*/
$currentRoute = request()->route()->getName() ?? '';
@endphp

<div class="sidebar">

    <div class="logo">
        <img src="https://yt3.ggpht.com/a/AATXAJzSu0_dWz3o0wVQBeVNEvS90WOnkRTh678MpQ=s900-c-k-c0xffffffff-no-rj-mo"
            alt="Logo"
            class="logo-img">

        <span>LOGISTIK SYSTEM</span>
    </div>
    <ul class="menu">

        {{-- ================= DASHBOARD ================= --}}
        <li>
            <a href="{{ $dashboard_url }}" class="{{ request()->is('dashboard') ? 'active' : '' }}">
                📊 Dashboard
            </a>
        </li>

        {{-- ================= PLANNER ================= --}}
        @if($role === 'planner' || $role === 'developer')

        <li class="title">PLANNER MENU</li>

      
        <li><a href="{{ url('/datalogistik') }}">📋 Full Data Logistik</a></li>
        <li><a href="{{ route('planner.datalogistik') }}">📦 Data Planner</a></li>
        <li><a href="{{ route('planner.sla.ontime') }}">✅ SLA On Time</a></li>
        <li><a href="{{ route('planner.sla.delay') }}">❌ SLA Delay</a></li>
        <li><a href="{{ route('armada') }}">🚚 Armada Ready</a></li>
         <!-- <li><a href="{{ route('armada.delay') }}">🚚 Armada delay</a></li> -->
  <li><a href="{{ route('planner.belum_armada') }}">⏳ Belum Armada</a></li>

        @endif

        {{-- ================= SPV PLANNER ================= --}}
        @if($role === 'spvplanner' || $role === 'developer')

        <li class="title">SPV PLANNER</li>
 <li> <a href="{{ route('spvplanner.full.dashboard', request()->query()) }}"> 📊 Full Dashboard </a> </li>

   <li>
            <a href="{{ url('/datalogistik') . '?' . http_build_query(request()->query()) }}">
                📦 Full Data Logistik
            </a>
        </li>

        <li>
            <a href="{{ route('spvplanner.datalogistik', request()->query()) }}">
                🗂️ Data Planner
            </a>
        </li>

        <li>
            <a href="{{ route('spvplanner.sla.ontime', request()->query()) }}">
                ✅ Tiba Di Gudang On Time
            </a>
        </li>

        <li>
            <a href="{{ route('spvplanner.sla.delay', request()->query()) }}">
                ❌ Tiba Di Gudang Delay
            </a>
        </li>

        <li>
            <a href="{{ route('spvplanner.armada', request()->query()) }}">
                🚚 Sudah Dapat Armada
            </a>
        </li>

        <li>
            <a href="{{ route('spvplanner.belum.armada', request()->query()) }}">
                ⏳ Belum Dapat Armada
            </a>
        </li>



        @endif

        {{-- ================= MONITORING ================= --}}
        @if($role === 'monitoring' || $role === 'developer')

        <li class="title">MONITORING</li>

     

        <li><a href="{{ url('/datalogistik') }}">📋 Full Data</a></li>
        <li><a href="{{ route('monitoring.datalogistik') }}">🚚 Data Monitoring</a></li>
        <li><a href="{{ route('monitoring.sla.ontime') }}">✅ Customer On Time</a></li>
        <li><a href="{{ route('monitoring.sla.delay') }}">❌ Customer Delay</a></li>
        <li><a href="{{ route('monitoring.bongkar.ontime') }}">📦 Bongkar On Time</a></li>
        <li><a href="{{ route('monitoring.bongkar.delay') }}">📦 Bongkar Delay</a></li>
        <li><a href="{{ route('monitoring.summary.area') }}">🌍 Summary Area</a></li>

        @endif

        {{-- ================= SPV MONITORING ================= --}}
        @if($role === 'spvmonitoring' || $role === 'developer')

        <li class="title">SPV MONITORING</li>


        <a>
<li> <a href="{{ route('spvmonitoring.full.dashboard', request()->query()) }}"> 📊 Full Dashboard </a> </li>
<a href="{{ url('/datalogistik') . '?' . http_build_query(request()->query()) }}">
    📦 Full Data Logistik
</a>
        <li> <a href="{{ route('spvmonitoring.datalogistik', request()->query()) }}"> 📦 Data Monitoring </a> </li>
        <li> <a href="{{ route('spvmonitoring.sla.ontime', request()->query()) }}"> ✅ SLA Tiba Ontime </a> </li>
        <li> <a href="{{ route('spvmonitoring.sla.delay', request()->query()) }}"> ❌ SLA Tiba Delay </a> </li>
        <li> <a href="{{ route('spvmonitoring.bongkar.ontime', request()->query()) }}"> 📥 Bongkar Ontime </a> </li>
        <li> <a href="{{ route('spvmonitoring.bongkar.delay', request()->query()) }}"> 🚨 Bongkar Delay </a> </li>
        <li> <a href="{{ route('spvmonitoring.summary.area', request()->query()) }}"> 🌍 Summary Area </a> </li>

        @endif

        {{-- ================= MANAGER ================= --}}
      @if($role === 'manager' || $role === 'developer')

    <a href="{{ url('/datalogistik') }}">📋 Full Data Logistik</a>

    <a href="{{ route('manager.gudang.ontime') }}">
        🏭 Gudang Ontime
    </a>

    <a href="{{ route('manager.gudang.delay') }}">
        🚨 Gudang Delay
    </a>

    <a href="{{ route('manager.customer.ontime') }}">
        🚚 Customer Ontime
    </a>

    <a href="{{ route('manager.customer.delay') }}">
        ⚠️ Customer Delay
    </a>

    <a href="{{ route('manager.bongkar.ontime') }}">
        📦 Bongkar Ontime
    </a>

    <a href="{{ route('manager.bongkar.delay') }}">
        ⏳ Bongkar Delay
    </a>

    <a href="{{ route('manager.summary.area') }}">
        🗺️ Summary Area
    </a>

    <a href="{{ route('manager.summary.total') }}">
        📑 Summary Total
    </a>
   

    <a href="{{ url('/storage') }}">
        🗄 Storage Archive
    </a>
    <li>
        <a href="{{ route('users.index') }}">
            👤 View Users
        </a>
    </li>




        @endif

        {{-- ================= SALES ================= --}}
        @if($role === 'sales' || $role === 'developer')

        <li class="title">SALES</li>

     <li>
    <a href="{{ route('sales.datalogistik') }}">
        📋 Full Data
    </a>
</li>
<li class="title">SALES</li>

<li><a href="{{ route('sales.datalogistik') }}">📋 Full Data</a></li>

<li><a href="{{ route('sales.gudang.ontime') }}">✅ Gudang On Time</a></li>
<li><a href="{{ route('sales.gudang.delay') }}">❌ Gudang Delay</a></li>

<li><a href="{{ route('sales.customer.ontime') }}">✅ Customer On Time</a></li>
<li><a href="{{ route('sales.customer.delay') }}">❌ Customer Delay</a></li>

<li><a href="{{ route('sales.bongkar.ontime') }}">🚚 Bongkar On Time</a></li>
<li><a href="{{ route('sales.bongkar.delay') }}">🚚 Bongkar Delay</a></li>

<li><a href="{{ route('sales.summary.area') }}">🌍 Area Summary</a></li>
        <!-- STORAGE MENU -->
     
        @endif

                {{-- ================= MANAGER ================= --}}
      @if($role === 'cmd' || $role === 'developer')

    <a href="{{ url('/datalogistik') }}">📋 Full Data Logistik</a>

    <a href="{{ route('cmd.gudang.ontime') }}">
        🏭 Gudang Ontime
    </a>

    <a href="{{ route('cmd.gudang.delay') }}">
        🚨 Gudang Delay
    </a>

    <a href="{{ route('cmd.customer.ontime') }}">
        🚚 Customer Ontime
    </a>

    <a href="{{ route('cmd.customer.delay') }}">
        ⚠️ Customer Delay
    </a>

    <a href="{{ route('cmd.bongkar.ontime') }}">
        📦 Bongkar Ontime
    </a>

    <a href="{{ route('cmd.bongkar.delay') }}">
        ⏳ Bongkar Delay
    </a>

    <a href="{{ route('cmd.summary.area') }}">
        🗺️ Summary Area
    </a>

    <a href="{{ route('cmd.summary.total') }}">
        📑 Summary Total
    </a>
   

    <a href="{{ url('/storage') }}">
        🗄 Storage Archive
    </a>
    <li>
        <a href="{{ route('users.index') }}">
            👤 View Users
        </a>
    </li>




        @endif

           @if($role === 'jess' || $role === 'developer')

    <a href="{{ url('/datalogistik') }}">📋 Full Data Logistik</a>

    <a href="{{ route('jess.gudang.ontime') }}">
        🏭 Gudang Ontime
    </a>

    <a href="{{ route('jess.gudang.delay') }}">
        🚨 Gudang Delay
    </a>

    <a href="{{ route('jess.customer.ontime') }}">
        🚚 Customer Ontime
    </a>

    <a href="{{ route('jess.customer.delay') }}">
        ⚠️ Customer Delay
    </a>

    <a href="{{ route('jess.bongkar.ontime') }}">
        📦 Bongkar Ontime
    </a>

    <a href="{{ route('jess.bongkar.delay') }}">
        ⏳ Bongkar Delay
    </a>

    <a href="{{ route('jess.summary.area') }}">
        🗺️ Summary Area
    </a>

    <a href="{{ route('jess.summary.total') }}">
        📑 Summary Total
    </a>
   

    <a href="{{ url('/storage') }}">
        🗄 Storage Archive
    </a>
    <li>
        <a href="{{ route('users.index') }}">
            👤 View Users
        </a>
    </li>




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
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', sans-serif;
    }

    .sidebar {
        width: 260px;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background: linear-gradient(180deg, #0f172a, #111827);
        padding: 20px 14px;
        overflow-y: auto;
        z-index: 999;
        box-shadow: 5px 0 20px rgba(0, 0, 0, .3);
    }

    .logo {
        color: #22c55e;
        font-size: 20px;
        font-weight: 800;
        text-align: center;
        padding: 12px;
        border-radius: 12px;
        background: rgba(34, 197, 94, .08);
        margin-bottom: 20px;
    }

    .menu {
        list-style: none;
    }

    .menu li {
        margin-bottom: 6px;
    }

    .title {
        color: #94a3b8;
        font-size: 11px;
        margin: 16px 8px 6px;
        letter-spacing: 1px;
    }

    .menu a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 11px 12px;
        border-radius: 10px;
        text-decoration: none;
        color: #e5e7eb;
        font-size: 14px;
        transition: .25s;
        position: relative;
        overflow: hidden;
    }

    .menu a::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        width: 0;
        height: 100%;
        background: linear-gradient(90deg, #22c55e, #3b82f6, #8b5cf6);
        transition: .3s;
        z-index: -1;
    }

    .menu a:hover::before {
        width: 100%;
    }

    .menu a:hover {
        color: #000;
        transform: translateX(6px);
    }

    .menu a.active {
        background: linear-gradient(90deg, #22c55e, #3b82f6);
        color: #000;
        font-weight: 600;
    }

    .logout-btn {
        width: 100%;
        padding: 12px;
        border: none;
        border-radius: 10px;
        background: linear-gradient(90deg, #ef4444, #f97316);
        color: #fff;
        font-weight: 700;
        cursor: pointer;
    }

    .logout-btn:hover {
        transform: scale(1.03);
    }

    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 10px;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 10px;
        justify-content: center;
        color: #22c55e;
        font-size: 18px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, .1);
    }

    .logo-img {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #22c55e;
    }
</style>