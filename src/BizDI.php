<?php

namespace NonePHP;

use function is_string;

trait BizDI
{
    public function addService(string $serviceName, $service, $params = null, bool $rewrite = false): void
    {
        if (!$this->$serviceName || $rewrite) {
            if (is_string($service)) {
                if ($params) {
                    $this->$serviceName = new $service($params);
                } else {
                    $this->$serviceName = new $service();
                }
            } else {
                $this->$serviceName = $service;
            }
        }
    }
}