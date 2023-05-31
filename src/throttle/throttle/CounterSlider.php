<?php

namespace Gzqsts\Core\throttle\throttle;
use Gzqsts\Core\laravelCache\Cache;

/**
 * 计数器滑动窗口算法
 */
class CounterSlider extends ThrottleAbstract
{
    public function allowRequest(string $key, float $micronow, int $max_requests, int $duration): bool
    {
        $key = 'Throttle:'.$key;
        $history = Cache::get($key, []);
        $now     = (int)$micronow;
        // 移除过期的请求的记录
        $history = array_values(array_filter($history, function ($val) use ($now, $duration) {
            return $val >= $now - $duration;
        }));

        $this->cur_requests = count($history);
        if ($this->cur_requests < $max_requests) {
            // 允许访问
            $history[] = $now;
            Cache::put($key, $history, $duration);
            return true;
        }

        if ($history) {
            $wait_seconds       = $duration - ($now - $history[0]) + 1;
            $this->wait_seconds = max($wait_seconds, 0);
        }

        return false;
    }

}