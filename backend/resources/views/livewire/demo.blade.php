<div class="mt-4 space-y-3">
    <div class="flex items-center gap-3">
        <button
            wire:click="decrement"
            type="button"
            class="rounded bg-gray-200 px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-300"
        >
            -1
        </button>

        <span class="text-sm text-gray-700">
            Count: <strong>{{ $count }}</strong>
        </span>

        <button
            wire:click="increment"
            type="button"
            class="rounded bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
            +1
        </button>
    </div>

    <p class="text-xs text-gray-500">Current time: {{ now()->format('H:i:s') }}</p>
</div>
