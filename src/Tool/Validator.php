<?php

namespace NonePHP\Tool;

use DateTime;
use function in_array;
use InvalidArgumentException;
use function is_array;
use function is_callable;
use function is_string;
use function mb_strlen;
use NonePHP\SingleInstance;
use NonePHP\Exception\ParamsException;
use function strlen;

/**
 * 参数校验类
 *
 * 使用方法:
 *  Validator::getInstance()->validate($params, $rules);
 *
 * 支持校验规则:
 * 1) 校验是否必填
 *  - notEmpty      非必传参数, 当有参数时,不能为空, 当有校验类型 或者校验内容时, 不需要额外使用此验证
 *  - required      必须传参数, 数据必须包含 key, 如果后面类型校验存在且不是string时, 参数空字符串也可以通过, 这种情况可以使用 notEmpty 组合验证
 * 2) 校验类型
 *  - string        数据包含 key 时,值必须为 is_string
 *  - int           数据包含 key 时,值必须 filter_var 成 int 成功
 *  - float         数据包含 key 时,值必须为 filter_var 成 float 成功
 *  - email         数据包含 key 时,值必须为 filter_var 成 email 成功
 *  - alphaNumber   数据包含 key 时,值必须只能包含数组和字母
 *  - stringNumber  数据包含 key 时,值必须只能包含 0-9 数字
 *  - stringJson    数据包含 key 时,值必须 json 字符串, 且不能为空 eg: {"test": 1}
 *  - stringJsonList数据包含 key 时,值必须 json list 字符串, 且不能为空  eg: [{"test": 1}]
 *  - date          数据包含 key 时,值必须是 Y-m-d 的时间格式 eg: 2018-12-20, 2018-12-1
 *  - date:format   数据包含 key 时,值必须是 format 定义的时间格式 eg: 2018-12-20, 2018-12-1
 *  - datetime      数据包含 key 时,值必须 Y-m-d H:i:s 的时间格式 eg: 2018-12-12 00:00:00 (right)  2018-12-12 00:00:0 (wrong)
 * 3) 校验内容
 *  - min:\d        数据包含 key 时,值不能大于 \d
 *  - max:\d        数据包含 key 时,值不能小于 \d
 *  - lenMin:\d     数据包含 key 时,值长度不能大于 \d
 *  - lenMax:\d     数据包含 key 时,值长度不能小于 \d
 *  - enum:x,y,z    数据包含 key 时,值必须为枚举类型 (全等)
 *  - enums:x,y,z   数据包含 key 时,值必须为枚举类型 (非全等)
 *
 *  update
 *  性能从 ~35ms/千次 ->  ~22ms/千次
 */
class Validator
{
    use SingleInstance;

    // key不存在时填充的内容,true不填充，其他值为填充值
    protected $fillNotExist;
    // 当前要验证的参数
    protected $params;
    protected $errors;
    // 支持的校验存在与否
    protected static $_SUPPORT_EXIST = [
        'notEmpty', 'required'
    ];
    // 支持的校验数据类型
    protected static $_SUPPORT_TYPE = [
        'string', 'int', 'float', 'email', 'alphaNumber', 'stringNumber', 'stringJson', 'stringJsonList', 'date', 'datetime',
    ];
    // 支持的校验数据内容
    protected static $_SUPPORT_VAR = [
        'min', 'max', 'lenMin', 'lenMax', 'enum', 'enums'
    ];
    // map 映射
    protected static $_SUPPORT_MAP = [
        'notEmpty' => 'exist',
        'required' => 'exist',
        'string' => 'type',
        'int' => 'type',
        'float' => 'type',
        'email' => 'type',
        'alphaNumber' => 'type',
        'stringNumber' => 'type',
        'stringJson' => 'type',
        'stringJsonList' => 'type',
        'date' => 'type',
        'datetime' => 'type',
        'min' => 'var',
        'max' => 'var',
        'lenMin' => 'var',
        'lenMax' => 'var',
        'enum' => 'var',
        'enums' => 'var',
    ];

    public function setFillNotExist($fill = true)
    {
        $this->fillNotExist = $fill;
        return $this;
    }

    protected function isExist($key): bool
    {
        return array_key_exists($key, $this->params);
    }

    public function validate(array $params, array $rules): array
    {
        $this->params = $params;
        if (empty($rules)) {
            return [];
        }

        foreach ($rules as $key => $value) {
            $hasSubValidate = false;
            if (is_array($value)) {
                $hasSubValidate = true;
                // just use first key,value
                $subValue = array_values($value)[0];
                $value = array_keys($value)[0];
                if (!is_array($subValue)) {
                    throw (new ParamsException())->debug('子校验必须为数组');
                }
            }
            $default = '';
            $value = preg_replace_callback('/\{(.*)\}/', static function ($match) use (&$default) {
                $default = $match[1] ?? '';
                return '';
            }, $value);
            $value = trim($value, '|');
            unset($match);
            // 更新默认值
            if ($default !== '' && (!$this->isExist($key) || $this->params[$key] === '')) {
                $this->params[$key] = $default;
            }

            $desc = '';
            $value = preg_replace_callback('/\[(.*)\]/', static function ($match) use (&$desc) {
                $desc = $match[1] ?? '';
                return '';
            }, $value);
            $value = trim($value, '|');
            unset($match);

            $error = '';
            $value = preg_replace_callback('/\<(.*)\>/', static function ($match) use (&$error) {
                $error = $match[1] ?? '';
                return '';
            }, $value);
            $value = trim($value, '|');

            $rule = explode('|', $value);

            $newRule = [
                'exist' => [],
                'type' => '',
                'var' => [],
            ];
            foreach ($rule as $v) {
                $_v = explode(':', $v);
                $method = 'validate' . ucfirst($_v[0]);
                if (is_callable([$this, $method])) {
                    switch (static::$_SUPPORT_MAP[$_v[0]] ?? null) {
                        case 'exist':
                            $newRule['exist'][] = $method;
                            break;
                        case 'type':
                            if (!empty($_v[1])) {
                                $newRule['var'][] = !empty($_v[1]) ? ($method . ':' . $_v[1]) : $method;
                                break;
                            }
                            if (!empty($newRule['type'])) {
                                trigger_error('已定义过校验类型 [' . $newRule['type'] . '], 无需重复定义 type[' . $_v[0] . ']');
                            } else {
                                $newRule['type'] = $method;
                            }
                            break;
                        case 'var':
                            $newRule['var'][] = !empty($_v[1]) ? ($method . ':' . $_v[1]) : $method;
                            break;
                    }
                } else {
                    trigger_error('未定义的校验 type[' . $_v[0] . ']');
                }
            }

            // 开始校验
            if (!empty($newRule['exist'])) {
                foreach ($newRule['exist'] as $v) {
                    try {
                        $this->$v($key, $desc);
                    } catch (InvalidArgumentException $e) {
                        if ($error) {
                            throw new InvalidArgumentException($error);
                        }
                        throw $e;
                    }
                }
            }
            if (!empty($newRule['type'])) {
                $v = $newRule['type'];
                try {
                    $this->$v($key, $desc);
                } catch (InvalidArgumentException $e) {
                    if ($error) {
                        throw new InvalidArgumentException($error);
                    }
                    throw $e;
                }
            }
            if (!empty($newRule['var'])) {
                foreach ($newRule['var'] as $v) {
                    $_v = explode(':', $v);
                    $__v = $_v[0];
                    if (!empty($_v[1])) {
                        $this->$__v($key, $desc, $_v[1]);
                    } else {
                        $this->$__v($key, $desc);
                    }
                }
            }


            // 更新默认值
            if ($default !== '' && (!array_key_exists($key, $this->params) || empty($this->params[$key]))) {
                $this->params[$key] = $default;
            }
            if (!isset($this->params[$key])) {
                if ($this->fillNotExist !== true) {
                    $this->params[$key] = $params[$key] = $this->fillNotExist;
                }
            } else {
                switch ($newRule['type'] ?? '') {
                    case 'validateString':
                    case 'validateAlphaNumeric':
                    case 'validateStringNumber':
                        $this->params[$key] = $params[$key] = (string)$this->params[$key];
                        break;
                    case 'validateStringJson':
                    case 'validateStringJsonList':
                        $this->params[$key] = $params[$key] = json_decode($this->params[$key], true);
                        break;
                    case 'validateInt':
                        $this->params[$key] = $params[$key] = (int)$this->params[$key];
                        break;
                }
            }

            if ($hasSubValidate && !empty($subValue) && !empty($this->params[$key])) {
                if ('validateStringJson' === $newRule['type']) {
                    $params[$key] = $this->validate($params[$key], $subValue);
                } else if ('validateStringJsonList' === $newRule['type']) {
                    $_new = [];
                    foreach ($params[$key] as $v) {
                        $_new[] = $this->validate($v, $subValue);
                    }
                    $params[$key] = $_new;
                    unset($_new);
                }
                $this->params = $params; // 重置当前校验参数
            }
        }

        return array_intersect_key($this->params, $rules);
    }

    // 是否存在校验

    // 必须存在，且不能为空；不存在，可以通过验证
    protected function validateNotEmpty($key, $desc, bool $throw = true): bool
    {
        if (!$this->isExist($key)) {
            return false;
        }
        if ($this->params[$key] !== 0 && $this->params[$key] !== '0' && empty($this->params[$key])) {
            if (is_numeric($this->params[$key])) { // 浮点数 double 类型 0.00 也不为空
                return true;
            }
            if ($throw) {
                throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '不能为空');
            }

            return $this->params[$key] === [] || $this->params[$key] === false;
        }
        return true;
    }

    protected function validateRequired($key, $desc): bool
    {
        if (!$this->validateNotEmpty($key, $desc)) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '必须存在');
        }
        return true;
    }

    // 数据类型校验

    protected function validateString($key, $desc): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        if (!is_string($this->params[$key])) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '类型必须为字符串');
        }
        return true;
    }

    protected function validateInt($key, $desc): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        if (($this->params[$key] = filter_var($this->params[$key], FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE])) === null) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '类型必须为整型');
        }
        return true;
    }

    protected function validateFloat($key, $desc): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        if (($this->params[$key] = filter_var($this->params[$key], FILTER_VALIDATE_FLOAT, ['flags' => FILTER_NULL_ON_FAILURE])) === null) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '类型必须为 float');
        }
        return true;
    }

    protected function validateAlphaNumber($key, $desc): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        if (!preg_match('/^[a-zA-Z|0-9]+[a-zA-Z0-9]+$/', $this->params[$key])) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '类型必须为字母或数字组合');
        }
        return true;
    }

    protected function validateStringNumber($key, $desc): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        if (!preg_match('/^\d+$/', $this->params[$key])) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '类型必须为数字组合');
        }
        return true;
    }

    protected function validateStringJson($key, $desc): bool
    {
        $this->validateString($key, $desc);
        if (!$this->isExist($key)) {
            return true;
        }
        if ($this->params[$key] === '[]') {
            return true;
        }
        if (preg_match('/^\{.*\}$/', $this->params[$key])) {
            $ret = json_decode($this->params[$key], true);
            if (empty($ret) || json_last_error() !== JSON_ERROR_NONE) {
                throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ')
                    . '类型必须为非空 json 字符串');
            }
            return true;
        }

        throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ')
            . '类型必须为非空 json 字符串');
    }

    protected function validateStringJsonList($key, $desc): bool
    {
        $this->validateString($key, $desc);
        if (!$this->isExist($key)) {
            return true;
        }
        if ($this->params[$key] === '[]') {
            return true;
        }

        if (preg_match('/^\[\{.*\}\]$/', $this->params[$key])) {
            json_decode($this->params[$key], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '类型必须为合法 json list 字符串');
            }
            return true;
        }

        throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '类型必须为合法的 json list 字符串');
    }

    protected function validateEmail($key, $desc): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        if (($this->params[$key] = filter_var($this->params[$key], FILTER_VALIDATE_EMAIL, ['flags' => FILTER_NULL_ON_FAILURE])) === null) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '必须为邮箱格式');
        }
        return true;
    }

    protected function validateDatetime($key, $desc): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        $this->validateNotEmpty($key, $desc, false);
        DateTime::createFromFormat('Y-m-d H:i:s', $this->params[$key]);
        $errors = DateTime::getLastErrors();
        if ($errors['warning_count'] > 0 || $errors['error_count'] > 0) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '类型必须为日期类型(Y-m-d H:i:s)');
        }
        return true;
    }

    protected function validateDate($key, $desc, $format = 'Y-m-d'): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        DateTime::createFromFormat($format, $this->params[$key]);
        $errors = DateTime::getLastErrors();
        if ($errors['warning_count'] > 0 || $errors['error_count'] > 0) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '类型必须为日期类型(' . $format . ')');
        }
        return true;
    }

    // 数据内容校验

    protected function validateMin($key, $desc, $value): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        if ($this->params[$key] < $value) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '不能小于' . $value);
        }
        return true;
    }

    protected function validateMax($key, $desc, $value): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        if ($this->params[$key] > $value) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '不能大于' . $value);
        }
        return true;
    }

    protected function validateLenMin($key, $desc, $value): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        if (strlen($this->params[$key]) < (int)$value) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '长度不能小于' . $value);
        }
        return true;
    }

    protected function validateLenMax($key, $desc, $value): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        if (mb_strlen($this->params[$key]) > (int)$value) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '长度不能大于' . $value);
        }
        return true;
    }

    protected function validateEnum($key, $desc, $value): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        $values = explode(',', $value);
        $values = array_map(static function ($value) {
            if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
                return (int)$value;
            }
            if (filter_var($value, FILTER_VALIDATE_FLOAT) !== false) {
                return (float)$value;
            }
            return $value;
        }, $values);
        if (!in_array($this->params[$key], $values, true)) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '必须为枚举值[' . $value . ']');
        }
        return true;
    }

    protected function validateEnums($key, $desc, $value): bool
    {
        if (!$this->validateNotEmpty($key, $desc, false)) {
            return true;
        }
        $values = explode(',', $value);
        if (!in_array($this->params[$key], $values, false)) {
            throw (new ParamsException())->debug('参数 {' . $key . ($desc ? ('}-[' . $desc . '] ') : '} ') . '必须为枚举值[' . $value . ']');
        }
        return true;
    }
}