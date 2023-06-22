<?php

namespace Gzqsts\Core\sms;

use Qiniu\Auth;
use Qiniu\Sms\Sms;

class Qiniu
{
    protected $config;
    protected $status;
    protected $sms;

    public function __construct($config)
    {
        $this->config = $config;
        if (empty($this->config['AccessKey']) || empty($this->config['SecretKey'])) {
            $this->status = false;
        } else {
            $this->status = true;
            $auth = new Auth($this->config['AccessKey'], $this->config['SecretKey']);
            $this->sms = new Sms($auth);
        }
    }

    public function send($mobile, $params, $areacode = '')
    {
        $data = [
            'code' => 100,
            'msg' => ''
        ];
        if ($this->status) {
            if(!empty($areacode)){
                $mobile = $areacode . $mobile;
            }
            $result = $this->sms->sendMessage($this->config['template_id'], $mobile, $params);
            if (isset($result[0]['job_id'])) {
                $data['code'] = 0;
                $data['msg'] = '发送成功';
            } else {
                $data['code'] = 100;
                $data['msg'] = '发送失败';
            }
        } else {
            $data['code'] = 100;
            $data['msg'] = '请在后台设置AccessKey和SecretKey';
        }
        return $data;
    }
}