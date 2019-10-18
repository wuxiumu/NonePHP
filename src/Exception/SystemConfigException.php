<?php

/**
 * 配置定义错误
 */

namespace NonePHP\Exception;

use NonePHP\BaseException;

class SystemConfigException extends BaseException
{
    protected $biz_code = 855;
    protected $message = '[config error]';
}