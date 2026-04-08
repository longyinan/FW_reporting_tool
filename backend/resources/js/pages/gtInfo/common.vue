<template>
    <div v-if="visible" class="loading-mask">
        <div class="loading-spinner"></div>
    </div>
</template>

<script setup>
defineProps({
    visible: {
        type: Boolean,
        default: false
    }
})
</script>


<style scoped>
.loading-mask {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(255, 255, 255, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    backdrop-filter: blur(2px);
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .loading-spinner {
        width: 40px;
        height: 40px;
        border-width: 4px;
    }
}
</style>
