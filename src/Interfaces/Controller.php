<?php

namespace Circuit\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Container\ContainerInterface as Container;

interface Controller
{
    public function __construct(Container $container);
}
