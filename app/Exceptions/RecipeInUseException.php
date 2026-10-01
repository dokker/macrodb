<?php

namespace App\Exceptions;

use RuntimeException;

class RecipeInUseException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A receptet már naplóztad, ezért nem törölhető.');
    }
}
