<?php

namespace Circuit;

use Circuit\Interfaces\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FastRoute\RouteParser;
use FastRoute\DataGenerator;

class RouteCollector
{
    /** @var RouteParser */
    protected $routeParser;
    
    /** @var DataGenerator */
    protected $dataGenerator;
    
    /** @var string */
    protected $currentGroupPrefix;
    protected $currentMiddlewareStack = [];
    
    /**
     * Constructs a route collector.
     *
     * @param RouteParser   $routeParser
     * @param DataGenerator $dataGenerator
     * @param Middleware[]  $middleware
     */
    public function __construct(RouteParser $routeParser, DataGenerator $dataGenerator, array $middleware = [])
    {
        $this->routeParser = $routeParser;
        $this->dataGenerator = $dataGenerator;
        $this->currentGroupPrefix = '';
        $this->currentMiddlewareStack = $middleware;
    }
    
    /**
     * Adds a route to the collection.
     *
     * The syntax used in the $route string depends on the used route parser.
     *
     * @param string|string[] $httpMethod
     * @param string $route
     * @param mixed  $handler
     * @param Middleware[] $middleware
     */
    public function addRoute($httpMethod, $route, $handler, array $middleware = [])
    {
        if (!$handler instanceof HandlerContainer) {
            $handler = new HandlerContainer($handler, array_merge($this->currentMiddlewareStack, $middleware));
        } else {
            $handler->addMiddleware(array_merge($this->currentMiddlewareStack, $middleware));
        }
        
        $route = $this->currentGroupPrefix . $route;
        $routeDatas = $this->routeParser->parse($route);
        foreach ((array) $httpMethod as $method) {
            foreach ($routeDatas as $routeData) {
                $this->dataGenerator->addRoute($method, $routeData, $handler);
            }
        }
    }
    
    /**
     * Create a route group with a common prefix.
     *
     * All routes created in the passed callback will have the given group prefix prepended.
     *
     * @param string $prefix
     * @param Middleware[] $middleware
     * @param callable $callback
     */
    public function addGroup($prefix, array $middleware, callable $callback)
    {
        $previousMiddlewareStack = $this->currentMiddlewareStack;
        $previousGroupPrefix = $this->currentGroupPrefix;
        $this->currentGroupPrefix = $previousGroupPrefix . $prefix;
        $this->currentMiddlewareStack = array_merge($previousMiddlewareStack, $middleware);
        $callback($this);
        $this->currentGroupPrefix = $previousGroupPrefix;
        $this->currentMiddlewareStack = $previousMiddlewareStack;
    }
    
    /**
     * Adds a GET route to the collection
     * 
     * This is simply an alias of $this->addRoute('GET', $route, $handler, $middleware)
     *
     * @param string $route
     * @param mixed  $handler
     * @param Middleware[] $middleware
     */
    public function get($route, $handler, array $middleware = []) {
        $this->addRoute('GET', $route, $handler, $middleware);
    }
    
    /**
     * Adds a POST route to the collection
     * 
     * This is simply an alias of $this->addRoute('POST', $route, $handler, $middleware)
     *
     * @param string $route
     * @param mixed  $handler
     * @param Middleware[] $middleware
     */
    public function post($route, $handler, array $middleware = []) {
        $this->addRoute('POST', $route, $handler, $middleware);
    }
    
    /**
     * Adds a PUT route to the collection
     * 
     * This is simply an alias of $this->addRoute('PUT', $route, $handler, $middleware)
     *
     * @param string $route
     * @param mixed  $handler
     * @param Middleware[] $middleware
     */
    public function put($route, $handler, array $middleware = []) {
        $this->addRoute('PUT', $route, $handler, $middleware);
    }
    
    /**
     * Adds a DELETE route to the collection
     * 
     * This is simply an alias of $this->addRoute('DELETE', $route, $handler, $middleware)
     *
     * @param string $route
     * @param mixed  $handler
     * @param Middleware[] $middleware
     */
    public function delete($route, $handler, array $middleware = []) {
        $this->addRoute('DELETE', $route, $handler, $middleware);
    }
    
    /**
     * Adds a PATCH route to the collection
     * 
     * This is simply an alias of $this->addRoute('PATCH', $route, $handler, $middleware)
     *
     * @param string $route
     * @param mixed  $handler
     * @param Middleware[] $middleware
     */
    public function patch($route, $handler, array $middleware = []) {
        $this->addRoute('PATCH', $route, $handler, $middleware);
    }
    /**
     * Adds a HEAD route to the collection
     *
     * This is simply an alias of $this->addRoute('HEAD', $route, $handler, $middleware)
     *
     * @param string $route
     * @param mixed  $handler
     * @param Middleware[] $middleware
     */
    public function head($route, $handler, array $middleware = []) {
        $this->addRoute('HEAD', $route, $handler, $middleware);
    }
    /**
     * Returns the collected route data, as provided by the data generator.
     *
     * @return array
     */
    public function getData() {
        return $this->dataGenerator->getData();
    }
}