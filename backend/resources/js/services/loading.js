import { computed, ref } from 'vue';

const pendingCount = ref(0);

export const isLoading = computed(() => pendingCount.value > 0);

export function startLoading() {
    pendingCount.value += 1;
}

export function stopLoading() {
    pendingCount.value = Math.max(0, pendingCount.value - 1);
}
