@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <x-ui.panel title="Livewire Starter (Cloud Run friendly)">
        <p class="text-sm text-gray-600">
            This demo uses a Livewire component for UI interaction.
        </p>

        <livewire:demo :initial-count="0" />

        <p class="mt-4 text-sm text-gray-600">
            Use Livewire for complex forms and table interactions.
        </p>

        <p x-data="{text:'aaa'}" class="mt-4 text-sm text-gray-600">
            <span x-text="text"></span>
        </p>
    </x-ui.panel>
@endsection
