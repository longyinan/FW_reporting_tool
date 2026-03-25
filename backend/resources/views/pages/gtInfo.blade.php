@extends('layouts.app')

@section('title', 'GT')

@push('head')
    @vite('resources/js/entries/gtInfo.js')
@endpush

@section('content')
    <div id="app"
         data-id="{{ $id }}"
         data-url="{{ config('app.api_base_url') }}">
    </div>
@endsection



