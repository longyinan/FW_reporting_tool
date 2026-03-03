<template>
    <section class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Vue Counter Demo</h2>
        <p class="mt-2 text-sm text-gray-600">
            This block is rendered by Vue 3 and mounted from <code>resources/js/app.js</code>.
        </p>

        <div class="mt-4 flex items-center gap-3">
            <button
                type="button"
                class="rounded bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
                @click="plus"
            >
                plus
            </button>
            <button
                type="button"
                class="rounded bg-gray-200 px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-300"
                @click="reset"
            >
                reset
            </button>
            <span class="text-sm text-gray-700">count: {{ count }}</span>
        </div>
    </section>
</template>

<script setup>
import { ref } from 'vue';

const count = ref(0);

function plus() {
    count.value += 1;
}

function reset() {
    count.value = 0;
}


import { onMounted } from 'vue'
onMounted(() => {
  console.log(`the component is now mounted.`)
})
</script>