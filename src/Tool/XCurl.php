<?php
/**
 * 基于 curl 的请求类
 * Created by PhpStorm.
 * User: deli
 * Date: 2018/4/26
 * Time: 下午2:31
 */

namespace NonePHP\Tool;

use NonePHP\Exception\SystemConfigException;
use NonePHP\SingleInstance;
use RuntimeException;
use function count;
use function is_array;
use function is_callable;

class XCurl
{
    use SingleInstance;

    private $host;
    private $timeout = 30;
    private $throw = true;
    // 是否开启 debug, 如果开启: 记录每次请求的日志
    private $debug = false;
    private $after_handlers = [];
    private $headers = [];
    public $origin;

    public function __construct(array $params = [])
    {
        $this->host = $params['host'] ?? '';
        if (!empty($params['timeout'])) {
            $this->timeout = (int)$params['timeout'];
        }
        if (!empty($params['debug'])) {
            $this->debug = true;
        }
        foreach ($params['headers'] ?? [] as $key => $value) {
            if (strpos($value, ':') > 0) {
                $_value = explode(':', $value, 2);
                $this->headers[strtolower($_value[0] ?? '')] = trim($_value[1] ?? '');
            } else {
                $this->headers[strtolower($key)] = $value;
            }
        }
        if (empty($this->headers['content-type'])) {
            $this->headers['content-type'] = 'application/x-www-form-urlencoded';
        }
    }

    public function setHeader(string $key, string $value): void
    {
        $this->headers[strtolower($key)] = trim($value);
    }

    public function afterHandler($func): void
    {
        if (is_callable($func)) {
            $this->after_handlers[] = $func;
        } else if (is_array($func) && count($func) >= 2 && method_exists($func[0], $func[1])) {
            $this->after_handlers[] = $func;
        } else {
            throw new RuntimeException('XCurl After Handler 类型错误!');
        }
    }

    /**
     * @param string $uri
     * @param array $query
     * @return array|mixed|null
     * @throws SystemConfigException()
     */
    public function get(string $uri, array $query = [])
    {
        return $this->_doRequest($uri, $query);
    }

    /**
     * @param string $uri
     * @param array $query
     * @param array $post
     * @return array|mixed|null
     * @throws SystemConfigException()
     * @throws RuntimeException
     */
    public function post(string $uri, array $query = [], array $post = [])
    {
        return $this->_doRequest($uri, $query, $post, true);
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function setThrowExceptionOnFailure(bool $throw = true): void
    {
        $this->throw = $throw;
    }

    /**
     * @param string $uri
     * @param array $query
     * @param array $post
     * @param bool $is_post
     * @return array|mixed|null
     * @throws SystemConfigException()
     * @throws RuntimeException
     */
    protected function _doRequest(string $uri, array $query = [], array $post = [], bool $is_post = false)
    {
        $url = $this->_buildUrl($uri);
        $_query = http_build_query($query);
        $_post = $this->_buildPost($post);

        if (!empty($query) && strpos('?', $url) !== false) {
            $url = ltrim($url, '&?') . '&' . $_query;
        } else if (!empty($query)) {
            $url = ltrim($url, '&?') . '?' . $_query;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $_headers = [];
        foreach ($this->headers as $key => $value) {
            $_headers[] = $key . ':' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $_headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'XClient_Chrome_UA_V1.0');

        if ($is_post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $_post);
        }

        $this->origin = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($this->debug) {
            $_log = 'curl debug -> (' . ($is_post ? 'POST' : 'GET') . ') ' . $this->_buildUrl($uri) . PHP_EOL;
            $_log .= '  |- headers: ' . json_encode($this->headers) . PHP_EOL;
            if (!empty($query)) {
                $_log .= '  |- query: ' . http_build_query($query) . PHP_EOL;
            }
            if ($is_post) {
                $_post = serialize($_post);
                $_log .= '  |- post:' . (strlen($_post) > 1024 ? ('1kb..' . substr($_post, 1024)) : $_post) . PHP_EOL;
            }
            $_log .= "  |- httpCode:$http_code" . PHP_EOL;
            $_log .= '  |- response:' . substr($this->origin, 0, 1000) . PHP_EOL;
            $this->_saveLog($_log);
        }

        if ($this->origin === false) {
            $errorNo = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($this->throw) {
                throw new RuntimeException('[XCurl Error][code=' . $errorNo . ']' . '[msg:' . $error . ']');
            }
        }
        curl_close($ch);
        if (!empty($errorNo)) {
            return [
                'err' => $errorNo,
                'msg' => $error ?? ''
            ];
        }
        $result = json_decode($this->origin, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result = [];
        }
        foreach ($this->after_handlers as &$after) {
            if (is_callable($after)) {
                $result = $after($result);
            } else if (is_array($after)) {
                $result = $after($result);
            }
        }
        unset($after);

        return $result;
    }

    /**
     * @param array $post
     * @return array|false|string
     * @throws SystemConfigException()
     * @throws RuntimeException
     */
    private function _buildPost(array $post)
    {
        if (empty($post)) {
            return '';
        }

        if (strpos('application/x-www-form-urlencoded', $this->headers['content-type']) === 0) {
            return http_build_query($post);
        }

        if (strpos('application/json', $this->headers['content-type']) === 0) {
            return json_encode($post);
        }

        if (strpos('multipart/form-data', $this->headers['content-type']) === 0) {
            return $post;
        }

        throw new SystemConfigException('XCurl 暂不支持 Content-Type:' . $this->headers['content-type']);
    }

    private function _buildUrl($uri): string
    {
        if (strpos($uri, 'http://') === 0 || strpos($uri, 'https://') === 0) {
            return $uri;
        }

        return rtrim($this->host, '/') . '/' . ltrim($uri, '/');
    }

    private function _saveLog($message): void
    {
        if (!$this->debug) {
            return;
        }
        XLog()->debug($message);
    }
}