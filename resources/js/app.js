import './bootstrap';
import '../css/app.css';

// Import Bootstrap
import * as bootstrap from 'bootstrap';

// Import Bootstrap Icons
import 'bootstrap-icons/font/bootstrap-icons.css';

// ✅ Check if we're on customer portal (skip Inertia completely)
// Check both path and global flag (set by customer portal views)
// IMPORTANT: Use exact match '/customer/' to avoid matching '/customers/' (admin panel)
const isCustomerPortal = window.location.pathname.match(/^\/customer(\/|$)/) || window.__SKIP_INERTIA__;

if (!isCustomerPortal) {
    // ✅ Only load Inertia for admin pages - use dynamic import to avoid errors
    Promise.all([
        import('vue'),
        import('@inertiajs/vue3'),
        import('laravel-vite-plugin/inertia-helpers'),
        import('../../vendor/tightenco/ziggy')
    ]).then(([
        { createApp, h },
        { createInertiaApp },
        { resolvePageComponent },
        { ZiggyVue }
    ]) => {
        const appName = import.meta.env.VITE_APP_NAME || 'KITABILL';

        // ✅ Wait for DOM and Inertia data to be ready
        const initInertia = () => {
            const appElement = document.getElementById('app');
            
            if (!appElement) {
                console.warn("⚠️ App element not found, retrying...");
                setTimeout(initInertia, 100);
                return;
            }

            // Inertia.js will inject data-page attribute automatically
            // We don't need to check for it, just initialize
            try {
                createInertiaApp({
                    title: (title) => `${title} - ${appName}`,
                    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
                    setup({ el, App, props, plugin }) {
                        return createApp({ render: () => h(App, props) })
                            .use(plugin)
                            .use(ZiggyVue)
                            .mount(el);
                    },
                    progress: {
                        color: '#4B5563',
                    },
                });

                console.log("✅ Inertia.js + Vue 3 loaded successfully!");
            } catch (error) {
                console.error("❌ Inertia initialization failed:", error);
                // Show error message to user
                if (appElement) {
                    appElement.innerHTML = `
                        <div style="padding: 2rem; text-align: center; font-family: system-ui;">
                            <h2 style="color: #dc2626;">Error Loading Page</h2>
                            <p>There was an error loading this page. Please refresh or contact support.</p>
                            <button onclick="window.location.reload()" style="padding: 0.5rem 1rem; margin-top: 1rem; cursor: pointer; background: #3b82f6; color: white; border: none; border-radius: 4px;">
                                Refresh Page
                            </button>
                            <details style="margin-top: 1rem; text-align: left;">
                                <summary style="cursor: pointer; color: #3b82f6;">Show Error Details</summary>
                                <pre style="margin-top: 0.5rem; background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow: auto; font-size: 0.875rem;">
${error.message}\n\n${error.stack}
                                </pre>
                            </details>
                        </div>
                    `;
                }
            }
        };

        // Start initialization when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initInertia);
        } else {
            // DOM already ready, but wait a bit for Inertia to inject data-page
            setTimeout(initInertia, 50);
        }
    }).catch((error) => {
        // Silently fail if imports fail (e.g., on customer portal)
        console.log("ℹ️ Skipping Inertia initialization");
    });
} else {
    console.log("ℹ️ Customer portal detected - Inertia.js skipped");
}