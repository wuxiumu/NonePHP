<?php

/**
 * 校验接口请求参数：
 * 0. 必须包含 timestamp 参数（get，post, header 都可以），有效期 5 分钟
 * 1. 所有除了 sign 字段的请求参数（GET+POST）(如果 timestamp 在 header 中，需要加入到排序) 按字典排序 得到 array
 * 2. 把 array k1=v1&k2=v2 拼接字符串并 url_encode 后 得到 string
 * 3. 把 string 进行 DES 256 加密并 base64 后，加入到 sign 参数（get，post 都可以）
 * 4. 每个签名只能用一次
 *
 * 本组件依赖于 xx
 * 和 必须配置 PUBLIC_KEY
 */

namespace NonePHP\Middleware;

use NonePHP\Core\Request;
use NonePHP\DI;
use NonePHP\Exception\AuthFailedException;
use NonePHP\Exception\SystemConfigException;
use NonePHP\Tool\OpensslAuth;
use NonePHP\Tool\StringTool;

class AuthRequestMiddleware implements MiddlewareInterface
{
    protected $public_key;
    protected $forceLoadEnv = false;

    public function __construct()
    {
        !empty($_SERVER['HTTP_X_ENV']) and $this->forceLoadEnv = true;
        $this->forceLoadEnv && load_file_env();
        $this->public_key = env('PUBLIC_KEY', null, $this->forceLoadEnv);
        $this->public_key = str_replace("\\n", "\n", $this->public_key);
        if (!$this->public_key) {
            throw (new SystemConfigException())->debug('AuthRequestMiddleware必须配置PUBLIC_KEY');
        }
    }

    public function handle($result = null): void
    {
        $di = DI::getInstance();
        /** @var Request $request */
        $request = $di->getShared('request');
        $params = $request->get();

        $timestamp = $params['timestamp'] ?? $_SERVER['HTTP_TIMESTAMP'] ?? '';
        $sign = $_SERVER['HTTP_SIGN'] ?? '';
        if (empty($timestamp)) {
            throw (new AuthFailedException())->debug('timestamp不能为空');
        }
        if (empty($sign)) {
            throw (new AuthFailedException())->debug('sign不能为空');
        }
        if ($timestamp < time() - 300) {
            throw (new AuthFailedException())->debug('timestamp5分钟超时');
        }
        if ($timestamp > time()) {
            throw (new AuthFailedException())->debug('timestamp不能是未来的时间');
        }

        unset($params['sign']);
        if (array_key_exists('_url', $params)) {
            unset($params['_url']);
        }
        $str = StringTool::getRequestParams($params);

        if (!OpensslAuth::verify_signature($str, $this->public_key, $sign, OPENSSL_ALGO_SHA256)) {
            if (is_dev()) {
                $private_key = env('PRIVATE_KEY', null, $this->forceLoadEnv);
                $private_key = str_replace("\\n", "\n", $private_key);
                $sign = OpensslAuth::generate_signature($str, $private_key, OPENSSL_ALGO_SHA256);
                DI::getInstance()->getShared('logger')->debug('sign : ' . PHP_EOL . $sign);
            }
            throw (new AuthFailedException())->debug('sign error');
        }
    }
}