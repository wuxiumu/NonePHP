<?php

namespace NonePHP;

use Exception;
use NonePHP\Component\Logger\File;
use NonePHP\Core\Annotation;
use NonePHP\Core\Request;
use NonePHP\Core\Response;
use NonePHP\Core\Router;

/**
 * @property Request $request;
 * @property Response $response;
 * @property Router $router;
 * @property File $logger;
 * @property Annotation $annotation;
 */
abstract class Injectable
{
    /** @var DI $di */
    protected $di;

    public function __construct()
    {
        $this->di = DI::getInstance();
    }

    /**
     * @param $name
     * @return bool|mixed
     * @throws Exception
     */
    public function __get($name)
    {
        if (!$this->di->has($name)) {
            return false;
        }
        return $this->di->getShared($name);
    }

    public function __set($name, $value)
    {
        $this->di->set($name, $value);
    }

    public function __isset($name)
    {
        return $this->di->has($name);
    }
}