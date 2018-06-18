<?php

pcntl_async_signals(true);//异步信号
$sign_array=array(
    SIGUSR1,
    SIGUSR2,
    SIGCHLD,
);
foreach ($sign_array as $sign){
    pcntl_signal($sign,'signalHandler');//调度工作
}

function signalHandler($sign){
    echo ("触发信号".$sign.PHP_EOL);
}
$time=time();
$i=0;
while($i<=5){
    $pid = pcntl_fork();
    if($pid>0){
    }else{
        while(1){
            if(date('is')==4810){
                exit(0);
            }
            usleep(1);
        }
    }
    $i++;
}
while (1){
    sleep(1);
}