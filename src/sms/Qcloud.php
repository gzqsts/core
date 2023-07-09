<?php

namespace Gzqsts\Core\sms;

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
            $this->sms = new QcloudSmsSingleSender($this->config['appid'], $this->config['appkey']);
        }
    }

    public function send($mobile, $params, $areacode)
    {
        $data = [
            'code' => 100,
            'msg' => ''
        ];
        if ($this->status) {
            $result = $this->sms->sendWithParam($areacode, $mobile, $this->config['template_id'], $params, $this->config['sign_name']);
            $result = json_decode($result,true);
            //var_export($result);
            if ($result['result'] == 0) {
                $data['code'] = 0;
                $data['msg'] = '发送成功';
            } else {
                $data['code'] = $result['result'];
                $data['msg'] = 'Error:'.$result['errmsg'];
            }
        } else {
            $data['code'] = 100;
            $data['msg'] = '请在后台设置appid和appkey';
        }
        return $data;
    }
}