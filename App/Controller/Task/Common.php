<?php
namespace App\Controller\Task;

use App\Controller\Base;
use App\Server\CacheKey;

class Common extends Base
{
    public function task()
    {
        echo "hello";
    }
    public function user_notice($server,$user_message)
    {
        $forum_uid_list_cacheKey   = CacheKey::FORUM_UID_LIST;
        var_dump($user_message);
        $this->call_uid($server,$user_message['to_uid'],['msg'=>$user_message['msg']],20001,
            function($uid) use($forum_uid_list_cacheKey)
            {
                $this->cache->srem($forum_uid_list_cacheKey,$uid);
            });
    }
}