<?php
/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 18-5-6
 * Time: 下午11:09
 */

class Manager
{
    private static $_instance;
    protected $worker_num = 10;//预派生进程数
    protected $proxy = array(
        'instance' => null,
        'pid'      => 0
    );//网络代理进程
    public $worker;
    public $pipe = array();//管道
    public $msg_queue = null;//消息队列
    public $ppid;//守护进程id
    protected $daemon = false;//是否开启守护进程
//    protected $systemid = 864;
    public $worker_list = null, $worker_free_list = null, $worker_busy_list = null;
    public $handle_queue = null;//处理队列

    public static function getInstance($worker_num = 10, $daemon = false)
    {
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new static($worker_num, $daemon);
        }
        return self::$_instance;
    }

    public function __construct($worker_num = 10, $daemon = false)
    {
        $this->worker_num = $worker_num;
        $this->daemon     = $daemon;
        $this->addMsgQueue();
//        var_dump($this->pipe);
        $this->worker_free_list = array();
        $this->worker_busy_list = array();
        $this->handle_queue     = new SplDoublyLinkedList();
    }

    /**
     * 设置网络代理实例
     * @param $proxy
     */
    public function setProxy($proxy)
    {
        $this->proxy['instance'] = $proxy;
    }

    public function run()
    {

        $this->daemon();
        for ($i = 0; $i < $this->worker_num; $i++) {
//            echo $i."最大次数:".$this->worker_num."\n";
            $this->createProcess();
//            echo "创建{$i}个成功\n";
        }
        $this->startSocket();
        $this->listenSign();
//        $this->readPipe($this->pipe['master'], array($this, 'handleWorker'));
        $i = 1;
        while (1) {
            if ($i == 100000) {
                if (count($this->worker_busy_list) > 1) {
                    echo "忙碌进程数:" . count($this->worker_busy_list) . "\n";
                }
//                echo "空闲进程数:".count($this->worker_free_list)."\n";
//                echo "忙碌进程数:".count($this->worker_busy_list)."\n";
                $i = 0;
            }
            $i++;
            $worker = $this->msg_queue['worker']->push(2, 0);
            if (!empty($worker)) {
                $this->handleWorker($worker);
            }
//                var_dump($worker);
//            usleep(1);
        }
//        $this->pipeWorker();

//        var_dump($this->worker_free_list);
        /*    while (1) {
                echo "好啦" . self::$ppid . "和" . getmypid() . "\n";
                sleep(1);
                var_dump($this->worker_free_list);
            }*/
    }

    /**
     * 创建监听事件
     */
    protected function startSocket()
    {
        $pid = pcntl_fork();
        if ($pid > 0) {
            $this->proxy['pid'] = $pid;
            return;
        } elseif ($pid == 0) {
            $this->proxy['instance']->run($this);
        } elseif ($pid === -1) {
            throw new Exception('派生网络代理进程出错!');
        }
    }

    /**
     * 守护进程
     * @throws Exception
     */
    protected function daemon()
    {
        if ($this->daemon === false) {
            $this->ppid = getmypid();
            return;
        }
        umask(0);
        set_time_limit(0);
        $pid = pcntl_fork();
        echo $pid . "\n";
        if ($pid > 0) {
//            sleep(15);
//            posix_kill($pid, SIGUSR1);
//            echo "主进程退出,子进程{$pid}继续执行\n";
            exit(0);
        }
        if ($pid == 0) {
            $i          = 0;
            $this->ppid = getmypid();
//            echo "子进程过来了!你们注意点" . static::$ppid . "和" . getmypid() . "\n";
            return;
        }
        if ($pid == -1) {
            throw new Exception('派生子进程错误!');
        }
    }

    /**
     * 创建子进程处理
     * @throws Exception
     */
    public function createProcess()
    {
        //只使用主进程进行创建任务
        $pid = pcntl_fork();
        if ($pid > 0) {
            $this->worker_list[$pid] = array(
                'status' => 0,
                'pid'    => $pid,
                'worker' => null,
            );
            return;
        } elseif ($pid == 0) {
            $this->listenWorker();
            exit(0);
        } else {
            echo "出错!";
            throw new Exception('派生子进程错误!');
        }
    }

    /**
     * 监听任务,worker进程
     */
    public function listenWorker()
    {
        //安装信号触发器器
        include_once 'new/Worker.php';
        $worker = new Worker($this);
        $worker->listenSign();
    }

    /**
     * 监听信号
     */
    public function listenSign()
    {
        pcntl_async_signals(true);//异步信号
        $sign_array = array(
            SIGUSR1,
            SIGUSR2,
            SIGCHLD,
        );
        foreach ($sign_array as $sign) {
            pcntl_signal($sign, array($this, 'signalHandler'));//信号调度工作
        }
//        posix_kill(getmypid(), SIGSTOP);//进程休眠
    }

    /**
     * 信号处理
     * @param $signo
     */
    public function signalHandler($signo)
    {
        switch ($signo) {
            case SIGUSR1:
//                echo '触发信号(处理数据)'.PHP_EOL;
//                $this->worker();
                break;
            case SIGUSR2:
//                echo "进程回收处理\n";
//                $worker = $this->msg_queue['worker']->push(2);
//                var_dump($worker);
//                $this->handleWorker($worker);
//                $this->readPipe($this->pipe['master'], array($this, 'handleWorker'));
                break;
            case SIGCHLD:
                //子进程退出
                echo "监控到子进程退出\n";
                $this->processRecycling();
                break;
            default:
                echo "unknow";
                break;
        }
        return;
    }

    /**
     * 进程回收
     */
    protected function processRecycling()
    {
        while (($pid = pcntl_waitpid(-1, $status, WUNTRACED)) != 0) {
            // 退出的子进程pid
            if ($pid > 0) {
                unset($this->worker_list[$pid]);
                $this->createProcess();
                echo "fork成功\n";
            } else {
                // 出错了
                throw new Exception('监控子进程退出发生错误');
            }
            usleep(1);
        }
    }

    /**
     * 实例化消息队列
     */
    public function addMsgQueue()
    {
        include_once 'new/MsgQueue.php';

        $message_queue_key = ftok(__FILE__, 'a');
        echo "worker:{$message_queue_key}";
        if (msg_queue_exists($message_queue_key)) {
            msg_remove_queue(msg_get_queue($message_queue_key, 0666));
        }
        $message_queue             = msg_get_queue($message_queue_key, 0666);
        $this->msg_queue['worker'] = new MsgQueue($message_queue);//worker队列


        $message_queue_key = ftok(__FILE__, 'b');
        echo "proxy:{$message_queue_key}";
        if (msg_queue_exists($message_queue_key)) {
            msg_remove_queue(msg_get_queue($message_queue_key, 0666));
        }
        $message_queue            = msg_get_queue($message_queue_key, 0666);
        $this->msg_queue['proxy'] = new MsgQueue($message_queue);//proxy队列
    }

###################以下代码已经作废,用于怀念#########################

    /**
     * 开始工作
     * @param $client_data
     */
    public function worker()
    {
        //空闲队列出列
        if (count($this->worker_free_list) > 0) {
            $free_worker                                 = array_shift($this->worker_free_list);
            $this->worker_busy_list[$free_worker['pid']] = $free_worker;
//            echo "忙碌进程数2：".count($this->worker_busy_list).PHP_EOL;
            $result = posix_kill($free_worker['pid'], SIGCONT);//唤醒进程
            return $result;
//            $this->writePipe($this->pipe['worker'], $client_data);
        } else {
//            $this->worker();
//            echo "卡死";
            return false;
//            $this->writePipe($this->pipe['master'], $client_data);
        }
    }

    public function handleWorker($data)
    {
//        var_dump($data);
        if ($data['type'] == 1) {
            $this->worker_busy_list[$data['pid']]['worker'] = $data['data'];
        } else {
            unset($this->worker_busy_list[$data['pid']]);
            $this->worker_free_list[$data['pid']] = array(
                'status' => 0,
                'pid'    => $data['pid'],
                'worker' => null,
            );
//            var_dump($this->worker_free_list);/
//            echo "新增进程:{$data['pid']}";
        }
    }

    /**
     * 处理管道数据
     */
    public function handle($worker)
    {
//        var_dump($this->worker_free_list);
        switch ($worker['type']) {
            case 'connect':
                $this->worker($worker);
                break;
            case 'receive':
                $this->worker($worker);
                break;
            case 'close':
                $this->worker($worker);
                break;
            case 'end_execute':
                $this->endExecute($worker['data']);
                break;
        }
    }

    /**
     * 结束工作
     * @param $data
     */
    public function endExecute($free)
    {
//        var_dump($free);
        unset($this->worker_busy_list[$free['pid']]);
        $this->worker_free_list[$free['pid']] = $free;
    }

    /**
     * 触发主进程信号
     * @param $sign
     * @return bool
     */
    public function triggerSign($sign)
    {
//        var_dump($this->ppid);
        return posix_kill($this->ppid, $sign);
    }


    /**
     * 处理管道数据
     */
    protected function pipeWorker()
    {
//        while (true) {
        $this->readPipe($this->pipe['master'], array($this, 'handleWorker'));
//            $this->handle();
//        }
    }

    /**
     * 将数据写入管道
     * @param $data
     */
    public function writePipe($pipe, $data)
    {
        $data  = serialize($data);
        $end   = '\end\end';
        $start = array(
            'length' => strlen($data),
            'end'    => $end,
        );
        $start = str_pad(serialize($start), 64, ' ', STR_PAD_RIGHT);
        //数据报文
        $data = $start . $data . $end;//自定义数据结尾
//        var_dump($data);
        $pipe->open_write();
        $pipe->write($data);
        $pipe->close_write($data);
    }


    /**
     * 监听管道数据
     */
    public function readPipe($pipe, callable $callable)
    {
        $data = '';
        while (true) {
            usleep(1);
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
        }
        return $worker;
    }
}