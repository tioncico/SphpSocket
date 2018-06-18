<?php
/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 18-4-27
 * Time: 下午9:10
 */
error_reporting(E_ALL);
$config = array(
    'host' => '127.0.0.1',
    'port' => 9501
);
//端口111
$service_port = 111;
//本地
$address = '127.0.0.1';
//创建 TCP/IP socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket < 0) {
    die("socket创建失败原因: " . socket_strerror($socket) . "\n");
} else {
    echo "socket创建成功...\n";

}
$result = socket_connect($socket, $config['host'], $config['port']);
if ($result < 0) {
    die("SOCKET连接失败原因: ($result) " . socket_strerror($result) . "\n");
} else {
    echo "SOCKET连接成功...\n";
}
/*while ($out = socket_read($socket, 2048)) {
    echo "服务器响应:{$out}\n";
    break;
}*/
while (true) {
    $i=1000000;
    $start = array(
        'length' => strlen($i),
        'end'=>'\end\end',
    );
    $start=serialize($start);
    $start=str_pad($start,64,' ',STR_PAD_RIGHT);
    //数据报文

    $data = $start.$i . '\end\end';//自定义数据结尾
//        var_dump($data);
    socket_write($socket, $data, strlen($data));
     $start = microtime(true);

    /*    echo "请输入您需要发送的数据\n";
        //发送命令
        $handle=fopen("php://stdin", "r");
        $s=fgets($handle);
        socket_write($socket, $s, strlen($s));*/
        while ($out = socket_read($socket, 2048)) {
            if(microtime(true)-$start>=0.5){
                echo "服务器响应超过1秒:{$out}\n";
            }
            break;
        }
    usleep(100);
    $i++;
}