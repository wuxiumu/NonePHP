<?php

namespace NonePHP\Exception;

use NonePHP\BaseException;

class DatabaseException extends BaseException
{
    public $biz_code = 777;
    public $message = '[db error]';
}