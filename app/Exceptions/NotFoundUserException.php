<?php

namespace App\Exceptions;

use RuntimeException;

class NotFoundUserException extends RuntimeException
{
    public function report(): bool
    {
        return true;
    }
}
