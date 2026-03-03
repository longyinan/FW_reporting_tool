<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home', ['num' => 1]);
});

Route::get('/user', function () {
    return view('pages.user', ['num' => 1]);
});