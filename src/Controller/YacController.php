<?php

namespace NonePHP\Controller;

use NonePHP\BaseException;
use NonePHP\Bll\YacBll;
use NonePHP\Injectable;
use RuntimeException;

class YacController extends Injectable
{
    /**
     * @return array|string
     * @throws BaseException
     * @throws RuntimeException
     * @middleware NonePHP\Middleware\AuthRequestMiddleware
     */
    public function getAction()
    {
        $type = $this->request->getQuery('type') ?? '';
        if ($type === 'info') {
            $keys = $this->request->getQuery('keys', '');
            return YacBll::getInstance()->getYacInfo($keys);
        }

        if ($type === 'reload') {
            YacBll::getInstance()->flushYac();
            YacBll::getInstance()->loadEnvFromFile();
            return ['msg' => 'reload yac cache ok'];
        }

        return ['msg' => 'type is empty'];
    }
}