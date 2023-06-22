<?php

namespace Gzqsts\Core\sms;

//aliyun阿里云配置
/*$config = [
    'type'          => 'aliyun',
    'region_id'     => 'cn-hangzhou',
    'access_key'    => 'XXXXXXXXXXXXXXXXX',
    'access_secret' => 'XXXXXXXXXXXXXXXXXXXXXXXXXX',
    'sign_name'     => '短信签名',
    //模板ID
    'template_id'   => $template_id,
    //模板替换参数 键值对形式
    'template_params'   => $params,
];*/
//qcloud腾讯云配置
/*$config = [
    'type'    => 'qcloud',
    'appid'   =>  '1400600000',//一串数字
    'appkey'  =>  'XXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
    'sign_name'  => '轻云科技',
    //模板ID
    'template_id'   => $template_id,
    //模板替换参数 键值对形式
    'template_params'   => $params,
];*/
//qiniu七牛云配置
/*$config = [
    'type'       => 'qiniu',
    'AccessKey'  =>  'XXXXXXXXXXXXXXXXXXXXXXXXXXX',
    'SecretKey'  =>  'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
    //模板ID
    'template_id'   => $template_id,
    //模板替换参数 键值对形式
    'template_params'   => $params,
];*/

class Wbsms
{

    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    //发送短信
    /*
     * $areacode qcloud时必传
     */
    public function sendsms($mobile, $areacode = '') : array
    {
        $data = [
            'code' => 100,
            'msg' => 'error'
        ];
        switch ($this->config['type']) {
            case 'aliyun':
                $config = array_merge($this->config, [
                    'version'       => '2017-05-25',
                    'host'          => 'dysmsapi.aliyuncs.com',
                    'scheme'        => 'http'
                ]);
                $sms = new Aliyun($config);
                $data = $sms->send($mobile, $this->config['template_params'], $areacode);
                break;
            case 'qcloud':
                //qcloud时必传国际区号
                if(empty($areacode)){
                    $areacode = '86';
                }
                $sms = new Qcloud($this->config);
                //需将内容数组主键序号化
                $params = $this->restoreArray($this->config['template_params']);
                $data = $sms->send($mobile, $params, $areacode);
                break;
            case 'qiniu':
                $sms = new Qiniu($this->config);
                $data = $sms->send($mobile, $this->config['template_params'], $areacode);
                break;
        }
        return $data;
    }

    /**
     * 数组主键序号化
     *
     * @arr  需要转换的数组
     */
    public function restoreArray($arr)
    {
        if (!is_array($arr)){
            return $arr;
        }
        $c = 0;
        $new = [];
        foreach ($arr as $key => $value) {
            $new[$c] = $value;
            $c++;
        }
        return $new;
    }

}