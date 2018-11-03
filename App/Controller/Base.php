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
    /**
     * 求两个日期之间相差的天数
     * (针对1970年1月1日之后，求之前可以采用泰勒公式)
     * @param string $day1
     * @param string $day2
     * @return number
     */
    public static function diffBetweenTwoDays ($day1, $day2)
    {
        $second1 = strtotime($day1);
        $second2 = strtotime($day2);

        if ($second1 < $second2) {
            $tmp = $second2;
            $second2 = $second1;
            $second1 = $tmp;
        }
        return ($second1 - $second2) / 86400;
    }

    public static function curl($url,$param)
    {
        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
//        curl_setopt($curl, CURLOPT_HEADER, 1);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);

        curl_setopt($curl, CURLOPT_POSTFIELDS, $param);
        //执行命令
        $data = curl_exec($curl);
        //关闭URL请求
        curl_close($curl);
        //显示获得的数据
//        return $data;
        return json_decode($data,true);
    }
}