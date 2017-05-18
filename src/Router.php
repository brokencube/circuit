<?php

namespace Circuit;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\ParameterDereferencer;
use Circuit\Interfaces\ExceptionHandler;
use Circuit\Interfaces\Delegate;
use FastRoute\Dispatcher;
use Psr\SimpleCache\CacheInterface as Psr16;
use Psr\Cache\CacheItemPoolInterface as Psr6;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception as Http;

class Router implements Delegate
{
    const CACHE_KEY = 'routes_v1';
    protected $options = [];
    protected $routeCollection;
    protected $dispatcher;
    protected $cache;
    protected $cached = false;

    /** @var ExceptionHandler[] */
    public $exceptionHandlers = [];

    /** @var mixed[] */
    public $controllerArguments = [];

    /** @var Middleware[] */
    protected $middleware = [];
    
    /** @var mixed[] */
    protected $preRouteMiddlewareStack = [null];

    public function __construct(array $options = [], $cache = null)
    {
        $this->options = $options + [
            'routeParser' => 'FastRoute\\RouteParser\\Std',
            'dataGenerator' => 'FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => 'Circuit\\RouteCollector',
            'cacheTimeout' => 3600,
            'errorRoutes' => []
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
        $response = $this->process($request);
        $response->prepare($request);
        $response->send();
    }
    
    public function process(Request $request) : Response
    {
        try {
            $next = next($this->preRouteMiddlewareStack);
            if ($next instanceof Middleware) {
                return $next->process($request, $this);
            } elseif (is_string($next)) {
                return $this->getMiddleware($next)->process($request, $this);
            } else {
                try {
                    list($uri) = explode('?', str_replace(chr(0), '', $request->server->get('REQUEST_URI')));
                    $dispatch = $this->dispatcher->dispatch($request->server->get('REQUEST_METHOD'), $uri);
                    switch ($dispatch[0]) {
                        case Dispatcher::NOT_FOUND:
                            throw new Http\NotFoundHttpException();
                            break;
                        
                        case Dispatcher::METHOD_NOT_ALLOWED:
                            throw new Http\MethodNotAllowedHttpException($dispatch[1]);
                            break;
                        
                        case Dispatcher::FOUND:
                            $dispatcher = unserialize($dispatch[1]);
                            return $dispatcher->startProcessing($this, $request, $dispatch[2]);
                            break;
                    }
                } catch (\Throwable $e) {
                    return $this->handleException($e, $request, $dispatcher ?: $dispatch);
                }
            }
        } catch (\Throwable $e) {
            return $this->handleException($e, $request, $next);
        }
    }
    
    public function handleException(Throwable $e, Request $request, $currentContext = null) : Response
    {
        // Figure out which Middleware/Controller we're in
        if ($currentContext instanceof Middleware) {
            $context = get_class($currentContext);
        } elseif (is_string($currentContext)) {
            $context = get_class($this->getMiddleware($currentContext));
        } elseif ($currentContext instanceof HandlerContainer) {
            if (current($currentContext->middlewareStack)) {
                $context = get_class(current($currentContext->middlewareStack));
            } else {
                $context = get_class($currentContext->controllerClass);
            }
        } elseif (is_array($currentContext)) {
            $context = $currentContext;
        }
        
        // Wrap non HTTP exception/errors
        if (!$e instanceof Http\HttpExceptionInterface && $e instanceof \Exception) {
            $e = new Http\HttpException(500, 'Uncaught Exception', $e);
        }

        if (!$e instanceof Http\HttpExceptionInterface && $e instanceof \Error) {
            $e = new Http\HttpException(500, 'Uncaught Error', new \Exception("Uncaught Error", 503, $e));
        }
        
        // Throw to an appropriate handler
        $code = $e->getStatusCode();
        if ($this->errorRoutes[$code] instanceof ExceptionHandler) {
            return $this->errorRoutes[$code]->handle($e, $request, $context);
        } else {
            return (new \Circuit\ExceptionHandler\DefaultHandler)->handle($e, $request, $context);
        }
    }
    
    public function default404route(Request $request) : Response
    {
        return new Response(
            '404 Not Found',
            Response::HTTP_NOT_FOUND,
            ['content-type' => 'text/html']
        );
    }
    
    public function default405route(Request $request) : Response
    {
        return new Response(
            '405 Method Not Allowed',
            Response::METHOD_NOT_ALLOWED,
            ['content-type' => 'text/html']
        );
    }

    public function setControllerArguments(...$args)
    {
        $this->controllerArguments = $args;
    }

    public function registerMiddleware($name, Middleware $middleware)
    {
        $this->middleware[$name] = $middleware;
    }
   
    public function getMiddleware($name) : Middleware
    {
        if (!array_key_exists($name, $this->middleware)) {
            throw new \Exception("No middleware registered under name '{$name}'");
        }
        return $this->middleware[$name];
    }
    
    public function setExceptionHandler($code, ExceptionHandler $handler)
    {
        $this->exceptionHandlers[$code] = $handler;
    }

    public function setPrerouteMiddleware($middleware)
    {
        $this->preRouteMiddlewareStack[] = $middleware;
    }
}
