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
'admin_pasuruan' => route('pasuruan.admin'),
default => url('/dashboard'),
};

/*
|-------------------------------------------------
| ACTIVE MENU HELPER
|-------------------------------------------------
*/
$currentRoute = request()->route()->getName() ?? '';
@endphp
<link rel="stylesheet" href="{{ asset('css/app-font.css') }}">
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

      

      
        <li><a href="{{ url('/datalogistik') }}">📋 Full Data Logistik Jakarta</a></li>
        <li><a href="{{ route('planner.datalogistik') }}">📦 Data Planner</a></li>
        <li><a href="{{ route('planner.sla.ontime') }}">✅ Sudah Tiba Gudang</a></li>
        <li><a href="{{ route('planner.sla.delay') }}">❌ Belum Tiba Gudang</a></li>
        <li><a href="{{ route('armada') }}">🚚 Armada Ready</a></li>
         <!-- <li><a href="{{ route('armada.delay') }}">🚚 Armada delay</a></li> -->
  <li><a href="{{ route('planner.belum_armada') }}">⏳ Belum Armada</a></li>

        @endif

        {{-- ================= SPV PLANNER ================= --}}
        @if($role === 'spvplanner' || $role === 'developer')

      
<li>
    <a href="javascript:void(0)"
       onclick="toggleSubmenu('dashboardSubmenu', this)"
       class="submenu-toggle {{ request()->routeIs('spvplanner.dashboard*') ? 'active' : '' }}">
        <span>📊 Dashboard</span>
        <i class="arrow {{ request()->routeIs('spvplanner.dashboard*') ? 'open' : '' }}">▾</i>
    </a>

    <ul class="submenu" id="dashboardSubmenu"
        style="display: {{ request()->routeIs('spvplanner.dashboard*') ? 'block' : 'none' }};">

        <li>
        <a href="{{ route('spvplanner.full.dashboard') }}"
   class="{{ request()->routeIs('spvplanner.full.dashboard') ? 'active' : '' }}">
    🏢 Dashboard Jakarta
</a>
        </li>

        <li>
            <a href="{{ route('spvplanner.dashboard.pasuruan') }}"
               class="{{ request()->routeIs('spvplanner.dashboard.pasuruan') ? 'active' : '' }}">
                🏭 Dashboard Pasuruan
            </a>
        </li>

    </ul>
</li>

 <li class="nav-item">
        <a href="{{ route('spvplanner.tarif.index') }}"
           class="nav-link">

            <i class="nav-icon fas fa-money-bill-wave"></i>

            <p>
                Tarif Pengiriman
            </p>

        </a>
    </li>
 <li class="nav-item">
    <a href="{{ route('spvplanner.tujuan.index') }}"
       class="{{ request()->routeIs('spvplanner.tujuan.*') ? 'active' : '' }}">
        <span>📍 Tujuan Filter</span>
    </a>
</li>
<li>
    <a href="javascript:void(0)"
       onclick="toggleSubmenu('dataLogistikSubmenu', this)"
       class="submenu-toggle {{ request()->routeIs('spvplanner.data*') ? 'active' : '' }}">
        <span>📋 Data Logistik</span>
        <i class="arrow {{ request()->routeIs('spvplanner.data*') ? 'open' : '' }}">▾</i>
    </a>

    <ul class="submenu" id="dataLogistikSubmenu"
        style="display: {{ request()->routeIs('spvplanner.data*') ? 'block' : 'none' }};">
<li><a href="{{ route('full.data.logistik') }}">📋 Full Data Logistik Jakarta</a></li>
        <li>
            <a href="{{ route('spvplanner.data.pasuruan') }}"
               class="{{ request()->routeIs('spvplanner.data.pasuruan') ? 'active' : '' }}">
                🏭 Full Data Logistik Pasuruan
            </a>
        </li>

    </ul>
</li>

        <li>
            <a href="{{ route('spvplanner.datalogistik', request()->query()) }}">
                🗂️ Data Planner
            </a>
        </li>

        <li>
            <a href="{{ route('spvplanner.sla.ontime', request()->query()) }}">
                ✅ Sudah Tiba Di Gudang 
            </a>
        </li>

        <li>
            <a href="{{ route('spvplanner.sla.delay', request()->query()) }}">
                ❌ Belum Tiba Di Gudang 
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

       

     

        <li><a href="{{ url('/datalogistik') }}">📋 Full Data Logistik Jakarta</a></li>
        <li><a href="{{ route('monitoring.datalogistik') }}">🚚 Data Monitoring</a></li>
        <li><a href="{{ route('monitoring.sla.ontime') }}">✅ Customer On Time</a></li>
        <li><a href="{{ route('monitoring.sla.delay') }}">❌ Customer Delay</a></li>
        <li><a href="{{ route('monitoring.bongkar.ontime') }}">📦 Bongkar On Time</a></li>
        <li><a href="{{ route('monitoring.bongkar.delay') }}">📦 Bongkar Delay</a></li>
        <li><a href="{{ route('monitoring.summary.area') }}">🌍 Summary Area</a></li>

        @endif

        {{-- ================= SPV MONITORING ================= --}}
        @if($role === 'spvmonitoring' || $role === 'developer')

     


 <li>
    <a href="javascript:void(0)"
       onclick="toggleSubmenu('dashboardSubmenu', this)"
       class="submenu-toggle {{ request()->routeIs('spvmonitoring.dashboard*') ? 'active' : '' }}">
        <span>📊 Dashboard</span>
        <i class="arrow {{ request()->routeIs('spvmonitoring.dashboard*') ? 'open' : '' }}">▾</i>
    </a>

    <ul class="submenu" id="dashboardSubmenu"
        style="display: {{ request()->routeIs('spvmonitoring.dashboard*') ? 'block' : 'none' }};">

    
        <li>
        <a href="{{ route('spvmonitoring.full.dashboard') }}"
   class="{{ request()->routeIs('spvmonitoring.full.dashboard') ? 'active' : '' }}">
    🏢 Dashboard Jakarta
</a>
        </li>

        <li>
            <a href="{{ route('spvmonitoring.dashboard.pasuruan') }}"
               class="{{ request()->routeIs('spvmonitoring.dashboard.pasuruan') ? 'active' : '' }}">
                🏭 Dashboard Pasuruan
            </a>
        </li>

    </ul>
</li>

 <li class="nav-item">
    <a href="{{ route('spvmonitoring.tujuan.index') }}"
       class="{{ request()->routeIs('spvmonitoring.tujuan.*') ? 'active' : '' }}">
        <span>📍 Tujuan Filter</span>
    </a>
</li>
<li>
    <a href="javascript:void(0)"
       onclick="toggleSubmenu('dataLogistikSubmenu', this)"
       class="submenu-toggle {{ request()->routeIs('spvmonitoring.data*') ? 'active' : '' }}">
        <span>📋 Data Logistik</span>
        <i class="arrow {{ request()->routeIs('spvmonitoring.data*') ? 'open' : '' }}">▾</i>
    </a>

    <ul class="submenu" id="dataLogistikSubmenu"
        style="display: {{ request()->routeIs('spvmonitoring.data*') ? 'block' : 'none' }};">

         <li>
            <a href="{{ url('/datalogistik') . '?' . http_build_query(request()->query()) }}">
                📦 Full Data Logistik Jakarta
            </a>
        </li>

        <li>
            <a href="{{ route('spvmonitoring.data.pasuruan') }}"
               class="{{ request()->routeIs('spvmonitoring.data.pasuruan') ? 'active' : '' }}">
                🏭 Full Data Logistik Pasuruan
            </a>
        </li>

    </ul>
</li>
        <li> <a href="{{ route('spvmonitoring.datalogistik', request()->query()) }}"> 📦 Data Monitoring </a> </li>
        <li> <a href="{{ route('spvmonitoring.sla.ontime', request()->query()) }}"> ✅ SLA Tiba Ontime </a> </li>
        <li> <a href="{{ route('spvmonitoring.sla.delay', request()->query()) }}"> ❌ SLA Tiba Delay </a> </li>
        <li> <a href="{{ route('spvmonitoring.bongkar.ontime', request()->query()) }}"> 📥 Bongkar Ontime </a> </li>
        <li> <a href="{{ route('spvmonitoring.bongkar.delay', request()->query()) }}"> 🚨 Bongkar Delay </a> </li>
        <li> <a href="{{ route('spvmonitoring.summary.area', request()->query()) }}"> 🌍 Summary Area </a> </li>

        @endif

        {{-- ================= MANAGER ================= --}}
    {{-- ================= MANAGER ================= --}}
@if($role === 'manager' || $role === 'developer')



{{-- DASHBOARD DROPDOWN --}}
<li>
    <a href="javascript:void(0)"
       onclick="toggleSubmenu('dashboardSubmenu', this)"
       class="submenu-toggle {{ request()->routeIs('manager.dashboard*') ? 'active' : '' }}">
        <span>📊 Dashboard</span>
        <i class="arrow {{ request()->routeIs('manager.dashboard*') ? 'open' : '' }}">▾</i>
    </a>

    <ul class="submenu" id="dashboardSubmenu"
        style="display: {{ request()->routeIs('manager.dashboard*') ? 'block' : 'none' }};">
        <li>
            <a href="{{ route('manager.dashboard') }}"
               class="{{ request()->routeIs('manager.dashboard') && !request()->routeIs('manager.dashboard.pasuruan') ? 'active' : '' }}">
                🏢 Dashboard Jakarta 
            </a>
        </li>
        <li>
            <a href="{{ route('manager.dashboard.pasuruan') }}"
               class="{{ request()->routeIs('manager.dashboard.pasuruan') ? 'active' : '' }}">
                🏭 Dashboard Pasuruan
            </a>
        </li>
    </ul>
</li>

{{-- DATA LOGISTIK DROPDOWN --}}
<li>
    <a href="javascript:void(0)"
       onclick="toggleSubmenu('dataLogistikSubmenu', this)"
       class="submenu-toggle {{ request()->routeIs('manager.data.pasuruan') || request()->is('datalogistik') ? 'active' : '' }}">
        <span>📋 Data Logistik</span>
        <i class="arrow {{ request()->routeIs('manager.data.pasuruan') || request()->is('datalogistik') ? 'open' : '' }}">▾</i>
    </a>

    <ul class="submenu" id="dataLogistikSubmenu"
        style="display: {{ request()->routeIs('manager.data.pasuruan') || request()->is('datalogistik') ? 'block' : 'none' }};">
        <li>
            <a href="{{ url('/datalogistik') }}" class="{{ request()->is('datalogistik') ? 'active' : '' }}">
                🏢 Full Data Logistik jakarta
            </a>
        </li>
        <li>
            <a href="{{ route('manager.data.pasuruan') }}" class="{{ request()->routeIs('manager.data.pasuruan') ? 'active' : '' }}">
                🏭 Full Data Logistik Pasuruan
            </a>
        </li>
    </ul>
</li>

<li>
    <a href="{{ route('manager.gudang.ontime') }}">
        🏭 Sudah Tiba Di Gudang
    </a>
</li>

<li>
    <a href="{{ route('manager.gudang.delay') }}">
        🚨 Belum Tiba Di Gudang
    </a>
</li>

<li>
    <a href="{{ route('manager.customer.ontime') }}">
        🚚 Customer Ontime
    </a>
</li>

<li>
    <a href="{{ route('manager.customer.delay') }}">
        ⚠️ Customer Delay
    </a>
</li>

<li>
    <a href="{{ route('manager.bongkar.ontime') }}">
        📦 Bongkar Ontime
    </a>
</li>

<li>
    <a href="{{ route('manager.bongkar.delay') }}">
        ⏳ Bongkar Delay
    </a>
</li>

<li>
    <a href="{{ route('manager.summary.area') }}">
        🗺️ Summary Area
    </a>
</li>

<li>
    <a href="{{ route('manager.summary.total') }}">
        📑 Summary Total
    </a>
</li>

<li>
    <a href="{{ url('/storage') }}">
        🗄 Storage Archive
    </a>
</li>

<li>
    <a href="{{ route('users.index') }}">
        👤 View Users
    </a>
</li>

@endif
    

                {{-- ================= MANAGER ================= --}}
      @if($role === 'cmd' || $role === 'developer')

    <a href="{{ url('/datalogistik') }}">📋 Full Data Logistik Jakarta</a>

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

           @if($role === 'sales' || $role === 'developer')

   <li>
    <a href="javascript:void(0)"
       onclick="toggleSubmenu('dashboardSubmenu', this)"
       class="submenu-toggle {{ request()->routeIs('sales.dashboard*') ? 'active' : '' }}">
        <span>📊 Dashboard</span>
        <i class="arrow {{ request()->routeIs('sales.dashboard*') ? 'open' : '' }}">▾</i>
    </a>

    <ul class="submenu" id="dashboardSubmenu"
        style="display: {{ request()->routeIs('sales.dashboard*') ? 'block' : 'none' }};">

        <li>
            <a href="{{ route('sales.dashboard') }}"
               class="{{ request()->routeIs('sales.dashboard') ? 'active' : '' }}">
                🏢 Dashboard Jakarta
            </a>
        </li>

        <li>
            <a href="{{ route('sales.dashboard.pasuruan') }}"
               class="{{ request()->routeIs('sales.dashboard.pasuruan') ? 'active' : '' }}">
                🏭 Dashboard Pasuruan
            </a>
        </li>

    </ul>
</li>

<li>
    <a href="javascript:void(0)"
       onclick="toggleSubmenu('dataLogistikSubmenu', this)"
       class="submenu-toggle {{ request()->routeIs('sales.data*') ? 'active' : '' }}">
        <span>📋 Data Logistik</span>
        <i class="arrow {{ request()->routeIs('sales.data*') ? 'open' : '' }}">▾</i>
    </a>

    <ul class="submenu" id="dataLogistikSubmenu"
        style="display: {{ request()->routeIs('sales.data*') ? 'block' : 'none' }};">

        <li>
             <a href="{{ url('/datalogistik') }}" class="{{ request()->is('datalogistik') ? 'active' : '' }}">
                🏢 Full Data Logistik jakarta
            </a>
        </li>

        <li>
            <a href="{{ route('sales.data.pasuruan') }}"
               class="{{ request()->routeIs('sales.data.pasuruan') ? 'active' : '' }}">
                🏭 Full Data Logistik Pasuruan
            </a>
        </li>

    </ul>
</li>

    <a href="{{ route('sales.gudang.ontime') }}">
        🏭 Sudah Tiba Di Gudang
    </a>

    <a href="{{ route('sales.gudang.delay') }}">
        🚨 Belum Tiba Di Gudang
    </a>

    <a href="{{ route('sales.customer.ontime') }}">
        🚚 Customer Ontime
    </a>

    <a href="{{ route('sales.customer.delay') }}">
        ⚠️ Customer Delay
    </a>

    <a href="{{ route('sales.bongkar.ontime') }}">
        📦 Bongkar Ontime
    </a>

    <a href="{{ route('sales.bongkar.delay') }}">
        ⏳ Bongkar Delay
    </a>





        @endif

{{-- ================= PASURUAN ================= --}}
@if($role === 'admin_pasuruan' || $role === 'developer')

<li class="{{ request()->routeIs('pasuruan.dashboard') ? 'active' : '' }}">
    <a href="{{ route('pasuruan.dashboard') }}">
        <i class="fas fa-chart-line"></i>
        <span>Dashboard</span>
    </a>
</li>

<li>
    <a href="{{ route('pasuruan.admin') }}">
        📝 Data Admin
    </a>
</li>

<li>
    <a href="{{ route('pasuruan.dataLogistik') }}">
        🚚 Data Logistik
    </a>
</li>

<li class="{{ request()->routeIs('pasuruan.gudang.ontime') ? 'active' : '' }}">
    <a href="{{ route('pasuruan.gudang.ontime') }}">
        🏭 Sudah Tiba Di Gudang
    </a>
</li>

<li class="{{ request()->routeIs('pasuruan.gudang.delay') ? 'active' : '' }}">
    <a href="{{ route('pasuruan.gudang.delay') }}">
        🚨 Belum Tiba Di Gudang
    </a>
</li>

<li class="{{ request()->routeIs('pasuruan.tujuan.ontime') ? 'active' : '' }}">
    <a href="{{ route('pasuruan.tujuan.ontime') }}">
        🚚 Customer Ontime
    </a>
</li>

<li class="{{ request()->routeIs('pasuruan.tujuan.delay') ? 'active' : '' }}">
    <a href="{{ route('pasuruan.tujuan.delay') }}">
        ⚠️ Customer Delay
    </a>
</li>

<li class="{{ request()->routeIs('pasuruan.bongkar.ontime') ? 'active' : '' }}">
    <a href="{{ route('pasuruan.bongkar.ontime') }}">
        📦 Bongkar Ontime
    </a>
</li>

<li class="{{ request()->routeIs('pasuruan.bongkar.delay') ? 'active' : '' }}">
    <a href="{{ route('pasuruan.bongkar.delay') }}">
        ⏳ Bongkar Delay
    </a>
</li>

@endif        {{-- ================= ACCOUNT ================= --}}
    

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
/* =========================
   RESET
========================= */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', sans-serif;
}

/* =========================
   SIDEBAR WRAPPER
========================= */
.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;

    /* 🔥 CERAH BARU */
    background: #0040c9;

    padding: 20px 14px;
    overflow-y: auto;
    z-index: 999;

    box-shadow: 4px 0 20px rgba(37, 99, 235, 0.25);
    transition: all 0.3s ease;
}

/* =========================
   LOGO
========================= */
.logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;

    color: #ffffff;
    font-size: 18px;
    font-weight: 700;

    padding: 12px;
    margin-bottom: 25px;

    border-radius: 12px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);

    border: 1px solid rgba(255, 255, 255, 0.2);
}

.logo-img {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
}

/* =========================
   MENU
========================= */
.menu {
    list-style: none;
}

.menu li {
    margin-bottom: 6px;
}

.title {
    color: rgba(255, 255, 255, 0.75);
    font-size: 11px;
    margin: 16px 8px 6px;
    letter-spacing: 1px;
}

/* =========================
   MENU LINK
========================= */
.menu a {
    display: flex;
    align-items: center;
    gap: 10px;

    padding: 11px 12px;
    border-radius: 10px;

    text-decoration: none;
    color: #ffffff;
    font-size: 14px;

    position: relative;
    overflow: hidden;

    transition: all 0.25s ease;
}

/* hover background glow */
.menu a::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 0%;
    height: 100%;

    background: rgba(255, 255, 255, 0.25);
    transition: 0.3s ease;
    z-index: 0;
}

.menu a:hover::before {
    width: 100%;
}

.menu a:hover {
    transform: translateX(6px);
    color: #0f172a;
    font-weight: 600;
}

/* active menu */
.menu a.active {
    background: rgba(255, 255, 255, 0.3);
    color: #0f172a;
    font-weight: 700;
}

/* =========================
   LOGOUT BUTTON
========================= */
.logout-btn {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 10px;

    background: linear-gradient(90deg, #ef4444, #f97316);
    color: #fff;
    font-weight: 700;

    cursor: pointer;
    transition: 0.2s ease;
}

.logout-btn:hover {
    transform: scale(1.05);
    opacity: 0.9;
}

/* =========================
   SCROLLBAR
========================= */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.4);
    border-radius: 10px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

/* =========================
   SUBMENU DROPDOWN
========================= */
.submenu-toggle {
    justify-content: space-between;
}

.submenu-toggle .arrow {
    font-style: normal;
    transition: transform 0.25s ease;
    font-size: 12px;
}

.submenu-toggle .arrow.open {
    transform: rotate(180deg);
}

.menu ul.submenu {
    list-style: none;
    padding-left: 0;
    margin-top: 2px;
    margin-bottom: 6px;
}

.menu ul.submenu li {
    margin-bottom: 4px;
}

.menu ul.submenu a {
    padding-left: 32px;
    font-size: 13px;
    opacity: 0.92;
}
</style>

<script>
function toggleSubmenu(id, el) {
    const submenu = document.getElementById(id);
    const isOpen = submenu.style.display === 'block';

    submenu.style.display = isOpen ? 'none' : 'block';

    const arrow = el.querySelector('.arrow');
    if (arrow) {
        arrow.classList.toggle('open', !isOpen);
    }
}
</script>