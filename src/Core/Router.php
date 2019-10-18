<?php

namespace NonePHP\Core;

use NonePHP\DI;

class Router
{
    protected $_routes = [];
    protected $_uri = '';
    protected $_default_namespace = 'App\Controller';
    protected $_default_controller = 'Index';
    protected static $_match = [
        'namespace' => '',
        'controller' => '',
        'action' => '',
        'params' => []
    ];

    public function __construct()
    {
        if (defined('APP_NAME') && APP_NAME) {
            $this->_default_namespace = ucfirst(APP_NAME) . '\Controller';
        }
    }

    /**
     * @param string $uri
     * @param string $controller
     * @param string $namespace
     * @param string $action
     * @param string $method
     * @param array $params
     */
    public function addRoute(string $uri, string $controller,
                             string $namespace = '', string $action = '', string $method = 'GET',
                             array $params = []): void
    {
        $this->_routes[strtoupper($method) . ' ' . $uri] = [
            'namespace' => $namespace ?: $this->_default_namespace,
            'controller' => $controller,
            'action' => $action ?: (strtolower($method) . 'Action'),
            'params' => $params
        ];
    }

    public function getRoute(string $cmd = '')
    {
        if ($cmd) {
            return $this->_routes[$cmd] ?? static::$_match;
        }

        return $this->_routes;
    }

    /**
     * 获取请求路径
     * 格式 '{Http Method} + $uri}'
     */
    public function getCmd(): string
    {
        /** @var Request $request */
        $request = DI::getInstance()->getShared('request');
        return strtoupper($request->method()) . ' ' . $this->_uri;
    }

    /**
     * 解析请求路径
     */
    public function init(): void
    {
        /** @var Request $request */
        $request = DI::getInstance()->getShared('request');
        // 初始化当前请求路径
        $_uri = $request->getQuery('_url');
        if (!$_uri) {
            $_uri = $_SERVER['REQUEST_URI'] ?? '';
            $_uri = explode('?', $_uri);
            $_uri = $_uri[0];
        } else {
            unset($_GET['_url']);
        }
        $this->_uri = $_uri;
        if (defined('BASE_URI') && strpos($this->_uri, BASE_URI) === 0) {
            $this->_uri = substr($this->_uri, strlen(BASE_URI));
        }
        if (!$this->_uri) {
            $this->_uri = '/';
        }

        // 初始化当前匹配路由
        $params = [];
        if ($this->_uri === '/') {
            $controller = $this->_default_controller;
        } else {
            $parts = explode('/', trim($this->_uri, '/'));
            $controller = '';
            foreach ($parts as $index => $part) {
                if ($this->isParams($part)) {
                    $controller .= '\\Detail';
                    $params[] = $part;
                } else {
                    $controller .= '\\' . ucfirst($part);
                }
            }
            $controller = ltrim($controller, '\\');
            unset($parts);
        }
        $this->addRoute($this->_uri, $controller,
            '', '', $request->method(),
            $params);
        static::$_match = $this->getRoute($this->getCmd());
    }

    /**
     * 查看解析的结果
     * @return array
     */
    public function getMatched(): array
    {
        return static::$_match;
    }

    /**
     * 判断 uri 是否是变量参数
     * 1. 只要包含数字
     * @param $key
     * @return bool
     */
    protected function isParams($key): bool
    {
        return preg_match('/^.*\d+.*$/', $key) && !preg_match('/^v\d+$/', $key);
    }
}
