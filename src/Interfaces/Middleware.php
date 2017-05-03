<?php

namespace Circuit\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface Middleware {
    public function process(Request $request, Delegate $next) : Response;
}