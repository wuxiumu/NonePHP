<?php

namespace NonePHP\Tool;

use NonePHP\BaseException;
use NonePHP\Exception\CustomErrorException;
use RuntimeException;

class OpensslAuth
{

    private $_private_key;
    private $_public_key;

    public function set_private_key($key): void
    {
        $this->_private_key = $key;
    }

    public function get_private_key()
    {
        return $this->_private_key;
    }

    public function set_public_key($key): void
    {
        $this->_public_key = $key;
    }

    public function get_public_key()
    {
        return $this->_public_key;
    }

    /**
     * 生成签名
     *
     * @param $salt
     * @param $private_key
     * @param int $type
     * @return string
     * @throws BaseException
     * @throws RuntimeException
     */
    public static function generate_signature($salt, $private_key, $type = OPENSSL_ALGO_SHA1): string
    {
        $res = openssl_pkey_get_private($private_key);
        if (!openssl_sign($salt, $signature, $res, $type)) {
            throw (new CustomErrorException())->debug('open ssl error');
        }
        return base64_encode($signature);
    }

    /**
     * 验证签名是否正确
     *
     * @param $salt
     * @param $public_key
     * @param $signature
     * @param int $type
     * @return bool
     */
    public static function verify_signature($salt, $public_key, $signature, $type = OPENSSL_ALGO_SHA1): bool
    {
        $signature = base64_decode($signature);
        $res = openssl_pkey_get_public($public_key);
        return openssl_verify($salt, $signature, $res, $type) === 1;
    }
}