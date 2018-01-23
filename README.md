### Group-Cron php实现的定时器

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
#### 定时规则
##### 指定时间
- \* 2 * * * 每天的2点执行
- */2 2 * * * 每天的2-3点，每2分钟执行一次（2点的00分、02分、04分...58分）
- 5 2 */3 * * 每3天的2点5分执行（每月的3号、6号、9号以此类推）

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
