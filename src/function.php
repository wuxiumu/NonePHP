<?php

use NonePHP\BaseException;
use NonePHP\Bll\YacBll;
use NonePHP\DI;
use NonePHP\Exception\SystemConfigException;
use NonePHP\Component\Logger\File as FileLogger;

function is_dev()
{
    return in_array(strtolower(defined('APP_ENV') ? APP_ENV : 'production'), ['dev', 'develop', 'development']);
}

function is_test()
{
    return in_array(strtolower(defined('APP_ENV') ? APP_ENV : 'production'), ['test', 'qa']);
}

function is_prod()
{
    return in_array(strtolower(defined('APP_ENV') ? APP_ENV : 'production'), ['prod', 'product', 'production']);
}

if (!function_exists('load_file_env')) {
    /**
     * 读取关键配置文件到环境变量
     * @throws BaseException
     */
    function load_file_env()
    {
        $conf = conf_from_file();
        foreach ($conf as $key => $value) {
            if (getenv($key)) {
                continue;
            }
            putenv("$key=$value");
        }
    }

    function conf_from_file()
    {
        $file = BASE_DIR . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($file)) {
            throw (new SystemConfigException())->debug('cannot found .env file');
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $_conf = [];
        foreach ($lines as $line) {
            $line = ltrim($line);
            if (strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                if (!$key) {
                    continue;
                }
                $_conf[$key] = $value;
            }
        }
        return $_conf;
    }
}

if (!function_exists('env')) {
    function env($key, $default = null, bool $fromFile = false)
    {
        $value = null;
        if (!$fromFile && defined('YAC_ENABLE') && YAC_ENABLE) {
            $value = YacBll::getInstance()->yac->get($key);
            if ($value === false) {
                trigger_error('get [' . $key . '] env from yac not found!');
            }
        }
        if (!$value) {
            $value = getenv($key);
        }
        if ($value === false) {
            return ($default instanceof Closure) ? $default() : $default;
        }
        switch (strtolower($value)) {
            case 'true':
                $value = true;
                break;
            case 'false':
                $value = false;
                break;
            case 'empty':
                $value = '';
                break;
            case 'null':
                $value = null;
                break;
        }
        return $value;
    }
}

if (!function_exists('get_datetime_micro')) {
    function get_datetime_micro()
    {
        return date('Y-m-d H:i:s') . ' ' . str_pad(intval(explode(' ', microtime())[0] * 1000), 3, 0, STR_PAD_LEFT);
    }
}

if (!function_exists('XLog')) {
    function XLog(): FileLogger
    {
        return DI::getInstance()->getShared('logger');
    }
}

// 记录到 /tmp/NonePHP_error.log
if (!function_exists('tmp_log')) {
    function tmp_log($msg)
    {
        $msg = sprintf('[%s] %s', date('Y-m-d H:i:s'), $msg) . PHP_EOL;
        file_put_contents(sys_get_temp_dir() . '/NonePHP_error.log', $msg, FILE_APPEND);
    }
}