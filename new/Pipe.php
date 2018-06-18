<?php

/**
 * Created by PhpStorm.
 * User: tioncico
 * Date: 18-5-21
 * Time: 下午11:51
 */
class Pipe
{
    public $fifoPath;
    private $w_pipe;
    private $r_pipe;

    /**
     * 自动创建一个管道
     *
     * @param string $name 管道名字
     * @param int $mode 管道的权限，默认任何用户组可以读写
     */
    function __construct($name = 'pipe', $mode = 0666)
    {
        $fifoPath = "tmp/$name".getmypid() ;
        if (!file_exists($fifoPath)) {
            if (!posix_mkfifo($fifoPath, $mode)) {
//                error("create new pipe ($name) error.");
                return false;
            }
        } else {
//            error("pipe ($name) has exit.");
            return false;
        }
        $this->fifoPath = $fifoPath;
    }

///////////////////////////////////////////////////
//  写管道函数开始
///////////////////////////////////////////////////
    function open_write()
    {
        $this->w_pipe = fopen($this->fifoPath, 'w');
//        var_dump($this->w_pipe);
        if ($this->w_pipe == NULL) {
            throw new Exception('打开管道错误!');
//            return false;
        }
        return true;
    }

    function write($data)
    {
        return fwrite($this->w_pipe, $data);
    }

    function write_all($data)
    {
        $w_pipe = fopen($this->fifoPath, 'w');
        fwrite($w_pipe, $data);
        fclose($w_pipe);
    }

    function close_write()
    {
        return fclose($this->w_pipe);
    }
/////////////////////////////////////////////////////////
/// 读管道相关函数开始
////////////////////////////////////////////////////////
    function open_read()
    {
        $this->r_pipe = fopen($this->fifoPath, 'r');
        if ($this->r_pipe == NULL) {
//            error("open pipe {$this->fifoPath} for read error.");
            return false;
        }
        return true;
    }

    function read($byte = 1024)
    {
        return fread($this->r_pipe, $byte);
    }

    function read_all()
    {
        $r_pipe = fopen($this->fifoPath, 'r');
        $data   = '';
        while (!feof($r_pipe)) {
            //echo "read one K\n";
            $data .= fread($r_pipe, 1024);
        }
        fclose($r_pipe);
        return $data;
    }

    function close_read()
    {
        return fclose($this->r_pipe);
    }
////////////////////////////////////////////////////

    /**
     * 删除管道
     *
     * @return boolean is success
     */
    function rm_pipe()
    {
        return unlink($this->fifoPath);
    }
}