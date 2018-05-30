<?php

namespace Circuit\Middleware;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parameter Matcher
 *
 * A middleware that reorders controller method parameters so that named parameters in the method signature
 * match to named parameters in the router definition. Any unmatched parameters will be called in their original
 * order
 *
 * @author Nik Barham <nik@brokencube.co.uk>
 */
class AutowireController implements Middleware
{
    /**
     * Constructor
     *
     * @param ContainerInterface $container PSR-11 container to use for autowiring
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
    
    /**
     * Set Controller constructor params using autowiring from container
     *
     * @param Request  $request    HTTP Foundation Request object
     * @param Delegate $delegate   Either the Router or HandlerContainer, depending on whether this is run pre or post
     *                             routing
     * @return Response
     */
    public function process(Request $request, Delegate $delegate) : Response
    {
        // Get the list of __construct params for the current controller
        $params = $request->attributes->get('controller');
        $parameters = (new \ReflectionClass($params->className))->getMethod('__construct')->getParameters();
        $args = [];
        
        // Determine constructor matches by name
        foreach ($parameters as $param) {
            
            if ($this->container->has($param->name)) {
                $args[] = $this->container->get($param->name);
            } else {
                $args[] = null;
            }
        }
        
        // Set constructor arguments
        $params->constructorArgs = $args;
        
        // Continue
        return $delegate->process($request);
    }
}
