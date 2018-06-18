<?php
// 获取消息队列key
$key = ftok(__FILE__,'w');

// 创建一个消息队列
$queue = msg_get_queue($key);

$child = [];
$num   = 1;
$result = [];

for($i=0;$i<$num;$i++){
    $pid = pcntl_fork();
    if($pid == -1) {
        die('fork failed');
    } else if ($pid > 0) {
        $child[] = $pid;
    } else if ($pid == 0) {
        $sleep = rand(1,4);
        msg_send($queue,2,array('name' => $i.'~'.$sleep));
        sleep($sleep);
        exit(0);
    }
}

while(count($child)){
    foreach($child as $k => $pid) {
        $res = pcntl_waitpid($pid,$status,WNOHANG);
        if ($res == -1 || $res > 0 ) {
            unset($child[$k]);
            msg_receive($queue,0,$message_type,1024,$data);
            $result[] = $data;
        }
    }
}
msg_remove_queue($queue);
print_r($result);