@extends('layouts.app')

@section('title', 'Demo')

@push('head')
    @vite('resources/js/entries/demo.js')
@endpush

@section('content')
<div id="app"></div>
@endsection
