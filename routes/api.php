<?php

use App\Http\Controllers\DailyTargetController;
use App\Http\Controllers\FoodAliasController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\MealItemController;
use App\Http\Controllers\MealPhotoController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('/foods', [FoodController::class, 'index']);
    Route::get('/foods/barcode/{code}', [FoodController::class, 'barcode']);
    Route::post('/foods', [FoodController::class, 'store']);
    Route::post('/foods/{food}/aliases', [FoodAliasController::class, 'store']);
    Route::get('/recipes', [RecipeController::class, 'index']);
    Route::get('/recipes/{recipe}', [RecipeController::class, 'show']);
    Route::post('/recipes', [RecipeController::class, 'store']);
    Route::put('/recipes/{recipe}', [RecipeController::class, 'update']);
    Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy']);
    Route::post('/meals', [MealController::class, 'store']);
    Route::post('/meals/photo', [MealPhotoController::class, 'store']);
    Route::patch('/meal-items/{mealItem}', [MealItemController::class, 'update']);
    Route::delete('/meal-items/{mealItem}', [MealItemController::class, 'destroy']);
    Route::get('/targets', [DailyTargetController::class, 'index']);
    Route::post('/targets', [DailyTargetController::class, 'store']);
    Route::delete('/targets/{dailyTarget}', [DailyTargetController::class, 'destroy']);
    Route::get('/summary/daily', [SummaryController::class, 'daily']);
    Route::get('/summary/range', [SummaryController::class, 'range']);
});
