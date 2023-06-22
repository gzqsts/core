<?php

declare(strict_types=1);

namespace Gzqsts\Core\exception;

class JwtTokenException extends BaseException
{
    public $statusCode = 401;
    public $errorCode = 401;

    public $errorMessage = 'token error';

}
