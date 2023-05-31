<?php

/**
 * 身份认证异常类
 *
 */
declare(strict_types=1);

namespace Gzqsts\Core\exception;

class UnauthorizedHttpException extends BaseException
{
    /**
     * HTTP 状态码
     */
    public $statusCode = 401;

    public $errorCode = 401;
    /**
     * 错误消息.
     */
    public $errorMessage = 'Unauthorized error';
}
