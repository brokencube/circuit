<?php
namespace Circuit;

/**
 * A container representing a route target (i.e. Controller) and a list of middleware for that route
 *
 * @author Nik Barham <nik@brokencube.co.uk>
 */
class HandlerContainer
{
    /** @var Middleware|string|null[]  Stack of middleware to call for this route */
    public $middlewareStack = [];
    
    /** @var string  The Controller class for this route */
    public $controllerClass;

    /** @var string  The method to call on the Controller for this route */
    public $controllerMethod;
    
    /**
     * Store a handler against a list of middleware
     *
     * @param mixed               $handler  Name of the controller that will be called for this route. Must be supplied
     *                                      Laravel style - "ControllerClass@MethodName"
     * @param Middleware|string[] $stack    A list of middleware (Middleware Objects or named Middleware) to be called
     *                                      before the controller
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
     * @param Middleware|string[] $stack Middleware to add to this route.
     * @return self
     */
    public function addMiddleware(array $stack)
    {
        $this->middlewareStack = array_merge($this->middlewareStack, $stack);
        return $this;
    }
}
