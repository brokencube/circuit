<?php

namespace Circuit\Middleware;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Circuit\Router;
use Circuit\Exception;
use Circuit\ControllerParams;

use Symfony\Component\HttpFoundation\{Request, Response};
use FastRoute\Dispatcher;

/**
 * Route Matcher
 *
 * Based on the current Request object (after preroute middleware), dispatch the route
 *
 * @author Nik Barham <nik@brokencube.co.uk>
 */
class RouteMatcher implements Middleware
{
    /**
     * Constructor
     *
     * @param LoggerInterface $log Logger to log error to
     */
    public function __construct(Router $router, Dispatcher $dispatcher)
    {
        $this->router = $router;
        $this->dispatcher = $dispatcher;
    }
    
    /**
     * Run Middleware for a particular request
     *
     * @param Request  $request    HTTP Foundation Request object
     * @param Delegate $delegate   Router
     * @return Response
     */
    public function process(Request $request, Delegate $delegate) : Response
    {
        // Null byte poisoning protection
        list($uri) = explode('?', str_replace(chr(0), '', $request->server->get('REQUEST_URI')));
        $dispatch = $this->dispatcher->dispatch($request->server->get('REQUEST_METHOD'), $uri);
        switch ($dispatch[0]) {
            case Dispatcher::NOT_FOUND:
                $this->log("Router: Route not matched");
                throw new Exception\NotFound('Router: Route not matched');
            
            case Dispatcher::METHOD_NOT_ALLOWED:
                $this->log("Router: Method not Allowed");
                throw new Exception\MethodNotAllowed(
                    $dispatch[1],
                    'Router: Method not Allowed: ' . $request->getMethod()
                );
            
            case Dispatcher::FOUND:
                $dispatcher = unserialize($dispatch[1]);
                $this->log(
                    "Router: Route matched: %s@%s",
                    $dispatcher->controllerClass,
                    $dispatcher->controllerMethod
                );
                
                $params = new ControllerParams($uri, $dispatcher->controllerClass, $dispatcher->controllerMethod, $dispatch[2], $this->router->getServiceContainer());
                $request->attributes->set('controller', $params);
                $request->attributes->set('router', $this->router);
                
                $this->router->addMiddleware(...$dispatcher->middlewareStack);
                $this->router->addMiddleware(new DispatchController($this->router));
                return $delegate->process($request);
        }
    }
    
    protected function log($message, ...$args)
    {
        $this->router->log($message, ...$args);
    }
}
