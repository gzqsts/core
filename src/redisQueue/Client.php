<?php

namespace Gzqsts\Core\redisQueue;

use Workerman\RedisQueue\Client as RedisClient;

/**
 * Class RedisQueue
 * @package support
 *
 * Strings methods
 * @method static void send($queue, $data, $delay=0)
 */
class Client
{
    /**
     * @var Client[]
     */
    protected static $_connections = null;
    

    /**
     * @param string $name
     * @return RedisClient
     */
    public static function connection($name = 'default') {
        if (!isset(static::$_connections[$name])) {
            $config = config('plugin.gzqsts.core.redis', []);
            if (!isset($config[$name])) {
                throw new \RuntimeException("RedisQueue connection $name not found");
            }
            $host = $config[$name]['host'];
            $options = $config[$name]['options'];
            $client = new RedisClient($host, $options);
            static::$_connections[$name] = $client;
        }
        return static::$_connections[$name];
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return static::connection('default')->{$name}(... $arguments);
    }
}
