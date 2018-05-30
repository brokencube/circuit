<?php
namespace Circuit;

/**
 * A container representing a target Controller
 * It holds the name, method, arguments and constructor arguments needed to construct and call a controller method
 *
 * @author Nik Barham <nik@brokencube.co.uk>
 */
class ControllerParams
{
    /** @var string URI that was matched to the route */
    public $route;
    
    /** @var string Controller class to call */
    public $className; 

    /** @var string Controller method to call */
    public $method;
    
    /** @var array Arguments to pass to the controller method */
    public $args;

    /** @var array Arguments to pass to the controller __construct method */
    public $constructorArgs;
    
    public function __construct($route, $class, $method, array $args, array $constructorArgs = [])
    {
        $this->route = $route;
        $this->className = $class;
        $this->method = $method;
        $this->args = $args;
        $this->constructorArgs = $constructorArgs;
    }
}
