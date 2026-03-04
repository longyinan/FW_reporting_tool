<template>
    <Teleport to="body">
        <div
            v-if="isLoading"
            class="fixed inset-0 z-9999 grid place-items-center bg-black/30"
            aria-live="polite"
            aria-busy="true"
        >
            <div class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-900 shadow">
                Loading...
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { isLoading } from '../services/loading';
</script>
