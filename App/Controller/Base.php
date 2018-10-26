<?php
namespace App\Controller;

use App\Server\BaseModel;
use App\Server\Cache;
use App\Server\CacheKey;

class Base
{
    public $cache;
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }
    public function shell_notice($msg)
    {
        echo $msg . "\r\n";
    }
    /**
     * @param          $server
     * @param int      $fd
     * @param          $data
     * @param int      $code
     *        [
     *        200=>普通提示,10001=>IM房间人数改变,10002=>IM房间内有人发消息
     * 20001 =>论坛帖子回复提醒
     *
     *
     *
     * ]
     * @param \Closure $err_fn
     * @返回 bool
     */
    public function call_fd($server, int $fd, $data, $code=200, \Closure $err_fn) : bool
    {
        $msg = json_encode(['data' => $data,'code'=>$code],true);
        $ret = (bool) @ $server->push($fd,$msg);
        if (!$ret)
            $err_fn($fd);
        $this->shell_notice("发送消息{$msg}给{$fd}结果".var_export($ret,true));
        return $ret;
    }
    /**
     * @param          $server
     * @param          $uid
     * @param array    $data
     * @param int      $code
     * @param \Closure $err_fn 如果uid下不存在fd句柄,则执行err_fn,通常这个闭包作用为删除缓存,
     *                         但因不同业务逻辑下(IM聊天室和论坛)uid不同,所以以闭包作为参数
     * @返回 bool
     */
    public function call_uid($server,$uid,$data =[],$code=200,\Closure $err_fn)
    {
        if (!$server || !$uid)
        {
            return false;
        }
        $cacheKey = CacheKey::USER_FD .$uid;
        $userFd = $this->cache->smembers($cacheKey);
        //如果该uid下没有fd链接了,则执行err_fn进行清理uid操作
        if (!$userFd)
            return $err_fn($uid);

        foreach ($userFd as $fd)
        {
            $this->call_fd($server,$fd,$data,$code,function ($fd) use ($cacheKey)
            {
                //发送消息失败,清理链接
                $this->cache->del(CacheKey::FD_USER . $fd);
                $this->cache->srem($cacheKey,$fd);
            });
        }
        return true;
    }
}