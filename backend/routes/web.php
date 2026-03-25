<?php

use App\Http\Controllers\GtInfoController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DemoController;
use App\Http\Controllers\AnsGraphController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/demo', [DemoController::class, 'index']);
Route::get('/ansGraph/{id}', [AnsGraphController::class, 'index'])->where('id', '[0-9]+');




Route::get('/gtInfo/{id}', [GtInfoController::class, 'index']);
