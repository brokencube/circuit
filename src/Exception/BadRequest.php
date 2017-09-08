<?php

namespace Circuit\Exception;

class BadRequest extends Exception
{
    public function __construct($message, $context = null, \Throwable $previous = null)
    {
        parent::__construct($message, $context, $previous, 400);
    }
}
