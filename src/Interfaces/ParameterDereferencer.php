<?php

namespace Circuit\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface ParameterDereferencer
{
    public function dereference(\ReflectionParameter $parameter, $value);
}
