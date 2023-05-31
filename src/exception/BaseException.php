<?php

declare(strict_types=1);

namespace Gzqsts\Core\exception;

class BaseException extends \Exception
{
    /**
     * HTTP Response Status Code. 400 请求异常，比如请求中的 body 无法解析
     */
    public $statusCode = 400;

    /**
     * Business Error code.
     *
     * @var int|mixed
     */
    public $errorCode = 400;

    /**
     * HTTP Response Header.
     */
    public $header = [];

    public $dataType = '';

    /**
     * Business Error message.
     * @var string
     */
    public $errorMessage = 'Request exception';

    /**
     * Business data.
     * @var array|mixed
     */
    public $data = [];

    /**
     * BaseException constructor.
     * @param string $errorMessage
     * @param array $params
     */
    public function __construct(string $errorMessage = '', array $params = [])
    {
        parent::__construct();
        if (!empty($errorMessage)) {
            $this->errorMessage = $errorMessage;
        }
        if (!empty($params)) {
            if (array_key_exists('statusCode', $params)) {
                $this->statusCode = $params['statusCode'];
            }
            if (array_key_exists('header', $params)) {
                $this->header = $params['header'];
            }
            if (array_key_exists('errorCode', $params)) {
                $this->errorCode = $params['errorCode'];
            }
            if (array_key_exists('data', $params)) {
                $this->data = $params['data'];
            }
            if (array_key_exists('dataType', $params)) {
                $this->dataType = $params['dataType'];
            }
        }
    }
}
