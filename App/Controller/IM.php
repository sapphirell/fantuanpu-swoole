<?php
namespace App\Controller;

use App\Server\BaseModel;
use App\Server\Cache;
use App\Server\CacheKey;

class IM extends Base
{
    public $cache;
    public $model;
    public function __construct(Cache $cache)
    {
        parent::__construct($cache);

    }

    public function saveConnect($server,$frame,$user_json)
    {
//        $this->cache->set('oop','ok');
//        $frame->
    }

    /**
     * 身份验证
     * @param $server
     * @param $frame
     * @param $userMessage
     */
    public function identify($server,$frame,$userMessage)
    {
        $user_id = $userMessage['user_id'];
        $fd_cacheKey            = CacheKey::FD_USER . $frame->fd;
        $uid_cacheKey           = CacheKey::USER_FD . $user_id;
        $im_uid_list_cacheKey   = CacheKey::IM_UID_LIST;
        //存储uid映射$fd一对多关系
        $this->cache->sadd($uid_cacheKey,$frame->fd);
        //存储$fd映射的用户信息
        $this->cache->hmset($fd_cacheKey,[
            'user_id'   => $user_id,
            'user_name' => $userMessage['user_name'],
        ]);
        //聊天室人数+1
        $this->cache->sadd($im_uid_list_cacheKey,$user_id);
        //通知人数+1
        $fetch_uid_list = (array) $this->cache->sMembers($im_uid_list_cacheKey);

        foreach ($fetch_uid_list as $value)
        {
            //通知所有用户,连接数改变
            $this->call_uid($server,$value,['talking_num' => count($fetch_uid_list)],10001,
                function($uid) use($im_uid_list_cacheKey){
                    $this->cache->srem($im_uid_list_cacheKey,$uid);
                });
            //通知所有用户,有人来到了聊天室
            $this->call_uid($server,$value,[
                'msg' => $userMessage['user_name'] .'来到了聊天室~',
                'username' => '系统消息',
                'date' => date("m-d H:i:s"),
            ],10002,
                function($uid) use($im_uid_list_cacheKey){
                    $this->cache->srem($im_uid_list_cacheKey,$uid);
                });
        }

        return true;
    }
    public function talking($server,$frame,$userMessage)
    {
        //根据fd寻找user_info
        $fd_cacheKey            = CacheKey::FD_USER . $frame->fd;
        $im_uid_list_cacheKey   = CacheKey::IM_UID_LIST;
        $user_info = $this->cache->hmget($fd_cacheKey,['user_id','user_name']);
        //存储消息 -_- model不完善,只能硬拼sql了

        $now_date = date("Y-m-d H:i:s");
        //记录消息
        BaseModel::$db->query("INSERT INTO `pre_web_im` SET
                            `username` = '{$user_info['user_name']}' ,
                            `postdate` = '{$now_date}' ,
                            `message`  = '{$userMessage['msg']}',
                            `uid`      = '{$user_info['user_id']}',
                            `avatar`   = '{$userMessage['avatar']}'
                            ",function ($e) {
                                var_dump($e);
//                                if ($e->connect_errno)
//                                    var_dump($e);
                            });
        //通知所有fd
        $fetch_uid_list = (array) $this->cache->sMembers($im_uid_list_cacheKey);
        foreach ($fetch_uid_list as $value)
        {
            $this->call_uid($server,$value,[
                'msg'       => $userMessage['msg'],
                'username'  => $user_info['user_name'],
                'user_id'   => $user_info['user_id'],
                'date'      => date("m-d H:i:s"),
                'avatar'    => $userMessage['avatar'],
            ],10002,
            function($uid) use($im_uid_list_cacheKey)
            {
                $this->cache->srem($im_uid_list_cacheKey,$uid);
            });
        }
        return true;
    }
}