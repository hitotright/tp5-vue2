<?php
/**
 * 进程swoole1.9
 * Created by PhpStorm.
 * User: hitoTright,wdy
 * Date: 2018/2/26
 * Time: 10:58
 * 示例：
 */
/*// 继承并实现parse和input方法，构造函数里设置队列Key
class foo extends Process {
    public function __construct(){
        //必须先设置队列唯一Key,规定为用当前路径名作为参数，避免重复
        $this->setQUEUEKEY(ftok(__FILE__, 1));
    }
    public function parse($work){echo 'parse handle '.$work.PHP_EOL;}
    public function input(){for ($i = 1;$i<10;$i++){$this->push($i);}}
}
// 开始执行
(new foo())->start();
// extras：
1. 如果想清除系统里队列：
$this->setFREEQUEUE(1);
*/

namespace app\common;
abstract class Process
{
    private $PROCESS_NUM = 5;
    private $THREAD_NUM = 2;
    private $QUEUE_KEY=0;
    private $TIMEOUT=5;
    private $worker;
    private $FREE_QUEUE = false;


    /**
     * 内容处理
     * @param string $work
     * @return mixed
     */
    abstract public function parse($work);

    /**
     * 添加任务
     * @return string $work
     */
    abstract public function input();

    public function start(){
        if($this->PROCESS_NUM < 1||$this->THREAD_NUM < 1){
            echo 'error:process_num or thread_num need greater than 1!'.PHP_EOL;
            return;
        }

        $monitor = new \swoole_process([$this,'produce'],false,false);
        if($this->QUEUE_KEY !=0){
            $monitor->useQueue($this->QUEUE_KEY);
        }
        $mid=$monitor->start();
        echo "Master: new produce, PID=".$mid."\n";

        $process=null;
        for($i = 0; $i < $this->PROCESS_NUM; $i++)
        {
            $process = new \swoole_process([$this,'consume'], false, false);
            if($this->QUEUE_KEY !=0){
                $t=$process->useQueue($this->QUEUE_KEY);
            }
            $pid = $process->start();
            echo "Master: new consume, PID=".$pid.",$t\n";
        }

        while ($ret = \swoole_process::wait())
        {
            $pid = $ret['pid'];
            echo "Worker Exit, PID=".$pid.PHP_EOL;
        }

        if($this->FREE_QUEUE){
            $monitor->freeQueue();
        }
    }

    public function consume(\swoole_process $worker)
    {
        while (true){
            if($this->TIMEOUT > 0){
                $status = $worker->statQueue();
                if($status['queue_num']==0){
                    sleep($this->TIMEOUT);
                    $status = $worker->statQueue();
                    if($status['queue_num']==0){
                        echo "PID=".$worker->pid.", no work\n";
                        $worker->exit(0);
                    }
                }
            }
            $work = $worker->pop();
            echo "PID=".$worker->pid." Worker From Master: $work\n";
            $this->parse($work);
        }
    }

    public function produce(\swoole_process $worker)
    {
        $this->worker = $worker;
        $this->input();
        $worker->exit(0);
    }

    public function push($work){
        $this->worker->push($work);
    }

    /**
     * @param bool $FREE_QUEUE
     * @return $this
     */
    public function setFREEQUEUE($FREE_QUEUE)
    {
        $this->FREE_QUEUE = $FREE_QUEUE;
        return $this;
    }

    /**
     * @param int $PROCESS_NUM
     * @return $this
     */
    public function setPROCESSNUM($PROCESS_NUM)
    {
        $this->PROCESS_NUM = $PROCESS_NUM;
        return $this;
    }

    /**
     * @param int $THREAD_NUM
     * @return $this
     */
    public function setTHREADNUM($THREAD_NUM)
    {
        $this->THREAD_NUM = $THREAD_NUM;
        return $this;
    }

    /**
     * @param int $QUEUE_KEY
     * @return $this
     */
    public function setQUEUEKEY($QUEUE_KEY)
    {
        $this->QUEUE_KEY = $QUEUE_KEY;
        return $this;
    }

    /**
     * 超时时间设置为0，则脚本不自动结束，一直等待任务
     * @param int $TIMEOUT
     * @return $this
     */
    public function setTIMEOUT($TIMEOUT)
    {
        $this->TIMEOUT = $TIMEOUT;
        return $this;
    }


}