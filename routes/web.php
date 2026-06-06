<?php

use Arhx\Improveme\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('improveme.prefix', 'improveme'), '/');
$controller = config('improveme.controller');

Route::prefix($prefix)->group(function () use ($controller) {
    Route::get('widget.js', [WidgetController::class, 'script'])->name('improveme.widget');
    Route::post('report', $controller)->name('improveme.report');
});
