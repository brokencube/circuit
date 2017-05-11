<?php

namespace Circuit;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HandlerContainer implements Delegate
{
    /** @var Middleware|null[] */
    public $middlewareStack = [null];
    
    /** @var mixed */
    public $handler;
    
    /** @var mixed[] */
    protected $controllerArguments = [];
    
    /** @var Circuit\Router */
    protected $router;
    
    /**
     * Store a handler against a list of middleware
     *
     * @param mixed  $handler
     * @param Middleware[] $middleware
     */
    public function __construct($handler, array $stack = [])
    {
        $this->handler = $handler;
        $this->addMiddleware($stack);
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

    /**
     * Set the router about to call this handler
     */
    public function startProcessing(Router $router, Request $request) : Response
    {
        $this->router = $router;
        return $this->process($request);
    }
    
    public function process(Request $request) : Response
    {
        $next = next($this->middlewareStack);
        if ($next instanceof Middleware) {
            return $next->process($request, $this);
        }
        elseif (is_string($next)) {
            return $this->router->getMiddleware($next)->process($request, $this);
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
            
            // Trim class/namespace
            $class = trim($class, ' \t\n\r\0\x0B\\');
            
            $c = new $class(...$this->controllerArguments);
            $return = $c->$function($request, ...$args);
        }
        
        if ($return instanceof Response) {
            return $return;
        }
        
        return new Response(
            $return,
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );        
    }
    
    public function setControllerArguments(...$args)
    {
        $this->controllerArguments = $args;
    }
    
    public function __sleep()
    {
        return ['handler', 'middlewareStack'];
    }
}