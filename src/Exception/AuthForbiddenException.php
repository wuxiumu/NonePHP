<?php

namespace NonePHP\Exception;

use NonePHP\BaseException;

class AuthForbiddenException extends BaseException
{
    public $biz_code = 403;
    public $http_code = 403;
    public $message = '[auth forbidden]';
}