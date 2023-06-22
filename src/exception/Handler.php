<?php

declare(strict_types=1);

namespace Gzqsts\Core\exception;

use Throwable;
use Webman\Exception\ExceptionHandler;
use Webman\Http\Request;
use Webman\Http\Response;

/*
----------正确使用状态码----------
200 OK - 对成功的 GET、PUT、PATCH 或 DELETE 操作进行响应。也可以被用在不创建新资源的 POST 操作上
201 Created - 对创建新资源的 POST 操作进行响应。应该带着指向新资源地址的 Location 头
202 Accepted - 服务器接受了请求，但是还未处理，响应中应该包含相应的指示信息，告诉客户端该去哪里查询关于本次请求的信息
204 No Content - 对不会返回响应体的成功请求进行响应（比如 DELETE 请求）
304 Not Modified - HTTP 缓存 header 生效的时候用
400 Bad Request - 请求异常，比如请求中的 body 无法解析
401 Unauthorized - 没有进行认证或者认证非法
403 Forbidden - 服务器已经理解请求，但是拒绝执行它
404 Not Found - 请求一个不存在的资源
405 Method Not Allowed - 所请求的 HTTP 方法不允许当前认证用户访问
410 Gone - 表示当前请求的资源不再可用。当调用老版本 API 的时候很有用
415 Unsupported Media Type - 如果请求中的内容类型是错误的
422 Unprocessable Entity - 用来表示校验错误
429 Too Many Requests - 由于请求频次达到上限而被拒绝访问
------------------------------------------------------------

::::异常类说明::::
统一错误码、统一客户端处理约定：
1.服务器内部错误/其它未自定义异常捕获（HTTP Status 500 - 错误码：500）（客户端：提示错误）
use Gzqsts\Core\exception\ServerErrorHttpException;
2.身份验证异常 - 没有进行认证或者认证非法（HTTP Status 401 - 错误码：401）
use Gzqsts\Core\exception\UnauthorizedHttpException;
    错误类型dataType (类型为空时，客户端提示错误)：
    1.login 登录（客户端：进入登录页面）
    2.quitLogin 强制退出登录（客户端：退出登录逻辑操作 - 返回首页）
    3.buyRole 角色权限不足（客户端：进去开通角色页面）
    4.buyCredits 积分不足（客户端：进入购买积分页面）
    5.buyMoney 钱包余额不足（客户端：进入购买余额页面）
    6.nullifyRole 用户角色过期而且无法自动切换角色（客户端：进入开通角色页面）
    7.errorUser 用户禁止访问/系统黑名单用户（客户端：进入系统错误页面）
    8.tokenError token操作错误（客户端：提示信息）
    9.addCreditsTip 积分提示（客户端：提示信息）
    10.encrypt 解密处理（客户端：解密数据）
3.资源授权异常类/接口限流访问/防抖/锁定/由于请求频次达到上限而被拒绝访问（HTTP Status 429 - 错误码：429）（客户端：提示错误）
use Gzqsts\Core\exception\ThrottleException;
4.资源/路由地址不存在异常类（HTTP Status 404 - 错误码：404）（客户端：跳转错误页面显示错误）
use Gzqsts\Core\exception\NotFoundHttpException;
5.当参数不是预期的类型时抛出 - 如果请求中的内容类型是错误的 （HTTP Status 415 - 错误码：415）InvalidArgumentException（客户端：提示错误）
6.用来表示校验错误 （HTTP Status 422 - 错误码：422）（客户端：提示错误）
use Gzqsts\Core\exception\UnprocessableException;

使用例:
$params = [
    'header' => [],
    'statusCode' => '',
    'errorCode' => '',
    'dataType' => '错误类型',
    'data' => [
        'customTip' => '自定义错误提示',
        'customUrl' => '自定义跳转页面地址（与customTip二选一）',
    ]
];
throw new UnprocessableException('提示消息',$params);
*/

class Handler extends ExceptionHandler
{
    /**
     * 不需要记录错误日志
     *
     * @var string[]
     */
    //public $dontReport = [];

    /**
     * HTTP Response Header.
     *
     * @var array
     */
    public $header = [];

    /**
     * HTTP Response Status Code.
     *
     * @var array
     */
    public $statusCode = 400;

    /**
     * Business Error code.
     *
     * @var int
     */
    public $errorCode = 400;

    /**
     * Business Error message.
     *
     * @var string
     */
    public $errorMessage = '';

    public $dataType = '';

    /**
     * 响应结果数据.
     *
     * @var array
     */
    protected $errData = [];

    /**
     * @var array
     */
    protected $responseData = [];
    /**
     * @param Throwable $exception
     */
    public function report(Throwable $exception)
    {
        if ($exception instanceof BaseException) {
            return;
        }
		//写入日志
        parent::report($exception);
    }

    /**
     * @param Request $request
     * @param Throwable $exception
     * @return Response
     */
    public function render(Request $request, Throwable $exception): Response
    {
        $this->addRequestInfoToResponse($request);
        $this->solveAllException($exception);
        $this->addDebugInfoToResponse($exception);
        return $this->buildResponse($request);
    }

    /**
     * 请求的相关信息.
     *
     * @param Request $request
     * @return void
     */
    protected function addRequestInfoToResponse(Request $request): void
    {
		if (config('app.debug', false)) {
			$this->errData = array_merge($this->errData, [
				'request_url' => $request->method() . ' ' . $request->fullUrl(),
				'time_date' => date('Y-m-d H:i:s'),
				'client_ip' => $request->getRealIp(),
				'request_param' => $request->all(),
				//判断是否是期待json返回
                //'JSON_expects' => $request->expectsJson(),
				//判断客户端是否接受json返回
                //'JSON_accept' => $request->acceptJson(),
                'inAjax' => $request->isAjax(),
				'UA' => $request->header('User-Agent')
			]);
		}
    }
	
    /**
     * 处理异常数据.
     *
     * @param Throwable $e
     */
    protected function solveAllException(Throwable $e)
    {
        if ($e instanceof BaseException) {
            $this->header = $e->header;
            $this->statusCode = $e->statusCode;
            $this->errorCode = $e->errorCode;
            $this->dataType = $e->dataType;
            $this->errorMessage = $e->errorMessage;
            if (isset($e->data)) {
                $this->responseData = $e->data;
            }
            return;
        }
        $this->solveExtraException($e);
    }

    /**
     * 处理扩展的异常.
     *
     * @param Throwable $e
     */
    protected function solveExtraException(Throwable $e): void
    {
        $this->errorMessage = $e->getMessage();
		$code = $e->getCode();
		if($e instanceof \InvalidArgumentException) {
			//当参数不是预期的类型时，抛出 InvalidArgumentException 。继承 LogicException ，继承 Exception ， PHP 7 引用接口 Throwable
            $this->errorCode = $this->statusCode = 415;
			//当参数不是预期的类型时抛出
            $this->errorMessage = 'Expected parameter Settings are abnormal:' . $e->getMessage();
        } else {
			$this->errorCode = $this->statusCode = !empty($code)?$code:500;
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * 调试模式：错误处理器会显示异常以及详细的函数调用栈和源代码行数来帮助调试，将返回详细的异常信息。
     * @param Throwable $e
     * @return void
     */
    protected function addDebugInfoToResponse(Throwable $e): void
    {
        if (config('app.debug', false)) {
            $this->errData['error_message'] = $this->errorMessage;
            $this->errData['error_trace'] = explode("\n", $e->getTraceAsString());
            $this->errData['file'] = $e->getFile();
            $this->errData['line'] = $e->getLine();
        }
    }

    /**
     * 构造 Response.
     *
     * @param Request $request
     * @return Response
     */
    protected function buildResponse(Request $request): Response
    {
        $responseBody = [
            'dataType' => $this->dataType,
            'code' => $this->errorCode,
            'msg'  => $this->errorMessage,
            'data' => $this->responseData
        ];
		if (config('app.debug', false)) {
			$responseBody['debug'] = $this->errData;
		}
        $header = array_merge(['Content-Type' => 'application/json'], $this->header);
		if ($request->expectsJson()) {
			return new Response($this->statusCode, $header, json_encode($responseBody, JSON_UNESCAPED_UNICODE));
		}
		//不是json请求正常返回HTTP状态码码
		return new Response($this->statusCode, $header, json_encode($responseBody, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
