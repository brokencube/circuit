<?php

namespace Circuit\ExceptionHandler;

use Circuit\Interfaces\ExceptionHandler;
use Circuit\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultHandler implements ExceptionHandler
{
    public function handle(Exception $e, Request $request, $context) : Response
    {
        return new Response(
            $e->getStatusCode() . ' ' . Response::$statusTexts[$e->getStatusCode()],
            $e->getStatusCode(),
            $e->getHeaders() + ['content-type' => 'text/html']
        );
    }
}
