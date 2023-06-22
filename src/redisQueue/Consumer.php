<?php

namespace Gzqsts\Core\redisQueue;

/**
 * Interface Consumer
 * @package Webman\RedisQueue
 */
interface Consumer
{
    public function consume($data);
}