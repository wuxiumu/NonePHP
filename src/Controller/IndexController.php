<?php

namespace NonePHP\Controller;

use NonePHP\Injectable;

class IndexController extends Injectable
{
    public function getAction(): string
    {
        $this->response->setContentType('text/html');
        $str = '<h1 style="display:flex;justify-content:center;align-items: center;padding-top:150px;color: #999;">Welcome to use NonePHP!</h1>';
        if (defined('APP_NAME')) {
            $str .= '<h2 style="display: flex;justify-content: center;align-items: center;padding-top: 20px; color: #999;">Your current APP_NAME is {' . APP_NAME . '}</h2>';
        }

        return $str;
    }
}