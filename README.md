# SphpSocket  
php预派生多进程tcp服务框架,  
一个manage主进程进行回收以及派生其他子进程  
一个proxy负责tcp socket的接收发送  
多个work进程进行处理  
进程通信采用linux消息队列以及进程信号(有管道通信,注释掉了)  
主要代码在./new文件夹中  
启动方法:  
test.php中启动  
socket_client进行连接  
#其他说明
该框架是为了学习php的多进程,tcp协议才产生的,所以有很多的不足  
tcp传输应该不需要增加协议头
proxy进程接收发送数据应该增加缓冲  
进程信号在框架中用的不多,都注释和弃用了  
只是为了学习嘛~~~~~~~  
