<?php

namespace App\Dealers\Exceptions;

class NewHandRequestedException  extends \Exception
{
    public function __construct()
    {
        parent::__construct("");
    }
}
