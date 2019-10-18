<?php

/**
 * 通用 Notice 异常
 */

namespace NonePHP\Exception;

use NonePHP\BaseException;

class CustomNoticeException extends BaseException
{
    protected $biz_code = 900;
    protected $http_code = 400;
    protected $message = '[custom exception]';
}