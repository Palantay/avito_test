<?php

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::get('users', [UserController::class, 'index']);
Route::delete('users/{id}', [UserController::class, 'destroy']);
Route::post('users', [UserController::class, 'store']);
Route::get('users/{id}/', [UserController::class, 'show']);
Route::post('users/{id}/transaction', [UserController::class, 'updateBalance']);
Route::post('users/{id}/transaction/{toUser}', [UserController::class, 'transactionsUserToUser']);
Route::get('users/{id}/transaction', [UserController::class, 'getTransactionsByUserID']);

Route::get('transactions', [TransactionController::class, 'index']);
