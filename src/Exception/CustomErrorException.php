<?php

/**
 * 通用错误异常
 */

namespace NonePHP\Exception;

use NonePHP\BaseException;

class CustomErrorException extends BaseException
{
    protected $biz_code = 999;
    protected $message = '[custom error]';
}