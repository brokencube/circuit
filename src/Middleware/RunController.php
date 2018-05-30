<?php

namespace Circuit\Middleware;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Circuit\Exception;
use Circuit\ControllerParams;
use FastRoute\Dispatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
    public function __construct(Router $router, LoggerInterface $log, Dispatcher $dispatcher)
    {
        $this->router = $router;
        $this->log = $log;
        $this->dispatcher = $dispatcher;
    }
    
    /**
     * Run Middleware for a particular request
     *
     * @param Request  $request    HTTP Foundation Request object
     * @param Delegate $delegate   Either the Router or HandlerContainer, depending on whether this is run pre or post
     *                             routing
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
                
                $router->addMiddleware($dispatcher->middlewareStack);
                return $delegate->process($request);
        }
    }
}
