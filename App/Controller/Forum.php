<?php
namespace App\Controller;

use App\Server\Cache;
use App\Server\CacheKey;

class Forum extends Base
{
    public function identify($server,$frame,$userMessage)
    {
        $fd_cacheKey            = CacheKey::FD_USER . $frame->fd;
        $uid_cacheKey           = CacheKey::USER_FD . $userMessage['user_id'];
        //存储uid映射$fd一对多关系
        $this->cache->sadd($uid_cacheKey,$frame->fd);
        //存储$fd映射的用户信息
        $this->cache->hmset($fd_cacheKey,[
            'user_id'   => $userMessage['user_id'],
            'user_name' => $userMessage['user_name'],
        ]);
    }

    /**
     * 获取论坛内当前所有保持websocket链接的用户
     */
    public function get_all_user()
    {

    }
}