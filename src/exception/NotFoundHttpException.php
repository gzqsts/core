<?php
/**
 * @desc 请求不存在异常类
 *
 */

declare(strict_types=1);

namespace Gzqsts\Core\exception;

class NotFoundHttpException extends BaseException
{
    /**
     * @var int
     */
    public $statusCode = 404;

    public $errorCode = 404;
    /**
     * @var string
	 * 未找到请求的资源
     */
    public $errorMessage = 'not found';
}
