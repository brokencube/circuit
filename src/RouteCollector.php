<?php

namespace Circuit;

use Circuit\Interfaces\Middleware;
use FastRoute\RouteParser;
use FastRoute\DataGenerator;

/**
 * Based on nikic\FastRoute RouteCollector, but with added Middleware support
 *
 * @author Nik Barham <nik@brokencube.co.uk>
 */
class RouteCollector
{
    /** @var RouteParser FastRoute compatible Route Parser as set in Router::$options */
    protected $routeParser;
    
    /** @var DataGenerator FastRoute compatible Data Generator as set in Router::$options */
    protected $dataGenerator;
    
    /** @var string Route prefix for current group(s) */
    protected $currentGroupPrefix;
    
    /** @var Middleware|string[] Stack of middleware to append to defined routes. Will be temporarily added to during
                                 ->addGroup() calls */
    protected $middlewareStack = [];
    
    /**
     * Constructs a route collector.
     *
     * @param RouteParser          $routeParser    FastRoute compatible Route Parser as set in Router::$options
     * @param DataGenerator        $dataGenerator  FastRoute compatible Data Generator as set in Router::$options
     * @param Middleware|string[]  $middleware     Array of middle ware to apply to all routes
     */
    public function __construct(RouteParser $routeParser, DataGenerator $dataGenerator, array $middleware = [])
    {
        $this->routeParser = $routeParser;
        $this->dataGenerator = $dataGenerator;
        $this->currentGroupPrefix = '';
        $this->middlewareStack = $middleware;
    }
    
    /**
     * Adds a route to the collection.
     *
     * The syntax used in the $route string depends on the used route parser.
     *
     * @param string|string[] $httpMethod      HTTP Verb(s) for this route
     * @param string $route                    The route
     * @param mixed  $handler                  Either a handler specified Laravel style: "ControllerClass@MethodName"
     *                                         or a pregenerated HandlerContainer object
     * @param Middleware|string[] $middleware  List of middleware (Middleware objects or named middleware) to add to
     *                                         this route
     */
    public function addRoute($httpMethod, $route, $handler, array $middleware = [])
    {
        if (!$handler instanceof HandlerContainer) {
            $handler = new HandlerContainer($handler, array_merge($this->middlewareStack, $middleware));
        } else {
            $handler->addMiddleware(array_merge($this->middlewareStack, $middleware));
        }
        
        $handler = serialize($handler);
        
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
     * @param string              $prefix      String to be prepended to this route
     * @param Middleware|string[] $middleware  Middleware to be added to all routes in this group
     * @param callable            $callback    Definition callback
     */
    public function addGroup($prefix, array $middleware, callable $callback)
    {
        $previousMiddlewareStack = $this->middlewareStack;
        $previousGroupPrefix = $this->currentGroupPrefix;
        $this->currentGroupPrefix = $previousGroupPrefix . $prefix;
        $this->middlewareStack = array_merge($previousMiddlewareStack, $middleware);
        $callback($this);
        $this->currentGroupPrefix = $previousGroupPrefix;
        $this->middlewareStack = $previousMiddlewareStack;
    }
    
    /**
     * Adds a GET route to the collection
     *
     * This is simply an alias of $this->addRoute('GET', $route, $handler, $middleware)
     *
     * @param string              $route       Defined route
     * @param mixed               $handler     Either a handler specified Laravel style: "ControllerClass@MethodName"
     *                                         or a pregenerated HandlerContainer object
     * @param Middleware|string[] $middleware  List of middleware (Middleware objects or named middleware) to add to
     *                                         this route
     */
    public function get($route, $handler, array $middleware = [])
    {
        $this->addRoute('GET', $route, $handler, $middleware);
    }
    
    /**
     * Adds a POST route to the collection
     *
     * This is simply an alias of $this->addRoute('POST', $route, $handler, $middleware)
     *
     * @param string              $route       Defined route
     * @param mixed               $handler     Either a handler specified Laravel style: "ControllerClass@MethodName"
     *                                         or a pregenerated HandlerContainer object
     * @param Middleware|string[] $middleware  List of middleware (Middleware objects or named middleware) to add to
     *                                         this route
     */
    public function post($route, $handler, array $middleware = [])
    {
        $this->addRoute('POST', $route, $handler, $middleware);
    }
    
    /**
     * Adds a PUT route to the collection
     *
     * This is simply an alias of $this->addRoute('PUT', $route, $handler, $middleware)
     *
     * @param string              $route       Defined route
     * @param mixed               $handler     Either a handler specified Laravel style: "ControllerClass@MethodName"
     *                                         or a pregenerated HandlerContainer object
     * @param Middleware|string[] $middleware  List of middleware (Middleware objects or named middleware) to add to
     *                                         this route
     */
    public function put($route, $handler, array $middleware = [])
    {
        $this->addRoute('PUT', $route, $handler, $middleware);
    }
    
    /**
     * Adds a DELETE route to the collection
     *
     * This is simply an alias of $this->addRoute('DELETE', $route, $handler, $middleware)
     *
     * @param string              $route       Defined route
     * @param mixed               $handler     Either a handler specified Laravel style: "ControllerClass@MethodName"
     *                                         or a pregenerated HandlerContainer object
     * @param Middleware|string[] $middleware  List of middleware (Middleware objects or named middleware) to add to
     *                                         this route
     */
    public function delete($route, $handler, array $middleware = [])
    {
        $this->addRoute('DELETE', $route, $handler, $middleware);
    }
    
    /**
     * Adds a PATCH route to the collection
     *
     * This is simply an alias of $this->addRoute('PATCH', $route, $handler, $middleware)
     *
     * @param string              $route       Defined route
     * @param mixed               $handler     Either a handler specified Laravel style: "ControllerClass@MethodName"
     *                                         or a pregenerated HandlerContainer object
     * @param Middleware|string[] $middleware  List of middleware (Middleware objects or named middleware) to add to
     *                                         this route
     */
    public function patch($route, $handler, array $middleware = [])
    {
        $this->addRoute('PATCH', $route, $handler, $middleware);
    }
    
    /**
     * Adds a HEAD route to the collection
     *
     * This is simply an alias of $this->addRoute('HEAD', $route, $handler, $middleware)
     *
     * @param string              $route       Defined route
     * @param mixed               $handler     Either a handler specified Laravel style: "ControllerClass@MethodName"
     *                                         or a pregenerated HandlerContainer object
     * @param Middleware|string[] $middleware  List of middleware (Middleware objects or named middleware) to add to
     *                                         this route
     */
    public function head($route, $handler, array $middleware = [])
    {
        $this->addRoute('HEAD', $route, $handler, $middleware);
    }
    
    /**
     * Returns the collected route data, as provided by the data generator.
     *
     * @return array
     */
    public function getData()
    {
        return $this->dataGenerator->getData();
    }
}
