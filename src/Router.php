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
            'routeCollector' => 'Circuit\\RouteCollector',
            'errorRoutes' => [
                '404' => ['Circuit\\Router', 'default404router']
            ]
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
                // $response will be of type HandlerContainer
                $response = $dispatch[1]->process($request);
                break;
        }
        
        $response->prepare($request);
        $response->send();
        exit;
    }
    
    public function default404router() : Response
    {
        return new Response(
            '404 Not Found',
            Response::HTTP_NOT_FOUND,
            ['content-type' => 'text/html']
        );
    }
}
