<?php

if (!function_exists('laravel_event')) {
    /**
     * 调用事件
     * @param $event
     * @param array $payload
     * @param bool $halt
     * @return void
     */
    function laravel_event($event, array $payload = [], bool $halt = false)
    {
        \Gzqsts\Core\laravelEvent\Event::dispatch($event, $payload, $halt);
    }
}