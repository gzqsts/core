<?php

declare(strict_types=1);

namespace Gzqsts\Core\Jwt;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Gzqsts\Core\Exception\{
    JwtTokenException,
    JwtLoginTokenException,
    JwtQuitLoginTokenException
};
use UnexpectedValueException;
use Gzqsts\Core\LaravelCache\Cache;
use support\Db;

class JwtToken
{
    /**
     * access_token.
     */
    private const ACCESS_TOKEN = 1;

    /**
     * refresh_token.
     */
    private const REFRESH_TOKEN = 2;

    protected static $payload = [];

    /**
     * @desc: 生成新令牌
     * @param array $extend 合并到token中的数据
     * @return array token表数据
     * @throws JwtTokenException
     */
    public static function generateToken(array $extend): array
    {
        if (!isset($extend['uid'])) {
            throw new JwtTokenException(trans('jwt.lackId',[], 'qstapp_msg'));
        }
        $config = self::_getConfig();
        $ip = request()->getRealIp($safe_mode = true);
        //token有效时间
        $config['access_exp'] = $extend['access_exp'] ?? $config['access_exp'];
        //刷新token有效时间
        $config['refresh_exp'] = $extend['refresh_exp'] ?? $config['refresh_exp'];

        $key = self::getkey($extend['uid']);
        $res = Cache::rememberForever($config['cache_token_pre'] . $key, function () use ($key){
            return (array)Db::table('user_token')->where('key', $key)->first();
        });
        //创建token时 判断token是否过期 没有过期的情况下 直接返回缓存数据
        if($res && time() < ($res['expires_in'] - $config['leeway'])){
            return $res;
        }

        $devices = QTgetDevices();
        $exts = [
            'key' => $key,
            'platform' => $devices['uniPlatform'],
            'os_name' => $devices['osName'],
            'device_model' => $devices['deviceModel'],
            'cid' => request()->header('x-qst-cid',''),
            'ip' => $ip
        ];
        //获取数据体 extend字段存放extend原数据
        $payload = self::generatePayload($config, array_merge($extend, $exts));
        $tokens = [
            'uid' => $extend['uid'],
            //token有效时间
            'expires_in' => $payload['accessPayload']['exp'],
            //非对称加密算法 私钥加密
            //RS256 系列是使用 RSA 私钥进行签名，使用 RSA 公钥进行验证。
            //公钥即使泄漏也毫无影响，只要确保私钥安全就行。RS256 可以将验证委托给其他应用，只要将公钥给他们就行。
            'access_token' => self::makeToken($payload['accessPayload'], self::getPrivateKey($config), $config['algorithms']),
            'refresh_token' => self::makeToken($payload['refreshPayload'], self::getPrivateKey($config, self::REFRESH_TOKEN), $config['algorithms']),
            'created_at' => date('Y-m-d H:i:s')
        ];
        unset($exts['key']);
        return self::upDataToken(array_merge($exts, $tokens), $key);
    }

    /**
     * @desc: 刷新令牌
     * @param string $refreshToken
     * @return array token表数据
     */
    public static function refreshToken(string $refreshToken, array $upExtend = []): array
    {
        if(empty($refreshToken) || 'undefined' == $refreshToken){
            //刷新令牌无效
            throw new JwtTokenException(trans('jwt.token_invalid',[], 'qstapp_msg'));
        }
        $config = self::_getConfig();
        //验证刷新token是否有效 - 返回刷新toekn数据
        $refreshData = self::verifyToken($refreshToken, self::REFRESH_TOKEN);
        //原扩展信息
        $extend = $refreshData['extend'];
        if (empty($extend['uid']) || empty($extend['key'])) {
            throw new JwtTokenException(trans('jwt.lackId',[], 'qstapp_msg'));
        }

        $request = request();
        $devices = QTgetDevices();
        //汇入新的扩展字段数据
        if(!empty($upExtend)){
            $extend = array_merge($extend, $upExtend);
        }
        if(empty($extend['platform'])){
            $extend['platform'] = $devices['uniPlatform'];
        }
        if(empty($extend['os_name'])){
            $extend['os_name'] = $devices['osName'];
        }
        if(empty($extend['device_model'])){
            $extend['device_model'] = $devices['deviceModel'];
        }
        $extend['cid'] = $request->header('x-qst-cid',$extend['cid']);
        $extend['ip'] = $request->getRealIp($safe_mode = true);
        $payload = self::generatePayload($config, $extend);
        self::$payload = $payload;

        //创建token新令牌
        $new_access_token = self::makeToken($payload['accessPayload'], self::getPrivateKey($config), $config['algorithms']);
        //创建刷新token新令牌
        $new_refresh_token = self::makeToken($payload['refreshPayload'], self::getPrivateKey($config, self::REFRESH_TOKEN), $config['algorithms']);
        //插入数据表扩展信息
        $tokens = [
            'platform' => $extend['platform'],
            'os_name' => $extend['os_name'],
            'device_model' => $extend['device_model'],
            'cid' => $extend['cid'],
            'ip' => $extend['ip'],
            'uid' => $extend['uid'],
            'expires_in' => $payload['accessPayload']['exp'],
            'access_token' => $new_access_token,
            'refresh_token' => $new_refresh_token,
            'created_at' => date('Y-m-d H:i:s')
        ];
        return self::upDataToken($tokens, $extend['key']);
    }

    /**
     * @desc: 验证令牌
     * @param int $tokenType 1验证token 2验证刷新token
     * @param string|null $token
     * @return array
     * 返回 [
        exp
        extend =>[]
        iat
        iss
        token
    ]
     */
    public static function verify(int $tokenType = self::ACCESS_TOKEN, string $token = ''): array
    {
		if(empty($token) && $tokenType != 2){
		    $token = request()->header('x-qst-token');
		}
        if(empty($token) || 'undefined' == $token){
            //刷新令牌无效
            throw new JwtTokenException(trans('jwt.token_invalid',[], 'qstapp_msg'));
        }
        return self::verifyToken($token, $tokenType);
    }

    /**
     * @desc: 注销令牌 全部用户或指定用户某端数据
     * @param string $key
     * @return bool
     */
    public static function clear(string $key = ''): bool
    {
        $key = $key??false;
        $config = self::_getConfig();
        Db::table('user_token')
            ->when($key, function ($query, $key) {
                return $query->where('key', $key);
            })
            ->sharedLock()
            ->orderBy('uid')
            ->lazy()
            ->each(function ($val) use ($key, $config){
                Db::table('user_token')
                    ->when($key, function ($query, $key) {
                        return $query->where('key', $key);
                    })
                    ->delete();
                Cache::forget($config['cache_token_pre'] . $val->key);
            });
        return true;
    }

    /**
     * @desc: 校验令牌
     * @param string $token
     * @param int $tokenType
     * @return array
     * 返回 [
        exp
        extend =>[]
        iat
        iss
        token
    ]
     */
    private static function verifyToken(string $token, int $tokenType): array
    {
        $config = self::_getConfig();
        $publicKey = self::ACCESS_TOKEN == $tokenType ? self::getPublicKey($config['algorithms']) : self::getPublicKey($config['algorithms'], self::REFRESH_TOKEN);
        JWT::$leeway = $config['leeway'];
        try {
            $decoded = JWT::decode($token, new Key($publicKey, $config['algorithms']));
        } catch (SignatureInvalidException $signatureInvalidException) {
            //身份验证令牌无效
            throw new JwtTokenException(trans('jwt.token_invalid',[], 'qstapp_msg'));
        } catch (BeforeValidException $beforeValidException) {
            //身份验证令牌尚未生效
            throw new JwtTokenException(trans('jwt.token_check_invalid',[], 'qstapp_msg'));
        } catch (ExpiredException $expiredException) {
            $isTip = true;
            //当验证token时 如果token过期自动刷新token
            if($tokenType == 1){
                //通过token获取刷新token
                $resObj = Db::table('user_token')->where('md5token',md5($token))->first();
                //判断刷新token时间是否过期没有过期执行刷新token - 必须同一个用户设备
                if($resObj && $resObj->uid && $resObj->key == self::getkey($resObj->uid)){
                    $res = self::refreshToken($resObj->refresh_token);
                    if(!empty($res['access_token'])){
                        $isTip = false;
                        //缓存新token - 提供给中间件拦截返回客户端
                        Cache::forever('app_new_token_'.request()->header('x-qst-appid'), $res['access_token']);
                        self::$payload['accessPayload']['token'] = $res['access_token'];
                        return (array)self::$payload['accessPayload'];
                    }
                }
            }
            if($isTip){
                //身份验证会话已过期，请重新登录！
                throw new JwtLoginTokenException(trans('jwt.token_to_login',[], 'qstapp_msg'));
            }
        } catch (UnexpectedValueException $unexpectedValueException) {
            //获取的扩展字段不存在
            throw new JwtTokenException(trans('jwt.token_Field_not_exist',[], 'qstapp_msg'));
        } catch (\Exception $exception) {
            throw new JwtTokenException($exception->getMessage());
        }
        $decoded = json_decode(json_encode($decoded), true);
        if ($config['is_single_device'] && $decoded['extend']['ip'] != request()->getRealIp()) {
            //验证IP是否一致不一致 已在别的地方登录强制下线
            throw new JwtQuitLoginTokenException(trans('jwt.token_log_off',[], 'qstapp_msg'));
        }
        $decoded['token'] = $token;
        return $decoded;
    }

    /**
     * @desc: 更新token数据缓存
     * @param array $newToekns
     * @param string $key
     * @return array
     */
    private static function upDataToken(array $newToekns, string $key): array
    {
        if(empty($newToekns['uid']) || empty($key)) return [];
        $config = self::_getConfig();
        //增加MD5token值用来方便查询数据
        $newToekns['md5token'] = md5($newToekns['access_token']);
        Db::table('user_token')
            ->updateOrInsert(
                ['key' => $key],
                $newToekns
            );
        $newToekns['key'] = $key;
        Cache::forever($config['cache_token_pre'] . $key, $newToekns);
        return $newToekns;
    }

    /**
     * @desc: 获取当前登录UID
     * @throws JwtTokenException
     * @return mixed
     */
    public static function getCurrentUid()
    {
        return self::getExtendVal('uid') ?? 0;
    }

    /**
     * @desc: 获取指定令牌扩展内容字段的值
     *
     * @param string $val
     * @return mixed|string
     * @throws JwtTokenException
     */
    public static function getExtendVal(string $val)
    {
        return self::getTokenExtend()[$val] ?? '';
    }

    /**
     * @desc: 获取扩展字段.
     * @return array
     * @throws JwtTokenException
     */
    public static function getTokenExtend(): array
    {
        return (array) self::verify()['extend'];
    }

    /**
     * @desc: 获令牌有效期剩余时长.
     * @param int $tokenType
     * @return int
     */
    public static function getTokenExp(int $tokenType = self::ACCESS_TOKEN): int
    {
        return (int) self::verify($tokenType)['exp'] - time();
    }

    /**
     * @desc: 生成令牌.
     *
     * @param array  $payload    载荷信息
     * @param string $secretKey  签名key
     * @param string $algorithms 算法
     * @return string
     */
    private static function makeToken(array $payload, string $secretKey, string $algorithms): string
    {
        return JWT::encode($payload, $secretKey, $algorithms);
    }

    /**
     * @desc: 获取加密载体.
     *
     * @param array $config 配置文件
     * @param array $extend 扩展加密字段
     * @return array
     */
    private static function generatePayload(array $config, array $extend): array
    {
        $basePayload = [
            'iss' => $config['iss'],
            'iat' => time(),
            'exp' => time() + $config['access_exp'],
            'extend' => $extend
        ];
        $resPayLoad = [];
        $resPayLoad['accessPayload'] = $basePayload;
        $basePayload['exp'] = time() + $config['refresh_exp'];
        $resPayLoad['refreshPayload'] = $basePayload;
        return $resPayLoad;
    }

    /**
     * @desc: 根据签名算法获取【公钥】签名值
     * @param string $algorithm 算法
     * @param int $tokenType 类型
     * @return string
     * @throws JwtTokenException
     */
    private static function getPublicKey(string $algorithm, int $tokenType = self::ACCESS_TOKEN): string
    {
        $config = self::_getConfig();
        switch ($algorithm) {
            case 'HS256':
                $key = self::ACCESS_TOKEN == $tokenType ? $config['access_secret_key'] : $config['refresh_secret_key'];
                break;
            case 'RS512':
            case 'RS256':
                $key = self::ACCESS_TOKEN == $tokenType ? $config['access_public_key'] : $config['refresh_public_key'];
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
    private static function getPrivateKey(array $config, int $tokenType = self::ACCESS_TOKEN): string
    {
        switch ($config['algorithms']) {
            case 'HS256':
                $key = self::ACCESS_TOKEN == $tokenType ? $config['access_secret_key'] : $config['refresh_secret_key'];
                break;
            case 'RS512':
            case 'RS256':
                $key = self::ACCESS_TOKEN == $tokenType ? $config['access_private_key'] : $config['refresh_private_key'];
                break;
            default:
                $key = $config['access_secret_key'];
        }
        return $key;
    }

    /**
     * @desc: 获取配置文件
     * @return array
     * @throws JwtTokenException
     */
    private static function _getConfig(): array
    {
        $config = config('plugin.gzqsts.qstapp.app.jwt');
        if (empty($config)) {
            throw new JwtTokenException(trans('jwt.notConfig',[], 'qstapp_msg'));
        }
        return $config;
    }

    /**
     * @desc: tokenMd5用于索引 增删改查 生成：uid +各平台标识 +操作系统名称 +设备品牌名称
     * @param int $uid
     * @return string
     */
    public static function getkey(int $uid): string
    {
        $devices = QTgetDevices();
        return md5($uid.$devices['uniPlatform'].$devices['osName'].$devices['deviceModel']);
    }
}
