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
 * @Time: 2022/11/14 23:31
 * @Notes: 常用操作工具
 */

namespace Gzqsts\Core;

class Util
{
    /**
     * 据传入的经纬度，和距离范围，返回所在距离范围内的经纬度的取值范围
     * @param $lng
     * @param $lat
     * @param float $distance 单位：km
     * @return array
     */
    public static function locationRange($lng, $lat, $distance = 2): array
    {
        $earthRadius = 6378.137;//单位km
        $d_lng = rad2deg(2 * asin(sin($distance / (2 * $earthRadius)) / cos(deg2rad($lat))));
        $d_lat = rad2deg($distance / $earthRadius);
        return array(
            'lat_start' => round($lat - $d_lat, 7),//纬度开始
            'lat_end' => round($lat + $d_lat, 7),//纬度结束
            'lng_start' => round($lng - $d_lng, 7),//纬度开始
            'lng_end' => round($lng + $d_lng, 7)//纬度结束
        );
    }

    /**
     * 根据经纬度返回距离
     * @param $lng1 经度
     * @param $lat1 纬度
     * @param $lng2 经度
     * @param $lat2 纬度
     * @return float 距离：m
     */
    public static function getDistance($lng1, $lat1, $lng2, $lat2): float
    {
        $radLat1 = deg2rad($lat1);//deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6370996;
        return round($s, 0);
    }

    /**
     *  根据经纬度返回距离
     * @param $lng1 经度
     * @param $lat1 纬度
     * @param $lng2 经度
     * @param $lat2 纬度
     * @return string 距离：km,m
     */
    public static function distance($lng1, $lat1, $lng2, $lat2): string
    {
        $m = self::getDistance($lng1, $lat1, $lng2, $lat2);
        if ($m > 1000) {
            return round($m / 1000, 1) . 'km';
        } else {
            return $m . 'm';
        }
    }

    /**
     * 生成二维码 （建议前端生成）
     * @param string $text 内容
     * @param $filename 文件名
     * @param string $level 等级3 L M Q H
     * @param int $size 大小
     * @param int $margin 边框
     * @param bool $saveAndPrint 保存并打印
     * @return void
     */
    public static function qrcode(string $text, $filename = false, string $level = 'L', int $size = 4, int $margin = 1, bool $saveAndPrint = false)
    {
        return \Gzqsts\Core\tool\QRcode::png($text, $filename, $level, $size, $margin, $saveAndPrint);
    }

    /**
     * 生成base64二维码 （建议前端生成）
     * @param string $text 内容
     * @param string $level 等级3 L M Q H
     * @param int $size 大小
     * @param int $margin 边框
     */
    public static function base64Qrcode(string $text, string $level = 'L', int $size = 4, int $margin = 1): string
    {
        ob_start();
        self::qrcode($text, false, $level, $size, $margin);
        //得到当前缓冲区的内容并删除当前输出缓冲区
        $img = ob_get_clean();
        //转base64清除base64中的换行符
        return 'data:image/png;base64,' . str_replace(["\r\n", "\r", "\n"], '', chunk_split(base64_encode($img)));
    }

    /**
     * 递归无限级分类
     * @param $data
     * @param int $value 父id初始值
     * @param string $child 子分组
     * @param string $pid 父字段
     * @param string $id 子字段
     * @return array
     */
    public static function recursion($data, int $value = 0, string $child = 'child', string $pid = 'pid', string $id = 'id'): array
    {
        $arr = [];
        foreach ($data as $key => $val) {
            if ($val[$pid] == $value) {
                unset($data[$key]);
                $val[$child] = self::recursion($data, $val[$id], $child, $pid, $id);
                $arr[] = $val;
            }
        }
        return $arr;
    }

    /**
     * 进制转换
     * @param mixed $num 需要转换的值
     * @param int $current 当前进制
     * @param int $result 需要转成的进制（最大支持62）
     * @return bool|int|string
     */
    public static function convert($num, int $current = 10, int $result = 32)
    {
        if ($current > 62 || $result > 62) return false;
        if ($current > 32 || $result > 32) {
            $dict = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            if ($current > $result) {
                // 62进制数转换成十进制数
                $num = strval($num);
                $len = strlen($num);
                $dec = 0;
                for ($i = 0; $i < $len; $i++) {
                    $pos = strpos($dict, $num[$i]);
                    $dec = bcadd(bcmul(bcpow($current, $len - $i - 1), $pos), $dec);
                }
                return $dec;
            } else {
                //十进制数转换成62进制
                $ret = '';
                do {
                    $ret = $dict[bcmod($num, $result)] . $ret;
                    $num = bcdiv($num, $result);
                } while ($num > 0);
                return $ret;
            }
        }
        return base_convert($num, $current, $result);
    }
}
