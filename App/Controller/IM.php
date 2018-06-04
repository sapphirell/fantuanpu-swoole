<?php
namespace App\Controller;

use App\Server\Cache;
use App\Server\CacheKey;

class IM extends Base
{
    public $cache;


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
        $fd_cacheKey            = CacheKey::FD_USER . $frame->fd;
        $uid_cacheKey           = CacheKey::USER_FD . $userMessage['user_id'];
        $im_user_num_cacheKey   = CacheKey::IM_USER_NUM;
        $im_uid_list_cacheKey   = CacheKey::IM_UID_LIST;
        //存储uid映射$fd一对多关系
        $this->cache->sadd($uid_cacheKey,$frame->fd);
        //存储$fd映射的用户信息
        $this->cache->hmset($fd_cacheKey,[
            'user_id'   => $userMessage['user_id'],
            'user_name' => $userMessage['user_name'],
        ]);
        //聊天室人数+1
        $num = $this->cache->incr($im_user_num_cacheKey);
        $this->cache->sadd($im_uid_list_cacheKey,$userMessage['user_id']);
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
        $user_info = $this->cache->hmget($fd_cacheKey,['uid','user_name']);

        //通知所有fd
        $fetch_uid_list = (array) $this->cache->sMembers($im_uid_list_cacheKey);
        foreach ($fetch_uid_list as $value)
        {
            $this->call_uid($server,$value,[
                'msg' => $userMessage['msg'],
                'username' => $user_info['user_name'],
                'date' => date("m-d H:i:s"),
            ],10002,
                function($uid) use($im_uid_list_cacheKey)
                {
                    $this->cache->srem($im_uid_list_cacheKey,$uid);
                });
        }
        return true;
    }
}