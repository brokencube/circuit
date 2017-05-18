<?php

namespace Circuit;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Circuit\Interfaces\ParameterDereferencer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HandlerContainer implements Delegate
{
    /** @var Middleware|null[] Stack of middleware to call for this route */
    public $middlewareStack = [null];
    
    /** @var string The Controller class for this route */
    public $controllerClass;

    /** @var string The method to call on the Controller for this route*/
    public $controllerMethod;
    
    /** @var \Circuit\Router The router responsible for this route - this gets assigned when a route is executed */
    protected $router;

    /**
     * Store a handler against a list of middleware
     *
     * @param mixed $handler
     * @param Middleware[] $stack
     */
    public function __construct($handler, array $stack = [])
    {
        list($class, $method) = explode('@', $handler);
        
        // Trim class/namespace
        $this->controllerClass = trim($class, " \t\n\r\0\x0B\\");
        $this->controllerMethod = $method ?: 'index';
        $this->addMiddleware($stack);
    }
    
    /**
     * Add middleware to an existing handler stack
     *
     * @param Middleware[] $stack
     * @return self
     */
    public function addMiddleware(array $stack)
    {
        $this->middlewareStack = array_merge($this->middlewareStack, $stack);
        return $this;
    }

    /**
     * Start processing this route, by calling middleware in order, and then calling the specified Controller
     * This call stores various information (args, controller info) on the Request
     *
     * @param Router $router The router calling this handler
     * @param Request $request The request for the current route
     * @param array $args The matched arguments from the route
     * @return Response
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
    
    /**
     * Call middleware in order, and then call the specified Controller, using a modified PSR15 Middleware pattern
     *
     * @param Request $request The request for the current route
     * @return Response
     */
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

    /**
     * Remove $this->router when serialising this object
     */
    public function __sleep()
    {
        return ['controllerClass', 'middlewareStack', 'controllerMethod'];
    }
}
