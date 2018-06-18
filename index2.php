<?php
/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 18-6-2
 * Time: 下午7:44
 */
include_once 'new/Pipe.php';
$pipe = new Pipe();
echo "正在插入数据\n";
if(pcntl_fork()){
    $pipe->open_write();
    echo "插入完毕\n";
    $pipe->write('测试数据\n');
}else{
    $pipe->open_read();
    $data = $pipe->read_all();
    var_dump($data);
}

