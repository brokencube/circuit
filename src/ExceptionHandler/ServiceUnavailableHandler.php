<?php

namespace Circuit\ExceptionHandler;
use Circuit\Interfaces\ExceptionHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceUnavailableHandler implements ExceptionHandler
{
    function handle(\HttpException $e, Request $request, $context) : Response
    {
        return new Response(
            '503 Service Unavailable',
            Response::HTTP_SERVICE_UNAVAILABLE,
            ['content-type' => 'text/html']
        );
    }
}