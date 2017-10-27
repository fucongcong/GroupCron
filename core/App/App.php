<?php

namespace Group\App;

use Group\Handlers\AliasLoaderHandler;
use Group\Config\Config;

class App
{
    /**
     * array instances
     *
     */
    protected $instances;

    private static $instance;

    public $container;

    public $router;

    /**
     * array aliases
     *
     */
    protected $aliases = [
        'App'               => 'Group\App\App',
        'Config'            => 'Group\Config\Config',
        'FileCache'         => 'Group\Cache\FileCache',
        'Service'           => 'Group\Services\Service',
        'ServiceProvider'   => 'Group\Services\ServiceProvider',
        'Log'               => 'Group\Log\Log',
    ];

    /**
     * array singles
     *
     */
    protected $singles = [
    ];

    protected $serviceProviders = [
        'Group\Cache\FileCacheServiceProvider',
    ];

    public function __construct()
    { 
        $this->aliasLoader();

        $this->doSingle();

        //$this->doSingleInstance();
    }

    /**
     * do the class alias
     *
     */
    public function aliasLoader()
    {
        $aliases = Config::get('app::aliases');
        $this->aliases = array_merge($aliases, $this->aliases);
        AliasLoaderHandler::getInstance($this->aliases)->register();

    }

    /**
     *  向App存储一个单例对象
     *
     * @param  name，callable
     * @return object
     */
    public function singleton($name, $callable = null)
    {
        if (!isset($this->instances[$name]) && $callable) {
            $this->instances[$name] = call_user_func($callable);
        }

        return $this->instances[$name];
    }

    /**
     *  在网站初始化时就已经生成的单例对象
     *
     */
    public function doSingle()
    {   
        $singles = Config::get('app::singles');
        $this->singles = array_merge($singles, $this->singles);
        foreach ($this->singles as $alias => $class) {
            $this->instances[$alias] = new $class();
        }
    }

    public function doSingleInstance()
    {
        $this->instances['container'] = Container::getInstance();
    }

    /**
     *  注册服务
     *
     */
    public function registerServices()
    {   
        $this->setServiceProviders();

        $this->register();
    }

    /**
     * return single class
     *
     * @return core\App\App App
     */
    public static function getInstance()
    {
        if (!(self::$instance instanceof self)){
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function initSelf()
    {
        self::$instance = $this;
    }

    public function rmInstances($name)
    {
        if(isset($this->instances[$name]))
            unset($this->instances[$name]);
    }

    /**
     * set ServiceProviders
     *
     */
    public function setServiceProviders()
    {
        $providers = Config::get('app::serviceProviders');
        $this->serviceProviders = array_merge($providers, $this->serviceProviders);
    }

    /**
     * ingore ServiceProviders
     *
     */
    public function ingoreServiceProviders($provider)
    {   
        foreach ($this->serviceProviders as $key => $val) {
            if ($val == $provider) unset($this->serviceProviders[$key]);
        } 
    }

    /**
     *  注册服务
     *
     */
    public function register()
    {   
        foreach ($this->serviceProviders as $provider) {
            $provider = new $provider(self::$instance);
            $provider->register();
        }
    }

    /**
     * 处理一个抽象对象
     * @param  string  $abstract
     * @return mixed
     */
    public function make($abstract)
    {
        //如果是已经注册的单例对象
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $reflector = app('container')->buildMoudle($abstract);
        if (!$reflector->isInstantiable()) {
            throw new Exception("Target [$concrete] is not instantiable!");
        }

        //有单例
        if ($reflector->hasMethod('getInstance')) {
            $object = $abstract::getInstance();
            $this->instances[$abstract] = $object;
            return $object;
        }

        $constructor = $reflector->getConstructor();
        if (is_null($constructor)) {
            return new $abstract;
        }

        return null;
    }
}
