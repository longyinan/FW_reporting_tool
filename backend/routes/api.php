<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/demo', function (Request $request) {
    sleep(3);
    return json_encode([
        'title' => 'demo'
    ]);
});