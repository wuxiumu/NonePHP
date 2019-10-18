<?php

namespace NonePHP\Job;

use NonePHP\Exception\SystemConfigException;
use NonePHP\JobInterface;
use NonePHP\Tool\OpensslAuth;
use NonePHP\Tool\StringTool;
use NonePHP\Tool\XCurl;

class YacJob implements JobInterface
{
    public function handle($params = []): void
    {
        $helpText = <<<EOL
--------- Yac Admin Job ------------------------
*** this is yac admin job class **
$ usage: php public/index.php YacJob [options]
[options] contains [--type=info|reload] [--keys=]
------------------------------------------------
EOL;
        echo $helpText;
        $type = $params['type'] ?? '';
        $keys = $params['keys'] ?? '';
        if (!$type) {
            die(PHP_EOL . '请输入正确的type' . PHP_EOL);
        }

        load_file_env();

        if (!env('BASE_URI', null, true)) {
            die (PHP_EOL . '必须在.env中定义BASE_URI，用来发起curl请求' . PHP_EOL);
        }

        $private_key = env('PRIVATE_KEY', null, true);
        $private_key = str_replace("\\n", "\n", $private_key);
        if (!$private_key) {
            throw (new SystemConfigException())->debug('需要在.env中配置PRIVATE_KEY');
        }

        $timestamp = time();
        $params = [
            'type' => $type,
            'keys' => $keys,
            'timestamp' => $timestamp
        ];
        $_str = StringTool::getRequestParams($params);
        $_sign = OpensslAuth::generate_signature($_str, $private_key, OPENSSL_ALGO_SHA256);
        $xcurl = XCurl::getInstance([
            'url' => env('BASE_URI', null, true),
            'headers' => [
                'sign' => $_sign,
                'x-env' => true
            ]
        ]);

        print_r($xcurl->get('yac', $params));
    }
}