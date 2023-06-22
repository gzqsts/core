<?php

declare(strict_types=1);

namespace Gzqsts\Core\exception;

class UnprocessableException extends BaseException
{
    public $statusCode = 422;

    public $errorCode = 422;

    //422 Unprocessable Entity - 用来表示校验错误
    public $errorMessage = 'Entity error';

}
