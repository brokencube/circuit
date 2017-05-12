<?php

namespace Circuit;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Circuit\Interfaces\ParameterDereferencer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HandlerContainer implements Delegate
{
    /** @var Middleware|null[] */
    public $middlewareStack = [null];
    
    /** @var string */
    public $controllerClass;

    /** @var string */
    public $controllerMethod;
    
    /** @var Circuit\Router */
    protected $router;

    /**
     * Store a handler against a list of middleware
     *
     * @param mixed $handler
     * @param Middleware[] $middleware
     */
    public function __construct($handler, array $stack = [])
    {
        list($class, $method) = explode('@', $handler);
        
        // Trim class/namespace
        $this->controllerClass = trim($class, ' \t\n\r\0\x0B\\');
        $this->controllerMethod = $method ?: 'index';
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
    public function startProcessing(Router $router, Request $request, $args) : Response
    {
        $this->router = $router;
        $request->attributes->set('args', $args);
        $request->attributes->set('class', $this->controllerClass);
        $request->attributes->set('method', $this->controllerMethod);
        $request->attributes->set('constructor', $this->router->controllerArguments);
        return $this->process($request);
    }
    
    public function process(Request $request) : Response
    {
        $next = next($this->middlewareStack);
        if ($next instanceof Middleware) {
            return $next->process($request, $this);
        } elseif (is_string($next)) {
            return $this->router->getMiddleware($next)->process($request, $this);
        } else {
            $args = $request->attributes->get('args');
            $constructerArgs = $request->attributes->get('constructor');
            // Call controller with request and args
            $return = (new $this->controllerClass(...$constructerArgs))->{$this->controllerMethod}($request, ...$args);
            
            if ($return instanceof Response) {
                return $return;
            }
            
            return new Response(
                $return,
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );        
        }
    }
    
    public function __sleep()
    {
        return ['controllerClass', 'middlewareStack', 'controllerMethod'];
    }
}
