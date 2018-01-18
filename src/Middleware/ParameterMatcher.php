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
class ParameterMatcher implements Middleware
{
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
        $args = $params->args;
        
        $parameters = (new \ReflectionClass($params->className))->getMethod($params->method)->getParameters();
        if ($parameters[0]->name == 'request') {
            array_shift($parameters);
        }
        $count = count($parameters);
        $newparams = [];
        
        // Slot matched params in proper order
        foreach ($parameters as $var => $p) {
            foreach ($args as $key => $value) {
                if ($key == $p->name) {
                    $newparams[$var] = $value;
                    unset($args[$key]);
                }
            }
        }
        
        // Fill in any unmatched gap based on original order (minus tracks)
        for ($i = 0; $i < $count; $i++) {
            if (!array_key_exists($i, $newparams)) {
                $newparams[$i] = array_shift($args);
            }
        }
        
        ksort($newparams);
        
        $param->args = $newparams;
        
        return $delegate->process($request);
    }
}
