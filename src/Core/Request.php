<?php

namespace NonePHP\Core;

class Request
{
    protected $rawBody = '';

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isPut(): bool
    {
        return $this->method() === 'PUT';
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function getQuery(string $name = '', $default = null)
    {
        return $this->p_get('GET', $name, $default);
    }

    public function getPost(string $name = '', $default = null)
    {
        return $this->p_get('POST', $name, $default);
    }

    public function getPut(string $name = '', $default = null)
    {
        return $this->p_get('PUT', $name, $default);
    }

    public function get(string $name = '', $default = null)
    {
        $value = null;
        if (!$name) {
            $value = $this->getQuery();
            if ($this->isPut()) {
                $value = array_merge($value, $this->getPut() ?: []);
            } elseif ($this->isPost()) {
                $value = array_merge($value, $this->getPost() ?: []);
            }
            return $value;
        }

        if ($this->isPut()) {
            $value = $this->getPut($name, $default);
        }
        if (!$value && $this->isPost()) {
            $value = $this->getPost($name, $default);
        }
        if (!$value) {
            $value = $this->getQuery($name, $default);
        }

        return $value;
    }

    public function getRawBody()
    {
        if (!$this->rawBody) {
            $this->rawBody = file_get_contents('php://input');
        }
        return $this->rawBody;
    }

    /**
     * @param string $type
     * @param string $name
     * @param string $default
     * @return null|string|array
     */
    protected function p_get(string $type, string $name = '', $default = null)
    {
        switch ($type) {
            case 'GET':
                return !$name ? $_GET : $_GET[$name] ?? $default;
                break;
            case 'POST':
            case 'PUT':
                if ($this->getContentType() === 'application/json') {
                    $_data = json_decode($this->getRawBody(), true) ?: [];
                } else {
                    parse_str($this->getRawBody(), $_data);
                }

                return !$name ? $_data : ($_data[$name] ?? $default);
        }

        return null;
    }

    public function getClientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function getContentType(): string
    {
        $type = explode(',', $_SERVER['CONTENT_TYPE']);
        return strtolower($type[0] ?? '');
    }

    public function getCharset()
    {
        $type = explode(',', $_SERVER['CONTENT_TYPE']);
        if ($type[1] ?? '') {
            $charset = explode('=', trim($type[1]));
            return $charset[1] ?? '';
        }
        return '';
    }
}