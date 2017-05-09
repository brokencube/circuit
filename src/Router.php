<?php

namespace Circuit;

use FastRoute\Dispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\SimpleCache\CacheInterface as Psr16;
use Psr\Cache\CacheItemPoolInterface as Psr6;

class Router
{
    const CACHE_KEY = 'routes_v1';
    protected $options = [];
    protected $routeCollection;
    protected $dispatcher;
    protected $cache;
    
    public function __construct(array $options = [], $cache = null)
    {
        $this->options = $options + [
            'routeParser' => 'FastRoute\\RouteParser\\Std',
            'dataGenerator' => 'FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => 'Circuit\\RouteCollector',
            'cacheTimeout' => 3600,
            'errorRoutes' => [
                '404' => ['Circuit\\Router', 'default404route'],
                '405' => ['Circuit\\Router', 'default405route']
            ]
        ];
        $this->cache = $cache;
        
        // PSR-16 Cache
        if ($this->cache instanceof Psr16) {
            $this->routeCollection = $this->cache->get(static::CACHE_KEY);
        }

        // PSR-6 Cache
        if ($this->cache instanceof Psr6) {
            $item = $this->cache->getItem(static::CACHE_KEY);
            if ($item->isHit()) {
                $this->routeCollection = $item->get();
            }
        }
        
        if ($this->routeCollection) {
            $this->cached = true;
        } else {
            $this->routeCollection = new $this->options['routeCollector'](
                new $this->options['routeParser'], new $this->options['dataGenerator']
            );
        }
    }
    
    public function defineRoutes(callable $routeDefinitionCallback)
    {
        if (!$this->cached) {
            $routeDefinitionCallback($this->routeCollection);
            // PSR-16 Cache
            if ($this->cache instanceof Psr16) {
                $this->cache->set(static::CACHE_KEY, $this->routeCollection, $this->options['cacheTimeout']);
            }

            // PSR-6 Cache
            if ($this->cache instanceof Psr6) {
                $item = $this->cache->getItem(static::CACHE_KEY);
                $item->set($this->routeCollection);
                $item->expiresAt(new \DateTime('now + ' . $this->options['cacheTimeout'] . 'seconds'));
                $this->cache->save($item);
            }
        }
        
        $this->dispatcher = new $this->options['dispatcher']($this->routeCollection->getData());
    }
    
    protected function getCachedValue($key)
    {
        if (!$this->cache) {
            return null;
        }
        
        if ($this->cache instanceof Psr\SimpleCache\CacheInterface) {
            return $this->cache->get($key);
        }
        
        if ($this->cache instanceof Psr\Cache\CachePoolInterface) {
            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {
                return null;
            }
            return $item->get();
        }
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
                $response = $this->options['errorRoutes']['405']($request);
                break;
            
            case Dispatcher::FOUND:
                // $response will be of type HandlerContainer
                $dispatcher = unserialize($dispatch[1]);
                
                $response = $dispatcher->process($request);
                break;
        }
        
        $response->prepare($request);
        $response->send();
    }
    
    public function default404route() : Response
    {
        return new Response(
            '404 Not Found',
            Response::HTTP_NOT_FOUND,
            ['content-type' => 'text/html']
        );
    }
    
    public function default405route() : Response
    {
        return new Response(
            '405 Method Not Allowed',
            Response::METHOD_NOT_ALLOWED,
            ['content-type' => 'text/html']
        );
    }
}
