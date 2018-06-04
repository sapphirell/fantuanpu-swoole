<?php
namespace App\Server;

class CacheKey
{
    /**
     * 根据user获取fd,类型set
     */
    const USER_FD = 'user_fd:' ;
    //根据fd获取user_info,类型 hash
    const FD_USER = 'fd_user:' ;
    const IM_USER_NUM = 'im_user_num';
    const IM_UID_LIST = 'im_uid_list';
    const FORUM_UID_LIST = 'forum_uid_list';

}