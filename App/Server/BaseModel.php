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
    public function connect()
    {
        $this->mysql->connect(
            Config::DB,
            function ($db, $result) {

                if ($result)
                {
                    self::$db = $db;
                }

                else
                    echo "Mysql connect failed \n";

            });

    }
    public function all($callback)
    {
        if($callback instanceof \Closure )
            return self::$db->query($this->getSql(),function ($db,$res) use ($callback)
            {
                $callback($res);
            });
        else
            return false;

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
    public static function update($table,array $update,array $where)
    {
        $query = "UPDATE {$table} ";
        foreach ($update as $key => $value)
        {
            $query .= " SET {$key} = {$value} ";
            if ($key != count($update) -1)
                $query .= ",";
        }
        $query .= " WHERE ";
        foreach ($where as $key => $value)
        {
            $query .= "{$key} = {$value} ";
            if ($key != count($update) -1)
                $query .= " AND ";
        }
        return $query;
    }
}