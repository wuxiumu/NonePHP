<?php

namespace NonePHP;

use LogicException;
use RuntimeException;
use function is_array;
use function is_object;

abstract class BaseException extends LogicException
{
    protected $biz_code = 500; // 返回给用户的 error_code
    protected $http_code = 500; // http_status_code, >=500 记录error日志，否则记录warn日志
    protected $message = '';   // 返回给用户的错误
    protected $debugError = [];// debug信息

    /**
     * @param string $message 业务错误信息 (用户可见)
     * @param int $bizCode 业务错误code (用户可见)
     * @param int $httpCode 重置http_status_code
     * @throws RuntimeException
     */
    final public function __construct(string $message = '', int $bizCode = 0, int $httpCode = 0)
    {
        $this->message = $message ?: $this->message;
        $this->http_code = $httpCode ?: $this->http_code;
        if ($bizCode) {
            if ($bizCode < 1000) {
                throw new RuntimeException('biz error code must not less than 1000');
            }
            $this->biz_code = $bizCode;
        }
        parent::__construct($this->message);
    }

    public function getDebug(): array
    {
        return $this->debugError;
    }

    /**
     * 自定义 http status code
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->http_code ?? 500;
    }

    /**
     * 错误代码，业务错误代码必须大于1000
     * @return int
     */
    public function getBizCode(): int
    {
        return $this->biz_code;
    }

    /**
     * 额外添加的debug信息，只会在测试环境通过接口返回，都会保存日志，供额外快速分析错误使用
     * @param mixed $debugMessage
     * @return $this
     */
    public function debug($debugMessage): self
    {
        if (is_object($debugMessage)) {
            $this->debugError = get_object_vars($debugMessage);
        } else if (is_array($debugMessage)) {
            $this->debugError = array_map(static function ($value) {
                return is_object($value) ? get_object_vars($value) : $value;
            }, $debugMessage);
        } else if (is_scalar($debugMessage)) {
            $this->debugError = [$debugMessage];
        }

        return $this;
    }
}