<?php
/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 18-5-18
 * Time: 上午12:09
 */

class SocketServer
{
    private static $_instance;
    protected $manager;
    public $connect_list = array();//客户端列表
    public $connect_callback, $receive_callback, $close_callback;//回调函数
    public $server;//socket服务
    public $is_run = true;//是否运行
    public $config = array(//各种配置
        'debug' => true,

        'host' => '0.0.0.0',
        'port' => '9501',

        'domain'   => AF_INET,
        'type'     => SOCK_STREAM,
        'protocol' => SOL_TCP,

        'accept' => 511,

        'option_level' => SOL_SOCKET,
        'optname'      => SO_REUSEADDR,
        'optval'       => 1,

        'read_length' => 1,
        'read_type'   => PHP_BINARY_READ
    );

    public $error_log = array();

    public static function getInstance($host = '0.0.0.0', $port = '9501')
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

    public function onConnect(string $connection)
    {
//        $this->manager->triggerSign(SIGUSR1);
        $msg = array(
            'type' => 'connect',
            'fd'   => $connection,
            'data' => array()
        );
//        echo "发送数据";
        $this->manager->msg_queue['worker']->pop($msg,1);
//        echo "发送数据完成\n";
    }

    public function onReceive(string $connection, $data)
    {
//        $this->manager->triggerSign(SIGUSR1);
        $msg = array(
            'type' => 'receive',
            'fd'   => $connection,
            'data' => $data
        );
//        var_dump($msg);
        $this->manager->msg_queue['worker']->pop($msg,1);
//        echo "发送数据完成\n";
    }

    public function onClose(string $connection)
    {
//        $this->manager->triggerSign(SIGUSR1);
        $msg = array(
            'type' => 'close',
            'fd'   => $connection,
            'data' => array()
        );
        $this->manager->msg_queue['worker']->pop($msg,1);
    }

    /**
     *
     */
    public function run($manager)
    {
        $this->manager = $manager;
        $this->createSocket();
        echo '创建socket成功!' . PHP_EOL;
        $this->bindSocket();
        echo '绑定端口成功!' . PHP_EOL;
        $this->listenSocket();
        echo '监听端口成功!' . PHP_EOL;
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
        socket_set_nonblock($this->server);
        return $this;
    }

    /**
     * 接收socket连接
     */
    protected function acceptSocket()
    {
        $this->server === false && $this->createSocket();
        $time = time();
        $i=0;
        $y=0;
        echo "开始工作\n";
        while (true && $this->is_run === true) {
            $this->respond();
            $connection = socket_accept($this->server);
//            var_dump($connection);
            if ($connection === false) {

            } else {
                echo "检测到链接" . PHP_EOL;
                $result = socket_set_nonblock($connection);
//                var_dump($result);
                $i++;
                $this->addConnectionList($connection);
                $this->onConnect((string)$connection);
            }
         /*   if(time()-$time>5){
                echo "socket进程".getmypid()."活着,处理了{$i}次事情\n";
                $time=time();
            }*/
            $this->readSocket();//收取客户端数据
//            echo "循环一次完成\n";
            usleep(1);
        }
    }

    /**
     * 响应数据
     */
    protected function respond(){
//        echo "获取响应数据\n";
        while ( $data = $this->manager->msg_queue['proxy']->push(3)){
            switch ($data['type']){
                case "send":
                    $this->send($this->connect_list[$data['fd']]['fd'],$data['data']);
                    break;
                case 'close':
                    $this->close($this->connect_list[$data['fd']]['fd']);
                    break;
            }
        }
//        echo"获取成功\n";
    }

    /**
     * 写入客户端信息
     * @param $connection
     * @return $this
     */
    protected function addConnectionList($connection)
    {
        $this->connect_list[(string)$connection]['fd'] = $connection;
        return $this;
    }
    /**
     * 根据客户端连接,遍历读取数据
     */
    protected function readSocket()
    {
//        var_dump($this->connect_list);
        foreach ($this->connect_list as $connect) {
//            echo '读取开始'.PHP_EOL;
            $start='';
//            socket_set_blocking($connect['fd'],false);
            $result = @socket_recv($connect['fd'],$start, 64,MSG_DONTWAIT);
//                var_dump($result);
            if($result===false){
                $error_id = socket_last_error();
                if($error_id!==11){
                    socket_clear_error();
                    throw new Exception(socket_strerror($error_id));
                }
            }elseif ($result===0){//客户端关闭
                $this->close($connect['fd']);
            }else{
//                var_dump($connect['fd']);
//                var_dump($start);
                $start = unserialize(trim($start,' '));//解析数据
                $data  = @socket_read($connect['fd'],$start['length']+strlen($start['end']), $this->config['read_type']);
                if (strpos($data, $start['end']) !== false) {
                    $queque = explode('\end\end', $data);
                    $num    = count($queque);
//                    var_dump($queque);
                    foreach ($queque as $key => $value) {
                        if ($key == $num - 1) {
                            $data = $value;
                        } else {
                            $data = ($value);
                            $this->onReceive((string)$connect['fd'], $data);
                        }
                    }
                }
            }
//            echo '读取结束'.PHP_EOL;
//            echo "接收数据" . $data . PHP_EOL;
//                var_dump($connect,$data).PHP_EOL;
          /*  if ($data === false) {
//                $this->close($connect['fd']);
            } else {
                if(empty($data)){
                    continue;
                }
                echo "接收数据" . $data . PHP_EOL;
//                var_dump($connect,$data).PHP_EOL;
                $this->onReceive($connect['fd'], $data);
            }*/
        }
    }


    /**
     * 发送消息给客户端
     * @param $connection
     * @param $msg
     * @return int
     */
    public function send($connection, $msg)
    {
        $result = socket_write($connection, $msg, strlen($msg));
        return $result;
    }

    /**
     * 主动关闭客户端
     * @param $connection
     */
    public function close($connection)
    {
        $this->onClose((string)$connection);
//        var_dump($this->connect_list[(string)$connection]['pid']);
//        die;
        //先关掉子进程
//        posix_kill($this->connect_list[(string)$connection]['pid'], SIGTERM);
        $result = socket_close($connection);
        unset($this->connect_list[(string)$connection]);
        return $result;
    }

    /**
     * 监听管道数据
     */
    public function readPipe($pipe, callable $callable)
    {
        $data = '';
        while (true) {
            $pipe->open_read();
            $start = $pipe->read(64);
            if (empty($start)) {
                $pipe->close_read();
                continue;
            }
            $start = unserialize(trim($start, ' '));
//            var_dump($start);
            $data = $pipe->read($start['length'] + strlen($start['end']));
            if (strpos($data, $start['end']) !== false) {
//                var_dump($data);
                $queque = explode('\end\end', $data);
                $num    = count($queque);
                foreach ($queque as $key => $value) {
                    if ($key == $num - 1) {
                        $data = $value;
                    } else {
                        $worker = unserialize($value);
//                        var_dump($worker);
                        $callable($worker);
                    }
                }
            }
            $pipe->close_read();
            break;
            usleep(1);
        }
        return $worker;
    }


    /**
     * 读取socket信息(旧)
     * @param $connection
     */
    /*  protected function readSocket($connection){
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
       * 写入客户端进程id(旧)
       * @param $connection
       * @param $pid
       * @return $this
       */
    /*    protected function addConnectionListProcess($connection,$pid){
            $this->connect_list[(string)$connection]['pid']=$pid;
            return $this;
        }*/

    /**
     * 派生进程处理(旧)
     * @param $connection
     */
    /*protected function forkProcess($connection)
    {
        echo "准备fork,当前进程id" . getmypid() . "\n";
        $pid = pcntl_fork();
        if ($pid > 0) {
            $this->addConnectionListProcess($connection, $pid);
            $this->readSocket($connection);
        } else {

        }
    }*/

}
