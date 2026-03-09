<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Chat test interface (DEV mode only)
if (config('agent.mode') === 'DEV') {
    Route::get('/chat', function () {
        return view('chat');
    })->name('chat');
}
