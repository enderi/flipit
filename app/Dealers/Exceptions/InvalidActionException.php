<?php

namespace App\Dealers\Exceptions;

class InvalidActionException extends \Exception
{
    public function __construct($msg)
    {
        parent::__construct($msg);
    }
}
