<?php

namespace Circuit\ExceptionHandler;
use Circuit\Interfaces\ExceptionHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DefaultHandler implements ExceptionHandler
{
    function handle(HttpException $e, Request $request, $context) : Response
    {
        return new Response(
            Response::$statusTexts[$e->getStatusCode()],
            $e->getStatusCode(),
            ['content-type' => 'text/html']
        );
    }
}