<?php


namespace NonePHP\Core;

use NonePHP\Component\Cache\Backend\File;
use NonePHP\Component\Cache\CacheInterface;
use NonePHP\Component\Cache\Format\FormatJson;
use NonePHP\DI;
use NonePHP\Exception\CustomErrorException;
use NonePHP\Exception\SystemConfigException;
use NonePHP\SingleInstance;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileObject;
use function is_array;
use function strlen;

class Config
{
    use SingleInstance;
    /** @var CacheInterface $_cache */
    protected $_cache;
    protected $_cache_key = 'config:conf';

    public function __construct()
    {
        $di = DI::getInstance();
        // 依赖 configCache 服务
        if ($di->has('configCache')) {
            $this->_cache = $di->getShared('configCache');
            if (!$this->_cache->getFormat() instanceof FormatJson) {
                // 保持和默认的一致
                throw (new SystemConfigException())->debug('cache服务必须使用FormatJson格式化');
            }
        } else {
            $this->_cache = new File(new FormatJson([
                'lifetime' => 86400,
            ]), []);
        }
    }

    public static function get(string $name)
    {
        $_instance = static::getInstance();
        $_cache_key = $_instance->_cache_key;
        if (!$_instance->_cache->exists($_cache_key)
            && !$_instance->_cache->save($_cache_key, $_instance->getConfigFromDir())) {
            throw (new CustomErrorException('配置保存到缓存失败'))->debug($_cache_key);
        }

        $configs = $_instance->_cache->get($_cache_key);
        if (!$configs) {
            return [];
        }
        if (!$name) {
            return $configs;
        }

        $names = explode('.', $name);
        $_result = false;
        foreach ($names as $n) {
            if ($_result === null) {
                break;
            }
            if (is_array($_result)) {
                $_result = ($_result[$n] ?? null) ?: null;
            } else {
                $_result = ($configs[$n] ?? null) ?: null;
            }
        }
        return $_result ?: false;
    }

    public static function reload(): bool
    {
        $_instance = static::getInstance();
        $_cache_key = $_instance->_cache_key;
        if (!$_instance->_cache->exists($_cache_key)) {
            $_instance->_cache->delete($_cache_key);
        }

        return $_instance->_cache->save($_cache_key, $_instance->getConfigFromDir());
    }

    protected function getConfigFromDir(string $dir = ''): array
    {
        $_config = [];
        $dir = $dir ?: CONF_DIR . DIRECTORY_SEPARATOR . 'conf';
        $configDir = new RecursiveDirectoryIterator($dir);
        $configDirIterator = new RecursiveIteratorIterator($configDir, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($configDirIterator as $splFileInfo) {
            /** @var SplFileObject $splFileInfo */
            if ($splFileInfo->isDir()) {
                continue;
            }
            $fileName = $splFileInfo->getRealPath();
            $paths = substr($fileName, strlen($dir) + 1);
            $paths = $paths ? explode('/', $paths) : [];
            $_temp = '';
            foreach ($paths as $path) {
                $_path = explode('.', $path)[0];
                if (is_array($_temp)) {
                    if (!isset($_temp[$_path])) {
                        $_temp[$_path] = [];
                    }
                    $_temp = &$_temp[$_path];
                } else if ('' === $_temp) {
                    if (!isset($_config[$_path])) {
                        $_config[$_path] = [];
                    }
                    $_temp = &$_config[$_path];
                }
                if ($path === $splFileInfo->getFilename() && substr($path, -4) === '.php') {
                    $_temp = include $fileName;
                    unset($_temp);
                }
            }
        }
        return $_config;
    }
}