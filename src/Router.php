<?php

namespace Circuit;

use FastRoute\Dispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Router
{
    protected $options = [];
    
    public function __construct(array $options = [])
    {
        $this->options = $options + [
            'routeParser' => 'FastRoute\\RouteParser\\Std',
            'dataGenerator' => 'FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => 'FastRoute\\RouteCollector',
            'errorRoutes' => [
                '404' => ['Circuit\\Router', 'default404router']
            ],
            'prependControllerNamespace' => ''
        ];
        
        $this->router = new $this->options['routeCollector'](
            new $this->options['routeParser'], new $this->options['dataGenerator']
        );
    }
    
    public function defineRoutes(callable $routeDefinitionCallback)
    {
        $routeDefinitionCallback($this->router);
        $this->dispatcher = new $this->options['dispatcher']($this->router->getData());
    }
    
    public function run(Request $request)
    {
        list($uri) = explode('?', str_replace(chr(0), '', $request->server->get('REQUEST_URI')));
        
        $dispatch = $this->dispatcher->dispatch($request->server->get('REQUEST_METHOD'), $uri);
        switch ($dispatch[0]) {
            case Dispatcher::NOT_FOUND:
                $response = $this->options['errorRoutes']['404']($request);
                
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $dispatch[1];
                $content = '405';

                $response = new Response(
                    $content,
                    Response::HTTP_METHOD_NOT_ALLOWED,
                    array('content-type' => 'text/html')
                );
                break;
            case Dispatcher::FOUND:
                $response = $this->dispatchController($request, $dispatch);
                break;
        }
        
        $response->prepare($request);
        $response->send();
        exit;
    }
    
    protected function dispatchController(Request $request, $route)
    {
        list (,$controller,$args) = $route;
        
        if (is_callable($controller)) {
            $response = $controller($request, ...$args);
        } elseif (is_string($controller)) {
            list($class, $function) = explode('@', $controller);
            if (!$function) {
                $function = 'index';
            }
            
            // Check whether this is an absolute namespace name
            if (substr($class,0,1) == '\\') {
                $class = trim($class, ' \t\n\r\0\x0B\\');
            } else {
                if ($pre = trim($this->options['prependControllerNamespace'], ' \t\n\r\0\x0B\\')) {
                    $class = $pre . '\\' . $class;
                }
            }
            
            $c = new $class;
            $response = $c->$function($request, ...$args);
        }
        
        return new Response(
            $response,
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );
    }
    
    public function default404router()
    {
        return new Response(
            '404 Not Found',
            Response::HTTP_NOT_FOUND,
            ['content-type' => 'text/html']
        );
    }
}
