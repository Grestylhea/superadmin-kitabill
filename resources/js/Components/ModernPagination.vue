<template>
    <div class="modern-pagination-wrapper">
        <div class="pagination-info">
            Showing <strong>{{ from }}</strong> to <strong>{{ to }}</strong> of <strong>{{ total }}</strong> results
        </div>

        <nav class="modern-pagination-nav">
            <ul class="modern-pagination">
                <!-- Previous Button -->
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                    <button 
                        class="page-link prev-next" 
                        @click.prevent="changePage(currentPage - 1)"
                        :disabled="currentPage === 1"
                    >
                        <i class="bi bi-chevron-left"></i> Previous
                    </button>
                </li>

                <!-- Page Numbers -->
                <li 
                    v-for="(page, index) in pageLinks" 
                    :key="index" 
                    class="page-item"
                    :class="{ active: page === currentPage }"
                >
                    <span v-if="page === '...'" class="page-link ellipsis">...</span>
                    <button 
                        v-else 
                        class="page-link page-number"
                        @click.prevent="changePage(page)"
                    >
                        {{ page }}
                    </button>
                </li>

                <!-- Next Button -->
                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                    <button 
                        class="page-link prev-next" 
                        @click.prevent="changePage(currentPage + 1)"
                        :disabled="currentPage === totalPages"
                    >
                        Next <i class="bi bi-chevron-right"></i>
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentPage: {
        type: Number,
        required: true
    },
    totalPages: {
        type: Number,
        required: true
    },
    from: {
        type: Number,
        default: 0
    },
    to: {
        type: Number,
        default: 0
    },
    total: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['page-change']);

const pageLinks = computed(() => {
    const total = props.totalPages;
    const current = props.currentPage;
    const pages = [];

    if (total <= 7) {
        // If 7 or fewer pages, show all (expanded from original 3 for better UX if space permits, 
        // but matching Customers "compact" logic if strict consistency is needed.
        // The server code I read used a "3 page" logic. Let's stick to the SERVER CODE exactly as users asked "samakan".
        // WAITING: The server code cat output I saw was:
        /*
        if (total <= 3) { ... } else { ... 3 pages logic ... }
        */
        // I will use THAT logic.
        for (let i = 1; i <= total; i++) {
            pages.push(i);
        }
    } else {
        // Always show exactly 3 pages: prev, current, next (PLUS start/end? No, strict 3 based on server code read)
        /* Match Server Code Logic: */
        if (current === 1) {
            pages.push(1);
            pages.push(2);
            pages.push(3);
        } else if (current === total) {
            pages.push(total - 2);
            pages.push(total - 1);
            pages.push(total);
        } else {
            pages.push(current - 1);
            pages.push(current);
            pages.push(current + 1);
        }
    }

    return pages;
});

const changePage = (page) => {
    if (page >= 1 && page <= props.totalPages && page !== props.currentPage) {
        emit('page-change', page);
    }
};
</script>

<style scoped>
/* Wrapper */
.modern-pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    gap: 1.5rem;
    flex-wrap: wrap;
    padding: 0 1rem; /* Add side padding for safety */
}

/* Info Text */
.pagination-info {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 500;
    letter-spacing: 0.3px;
}

.pagination-info strong {
    color: #212529;
    font-weight: 600;
}

[data-theme="dark"] .pagination-info {
    color: rgba(255, 255, 255, 0.65);
}

[data-theme="dark"] .pagination-info strong {
    color: rgba(255, 255, 255, 0.95);
}

/* Navigation Container */
.modern-pagination-nav {
    flex-shrink: 0;
}

/* Pagination UL - COMPACT DESIGN */
.modern-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 0;
    padding: 10px 16px;
    list-style: none;
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.98) 100%);
    border-radius: 12px;
    backdrop-filter: blur(12px);
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.08);
    
    /* COMPACT WIDTH - auto-adjust based on content */
    width: auto;
    min-width: auto;
    max-width: 100%;
}

/* Page Items */
.modern-pagination .page-item {
    margin: 0;
    flex-shrink: 0;
}

/* Base Page Link Styles */
.modern-pagination .page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.85);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    letter-spacing: 0.3px;
}

/* Page Numbers - Compact Size */
.modern-pagination .page-number {
    min-width: 36px;
    max-width: 36px;
    width: 36px;
    height: 36px;
    padding: 0;
    font-size: 14px;
}

/* Previous/Next Buttons - Compact */
.modern-pagination .prev-next {
    min-width: 85px;
    max-width: 85px;
    width: 85px;
    height: 36px;
    padding: 0 12px;
    font-size: 13px;
    gap: 6px;
    font-weight: 600;
}

.modern-pagination .prev-next i {
    font-size: 12px;
}

/* Ellipsis */
.modern-pagination .ellipsis {
    min-width: 44px;
    max-width: 44px;
    width: 44px;
    height: 44px;
    background: transparent !important;
    box-shadow: none !important;
    cursor: default;
    color: rgba(255, 255, 255, 0.4);
    font-size: 16px;
}

/* Hover Effect - Sleek */
.modern-pagination .page-link:hover:not(:disabled):not(.ellipsis) {
    background: rgba(255, 255, 255, 0.15);
    color: rgba(255, 255, 255, 0.95);
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
}

/* Active State - Prominent Blue */
.modern-pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: #ffffff;
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
    transform: scale(1.1);
    font-weight: 700;
}

.modern-pagination .page-item.active .page-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0) 100%);
    pointer-events: none;
    border-radius: 12px;
}

/* Disabled State */
.modern-pagination .page-link:disabled {
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.25);
    opacity: 0.6;
    cursor: not-allowed;
    box-shadow: none;
    transform: none !important;
}

/* Ripple Effect */
.modern-pagination .page-link::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.4);
    transform: translate(-50%, -50%);
    transition: width 0.5s, height 0.5s;
}

.modern-pagination .page-link:active:not(:disabled)::after {
    width: 120px;
    height: 120px;
}

/* Light Mode Override */
[data-theme="light"] .modern-pagination {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

[data-theme="light"] .modern-pagination .page-link {
    background: rgba(0, 0, 0, 0.04);
    color: #475569;
}

[data-theme="light"] .modern-pagination .page-link:hover:not(:disabled) {
    background: rgba(0, 0, 0, 0.08);
    color: #1e293b;
}

[data-theme="light"] .modern-pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: #ffffff;
}

/* Fix text color in light mode */
[data-theme="light"] .pagination-info {
    color: #4b5563 !important; /* Darker gray for better contrast */
}

[data-theme="light"] .pagination-info strong {
    color: #111827 !important; /* Almost black for strong text */
}

/* Responsive - Mobile */
@media (max-width: 576px) {
    .modern-pagination-wrapper {
        flex-direction: column;
        align-items: center; /* Center everything */
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .pagination-info {
        text-align: center;
        order: 2; /* Info below pagination */
        font-size: 0.85rem;
        width: 100%;
    }

    .modern-pagination-nav {
        order: 1;
        width: 100%;
        display: flex;
        justify-content: center;
    }

    .modern-pagination {
        width: auto; /* Allow auto width */
        min-width: 0;
        padding: 8px 12px;
        gap: 6px;
        border-radius: 10px;
    }

    /* Keep button sizes consistent with desktop/compact design */
    .modern-pagination .page-number {
        min-width: 34px;
        max-width: 34px;
        width: 34px;
        height: 34px;
        font-size: 13px;
    }

    .modern-pagination .prev-next {
        min-width: 80px;
        max-width: 80px;
        width: 80px;
        height: 34px;
        font-size: 12px;
    }
}

/* Tablet */
@media (min-width: 577px) and (max-width: 768px) {
    .modern-pagination-wrapper {
        justify-content: center;
        flex-direction: column;
        gap: 1rem;
    }
    
    .pagination-info {
        margin-bottom: 0.5rem;
    }
}

/* Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modern-pagination {
    animation: fadeInUp 0.5s ease-out;
}

/* Glow effect for active */
@keyframes glow {
    0%, 100% {
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
    }
    50% {
        box-shadow: 0 6px 28px rgba(59, 130, 246, 0.7);
    }
}

.modern-pagination .page-item.active .page-link {
    animation: glow 2s ease-in-out infinite;
}
</style>
