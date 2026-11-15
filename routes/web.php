<?php

use Illuminate\Support\Facades\Route;

Route::get('/geo/{name}', function (string $name) {
    abort_unless(preg_match('/^[a-zA-Z0-9._-]+$/', $name), 404);
    $path = public_path('geo/'.$name);
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Content-Type' => 'application/json']);
});

Route::get('/tarif-shartnomasi/yuklab', function () {
    $path = public_path('docs/tarif-shartnomasi.html');
    abort_unless(is_file($path), 404);

    return response()->download($path, 'BOSS-FISH-tarif-shartnomasi.html', [
        'Content-Type' => 'text/html; charset=UTF-8',
    ]);
});

Route::view('/{any?}', 'app')->where('any', '.*');
