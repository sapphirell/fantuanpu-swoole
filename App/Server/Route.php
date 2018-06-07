<?php
/***
 *  路由类
 */
namespace App\Server;

use App\Controller\IM;
use App\Server\BaseModel;
use App\Model\TestModel;

class Route
{
    public $websocketServer;
    public $model;
    public $cache;
    public function __construct()
    {
        date_default_timezone_set('Asia/Shanghai');
        $this->cache = new Cache();
        $this->cache->del("tick");
        /**
         * 初始化websocket
         */
        $this->websocketServer = new \swoole_websocket_server("0.0.0.0", "8002");
    }
    public function filter_arr($arr)
    {
        foreach ($arr as $key => &$value)
        {
            if (is_string($value))
                $value = htmlspecialchars(addslashes($value));
            if (is_array($value))
                $value = $this->filter_arr($arr);
        }
        return $arr;
    }
    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
    }
    protected function call_shell($msg)
    {
        echo $msg . '\n';
        return true;
    }
    public function start_ws()
    {
        #清理定时器

        $this->websocketServer->on("start",     [$this , "ws_onStart"]);
        $this->websocketServer->on("workerStart",[$this , "ws_onWorkerStart"]);
        $this->websocketServer->on("open",      [$this , "ws_open"]);
        $this->websocketServer->on("message",   [$this , "ws_onMessage"]);
        $this->websocketServer->on("close",     [$this , "ws_onClose"]);
        $this->websocketServer->on("task",      [$this , "ws_onTask"]);
        $this->websocketServer->on("finish",    [$this , "ws_onTaskFinish"]);
        $this->websocketServer->on('request', function ($request, $response) {
            // 接收http请求从get获取message参数的值，给用户推送
            // $this->server->connections 遍历所有websocket连接用户的fd，给所有用户推送
        });

        $this->websocketServer->set([
            'worker_num'      => 4,
            'task_worker_num' => 1
        ]);
        $this->websocketServer->start();
    }
    public function ws_open(\swoole_websocket_server $server, $request)
    {

//        if (empty($server->model))
//        {
//            $server->model = new BaseModel();
//        }
//        else
//        {
//            $res =  $server->model
//                            ->table('test')
//                            ->select()
//                            ->where(['id'=>'1'])
//                            ->all(function ($res){
////                                var_dump($res);
//                            });

//            BaseModel::$db->query("SELECT * FROM pre_comic_area",function ($db,$res) {
//                var_dump($db);
//                var_dump($res);
//                foreach ($res as $value)
//                {
//                    echo $value ."\n";
//                }
//                BaseModel::$db->query("INSERT INTO test SET val = '123'",function (){});
//            });
//        }

        echo "server: handshake success with fd{$request->fd}\n";
    }
    public function ws_onMessage(\swoole_websocket_server $server, $frame)
    {
        $ret = $this->cache->rpop("list");
        $server->tick(1000,function () {echo 1;});
        $userMessage = $this->filter_arr(json_decode($frame->data,true));
        if (!$userMessage)
        {
            return false;
        }
        if (!$userMessage['type'] || !$userMessage['action'])
        {
            return $this->call_shell("Type or action not found! ");
        }
        //使用依赖注入容器做伪路由
        $App = new Container('\App\Controller\\'.$userMessage['type']);
        return $App->builderController($userMessage['action'],$server,$frame,$userMessage);
    }
    public function ws_onClose(\swoole_websocket_server $server,$fd)
    {}
    public function ws_onStart(\swoole_websocket_server $server)
    {}
    public function ws_onWorkerStart(\swoole_websocket_server $server, $worker_id)
    {
        if ($worker_id < $server->setting['worker_num'])
        {
            //worker 进程 初始化model和cache
            $server->model = new BaseModel();
            $server->cache = new Cache();

            if (!in_array($worker_id,(array)$this->cache->sMembers('tick')))
                //为每个worker初始化唯一定时器
                $server->tick(2000,function ($timer_id) use($worker_id,$server)
                {
                    if ($event = $server->cache->rpop("list"))
                    {
                        $event = json_decode($event,true);
                        $App = new Container('\App\Controller\\Task\\'.$event['class']);
                        $App->builderTask($event['action'],$server,$event);
                    }
                });
        }
        else
        {
            //task进程
        }

    }
    public function ws_onTask($server, $task_id, $from_id, $data)
    {
        echo "New AsyncTask[id=$task_id]".PHP_EOL;
        
        //返回任务执行的结果
        $server->finish("$data -> OK");
    }
    public function ws_onTaskFinish($server, $task_id, $data)
    {
        $this->call_shell("$task_id,$data 执行结束");
    }
}