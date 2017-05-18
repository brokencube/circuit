<?php

namespace Circuit\ExceptionHandler;
use Circuit\Interfaces\ExceptionHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NotFoundHandler implements ExceptionHandler
{
    function handle(HttpException $e, Request $request, $context) : Response
    {
        return new Response(
            '404 Not Found',
            Response::HTTP_NOT_FOUND,
            ['content-type' => 'text/html']
        );
    }
}