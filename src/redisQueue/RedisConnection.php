<?php

namespace Gzqsts\Core\redisQueue;

use Workerman\Timer;
use Workerman\Worker;

class RedisConnection extends \Redis
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * @param array $config
     * @return void
     */
    public function connectWithConfig(array $config = [])
    {
        static $timer;
        if ($config) {
            $this->config = $config;
        }
        if (false === $this->connect($this->config['host'], $this->config['port'], $this->config['timeout'] ?? 2)) {
            throw new \RuntimeException("Redis connect {$this->config['host']}:{$this->config['port']} fail.");
        }
        if (!empty($this->config['auth'])) {
            $this->auth($this->config['auth']);
        }
        if (!empty($this->config['db'])) {
            $this->select($this->config['db']);
        }
        if (!empty($this->config['prefix'])) {
            $this->setOption(\Redis::OPT_PREFIX, $this->config['prefix']);
        }
        if (Worker::getAllWorkers() && !$timer) {
            $timer = Timer::add($this->config['ping'] ?? 55, function ()  {
                $this->execCommand('ping');
            });
        }
    }

    /**
     * @param $command
     * @param ...$args
     * @return mixed
     * @throws \Throwable
     */
    protected function execCommand($command, ...$args)
    {
        try {
            return $this->{$command}(...$args);
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());
            if ($msg === 'connection lost' || strpos($msg, 'went away')) {
                $this->connectWithConfig();
                return $this->{$command}(...$args);
            }
            throw $e;
        }
    }

    /**
     * @param $queue
     * @param $data
     * @param $delay
     * @return bool
     */
    public function send($queue, $data, $delay = 0)
    {
        $queue_waiting = '{redis-queue}-waiting';
        $queue_delay = '{redis-queue}-delayed';
        $now = time();
        $package_str = json_encode([
            'id'       => time().rand(),
            'time'     => $now,
            'delay'    => $delay,
            'attempts' => 0,
            'queue'    => $queue,
            'data'     => $data
        ]);
        if ($delay) {
            return (bool)$this->execCommand('zAdd' ,$queue_delay, $now + $delay, $package_str);
        }
        return (bool)$this->execCommand('lPush', $queue_waiting.$queue, $package_str);
    }
}
