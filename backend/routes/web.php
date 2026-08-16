<?php
/* BISM4RCK-KUN3H0 2026 */

use Illuminate\Support\Facades\Route;

Route::get('/{any?}', function () {
    $index = public_path('index.html');

    abort_unless(is_file($index), 503, 'Smart Gate frontend is not built.');

    return response()->file($index);
})->where('any', '.*');
