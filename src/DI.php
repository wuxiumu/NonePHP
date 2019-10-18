<?php

namespace NonePHP;

use BadMethodCallException;
use Closure;
use NonePHP\Component\Logger\File;
use NonePHP\Core\Annotation;
use NonePHP\Core\Request;
use NonePHP\Core\Response;
use NonePHP\Core\Router;
use ReflectionClass;
use RuntimeException;
use Throwable;
use function call_user_func_array;
use function count;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;

/**
 * dependency injection 是把框架或者对象依赖的其他对象，通过传递的方式，解决依赖与被依赖之间高耦合问题。很大的方便了单元测试。DI 类通过 DI::getInstance() 获得当前的唯一实例，通过 set, get, getShared 方法设置和获取被依赖的对象
 **/
class DI
{
    protected $_services = [
        'request' => Request::class,
        'response' => Response::class,
        'router' => Router::class,
        'logger' => File::class,
        'annotation' => Annotation::class,
    ];

    protected $_services_resolved = [];
    protected static $_instance;

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static;
        }

        return static::$_instance;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->_services);
    }

    /**
     * just new instance at first time
     * @param string $name
     * @param null $parameters
     * @return mixed
     * @throws BadMethodCallException
     * @throws RuntimeException
     */
    public function getShared(string $name, $parameters = null)
    {
        if (empty($this->_services_resolved[$name])) {
            $this->_services_resolved[$name] = $this->get($name, $parameters);
        }
        return $this->_services_resolved[$name];
    }

    /**
     * always return new instance
     * @param string $name
     * @param null $parameters
     * @return null|mixed
     * @throws BadMethodCallException
     * @throws RuntimeException
     */
    public function get(string $name, $parameters = null)
    {
        if ($this->has($name)) {
            return $this->resolve($this->_services[$name], $parameters);
        }
        throw new BadMethodCallException('Service {' . $name . '} not defined!');
    }

    public function set(string $name, $definition): void
    {
        $this->_services[$name] = $definition;
        if (!empty($this->_services_resolved[$name])) {
            unset($this->_services_resolved[$name]);
        }
    }

    /**
     * @param $definition
     * @param $parameters
     * @return mixed
     * @throws RuntimeException
     */
    protected function resolve($definition, $parameters)
    {
        if (is_string($definition)) {
            if (class_exists($definition)) {
                if (is_array($parameters) && count($parameters) > 0) {
                    try {
                        $_instance = new ReflectionClass($definition);
                        $_instance = $_instance->newInstanceArgs($parameters);
                    } catch (Throwable $e) {
                        throw new RuntimeException('{' . $definition . '} cannot resolved![' . $e->getMessage() . ']');
                    }
                } else {
                    $_instance = new $definition;
                }
            } else {
                throw new RuntimeException('{' . $definition . '} cannot resolved! It is not a existed class.');
            }
        } else if (is_callable($definition)) {
            if (is_array($parameters) && count($parameters) > 0) {
                $_instance = call_user_func_array($definition, $parameters);
            } else {
                $_instance = $definition();
            }
        } else if (is_object($definition)) {
            if ($definition instanceof Closure) {
                $_instance = $definition();
            } else {
                $_instance = $definition;
            }
        } else {
            throw new RuntimeException('{ ' . $definition . '} should be a class string , either callable definition.');
        }

        return $_instance;
    }

    public function __destruct()
    {
        unset($this->_services, $this->_services_resolved);
    }
}
