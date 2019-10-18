<?php

namespace NonePHP\Component\Logger;

use NonePHP\DI;
use NonePHP\ErrorHandler;
use NonePHP\Exception\SystemConfigException;

class File implements LoggerInterface
{
    /**
     * 日志文件夹路径 一般为 /var/log/APP_NAME
     * @var string $path
     */
    protected $path;

    // 是否使用批处理模式，先缓存日志，最后批量写入文件
    // 如果时脚本模式，最好立即写入日志 debug('', true)
    protected $batch = false;
    protected $logs = [];

    /**
     * 打开文件句柄
     */
    protected $fp = [];

    public function __construct(string $path = '', bool $batch = false)
    {
        $this->batch = $batch;
        $this->path = $path ?: '/var/log/' . (defined('APP_NAME') ? APP_NAME : 'NonePHP');
        // 第一次创建文件夹
        if (!is_dir($this->path) && !mkdir($this->path) && !is_dir($this->path)) {
            throw (new SystemConfigException('创建日志目录失败'))->debug('path:' . $this->path);
        }
        if (!is_writable($this->path)) {
            throw (new SystemConfigException('日志文件夹必须可写'))->debug('path:' . $this->path);
        }
    }

    public function fatal($message, $atOnce = false)
    {
        $_message = '[FATAL]';
        if (DI::getInstance()->has('app') && ($_reqId = DI::getInstance()->getShared('app')->getReqId())) {
            $_message .= '[' . $_reqId . ']';
        }
        $_message .= ' $' . get_datetime_micro() . '$ -> ';
        $this->logInternal($_message . $this->_getMessageStr($message), 'F', '', $atOnce);
    }

    public function error($message, $atOnce = false)
    {
        $_message = '[ERROR]';
        if (DI::getInstance()->has('app') && ($_reqId = DI::getInstance()->getShared('app')->getReqId())) {
            $_message .= '[' . $_reqId . ']';
        }
        $_message .= ' $' . get_datetime_micro() . '$ -> ';
        $this->logInternal($_message . $this->_getMessageStr($message), 'E', '', $atOnce);
    }

    public function debug($message, $atOnce = false)
    {
        $_message = '[DEBUG]';
        if (DI::getInstance()->has('app') && ($_reqId = DI::getInstance()->getShared('app')->getReqId())) {
            $_message .= '[' . $_reqId . ']';
        }
        $_message .= ' $' . get_datetime_micro() . '$ -> ';
        $_message .= $this->_getMessageStr($message);
        $this->logInternal($_message, 'D', '', $atOnce);
    }

    public function warn($message, $atOnce = false)
    {
        $_message = '[WARN]';
        if (DI::getInstance()->has('app') && ($_reqId = DI::getInstance()->getShared('app')->getReqId())) {
            $_message .= '[' . $_reqId . ']';
        }
        $_message .= ' $' . get_datetime_micro() . '$ -> ';
        $this->logInternal($_message . $this->_getMessageStr($message), 'W', '', $atOnce);
    }

    public function info($message, $atOnce = false)
    {
        $_message = '[INFO]';
        if (DI::getInstance()->has('app') && ($_reqId = DI::getInstance()->getShared('app')->getReqId())) {
            $_message .= '[' . $_reqId . ']';
        }
        $_message .= ' $' . get_datetime_micro() . '$ -> ';
        $this->logInternal($_message . $this->_getMessageStr($message), 'I', '', $atOnce);
    }

    public function other($message, $type, $file, $atOnce = false): void
    {
        $_message = '[' . $type . ']';
        if (DI::getInstance()->has('app') && ($_reqId = DI::getInstance()->getShared('app')->getReqId())) {
            $_message .= '[' . $_reqId . ']';
        }
        $_message .= ' $' . get_datetime_micro() . '$ -> ';
        $this->logInternal($_message . $this->_getMessageStr($message), 'O', $file, $atOnce);
    }

    /**
     * @param $message
     * @param $type
     * @param string $file
     * @param bool $atOnce 立即写入，忽略 batch
     */
    protected function logInternal($message, $type, $file = '', bool $atOnce = false): void
    {
        if ($this->batch && !$atOnce) {
            $key = $type . ':' . $file;
            !isset($this->logs[$key]) and $this->logs[$key] = [];
            $this->logs[$key][] = $message;
            return;
        }
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $message .= ' [' . substr($trace['file'], strlen(BASE_DIR)) . '@' . $trace['line'] . ']' . PHP_EOL;
        $fp = null;
        switch ($type) {
            case 'F':
                if (empty($this->fp['F'])) {
                    $this->fp['F'] = fopen($this->getLogFile('fatal'), 'ab+');
                }
                $fp = $this->fp['F'];
                break;
            case 'E':
                if (empty($this->fp['E'])) {
                    $this->fp['E'] = fopen($this->getLogFile('error'), 'ab+');
                }
                $fp = $this->fp['E'];
                break;
            case 'W':
                if (empty($this->fp['W'])) {
                    $this->fp['W'] = fopen($this->getLogFile('warn'), 'ab+');
                }
                $fp = $this->fp['W'];
                break;
            case 'D':
                if (empty($this->fp['D'])) {
                    $this->fp['D'] = fopen($this->getLogFile('debug'), 'ab+');
                }
                $fp = $this->fp['D'];
                break;
            case 'O':
                if (empty($this->fp['O'])) {
                    if ((!is_file($file) && $file && touch($file) && !is_file($file)) || !is_writable($file)) {
                        tmp_log('[FILE.LOGGER] 创建日志文件失败，或者不可写 file:' . $file);
                        exit;
                    }
                    $this->fp['O'] = fopen($file, 'ab+');
                }
                $fp = $this->fp['O'];
                break;
            case 'I':
            default:
                if (empty($this->fp['I'])) {
                    $this->fp['I'] = fopen($this->getLogFile('info'), 'ab+');
                }
                $fp = $this->fp['I'];
                break;
        }

        if (fwrite($fp, $message) === false) {
            tmp_log('[FILE.LOGGER] 写日志到文件失败');
        }
    }

    protected function getLogFile($type): string
    {
        return rtrim($this->path, '/') . '/' . $type . '.log';
    }

    protected function _getMessageStr($message)
    {
        if (\is_array($message) || \is_object($message)) {
            return print_r($message, true);
        }

        return $message;
    }

    public function __destruct()
    {
        if ($this->batch && $this->logs) {
            foreach ($this->logs as $key => $log) {
                $keys = explode(':', $key);
                $msgs = implode(PHP_EOL, $log);
                // 线上环境，只在有错误时，输出其他日志，比如关键的 debug | info | other  信息
                if (is_prod() && !in_array($keys[0], ['F', 'E', 'W']) && !ErrorHandler::hasError()) {
                    continue;
                }
                $this->logInternal($msgs, $keys[0], $keys[1], true);
            }
        }
    }
}