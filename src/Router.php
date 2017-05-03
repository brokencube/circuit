<?php

namespace brokencube\Circuit;

use FastRoute\Dispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Router
{
    protected $options = [];
    
    public function __construct(array $options = [])
    {
        $this->options += [
            'routeParser' => 'FastRoute\\RouteParser\\Std',
            'dataGenerator' => 'FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => 'FastRoute\\RouteCollector',
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
        $dispatch = $this->dispatcher->dispatch($request->server->get('REQUEST_METHOD'), $request->server->get('REQUEST_URI'));
        switch ($dispatch[0]) {
            case Dispatcher::NOT_FOUND:
                $content = '404';
                
                $response = new Response(
                    $content,
                    Response::HTTP_NOT_FOUND,
                    array('content-type' => 'text/html')
                );
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                $content = '405';

                $response = new Response(
                    $content,
                    Response::HTTP_METHOD_NOT_ALLOWED,
                    array('content-type' => 'text/html')
                );
                break;
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                $content = '200';
                
                $response = new Response(
                    $content,
                    Response::HTTP_OK,
                    array('content-type' => 'text/html')
                );
                break;
        }
        
        $response->prepare($request);
        $response->send();
    }
    
    public function default404route()
    {
        header();
    }
}
