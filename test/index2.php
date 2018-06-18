<?php
/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 18-5-7
 * Time: 下午10:05
 */
$message_queue_key= ftok(__FILE__, 'b');
var_dump($message_queue_key);
$message_queue= msg_get_queue($message_queue_key, 0666);
var_dump($message_queue);
//插入一条数据到队列中
msg_send($message_queue, 10086, array('test'=>'这是一条测试的数组数据'));
msg_receive($message_queue, 0,$type,1000,$message);
var_dump($type,$message);
