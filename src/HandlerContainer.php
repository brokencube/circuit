<?php
namespace Circuit;

use Circuit\Router;
use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Circuit\Interfaces\ParameterDereferencer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

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
     * @param string $uri      The matched route uri
     * @param array $args      The matched arguments from the route
     * @return Response
     */
    public function startProcessing(Router $router, Request $request, $uri, $args) : Response
    {
        $params = new ControllerParams($uri, $this->controllerClass, $this->controllerMethod, $args, $router->getServiceContainer());
        
        $request->attributes->set('controller', $params);
        $request->attributes->set('router', $router);
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
        $router = $request->attributes->get('router');
        
        $next = next($this->middlewareStack);
        if ($next instanceof Middleware) {
            $router->log("Router: Calling Middleware: %s", get_class($next));
            $response = $next->process($request, $this);
            $router->log("Router: Leaving Middleware: %s", get_class($next));
            return $response;
        } elseif (is_string($next)) {
            $router->log("Router: Calling Middleware: %s", $next);
            $response = $router->getMiddleware($next)->process($request, $this);
            $router->log("Router: Leaving Middleware: %s", $next);
            return $response;
        } else {
            $params = $request->attributes->get('controller');

            // Call controller with request and args
            $router->log("Router: Calling Controller: %s@%s", $params->className, $params->method);
            $return = (new $params->className($params->container))->{$params->method}($request, ...array_values($params->args));
            $router->log("Router: Controller Left");
            
            // Instantly return Response objects
            if ($return instanceof Response) {
                return $return;
            }
            
            // If not a string (or something easily castable to a string) assume Json -> JsonResponse
            if (is_array($return) or is_object($return)) {
                return new JsonResponse(
                    $return,
                    Response::HTTP_OK,
                    array('content-type' => 'application/json')
                );
            }
            
            // Strings, and other primitives.
            return new Response(
                $return,
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );
        }
    }
}
