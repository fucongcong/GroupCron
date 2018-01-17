<?php

namespace Group\Cron;

use Group\Cron\ParseCrontab;
use Group\App\App;
use swoole_process;
use swoole_table;

class Cron
{
    protected $cacheDir;

    /**
     * 定时器轮询周期，精确到毫秒
     *
     */
    protected $tickTime;

    protected $argv;

    protected $loader;

    protected $jobs;

    protected $workerNum;

    protected $workers;

    protected $logDir;

    protected $table;

    protected $max_handle;

    protected $daemon = false;

    protected $help = "
\033[34m
 ----------------------------------------------------------

     -----        ----      ----      |     |   / ----
    /          | /        |      |    |     |   |      |
    |          |          |      |    |     |   | ----/
    |   ----   |          |      |    |     |   |
     -----|    |            ----       ----     |

 ----------------------------------------------------------
\033[0m
\033[31m 使用帮助: \033[0m
\033[33m Usage: app/cron [start|restart|stop|status|exec (cron name)|rejob (cron name)]|server \033[0m
";
    /**
     * 初始化环境
     *
     */
    public function __construct($argv, $loader)
    {
        $this->cacheDir = \Config::get('cron::cache_dir') ? : 'runtime/cron';
        $this->tickTime = \Config::get('cron::tick_time') ? : 2;
        $this->argv = $argv;
        $this->loader = $loader;
        $this->jobs = \Config::get('cron::job');
        $this->workerNum = count($this->jobs);
        $this->logDir = \Config::get("cron::log_dir");
        $this->daemon = \Config::get("cron::daemon") ? : false;
        $this->max_handle = \Config::get("cron::max_handle") ? : 10;
        \Log::$cacheDir = $this->logDir;
    }

    /**
     * 执行cron任务
     *
     */
    public function run()
    {
        $this->checkArgv();
    }

    public function start()
    {   
        $this->checkStatus();
        \Log::info("定时服务启动", [], 'cron');

        if(file_exists($this->logDir."/work_ids")) unlink($this->logDir."/work_ids");
        
        //将主进程设置为守护进程
        if ($this->daemon) swoole_process::daemon(true);

        //启动N个work工作进程
        $this->startWorkers();

        //设置信号
        $this->setSignal();

        swoole_timer_tick($this->tickTime * 1000, function($timerId) {
            foreach ($this->jobs as $key => $job) {
                $worker = $this->table->get($job['name'].'_worker');
                $worker = json_decode($worker[$job['name'].'_worker'], true);

                if (isset($worker['nextTime'])) continue;

                if (empty($worker)) {
                    $this->newProcess($key);
                }

                $this->workers[$job['name']]['process']->write(json_encode($this->workers[$job['name']]['job']));
            }
        });

        $this->setPid();
    }

    public function status()
    {
        if (!$this->getPid()) {
            exit("cron服务未启动\n");
        }

         exit("请执行app/cron server 查看当前cron信息\n");
    }

    public function restart()
    {
        $this->stop();
        sleep(1);
        $this->start();
    }

    /**
     * 将上一个进程杀死，并清除cron
     *
     */
    public function stop()
    {
        $pid = $this->getPid();

        if (!empty($pid) && $pid) {
            if (swoole_process::kill($pid, 0)) {
                //杀掉worker进程
                foreach ($this->jobs as $job) {
                    $work_id = \FileCache::get('work_id', $this->cacheDir."/".$job['name']);
                    if (is_array($work_id)) {
                        //向子进程发送退出命令,结束完当前任务后退出
                        try {
                            swoole_process::kill($work_id[0], SIGTERM);
                        } catch (Exception $e) {
                            \Log::info("进程{$work_id}不存在", [], 'cron.stop');
                        }
                    }
                }
            }
        }
    }

    /**
     * 设置信号监听
     *
     */
    private function setSignal()
    {
        //子进程结束时主进程收到的信号
        swoole_process::signal(SIGCHLD, function ($signo) {
            //kill掉所有worker进程 必须为false，非阻塞模式
            static $worker_count = 0;
            while($ret = swoole_process::wait(false)) {
                $worker_count++;
                $workerNum = $this->table->get('workers_num');
                \Log::info("PID={$ret['pid']}worker进程退出!", [$workerNum, $signo], 'cron');

                if ($worker_count >= $workerNum['workers_num']){
                    \Log::info("主进程退出!", [], 'cron');
                    foreach ($this->jobs as $job) {
                        @unlink($this->logDir."/".$job['name']."/work_id");
                    }
                    
                    @unlink($this->logDir."/pid");
                    swoole_process::kill($this->getPid(), SIGKILL); 
                }
            }
        });
    }

    /**
     * 启动worker进程处理定时任务
     *
     */
    private function startWorkers()
    {
        $this->table = new swoole_table(1024);
        $this->table->column("workers_num", swoole_table::TYPE_INT);

        foreach ($this->jobs as $job) {
            $this->table->column($job['name'].'_worker', swoole_table::TYPE_STRING, 1024 * 20);
        }
        $this->table->create();
        $this->table->set('workers_num', ["workers_num" => 0]);
        $this->table->incr('workers_num', 'workers_num', $this->workerNum);

        //启动worker进程
        for ($i = 0; $i < $this->workerNum; $i++) {
            $this->newProcess($i);
        }
    }

    /**
     * 检查输入的参数与命令
     *
     */
    protected function checkArgv()
    {
        $argv = $this->argv;
        if (!isset($argv[1])) die($this->help);

        if (!in_array($argv[1], ['start', 'restart', 'stop', 'status', 'exec', 'rejob', 'server'])) die($this->help);

        $function = $argv[1];
        $this->$function();
    }

    public function server()
    {
        $server = new \Group\Cron\CronAdmin();
        $server->start();
    }

    public function exec()
    {   
        $this->init();
        
        $argv = $this->argv;
        $jobName = isset($argv[2]) ? $argv[2] :'';
        foreach ($this->jobs as $job) {
            if ($job['name'] == $jobName) {
                call_user_func_array([new $job['command'], 'handle'], []);
                exit("{$jobName}执行完成\n");
            }

            continue;
        }

        exit("job不存在\n");
    }

    public function rejob()
    {
        $argv = $this->argv;
        $jobName = isset($argv[2]) ? $argv[2] :'';
        foreach ($this->jobs as $job) {
            if ($job['name'] == $jobName) {
                $worker = \FileCache::get('cronAdmin', $this->cacheDir."/".$jobName);
                if (isset($worker[0])) {
                    $processPid = $worker[0]['pid'];
                    exec("kill -USR1 {$processPid}");
                    exit("{$jobName}重启完成\n");
                }
            }
            continue;
        }

        exit("job不存在\n");
    }

    public function workerCallBack(swoole_process $worker)
    {   
        $this->init();
        
        swoole_event_add($worker->pipe, function($pipe) use ($worker) { 
            $recv = $worker->read(); 
            $recv = json_decode($recv, true);
            if (!is_array($recv)) return;

            $this->bindTick($recv);
        });

        //接受重启的信号
        swoole_process::signal(SIGUSR1, function ($signo) use ($worker) {
            $pid = $worker->pid;
            foreach ($this->jobs as $job) {
                $workers = \FileCache::get('cronAdmin', $this->cacheDir."/".$job['name']);
                foreach ($workers as $worker) {
                    if ($worker['pid'] == $pid) {
                        $timerId = isset($worker['timerId']) ? $worker['timerId'] : 0;
                        $this->restartJob($worker['job']);
                    }
                }
            }
        });

        //接受退出的信号
        swoole_process::signal(SIGTERM, function ($signo) use ($worker) {
            $worker->exit();
        });
    }

    /**
     * 绑定cron job
     *
     */
    public function bindTick($job)
    {
        set_time_limit(0);

        $count = 0;
        while ($count <= $this->max_handle) {
            $timer = ParseCrontab::parse($job['time']);
            if (is_null($timer)) return;

            $job['timer'] = $timer;
            $this->jobStart($job);

            if (sleep($timer) > 0) {
               return; 
            }
            $count++;
        }

        $this->restartJob($job);
    }

    private function checkStatus()
    {
        if ($this->getPid()) {
            if (swoole_process::kill($this->getPid(), 0)) {
                exit('定时服务已启动！');
            }
        }
    }

    /**
     * 设置worker进程的pid
     *
     * @param pid int
     */
    private function setWorkerPid($pid, $jobName)
    {   
        $dir = $this->cacheDir."/".$jobName;
        \FileCache::set('work_id', [$pid], $dir);
    }

    /**
     * remove worker进程的pid
     *
     * @param pid int
     */
    private function removeWorkerPid($pid, $jobName)
    {   
        $dir = $this->cacheDir."/".$jobName;
        @unlink($this->cacheDir."/".$jobName."/work_id");
    }

    public function setPid()
    {
        $pid = posix_getpid();
        $parts = explode('/', $this->cacheDir."/pid");
        $file = array_pop($parts);
        $dir = '';
        foreach ($parts as $part) {
            if (!is_dir($dir .= "$part/")) {
                 mkdir($dir);
            }
        }
        file_put_contents("$dir/$file", $pid);
    }

    public function getPid()
    {
        if (file_exists($this->cacheDir."/pid"))
        return file_get_contents($this->cacheDir."/pid");
    }

    private function jobStart($job)
    {
        $worker = $this->table->get($job['name'].'_worker');
        $worker = json_decode($worker[$job['name'].'_worker'], true);
        $worker['startTime'] = date('Y-m-d H:i:s', time());
        $worker['timer'] = intval($job['timer']);
        $worker['nextTime'] = date('Y-m-d H:i:s', time() + intval($job['timer']));
        $this->table->set($job['name'].'_worker', [$job['name'].'_worker' => json_encode($worker)]);

        \FileCache::set('cronAdmin', [$worker], $this->cacheDir."/".$job['name']);
  
        \Log::info('定时任务启动'.$job['name'], [], 'cron.start');

        //先执行一次任务
        call_user_func_array([new $job['command'], 'handle'], []);
    }

    private function restartJob($job)
    {
        foreach ($this->jobs as $key => $one) {
            if ($one['name'] == $job['name']) {
                $this->table->set($job['name'].'_worker', [$job['name'].'_worker' => json_encode([])]);
                $this->table->incr('workers_num', 'workers_num');

                $this->removeWorkerPid($job['workId'], $job['name']);
                swoole_process::kill($job['workId'], SIGKILL);
                break;
            }
        }
    }

    private function newProcess($i)
    {
        $process = new swoole_process(array($this, 'workerCallBack'), false);
        $processPid = $process->start();

        $this->setWorkerPid($processPid, $this->jobs[$i]['name']);
        
        $this->jobs[$i]['workId'] = $processPid;
        $this->workers[$this->jobs[$i]['name']] = [
            'process' => $process,
            'job' => $this->jobs[$i],
        ];

        $worker = [
            'job' => $this->jobs[$i],
            'pid' => $processPid,
            'process' => $process,
            'startTime' => date('Y-m-d H:i:s', time()),
        ];

        $this->table->set($this->jobs[$i]['name'].'_worker', [$this->jobs[$i]['name'].'_worker' => json_encode($worker)]);

        \Log::info("工作worker{$processPid}启动", [$this->jobs[$i]['name']], 'cron.work');
    }

    private function init()
    {   
        if(function_exists("opcache_reset")) opcache_reset();
        
        $app = new \Group\App\App();
        $app->initSelf();
        $app->registerServices();
    }
}
