<?php

declare(strict_types=1);

namespace Gzqsts\Core\exception;

class ThrottleException extends BaseException
{
    /**
     * @var int
     */
    public $statusCode = 429;

    public $errorCode = 429;
    /**
     * @var string
	 * 访问过于频繁
     */
    public $errorMessage = 'Too Many Requests';
}