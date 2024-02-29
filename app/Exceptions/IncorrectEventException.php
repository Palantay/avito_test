<?php

namespace App\Exceptions;

use RuntimeException;

class IncorrectEventException extends RuntimeException
{
    public function report(): bool
    {
        return true;
    }
}
