<script setup>
import { ref, onMounted } from 'vue';

const isDark = ref(false);

const toggleTheme = () => {
    isDark.value = !isDark.value;
    
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

onMounted(() => {
    // Check saved theme preference or system preference
    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    }
});
</script>

<template>
    <button
        @click="toggleTheme"
        class="theme-toggle-fixed"
        :class="{ 'dark': isDark }"
        aria-label="Toggle theme"
    >
        <!-- Sun Icon (Light Mode) -->
        <svg v-if="!isDark" xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        
        <!-- Moon Icon (Dark Mode) -->
        <svg v-else xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
    </button>
</template>

<style scoped>
.theme-toggle-fixed {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 9999;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.95);
    border: 2px solid rgba(59, 130, 246, 0.2);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.theme-toggle-fixed:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    background: rgba(255, 255, 255, 1);
    border-color: rgba(59, 130, 246, 0.4);
}

.theme-toggle-fixed.dark {
    background: rgba(31, 41, 55, 0.95);
    border-color: rgba(96, 165, 250, 0.3);
}

.theme-toggle-fixed.dark:hover {
    background: rgba(31, 41, 55, 1);
    border-color: rgba(96, 165, 250, 0.5);
}

.icon {
    width: 24px;
    height: 24px;
    color: #3B82F6;
    transition: all 0.3s ease;
}

.theme-toggle-fixed.dark .icon {
    color: #60A5FA;
}

.theme-toggle-fixed:active {
    transform: scale(0.95);
}

@media (max-width: 768px) {
    .theme-toggle-fixed {
        top: 16px;
        right: 16px;
        width: 44px;
        height: 44px;
    }
    
    .icon {
        width: 20px;
        height: 20px;
    }
}
</style>



