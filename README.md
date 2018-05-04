# circuit
[![Latest Stable Version](https://poser.pugx.org/brokencube/circuit/v/stable)](https://packagist.org/packages/brokencube/circuit)
[![Code Climate](https://codeclimate.com/github/brokencube/circuit/badges/gpa.svg)](https://codeclimate.com/github/brokencube/circuit) 

Router + Middleware built on top of [HTTP Foundation](https://github.com/symfony/http-foundation) and [FastRoute](https://github.com/nikic/FastRoute)

# Basic Usage
See FastRoute docs for more info on advanced matching patterns, grouping etc
```php
$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();  // From HTTP Foundation

$options = [];         // Options - allows you to provide alternative internals - see below
$cache = new PSR6();   // PSR6 or 16 cache - can be null
$log = new PSR3();     // PSR-3 compatible log - can be null

$router = new \Circuit\Router($options, $cache, $log);
$router->defineRoutes(function (\Circuit\RouteCollector $r) {
  $r->get('/', 'controllers\Home');                    // Calls \controllers\Home->index($request);
  $r->get('/search', 'controllers\Home@search');       // Calls \controllers\Home->search($request);
  $r->get('/blog/{id}', 'controllers\Blog@index', []); // Calls \controllers\Blog->index($request, $id);
  $r->addGroup('/group', [], function(Circuit\RouteCollector $r) {
    $r->get('/route', 'controllers\GroupRoute@index'); 
  }
}

$router->run($request);  // Dispatch route
```
Generally:
```php
$r->get($route, $controllerName, $middlewareArray);
$r->post($route, $controllerName, $middlewareArray);
$r->addRoute(['GET', 'POST'], $route, $controllerName, $middlewareArray);
$r->addGroup($prefix, $middlewareArray, function(Circuit\RouteCollector $r) {
   // Group routes
};
```
`$controllerName` should be in format `namespaced\ControllerClass@method`  (Similar to laravel)


# Middleware Example
### `middleware/AddCookie.php`
```php
namespace middleware;

use Circuit\Interfaces\{Middleware, Delegate};
use Symfony\Component\HttpFoundation\{Request, Response, Cookie};

class AddCookie implements Middleware
{
    public function process(Request $request, Delegate $delegate) : Response
    {
        $response = $delegate->process($request);
        
        $cookie = new Cookie('cookie', 'cookievalue', time() + (24 * 60 * 60));
        $response->headers->setCookie($cookie);
        
        return $response;        
    }
}
```

### `routes.php`
Add middleware individually to a route
```php
$router->defineRoutes(function (\Circuit\RouteCollector $r) {
  $r->get('/', 'controllers\Home', [new middleware\AddCookie()]);  
}
```
Or register the middleware in the router, and call it by name
```php
$router->registerMiddleware('addcookie', new middleware\AddCookie());
$router->defineRoutes(function (\Circuit\RouteCollector $r) {
  $r->get('/', 'controllers\Home', ['addcookie']);
}
```
Or add middleware to a group of routes
```php
$router->registerMiddleware('addcookie', new middleware\AddCookie());
$router->defineRoutes(function (\Circuit\RouteCollector $r) {
  $r->addGroup('', ['addcookie'], function(Circuit\RouteCollector $r) {
    $r->get('/', 'controllers\Home');  
  }
}
```
Or add middleware to be run before a route is even matched (this will logically be applied to all routes, as it happens before the matching step. This allows for middleware to modify the route before matching)
```php
$router->registerMiddleware('addcookie', new middleware\AddCookie());
$router->addPrerouteMiddleware('addcookie');
$router->defineRoutes(function (\Circuit\RouteCollector $r) {
  $r->get('/', 'controllers\Home');  
}
```

Middleware will be run in the order defined, with preroute middleware always running first, e.g.:
```php
$router->addPrerouteMiddleware('middleware1');
$router->defineRoutes(function (\Circuit\RouteCollector $r) {
  $r->addGroup('', ['middleware3', 'middleware4'], function(Circuit\RouteCollector $r) {
    $r->get('/', 'controllers\Home', ['middleware5']);  
  }
}
$router->addPrerouteMiddleware('middleware2');
```


