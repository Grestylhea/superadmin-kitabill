// KITABILL Configuration
// Edit sekali, ter-update di semua bagian aplikasi

export const KITABILL_CONFIG = {
    // Branding
    name: 'KITABILL',
    tagline: 'Billing System Terpercaya',
    version: '1.0.0',
    
    // Copyright - EDIT DI SINI UNTUK UPDATE SEMUA
    copyright: {
        year: new Date().getFullYear(),
        company: 'KITABILL',
        text: `© ${new Date().getFullYear()} KITABILL. All rights reserved.`,
        poweredBy: 'Powered by KITABILL Billing System'
    },
    
    // Color Palette - Modern & Professional
    colors: {
        primary: '#6366f1',      // Indigo - Modern & Trustworthy
        primaryDark: '#4f46e5',
        primaryLight: '#818cf8',
        secondary: '#10b981',   // Green - Success & Growth
        accent: '#f59e0b',       // Amber - Warning & Attention
        danger: '#ef4444',      // Red - Error & Alert
        success: '#10b981',     // Green
        warning: '#f59e0b',     // Amber
        info: '#3b82f6',        // Blue
        
        // Neutral Colors
        dark: '#1f2937',
        gray: '#6b7280',
        light: '#f3f4f6',
        white: '#ffffff',
        
        // Backgrounds
        bgPrimary: '#ffffff',
        bgSecondary: '#f9fafb',
        bgDark: '#111827',
        
        // Gradients
        gradientPrimary: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)',
        gradientSuccess: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        gradientWarning: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        gradientDanger: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
    },
    
    // Typography
    fonts: {
        primary: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        heading: "'Inter', sans-serif",
        mono: "'Fira Code', 'Courier New', monospace"
    },
    
    // Spacing
    spacing: {
        xs: '0.25rem',
        sm: '0.5rem',
        md: '1rem',
        lg: '1.5rem',
        xl: '2rem',
        '2xl': '3rem',
    },
    
    // Border Radius
    radius: {
        sm: '0.5rem',
        md: '0.75rem',
        lg: '1rem',
        xl: '1.5rem',
        full: '9999px'
    },
    
    // Shadows
    shadows: {
        sm: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
        md: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
        lg: '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
        xl: '0 20px 25px -5px rgba(0, 0, 0, 0.1)',
    }
};

// Export copyright untuk mudah diakses
export const COPYRIGHT = KITABILL_CONFIG.copyright;

