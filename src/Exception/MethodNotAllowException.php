<?php

namespace NonePHP\Exception;

use NonePHP\BaseException;

class MethodNotAllowException extends BaseException
{
    public $biz_code = 405;
    public $http_code = 405;
    public $message = '[method not allowed]';
}