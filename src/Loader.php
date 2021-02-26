<?php

namespace PhpRemix\DependencyAutoload;

use PhpRemix\Foundation\Application;

class Loader
{
    /**
     * composer.lock
     *
     * @var string
     */
    private $composerLockFilename;

    /**
     * 解析好的composer.lock
     *
     * @var array
     */
    private $composer;

    /**
     * @var Application
     */
    private $app;

    /**
     * 运行依赖
     *
     * @var array
     */
    private $run = [];

    /**
     * 销毁依赖
     *
     * @var array
     */
    private $terminated = [];

    /**
     * 注入依赖
     *
     * @var array
     */
    private $di = [];

    /**
     * 缓存目录
     *
     * @var mixed|null
     */
    private $cacheDir;

    public function __construct(Application $app, $composerLockFilename = null, $cacheDir = null)
    {
        $this->composerLockFilename = $composerLockFilename;
        $this->app = $app;
        $this->cacheDir = $cacheDir;
    }

    /**
     * 解析composer.lock
     */
    public function parseComposerLock()
    {
        $this->composer = json_decode(file_get_contents($this->composerLockFilename), true);
    }

    /**
     * 扫描extra字段
     */
    public function scanExtra()
    {
        foreach ($this->composer['packages'] as $package) {
            if (empty($package['extra']['php-remix'])) continue;

            $data = $package['extra']['php-remix'];

            if (!empty($data['run'])) {
                $this->run[] = $data['run'];
            }

            if (!empty($data['terminated'])) {
                $this->run[] = $data['terminated'];
            }

            if (!empty($data['di'])) { // 这个才是真正重要的
                /**
                 * DI选项，只能指定文件名或[class,method]
                 * 例如：
                 * config.php
                 * ["PhpRemix\\Config\\Config","di"]
                 * 其中文件名会自动定位到依赖所在目录，文件需返回数组
                 * 而[class,method]仅支持静态方法，且返回数组
                 * 数组形式为di的Definitions
                 */
                $vendorPath = $this->app->getBasePath() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
                $packagePath = $vendorPath . $package['name'] . DIRECTORY_SEPARATOR;
                $this->di[] = is_callable($data['di']) ? $this->app->call($data['di']) : $packagePath . $data['di'];
            }
        }
    }

    /**
     * 将依赖加载到app
     */
    public function loadToApp()
    {
        foreach ($this->run as $run) {
            ['name' => $name, 'method' => $method] = $run;
            $this->app->addRun(['type' => 'DI', 'name' => $name, 'method' => $method]);
        }
        foreach ($this->terminated as $terminated) {
            ['name' => $name, 'method' => $method] = $terminated;
            $this->app->addTerminated(['type' => 'DI', 'name' => $name, 'method' => $method]);
        }
        foreach ($this->di as $di) {
            $this->app->addDefinitions($di); // 新的加载方式
        }
    }

    /**
     * 进行缓存
     */
    public function cache()
    {
        if (is_null($this->cacheDir)) return;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir);
        }

        $runCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'run.php';
        $terminatedCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'terminated.php';
        $dependencyFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'dependency.php';

        file_put_contents($runCacheFile, "<?php\n" . var_export($this->run, true) . ";");
        file_put_contents($terminatedCacheFile, "<?php\n" . var_export($this->terminated, true) . ";");
        file_put_contents($dependencyFile, "<?php\n" . var_export($this->di, true) . ";");
    }

    /**
     * 检查缓存
     *
     * @return array
     */
    public function cacheCheck()
    {
        $runCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'run.php';
        $terminatedCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'terminated.php';
        $dependencyFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'dependency.php';

        return [
            'run' => file_exists($runCacheFile),
            'terminated' => file_exists($terminatedCacheFile),
            'dependency' => file_exists($dependencyFile)
        ];
    }

    /**
     * 加载缓存
     *
     * @param bool $run
     * @param bool $terminated
     * @param bool $dependency
     */
    public function loadCache(bool $run, bool $terminated, bool $dependency)
    {
        $runCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'run.php';
        $terminatedCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'terminated.php';
        $dependencyFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'dependency.php';

        if ($run && file_exists($runCacheFile)) $this->run = include $runCacheFile;
        if ($terminated && file_exists($terminatedCacheFile)) $this->terminated = include $terminatedCacheFile;
        if ($dependency && file_exists($dependencyFile)) $this->di = include $dependencyFile;
    }
}