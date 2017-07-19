<?php

namespace Circuit\Exception;
use Circuit\Interfaces\ExceptionContextInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

class Exception extends \Exception implements HttpExceptionInterface, ExceptionContextInterface
{
    public $context;
    public $statusCode;
    public $headers = [];
    
    public function __construct($message, $context = null, Throwable $previous = null, int $code = 500)
    {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $code;
        $this->context = $context;
    }
    
    public function getContext()
    {
        return $this->context;
    }
    
    public function getStatusCode() : int
    {
        return $this->statusCode;
    }
    
    public function getHeaders() : array
    {
        return $this->headers;
    }
    
    public function setHeaders($header, $value)
    {
        $this->headers[$header] = $value;
    }    
}