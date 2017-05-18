<?php

namespace Circuit\ExceptionHandler;
use Circuit\Interfaces\ExceptionHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InternalServerErrorHandler implements ExceptionHandler
{
    function handle(\HttpException $e, Request $request, $context) : Response
    {
        return new Response(
            '500 Internal Server Error',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ['content-type' => 'text/html']
        );
    }
}