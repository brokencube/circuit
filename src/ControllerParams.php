<?php
namespace Circuit;

use Circuit\Router;
use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Circuit\Interfaces\ParameterDereferencer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Container\ContainerInterface as Container;

/**
 * A container representing a target Controller
 * It holds the name, method, arguments and constructor arguments needed to construct and call a controller method
 *
 * @author Nik Barham <nik@brokencube.co.uk>
 */
class ControllerParams
{
    /** @var string Controller class to call */
    public $className; 

    /** @var string Controller method to call */
    public $method;
    
    /** @var array Arguments to pass to the controller method */
    public $args;
    
    /** @var Psr\Container\ContainerInterface Container to pass to constructor */
    public $container;
    
    public function __construct($class, $method, $args, Container $container)
    {
        $this->className = $class;
        $this->method = $method;
        $this->args = $args;
        $this->container = $container;
    }
}
