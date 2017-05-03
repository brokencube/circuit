<?php

namespace Circuit;

use Circuit\Interfaces\Middleware as MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

function Middleware implements MiddlewareInterface {
    public function process(Request $request, Delegate $next) : Response;
}