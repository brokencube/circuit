<?php

namespace Circuit\Middleware;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ParameterMatcher implements Middleware
{
    public function process(Request $request, Delegate $delegate) : Response
    {
        $controller = $request->attributes->get('class');
        $method = $request->attributes->get('method');
        $args = $request->attributes->get('args');
        
        $parameters = (new \ReflectionClass($controller))->getMethod($method)->getParameters();
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
                    unset ($args[$key]);
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
        
        $request->attributes->set('args', $newparams);
        
        return $delegate->process($request);
    }
}
