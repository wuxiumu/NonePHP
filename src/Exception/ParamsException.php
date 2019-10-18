<?php

/**
 * 参数异常
 */

namespace NonePHP\Exception;

use NonePHP\BaseException;

class ParamsException extends BaseException
{
    public $biz_code = 400;
    public $http_code = 400;
    public $message = '[invalid request]';
}