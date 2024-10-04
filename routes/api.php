<?php

use App\Http\Controllers\PlantHealthController;
use App\Http\Controllers\S3Controller;
use Illuminate\Support\Facades\Route;

Route::get('/test-json', [PlantHealthController::class, 'returnTestData']);

Route::post('/generate-presigned-url', [S3Controller::class, 'generatePresignedUrl']);
Route::post('/confirm-upload', [S3Controller::class, 'confirmUpload']);
Route::post('/analyze-plant', [S3Controller::class, 'analyzePlant']);
Route::get('/test-file-exists', [S3Controller::class, 'testFileExists']);
Route::get('/test-image', [S3Controller::class, 'testFile']);
// Route::post('/check-plant-health', [PlantHealthController::class, 'analyzeImage']);
