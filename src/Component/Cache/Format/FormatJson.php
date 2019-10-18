<?php

namespace NonePHP\Component\Cache\Format;

use NonePHP\Component\Cache\Format;
use RuntimeException;
use function is_array;
use function strlen;

class FormatJson extends Format
{
    public function afterRetrieve($data) :array
    {
        return json_decode($data, true) ?? [];
    }

    public function beforeSave($data) :string
    {
        if (is_array($data)) {
            $data = json_encode($data);
        } else if (is_scalar($data)) {
            $data = json_encode([$data]);
        } else {
            throw new RuntimeException('Format data type error');
        }
        if (strlen($data) > 1024*1024) {
            throw new RuntimeException('Cache data is too big');
        }
        return $data;
    }
}