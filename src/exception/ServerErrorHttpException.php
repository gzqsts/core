<?php

/**
 * @desc 服务器内部异常
 */

declare(strict_types=1);

namespace Gzqsts\Core\exception;

class ServerErrorHttpException extends BaseException
{
    /**
     * @var int
     */
    public $statusCode = 500;

    public $errorCode = 500;
    /**
     * @var string
     */
    public $errorMessage = 'Internal Server Error';
}
