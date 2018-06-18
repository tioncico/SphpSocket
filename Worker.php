<?php
/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 18-5-4
 * Time: 下午11:38
 */

class Worker
{
    private static $_instance;
    protected $worker_num = 10;
    public $worker_status_list, $worker_free_list, $worker_busy_list;
    protected $worker_file = 'Runtime/worker.txt';
    protected $process_file = 'Runtime/process.txt';
    protected $is_run = false;
    public $server;

    public static function getInstance($worker_num = 10)
    {
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new static($worker_num);
        }
        return self::$_instance;
    }

    public function __construct($worker_num = 10)
    {
        $this->worker_num = 10;
        /*   $this->worker_busy_list   = new \SplStack();
           $this->worker_free_list   = new \SplStack();
           $this->worker_status_list = array();*/
        $this->createProcess();
    }

    public function getWorker()
    {
        $fp          = fopen($this->worker_file, 'a+');
        $worker_list = unserialize(fread($fp, filesize($this->worker_file)), 1);
        if (!is_writable($this->worker_file) || empty($worker_list)) {
            return array();
        }
        $process = $worker_list->pop();
        flock($fp, LOCK_EX);
        fwrite($fp, serialize($worker_list));
        flock($fp, LOCK_UN);
        fclose($fp);
        return $process;
    }

    public function createProcess()
    {
        $process_list = array();
        for ($i = 0; $i < $this->worker_num; $i++) {
                $pid = pcntl_fork();
            if ($pid == -1) {
                throw new Exception('派生子进程错误!');
            } elseif ($pid > 0) {
                $process_list[$pid] = array(
                    'status' => 0,
                    'pid'    => $pid,
                    'worker' => null,
                );
            } elseif ($pid == 0) {
                $this->startWorker();
            }
        }
        $result = $this->writeProcess($process_list);
        if ($result === false) {
            throw new Exception('文件存在异常!');
        }
    }

    public function writeProcess($process_list)
    {
        $fp = fopen($this->process_file, 'a+');
        if (!is_writable($this->process_file) || empty($process_list)) {
            return false;
        }
        flock($fp, LOCK_EX);
        fwrite($fp, serialize($process_list));
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }


    public function startWorker()
    {
        $my_pid = getmypid();
        while (true) {
            $this->registerSignal();
            $worker = $this->getWorker();
            if ($worker) {
                $this->is_run = true;
                while ($this->is_run) {


                }
            }
        }
    }

    public function registerSignal()
    {
        pcntl_signal(SIGUSR1, function () {
            //运行

        });
        pcntl_signal(SIGUSR2, function () {
            //结束运行

        });
    }


}