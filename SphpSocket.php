<?php
/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 18-5-1
 * Time: 下午7:56
 */

class SphpSocket
{

    private static $_instance;
    public $connect_list = array();//客户端列表
    public $connect_callback, $receive_callback, $close_callback;//回调函数
    public $server;//socket服务
    public $is_run=true;//是否运行
    public $config = array(//各种配置
        'debug'=>true,

        'host' => '0.0.0.0',
        'port' => '9501',

        'domain'   => AF_INET,
        'type'     => SOCK_STREAM,
        'protocol' => SOL_TCP,

        'accept' => 511,

        'option_level' => SOL_SOCKET,
        'optname'      => SO_REUSEADDR,
        'optval'       => 1,

        'read_length'=>1024,
        'read_type'=>PHP_NORMAL_READ
    );

    public $error_log=array();

    public static function getInstance($host='0.0.0.0', $port='9501')
    {
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new static($host, $port);
        }
        return self::$_instance;
    }

    public function __construct($host, $port)
    {
        $this->config['host'] = $host;
        $this->config['port'] = $port;
    }

    /**
     * 绑定事件
     * @param $type connect|receive|close
     * @param callable $function
     */
    public function on($type, callable $function)
    {
        switch (strtolower($type)) {
            case 'connect':
                $this->connect_callback = $function;
                break;
            case 'receive':
                $this->receive_callback = $function;
                break;
            case 'close':
                $this->close_callback = $function;
                break;
        }
        return $this;
    }

    public function onConnect($connection){
        if (is_callable($this->connect_callback)) {
            call_user_func($this->connect_callback,$connection);
        }
    }
    public function onReceive($connection,$data){

        if (is_callable($this->receive_callback)) {
            call_user_func($this->receive_callback,$connection,$data);
        }
    }
    public function onClose($connection){
        if (is_callable($this->close_callback)) {
            call_user_func($this->close_callback,$connection);
        }
    }

    /**
     *
     */
    public function start()
    {
        $this->createSocket();
        echo '创建socket成功!'.PHP_EOL;
        $this->bindSocket();
        echo '绑定端口成功!'.PHP_EOL;
        $this->listenSocket();
        echo '监听端口成功!'.PHP_EOL;
        $this->setOptionSocket();
        $this->acceptSocket();
        return $this;
    }

    /**
     * 创建socket
     * @return $this
     * @throws Exception
     */
    protected function createSocket()
    {
        $this->server = socket_create($this->config['domain'], $this->config['type'], $this->config['protocol']);
        if ($this->server === false) {
            throw new Exception('创建socket失败!');
        }
        return $this;
    }

    /**
     * 绑定端口
     * @return $this
     * @throws Exception
     */
    protected function bindSocket()
    {
        $this->server === false && $this->createSocket();

        $result = socket_bind($this->server, $this->config['host'], $this->config['port']);
        if ($result === false) {
            throw new Exception('绑定端口失败!');
        }
        return $this;
    }

    /**
     * 监听端口
     * @param null $accept
     * @return $this
     * @throws Exception
     */
    protected function listenSocket($accept = null)
    {
        $this->server === false && $this->createSocket();
        $accept || $accept = $this->config['accept'];
        $result = socket_listen($this->server, $accept);
        if ($result === false) {
            throw new Exception('监听端口失败!');
        }
        return $this;
    }

    /**
     * 配置socket
     * @return $this
     * @throws Exception
     */
    protected function setOptionSocket()
    {
        $this->server === false && $this->createSocket();
        $result = socket_set_option($this->server, $this->config['option_level'], $this->config['optname'], $this->config['optval']);
        if ($result === false) {
            throw new Exception('配置socket失败!');
        }
        return $this;
    }

    /**
     * 接收socket连接
     */
    protected function acceptSocket(){
        $this->server === false && $this->createSocket();
        while(true&&$this->is_run===true){
            $connection = socket_accept($this->server);
            if($connection===false){

            }else{
                $this->addConnectionList($connection);
                $this->onConnect($connection);
                $this->forkProcess($connection);
            }
        }
    }

    /**
     * 写入客户端信息
     * @param $connection
     * @return $this
     */
    protected function addConnectionList($connection){
//        $fd =
        $this->connect_list[(string)$connection]['fd']=$connection;
        return $this;
    }

    /**
     * 写入客户端进程id
     * @param $connection
     * @param $pid
     * @return $this
     */
    protected function addConnectionListProcess($connection,$pid){
        $this->connect_list[(string)$connection]['pid']=$pid;
        return $this;
    }

    /**
     * 派生进程处理
     * @param $connection
     */
    protected function forkProcess($connection){
        echo "准备fork,当前进程id".getmypid()."\n";
        $pid = pcntl_fork();
        if($pid>0){
            $this->addConnectionListProcess($connection,$pid);
            $this->readSocket($connection);
        }else{

        }
    }

    /**
     * 读取socket信息
     * @param $connection
     */
    protected function readSocket($connection){
        while(true&&isset($this->connect_list[(string)$connection])&&$this->is_run){
            $data = @socket_read($connection,$this->config['read_length'],$this->config['read_type']);
            if($data===false){
                $this->close($connection);
            }else{
                $this->onReceive($connection,$data);
            }
        }
   }

    /**
     * 发送消息给客户端
     * @param $connection
     * @param $msg
     * @return int
     */
   public function send($connection,$msg){
       $result = socket_write($connection, $msg,strlen($msg));
       return $result;
   }

    /**
     * 主动关闭客户端
     * @param $connection
     */
   public function close($connection){
       $this->onClose($connection);
       var_dump($this->connect_list[(string)$connection]['pid']);die;
       //先关掉子进程
       posix_kill($this->connect_list[(string)$connection]['pid'],SIGTERM);
       $result = socket_close($connection);
       unset($this->connect_list[(string)$connection]);
       return $result;
   }



}