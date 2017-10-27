### Group-Cron php的秒级定时器

#### 依赖
- php > 5.6
- swoole > 1.7.16 

#### 安装依赖

    composer install

#### 快速开始
修改config/cron.php文件 ，向job中添加你的定时任务.

```php 
    'job' => [

        [
            'name' => 'TestLog',//任务名
            'time' => '* * * * *',//定时规则 分 小时 天 周 月
            'command' => 'src\Test',//执行的类库
        ],
    ],
```

#### 任务示例
请继承CronJob实现handle()方法
```php
    <?php

    namespace src;

    use Group\Cron\CronJob;

    class Test extends CronJob
    {
        public function handle()
        {
            \Log::info('nihao', ['time' => date('Y-m-d H:i:s', time())], 'cron.job');
        }

    } 
```

#### 启动任务

    app/cron start

#### 命令使用

    app/cron [start|restart|stop|status|exec (cron name)|rejob (cron name)]|server 