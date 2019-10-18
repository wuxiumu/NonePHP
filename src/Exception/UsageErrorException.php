<?php

namespace NonePHP\Exception;

use NonePHP\BaseException;

class UsageErrorException extends BaseException
{
    protected $biz_code = 800;
    protected $message = '[usage error]';
}