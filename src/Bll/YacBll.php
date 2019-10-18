<?php

namespace NonePHP\Bll;

use NonePHP\BaseException;
use NonePHP\BizDI;
use NonePHP\Exception\SystemConfigException;
use NonePHP\Exception\UsageErrorException;
use NonePHP\SingleInstance;
use NonePHP\Tool\Yac;
use RuntimeException;

class YacBll
{
    use BizDI;
    use SingleInstance;

    /** @var Yac $yac */
    public $yac;

    public function __construct()
    {
        if (!class_exists('Yac', false)) {
            throw (new UsageErrorException())->debug('缺少Yac扩展，不能使用Yac相关功能');
        }

        $this->addService('yac', Yac::class, [
            'prefix' => '_' . APP_NAME . '_',
            'lifetime' => 86400
        ]);
    }

    /**
     * 查询 yac 中的 env 配置版本号
     * 约定 env_version 存在, yac 中的配置必然是存在的, 否则需要重新加载
     * @return mixed
     * @throws RuntimeException
     */
    public function getEnvVersion()
    {
        return $this->yac->get('env_version');
    }

    /**
     * 加载 .env 文件到 yac 配置中
     * @return array|string
     * @throws BaseException
     * @throws RuntimeException
     */
    public function loadEnvFromFile()
    {
        if (!defined('YAC_ENABLE') || !YAC_ENABLE) {
            throw (new SystemConfigException('[Yac Error]'))->debug('Yac不可用, 无法加载缓存');
        }

        $conf = conf_from_file();
        // 保存当前版本号
        $conf['env_version'] = date('Y-m-d H:i:s');
        $this->yac->set($conf);
        error_log('yac reload ' . APP_NAME);

        if ($this->yac->get('env_version') === $conf['env_version']) {
            return $conf;
        }

        throw (new SystemConfigException('[Yac Error]'))->debug('Yac更新缓存失败');
    }

    /**
     * 获取 Yac 中的配置信息
     * @param string $keys
     * @return array
     * @throws BaseException
     * @throws RuntimeException
     */
    public function getYacInfo(string $keys = ''): array
    {
        if (!defined('YAC_ENABLE') || !YAC_ENABLE) {
            throw (new SystemConfigException('[Yac Error]'))->debug('Yac不可用, 无法获取缓存信息');
        }

        $keys = 'env_version,' . $keys;
        return [
            'info' => $this->yac->info(),
            'keys' => $this->yac->get(explode(',', $keys)),
        ];
    }

    /**
     * 删除 Yac 缓存
     * 只要情况缓存，所有的 __prefix__ 下的缓存全部情况，其他应用下次登录需要重新加载缓存
     */
    public function flushYac(): void
    {
        if (!defined('YAC_ENABLE') || !YAC_ENABLE) {
            throw (new SystemConfigException('[Yac Error]'))->debug('Yac不可用, 无法删除缓存');
        }

        if (!$this->yac->flush()) {
            throw (new SystemConfigException('[Yac Error]'))->debug('Yac清空缓存失败');
        }
    }
}