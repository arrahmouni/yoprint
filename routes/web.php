<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileUploadController;

Route::controller(FileUploadController::class)->name('upload.')->group(function () {
    Route::get('/'              , 'index')->name('index');
    Route::post('upload'        , 'store')->name('store');
    Route::get('upload/status'  , 'status')->name('status');
});

