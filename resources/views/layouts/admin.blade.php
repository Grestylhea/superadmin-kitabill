<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - ISP Manager</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Vite CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')

    <!-- Custom Style for Hamburger -->
    <style>
        .hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            color: var(--text-color);
        }


        /* Mobile Responsive - Enhanced for Hamburger Menu */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                z-index: 1050 !important;
            }

            .sidebar.show {
                transform: translateX(0) !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1040 !important;
                opacity: 0;
                transition: opacity 0.3s ease-in-out;
            }

            .sidebar-overlay.show {
                display: block !important;
                opacity: 1 !important;
            }

            .hamburger {
                display: block !important;
                background: none;
                border: none;
                font-size: 1.75rem;
                cursor: pointer;
                padding: 0.5rem;
                color: #333;
                z-index: 1 !important;
                position: relative;
            }

            .hamburger:active {
                transform: scale(0.95);
                background-color: rgba(0, 0, 0, 0.05);
                border-radius: 0.25rem;
            }

            .topbar {
                z-index: 1030 !important;
                position: relative;
            }
        }

        @media (min-width: 769px) {
            .hamburger {
                display: none !important;
            }
        }

        }

        /* Hotspot Submenu Styling - Clean & Minimal */
        .submenu {
            padding-left: 0;
            background-color: transparent !important;
            border-left: none;
        }

        .submenu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem 0.75rem 2rem;
            color: #6c757d !important;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border-left: 3px solid transparent;
            background-color: transparent !important;
        }

        .submenu-item:hover {
            background-color: #f8f9fa !important;
            color: #495057 !important;
            border-left-color: #4e73df;
            text-decoration: none;
        }

        .submenu-item.active {
            background-color: #e3f2fd !important;
            color: #1976d2 !important;
            border-left-color: #1976d2;
            font-weight: 500;
        }

        .submenu-item i {
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .submenu-item-child {
            display: block;
            padding: 0.6rem 1rem 0.6rem 3.5rem;
            color: #6c757d !important;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            border-left: 2px solid transparent;
            background-color: transparent !important;
        }

        .submenu-item-child:hover {
            background-color: #f8f9fa !important;
            color: #495057 !important;
            border-left-color: #4e73df;
            padding-left: 3.7rem;
            text-decoration: none;
        }

        .submenu-item-child.active {
            background-color: #e7f1ff !important;
            color: #1976d2 !important;
            font-weight: 600;
            border-left-color: #1976d2;
        }

        .submenu-item-child i {
            margin-right: 0.5rem;
            font-size: 0.85rem;
        }

        .menu-item-group .menu-item {
            cursor: pointer;
        }

        .menu-item-group .menu-item i.ms-auto {
            transition: transform 0.3s ease;
            font-size: 0.8rem;
        }

        .menu-item-group .menu-item[aria-expanded="true"] i.ms-auto {
            transform: rotate(180deg);
        }

        .menu-item-group .menu-item[aria-expanded="false"] i.ms-auto {
            transform: rotate(0deg);
        }
    </style>
</head>

<body>
    <!-- Sidebar Overlay (untuk mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <h4 class="mb-0 fw-bold" style="color: var(--primary-color);">
                <i class="bi bi-wifi"></i> ISP MANAGER
            </h4>
        </div>

        <div class="sidebar-menu">
            <div class="px-3 mb-2">
                <small class="text-muted fw-semibold">MENU</small>
            </div>

            <a href="{{ route('dashboard') }}" class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            @can('view_users')
                <a href="{{ route('users.index') }}" class="menu-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i>
                    <span>User Management</span>
                </a>
            @endcan

            @can('view_customers')
                <a href="{{ route('customers.index') }}"
                    class="menu-item {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                    <i class="bi bi-person-badge"></i>
                    <span>Customers</span>
                </a>
            @endcan

            @can('view_packages')
                <a href="{{ route('packages.index') }}"
                    class="menu-item {{ request()->routeIs('packages.*') ? 'active' : '' }}">
                    <i class="bi bi-box-seam"></i>
                    <span>Packages</span>
                </a>
            @endcan

            @can('view_invoices')
                <a href="{{ route('invoices.index') }}"
                    class="menu-item {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                    <i class="bi bi-receipt"></i>
                    <span>Invoices</span>
                </a>
            @endcan


            @can('view_all_tickets')
                <a href="{{ route('tickets.index') }}"
                    class="menu-item {{ request()->routeIs('tickets.*') ? 'active' : '' }}">
                    <i class="bi bi-ticket-perforated"></i>
                    <span>Tickets</span>
                </a>
            @endcan

            <!-- Hotspot Menu - Uses redirect route to bypass JS interceptor -->
            <a href="{{ route('mikhmon.external') }}"
                class="menu-item {{ request()->routeIs('hotspot.*') ? 'active' : '' }}">
                <i class="bi bi-wifi"></i>
                <span>Hotspot</span>
            </a>

            <!-- Old Hotspot Submenu - Hidden (kept for reference, can be deleted later) -->
            <div class="menu-item-group" style="display: none;">
                <a href="javascript:void(0)" class="menu-item {{ request()->routeIs('hotspot.*') ? 'active' : '' }}"
                    onclick="toggleHotspotMenu('hotspotSubmenu', this)"
                    aria-expanded="{{ request()->routeIs('hotspot.*') ? 'true' : 'false' }}">
                    <i class="bi bi-wifi"></i>
                    <span>Hotspot (Old)</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse {{ request()->routeIs('hotspot.*') ? 'show' : '' }}" id="hotspotSubmenu">
                    <div class="submenu">
                        <!-- Users Submenu -->
                        <a href="javascript:void(0)" class="submenu-item"
                            onclick="toggleHotspotMenu('usersSubmenu', this)"
                            aria-expanded="{{ request()->routeIs('hotspot.users*') ? 'true' : 'false' }}">
                            <i class="bi bi-people"></i>
                            <span>Users</span>
                            <i class="bi bi-chevron-down ms-auto"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('hotspot.users*') ? 'show' : '' }}"
                            id="usersSubmenu">
                            <a href="{{ route('hotspot.users') }}"
                                class="submenu-item-child {{ request()->routeIs('hotspot.users') && !request()->routeIs('hotspot.users.create') ? 'active' : '' }}">
                                <i class="bi bi-list-ul"></i> User List
                            </a>
                            <a href="{{ route('hotspot.users.create') }}"
                                class="submenu-item-child {{ request()->routeIs('hotspot.users.create') ? 'active' : '' }}">
                                <i class="bi bi-person-plus"></i> Add User
                            </a>
                            <a href="{{ route('hotspot.generate') }}"
                                class="submenu-item-child {{ request()->routeIs('hotspot.generate') ? 'active' : '' }}">
                                <i class="bi bi-magic"></i> Generate
                            </a>
                        </div>

                        <!-- User Profile Submenu -->
                        <a href="javascript:void(0)" class="submenu-item"
                            onclick="toggleHotspotMenu('profileSubmenu', this)"
                            aria-expanded="{{ request()->routeIs('hotspot.profiles*') ? 'true' : 'false' }}">
                            <i class="bi bi-person-badge"></i>
                            <span>User Profile</span>
                            <i class="bi bi-chevron-down ms-auto"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('hotspot.profiles*') ? 'show' : '' }}"
                            id="profileSubmenu">
                            <a href="{{ route('hotspot.profiles') }}"
                                class="submenu-item-child {{ request()->routeIs('hotspot.profiles') ? 'active' : '' }}">
                                <i class="bi bi-list-ul"></i> Profile List
                            </a>
                        </div>

                        <!-- Direct Menu Items -->
                        <a href="{{ route('hotspot.active') }}"
                            class="submenu-item {{ request()->routeIs('hotspot.active') ? 'active' : '' }}">
                            <i class="bi bi-activity"></i> Hotspot Active
                        </a>

                        <a href="{{ route('hotspot.hosts') }}"
                            class="submenu-item {{ request()->routeIs('hotspot.hosts') ? 'active' : '' }}">
                            <i class="bi bi-pc-display"></i> Hosts
                        </a>

                        <a href="{{ route('hotspot.bindings') }}"
                            class="submenu-item {{ request()->routeIs('hotspot.bindings') ? 'active' : '' }}">
                            <i class="bi bi-link-45deg"></i> IP Bindings
                        </a>

                        <a href="{{ route('hotspot.cookies') }}"
                            class="submenu-item {{ request()->routeIs('hotspot.cookies') ? 'active' : '' }}">
                            <i class="bi bi-cookie"></i> Cookies
                        </a>

                        <a href="{{ route('hotspot.log') }}"
                            class="submenu-item {{ request()->routeIs('hotspot.log') ? 'active' : '' }}">
                            <i class="bi bi-clock-history"></i> Log
                        </a>

                        <!-- Report Submenu -->
                        <a href="javascript:void(0)" class="submenu-item"
                            onclick="toggleHotspotMenu('reportSubmenu', this)"
                            aria-expanded="{{ request()->routeIs('hotspot.report.*') ? 'true' : 'false' }}">
                            <i class="bi bi-graph-up"></i>
                            <span>Report</span>
                            <i class="bi bi-chevron-down ms-auto"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('hotspot.report.*') ? 'show' : '' }}"
                            id="reportSubmenu">
                            <a href="{{ route('hotspot.report.selling') }}"
                                class="submenu-item-child {{ request()->routeIs('hotspot.report.selling') ? 'active' : '' }}">
                                <i class="bi bi-cash-coin"></i> Selling Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-3 mt-4 mb-2">
                <small class="text-muted fw-semibold">NETWORK</small>
            </div>

            @can('view_routers')
                <a href="{{ route('routers.index') }}"
                    class="menu-item {{ request()->routeIs('routers.*') ? 'active' : '' }}">
                    <i class="bi bi-router"></i>
                    <span>Routers</span>
                </a>
            @endcan

            @can('view_olts')
                <a href="{{ route('olts.index') }}" class="menu-item {{ request()->routeIs('olts.*') ? 'active' : '' }}">
                    <i class="bi bi-hdd-network"></i>
                    <span>OLTs</span>
                </a>
            @endcan

            <a href="{{ route('odfs.index') }}" class="menu-item {{ request()->routeIs('odfs.*') ? 'active' : '' }}">
                <i class="bi bi-columns"></i>
                <span>ODF (Patch Panel)</span>
            </a>

            <a href="{{ route('odcs.index') }}" class="menu-item {{ request()->routeIs('odcs.*') ? 'active' : '' }}">
                <i class="bi bi-server"></i>
                <span>ODC (Cabinet)</span>
            </a>

            <a href="{{ route('splitters.index') }}"
                class="menu-item {{ request()->routeIs('splitters.*') ? 'active' : '' }}">
                <i class="bi bi-shuffle"></i>
                <span>Splitters</span>
            </a>

            <a href="{{ route('odps.index') }}" class="menu-item {{ request()->routeIs('odps.*') ? 'active' : '' }}">
                <i class="bi bi-box"></i>
                <span>ODP (Distribution)</span>
            </a>

            <a href="{{ route('onts.index') }}" class="menu-item {{ request()->routeIs('onts.*') ? 'active' : '' }}">
                <i class="bi bi-modem"></i>
                <span>ONTs (Customer)</span>
            </a>

            <a href="{{ route('switches.index') }}"
                class="menu-item {{ request()->routeIs('switches.*') ? 'active' : '' }}">
                <i class="bi bi-diagram-3"></i>
                <span>Switches</span>
            </a>

            <a href="{{ route('access-points.index') }}"
                class="menu-item {{ request()->routeIs('access-points.*') ? 'active' : '' }}">
                <i class="bi bi-wifi"></i>
                <span>Access Points</span>
            </a>

            <div class="menu-item" style="cursor: pointer;" onclick="toggleSubmenu('fiberInfra')">
                <i class="bi bi-bezier2"></i>
                <span>Fiber Infrastructure</span>
                <i class="bi bi-chevron-down ms-auto" id="fiberInfraChevron"></i>
            </div>

            <div id="fiberInfraSubmenu" style="display: none; padding-left: 1rem;">
                <a href="{{ route('joint-boxes.index') }}"
                    class="menu-item {{ request()->routeIs('joint-boxes.*') ? 'active' : '' }}">
                    <i class="bi bi-box-seam"></i>
                    <span>Joint Boxes</span>
                </a>

                <a href="{{ route('cable-segments.index') }}"
                    class="menu-item {{ request()->routeIs('cable-segments.*') ? 'active' : '' }}">
                    <i class="bi bi-bezier"></i>
                    <span>Cable Segments</span>
                </a>

                <a href="{{ route('cores.index') }}"
                    class="menu-item {{ request()->routeIs('cores.*') ? 'active' : '' }}">
                    <i class="bi bi-diagram-2"></i>
                    <span>Fiber Cores</span>
                </a>

                <a href="{{ route('fiber-splices.index') }}"
                    class="menu-item {{ request()->routeIs('fiber-splices.*') ? 'active' : '' }}">
                    <i class="bi bi-link-45deg"></i>
                    <span>Fiber Splices</span>
                </a>

                <a href="{{ route('fiber-test-results.index') }}"
                    class="menu-item {{ request()->routeIs('fiber-test-results.*') ? 'active' : '' }}">
                    <i class="bi bi-clipboard-data"></i>
                    <span>Test Results (OTDR)</span>
                </a>
            </div>

            <div class="menu-item" style="cursor: pointer;" onclick="toggleSubmenu('acsMenu')">
                <i class="bi bi-sliders"></i>
                <span>ACS Management</span>
                @php
                    $alertCount = \App\Models\AcsAlert::where('status', 'new')->count();
                @endphp
                @if($alertCount > 0)
                    <span class="badge bg-danger">{{ $alertCount }}</span>
                @endif
                <i class="bi bi-chevron-down ms-auto" id="acsMenuChevron"></i>
            </div>

            <div id="acsMenuSubmenu"
                style="display: {{ request()->routeIs('acs.*') ? 'block' : 'none' }}; padding-left: 1rem;">
                <a href="{{ route('acs.index') }}"
                    class="menu-item {{ request()->routeIs('acs.index') ? 'active' : '' }}">
                    <i class="bi bi-hdd-network"></i>
                    <span>Devices</span>
                </a>

                <a href="{{ route('acs.templates.index') }}"
                    class="menu-item {{ request()->routeIs('acs.templates.*') ? 'active' : '' }}">
                    <i class="bi bi-file-text"></i>
                    <span>Templates</span>
                </a>

                <a href="{{ route('acs.bulk.index') }}"
                    class="menu-item {{ request()->routeIs('acs.bulk.*') ? 'active' : '' }}">
                    <i class="bi bi-stack"></i>
                    <span>Bulk Operations</span>
                </a>

                <a href="{{ route('acs.alerts.index') }}"
                    class="menu-item {{ request()->routeIs('acs.alerts.*') ? 'active' : '' }}">
                    <i class="bi bi-bell"></i>
                    <span>Alerts</span>
                    @if($alertCount > 0)
                        <span class="badge bg-danger ms-2">{{ $alertCount }}</span>
                    @endif
                </a>

                <a href="{{ route('acs.alert-rules.index') }}"
                    class="menu-item {{ request()->routeIs('acs.alert-rules.*') ? 'active' : '' }}">
                    <i class="bi bi-gear"></i>
                    <span>Alert Rules</span>
                </a>

                <a href="{{ route('acs.provisioning.queue') }}"
                    class="menu-item {{ request()->routeIs('acs.provisioning.*') ? 'active' : '' }}">
                    <i class="bi bi-hourglass-split"></i>
                    <span>Provisioning Queue</span>
                    @php
                        $queueCount = \App\Models\AcsProvisioningQueue::where('status', 'pending')->count();
                    @endphp
                    @if($queueCount > 0)
                        <span class="badge bg-info ms-2">{{ $queueCount }}</span>
                    @endif
                </a>

                <a href="{{ route('acs.statistics') }}"
                    class="menu-item {{ request()->routeIs('acs.statistics') ? 'active' : '' }}">
                    <i class="bi bi-graph-up"></i>
                    <span>Statistics</span>
                </a>

                <a href="{{ route('acs.unprovisioned') }}"
                    class="menu-item {{ request()->routeIs('acs.unprovisioned') ? 'active' : '' }}">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Unprovisioned</span>
                </a>
            </div>

            <a href="{{ route('network.topology') }}"
                class="menu-item {{ request()->routeIs('network.topology') ? 'active' : '' }}">
                <i class="bi bi-diagram-3"></i>
                <span>Network Topology</span>
            </a>

            <a href="{{ route('map.index') }}" class="menu-item {{ request()->routeIs('map.*') ? 'active' : '' }}">
                <i class="bi bi-map"></i>
                <span>Network Map</span>
            </a>

            <div class="px-3 mt-4 mb-2">
                <small class="text-muted fw-semibold">LAPORAN</small>
            </div>

            @can('view_financial_reports')
                <a href="{{ route('reports.index') }}"
                    class="menu-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <i class="bi bi-bar-chart"></i>
                    <span>Reports</span>
                </a>
            @endcan

            <div class="px-3 mt-4 mb-2">
                <small class="text-muted fw-semibold">PENGATURAN</small>
            </div>

            @can('view_settings')
                <a href="{{ route('settings.index') }}"
                    class="menu-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            @endcan

            <a href="{{ route('profile.edit') }}" class="menu-item">
                <i class="bi bi-person-circle"></i>
                <span>Profile</span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="menu-item border-0 bg-transparent w-100 text-start">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="d-flex align-items-center gap-3">
                <!-- Hamburger Menu (Mobile) -->
                <button class="hamburger" id="sidebarToggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <h5 class="mb-0 fw-bold">@yield('page-title', 'Dashboard')</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span
                    class="text-muted d-none d-md-inline">{{ auth()->check() ? auth()->user()->getRoleNames()->first() : '' }}</span>
                @php
                    $user = auth()->user();
                @endphp

                <div class="d-flex align-items-center gap-2">
                    @if($user && $user->photo)
                        <img src="{{ asset('storage/' . $user->photo) }}" alt="User" class="rounded-circle" width="40"
                            height="40">
                    @else
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px;">
                            {{ $user ? substr($user->name, 0, 1) : '?' }}
                        </div>
                    @endif
                </div>

            </div>
        </div>

        <!-- Content -->
        <div class="content-wrapper">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle"></i> {{ session('info') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </div>

    <!-- ✅ JQUERY - HARUS LOAD PERTAMA SEBELUM SCRIPT LAIN -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- ✅ USER SCRIPTS (AJAX, dll) -->
    @stack('scripts')

    <!-- Main Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            // Toggle sidebar
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    sidebar.classList.toggle('show');
                    sidebarOverlay.classList.toggle('show');
                });
            }

            // Close sidebar when overlay clicked
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function () {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                });
            }

            // Close sidebar when menu item clicked (mobile only)
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function () {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('show');
                        sidebarOverlay.classList.remove('show');
                    }
                });
            });
        });

        // Toggle submenu function
        function toggleSubmenu(menuId) {
            const submenu = document.getElementById(menuId + 'Submenu');
            const chevron = document.getElementById(menuId + 'Chevron');

            if (submenu && chevron) {
                if (submenu.style.display === 'none' || submenu.style.display === '') {
                    submenu.style.display = 'block';
                    chevron.classList.remove('bi-chevron-down');
                    chevron.classList.add('bi-chevron-up');
                } else {
                    submenu.style.display = 'none';
                    chevron.classList.remove('bi-chevron-up');
                    chevron.classList.add('bi-chevron-down');
                }
            }
        }

        // Auto expand if current route is in submenu
        document.addEventListener('DOMContentLoaded', function () {
            const currentRoute = '{{ request()->route()->getName() }}';

            // Fiber Infrastructure submenu routes
            const fiberRoutes = [
                'joint-boxes.',
                'cable-segments.',
                'cores.',
                'fiber-splices.',
                'fiber-test-results.'
            ];

            if (fiberRoutes.some(route => currentRoute.startsWith(route))) {
                const submenu = document.getElementById('fiberInfraSubmenu');
                const chevron = document.getElementById('fiberInfraChevron');
                if (submenu && chevron) {
                    submenu.style.display = 'block';
                    chevron.classList.remove('bi-chevron-down');
                    chevron.classList.add('bi-chevron-up');
                }
            }
        });


        // Simple toggle function for hotspot menu
        function toggleHotspotMenu(menuId, element) {
            const submenu = document.getElementById(menuId);
            const chevron = element.querySelector('i.ms-auto');

            if (submenu) {
                if (submenu.classList.contains('show')) {
                    // Close menu
                    submenu.classList.remove('show');
                    if (chevron) {
                        element.setAttribute('aria-expanded', 'false');
                    }
                } else {
                    // Open menu
                    submenu.classList.add('show');
                    if (chevron) {
                        element.setAttribute('aria-expanded', 'true');
                    }
                }
            }
        }

            // Hotspot menu handled by toggleHotspotMenu() function
            // Using onclick handlers instead of Bootstrap Collapse instances
        });

    </script>

    <!-- Global Menu Functions -->
    <script>
        // Make functions global so onclick handlers work
        window.toggleHotspotMenu = function (menuId, element) {
            const submenu = document.getElementById(menuId);
            if (submenu) {
                submenu.classList.toggle('show');
                const chevron = element.querySelector('i.ms-auto');
                if (chevron) {
                    element.setAttribute('aria-expanded',
                        submenu.classList.contains('show') ? 'true' : 'false');
                }
            }
        };

        window.toggleSidebar = function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        };
    </script>

    <!-- ✅ Keep-alive untuk dynamic sync frequency -->
    <!-- ✅ Jika billing dibuka → sync setiap 1 menit (real-time) -->
    <!-- ✅ Jika billing tidak dibuka selama 2 menit → sync kembali ke 5 menit -->
    <script>
        (function () {
            let keepAliveInterval = null;
            let isPageVisible = true;

            // ✅ Fungsi untuk send keep-alive
            function sendKeepAlive() {
                fetch('{{ route("api.sync.keep-alive") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('✅ Keep-alive sent - Sync mode: ' + data.sync_mode);
                        }
                    })
                    .catch(error => {
                        console.warn('⚠️ Keep-alive failed:', error);
                    });
            }

            // ✅ Start keep-alive saat halaman load
            function startKeepAlive() {
                // Send immediately saat halaman load
                sendKeepAlive();

                // Set interval setiap 30 detik
                if (keepAliveInterval) {
                    clearInterval(keepAliveInterval);
                }
                keepAliveInterval = setInterval(() => {
                    if (isPageVisible) {
                        sendKeepAlive();
                    }
                }, 30000); // 30 detik
            }

            // ✅ Stop keep-alive saat halaman unload
            function stopKeepAlive() {
                if (keepAliveInterval) {
                    clearInterval(keepAliveInterval);
                    keepAliveInterval = null;
                }
            }

            // ✅ Handle page visibility (tab switch)
            document.addEventListener('visibilitychange', function () {
                isPageVisible = !document.hidden;
                if (isPageVisible) {
                    // Tab kembali aktif → send keep-alive langsung
                    sendKeepAlive();
                    startKeepAlive();
                } else {
                    // Tab tidak aktif → stop keep-alive
                    stopKeepAlive();
                }
            });

            // ✅ Start saat DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startKeepAlive);
            } else {
                startKeepAlive();
            }

            // ✅ Stop saat halaman unload
            window.addEventListener('beforeunload', stopKeepAlive);
            window.addEventListener('pagehide', stopKeepAlive);
        })();
    </script>

</body>

</html>