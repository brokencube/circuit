<?php

namespace Circuit\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface Delegate
{
    public function process(Request $request) : Response;
}
