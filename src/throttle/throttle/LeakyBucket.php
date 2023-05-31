<?php

namespace Gzqsts\Core\throttle\throttle;
use Gzqsts\Core\laravelCache\Cache;

/**
 * 漏桶算法
 */
class LeakyBucket extends ThrottleAbstract
{

    public function allowRequest(string $key, float $micronow, int $max_requests, int $duration): bool
    {
        if ($max_requests <= 0) return false;
        $key = 'Throttle:'.$key;
        $last_time = (float)Cache::get($key, 0);      // 最近一次请求
        $rate      = (float)$duration / $max_requests;       // 平均 n 秒一个请求
        if ($micronow - $last_time < $rate) {
            $this->cur_requests = 1;
            $this->wait_seconds = ceil($rate - ($micronow - $last_time));
            return false;
        }

        Cache::put($key, $micronow, $duration);
        return true;
    }
}