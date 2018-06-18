<?php
include_once "new/Manager.php";
include_once "new/SocketServer.php";
include_once "new/Worker.php";
$obj = Manager::getInstance(10,true);
$obj->setProxy(SocketServer::getInstance('0.0.0.0',9501));
//$obj->master=SocketServer::getInstance('0.0.0.0',9503);
//$obj->worker = Worker::getInstance($obj->master);
$obj->run();