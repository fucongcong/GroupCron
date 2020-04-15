<?php
return [
    //false时,重启注意清除cache
    'daemon' => true,

    //设置时区
    'timezone' => "PRC",

    'cache_dir' => 'runtime',

    //log路径
    'log_dir' => 'runtime',

    #任务进程处理次数上限，超出后将会自动重启进程，释放内存
    'max_handle' => 5,

    //定时器轮询周期，精确到秒
    'tick_time' => 2,

    'job' => [
        [
            'name' => 'TestLog',//任务名
            'time' => '* * * * *',//定时规则 分 小时 天 月 周 
            'command' => 'src\Test',//执行的类库
        ],        [
            'name' => 'TestLog2',//任务名
            'time' => '* * * * *',//定时规则 分 小时 天 月 周 
            'command' => 'src\Test',//执行的类库
        ],

        [
            'name' => 'TestLog1',//任务名
            'time' => '*/2 */2 * * *',//定时规则 分 小时 天 月 周 
            'command' => 'src\Test',//执行的类库
        ],
        // [
        //     'name' => 'TestLog2',//任务名
        //     'time' => '*/2 */4 * * *',//定时规则 分 小时 天 月 周 
        //     'command' => 'src\TestCache',//执行的类库
        // ],

        // [
        //     'name' => 'TestLog4',//任务名
        //     'time' => '* * * * *',//定时规则 分 小时 天 月 周 
        //     'command' => 'src\TestSql',//执行的类库
        // ],

    ],
];