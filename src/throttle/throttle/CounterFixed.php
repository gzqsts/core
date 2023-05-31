<?php

namespace Gzqsts\Core\throttle\throttle;
use Gzqsts\Core\laravelCache\Cache;

/**
 * 计数器固定窗口算法
 */
class CounterFixed extends ThrottleAbstract
{

    public function allowRequest(string $key, float $micronow, int $max_requests, int $duration): bool
    {
        $cur_requests       = (int)Cache::get('Throttle:'.$key, 0);
        $now                = (int)$micronow;
        $wait_reset_seconds = $duration - $now % $duration;// 距离下次重置还有n秒时间
        $this->wait_seconds = $wait_reset_seconds % $duration + 1;
        $this->cur_requests = $cur_requests;
        if ($cur_requests < $max_requests) {   // 允许访问
            Cache::put('Throttle:'.$key, $this->cur_requests + 1, $wait_reset_seconds);
            return true;
        }
        return false;
    }
}