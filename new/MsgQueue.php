<?php

/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 18-5-29
 * Time: 下午11:00
 */
class MsgQueue
{

    public $queue;

    public function __construct($queue)
    {
        $this->queue = $queue;
    }

    public function pop($data, $type = 1)
    {
        $result = msg_send($this->queue, $type, $data);
        return $result;
    }

    public function push($type = 0,$flags = MSG_IPC_NOWAIT)
    {
        msg_receive($this->queue, $type, $message_type, 1024, $message,true,$flags);
//        var_dump($message_type);
//        msg_receive($this->queue,$type,$message_type,1024,$message);
        return $message;
    }

    public function close()
    {
        return msg_remove_queue($this->queue);
    }

    /**
     * 创建一个队列（TODO:疑问待解决）
     * @param string $path_name
     * @param string $prop
     * @param string $perms
     * @return array
     */
    public static function getQueue($path_name, $prop = '1', $perms = '0666')
    {
        $data              = array();
        $data['queue＿key'] = ftok($path_name, $prop);
        $data['queue']     = msg_get_queue($data['queue＿key'], $perms);
        return $data;
    }
}