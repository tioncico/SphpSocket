<?php
/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 18-5-6
 * Time: 下午11:58
 */

class Worker
{
    protected $manager;
    protected $is_run = 1;
    private static $_instance;
    protected $max_worker_num=5000;//处理5000次时自动重启进程
    public $connect_callback, $receive_callback, $close_callback;//回调函数
    public $config = array(//各种配置
        'debug'       => true,
        'read_length' => 1024,
        'read_type'   => PHP_NORMAL_READ
    );

//    const WORKING = 1, WORKEND = 2;

    public static function getInstance($manager)
    {
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new static($manager);
        }
        return self::$_instance;
    }

    public function __construct($manager, $config = array())
    {
        $this->manager = $manager;
        foreach ($config as $key => $value) {//配置
            $this->$key = $value;
        }
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

    public function onConnect($connection)
    {
        if (is_callable($this->connect_callback)) {
            call_user_func($this->connect_callback, $connection);
        }
//        $this->send($connection, '连接成功!');
//        var_dump('连接事件'.$connection . getmypid()) . PHP_EOL;
    }

    public function onReceive($connection, $data)
    {
        if (is_callable($this->receive_callback)) {
            call_user_func($this->receive_callback, $connection, $data);
        }
        $this->send($connection, $data);

//        $this->close($connection);
//        var_dump('发送事件'.$connection  . getmypid()).PHP_EOL;
    }

    public function onClose($connection)
    {
        if (is_callable($this->close_callback)) {
            call_user_func($this->close_callback, $connection);
        }
//        var_dump('关闭事件' . $connection . getmypid()) . PHP_EOL;
    }

    public function execute()
    {
        echo "子进程启动";
        $i=0;
        while (1) {
//            echo "获取队列\n";
            $worker = $this->manager->msg_queue['worker']->push(1, 0);
//            var_dump($worker);
//            echo "获取成功\n";
            if ($worker) {
                $this->handle($worker);
                $i++;
            }
            if($i>=$this->max_worker_num){
                echo getmypid()."退出\n";
                exit(0);
            }
        }
//        var_dump($worker);return;
        return;
    }

    public function listenSign()
    {
        $this->execute();
        return;
        pcntl_async_signals(true);//异步信号
        pcntl_signal(SIGCONT, array($this, 'signalHandler'));
        pcntl_signal(SIGUSR1, array($this, 'signalHandler'));
        pcntl_signal(SIGUSR2, array($this, 'signalHandler'));
//        posix_kill(getmypid(), SIGSTOP);//进程休眠
    }


    public function signalHandler($signo)
    {
        switch ($signo) {
            case SIGCONT:
//                echo "进程唤醒";
                $this->execute();
                posix_kill(getmypid(), SIGSTOP);///处理完进程继续休眠
                break;
            case SIGUSR1:
//                echo '触发信号(处理数据)';
//                $worker->execute();
//                usleep(100);
                break;
            case SIGUSR2:
                echo "SIGUSR2\n";
                break;
            default:
                echo "unknow";
                break;
        }
        return;
    }

    /**
     * 处理管道数据
     * @param $worker
     */
    protected function handle($worker)
    {
        $this->talkManager(1, $worker);
        switch ($worker['type']) {
            case 'connect':
                $this->onConnect($worker['fd']);
                break;
            case 'receive':
//                var_dump($worker);
                $this->onReceive($worker['fd'], $worker['data']);
                break;
            case 'close':
                $this->onClose($worker['fd']);
                break;
        }
//        echo "发送结束请求\n";
        $this->talkManager(2, $worker);

    }

    /**
     * 告诉主进程工作状态
     * @param $work_status
     * @param $work_data
     */
    protected function talkManager($work_status, $work_data)
    {
        $msg = array(
            'type' => $work_status,
            'pid'  => getmypid(),
            'data' => $work_data
        );
//        $this->manager->triggerSign(SIGUSR2);
//        $this->manager->msg_queue['worker']->pop($msg, 2);
//        $this->manager->writePipe($this->manager->pipe['master'], $msg);
    }

    public function send($fd, $data)
    {
        $msg    = array(
            'type' => 'send',
            'fd'   => $fd,
            'data' => $data,
        );
        $result = $this->manager->msg_queue['proxy']->pop($msg, 3);
    }

    public function close($fd)
    {
        $msg    = array(
            'type' => 'close',
            'fd'   => $fd,
        );
        $result = $this->manager->msg_queue['proxy']->pop($msg, 3);
    }

    /**
     * 结束执行(旧)
     */
    /*    public function endExecute()
        {
            $msg = array(
                'type' => 'end_execute',
                'data' => array(
                    'pid'    => getmypid(),
                    'status' => null,
                    'worker' => null
                )
            );
    //        echo '结束';
            $this->manager->writePipe($this->manager->pipe['master'], $msg);
        }*/


}