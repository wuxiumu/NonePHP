<?php

namespace NonePHP\Middleware;

use NonePHP\Core\Response;
use NonePHP\DI;
use NonePHP\Exception\SystemConfigException;

class ResponseJsonMiddleware implements MiddlewareInterface
{
    public function handle($result = null)
    {
        /** @var Response $response */
        $response = DI::getInstance()->getShared('response');
        $response->setStatusCode(200);
        if (is_scalar($result)) {
            $response->getContentType() || $response->setContentType('text/plain');
            $response->setContent($result);
        } else if (is_array($result)) {
            $response->setJsonContent($result);
        } else if ($result) {
            throw (new SystemConfigException('输出有误'))->debug('result:' . serialize($result));
        }

        return $result;
    }
}