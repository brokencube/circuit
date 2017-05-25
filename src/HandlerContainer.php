<?php
namespace Circuit;

use Circuit\Router;
use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Circuit\Interfaces\ParameterDereferencer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A container representing a route target (i.e. Controller) and a list of middleware for that route
 * It is also responsible for dispatching all middleware before calling the controller.
 *
 * @author Nik Barham <nik@brokencube.co.uk>
 */
class HandlerContainer implements Delegate
{
    /** @var Middleware|string|null[]  Stack of middleware to call for this route */
    public $middlewareStack = [null];
    
    /** @var string  The Controller class for this route */
    public $controllerClass;

    /** @var string  The method to call on the Controller for this route */
    public $controllerMethod;
    
    /** @var Router  The router responsible for this route - this gets assigned when a route is executed */
    protected $router;

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

    /**
     * Start processing this route, by calling middleware in order, and then calling the specified Controller
     * This call stores various information (args, controller info) on the Request
     *
     * @param Router $router   The router calling this handler
     * @param Request $request The request for the current route
     * @param array $args      The matched arguments from the route
     * @return Response
     */
    public function startProcessing(Router $router, Request $request, $args) : Response
    {
        $this->router = $router;
        $request->attributes->set('args', $args);
        $request->attributes->set('class', $this->controllerClass);
        $request->attributes->set('method', $this->controllerMethod);
        $request->attributes->set('constructor', $this->router->getControllerArguments());
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
            $this->router->log("Router: Calling Middleware: %s", get_class($next));
            $response = $next->process($request, $this);
            $this->router->log("Router: Leaving Middleware: %s", get_class($next));
            return $response;
        } elseif (is_string($next)) {
            $this->router->log("Router: Calling Middleware: %s", $next);
            $response = $this->router->getMiddleware($next)->process($request, $this);
            $this->router->log("Router: Leaving Middleware: %s", $next);
            return $response;
        } else {
            $args = $request->attributes->get('args');
            $constructerArgs = $request->attributes->get('constructor');
            
            // Call controller with request and args
            $this->router->log("Router: Calling Controller: %s@%s", $this->controllerClass, $this->controllerMethod);
            $return = (new $this->controllerClass(...$constructerArgs))->{$this->controllerMethod}($request, ...$args);
            $this->router->log("Router: Controller Left");
            
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
