<?php

namespace NonePHP\Exception;

use NonePHP\BaseException;

class AuthFailedException extends BaseException
{
    public $biz_code = 401;
    public $http_code = 401;
    public $message = '[auth error]';
}