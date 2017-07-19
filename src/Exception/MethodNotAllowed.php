<?php

namespace Circuit\Exception;

class MethodNotAllowed extends Exception
{
    public function __construct(array $allowed, $message, $context = null, \Throwable $previous = null)
    {
        parent::__construct($message, $context, $previous, 405);
        $this->setHeaders('Allow', strtoupper(implode(', ', $allowed)));
    }
}