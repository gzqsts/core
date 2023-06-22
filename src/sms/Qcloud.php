<?php

namespace Gzqsts\Core\sms;

/*
 *
在php8版本下:
腾讯云官方短信插件bug修改如下:

/vendor/qcloudsms/qcloudsms_php/src/SmsSingleSender.php:82

原:
public function sendWithParam($nationCode, $phoneNumber, $templId = 0, $params,
    $sign = "", $extend = "", $ext = "")

改为:
public function sendWithParam($nationCode, $phoneNumber, $templId = 0, $params=‘’,
    $sign = "", $extend = "", $ext = "")

 */

use Qcloud\Sms\SmsSingleSender;

class Qcloud
{
    protected $config;
    protected $status;
    protected $sms;

    public function __construct($config)
    {
        $this->config = $config;
        if (empty($this->config['appid']) || empty($this->config['appkey'])) {
            $this->status = false;
        } else {
            $this->status = true;
            $this->sms = new SmsSingleSender($this->config['appid'], $this->config['appkey']);
        }
    }

    public function send($mobile, $params, $areacode)
    {
        $data = [
            'code' => 100,
            'msg' => ''
        ];
        if ($this->status) {
            $result = $this->sms->sendWithParam($areacode, $mobile, $this->config['template_id'], $params, $this->config['sign_name'], "", "");
            $result = json_decode($result,true);
            if ($result['result'] == 0) {
                $data['code'] = 0;
                $data['msg'] = '发送成功';
            } else {
                $data['code'] = $result['result'];
                $data['msg'] = '发送失败，'.$result['errmsg'];
            }
        } else {
            $data['code'] = 100;
            $data['msg'] = '请在后台设置appid和appkey';
        }
        return $data;
    }
}