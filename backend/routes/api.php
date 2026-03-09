<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnsGraphController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/demo', function (Request $request) {
    sleep(3);
    return json_encode([
        'title' => 'demo'
    ]);
});

Route::post('/ansGraph/{ank_id}/showGraph', [AnsGraphController::class, 'showGraph'])->where('ank_id', '[0-9]+');