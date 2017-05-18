<?php

namespace Circuit\Interfaces;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

interface ExceptionHandler
{
    function handle(\HttpException $e, Request $request, $context) : Response;
}