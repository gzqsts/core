<?php

namespace Gzqsts\Core\redisQueue;

use Workerman\Timer;

/**
 * Class RedisQueue
 * @package support
 *
 * Strings methods
 * @method static void send($queue, $data, $delay=0)
 */
class Redis
{
    /**
     * @var RedisConnection[]
     */
    protected static $_connections = [];

    /**
     * @param string $name
     * @return RedisConnection
     */
    public static function connection($name = 'default') {
        if (!isset(static::$_connections[$name])) {
            $config = config('plugin.gzqsts.core.redis', []);
            if (!isset($configs[$name])) {
                throw new \RuntimeException("RedisQueue connection $name not found");
            }
            $config = $configs[$name];
            static::$_connections[$name] = static::connect($config);
        }
        return static::$_connections[$name];
    }

    protected static function connect($config)
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Please make sure the PHP Redis extension is installed and enabled.');
        }

        $redis = new RedisConnection();
        $address = $config['host'];
        $config = [
            'host' => parse_url($address, PHP_URL_HOST),
            'port' => parse_url($address, PHP_URL_PORT),
            'db' => $config['options']['database'] ?? $config['options']['db'] ?? 0,
            'auth' => $config['options']['auth'] ?? '',
            'timeout' => $config['options']['timeout'] ?? 2,
            'ping' => $config['options']['ping'] ?? 55,
            'prefix' => $config['options']['prefix'] ?? '',
        ];
        $redis->connectWithConfig($config);
        return $redis;
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
