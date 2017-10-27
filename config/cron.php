<?php
return [
    //false时,重启注意清除cache
    'daemon' => true,

    'cache_dir' => 'runtime',

    //log路径
    'log_dir' => 'runtime',

    //定时器轮询周期，精确到毫秒
    'tick_time' => 1000,

    'job' => [

        [
            'name' => 'TestLog',//任务名
            'time' => '* * * * *',//定时规则 分 小时 天 周 月
            'command' => 'src\Test',//执行的类库
        ],

        [
            'name' => 'TestLog1',//任务名
            'time' => '*/2 */2 * * *',//定时规则 分 小时 天 周 月
            'command' => 'src\Test',//执行的类库
        ],
        // [
        //     'name' => 'TestLog2',//任务名
        //     'time' => '*/2 */4 * * *',//定时规则 分 小时 天 周 月
        //     'command' => 'src\TestCache',//执行的类库
        // ],

        // [
        //     'name' => 'TestLog4',//任务名
        //     'time' => '* * * * *',//定时规则 分 小时 天 周 月
        //     'command' => 'src\TestSql',//执行的类库
        // ],

    ],
];