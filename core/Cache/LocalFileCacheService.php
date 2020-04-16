<?php

namespace Group\Cache;

class LocalFileCacheService
{
    protected static $cacheDir = "runtime/cache";

    /**
     * 获取cache
     *
     * @param  cacheName,  name::key
     * @param  cacheDir
     * @return string|array
     */
    public function get($cacheName, $cacheDir = false)
    {
        $cacheDir = $cacheDir == false ? self::$cacheDir : $cacheDir;
        $dir = __FILEROOT__.$cacheDir."/".$cacheName;

        if ($this->isExist($cacheName, $cacheDir)) {
            $data = file_get_contents($dir);
            if ($data) {
                return json_decode($data, true);
            }
        }
        return null;
    }

    /**
     * 设置cache
     *
     * @param  cacheName(string)
     * @param  data(array)
     * @param  cacheDir(string)
     */
    public function set($cacheName, $data, $cacheDir = false, $flag = false)
    {
        $cacheDir = $cacheDir == false ? self::$cacheDir : $cacheDir;
        $dir = __FILEROOT__.$cacheDir."/".$cacheName;

        $data = json_encode($data);

        $parts = explode('/', $dir);
        $file = array_pop($parts);
        $dir = '';
        foreach ($parts as $part) {
            if (!is_dir($dir .= "$part/")) {
                 mkdir($dir);
            }
        }

        file_put_contents("$dir/$file", $data, $flag);
    }
    /**
     * 文件是否存在
     *
     * @param  cacheName(string)
     * @param  cacheDir(string)
     * @return boolean
     */
    public function isExist($cacheName, $cacheDir = false)
    {
        $cacheDir = $cacheDir == false ? self::$cacheDir : $cacheDir;
        $dir = __FILEROOT__.$cacheDir."/".$cacheName;

        return file_exists($dir);

    }

    public function remove($cacheName, $cacheDir = false)
    {
        $cacheDir = $cacheDir == false ? self::$cacheDir : $cacheDir;
        $dir = __FILEROOT__.$cacheDir."/".$cacheName;

        return @unlink($dir);
    }
}
