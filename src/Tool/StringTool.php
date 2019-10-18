<?php

namespace NonePHP\Tool;

use Exception;

class StringTool
{
    /**
     * 获取请求参数，验证 AuthRequestMiddleware 的请求拼接字符串
     * @param array $request
     * @return string
     */
    public static function getRequestParams(array $request): string
    {
        ksort($request);
        $str = '';
        foreach ($request as $key => $value) {
            $str .= $key . '=' . $value . '&';
        }

        return urlencode(rtrim($str, '&'));
    }

    /**
     * 根据雪花算法生成分布式唯一ID
     */
    public static function genUUID(): string
    {
        $workFile = '/opt/snowflake/worker';
        if (!is_file($workFile) || !is_readable($workFile)) {
            $work = 1;
        } else {
            $work = (int)file_get_contents($workFile);
        }
        if (!$work) {
            $work = 1;
        }
        $workId = str_pad(decbin($work << 10), 64, 0, STR_PAD_LEFT);

        $timestamp = microtime(true) * 1000;
        $timestamp = (int)$timestamp;
        $timeId = str_pad(decbin($timestamp << 22), 64, 0, STR_PAD_LEFT);

        try {
            $randomId = random_int(1, 1023);
        } catch (Exception $e) {
            $randomId = 100;
        }
        $randomId = str_pad(decbin($randomId), 64, 0, STR_PAD_LEFT);

        $uuid = $timeId | $workId | $randomId;
        return (string)bindec($uuid);
    }

    /**
     * 解析一个uuid包含的信息
     * @param string $uuid
     * @return string
     */
    public static function decodeUUID(string $uuid): string
    {
        $uuid = str_pad(decbin($uuid), 64, 0, STR_PAD_LEFT);
        $timeId = bindec(substr($uuid, 0, 42));
        $workId = substr($uuid, 42, 12);
        $randomId = substr($uuid, 54);
        $str = 'create-time: ' . date('Y-m-d H:i:s', substr($timeId, 0, 10)) . ' ' . substr($timeId, 10);
        $str .= ';dc: ' . bindec(substr($workId, 0, 6));
        $str .= ';worker: ' . bindec(substr($workId, 6));
        $str .= ';random: ' . bindec($randomId);

        return $str;
    }

    /**
     * 数组转成树结构
     * @param array $data 原始数组
     * @param int $pid 初始的父id
     * @param string $id_field 主id
     * @param string $pid_field 父id
     * @return array
     */
    public static function array2tree(array &$data, int $pid = 0,
                                      string $id_field = 'id', string $pid_field = 'parent_id'): array
    {
        $tree = [];
        foreach ($data as $datum) {
            $_id = (int)$datum[$id_field];
            $_pid = (int)$datum[$pid_field];
            if ($_pid === $pid) {
                if ($children = static::array2tree($data, $_id, $id_field, $pid_field)) {
                    $datum['children'] = $children;
                }
                $tree[] = $datum;
                unset($data[$_id]);
            }
        }
        return $tree;
    }
}