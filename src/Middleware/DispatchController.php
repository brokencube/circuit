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
class DispatchController implements Middleware
{
    /**
     * Constructor
     *
     * @param LoggerInterface $log Logger to log error to
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
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
        $params = $request->attributes->get('controller');

        // Call controller with request and args
        $this->log("Router: Calling Controller: %s@%s", $params->className, $params->method);
        $return = (new $params->className($params->container))->{$params->method}($request, ...array_values($params->args));
        $this->log("Router: Controller Left");
        
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
