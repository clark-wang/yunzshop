<?php

namespace app\common\services;

use app\common\events;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use app\common\events\PluginWasUninstalled;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Events\Dispatcher;
use app\common\repositories\OptionRepository;
use Illuminate\Contracts\Foundation\Application;

class PluginManager
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var OptionRepository
     */
    protected $option;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var Collection|null
     */
    protected $plugins;

    public function __construct(
        Application $app,
        OptionRepository $option,
        Dispatcher $dispatcher,
        Filesystem $filesystem
    ) {
        $this->app        = $app;
        $this->option     = $option;
        $this->dispatcher = $dispatcher;
        $this->filesystem = $filesystem;
    }

    /**
     * @return Collection
     */
    public function getPlugins()
    {
        if (is_null($this->plugins)) {
            $plugins = new Collection();

            $installed = [];

            $resource = opendir(base_path('plugins'));
            // traverse plugins dir
            while($filename = @readdir($resource)) {
                if ($filename == "." || $filename == "..")
                    continue;

                $path = base_path('plugins')."/".$filename;

                if (is_dir($path)) {
                    if (file_exists($path."/package.json")) {
                        // load packages installed
                        $installed[$filename] = json_decode($this->filesystem->get($path."/package.json"), true);
                    }
                }

            }
            closedir($resource);

            foreach ($installed as $path => $package) {

                // Instantiates an Plugin object using the package path and package.json file.
                $plugin = new Plugin($this->getPluginsDir().'/'.$path, $package);

                // Per default all plugins are installed if they are registered in composer.
                $plugin->setDirname($path);
                $plugin->setInstalled(true);
                $plugin->setNameSpace(Arr::get($package, 'namespace'));
                $plugin->setVersion(Arr::get($package, 'version'));
                $plugin->setEnabled($this->isEnabled($plugin->name));

                $plugins->put($plugin->name, $plugin);
            }

            $this->plugins = $plugins->sortBy(function ($plugin, $name) {
                return $plugin->name;
            });
        }

        return $this->plugins;
    }

    /**
     * Loads an Plugin with all information.
     *
     * @param string $name
     * @return Plugin|null
     */
    public function getPlugin($name)
    {
        return $this->getPlugins()->get($name);
    }

    /**
     * Enables the plugin.
     *
     * @param string $name
     */
    public function enable($name)
    {
        if (! $this->isEnabled($name)) {
            $plugin = $this->getPlugin($name);

            $enabled = $this->getEnabled();

            $enabled[] = $name;

            $this->setEnabled($enabled);

            $plugin->setEnabled(true);

            $this->dispatcher->fire(new events\PluginWasEnabled($plugin));
        }
    }

    /**
     * Disables an plugin.
     *
     * @param string $name
     */
    public function disable($name)
    {
        $enabled = $this->getEnabled();

        if (($k = array_search($name, $enabled)) !== false) {
            unset($enabled[$k]);

            $plugin = $this->getPlugin($name);

            $this->setEnabled($enabled);

            $plugin->setEnabled(false);

            $this->dispatcher->fire(new events\PluginWasDisabled($plugin));
        }
    }

    /**
     * Uninstalls an plugin.
     *
     * @param string $name
     */
    public function uninstall($name)
    {
        $plugin = $this->getPlugin($name);

        $this->disable($name);

        // fire event before deleeting plugin files
        $this->dispatcher->fire(new events\PluginWasDeleted($plugin));

        $this->filesystem->deleteDirectory($plugin->getPath());

        // refresh plugin list
        $this->plugins = null;
    }

    /**
     * Get only enabled plugins.
     *
     * @return Collection
     */
    public function getEnabledPlugins()
    {
        return $this->getPlugins()->only($this->getEnabled());
    }

    /**
     * Loads all bootstrap.php files of the enabled plugins.
     *
     * @return Collection
     */
    public function getEnabledBootstrappers()
    {
        $bootstrappers = new Collection;

        foreach ($this->getEnabledPlugins() as $plugin) {
            if ($this->filesystem->exists($file = $plugin->getPath().'/bootstrap.php')) {
                $bootstrappers->push($file);
            }
        }

        return $bootstrappers;
    }

    /**
     * The id's of the enabled plugins.
     *
     * @return array
     */
    public function getEnabled()
    {
        return (array) json_decode($this->option->get('plugins_enabled'), true);
    }

    /**
     * Persist the currently enabled plugins.
     *
     * @param array $enabled
     */
    protected function setEnabled(array $enabled)
    {
        $enabled = array_values(array_unique($enabled));

        $this->option->set('plugins_enabled', json_encode($enabled));
 
        // ensure to save options
        $this->option->save();
    }

    /**
     * Whether the plugin is enabled.
     *
     * @param $plugin
     * @return bool
     */
    public function isEnabled($plugin)
    {
        return in_array($plugin, $this->getEnabled());
    }

    /**
     * The plugins path.
     *
     * @return string
     */
    protected function getPluginsDir()
    {
        return $this->app->basePath().'/plugins';
    }

}
