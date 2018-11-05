<?php

namespace App\Server;

class Config
{
    const WEB_URL = "https://fantuanpu.com";
    const DB = [
        'host' => '103.56.55.156',
        'user' => 'fantuanpu_remote',
        'password' => 'fantuanpu_123',
        'database' => 'fantuanpu_2019',
        'charset' => 'utf8'
    ];
    const SWOOLE_TOKEN = 'OHMYJXY_hahaha';
    public static $extcredits = [
        'extcredits1' => '酸奶',
        'extcredits2' => '扑币',
        'extcredits3' => '项链',
        'extcredits4' => '草莓棉花糖',
        'extcredits5' => '灵魂宝石',
        'extcredits6' => '文点',
        'extcredits7' => '分享积分',
        'extcredits8' => '图点',
    ];
    public static function ext()
    {
        foreach (self::$extcredits as $key => $value)
            $ext[$key] = 0;

        return $ext;
    }
}