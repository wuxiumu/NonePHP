<?php

namespace NonePHP\Exception;

use NonePHP\BaseException;

class CorsForbiddenException extends BaseException
{
    public $biz_code = 399;
    public $http_code = 403;
    public $message = '[auth forbidden]';
}