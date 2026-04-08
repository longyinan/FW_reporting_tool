@extends('layouts.app')

@section('title', '回答データ確認画面')

@push('head')
    @vite('resources/js/entries/ankConfirm.js')
@endpush

@section('content')
    <div id="app"
         data-id="{{ $id }}"
         data-url="{{ config('app.api_base_url') }}">
    </div>
@endsection



