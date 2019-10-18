<?php

namespace NonePHP\Component\Cache\Format;

use NonePHP\Component\Cache\Format;

class FormatNull extends Format
{
    public function beforeSave($data)
    {
        return $data;
    }

    public function afterRetrieve($data)
    {
        return $data;
    }
}