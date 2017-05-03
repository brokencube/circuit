<?php

namespace Circuit;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HandlerContainer implements Delegate
{
    /** @var string */    
    public $route;
    
    /** @var Middleware[] */
    public $middlewareStack = [null];
    
    /**
     * Store a handler against a list of middleware
     *
     * @param mixed  $handler
     * @param Middleware[] $middleware
     */
    public function __construct($handler, array $stack = [])
    {
        $this->handler = $handler;
        $this->middlewareStack = array_merge([null], $stack);
    }
    
    /**
     * Add middleware to an existing handler stack
     *
     * @param Middleware[] $middleware
     */
    public function addMiddleware(array $stack)
    {
        $this->middlewareStack = array_merge($this->middlewareStack, $stack);
    }
    
    public function process(Request $request) : Response
    {
        $next = next($this->middlewareStack);
        if ($next !== false) {
            return $next->process($request, $this);
        } else {
            return $this->dispatchController($request);
        }
    }
    
    public function dispatchController(Request $request) : Response
    {
        if (is_callable($this->handler)) {
            $return = $this->handler($request, ...$args);
        } elseif (is_string($this->handler)) {
            list($class, $function) = explode('@', $this->handler);
            if (!$function) {
                $function = 'index';
            }
            
            // Check whether this is an absolute namespace name
            $class = trim($class, ' \t\n\r\0\x0B\\');
            
            $c = new $class;
            $return = $c->$function($request, ...$args);
        }
        
        if ($return instanceof Response) {
            return $response;
        }
        
        return new Response(
            $return,
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );        
    }
}