<?php

namespace Group\Cron;

use swoole_http_server;
use swoole_process;

class CronAdmin
{   
    protected $http;

    protected $jobs;

    protected $pidPath = __FILEROOT__.'runtime/cron-server/pid';

    public function __construct()
    {   
        $this->mkDir(__FILEROOT__.'runtime/cron-server');
        $http = new swoole_http_server('0.0.0.0', '10008');
        $http->set(array(
            'daemonize' => true,
            'log_file' => __FILEROOT__.'runtime/cron-server/app.log',
            'reactor_num' => 1,
            'worker_num' => 2,    //worker process num
            'backlog' => 128,   //listen backlog
            'max_request' => 500,
            'heartbeat_idle_time' => 30,
            'heartbeat_check_interval' => 10,
            'dispatch_mode' => 3, 
        ));

        $http->on('Start', [$this, 'onStart']);
        $http->on('WorkerStart', [$this, 'onWorkerStart']);
        $http->on('shutdown', [$this, 'onShutdown']);

        $http->on('request', function ($request, $response){
            $request->get = isset($request->get) ? $request->get : [];
            $request->post = isset($request->post) ? $request->post : [];
            $request->cookie = isset($request->cookie) ? $request->cookie : [];
            $request->files = isset($request->files) ? $request->files : [];
            $request->server = isset($request->server) ? $request->server : [];
   
            if ($request->server['request_uri'] == '/favicon.ico') {
                $response->end();
                return;
            }
            
            if ($request->post) {
                $post = $request->post;
                $action = $post['action'];
                $this->$action($post);
        
                $response->status(200);
                $response->end(json_encode(['status' => 'success', 'code' => 200]));
                return;
            }

            $cacheDir = \Config::get('cron::cache_dir') ? : 'runtime';
            $pid = $this->get('pid', $cacheDir) ? : 0;

            $works = [];
            foreach ($this->jobs as $job) {
                $cronAdmin = $this->get('cronAdmin', $cacheDir."/".$job['name']);
                $work_id = $this->get('work_id', $cacheDir."/".$job['name']);
                if (is_array($cronAdmin) && is_array($work_id)) {
                    $work = $cronAdmin[0];
                    if ($work['pid'] == $work_id[0]) {
                        $works[] = $work;
                    }
                }
            }

            $output = $this->twigInit()->render('console.html.twig', [
                'pid' => $pid,
                'works' => $works,
                ]);
            $response->status(200);
            $response->end($output);
            return;
        });
        
        $this->http = $http;
    }

    public function start()
    {
        $this->http->start();
    }

    /**
     * 服务启动回调事件
     * @param  【swoole_http_server】 $serv
     */
    public function onStart($serv)
    {
        if (PHP_OS !== 'Darwin') {
            swoole_set_process_name("php http server: master");
        }

        echo "HTTP Server Start...".PHP_EOL;

        $pid = $serv->master_pid;
        $this->mkDir($this->pidPath);
        file_put_contents($this->pidPath, $pid);
    }

    public function startMaster($post)
    {
        $path = __ROOT__;
        exec("cd {$path} && app/cron start > /dev/null &");
        \Log::info("cd {$path} && app/cron start > /dev/null &", ['startMaster']);
    }

    public function restartMaster($post)
    {
        $path = __ROOT__;
        exec("cd {$path} && app/cron restart > /dev/null &");
        \Log::info("cd {$path} && app/cron restart > /dev/null &", ['restartMaster']);
        $this->reloadServer($post);
    }

    public function stopMaster($post)
    {
        $path = __ROOT__;
        exec("cd {$path} && app/cron stop > /dev/null &");
        \Log::info("cd {$path} && app/cron stop > /dev/null &", ['stopMaster']);
    }

    public function execWorker($post)
    {
        $path = __ROOT__;
        $jobName = $post['jobName'];
        exec("cd {$path} && app/cron exec {$jobName} > /dev/null &");
        \Log::info("cd {$path} && app/cron exec {$jobName} > /dev/null &", ['execWorker']);
    }

    public function rejobWorker($post)
    {
        $path = __ROOT__;
        $jobName = $post['jobName'];
        exec("cd {$path} && app/cron rejob {$jobName} > /dev/null &");
        \Log::info("cd {$path} && app/cron rejob {$jobName} > /dev/null &", ['rejobWorker']);
    }

    public function reloadServer($post)
    {
        $pid = file_get_contents($this->pidPath);
        echo "当前进程".$pid.PHP_EOL;
        echo "热重启中".PHP_EOL;
        if ($pid) {
            if (swoole_process::kill($pid, 0)) {
                swoole_process::kill($pid, SIGUSR1);
            }
        }
        echo "重启完成".PHP_EOL;
        swoole_process::daemon(true);
    }

    /**
     * 服务关闭回调事件
     * @param  【swoole_http_server】 $serv
     */
    public function onShutdown($serv)
    {   
        @unlink($this->pidPath);
        echo "HTTP Server Shutdown...".PHP_EOL;
    }

    /**
     * worker启动回调事件
     * @param  【swoole_http_server】 $serv
     * @param  【int】 $workerId
     */
    public function onWorkerStart($serv, $workerId)
    {   
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        //设置不同进程名字,方便grep管理
        if (PHP_OS !== 'Darwin') {
            swoole_set_process_name("php http server: worker");
        }

        \Config::clear();
        $this->jobs = \Config::get('cron::job');

        echo "HTTP Worker Start...".PHP_EOL;
    }

    private function get($cacheName, $cacheDir)
    {
        $dir = __FILEROOT__.$cacheDir."/".$cacheName;

        if (file_exists($dir)) {
            $data = file_get_contents($dir);
            if ($data) {
                return json_decode($data, true);
            }
        }
        return null;
    }

    private function twigInit()
    {
        $loader = new \Twig_Loader_Filesystem(dirname(__FILE__)."/View");
        $twig = new \Twig_Environment($loader, isset($env) ? $env : array());

        return $twig;
    }

    /**
     * 新建目录
     * @param  [string] $dir
     */
    private function mkDir($dir)
    {
        $parts = explode('/', $dir);
        $file = array_pop($parts);
        $dir = '';
        foreach ($parts as $part) {
            if (!is_dir($dir .= "$part/")) {
                 mkdir($dir);
            }
        }
    }
}
