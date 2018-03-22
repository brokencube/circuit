<?php

namespace Circuit;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\ParameterDereferencer;
use Circuit\Interfaces\ExceptionHandler;
use Circuit\Interfaces\Delegate;
use Circuit\Exception;
use Circuit\Middleware\RouteMatcher;
use FastRoute\Dispatcher;
use Psr\SimpleCache\CacheInterface as Psr16;
use Psr\Cache\CacheItemPoolInterface as Psr6;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Container\ContainerInterface as Container;

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
    
    const CACHE_KEY = 'routes_v3';
    
    /** @var mixed[] Various options as defined by FastRoute */
    protected $options = [];
    
    /** @var mixed Router Collector - Concrete class defined by options. */
    protected $routeCollection;
    
    /** @var float Timer for debug logging purposes */
    protected $stopwatch;
    
    /** @var CacheWrapper PSR6/16 compatible cache item */
    protected $cache;

    /** @var bool Did we pull results from cache i.e. do we need to call the RouteCollector callback */
    protected $cached = false;

    /** @var ExceptionHandler[] List of exception handlers for particular HTTP codes */
    public $exceptionHandlers = [];
    
    /** @var ExceptionHandler|null Default Exception Handler */
    public $defaultExceptionHandler = null;

    /** @var mixed[] List of arguments passed to Controller constructor */
    protected $controllerArgs = [];

    /** @var Middleware[] List of registered middlewares on this router */
    protected $namedMiddleware = [];
    
    /** @var array List of middlewares to run before matching routes */
    protected $prerouteMiddleware = [null];

    /** @var array List of middlewares to run before matching routes */
    protected $currentMiddleware = [];
    
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
        $this->logger = $logger;
        $this->cache = $cache ? new CacheWrapper($cache) : null;
        $this->routeCollection = $this->cache ? $this->cache->get(static::CACHE_KEY) : null;
        
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
            // Cache
            if ($this->cache) {
                $this->cache->set(static::CACHE_KEY, $this->routeCollection, $this->options['cacheTimeout']);
            }
        }
        
        $this->dispatcher = new $this->options['dispatcher']($this->routeCollection->getData());
        return $this;
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
        
        $this->currentMiddleware = $this->prerouteMiddleware;
        $this->currentMiddleware[] = new RouteMatcher($this);
        
        $this->log("Router: ->run() called. Starting clock at REQUEST_TIME+%.2fms", microtime(true) - $starttime);
        try {
            $response = $this->process($request);
        } catch (\Throwable $e) {
            $this->log("Router: Exception");
            $response = $this->handleException($e, $request);
        }
        
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
        }
    }
    
    /**
     * Handle an exception during processing of route.
     *
     * @param Throwable $e The Exception / Error thrown.
     * @param Request $request The request that caused the exception.
     * @return Response The response to the exception (e.g. error page)
     */
    protected function handleException(\Throwable $e, Request $request) : Response
    {
        // Wrap non HTTP exception/errors
        if (!$e instanceof Exception\Exception) {
            $e = new Exception\UncaughtException($e);
        }
        
        // Throw to an appropriate handler
        $code = $e->getStatusCode();
        if ($this->exceptionHandlers[$code] instanceof ExceptionHandler) {
            return $this->exceptionHandlers[$code]->handle($e, $request);
        } elseif ($this->defaultExceptionHandler instanceof ExceptionHandler) {
            return $this->defaultExceptionHandler->handle($e, $request);
        } else {
            return (new \Circuit\ExceptionHandler\DefaultHandler)->handle($e, $request);
        }
    }
    
    /**
     * Set arguments that will be passed to the constructor for any controllers invoked
     *
     * @param Psr\Container\ContainerInterface $container Service container passed to controller constructor
     * @return self
     */
    public function setServiceContainer(Container $container)
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Get arguments that will be passed to the constructor for any controllers invoked
     *
     * @return Psr\Container\ContainerInterface Service container passed to controller constructor
     */
    public function getServiceContainer()
    {
        return $this->container;
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
     * Add middleware that will be run after routes are matched
     *
     * @param mixed $middleware Middleware object or named middleware (via ->registerMiddleware($name))
     * @return self
     */
    public function addMiddleware(array $middleware)
    {
        $this->currentMiddleware = array_merge($this->currentMiddleware, $middleware);
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
     * Register an exception handler for a particular HTTP code.
     *
     * @param string $code HTTP code handler is responsible for
     * @param ExceptionHandler $hander Handler
     * @return self
     */
    public function setDefaultExceptionHandler(ExceptionHandler $handler)
    {
        $this->defaultExceptionHandler = $handler;
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
            $this->logger->debug(sprintf($message . ' (%.2fs)', ...$args));
        }
        return $this;
    }
}
