<?php
namespace App\Server;

class BaseModel
{
    /**
     * @var \swoole_mysql
     */
    public $mysql;
    /**
     * @var swoole db
     */
    public static $db;

    public static $orm;
    /**
     * @var self db class
     */
    public $queryParam;
    /**
     * @var array 提供的链式操作方法
     */
    protected $method;

    public function __construct()
    {
        $this->method = ['where','select','limit','order','table'];
        $this->mysql = new \swoole_mysql();
        $this->connect();

    }
    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
        if (in_array($name, $this->method))
        {
            $this->queryParam[$name] = reset($arguments);
            return $this;
        }
    }
    public function clear()
    {
        $this->querySql = [] ;
        return $this ;
    }
    public function connect($connect_fn = "")
    {
        $this->mysql->connect(
            Config::DB,
            function ($db, $result) use ($connect_fn) {

                if ($result)
                {
                    self::$db = $db;
                    $connect_fn && $connect_fn();
                }

                else
                    echo "Mysql connect failed \n";

            });

    }

    /**
     *
     * @param        $query
     * @param string $callable
     */
    public function query($query,$callable='')
    {
        //这是将要执行的query方法
        $to_do = function () use ($query , $callable)
        {
            if($callable instanceof \Closure )
                return self::$db->query($query,function ($db,$res) use ($callable)
                {
                    $callable($res);
                });
        };
        //执行query前,要检查是否处于链接状态,否则把query方法包起来,给connect执行
        if (self::$db->connected == false)
            $this->connect($to_do);
        else
            $to_do();
    }
    public function all($callback)
    {
        $query = function () use ($callback){
            if($callback instanceof \Closure )
                return self::$db->query($this->getSql(),function ($db,$res) use ($callback)
                {
                    $callback($res);
                });
            else
                return false;
        };
        if (self::$db->connected == false)
        {
            $this->connect($query);
        }
        else
        {
            $query();
        }

    }

    public function getSql()
    {
        //select
        $select = $this->queryParam['select']
                ? rtrim(implode(",",$this->queryParam['select']),',')
                : " * ";

        //表
        if ($this->queryParam['table'])
        {
            $table = $this->queryParam['table'];
        }

        $sql = "SELECT {$select} FROM {$table} WHERE 1=1 ";
        //where
        if (!empty($this->queryParam['where']))
        {
            foreach ($this->queryParam['where'] as $key=>$value)
            {
                $sql .= " AND `{$key}` = '$value'";
            }
        }
//        echo $sql;
        return $sql;
    }

    /**
     * 生成sql语句
     * @param       $table
     * @param array $update
     * @param array $where
     * @返回 string
     */
    public static function update($table,array $update,array $where)
    {
        $query = "UPDATE {$table}  SET ";
        foreach ($update as $key => $value)
        {
            $query .= " {$key} = '".addslashes(htmlspecialchars($value))."' ,";
        }
        $query = rtrim($query,',');
        $query .= " WHERE ";
        foreach ($where as $key => $value)
        {
            $query .= " {$key} = '".addslashes(htmlspecialchars($value))."'  AND";
            if ($key != count($update) -1)
                $query .= "";
        }
        $query = rtrim($query,'AND');
        return $query;
    }

    /**
     * 生成insert 语句
     */
    public static function insert($table,array $insert)
    {
        $query = "INSERT INTO {$table}  SET ";
        foreach ($insert as $key => $value)
        {
            $query .= "{$key} = '".addslashes(htmlspecialchars($value))."' ,";
        }
        return rtrim($query,',');
    }
}