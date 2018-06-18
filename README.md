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
