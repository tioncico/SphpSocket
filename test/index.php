<?php
/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 18-5-7
 * Time: 下午10:04
 */
pcntl_async_signals(true);
pcntl_signal(SIGUSR1,function($sign){
    switch ($sign) {
        case SIGUSR1:
            echo '触发信号';
            while(1){
//            echo "循环中";
                /*  $client = $this->getClientShmop();
                  var_dump($client);
                  if($client){
                      $this->worker->onConnect($client);
                      $this->worker->readSocket($client);
                      break;
                  }*/
                break;
                usleep(100);
            }
            break;
        case SIGUSR2:
            echo "SIGUSR2\n";
            break;
        default:
            echo "unknow";
            break;
    }
});


while(1){
    posix_kill(getmypid(),SIGUSR1);
    echo 1;
    sleep(2);
}