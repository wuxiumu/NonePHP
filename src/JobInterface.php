<?php

namespace NonePHP;

interface JobInterface
{
    public function handle($params = []);
}