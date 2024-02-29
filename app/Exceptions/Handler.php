<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {

        });

        $this->renderable(function (NotFoundUserException $e, $request) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        });

        $this->renderable(function (InsufficientFundsException $e, $request) {
            return response()->json([
                'message' => 'Insufficient funds for financial transaction'
            ], 402);
        });

        $this->renderable(function (IncorrectEventException $e, $request) {
            return response()->json([
                'message' => 'Incorrect event value'
            ], 400);
        });

        $this->renderable(function (IncorrectQueryParamException $e, $request) {
            return response()->json([
                'message' => 'Incorrect query parameters'
            ], 400);
        });

    }
}
