<?php

namespace App\Http\Controllers;

use App\Http\Resources\Transaction\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionController extends Controller
{
    public function index(): JsonResource
    {
        $transactions = Transaction::all();
        return TransactionResource::collection($transactions);
    }
}
