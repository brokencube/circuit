<?php

namespace Circuit\Exception;

class UncaughtException extends Exception
{
    public function __construct(\Throwable $previous)
    {
        parent::__construct('Uncaught Exception', null, $previous, 501);
    }
}