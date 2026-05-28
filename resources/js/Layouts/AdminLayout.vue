<template>
    <div class="admin-layout">
        <div class="sidebar-overlay" :class="{ 'show': sidebarOpen }" @click="closeSidebar"></div>
        
        <!-- Sidebar -->
        <div class="sidebar" :class="{ 'show': sidebarOpen }">
            <div class="sidebar-header">
                <div class="logo">
                    <img 
                        v-if="$page.props.company_logo" 
                        :src="`/storage/${$page.props.company_logo}`" 
                        alt="Company Logo" 
                        class="img-fluid" 
                        style="max-height: 40px; max-width: 100%; width: auto; height: auto; object-fit: contain;">
                    <div 
                        v-else 
                        class="d-flex align-items-center justify-content-center bg-primary text-white rounded px-3 py-2"
                        style="min-height: 40px; min-width: 120px;">
                        <strong class="mb-0">KITABILL</strong>
                    </div>
                </div>

            </div>

            <div class="sidebar-menu">
                <div class="px-3 mb-2">
                    <small class="text-muted fw-semibold">MENU UTAMA</small>
                </div>

                <Link :href="getRoute('dashboard')" class="menu-item" :class="{ active: $page.url === '/dashboard' }">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </Link>

                <Link :href="getRoute('customers.index')" class="menu-item" :class="{ active: $page.url.startsWith('/customers') }">
                    <i class="bi bi-people-fill"></i>
                    <span>Customers</span>
                </Link>

                <Link :href="getRoute('packages.index')" class="menu-item" :class="{ active: $page.url.startsWith('/packages') }">
                    <i class="bi bi-box-seam"></i>
                    <span>Packages</span>
                </Link>

                <Link :href="getRoute('invoices.index')" class="menu-item" :class="{ active: $page.url.startsWith('/invoices') }">
                    <i class="bi bi-receipt"></i>
                    <span>Invoices</span>
                </Link>

                <Link :href="getRoute('tickets.index')" class="menu-item" :class="{ active: $page.url.startsWith('/tickets') }">
                    <i class="bi bi-ticket-perforated"></i>
                    <span>Tickets</span>
                </Link>

                <Link :href="'/hotspot'" class="menu-item" :class="{ active: $page.url.startsWith('/hotspot') }">
                    <i class="bi bi-wifi"></i>
                    <span>Hotspot</span>
                </Link>

                <div class="px-3 mt-4 mb-2">
                    <small class="text-muted fw-semibold">NETWORK</small>
                </div>

                <Link :href="getRoute('routers.index')" class="menu-item" :class="{ active: $page.url.startsWith('/routers') }">
                    <i class="bi bi-router"></i>
                    <span>Routers & NAS</span>
                </Link>

                <Link :href="getRoute('ip-pools.index')" class="menu-item" :class="{ active: $page.url.startsWith('/ip-pools') }">
                    <i class="bi bi-hdd-network"></i>
                    <span>IP Pools</span>
                </Link>

                <!-- Fiber Infrastructure Submenu -->
                <div class="menu-item-group">
                    <div class="menu-item nested-header" @click="toggleSubmenu('fiberInfraSubmenu')" :class="{ active: isFiberInfraActive() }">
                        <i class="bi bi-diagram-2"></i>
                        <span>Fiber Infrastructure</span>
                        <i class="bi bi-chevron-down ms-auto" :class="{ 'rotate-180': submenus.fiberInfraSubmenu }"></i>
                    </div>
                    <div class="collapse" :class="{ 'show': submenus.fiberInfraSubmenu }">
                        <div class="submenu">
                             <Link :href="getRoute('olts.index')" class="submenu-item" :class="{ active: $page.url.startsWith('/olts') }">
                                <i class="bi bi-circle"></i> OLTs
                            </Link>
                            <Link :href="getRoute('odfs.index')" class="submenu-item" :class="{ active: $page.url.startsWith('/odfs') }">
                                <i class="bi bi-circle"></i> ODFs
                            </Link>
                            <Link :href="getRoute('odcs.index')" class="submenu-item" :class="{ active: $page.url.startsWith('/odcs') }">
                                <i class="bi bi-circle"></i> ODCs
                            </Link>
                            <Link :href="getRoute('odps.index')" class="submenu-item" :class="{ active: $page.url.startsWith('/odps') }">
                                <i class="bi bi-circle"></i> ODPs
                            </Link>
                            <Link :href="getRoute('onts.index')" class="submenu-item" :class="{ active: $page.url.startsWith('/onts') }">
                                <i class="bi bi-circle"></i> ONTs
                            </Link>
                            
                            <!-- Additional Fiber Menu Items -->
                            <Link :href="getRoute('joint-boxes.index')" class="submenu-item" :class="{ active: $page.url.startsWith('/joint-boxes') }">
                                <i class="bi bi-circle"></i> Joint Boxes
                            </Link>
                            <Link :href="getRoute('cable-segments.index')" class="submenu-item" :class="{ active: $page.url.startsWith('/cable-segments') }">
                                <i class="bi bi-circle"></i> Cables & Segments
                            </Link>
                            <Link :href="getRoute('cores.index')" class="submenu-item" :class="{ active: $page.url.startsWith('/cores') }">
                                <i class="bi bi-circle"></i> Cores Management
                            </Link>
                            <Link :href="getRoute('fiber-splices.index')" class="submenu-item" :class="{ active: $page.url.startsWith('/fiber-splices') }">
                                <i class="bi bi-circle"></i> Splices
                            </Link>
                            <Link :href="getRoute('fiber-test-results.index')" class="submenu-item" :class="{ active: $page.url.startsWith('/fiber-test-results') }">
                                <i class="bi bi-circle"></i> OTDR Results
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- ACS Management Direct Link -->
                <Link :href="getRoute('acs.index')" class="menu-item" :class="{ active: $page.url.startsWith('/acs') }">
                    <i class="bi bi-broadcast"></i>
                    <span>ACS Management</span>
                </Link>

                <Link :href="getRoute('network.topology')" class="menu-item" :class="{ active: $page.url.startsWith('/network-topology') || $page.url === '/network-topology' }">
                    <i class="bi bi-diagram-3"></i>
                    <span>Network Topology</span>
                </Link>

                <Link :href="getRoute('network.map')" class="menu-item" :class="{ active: $page.url.startsWith('/network-map') || $page.url === '/network-map' }">
                    <i class="bi bi-geo-alt"></i>
                    <span>Network Map</span>
                </Link>

                <div class="px-3 mt-4 mb-2">
                    <small class="text-muted fw-semibold">LAPORAN</small>
                </div>

                <Link :href="getRoute('reports.index')" class="menu-item" :class="{ active: $page.url.startsWith('/reports') }">
                    <i class="bi bi-bar-chart"></i>
                    <span>Reports</span>
                </Link>

                <div class="px-3 mt-4 mb-2">
                    <small class="text-muted fw-semibold">SETTINGS</small>
                </div>

                <Link :href="getRoute('subscription.index')" class="menu-item" :class="{ active: $page.url.startsWith('/subscription') }">
                    <i class="bi bi-credit-card"></i>
                    <span>Subscription & Billing</span>
                </Link>

                <Link :href="getRoute('settings.index')" class="menu-item" :class="{ active: $page.url.startsWith('/settings') }">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </Link>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="hamburger" @click="toggleSidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <h5 class="mb-0 fw-bold" style="color: var(--kitabill-text-primary);">
                        KITABILL
                    </h5>
                </div>
                <div class="topbar-right">
                    <ThemeToggle />
                    <div class="dropdown">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <span>{{ auth?.user?.name || 'User' }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <Link :href="getRoute('profile.edit')" class="dropdown-item">
                                    <i class="bi bi-person"></i> Profile
                                </Link>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form @submit.prevent="logout">
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Page Content -->
            <div class="content-wrapper">
                <slot />
            </div>
            
            <!-- Footer dengan Copyright -->
            <footer class="kitabill-footer">
                <Copyright />
            </footer>
        </div>
    </div>
</template>

<script setup>
import { useTheme } from '@/composables/useTheme';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import Copyright from '../Components/Copyright.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';

const page = usePage();
const auth = page.props.auth;

const sidebarOpen = ref(false);

// Check if current page is in fiber infra or ACS submenu
const isFiberInfraActive = () => {
    const url = page.url;
    return url.startsWith('/joint-boxes') || 
           url.startsWith('/cable-segments') || 
           url.startsWith('/cores') || 
           url.startsWith('/fiber-splices') || 
           url.startsWith('/fiber-test-results');
};

const isAcsActive = () => {
    const url = page.url;
    return url.startsWith('/acs');
};

const submenus = reactive({
    fiberInfraSubmenu: isFiberInfraActive(),
    acsSubmenu: isAcsActive(),
});

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const toggleSubmenu = (submenuId) => {
    submenus[submenuId] = !submenus[submenuId];
};

/**
 * Robust Route Getter
 * Prevents 'undefined' errors causing Inertia Link crashes.
 */
const getRoute = (name, params = {}) => {
    if (!name) return '#';

    // 1. Critical Fallback for Network Map
    if (name === 'network.map') {
        return '/network-map';
    }

    let url = '#';

    try {
        // Don't call window.route for hotspot routes to prevent errors
        if (name.startsWith('hotspot.')) {
            // Handle hotspot routes manually
            if (name === 'hotspot.index') {
                url = '/hotspot';
            } else if (name === 'hotspot.dashboard' && params.router) {
                url = '/hotspot/' + params.router + '/dashboard';
            } else {
                url = '/' + name.replace(/\./g, '/');
            }
        } else if (typeof window !== 'undefined' && window.route) {
            url = window.route(name, params);
        } else {
            // Fallback: Check props manually
            const ziggy = page.props.ziggy;
            if (ziggy && ziggy.routes && ziggy.routes[name]) {
                let uri = ziggy.routes[name].uri;
                Object.keys(params).forEach(key => {
                    uri = uri.replace(`{${key}}`, params[key]);
                    uri = uri.replace(`{${key}?}`, params[key] || '');
                });
                url = (ziggy.url || '') + '/' + uri.replace(/^\//, '');
            } else {
                // Ultimate Fallback: Construct URL from name
                url = '/' + name.replace('.', '/').replace('index', '');
            }
        }
    } catch (e) {
        // console.warn(`Ziggy route('${name}') failed:`, e);
        // Ensure we still return a string path if possible
        url = '/' + name.replace(/\./g, '/');
    }

    // Double safety: Ensure explicitly String
    return String(url || '#');
};

const logout = () => {
    router.post('/logout');
};

// Close sidebar on navigation (mobile)
onMounted(() => {
    const unbindNavigate = router.on('navigate', () => {
        if (window.innerWidth <= 768) {
            closeSidebar();
        }
    });

    onUnmounted(() => {
        unbindNavigate();
    });
});
</script>

<style>
.admin-layout {
    display: flex;
    min-height: 100vh;
    flex-direction: column;
}

.kitabill-footer {
    margin-top: auto;
    padding-top: var(--kitabill-spacing-xl);
}

.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1040;
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

.sidebar-overlay.show {
    display: block;
    opacity: 1;
}

.menu-item-group .menu-item i.ms-auto {
    transition: transform 0.3s ease;
}

.menu-item-group .menu-item i.ms-auto.rotate-180 {
    transform: rotate(180deg);
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.topbar-right .dropdown-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    color: #333;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: background 0.2s;
}

.topbar-right .dropdown-toggle:hover {
    background: #f3f4f6;
}
.nested-item {
    padding-left: 3rem !important;
}
.nested-header {
    cursor: pointer;
}

/* Ensure collapse transitions work properly - CRITICAL FIX */
.sidebar .collapse {
    display: none !important;
    visibility: hidden !important;
    height: 0 !important;
    overflow: hidden !important;
}

.sidebar .collapse.show {
    display: block !important;
    visibility: visible !important;
    height: auto !important;
    overflow: visible !important;
}

.sidebar .submenu {
    padding-left: 0 !important;
    background-color: transparent !important;
    margin-top: 0.25rem !important;
    display: block !important;
}

.sidebar .submenu-item {
    display: flex !important;
    align-items: center !important;
    padding: 0.75rem 1rem 0.75rem 3rem !important;
    color: #6c757d !important;
    text-decoration: none !important;
    transition: all 0.2s ease !important;
    border-left: 3px solid transparent !important;
    font-size: 0.9rem !important;
}

.sidebar .submenu-item i {
    margin-right: 0.5rem !important;
    font-size: 0.85rem !important;
}

.sidebar .submenu-item:hover {
    background-color: #f8f9fa !important;
    color: #495057 !important;
    border-left-color: #4e73df !important;
}

.sidebar .submenu-item.active {
    background-color: #e3f2fd !important;
    color: #1976d2 !important;
    border-left-color: #1976d2 !important;
    font-weight: 500 !important;
}

/* ========================================
   DARK MODE STYLES
   ======================================== */

:global(.dark) .admin-layout {
    background-color: #111827;
    color: #F9FAFB;
}

:global(.dark) .sidebar {
    background-color: #1F2937 !important;
    border-right-color: #374151 !important;
}

:global(.dark) .sidebar-header {
    background-color: #1F2937 !important;
    border-bottom-color: #374151 !important;
}

:global(.dark) .menu-item {
    color: #D1D5DB !important;
}

:global(.dark) .menu-item:hover {
    background-color: #374151 !important;
    color: #F9FAFB !important;
}

:global(.dark) .menu-item.active {
    background-color: #3B82F6 !important;
    color: #FFFFFF !important;
}

:global(.dark) .submenu-item {
    color: #9CA3AF !important;
}

:global(.dark) .submenu-item:hover {
    background-color: #374151 !important;
    color: #E5E7EB !important;
    border-left-color: #60A5FA !important;
}

:global(.dark) .submenu-item.active {
    background-color: rgba(59, 130, 246, 0.2) !important;
    color: #60A5FA !important;
    border-left-color: #60A5FA !important;
}

:global(.dark) .topbar {
    background-color: #1F2937 !important;
    border-bottom-color: #374151 !important;
}

:global(.dark) .topbar-right .dropdown-toggle {
    color: #F9FAFB !important;
}

:global(.dark) .topbar-right .dropdown-toggle:hover {
    background-color: #374151 !important;
}

:global(.dark) .dropdown-menu {
    background-color: #1F2937 !important;
    border-color: #374151 !important;
}

:global(.dark) .dropdown-item {
    color: #D1D5DB !important;
}

:global(.dark) .dropdown-item:hover {
    background-color: #374151 !important;
    color: #F9FAFB !important;
}

:global(.dark) .main-content {
    background-color: #111827 !important;
}

:global(.dark) .card {
    background-color: #1F2937 !important;
    border-color: #374151 !important;
    color: #F9FAFB !important;
}

:global(.dark) .card-header {
    background-color: #374151 !important;
    border-bottom-color: #4B5563 !important;
    color: #F9FAFB !important;
}

:global(.dark) .table {
    color: #F9FAFB !important;
}

:global(.dark) .table thead th {
    background-color: #374151 !important;
    color: #F9FAFB !important;
    border-color: #4B5563 !important;
}

:global(.dark) .table tbody td {
    border-color: #374151 !important;
}

:global(.dark) .table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(55, 65, 81, 0.3) !important;
}

:global(.dark) .table-hover tbody tr:hover {
    background-color: rgba(55, 65, 81, 0.5) !important;
}

:global(.dark) .form-control {
    background-color: #374151 !important;
    border-color: #4B5563 !important;
    color: #F9FAFB !important;
}

:global(.dark) .form-control:focus {
    background-color: #374151 !important;
    border-color: #60A5FA !important;
    color: #F9FAFB !important;
}

:global(.dark) .form-select {
    background-color: #374151 !important;
    border-color: #4B5563 !important;
    color: #F9FAFB !important;
}

:global(.dark) .btn-primary {
    background-color: #3B82F6 !important;
    border-color: #3B82F6 !important;
}

:global(.dark) .btn-primary:hover {
    background-color: #2563EB !important;
    border-color: #2563EB !important;
}

:global(.dark) .btn-secondary {
    background-color: #6B7280 !important;
    border-color: #6B7280 !important;
}

:global(.dark) .btn-outline-secondary {
    color: #9CA3AF !important;
    border-color: #4B5563 !important;
}

:global(.dark) .btn-outline-secondary:hover {
    background-color: #374151 !important;
    border-color: #4B5563 !important;
    color: #F9FAFB !important;
}

:global(.dark) .badge {
    background-color: #374151 !important;
    color: #F9FAFB !important;
}

:global(.dark) .badge.bg-success {
    background-color: #10B981 !important;
}

:global(.dark) .badge.bg-danger {
    background-color: #EF4444 !important;
}

:global(.dark) .badge.bg-warning {
    background-color: #F59E0B !important;
}

:global(.dark) .badge.bg-info {
    background-color: #3B82F6 !important;
}

:global(.dark) .alert {
    background-color: #1F2937 !important;
    border-color: #374151 !important;
    color: #F9FAFB !important;
}

:global(.dark) .modal-content {
    background-color: #1F2937 !important;
    border-color: #374151 !important;
}

:global(.dark) .modal-header {
    background-color: #374151 !important;
    border-bottom-color: #4B5563 !important;
    color: #F9FAFB !important;
}

:global(.dark) .modal-footer {
    background-color: #374151 !important;
    border-top-color: #4B5563 !important;
}

:global(.dark) .text-muted {
    color: #9CA3AF !important;
}

:global(.dark) .border {
    border-color: #374151 !important;
}

:global(.dark) .bg-light {
    background-color: #374151 !important;
}

:global(.dark) small.text-muted {
    color: #6B7280 !important;
}

/* Smooth transitions for dark mode */
.admin-layout,
.sidebar,
.topbar,
.card,
.table,
.form-control,
.form-select,
.dropdown-menu,
.modal-content {
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease !important;
}
</style>
