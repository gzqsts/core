<?php
/**
 * Copyright (C) 2020 gzqsts.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @Time: 2022/11/15 14:26
 * @Notes:
 */

namespace Gzqsts\Core\tool;

//创建证书
/*
$config = ['config' => 'E:\phpstudy_pro\Extensions\php\php8.0.2nts\extras\ssl\openssl.cnf'];
$public_key = base_path().'/resource/cert/cert_public.key';
$private_key = base_path().'/resource/cert/cert_private.pem';
$a = Encrypt::generateKey($config, $public_key, $private_key);
*/

//私钥加签名
//$a = Encrypt::rsaSign($params);
//公钥验证验签
//$a = Encrypt::verifySign($params, '验签');

//公钥加密 - 1028 非对称
//$a = Encrypt::publicEncrypt($params);
//公钥解密 - 1028 非对称
//$a = Encrypt::publicDecrypt($params);

//私钥加密 非对称
//$a = Encrypt::privateEncrypt($token);
//私钥解密 非对称
//$a = Encrypt::privateDecrypt($token);

//加密
//$a = Encrypt::encryptAES($params, $secret_key - 32位, $iv - 16位);
//解密
//$a = Encrypt::decryptAES($token,$secret_key - 32位, $iv - 16位);

class Encrypt
{

    private static int $level = 1024;

    //设置证书长度
    private static function setLevel(int $level): void
    {
        self::$level = $level;
    }

    private static function getPublicKey(): ? string
    {
        $dir = config('app.cert_public_path');
        return file_get_contents($dir);
    }

    private static function getPrivateKey(): ? string
    {
        $dir = config('app.cert_private_path');
        return file_get_contents($dir);
    }

    protected static function level():array
    {
        $array = [
            1024 => [117, 128],//1024字节证书密钥  1024/8-11  1024/8
            2048 => [245, 256],//2048字节证书密钥  2048/8-11  2048/8
            4096 => [501, 512],//4096字节证书密钥  4096/8-11  4096/8
        ];
        return $array[self::$level];
    }

    /**
     * url base64编码
     * @param $string
     * @return mixed|string
     */
    protected static function urlSafeBase64encode(string $string):string
    {
        return str_replace(array('+','/','='), array( '-','_',''), base64_encode($string));
    }

    /**
     * url base64解码 - 还原
     * @param $string
     * @return bool|string
     */
    protected static function urlSafeBase64decode(string $string): string
    {
        $data = str_replace(array('-','_'), array('+','/'), $string);
        $mod4 = strlen($data) % 4;
        if($mod4){
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

    /**
     * 公钥加密 - RSA非对称
     * @param $data string|array 需要加密的数据
     * @return string 返回加密串(base64编码)
     */
    public static function publicEncrypt(string|array $data, int $level = 1024): string
    {
        if(is_array($data)){
            $data = json_encode($data,320);
        }
        self::setLevel($level);
        //中文的情况下 不支持拆分
        $data = str_split($data, self::level()[0]);
        $encrypted = '';
        foreach($data as & $chunk){
            if(!openssl_public_encrypt($chunk, $encryptData, self::getPublicKey())){
                return '';
            }else{
                $encrypted .= $encryptData;
            }
        }
        return self::urlSafeBase64encode($encrypted);
    }

    /**
     * 公钥解密  - RSA非对称
     * @param $data string 需要解密的数据
     * @return string 返回解密串
     */
    public static function publicDecrypt(string $data, int $level = 1024): string|int|array
    {
        self::setLevel($level);
        $data = str_split(self::urlSafeBase64decode($data), self::level()[1]);
        $decrypted = '';
        foreach($data as & $chunk){
            if(!openssl_public_decrypt($chunk, $decryptData, self::getPublicKey())){
                return '';
            }else{
                $decrypted .= $decryptData;
            }
        }
        //判断是否需要转数组
        $crypto = json_decode($decrypted, true);
        return $crypto??$decrypted;
    }

    /**
     * 私钥加密  - RSA非对称
     * @param $data string 需要加密的数据
     * @return string 返回加密串(base64编码)
     */
    public static function privateEncrypt(array|string $data, int $level = 1024): string
    {
        if(is_array($data)){
            $data = json_encode($data,320);
        }
        self::setLevel($level);
        $data = str_split($data, self::level()[0]);
        $encrypted = '';
        foreach($data as & $chunk){
            if(!openssl_private_encrypt($chunk, $encryptData, self::getPrivateKey())){
                return '';
            }else{
                $encrypted .= $encryptData;
            }
        }
        return self::urlSafeBase64encode($encrypted);
    }

    /**
     * 私钥解密  - RSA非对称
     * @param $data string 需要解密的数据
     * @return string 返回解密串
     */
    public static function privateDecrypt(string $data, int $level = 1024): int|string|array
    {
        self::setLevel($level);
        $data = str_split(self::urlSafeBase64decode($data), self::level()[1]);
        $decrypted = '';
        foreach($data as & $chunk){
            if(!openssl_private_decrypt($chunk, $decryptData, self::getPrivateKey())){
                return '';
            }else{
                $decrypted .= $decryptData;
            }
        }
        //判断是否需要转数组
        $crypto = json_decode($decrypted, true);
        return $crypto??$decrypted;
    }

    /**
     * 私钥签名  - RSA非对称
     * @param $data 被加签数据
     * @return mixed|string
     */
    public static function rsaSign(array|string $data):string
    {
        if(is_array($data)){
            $data = json_encode($data,320);
        }
        //签名算法，SHA256WithRSA
        if(openssl_sign($data, $sign, self::getPrivateKey(), OPENSSL_ALGO_SHA256)){
            return self::urlSafeBase64encode($sign);
        }
        return '';
    }

    /**
     * 公钥验签  - RSA非对称
     * @param $data 被加签数据
     * @param $sign 签名
     * @return bool
     */
    public static function verifySign(array|string $data, string $sign):bool
    {
        if(is_array($data)){
            $data = json_encode($data,320);
        }
        //签名算法，SHA256WithRSA
        return (1 == openssl_verify($data, self::urlSafeBase64decode($sign), self::getPublicKey(), OPENSSL_ALGO_SHA256));
    }

    /**
     * 对称算法：AES DES、3DES - 加密
     * 公钥和私钥 - 操作加密
     * @param array $data
     * @param string $secret_key 必须32位 字母和数组组合
     * @param string $method 加密方法 DES-ECB DES-CBC DES-CTR DES-OFB DES-CFB等等
     * @param string $iv 密初始化向量（可选） 必须26位 字母和数组组合
     * @param string $options 数据格式选项（可选）【选项有：】：0, OPENSSL_RAW_DATA=1,OPENSSL_ZERO_PADDING=2,OPENSSL_NO_PADDING=3
     * @return string bs64字符串
     */
    public static function encryptAES(array|string $data, string $secret_key, string $iv = '',string $method = 'aes-256-cbc', $options = OPENSSL_RAW_DATA): string
    {
        if(strlen($secret_key)!=32){
            return '';
        }
        //如果method为DES-ECB，则method为DES−ECB，则iv无需填写
        if(strtoupper($method) == 'DES-ECB'){
            $iv = '';
        }
        if($iv && strlen($iv) != 16){
            return '';
        }
        $str_padded = $data;
        if(is_array($data)){
            $str_padded = json_encode($data,320);
        }
        if($options == OPENSSL_NO_PADDING){
            if (strlen($str_padded) % 16) {
                $str_padded = str_pad($str_padded,strlen($str_padded) + 16 - strlen($str_padded) % 16, "\0");
            }
        }
        return self::urlSafeBase64encode(openssl_encrypt($str_padded, $method, $secret_key, $options, $iv));
    }

    /**
     * 对称算法：AES DES、3DES - 解密
     * @param string $encrypted bs64字符串
     * @param string $secret_key 必须32位 字母和数组组合
     * @param string $method 加密方法 DES-ECB DES-CBC DES-CTR DES-OFB DES-CFB等等
     * @param string $iv 密初始化向量（可选）必须16位 字母和数组组合
     * @param string $options 数据格式选项（可选）【选项有：】：0, OPENSSL_RAW_DATA=1,OPENSSL_ZERO_PADDING=2,OPENSSL_NO_PADDING=3
     * @return string|array
     */
    public static function decryptAES(string $encrypted, string $secret_key, string $iv = '',string $method = 'aes-256-cbc', $options = OPENSSL_RAW_DATA) : string|array
    {
        if(strlen($secret_key)!=32){
            return '';
        }
        if(strtoupper($method) == 'DES-ECB'){
            $iv = '';
        }
        if($iv && strlen($iv) != 16){
            return '';
        }
        //解密字符串
        $encrypted = self::urlSafeBase64decode($encrypted);
        $result = openssl_decrypt($encrypted, $method, $secret_key, $options, $iv);
        if($options == OPENSSL_NO_PADDING) {
            $result = rtrim(rtrim($result, chr(0)), chr(7));
        }
        $res = json_decode($result, true);
        return $res??$result;
    }

    /**
     * 生成密钥
     * @param $config $config = [
     *                     'config' => '/opt/service/php7.3.9/extras/ssl/openssl.cnf', // 定位至你的openssl.cnf文件
     *                     'digest_alg' => 'SHA512', // openssl_get_md_methods() 的返回值是可以使用的加密方法列表
     *                     'private_key_bits' => 4096,//1024,2048,4096  （不能使用字符型）
     *                ]
     * @param $public_key 'public_key.cer' 此参数如果有值生成文件,无值返回公钥
     * @param $private_key 'private_key.cer' 此参数如果有值生成文件,无值返回私钥
     * @return bool|string
     * +--------------+-------------------------------+--------------------+
    | "alg" Param  | Digital Signature or MAC      | Implementation     |
    | Value        | Algorithm                     | Requirements       |
    +--------------+-------------------------------+--------------------+
    | HS256        | HMAC using SHA-256            | Required           |
    | HS384        | HMAC using SHA-384            | Optional           |
    | HS512        | HMAC using SHA-512            | Optional           |
    | RS256        | RSASSA-PKCS1-v1_5 using       | Recommended        |
    |              | SHA-256                       |                    |
    | RS384        | RSASSA-PKCS1-v1_5 using       | Optional           |
    |              | SHA-384                       |                    |
    | RS512        | RSASSA-PKCS1-v1_5 using       | Optional           |
    |              | SHA-512                       |                    |
    | ES256        | ECDSA using P-256 and SHA-256 | Recommended+       |
    | ES384        | ECDSA using P-384 and SHA-384 | Optional           |
    | ES512        | ECDSA using P-521 and SHA-512 | Optional           |
    | PS256        | RSASSA-PSS using SHA-256 and  | Optional           |
    |              | MGF1 with SHA-256             |                    |
    | PS384        | RSASSA-PSS using SHA-384 and  | Optional           |
    |              | MGF1 with SHA-384             |                    |
    | PS512        | RSASSA-PSS using SHA-512 and  | Optional           |
    |              | MGF1 with SHA-512             |                    |
    | none         | No digital signature or MAC   | Optional           |
    |              | performed                     |                    |
    +--------------+-------------------------------+--------------------+
     */
    public static function generateKey(array $opensslConfig, string $public_key = '', string $private_key = ''): array
    {
        $returnData = [
            'public_key' => '',
            'private_key' => ''
        ];
        $opensslConfig = array_merge([
            'config' => '',
            'digest_alg' => 'SHA512',
            //加密类型
            //'private_key_type'  => OPENSSL_KEYTYPE_RSA,
            //字节数 1024 2048 4096 此处长度与加密的字符串长度有关系
            'private_key_bits' => 1024
        ], $opensslConfig);
        $privateKey = '';
        if ($resource = openssl_pkey_new($opensslConfig)){
            // 生成私钥
            openssl_pkey_export($resource, $privateKey, null, $opensslConfig);
            // 生成公钥
            $details = openssl_pkey_get_details($resource);
            //生成公钥文件
            if ($public_key) {
                self::create_dirs($public_key);
                $fp = fopen($public_key, "w");
                fwrite($fp, $details['key']);
                fclose($fp);
                $returnData['public_key'] = $public_key;
            } else {
                $returnData['public_key'] = $details['key'];
            }
            //生成密钥文件
            if ($private_key) {
                self::create_dirs($private_key);
                $fp = fopen($private_key, "w");
                fwrite($fp, $privateKey);
                fclose($fp);
                $returnData['private_key'] = $private_key;
            } else {
                $returnData['private_key'] = $privateKey;
            }
        };
        return $returnData;
    }

    /**
     * @param  string $string 加密内容
     * @param  string $operation 动作 DECODE表示解密,其它表示加密
     * @param  string $key 密钥
     * @param  int $expiry 有效时间秒 - 前后端时间不一致也不会影响解密
     * @return string 加密串
     */
    public static function authcode(string $string, string $operation = 'DECODE', string $key = '', int $expiry = 0):string
    {
        if($operation == 'DECODE'){
            $string = str_replace(array('-','_'), array('+','/'), $string);
        }
        $ckey_length = 4;
        $key = md5($key);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey = $keya.md5($keya.$keyc);
        $key_length = strlen($cryptkey);

        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
        $string_length = strlen($string);

        $result = '';
        $box = range(0, 255);

        $rndkey = array();
        for($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if($operation == 'DECODE') {
            $eya = (int)substr($result, 0, 10);
            if(!$eya){
                $eya = 0;
            }
            if((substr($result, 0, 10) == 0 || $eya - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc.str_replace(array('+','/','='), array( '-','_',''), base64_encode($result));
        }
    }

    /**
     * 哈希处理封装
     * @param string|array $str
     * @param string $key
     * @param string $algo md5 | sha256
     * @return string
     */
    public static function hash(string|array $str, string $key = '',bool $raw_output = false, string $algo = 'sha256') : string
    {
        $resStr = '';
        if(is_array($str)){
            $str = json_encode($str,320);
        }
        if($key){
            //$raw_output 设置为 TRUE 输出原始二进制数据， 设置为 FALSE 输出小写 16 进制字符串。
            $resStr = hash_hmac($algo, $str, $key, $raw_output);
        }else{
            $resStr = hash($algo, $str, $raw_output);
        }
        if($raw_output){
            $resStr = base64_encode($resStr);
        }
        return $resStr;
    }

    /**
     * 创建目录
     */
    protected static function create_dirs(string $filePath): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}