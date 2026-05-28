<template>
    <div class="skeleton-loader">
        <!-- Skeleton for Stat Cards -->
        <div v-if="type === 'stats'" class="row g-3 mb-4">
            <div v-for="n in 4" :key="n" class="col-md-3">
                <div class="skeleton-stat-card">
                    <div class="skeleton-label"></div>
                    <div class="skeleton-value"></div>
                    <div class="skeleton-button"></div>
                </div>
            </div>
        </div>

        <!-- Skeleton for Quick Access Menu -->
        <div v-if="type === 'quick-menu'" class="row g-3">
            <div v-for="n in 7" :key="n" class="col-6 col-md-2">
                <div class="skeleton-quick-card">
                    <div class="skeleton-icon"></div>
                    <div class="skeleton-text"></div>
                </div>
            </div>
        </div>

        <!-- Skeleton for Table -->
        <div v-if="type === 'table'" class="skeleton-table">
            <div class="skeleton-table-header"></div>
            <div v-for="n in rows || 5" :key="n" class="skeleton-table-row">
                <div class="skeleton-table-cell"></div>
                <div class="skeleton-table-cell"></div>
                <div class="skeleton-table-cell"></div>
            </div>
        </div>

        <!-- Skeleton for Cards -->
        <div v-if="type === 'card'" class="skeleton-card">
            <div class="skeleton-card-header"></div>
            <div class="skeleton-card-body">
                <div v-for="n in 3" :key="n" class="skeleton-line"></div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    type: {
        type: String,
        default: 'stats',
        validator: (value) => ['stats', 'quick-menu', 'table', 'card'].includes(value)
    },
    rows: {
        type: Number,
        default: 5
    }
});
</script>

<style scoped>
/* Base Skeleton Animation */
@keyframes shimmer {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
    }
}

.skeleton-loader {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Skeleton Elements */
.skeleton-stat-card,
.skeleton-quick-card,
.skeleton-table-header,
.skeleton-table-row,
.skeleton-card,
.skeleton-label,
.skeleton-value,
.skeleton-button,
.skeleton-icon,
.skeleton-text,
.skeleton-table-cell,
.skeleton-card-header,
.skeleton-line {
    background: linear-gradient(
        90deg,
        var(--kitabill-bg-secondary, #f0f0f0) 0%,
        var(--kitabill-bg-tertiary, #e0e0e0) 50%,
        var(--kitabill-bg-secondary, #f0f0f0) 100%
    );
    background-size: 2000px 100%;
    animation: shimmer 1.5s infinite linear;
    border-radius: 8px;
}

/* Stat Card Skeleton */
.skeleton-stat-card {
    padding: 1.5rem;
    border-radius: 12px;
    height: 140px;
}

.skeleton-label {
    width: 60%;
    height: 12px;
    margin-bottom: 8px;
}

.skeleton-value {
    width: 40%;
    height: 32px;
    margin-bottom: 16px;
}

.skeleton-button {
    width: 80px;
    height: 28px;
}

/* Quick Access Card Skeleton */
.skeleton-quick-card {
    padding: 1.25rem;
    border-radius: 12px;
    height: 120px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.skeleton-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
}

.skeleton-text {
    width: 60px;
    height: 14px;
}

/* Table Skeleton */
.skeleton-table {
    background: var(--kitabill-bg-primary);
    border-radius: 12px;
    padding: 1rem;
}

.skeleton-table-header {
    height: 40px;
    margin-bottom: 12px;
}

.skeleton-table-row {
    display: flex;
    gap: 12px;
    margin-bottom: 8px;
}

.skeleton-table-cell {
    flex: 1;
    height: 32px;
}

/* Card Skeleton */
.skeleton-card {
    background: var(--kitabill-bg-primary);
    border-radius: 12px;
    overflow: hidden;
}

.skeleton-card-header {
    height: 60px;
    border-radius: 0;
}

.skeleton-card-body {
    padding: 1.5rem;
}

.skeleton-line {
    height: 16px;
    margin-bottom: 12px;
}

.skeleton-line:last-child {
    width: 60%;
}

/* Dark Mode Support */
[data-theme="dark"] .skeleton-stat-card,
[data-theme="dark"] .skeleton-quick-card,
[data-theme="dark"] .skeleton-table-header,
[data-theme="dark"] .skeleton-table-row,
[data-theme="dark"] .skeleton-card,
[data-theme="dark"] .skeleton-label,
[data-theme="dark"] .skeleton-value,
[data-theme="dark"] .skeleton-button,
[data-theme="dark"] .skeleton-icon,
[data-theme="dark"] .skeleton-text,
[data-theme="dark"] .skeleton-table-cell,
[data-theme="dark"] .skeleton-card-header,
[data-theme="dark"] .skeleton-line {
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.05) 0%,
        rgba(255, 255, 255, 0.1) 50%,
        rgba(255, 255, 255, 0.05) 100%
    );
    background-size: 2000px 100%;
}
</style>
