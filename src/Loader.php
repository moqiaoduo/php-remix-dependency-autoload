<?php

namespace PhpRemix\DependencyAutoload;

use PhpRemix\Foundation\Application;

class Loader
{
    /**
     * @var string
     */
    private $composerLockFilename;

    /**
     * @var array
     */
    private $composer;

    /**
     * @var Application
     */
    private $app;

    private $run = [];

    private $terminated = [];

    private $cacheDir;

    public function __construct(Application $app, $composerLockFilename, $cacheDir = null)
    {
        $this->composerLockFilename = $composerLockFilename;
        $this->app = $app;
        $this->cacheDir = $cacheDir;
    }

    public function parseComposerLock()
    {
        $this->composer = json_decode(file_get_contents($this->composerLockFilename), true);
    }

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
        }
    }

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
    }

    public function cache()
    {
        if (is_null($this->cacheDir)) return;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir);
        }

        $runCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'run.php';
        $terminatedCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'terminated.php';

        file_put_contents($runCacheFile, "<?php\n" . var_export($this->run, true) . ";");
        file_put_contents($terminatedCacheFile, "<?php\n" . var_export($this->terminated, true) . ";");
    }

    public function cacheCheck()
    {
        $runCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'run.php';
        $terminatedCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'terminated.php';

        return ['run' => file_exists($runCacheFile), 'terminated' => file_exists($terminatedCacheFile)];
    }

    public function loadCache($run, $terminated)
    {
        $runCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'run.php';
        $terminatedCacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'terminated.php';

        if ($run) $this->run = include $runCacheFile;
        if ($terminated) $this->terminated = include $terminatedCacheFile;
    }
}