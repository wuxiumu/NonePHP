<?php

namespace NonePHP\Middleware;

interface MiddlewareInterface
{
    public function handle($result = null);
}