<?php

namespace Circuit\Exception;

class NotFound extends Exception
{
    public function __construct($message, $context = null, \Throwable $previous = null)
    {
        parent::__construct($message, $context, $previous, 404);
    }
}