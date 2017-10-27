<?php

namespace Group\Cron;

use swoole_http_server;

class CronAdmin
{   
    protected $http;

    public function __construct()
    {
        $http = new swoole_http_server('0.0.0.0', '10008');
        $http->set(array(
            'reactor_num' => 1,
            'worker_num' => 2,    //worker process num
            'backlog' => 128,   //listen backlog
            'max_request' => 500,
            'heartbeat_idle_time' => 30,
            'heartbeat_check_interval' => 10,
            'dispatch_mode' => 3, 
        ));

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

            $cacheDir = \Config::get('cron::cache_dir') ? : 'runtime/cron';
            $jobs = \Config::get('cron::job');
            $pid = \FileCache::get('pid', $cacheDir) ? : 0;

            $works = [];
            foreach ($jobs as $job) {
                $cronAdmin = \FileCache::get('cronAdmin', $cacheDir."/".$job['name']);
                $work_id = \FileCache::get('work_id', $cacheDir."/".$job['name']);
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

    private function twigInit()
    {
        $loader = new \Twig_Loader_Filesystem(dirname(__FILE__)."/View");
        $twig = new \Twig_Environment($loader, isset($env) ? $env : array());

        return $twig;
    }
}
