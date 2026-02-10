<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CsvImportController;

Route::get('/', [CsvImportController::class, 'index']);
Route::post('/upload-chunk', [CsvImportController::class, 'uploadChunk']);
Route::post('/merge-file', [CsvImportController::class, 'dispatchImport']);
Route::get('/import-status/{id}', [CsvImportController::class, 'checkStatus']);

Route::get('/docs', function () {
    return view('csv-doc');
});
