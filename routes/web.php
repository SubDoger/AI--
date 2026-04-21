<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function () {
    $indexFile = public_path('index.html');

    abort_unless(file_exists($indexFile), 404);

    return response()->file($indexFile);
})->where('path', '^(?!api).*$');
