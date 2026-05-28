<template>
    <button 
        @click="toggleTheme" 
        class="theme-switcher"
        :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        aria-label="Toggle theme"
    >
        <i :class="isDark ? 'bi bi-sun-fill' : 'bi bi-moon-fill'"></i>
        <span class="theme-label">{{ isDark ? 'Light' : 'Dark' }}</span>
    </button>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const isDark = ref(false);

const getTheme = () => {
    if (typeof window !== 'undefined') {
        const saved = localStorage.getItem('kitabill-theme');
        if (saved) {
            return saved === 'dark';
        }
        // Default to system preference
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    return false;
};

const setTheme = (dark) => {
    isDark.value = dark;
    const html = document.documentElement;
    if (dark) {
        html.setAttribute('data-theme', 'dark');
        localStorage.setItem('kitabill-theme', 'dark');
    } else {
        html.setAttribute('data-theme', 'light');
        localStorage.setItem('kitabill-theme', 'light');
    }
};

const toggleTheme = () => {
    setTheme(!isDark.value);
};

onMounted(() => {
    const dark = getTheme();
    setTheme(dark);
    
    // Listen for system theme changes
    if (typeof window !== 'undefined') {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem('kitabill-theme')) {
                setTheme(e.matches);
            }
        });
    }
});
</script>

<style scoped>
.theme-switcher {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--kitabill-bg-tertiary);
    border: 1px solid var(--kitabill-border);
    border-radius: var(--kitabill-radius-md);
    color: var(--kitabill-text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    font-weight: 500;
}

.theme-switcher:hover {
    background: var(--kitabill-bg-secondary);
    border-color: var(--kitabill-primary);
    color: var(--kitabill-primary);
}

.theme-switcher i {
    font-size: 1rem;
}

.theme-label {
    font-size: 0.8125rem;
}

@media (max-width: 768px) {
    .theme-label {
        display: none;
    }
    
    .theme-switcher {
        padding: 0.5rem;
        width: 40px;
        height: 40px;
        justify-content: center;
    }
}
</style>

