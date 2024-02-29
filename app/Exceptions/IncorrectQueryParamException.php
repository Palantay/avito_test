<?php

namespace App\Exceptions;

use RuntimeException;

class IncorrectQueryParamException extends RuntimeException
{
    public function report(): bool
    {
        return true;
    }
}
