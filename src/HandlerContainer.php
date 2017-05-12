<?php

namespace Circuit;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Circuit\Interfaces\ParameterDereferencer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HandlerContainer implements Delegate
{
    /** @var Middleware|null[] */
    public $middlewareStack = [null];
    
    /** @var mixed */
    public $handler;
    
    /** @var mixed[] */
    protected $controllerArguments = [];
    
    /** @var Circuit\Router */
    protected $router;

    /** @var array */
    protected $args;
    
    /**
     * Store a handler against a list of middleware
     *
     * @param mixed  $handler
     * @param Middleware[] $middleware
     */
    public function __construct($handler, array $stack = [])
    {
        $this->handler = $handler;
        $this->addMiddleware($stack);
    }
    
    /**
     * Add middleware to an existing handler stack
     *
     * @param Middleware[] $middleware
     */
    public function addMiddleware(array $stack)
    {
        $this->middlewareStack = array_merge($this->middlewareStack, $stack);
    }

    /**
     * Set the router about to call this handler
     */
    public function startProcessing(Router $router, Request $request, $args) : Response
    {
        $this->router = $router;
        $this->args = $args;
        return $this->process($request);
    }
    
    public function process(Request $request) : Response
    {
        $next = next($this->middlewareStack);
        if ($next instanceof Middleware) {
            return $next->process($request, $this);
        } elseif (is_string($next)) {
            return $this->router->getMiddleware($next)->process($request, $this);
        } else {
            return $this->dispatchController($request);
        }
    }
    
    public function dispatchController(Request $request) : Response
    {
        if (is_callable($this->handler)) {
            $return = $this->handler($request, ...$this->args);
        } elseif (is_string($this->handler)) {
            list($class, $function) = explode('@', $this->handler);
            if (!$function) {
                $function = 'index';
            }
            
            // Trim class/namespace
            $class = trim($class, ' \t\n\r\0\x0B\\');
            $c = new $class(...$this->controllerArguments);
            
            $args = $this->matchArguments($c, $function, $this->args);
            
            $return = $c->$function($request, ...$args);
        }
        
        if ($return instanceof Response) {
            return $return;
        }
        
        return new Response(
            $return,
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );        
    }
    
    protected function matchArguments($controller, $function, $args) : array
    {
        $parameters = (new \ReflectionClass($controller))->getMethod($function)->getParameters();
        if ($parameters[0]->name == 'request') {
            array_shift($parameters);
        }
        $count = count($parameters);
        $newparams = [];
        
        // Slot matched params in proper order
        foreach ($parameters as $var => $p) {
            foreach ($args as $key => $value) {
                if ($key == $p->name) {
                    if ($this->router->parameterDereferencer instanceof ParameterDereferencer) {
                        $newparams[$var] = $this->router->parameterDereferencer->dereference($p, $value);
                    } else {
                        $newparams[$var] = $value;
                    }
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
        return $newparams;
    }
    
    public function setControllerArguments(...$args)
    {
        $this->controllerArguments = $args;
        return $this;
    }
    
    public function __sleep()
    {
        return ['handler', 'middlewareStack'];
    }
}
