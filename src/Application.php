<?php

namespace NonePHP;

use NonePHP\Bll\YacBll;
use NonePHP\Exception\NotFoundException;
use NonePHP\Exception\SystemConfigException;
use NonePHP\Exception\UsageErrorException;
use NonePHP\Middleware\MiddlewareInterface;
use RuntimeException;
use function call_user_func_array;
use function class_exists;
use function define;
use function defined;
use function gettype;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;

class Application extends Injectable
{

    /**
     * 唯一请求ID
     */
    protected $_uniqueReqId;

    /**
     * app 事件列表
     */
    protected $_events = [];

    /**
     *  支持的事件类型
     */
    protected static $SUPPORT_EVENTS = [
        'notFound',
        'before',
        'after'
    ];

    /**
     * API 响应结构
     */
    public static $DEFAULT_RESULT = [
        'code' => 'err',
        'message' => 'msg',
    ];

    /**
     * cli or *cgi
     */
    protected $mode;

    /**
     * 初始化APP相关
     * @param DI $di
     * @throws BaseException
     * @throws RuntimeException
     */
    public function __construct(DI $di = null)
    {
        if (null === $di) {
            parent::__construct();
        } else {
            $this->di = $di;
        }

        $this->mode = PHP_SAPI;
        (new ErrorHandler())->register();
        defined('START_TIME') || define('START_TIME', microtime(true));
        defined('START_MEMORY') || define('START_MEMORY', memory_get_usage());
        defined('APP_NAME') || define('APP_NAME', 'NonePHP');
        defined('YAC_ENABLE') || define('YAC_ENABLE', PHP_SAPI !== 'cli' && class_exists('Yac', false));
        // 加载关键配置 .env
        if (!YAC_ENABLE) {
            load_file_env();
        } else if (!YacBll::getInstance()->getEnvVersion()) {
            YacBll::getInstance()->loadEnvFromFile();
        }

        define('APP_ENV', env('APP_ENV', static function () {
            return $_SERVER['ENVIRONMENT'] ?? 'development';
        }));
        define('APP_DEBUG', env('APP_DEBUG', false));
        $this->di->set('app', $this);

        $this->router->init();
        $this->setReqId();
    }

    /**
     * 主流程执行
     */
    public function handle()
    {
        if ($this->mode === 'cli') {
            return $this->handleCli();
        }
        return $this->handleCgi();
    }

    protected function handleCgi()
    {
        $result = '';
        $matched = $this->router->getMatched();
        $namespace = $matched['namespace'];
        $controller = $matched['controller'];
        $action = $matched['action'];
        $params = $matched['params'];

        $class = $namespace . '\\' . $controller . 'Controller';
        if (!class_exists($class)) {
            $baseClass = 'NonePHP\\Controller\\' . $controller . 'Controller';
            if (!class_exists($baseClass)) {
                $result = $this->notFound('class: ' . $class . ' not found');
            }
            $class = $baseClass;
        }

        if (!$result) {
            $classInstance = new $class();
            if (!is_callable([$classInstance, $action])) {
                $result = $this->notFound('class ' . $class . '@' . $action . ' not found');
            }
        }

        // 全局 before 事件
        if (!$result && isset($this->_events['before'])) {
            foreach ($this->_events['before'] as $event) {
                if ($result) {
                    break;
                }
                $result = $this->resolveEvent($event);
            }
        }

        // 执行 API 接口
        if (!$result && isset($classInstance) && is_callable([$classInstance, 'initialize'])) {
            $classInstance->initialize();
        }
        // API before middleware
        if (!$result && isset($classInstance) && is_callable([$classInstance, 'before'])) {
            // 且不包含在 noBefore 排除数组中 (必须为public)
            if (!isset($classInstance->noBefore) || (is_array($classInstance->noBefore) && !in_array($action, $classInstance->noBefore, true))) {
                $result = $classInstance->before();
            }
        }
        // API method 执行注解的 Middleware
        $annotations = $this->annotation->getMethod($class, $action);
        if ($annotations) {
            $annotationMiddleware = array_values(array_filter($annotations, static function ($value) {
                return !empty($value[0]) && !empty($value[1]) && $value[0] === '@middleware';
            }));

            foreach ($annotationMiddleware as $middleware) {
                if (!class_exists($middleware[1])) {
                    throw (new UsageErrorException())->debug(sprintf('middleware %s cannot found', $middleware[1]));
                }
                $_middleware = new $middleware[1];
                if (!$_middleware instanceof MiddlewareInterface) {
                    throw (new UsageErrorException())->debug(sprintf('middleware %s must instance MiddlewareInterface', $middleware[1]));
                }
                $_middleware->handle();
            }
        }

        // API 执行
        if (!$result && isset($classInstance)) {
            if ($params) {
                $result = call_user_func_array([$classInstance, $action], $params);
            } else {
                $result = $classInstance->$action();
            }
        }

        // 全局 after 事件
        if (isset($this->_events['after'])) {
            foreach ($this->_events['after'] as $event) {
                $result = $this->resolveEvent($event, [$result]);
            }
        }

        return $this;
    }

    protected function handleCli()
    {
        $HELP_TEXT = <<<EOT
----------------- job --------------------------
$ usage: php index.php [jobName] [-key=value]...

$ eg: php index.php jobName -k1=v1 -k2=v2

:)
------------------------------------------------

EOT;
        $opts = 'h';
        $options = getopt($opts);
        if (isset($options['h'])) {
            echo $HELP_TEXT;
            exit(0);
        }

        global $argv;
        array_shift($argv);

        if (empty($argv)) {
            echo $HELP_TEXT;
            exit(0);
        }

        $jobName = array_shift($argv);
        $jobNamespace = (defined('APP_NAME') ? ucfirst(APP_NAME) : 'App') . '\\Job';
        $jobClass = $jobNamespace . '\\' . ucfirst($jobName);
        if (!class_exists($jobClass)) {
            $jobClass = 'NonePHP\\Job\\' . ucfirst($jobName);
        }
        if (!class_exists($jobClass)) {
            throw (new NotFoundException('Job not found'))->debug('jobName:' . $jobName . ', jobClass:' . $jobNamespace . '\\' . ucfirst($jobName));
        }

        $jobInstance = new $jobClass;
        if (!$jobInstance instanceof JobInterface) {
            throw (new SystemConfigException('Job defined error'))->debug('jobClass: ' . $jobClass . ' 必须实现 None\JobInterface 接口');
        }

        $params = [];
        array_map(static function ($value) use (&$params) {
            $_value = ltrim($value, '-');
            $_value = explode('=', $_value);
            if (!empty($_value[0]) && !empty($_value[1])) {
                $params[$_value[0]] = $_value[1];
            }
        }, $argv);

        $jobInstance->handle($params);

        return $this;
    }

    public function output(): void
    {
        if ($this->mode !== 'cli' && !$this->response->hasSend()) {
            $this->response->send();
        }
    }

    public function getReqId()
    {
        return $this->_uniqueReqId;
    }

    public function addEvent(string $name, $definition): void
    {
        if (!in_array($name, static::$SUPPORT_EVENTS, true)) {
            throw (new SystemConfigException())->debug('Event ' . $name . ' in application is not support');
        }

        if (!isset($this->_events[$name])) {
            $this->_events[$name] = [];
        }
        $this->_events[$name][] = $definition;
    }

    protected function setReqId(): void
    {
        $_string = $this->router->getCmd();
        $_string .= serialize($this->request->get());
        $_string .= $this->request->getClientIp() . $this->request->getUserAgent();
        $_string .= getmygid() . microtime(true);
        $this->_uniqueReqId = substr(md5($_string), 12, 8);
        unset($_string);
    }

    protected function notFound(string $debugMsg = '')
    {
        if (isset($this->_events['notFound'])) {
            foreach ($this->_events['notFound'] as $event) {
                $result = $this->resolveEvent($event);
            }
        } else {
            throw (new NotFoundException('Url Not Found'))->debug($debugMsg);
        }
        return $result ?? null;
    }

    protected function resolveEvent($definition, $params = null)
    {
        if (is_string($definition)) {
            if (class_exists($definition) && is_callable([$instance = new $definition, 'handle'])) {
                $result = call_user_func_array([$instance, 'handle'], $params);
            } else {
                throw (new SystemConfigException('事件定义异常'))->debug('{' . $definition . '} cannot resolved! It is not a existed class or no handle method.');
            }
        } else if (is_callable($definition)) {
            $result = $definition($params);
        } else {
            throw (new SystemConfigException('事件定义异常'))->debug(gettype($definition) . ' type $definition is not callable definition.');
        }
        return $result;
    }

    protected function debug($message): void
    {
        if (is_dev()) {
            $this->logger->debug($message);
        }
    }
}
