<?php

namespace Circuit;

use Circuit\Interfaces\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HandlerContainer
{
    /** @var string */    
    public $route;
    
    /** @var Middleware[] */
    public $middlewareStack = [];
    
    public function __construct($handler, array $stack = [])
    {
        $this->handler = $handler;
        $this->middlewareStack = $stack;
    }
}