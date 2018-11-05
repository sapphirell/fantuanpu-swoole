<?php
namespace App\Controller\Task;

use App\Controller\Base;
use App\Server\BaseModel;
use App\Server\Cache;
use App\Server\CacheKey;
use App\Server\Config;

class Common extends Base
{
    public function task()
    {
        echo "hello";
    }

    /**
     * 用户回帖提醒
     * @param $server
     * @param $user_message
     */
    public function user_notice($server,$user_message)
    {
        $forum_uid_list_cacheKey   = CacheKey::FORUM_UID_LIST;
        $this->call_uid(
            $server,
            $user_message['to_uid'],
            ['msg'=>$user_message['msg']],20001,
            function($uid) use($forum_uid_list_cacheKey)
            {
                $this->cache->srem($forum_uid_list_cacheKey,$uid);
            }
        );
    }

    /**
     * 用户签到队列
     * @param $server
     * @param $user_message
     */
    public function user_sign($server,$user_message)
    {
        $server->model->table('pre_user_sign')->select()->where(['uid'=>$user_message['uid']])
            ->all(function ($user_sign) use($user_message,$server){
                $user_sign = $user_sign[0];
                if (empty($user_sign))
                    $this->shell_notice("用户的签到记录不存在,请检查web端");

                //判断是否达成连续签到
                $dif = self::diffBetweenTwoDays($user_sign['sign_date'],date("Y-m-d"));
                if ($dif == 0)
                    return $this->shell_notice("用户已签到");

                //查看连续签到天数,最高七天奖励
                $user_sign['hit']       = $dif > 1 ? 1 : $user_sign['hit'] + 1;
                $user_sign['sign_date'] = date("Y-m-d");
                $user_sign['sign_time'] = time();

                BaseModel::$db->query(BaseModel::update('pre_user_sign',$user_sign,['uid',$user_message['uid']]),
                    function ($event) use($user_sign,$user_message,$server) {
                        //这是当天第几个签到
                        $cache      = new Cache();
                        $sign_num   = $cache->incr('sign_'.date("Y-m-d") ,1);

                        $sign_message = self::_user_sign_ext_message($user_message['uid'],$sign_num,$user_sign['hit']);

                        $this->call_uid($server,$user_message['uid'],['msg' => $sign_message],30001,function($uid){});
                });
            });

    }

    /**
     * 传入参数,获取用户签到获得的奖励文字,并调用奖励接口
     * @param $sign_num 今天第几个签到的
     * @param $uid
     * @param $hit 连续签到多少天
     * @param $ext
     */
    public static function _user_sign_ext_message(int $uid ,int $sign_num,int $hit)
    {
        $ext = Config::ext();
        //连续签到的奖励数额
        $ext['extcredits2'] += ( 8 + ($hit > 7 ? 7 : $hit) * 2 );
        //验证用户发帖奖励数值
        $act = self::curl(Config::WEB_URL."/app/complete_action" ,[
            'swoole_token' => Config::SWOOLE_TOKEN,
            'act'           => 'SIG',
            'uid'           => $uid,
            'rid'           => 0
        ]);
        if (!empty($act))
            foreach ($act['data'] as $key =>$value)
                $ext[$key] += $value;



        $msg = "签到成功!您是第{$sign_num}个签到的用户,";
        if ($sign_num == 1)
        {
            $msg .= "额外奖励项链 1";
            $ext['extcredits3'] += 1;
        }
        elseif($sign_num < 11)
        {
            $msg .= "额外奖励扑币 90" ;
            $ext['extcredits2'] += 40;
        }
        elseif($sign_num < 101)
        {
            $msg .= "额外奖励扑币 5" ;
            $ext['extcredits2'] += 5;
        }
        //翻译成奖励,并通知用户
        $msg .= ",连续签到{$hit}天,本次签到一共获得";
        foreach ($ext as $key => $value)
            $msg .= $value ? " " . $value . "枚" . Config::$extcredits[$key] : "";

        //奖励用户并记录日志
        self::curl(Config::WEB_URL.'/app/add_user_coin',[
            'swoole_token'  => Config::SWOOLE_TOKEN,
            'uid'           => $uid,
            'ext'           => json_encode($ext)
        ]);

        return $msg;
    }
}