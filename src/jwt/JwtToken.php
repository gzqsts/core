<?php

declare(strict_types=1);

namespace Gzqsts\Core\jwt;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;

use Gzqsts\Core\exception\{
    //422
    UnprocessableException,
    //401
    JwtTokenException
};

use Gzqsts\Core\laravelCache\Cache;
use app\model\Device;
use app\model\user\UserToken;

class JwtToken
{
    /**
     * @desc: 获取配置文件
     * @return array
     * @throws JwtTokenException
     */
    private static function getConfig(): array
    {
        $config = config('plugin.gzqsts.core.app.jwt');
        if (empty($config)) {
            throw new UnprocessableException(trans('jwt.notConfig',[], 'messages'));
        }
        return $config;
    }

    /**
     * @desc: 根据签名算法获取【公钥】签名值
     * @param string $algorithm 算法
     * @param int $tokenType 类型
     * @return string
     * @throws JwtTokenException
     */
    private static function getPublicKey(bool $isRefresh = false): string
    {
        $config = self::getConfig();
        switch ($config['algorithms']) {
            case 'HS256':
                $key = $isRefresh ? $config['refresh_secret_key'] : $config['access_secret_key'];
                break;
            case 'RS512':
            case 'RS256':
                $dir = $isRefresh ? $config['refresh_public_key'] : $config['access_public_key'];
                $key = file_get_contents($dir);
                break;
            default:
                $key = $config['access_secret_key'];
        }
        return $key;
    }

    /**
     * @desc: 根据签名算法获取【私钥】签名值
     * @param array $config 配置文件
     * @param int $tokenType 令牌类型
     * @return string
     */
    private static function getPrivateKey(bool $isRefresh = false): string
    {
        $config = self::getConfig();
        switch ($config['algorithms']) {
            case 'HS256':
                $key = $isRefresh ? $config['refresh_secret_key']: $config['access_secret_key'];
                break;
            case 'RS512':
            case 'RS256':
                $dir = $isRefresh ? $config['refresh_private_key']: $config['access_private_key'];
                $key = file_get_contents($dir);
                break;
            default:
                $key = $config['access_secret_key'];
        }
        return $key;
    }

    //获取本次启动设备信息 - 优先缓存 -> 查询SQL - 已初始化过应用基础信息记录 才能正确获取到数据
    public static function getDevices(): array
    {
        $device  = request()->header('qst-device-id','');
        $dev = Cache::get($device, function () use($device){
            $res = (new Device)::where('device_id', $device)->first();
            return !empty($res)? $res->toArray() : [];
        });
        if (empty($dev)) {
            throw new UnprocessableException(trans('jwt.token_Field_not_exist',[], 'messages'));
        }
        return $dev;
    }

    /**
     * @desc: 唯一KEY 生成：MD5 uid + 平台标识
     * @param int $uid
     * @return string
     */
    public static function getkey(int $uid, array $devices): string
    {
        return md5((string)$uid.$devices['platform']);
    }

    /**
     * @desc: 生成令牌.
     *
     * @param array  $payload    载荷信息
     * @param string $secretKey  签名key
     * @param string $algorithms 算法
     * @return string
     */
    private static function makeToken(array $payload, string $secretKey): string
    {
        $config = self::getConfig();
        return JWT::encode($payload, $secretKey, $config['algorithms']);
    }

    /**
     * @desc: 获取加密载体.
     *
     * @param array $config 配置文件
     * @param array $extend 扩展加密字段
     * @return array
     */
    private static function generatePayload(array $extend): array
    {
        $config = self::getConfig();
        $basePayload = [
            'iss' => $config['iss'],
            'iat' => time(),
            'exp' => time() + (int)$config['access_exp'],
            'extend' => $extend
        ];
        $resPayLoad = [];
        $resPayLoad['accessPayload'] = $basePayload;
        $basePayload['exp'] = time() + (int)$config['refresh_exp'];
        $resPayLoad['refreshPayload'] = $basePayload;
        return $resPayLoad;
    }

    //获取客户端 token
    public static function getHeaderToken(): string
    {
        $reqToken  = request()->header('qst-token','');
        if(empty($reqToken)){
            return '';
        }
        if(strlen($reqToken)>32){
            return $reqToken;
        }else{
            //说明$reqToken是token表中的KEY，通过key获取token表数据
            $cdata = self::getUserTokenData($reqToken);
            return !empty($cdata['access_token'])?$cdata['access_token']:'';
        }
    }

    //获取token表数据
    public static function getUserTokenData(string $key): array
    {
        $config = self::getConfig();
        return Cache::get($config['cache_token_pre'] . $key, function () use($key){
            $res = (new UserToken)::where('key', $key)->first();
            return !empty($res)? $res->toArray() : [];
        });
    }

    private static function getUserExt(array $user): array
    {
        $fields = [
            'uid',
            'cloud_id',
            'mobile',
            'email',
            'invite_code',
            'state',
            'roles',
            'role_id',
            'role_time'
        ];
        $newUser = array_intersect_key($user, array_flip($fields));
        //必须存在$fields指定字段数据
        foreach($fields as $val){
            if(!in_array($val, array_keys($newUser))){
                throw new UnprocessableException(trans('jwt.emptyExt',[], 'messages'));
            }
        }
        return $newUser;
    }

    /**
     * @desc: 获令牌有效期剩余时长.
     * @param int $tokenType
     * @return int
     */
    private static function getTokenExp(int $exp): int
    {
        return $exp - time();
    }

    /**
     * @desc: 只负责创建新token
     * @param array $user 用户表数据合并到token中
        设置TOKEN用户基础数据
        "uid": 2,
        "cloud_id": "1368-sdasdasd-54511-552",
        "mobile": "136854511552",
        "email": "sfsd3546234334",
        "invite_code": "sftttttttttsd",
        "state": 1,
        "roles": "21,22,23",
        "role_id": 21,
        "role_time": 0,
     * @return array token表数据
     * @throws UnprocessableException
     */
    public static function creationToken(array $user): array
    {
        if (!isset($user['uid']) || empty($user['uid'])) {
            throw new UnprocessableException(trans('jwt.lackId',[], 'messages'));
        }
        $config = self::getConfig();
        $devices = self::getDevices();
        $key = self::getkey($user['uid'], $devices);
        $ip = request()->getRealIp($safe_mode = true);
        //合并到扩展数据中的部分数据,解析token得到用户表完整字段数据+ 该扩展字段
        $userExts = [
            'key' => $key,
            'platform' => $devices['platform'],
            'device_id' => $devices['device_id'],
            'ip' => $ip
        ];
        //获取数据体 extend字段存放extend原数据
        $payload = self::generatePayload(array_merge(self::getUserExt($user), $userExts));
        //插入token表数据
        $in_user_token = [
            'key' => $key,
            'uid' => $user['uid'],
            'appid' => $devices['appid'],
            'device_id' => $devices['device_id'],
            'push_clientid' => $devices['push_clientid'],
            'device_type' => $devices['device_type'],
            'device_brand' => $devices['device_brand'],
            'device_model' => $devices['device_model'],
            'os_name' => $devices['os_name'],
            'os_version' => $devices['os_version'],
            'os_language' => $devices['os_language'],
            'rom_name' => $devices['rom_name'],
            'rom_version' => $devices['rom_version'],
            'platform' => $devices['platform'],
            'app_language' => $devices['app_language'],
            'ua' => $devices['ua'],
            'access_exptime' => $payload['accessPayload']['exp'],
            'access_token' => self::makeToken($payload['accessPayload'], self::getPrivateKey()),
            'refresh_exptime' => $payload['refreshPayload']['exp'],
            'refresh_token' => self::makeToken($payload['refreshPayload'], self::getPrivateKey(true)),
            'last_login_ip' => $ip,
            'last_login_date' => time()
        ];
        $UserToken = new UserToken;
        //通过平台类型 +UID 查询TOKEN表是否已存在数据 无需查询缓存
        $res = $UserToken::where('platform', $devices['platform'])
            ->where('uid', $user['uid'])
            ->first();
        //如果存在数据 - 数据
        if(!empty($res) && $res->key){
            //token被更新事件
            \Webman\Event\Event::emit('user.updataToken', $in_user_token);
            $UserToken::where('key', $res->key)->update($in_user_token);
        }else{
            $UserToken::create($in_user_token);
        }
        //缓存TOKEN表数据 - 永久
        Cache::forever($config['cache_token_pre'] . $key, $in_user_token);
        return $in_user_token;
    }

    /**
     * @desc: 刷新token 返回用户数据
     * @param string $refreshToken
     * @return array token表数据
     *
    [key] => e68758ad043da8bb938225a12f64d4a6
    [uid] => 5
    [appid] => __UNI__37775A7
    [device_id] => 16882041878022102502
    [push_clientid] => 1793ed1ea54accaf9831fa0aeb541541
    [device_type] => pc
    [device_brand] =>
    [device_model] => PC
    [os_name] => windows
    [os_version] => 10 x64
    [os_language] =>
    [rom_name] =>
    [rom_version] =>
    [platform] => web
    [app_language] => zh-Hans
    [ua] => Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36
    [access_exptime] => 1688302930
    [access_token] =>
    [refresh_exptime] => 1695992530
    [refresh_token] =>
    [last_login_ip] => 192.168.1.106
    [last_login_date] => 1688216530
    [updated_at] => 2023-07-01 17:38:30
    [created_at] => 2023-07-01 17:38:30
     */
    public static function refreshToken(string $refreshToken, array $usersExp = []): array
    {
        if(empty($refreshToken) || 'undefined' == $refreshToken){
            //刷新令牌无效
            throw new UnprocessableException(trans('jwt.token_invalid',[], 'messages'));
        }
        $config = self::getConfig();
        JWT::$leeway = $config['leeway'];
        try {
            $decoded = JWT::decode($refreshToken, new Key(self::getPublicKey(true), $config['algorithms']));
        } catch (SignatureInvalidException $signatureInvalidException) {
            //身份验证令牌无效
            throw new JwtTokenException(trans('jwt.token_invalid',[], 'messages'), ['dataType' => 'login']);
        } catch (BeforeValidException $beforeValidException) {
            //身份验证令牌尚未生效
            throw new UnprocessableException(trans('jwt.token_check_invalid',[], 'messages'));
        } catch (ExpiredException $expiredException) {
            //身份验证会话已过期，请重新登录
            throw new JwtTokenException(trans('jwt.token_to_login',[], 'messages'), ['dataType' => 'login']);
        } catch (\Exception $exception) {
            throw new UnprocessableException($exception->getMessage());
        }
        /*(
            [iss] => www.gzqsts.com
            [iat] => 1686548577
            [exp] => 1686634977
            [extend] => Array(
                [uid] => 1
                [key] => 5ce5bf12ee2b05f5f450f159a8398a07
                [platform] => web
                [device_id] => 1684995458543188923
                [ip] => 192.168.1.106
                ...user扩展数据
            )
        )*/
        $decoded = json_decode(json_encode($decoded), true);
        if(empty($decoded['extend'])){
            throw new UnprocessableException(trans('jwt.token_invalid',[], 'messages'));
        }
        $user = $decoded['extend'];
        if(!empty($usersExp)){
            //更新用户最新的扩展数据
            $user = array_merge($user, $usersExp);
        }
        //直接创建新token
        $ip = request()->getRealIp($safe_mode = true);
        //合并到扩展数据中的部分数据,解析token得到用户表完整字段数据+ 该扩展字段
        $userExts = [
            'key' => $user['key'],
            'platform' => $user['platform'],
            'device_id' => $user['device_id'],
            'ip' => $ip
        ];
        //获取数据体 extend字段存放extend原数据
        $payload = self::generatePayload(array_merge(self::getUserExt($user), $userExts));
        $upData = [
            'access_exptime' => $payload['accessPayload']['exp'],
            'access_token' => self::makeToken($payload['accessPayload'], self::getPrivateKey()),
            'refresh_exptime' => $payload['refreshPayload']['exp'],
            'refresh_token' => self::makeToken($payload['refreshPayload'], self::getPrivateKey(true)),
            'last_login_ip' => $ip,
            'last_login_date' => time()
        ];
        $UserToken = new UserToken;
        //通过设备ID + 平台类型 查询 TOKEN表是否已存在数据 无需查询缓存
        $res = $UserToken::where('key', $user['key'])->first();
        //如果存在数据 - 数据
        if(!empty($res) && $res->key){
            $UserToken::where('key', $user['key'])->update($upData);
            //更新缓存
            $upData = array_merge($res->toArray(), $upData);
            //token被更新事件
            \Webman\Event\Event::emit('user.updataToken', $upData);
            //缓存TOKEN表数据 - 永久
            Cache::forever($config['cache_token_pre'] . $user['key'], $upData);
        }else{
            throw new UnprocessableException(trans('jwt.token_updata',[], 'messages'));
        }
        return $upData;
    }

    //认证token（频繁操作） 返回用户数据 - 更新最后登录时间、最后访问IP - 需查询限制8小时只更新一次
    /*返回{
         "uid": 2,
         "cloud_id": "1368-sdasdasd-54511-552",
         "mobile": "136854511552",
         "email": "sfsd3546234334",
         "invite_code": "sftttttttttsd",
         "state": 1,
         "roles": "21,22,23",
         "role_id": 21,
         "role_time": 0,
         "key": "bec5e4d6ba2781c234609a8e182f6d7a",
         "platform": "web",
         "device_id": "16866417572792196384",
         "ip": "192.168.1.106",
         "token": ""
     }*/
    public static function verifyToken(string $token = '', bool $isLoginTip = true): array
    {
        $config = self::getConfig();
        //时钟偏差冗余时间，单位秒。建议这个余地应该不大于几分钟。
        JWT::$leeway = $config['leeway'];
        $decoded = [];
        try {
            if(empty($token)){
                $token = self::getHeaderToken();
            }else{
                if(strlen($token)<=32){
                    //说明$reqToken是token表中的KEY，通过key获取token表数据
                    $cdata = self::getUserTokenData($token);
                    $token = !empty($cdata['access_token'])?$cdata['access_token']:'';
                }
            }
            //algorithms 算法类型
            $decoded = JWT::decode($token, new Key(self::getPublicKey(), $config['algorithms']));
        } catch (SignatureInvalidException $signatureInvalidException) {
            //身份验证令牌无效
            if($isLoginTip){
                throw new JwtTokenException(trans('jwt.token_invalid',[], 'messages'), ['dataType' => 'login']);
            }
        } catch (BeforeValidException $beforeValidException) {
            //身份验证令牌尚未生效
            throw new UnprocessableException(trans('jwt.token_check_invalid',[], 'messages'));
        } catch (ExpiredException $expiredException) {
            //身份验证会话已过期，请重新登录
            if($isLoginTip){
                throw new JwtTokenException(trans('jwt.token_to_login',[], 'messages'), ['dataType' => 'login']);
            }
        } catch (\Exception $exception) {
            throw new UnprocessableException($exception->getMessage()??'Token error');
        }

        $decoded = json_decode(json_encode($decoded), true);
        if(empty($decoded['extend'])){
            throw new UnprocessableException(trans('jwt.token_invalid',[], 'messages'));
        }
        $decoded['extend']['token'] = $token;
        if ($config['is_single_device'] && $decoded['extend']['ip'] != request()->getRealIp($safe_mode = true) && $isLoginTip) {
            //验证IP是否一致不一致 已在别的地方登录强制下线
            throw new JwtTokenException(trans('jwt.token_log_off',[], 'messages'), ['dataType' => 'quitLogin']);
        }

        if(empty($decoded)){
            //toekn 错误 - 客户端执行去登录
            throw new JwtTokenException(trans('jwt.token_empty',[], 'messages'), ['dataType' => 'login']);
        }

        $cdata = self::getUserTokenData($decoded['extend']['key']);
        //token过期自动刷新token 过期时间剩10分钟进行刷新新token
        if(self::getTokenExp((int)$decoded['exp']) < 600){
            if(!empty($cdata) && self::getTokenExp((int)$cdata['refresh_exptime']) >0){
                $res = self::refreshToken($cdata['refresh_token']);
                //增加新token下发客户端 $res['access_token']
                if(!empty($res['key'])){
                    //缓存新token - 提供给中间件拦截返回客户端
                    Cache::forever('app_new_token_'.$res['uid'], $res['key']);
                    $decoded['extend']['key'] = $res['key'];
                    $decoded['extend']['token'] = $res['access_token'];
                }
            }
        }

        //更新最后登录时间、最后访问IP - 需查询限制1小时更新一次
        if(!empty($cdata) && ($cdata['last_login_date'] + 3600) < time()){
            $upData = [
                'last_login_ip' => request()->getRealIp($safe_mode = true),
                'last_login_date' => time()
            ];
            (new UserToken)::where('key', $cdata['key'])->update($upData);
            //更新缓存
            Cache::forever($config['cache_token_pre'] . $cdata['key'], array_merge($cdata, $upData));
        }
        return $decoded['extend'];
    }

    //删除token 通过key或token解析得到KEY删除
    public static function clearByKey(string $key): void
    {
        $config = self::getConfig();
        (new UserToken)::where('key', $key)->delete();
        Cache::forget($config['cache_token_pre'] . $key);
    }

}
