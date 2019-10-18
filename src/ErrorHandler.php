<?php

namespace NonePHP;


use Error;
use Exception;
use NonePHP\Component\Logger\LoggerInterface;
use NonePHP\Core\Request;
use NonePHP\Core\Response;
use Throwable;
use function in_array;

class ErrorHandler
{
    protected static $has_error = false;
    protected static $has_handle = false;
    /** @var null|Response $response */
    protected $response;

    public function register(): void
    {
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);

        // 处理 php 致命错误
        set_error_handler(function ($errorNo, $errorMsg, $errorFile, $errorLine) {
            // 错误控制符@，可以把当前的的错误标记改成 0
            if ($errorNo !== 0 && !($errorNo & error_reporting())) {
                return;
            }
            static::$has_error = !in_array($errorNo, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE], true);
            // 输出响应内容, 警告不输出
            !$this->response && DI::getInstance()->has('response') && $this->response = DI::getInstance()->getShared('response');

            if (static::$has_error && (!$this->response || !$this->response instanceof Response)) {
                // 无法正确获取组件
                tmp_log('无法加载Response组件, error:' . $errorMsg . ' file:' . $errorFile . '@' . $errorLine);
            } else if (static::$has_error) { // 接口输出
                $this->response->setStatusCode(503); // 服务不可用
                $this->response->setContentType('application/json', 'utf-8');
                $this->response->setJsonContent([
                    Application::$DEFAULT_RESULT['code'] => 503,
                    Application::$DEFAULT_RESULT['message'] => is_dev() ? $errorMsg : static::getCodeStr($errorNo),
                    'trace_id' => DI::getInstance()->has('app') ? DI::getInstance()->getShared('app')->getReqId() : '',
                ]);
            }

            $traces = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
            // 去掉与当前的
            array_shift($traces);

            $this->handle([
                'error_source' => 'error',
                'error_no' => $errorNo,
                'error_no_str' => static::getCodeStr($errorNo),
                'error_message' => $errorMsg,
                'error_file' => $errorFile,
                'error_line' => $errorLine,
                'error_trace' => $traces,
            ]);
        });

        // 统一处理 接口异常 处理, 不管什么异常，都会输出接口
        set_exception_handler(function ($e) {
            try {
                /** @var Exception|Error $e */
                $debug = [];
                if ($e instanceof BaseException) { // 继承自定义异常，包含debug信息，httpCode等信息
                    $code = $e->getBizCode();
                    $httpCode = $e->getHttpCode();
                    $appError = [
                        Application::$DEFAULT_RESULT['code'] => $code,
                        Application::$DEFAULT_RESULT['message'] => $e->getMessage(),
                    ];

                    $debug = $e->getDebug();
                    if (is_dev()) {
                        $appError['debug'] = $e->getDebug();
                    }
                } else {
                    // 否则其他异常,返回 Internal Server Error
                    $code = $e->getCode();
                    $httpCode = 500;
                    $appError = [
                        Application::$DEFAULT_RESULT['code'] => $httpCode,
                        Application::$DEFAULT_RESULT['message'] => is_dev() ? $e->getMessage() : '系统异常',
                    ];
                }

                !$this->response && DI::getInstance()->has('response') && $this->response = DI::getInstance()->getShared('response');
                if (!$this->response || !$this->response instanceof Response) {
                    tmp_log('无法加载Response组件- | trace: ' . $e->getTraceAsString());
                } else {
                    $appError['trace_id'] = DI::getInstance()->has('app') ? DI::getInstance()->getShared('app')->getReqId() : '';
                    $this->response->setStatusCode($httpCode);
                    $this->response->setContentType('application/json');
                    $this->response->setJsonContent($appError);
                }

                static::$has_error = $httpCode >= 500;
                $this->handle([
                    'error_source' => 'exception',
                    'error_no' => $code,
                    'error_no_str' => static::getCodeStr($code),
                    'error_message' => $e->getMessage() . ($debug ? ' | debug-> ' . json_encode($debug, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'error_trace' => $e->getTraceAsString(),
                ]);
            } catch (Throwable $throwable) {
                tmp_log($throwable->getMessage() . ' | trace: ' . $e->getTraceAsString());
            }
        });

        register_shutdown_function(// Access level to xxx must be public 类的错误.
            function () {
                if (($options = error_get_last()) !== null) {
                    $this->handle([
                        'error_source' => 'shutdown',
                        'error_no' => $options['type'],
                        'error_no_str' => static::getCodeStr($options['type']),
                        'error_message' => $options['message'],
                        'error_file' => $options['file'],
                        'error_line' => $options['line'],
                        'error_trace' => debug_backtrace(-1),
                    ]);
                }
            }
        );
    }

    /**
     * 统一处理 错误输出 & 日志
     * @param array $error
     * @throws Exception
     */
    public function handle(array $error): void
    {
        if (!static::$has_handle) {
            static::$has_handle = true;
        }

        if ($this->response && $this->response->getContent()) { // response 组件存在且未发送，输出响应
            !$this->response->hasSend() && $this->response->send();
        }

        $error = array_merge($error, static::getRequestInfo());
        if (!DI::getInstance()->has('logger')) {
            tmp_log(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }
        /** @var LoggerInterface $logger */
        $logger = DI::getInstance()->getShared('logger');
        if (!static::$has_error) { // warning log
            $logger->warn(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else if ($error['error_source'] === 'error') {
            $logger->fatal(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $logger->error(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    public static function getCodeStr($code): ?string
    {
        switch ($code) {
            case 0:
                return 'unknown';
            case E_ERROR:
                return 'E_ERROR';
            case E_WARNING:
                return 'E_WARNING';
            case E_PARSE:
                return 'E_PARSE';
            case E_NOTICE:
                return 'E_NOTICE';
            case E_CORE_ERROR:
                return 'E_CORE_ERROR';
            case E_CORE_WARNING:
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR:
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING:
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR:
                return 'E_USER_ERROR';
            case E_USER_WARNING:
                return 'E_USER_WARNING';
            case E_USER_NOTICE:
                return 'E_USER_NOTICE';
            case E_STRICT:
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR:
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED:
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED:
                return 'E_USER_DEPRECATED';
            // todo 定义用户业务code
            default:
                return 'USER_EXCEPTION';
        }
    }

    /**
     * 获取当前请求相关信息
     */
    protected static function getRequestInfo(): array
    {
        DI::getInstance()->has('request') && $request = DI::getInstance()->getShared('request');
        $ret = [
            'req.id' => DI::getInstance()->has('app') ? DI::getInstance()->getShared('app')->getReqId() : '',
            'req.cmd' => DI::getInstance()->has('router') ? DI::getInstance()->getShared('router')->getCmd() : '',
        ];
        if (isset($request) && $request instanceof Request) {
            $ret['req.get'] = json_encode($request->getQuery() ?: [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($request->isPost()) {
                $ret['req.post'] = json_encode($request->getPost() ?: [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else if ($request->isPut()) {
                $ret['req.put'] = json_encode($request->getPut() ?: [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            if (PHP_SAPI !== 'cli') {
                $ret['req.client'] = 'ip->' . $request->getClientIp() . ' agent->' . $request->getUserAgent();
            }
        }

        return $ret;
    }

    public static function hasError(): bool
    {
        return static::$has_error;
    }
}
