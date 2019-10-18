<?php

namespace NonePHP\Exception;

use NonePHP\BaseException;

class NotFoundException extends BaseException
{
    public $biz_code = 404;
    public $http_code = 404;
    public $message = '[not found]';
}