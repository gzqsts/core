<?php

declare(strict_types=1);
namespace Gzqsts\Core\throttle;

use support\Container;
use Webman\Http\{Request,Response};
use Gzqsts\Core\throttle\throttle\{CounterFixed, ThrottleAbstract};
use Gzqsts\Core\exception\ThrottleException;
use function sprintf;

/*
自定函数返回一个key
实例：在回调函数里针对不同控制器和方法定制生成key，中间件会进行转换:
'key' => function($throttle, $request) {
    return 'deviceId');
},

//'key' => 'controller/action/ip' //上述配置的快捷实现
示例三：在闭包内修改本次访问频率或临时更换限流策略：(PS：此示例需要本中间件在路由中间件后启用，这样预设的替换功能才会生效。)
'key' => function($throttle, $request) {
    $throttle->setRate('5/m');                      // 设置频率
    $throttle->setDriverClass(CounterSlider::class);// 设置限流策略
    return true;
},

示例四：在路由中独立配置
Route::any('/api/driver-ocr', [ app\api\controller\Ocr::class, 'driver'])->middleware([
    \Gzqsts\Core\throttle\throttle\Throttle::class
]);

Route::group('/path', function() {
    //路由注册
    ...
})->setParams(['visit_rate' => '20/m',
    ...
])->middleware(\Gzqsts\Core\throttle\throttle\Throttle::class);*/

class Throttle
{
    public static $duration = [
        's' => 1,
        'm' => 60,
        'h' => 3600,
        'd' => 86400,
    ];
	
    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    protected $key = null;          // 解析后的标识
    protected $wait_seconds = 0;    // 下次合法请求还有多少秒
    protected $now = 0;             // 当前时间戳
    protected $max_requests = 0;    // 规定时间内允许的最大请求次数
    protected $expire = 0;          // 规定时间
    protected $remaining = 0;       // 规定时间内还能请求的次数
	
    /**
     * @var ThrottleAbstract|null
     */
    protected $driver_class = null;
	/**
	 * Throttle constructor.
	 * @param Cache $cache
	 * @param Config $config
	*/
	public function __construct(array $params = [])
	{
		$this->config = array_merge(config('plugin.gzqsts.core.app.throttle',[]), $params);
	}
	
    /**
     * 请求是否允许
     * @param Request $request
     * @return bool
     */
    protected function allowRequest(Request $request): bool
    {
        // 若请求类型不在限制内
        if (!in_array($request->method(), $this->config['visit_method'])) {
            return true;
        }
        $key = $this->getCacheKey($request);
        if (null === $key) {
            return true;
        }
        [$max_requests, $duration] = $this->parseRate($this->config['visit_rate']);

        $micronow = microtime(true);
        $now      = (int)$micronow;

        $this->driver_class = Container::make($this->config['driver_name'], []);
        if (!$this->driver_class instanceof ThrottleAbstract) {
            throw new ThrottleException('The throttle driver must extends ' . ThrottleAbstract::class);
        }
        $allow = $this->driver_class->allowRequest($key, $micronow, $max_requests, $duration);

        if ($allow) {
            // 允许访问
            $this->now          = $now;
            $this->expire       = $duration;
            $this->max_requests = $max_requests;
            $this->remaining    = $max_requests - $this->driver_class->getCurRequests();
            return true;
        }

        $this->wait_seconds = $this->driver_class->getWaitSeconds();
        return false;
    }

    /**
     * 处理限制访问
     * @param Request $request
     * @param array $params
     * @return Response
     * @exception
     */
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        if (!empty($params)) {
            $this->config = array_merge($this->config, $params);
        }
        $allow = $this->allowRequest($request);
        if (!$allow) {
            // 访问受限
            $header = [];
            if ($this->config['visit_enable_show_rate_limit']) {
                $header['Retry-After'] = $this->wait_seconds;
            }
            //访问频繁稍后在试
            throw new ThrottleException(trans('middleware.throttleErr',[], 'messages'), ['header' => $header]);
        }
        $response = $next($request);
        if ((200 <= $response->getStatusCode() || 300 > $response->getStatusCode()) && $this->config['visit_enable_show_rate_limit']) {
            // 将速率限制 headers 添加到响应中
            $response->withHeaders($this->getRateLimitHeaders());
        }
        //var_export('执行访问限制');
        return $response;
    }

    /**
     * 生成缓存的 key
     * @param Request $request
     * @return null|string
     */
    protected function getCacheKey(Request $request): ?string
    {
        $key = $this->config['key'];
        if ($key instanceof \Closure) {
            $key = $key($this, $request);
        }
        if ($key === null || $key === false || $this->config['visit_rate'] === null) {
            // 关闭当前限制
            return null;
        }
        if ($key === true) {
            $key = $request->getRealIp($safe_mode = true);
        }else if ($key == 'deviceId') {
            $Devices = QTgetDevices();
            if($Devices['deviceId']){
                $key = $Devices['deviceId'];
            }
        } else {
            $key = $request->controller . '/' . $request->action . '/' . $request->getRealIp($safe_mode = true);
        }
        return md5($this->config['prefix'] . $key . $this->config['driver_name']);
    }

    /**
     * 解析频率配置项
     * @param string $rate
     * @return int[]
     */
    protected function parseRate($rate): array
    {
        [$num, $period] = explode("/", $rate);
        $max_requests = (int)$num;
        $duration     = static::$duration[$period] ?? (int)$period;
        return [$max_requests, $duration];
    }

    /**
     * 设置速率
     * @param string $rate '10/m'  '20/300'
     * @return $this
     */
    public function setRate(string $rate): self
    {
        $this->config['visit_rate'] = $rate;
        return $this;
    }

    /**
     * 设置限流算法类
     * @param string $class_name
     * @return $this
     */
    public function setDriverClass(string $class_name): self
    {
        $this->config['driver_name'] = $class_name;
        return $this;
    }

    /**
     * 获取速率限制头
     * @return array
     */
    public function getRateLimitHeaders(): array
    {
        return [
            'X-Rate-Limit-Limit'     => $this->max_requests,
            'X-Rate-Limit-Remaining' => max($this->remaining, 0),
            'X-Rate-Limit-Reset'     => $this->now + $this->expire,
        ];
    }
}