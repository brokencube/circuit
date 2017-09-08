<?php

namespace Circuit\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Circuit\Exception\Exception;

interface ExceptionHandler
{
    public function handle(Exception $e, Request $request) : Response;
}
