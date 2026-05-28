<template>
    <div class="superadmin-layout">
        <!-- Sidebar overlay for mobile drawer -->
        <div class="sidebar-overlay" :class="{ 'show': sidebarOpen }" @click="closeSidebar"></div>
        
        <!-- Sidebar -->
        <div class="sidebar" :class="{ 'show': sidebarOpen }">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="/images/logo.png" alt="KitaBill Logo" class="sidebar-logo">
                    <div class="logo-text d-flex flex-column ms-2">
                        <strong class="logo-title">KitaBill</strong>
                        <span class="badge badge-superadmin mt-1">SUPER ADMIN</span>
                    </div>
                </div>
            </div>

            <div class="sidebar-menu">
                <!-- SECTION: UTAMA -->
                <div class="menu-section">
                    <div class="px-3 mb-2">
                        <small class="text-muted fw-bold uppercase tracking-wider" style="font-size: 0.7rem;">UTAMA</small>
                    </div>
                    <div class="section-content">
                        <Link :href="route('superadmin.dashboard')" class="menu-item" :class="{ active: $page.url === '/dashboard' || $page.url === '/superadmin/dashboard' }">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </Link>

                        <Link :href="route('superadmin.tenants.index')" class="menu-item" :class="{ active: $page.url.startsWith('/tenants') || $page.url.startsWith('/superadmin/tenants') }">
                            <i class="bi bi-building"></i>
                            <span>Tenant Management</span>
                        </Link>

                        <Link :href="route('superadmin.subscription-plans.index')" class="menu-item" :class="{ active: $page.url.startsWith('/subscription-plans') || $page.url.startsWith('/superadmin/subscription-plans') }">
                            <i class="bi bi-list-check"></i>
                            <span>Subscription Plans</span>
                        </Link>

                        <Link :href="route('superadmin.revenue')" class="menu-item" :class="{ active: $page.url.startsWith('/revenue') || $page.url.startsWith('/superadmin/revenue') }">
                            <i class="bi bi-graph-up-arrow"></i>
                            <span>Revenue & Billing</span>
                        </Link>

                        <Link :href="route('superadmin.withdrawals.index')" class="menu-item" :class="{ active: $page.url.startsWith('/withdrawals') || $page.url.startsWith('/superadmin/withdrawals') }">
                            <i class="bi bi-wallet2"></i>
                            <span>Withdrawals</span>
                        </Link>

                        <Link :href="route('superadmin.settings.index')" class="menu-item" :class="{ active: $page.url.startsWith('/settings') || $page.url.startsWith('/superadmin/settings') }">
                            <i class="bi bi-gear"></i>
                            <span>System Settings</span>
                        </Link>
                    </div>
                </div>

                <!-- SECTION: MONITORING -->
                <div class="menu-section mt-4">
                    <div class="px-3 mb-2">
                        <small class="text-muted fw-bold uppercase tracking-wider" style="font-size: 0.7rem;">MONITORING</small>
                    </div>
                    <div class="section-content">
                        <Link :href="route('superadmin.monitoring.index')" class="menu-item" :class="{ active: $page.url === '/monitoring' || $page.url === '/superadmin/monitoring' }">
                            <i class="bi bi-display"></i>
                            <span>Global Monitoring</span>
                        </Link>

                        <Link :href="route('superadmin.analytics')" class="menu-item" :class="{ active: $page.url.startsWith('/analytics') || $page.url.startsWith('/superadmin/analytics') }">
                            <i class="bi bi-bar-chart"></i>
                            <span>Analytics</span>
                        </Link>

                        <Link :href="route('superadmin.logs')" class="menu-item" :class="{ active: $page.url.startsWith('/logs') || $page.url.startsWith('/superadmin/logs') }">
                            <i class="bi bi-journal-text"></i>
                            <span>Activity Logs</span>
                        </Link>

                        <Link :href="route('superadmin.backups.index')" class="menu-item" :class="{ active: $page.url.startsWith('/backups') || $page.url.startsWith('/superadmin/backups') }">
                            <i class="bi bi-hdd-network"></i>
                            <span>Database Backups</span>
                        </Link>

                        <Link :href="route('superadmin.wa-gateways.index')" class="menu-item" :class="{ active: $page.url.startsWith('/wa-gateways') || $page.url.startsWith('/superadmin/wa-gateways') }">
                            <i class="bi bi-whatsapp"></i>
                            <span>WhatsApp Gateway</span>
                        </Link>

                        <Link :href="route('superadmin.bulk-messages.index')" class="menu-item" :class="{ active: $page.url.startsWith('/bulk-messages') || $page.url.startsWith('/superadmin/bulk-messages') }">
                            <i class="bi bi-chat-right-text"></i>
                            <span>Bulk Messages</span>
                        </Link>

                        <Link :href="route('superadmin.support')" class="menu-item" :class="{ active: $page.url.startsWith('/support') || $page.url.startsWith('/superadmin/support') }">
                            <i class="bi bi-headset"></i>
                            <span>Support Tickets</span>
                        </Link>
                    </div>
                </div>
            </div>

            <div class="sidebar-footer">
                <div class="system-info">
                    <div class="system-status">
                        <i class="bi bi-circle-fill text-success"></i>
                        <span>System Healthy</span>
                    </div>
                    <small class="text-muted">v1.0.0</small>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <div class="d-flex align-items-center gap-2 topbar-left">
                    <button class="hamburger-btn d-md-none" @click="toggleSidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="d-flex align-items-center gap-2 topbar-logo-container">
                        <img src="/images/logo.png" alt="Logo" class="topbar-mobile-logo d-md-none">
                        <h5 class="company-name-text mb-0 fw-bold">
                            SUPER ADMIN PANEL
                        </h5>
                    </div>
                </div>
                <div class="topbar-right">
                    <!-- Minimalist Theme Switcher -->
                    <button 
                        @click="toggleTheme" 
                        class="theme-switcher-btn"
                        :title="isDarkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                        aria-label="Toggle theme"
                    >
                        <i :class="isDarkMode ? 'bi bi-sun-fill' : 'bi bi-moon-fill'"></i>
                    </button>
                    
                    <!-- User Dropdown -->
                    <div class="dropdown">
                        <a class="user-dropdown-btn" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Pengguna">
                            <img v-if="$page.props.auth?.user?.photo" 
                                 :src="'/storage/' + $page.props.auth.user.photo" 
                                 alt="Profile" 
                                 class="topbar-user-avatar rounded-circle border border-2 border-white shadow-sm" 
                                 style="width: 32px; height: 32px; object-fit: cover;">
                            <i v-else class="bi bi-person-circle fs-4"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 font-sm">
                            <li>
                                <div class="px-3 py-2 border-bottom">
                                    <span class="d-block fw-bold text-truncate" style="max-width: 150px;">{{ $page.props.auth?.user?.name || 'Super Admin' }}</span>
                                    <small class="text-muted" style="font-size: 0.75rem;">Super Administrator</small>
                                </div>
                            </li>
                            <li>
                                <Link :href="route('superadmin.profile.edit')" class="dropdown-item mt-1">
                                    <i class="bi bi-person"></i> Profile
                                </Link>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form @submit.prevent="logout">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Page Content -->
            <div class="content-wrapper kb-content">
                <slot />
            </div>

            <!-- Footer -->
            <footer class="kitabill-footer mt-auto">
                <Copyright />
            </footer>
        </div>

        <!-- Mobile Bottom Navigation -->
        <nav class="mobile-bottom-nav d-md-none">
            <Link :href="route('superadmin.dashboard')" class="bottom-nav-item" :class="{ active: $page.url === '/dashboard' || $page.url === '/superadmin/dashboard' }">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </Link>
            
            <Link :href="route('superadmin.tenants.index')" class="bottom-nav-item" :class="{ active: $page.url.startsWith('/tenants') || $page.url.startsWith('/superadmin/tenants') }">
                <i class="bi bi-building"></i>
                <span>Tenants</span>
            </Link>
            
            <Link :href="route('superadmin.subscription-plans.index')" class="bottom-nav-item" :class="{ active: $page.url.startsWith('/subscription-plans') || $page.url.startsWith('/superadmin/subscription-plans') }">
                <i class="bi bi-list-check"></i>
                <span>Plans</span>
            </Link>
            
            <Link :href="route('superadmin.revenue')" class="bottom-nav-item" :class="{ active: $page.url.startsWith('/revenue') || $page.url.startsWith('/superadmin/revenue') }">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Revenue</span>
            </Link>
            
            <button class="bottom-nav-item hamburger-btn-bottom" @click="toggleMobileMenu">
                <i class="bi bi-list"></i>
                <span>Menu</span>
            </button>
        </nav>

        <!-- Modern Mobile Full Screen Menu (Bottom-up) -->
        <div class="mobile-full-menu d-md-none" :class="{ 'show': mobileMenuOpen }">
            <div class="mobile-menu-header">
                <div class="mobile-menu-brand">
                    <img src="/images/logo.png" alt="Logo" class="menu-logo-sm">
                    <span class="fw-bold">KitaBill SuperAdmin</span>
                </div>
                <button class="menu-close-btn" @click="closeMobileMenu">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <div class="mobile-menu-content">
                <!-- SECTION: UTAMA -->
                <div class="menu-grid-section">
                    <h6 class="menu-section-title">UTAMA</h6>
                    <div class="menu-grid">
                        <Link :href="route('superadmin.dashboard')" class="grid-item" :class="{ active: $page.url === '/dashboard' || $page.url === '/superadmin/dashboard' }" @click="closeMobileMenu">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </Link>
                        <Link :href="route('superadmin.tenants.index')" class="grid-item" :class="{ active: $page.url.startsWith('/tenants') || $page.url.startsWith('/superadmin/tenants') }" @click="closeMobileMenu">
                            <i class="bi bi-building"></i>
                            <span>Tenants</span>
                        </Link>
                        <Link :href="route('superadmin.subscription-plans.index')" class="grid-item" :class="{ active: $page.url.startsWith('/subscription-plans') || $page.url.startsWith('/superadmin/subscription-plans') }" @click="closeMobileMenu">
                            <i class="bi bi-list-check"></i>
                            <span>Plans</span>
                        </Link>
                        <Link :href="route('superadmin.revenue')" class="grid-item" :class="{ active: $page.url.startsWith('/revenue') || $page.url.startsWith('/superadmin/revenue') }" @click="closeMobileMenu">
                            <i class="bi bi-graph-up-arrow"></i>
                            <span>Revenue</span>
                        </Link>
                        <Link :href="route('superadmin.withdrawals.index')" class="grid-item" :class="{ active: $page.url.startsWith('/withdrawals') || $page.url.startsWith('/superadmin/withdrawals') }" @click="closeMobileMenu">
                            <i class="bi bi-wallet2"></i>
                            <span>Withdrawals</span>
                        </Link>
                        <Link :href="route('superadmin.settings.index')" class="grid-item" :class="{ active: $page.url.startsWith('/settings') || $page.url.startsWith('/superadmin/settings') }" @click="closeMobileMenu">
                            <i class="bi bi-gear"></i>
                            <span>Settings</span>
                        </Link>
                    </div>
                </div>

                <!-- SECTION: MONITORING -->
                <div class="menu-grid-section mt-4">
                    <h6 class="menu-section-title">MONITORING</h6>
                    <div class="menu-grid">
                        <Link :href="route('superadmin.monitoring.index')" class="grid-item" :class="{ active: $page.url === '/monitoring' || $page.url === '/superadmin/monitoring' }" @click="closeMobileMenu">
                            <i class="bi bi-display"></i>
                            <span>Global Mon</span>
                        </Link>

                        <Link :href="route('superadmin.analytics')" class="grid-item" :class="{ active: $page.url.startsWith('/analytics') || $page.url.startsWith('/superadmin/analytics') }" @click="closeMobileMenu">
                            <i class="bi bi-bar-chart"></i>
                            <span>Analytics</span>
                        </Link>
                        <Link :href="route('superadmin.logs')" class="grid-item" :class="{ active: $page.url.startsWith('/logs') || $page.url.startsWith('/superadmin/logs') }" @click="closeMobileMenu">
                            <i class="bi bi-journal-text"></i>
                            <span>Activity Logs</span>
                        </Link>
                        <Link :href="route('superadmin.backups.index')" class="grid-item" :class="{ active: $page.url.startsWith('/backups') || $page.url.startsWith('/superadmin/backups') }" @click="closeMobileMenu">
                            <i class="bi bi-hdd-network"></i>
                            <span>DB Backups</span>
                        </Link>
                        <Link :href="route('superadmin.wa-gateways.index')" class="grid-item" :class="{ active: $page.url.startsWith('/wa-gateways') || $page.url.startsWith('/superadmin/wa-gateways') }" @click="closeMobileMenu">
                            <i class="bi bi-whatsapp"></i>
                            <span>WA Gateway</span>
                        </Link>
                        <Link :href="route('superadmin.bulk-messages.index')" class="grid-item" :class="{ active: $page.url.startsWith('/bulk-messages') || $page.url.startsWith('/superadmin/bulk-messages') }" @click="closeMobileMenu">
                            <i class="bi bi-chat-right-text"></i>
                            <span>Bulk Msg</span>
                        </Link>
                        <Link :href="route('superadmin.support')" class="grid-item" :class="{ active: $page.url.startsWith('/support') || $page.url.startsWith('/superadmin/support') }" @click="closeMobileMenu">
                            <i class="bi bi-headset"></i>
                            <span>Tickets</span>
                        </Link>
                    </div>
                </div>

                <!-- SECTION: ACCOUNT -->
                <div class="menu-grid-section mt-4 mb-4">
                    <h6 class="menu-section-title">ACCOUNT</h6>
                    <div class="menu-grid">
                        <Link :href="route('superadmin.profile.edit')" class="grid-item" @click="closeMobileMenu">
                            <i class="bi bi-person-circle"></i>
                            <span>Profile</span>
                        </Link>
                        <form @submit.prevent="logout" class="d-contents">
                            <button type="submit" class="grid-item text-danger border-0 bg-transparent" style="width: 100%;">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import Copyright from '../Components/Copyright.vue';

const sidebarOpen = ref(false);
const mobileMenuOpen = ref(false);
const isDarkMode = ref(false);

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
    if (mobileMenuOpen.value) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
};

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
    document.body.style.overflow = '';
};

const logout = () => {
    router.post(route('logout'));
};

const toggleTheme = () => {
    isDarkMode.value = !isDarkMode.value;
    if (isDarkMode.value) {
        document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        document.documentElement.setAttribute('data-theme', 'light');
        localStorage.setItem('theme', 'light');
    }
};

onMounted(() => {
    // Check saved theme preference or system preference
    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
        isDarkMode.value = true;
        document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.documentElement.setAttribute('data-theme', 'light');
    }

    const unbindNavigate = router.on('navigate', () => {
        closeSidebar();
        closeMobileMenu();
    });

    onUnmounted(() => {
        unbindNavigate();
    });
});
</script>

<style scoped>
.superadmin-layout {
    display: flex;
    flex-direction: column; /* Mobile default */
    min-height: 100vh;
    width: 100%;
    overflow-x: clip;
    background: var(--kitabill-bg-tertiary);
}

@media (min-width: 768px) {
    .superadmin-layout {
        flex-direction: row; /* Desktop */
    }
}

/* Logo & Header Styling */
.sidebar-header {
    padding: 1.5rem 1.25rem;
    border-bottom: 1px solid var(--kitabill-border);
    display: flex;
    align-items: center;
    height: 70px; /* Align height with topbar */
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    overflow: hidden;
    width: 100%;
}

.sidebar-logo {
    height: 38px !important;
    width: auto !important;
    max-width: 100% !important;
    object-fit: contain;
    flex-shrink: 0;
}

.logo-title {
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1.2;
    color: var(--kitabill-text-primary);
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

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 1.5rem;
    background: var(--kitabill-bg-primary);
    border-bottom: 1px solid var(--kitabill-border);
    position: sticky;
    top: 0;
    z-index: 1000;
    height: 70px;
    margin: 0;
    width: 100%;
}

.main-content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    margin: 0;
    position: relative;
    overflow-x: hidden;
}

.content-wrapper {
    flex: 1;
    padding: 2rem;
    width: 100%;
    margin: 0;
}

@media (min-width: 768px) {
    .sidebar {
        width: 260px;
        flex-shrink: 0;
        height: 100vh;
        position: sticky;
        top: 0;
        background: var(--kitabill-bg-primary);
        border-right: 1px solid var(--kitabill-border);
        display: flex !important;
        flex-direction: column;
        z-index: 1010;
        margin: 0 !important;
    }
}

@media (max-width: 767.98px) {
    .sidebar {
        position: fixed;
        left: -260px;
        top: 0;
        bottom: 0;
        width: 260px;
        z-index: 2000;
        transition: left 0.3s ease;
        background: var(--kitabill-bg-primary);
        display: flex;
        flex-direction: column;
    }
    .sidebar.show {
        left: 0;
    }
    .topbar {
        position: sticky;
        top: 0;
        z-index: 1000;
        width: 100%;
        padding: 0 1rem;
        margin: 0 !important;
    }
    .content-wrapper {
        padding: 1rem !important;
        padding-bottom: 90px !important;
    }
    .menu-grid {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 0.65rem !important;
        width: 100% !important;
    }
}

/* Theme Switcher & Dropdown buttons */
.theme-switcher-btn {
    background: transparent;
    border: 1px solid transparent;
    color: var(--kitabill-text-secondary);
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.theme-switcher-btn:hover {
    background: rgba(59, 130, 246, 0.05);
    color: var(--kitabill-primary);
    border-color: rgba(59, 130, 246, 0.1);
}

.user-dropdown-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: transparent;
    border: 1px solid transparent;
    color: var(--kitabill-text-secondary);
    font-size: 1.25rem;
    transition: all 0.3s ease;
    text-decoration: none;
}

.user-dropdown-btn:hover, .user-dropdown-btn.show {
    background: rgba(59, 130, 246, 0.05);
    color: var(--kitabill-primary);
    border-color: rgba(59, 130, 246, 0.1);
}

.badge-superadmin {
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    color: #ffffff;
    font-size: 9px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    letter-spacing: 0.5px;
    display: inline-block;
}

/* Mobile Bottom Navigation */
.mobile-bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--kitabill-bg-primary, #ffffff);
    display: flex;
    justify-content: space-around;
    align-items: center;
    padding: 0.5rem 0;
    box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.05);
    z-index: 1040;
    border-top: 1px solid var(--kitabill-border, #e5e7eb);
    padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));
}

[data-theme='dark'] .mobile-bottom-nav {
    background: var(--kitabill-bg-primary, #1e293b);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.2);
}

.bottom-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--kitabill-text-secondary, #64748b);
    text-decoration: none;
    font-size: 0.70rem;
    font-weight: 500;
    gap: 0.25rem;
    background: transparent;
    border: none;
    padding: 0.25rem 0.5rem;
    flex: 1;
    transition: all 0.2s ease;
}

.bottom-nav-item i {
    font-size: 1.25rem;
    margin-bottom: 2px;
}

.bottom-nav-item.active, .bottom-nav-item:hover {
    color: var(--kitabill-primary, #3b82f6);
}

.bottom-nav-item.active i {
    transform: translateY(-2px);
}

@media (max-width: 767.98px) {
    .kb-content {
        padding-bottom: 80px;
    }
    
    .topbar .hamburger-btn {
        display: none !important;
    }

    .mobile-full-menu {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        top: 0;
        background: var(--kitabill-bg-primary);
        z-index: 2000;
        display: flex;
        flex-direction: column;
        transform: translateY(100%);
        transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        padding-top: env(safe-area-inset-top);
    }

    .mobile-full-menu.show {
        transform: translateY(0);
    }

    .mobile-menu-header {
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--kitabill-border);
    }

    .mobile-menu-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .menu-logo-sm {
        height: 28px;
        width: auto;
    }

    .menu-close-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--kitabill-bg-tertiary);
        border: none;
        color: var(--kitabill-text-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .mobile-menu-content {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
        padding-bottom: 5rem;
    }

    .menu-section-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--kitabill-text-muted);
        margin-bottom: 1.25rem;
        font-weight: 700;
    }

    .menu-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.65rem;
    }

    .grid-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 0.25rem;
        background: var(--kitabill-bg-tertiary);
        border-radius: 12px;
        text-decoration: none;
        color: var(--kitabill-text-primary);
        transition: all 0.2s ease;
        gap: 0.25rem;
        text-align: center;
        height: 82px !important;
        border: 1px solid transparent;
        overflow: hidden;
    }

    .grid-item i {
        font-size: 1.25rem !important;
        color: var(--kitabill-primary);
        margin-bottom: 2px;
    }

    .grid-item span {
        font-size: 0.62rem !important;
        font-weight: 600;
        white-space: normal;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        width: 100%;
        line-height: 1.1;
        padding: 0 4px;
    }

    .grid-item.active {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid var(--kitabill-primary);
    }

    .grid-item:active {
        transform: scale(0.95);
    }
}
</style>
