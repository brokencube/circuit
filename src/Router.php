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
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Circuit - FastRoute + Middleware
 *
 * An implementation of nikic\FastRoute, with added capability of pre and post route middleware,
 * modelled after PSR-15 but using HTTP Foundation Request/Response objects as well as support for
 * PSR 6 and PSR 16 based caching solutions.
 *
 * @author Nik Barham <nik@brokencube.co.uk>
 */
class Router implements Delegate, LoggerAwareInterface
{
    use LoggerAwareTrait;
    
    const CACHE_KEY = 'routes_v2';
    
    /** @var mixed[] Various options as defined by FastRoute */
    protected $options = [];
    
    /** @var mixed Router Collector - Concrete class defined by options. */
    protected $routeCollection;
    
    /** @var float Timer for debug logging purposes */
    protected $stopwatch;
    
    /** @var Psr16|Psr6 PSR6/16 compatible cache item */
    protected $cache;

    /** @var bool Did we pull results from cache i.e. do we need to call the RouteCollector callback */
    protected $cached = false;

    /** @var ExceptionHandler[] List of exception handlers for particular HTTP codes */
    public $exceptionHandlers = [];

    /** @var mixed[] List of arguments passed to Controller constructor */
    protected $controllerArgs = [];

    /** @var Middleware[] List of registered middlewares on this router */
    protected $namedMiddleware = [];
    
    /** @var array List of middlewares to run before matching routes */
    protected $prerouteMiddleware = [null];

    /**
     * Create a new Router
     * See https://github.com/nikic/FastRoute for more details
     *
     * @param array $options Option overrides
     * @param Psr16|Psr6 $cache A PSR-6 or PSR-16 compatible Cache object
     */
    public function __construct(array $options = [], $cache = null, LoggerInterface $logger = null)
    {
        $this->options = $options + [
            'routeParser' => 'FastRoute\\RouteParser\\Std',
            'dataGenerator' => 'FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => 'Circuit\\RouteCollector',
            'cacheTimeout' => 3600
        ];
        $this->cache = $cache;
        $this->logger = $logger;
        
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
    
    /**
     * Define routes using a routerCollector
     * See https://github.com/nikic/FastRoute for more details
     *
     * @param callable $definitionCallback Callback that will define the routes
     * @return self
     */
    public function defineRoutes(callable $definitionCallback)
    {
        if (!$this->cached) {
            $definitionCallback($this->routeCollection);
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
        return $this;
    }
    
    /**
     * Internal function to retrieve a cached value from PSR-6/16 cache object
     *
     * @param string $key Cache key to retrieve from cache
     */
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
    
    /**
     * Execute a route
     *
     * @param Request $request Request object for current process
     */
    public function run(Request $request)
    {
        $this->stopwatch = microtime(true);
        $starttime = $request->server->get('REQUEST_TIME_FLOAT');
        
        $this->log("Router: ->run() called. Starting clock at REQUEST_TIME+%.2fms", microtime(true) - $starttime);
        $response = $this->process($request);
        $this->log("Router: Preparing to send response");
        $response->prepare($request);
        $response->send();
        $this->log("Router: Response sent");
    }
    
    /**
     * Process a route
     * Will call pre-route middleware, then match route and execute that route (more middleware + controller)
     *
     * @param Request $request Request object for current process
     * @return Response Response to http request ready for dispatch
     */
    public function process(Request $request) : Response
    {
        try {
            // Try and run the next middleware
            $next = next($this->prerouteMiddleware);
            if ($next instanceof Middleware) {
                $this->log("Router: Calling Middleware: %s", get_class($next));
                $response = $next->process($request, $this);
                $this->log("Router: Leaving Middleware: %s", get_class($next));
                return $response;
            } elseif (is_string($next)) {
                $this->log("Router: Calling Middleware: %s", $next);
                $response = $this->getMiddleware($next)->process($request, $this);
                $this->log("Router: Leaving Middleware: %s", $next);
                return $response;
            } else {
                // Null byte poisoning protection
                list($uri) = explode('?', str_replace(chr(0), '', $request->server->get('REQUEST_URI')));
                $dispatch = $this->dispatcher->dispatch($request->server->get('REQUEST_METHOD'), $uri);
                switch ($dispatch[0]) {
                    case Dispatcher::NOT_FOUND:
                        $this->log("Router: Route not matched");
                        throw new Http\NotFoundHttpException('Router: Route not matched');
                    
                    case Dispatcher::METHOD_NOT_ALLOWED:
                        $this->log("Router: Method not Allowed");
                        throw new Http\MethodNotAllowedHttpException('Router: Method not Allowed: ' . $dispatch[1]);
                    
                    case Dispatcher::FOUND:
                        try {
                            $dispatcher = unserialize($dispatch[1]);
                            $this->log("Router: Route matched: %s@%s", $dispatcher->controllerClass, $dispatcher->controllerMethod);
                            return $dispatcher->startProcessing($this, $request, $dispatch[2]);
                        } catch (\Throwable $e) {
                            return $this->handleException($e, $request, $dispatcher);
                        }
                }
            }
        } catch (\Throwable $e) {
            return $this->handleException($e, $request, $next ?: $this);
        }
    }
    
    /**
     * Handle an exception during processing of route.
     * Will try and determine context (Controller, Middleware, Router etc) before calling ExceptionHandler
     * based on HTTP code of Exception (default 500).
     *
     * @param Throwable $e The Exception / Error thrown.
     * @param Request $request The request that caused the exception.
     * @param mixed $currentContext Some data to try and guess the context from.
     * @return Response The response to the exception (e.g. error page)
     */
    protected function handleException(\Throwable $e, Request $request, $currentContext = null) : Response
    {
        // Figure out which Middleware/Controller we're in
        if ($currentContext instanceof Middleware) {
            $context = get_class($currentContext);
        } elseif (is_string($currentContext)) {
            $context = get_class($this->getMiddleware($currentContext));
        } elseif ($currentContext instanceof HandlerContainer) {
            if (current($currentContext->namedMiddlewareStack)) {
                $context = get_class(current($currentContext->namedMiddlewareStack));
            } else {
                $context = $currentContext->controllerClass . '@' . $currentContext->controllerMethod;
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
        if ($this->exceptionHandlers[$code] instanceof ExceptionHandler) {
            return $this->exceptionHandlers[$code]->handle($e, $request, $context);
        } else {
            return (new \Circuit\ExceptionHandler\DefaultHandler)->handle($e, $request, $context);
        }
    }
    
    /**
     * Set arguments that will be passed to the constructor for any controllers invoked
     *
     * @param mixed $args Array containing all passed variadic arguments
     * @return self
     */
    public function setControllerArguments(...$args)
    {
        $this->controllerArgs = $args;
        return $this;
    }

    /**
     * Get arguments that will be passed to the constructor for any controllers invoked
     *
     * @return mixed[] An array of the arguments, which can be unpacked with the ... splat operator
     */
    public function getControllerArguments()
    {
        return $this->controllerArgs;
    }

    /**
     * Register a middleware against a name.
     * This allows middleware to be created on startup and then refered to in serialised/cached route table
     *
     * @param string $name Unique name for middleware instance
     * @param Middleware $middleware Middleware object
     * @return self
     */
    public function registerMiddleware($name, Middleware $middleware)
    {
        $this->namedMiddleware[$name] = $middleware;
        return $this;
    }
   
    /**
     * Retrieve a middleware by name.
     *
     * @param string $name Name of middleware set by ->registerMiddleware($name)
     * @throws UnexpectedValueException For unrecognised names
     * @return Middleware The referenced middleware instance
     */
    public function getMiddleware($name) : Middleware
    {
        if (!array_key_exists($name, $this->namedMiddleware)) {
            throw new \UnexpectedValueException("No middleware registered under name '{$name}'");
        }
        return $this->namedMiddleware[$name];
    }
    
    /**
     * Add a middleware that will be run before routes are matched
     *
     * @param mixed $middleware Middleware object or named middleware (via ->registerMiddleware($name))
     * @return self
     */
    public function addPrerouteMiddleware($middleware)
    {
        $this->prerouteMiddleware[] = $middleware;
        return $this;
    }

    /**
     * Register an exception handler for a particular HTTP code.
     *
     * @param string $code HTTP code handler is responsible for
     * @param ExceptionHandler $hander Handler
     * @return self
     */
    public function setExceptionHandler($code, ExceptionHandler $handler)
    {
        $this->exceptionHandlers[$code] = $handler;
        return $this;
    }
    
    /**
     * Log a debug message, and append time elapsed since ->run() was called
     *
     * @param string $message sprintf compatible string
     * @param mixed[] $args Data to pass to sprintf
     * @return self
     */
    public function log($message, ...$args)
    {
        if ($this->logger) {
            $args[] = $this->stopwatch ? microtime(true) - $this->stopwatch : 0;
            $this->logger->debug(sprintf($message . ' (%.2fms)', ...$args));
        }
        return $this;
    }
}
