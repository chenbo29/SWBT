<?php
/**
 * Created by PhpStorm.
 * User: chenbo
 * Date: 18-5-29
 * Time: 上午9:45
 */

namespace SWBT\process;


use Pheanstalk\Pheanstalk;
use SWBT\Tubes;
use SWBT\Worker;

class TubesProcess
{
    private $tubes;
    private $container;
    private $logger;
    public $processInfo;
    public $processInfoWithPidKey;
    public function __construct($tubes, $container)
    {
        $this->tubes = $tubes;
        $this->container = $container;
        $this->logger = $container->logger;
    }

    public function start(){
        foreach ($this->tubes as $tube => $tubeInfo){
            $this->logger->info("Tube Starting ...", ['tube' => $tube]);
            $processInfo = $this->startProcess($tube);
            $this->logger->info("Tube Start Success", ['tube' => $tube, 'pid' => $processInfo['pid']]);
            $this->processInfo[] = $processInfo;
            $this->processInfoWithPidKey[$processInfo['pid']] = $processInfo;
        }
        $this->registerSignal();
        return ;
    }

    public function startProcess($tubeName){
        $processInfo['tube'] = $tubeName;
        $workerProcess = new \Swoole\Process(function ($process) use($processInfo) {
            swoole_set_process_name("SWBT {$processInfo['tube']} tube");
            $tubeWorker = new Worker($this->container, new Pheanstalk(getenv('beanstalkdHost')), $processInfo['tube']);
            $tubeWorker->run();
        });
        if (!$workerProcess->start()) $this->logger->error('Process Start Failed', ['tube' => $processInfo['tube'], 'swoole_errno'=>swoole_errno, 'swoole_strerror' => swoole_strerror]);
        $processInfo['pid'] = $workerProcess->pid;
        return $processInfo;
    }

    private function registerSignal()
    {
        $tubeProcesses = $this->processInfoWithPidKey;
        \Swoole\Process::signal(SIGCHLD, function () use ($tubeProcesses) {
            while ($ret = \Swoole\Process::wait(false)) {
                $this->logger->info("Worker Process Closed", ['pid'=>$ret['pid'],'tube'=>$tubeProcesses[$ret['pid']['tube']]]);
            }
        });
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }

    public function __get($name)
    {
        if (isset($this->$name)){
            return $this->$name;
        } else {
            return null;
        }
    }
}