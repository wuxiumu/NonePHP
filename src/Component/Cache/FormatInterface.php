<?php

namespace NonePHP\Component\Cache;

interface FormatInterface
{
    public function setLifetime(int $lifetime);
    public function getLifetime();
    public function beforeSave($data);
    public function afterRetrieve($data);
}