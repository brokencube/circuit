<?php

namespace Circuit\Middleware;

use Circuit\Interfaces\Middleware;
use Circuit\Interfaces\Delegate;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Error Logger
 *
 * A middleware that pushes exceptions out into a logger
 *
 * @author Nik Barham <nik@brokencube.co.uk>
 */
class ErrorLogger implements Middleware
{
    /**
     * Constructor
     *
     * @param LoggerInterface $log Logger to log error to
     */
    public function __construct(LoggerInterface $log)
    {
        $this->log = $log;
    }
    
    /**
     * Run Middleware for a particular request
     *
     * @param Request  $request    HTTP Foundation Request object
     * @param Delegate $delegate   Either the Router or HandlerContainer, depending on whether this is run pre or post
     *                             routing
     * @return Response
     */
    public function process(Request $request, Delegate $delegate) : Response
    {
        try {
            return $delegate->process($request);
        } catch (\Throwable $e) {
            $this->log->error($e->getMessage(), ['trace' => $e->getTrace(), 'line' => $e->getLine(), 'file' => $e->getFile(), 'code' => $e->getCode()]);
            throw $e;
        }
    }
}
