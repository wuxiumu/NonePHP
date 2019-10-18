<?php

namespace NonePHP\Component\Logger;

interface LoggerInterface
{
    /**
     * 系统内部错误
     * @param $message
     * @return mixed
     */
    public function fatal($message);

    /**
     * 严重错误
     * @param $message
     * @return mixed
     */
    public function error($message);

    /**
     * debug日志
     * @param $message
     * @return mixed
     */
    public function debug($message);

    /**
     * 警告日志
     * @param $message
     * @return mixed
     */
    public function warn($message);

    /**
     * 一般性日志
     * @param $message
     * @return mixed
     */
    public function info($message);
}