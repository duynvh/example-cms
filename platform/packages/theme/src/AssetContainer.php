<?php

namespace Botble\Theme;

use App;
use Config;
use Exception;
use File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AssetContainer
{

    /**
     * Use a theme path.
     *
     * @var boolean
     */
    public $usePath = false;

    /**
     * Path to theme.
     *
     * @var string
     */
    public $path;

    /**
     * The asset container name.
     *
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $assets = [];

    /**
     * Create a new asset container instance.
     *
     * @param  string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Root asset path.
     *
     * @param  string $uri
     * @param  boolean $secure
     * @return string
     */
    public function originUrl($uri, $secure = null)
    {
        return $this->configAssetUrl($uri, $secure);
    }

    /**
     * Generate a URL to an application asset.
     *
     * @param  string $path
     * @param  bool $secure
     * @return string
     */
    protected function configAssetUrl($path, $secure = null)
    {
        static $assetUrl;

        // Remove this.
        $index = 'index.php';

        if (URL::isValidUrl($path)) {
            return $path;
        }

        // Finding asset url config.
        if (empty($assetUrl)) {
            $assetUrl = Config::get('packages.theme.general.assetUrl', '');
        }

        // Using asset url, if available.
        if ($assetUrl) {
            $base = rtrim($assetUrl, '/');

            // Asset URL without index.
            $basePath = Str::contains($base, $index) ? str_replace('/' . $index, '', $base) : $base;
        } else {
            if (empty($secure)) {
                $scheme = Request::getScheme() . '://';
            } else {
                $scheme = $secure ? 'https://' : 'http://';
            }

            // Get root URL.
            $root = Request::root();
            $start = Str::startsWith($root, 'http://') ? 'http://' : 'https://';
            $root = preg_replace('~' . $start . '~', $scheme, $root, 1);

            // Asset URL without index.
            $basePath = Str::contains($root, $index) ? str_replace('/' . $index, '', $root) : $root;
        }

        return $basePath . '/' . $path;
    }

    /**
     * Return asset path with current theme path.
     *
     * @param  string $uri
     * @param  boolean $secure
     * @return string
     */
    public function url($uri, $secure = null)
    {
        // If path is full, so we just return.
        if (preg_match('#^http|//:#', $uri)) {
            return $uri;
        }

        $path = $this->getCurrentPath() . $uri;

        return $this->configAssetUrl($path, $secure);
    }

    /**
     * Get path from asset.
     *
     * @return string
     */
    public function getCurrentPath()
    {
        return Asset::$path;
    }

    /**
     * Alias add an asset to container.
     *
     * @param string $name
     * @param string $source
     * @param array $dependencies
     * @param array $attributes
     */
    public function add($name, $source, $dependencies = [], $attributes = [])
    {
        $this->added($name, $source, $dependencies, $attributes);
        return $this;
    }

    /**
     * Add an asset to the container.
     *
     * The extension of the asset source will be used to determine the type of
     * asset being registered (CSS or JavaScript). When using a non-standard
     * extension, the style/script methods may be used to register assets.
     *
     * <code>
     *      // Add an asset to the container
     *      Asset::container()->add('jquery', 'js/jquery.js');
     *
     *      // Add an asset that has dependencies on other assets
     *      Asset::add('jquery', 'js/jquery.js', 'jquery-ui');
     *
     *      // Add an asset that should have attributes applied to its tags
     *      Asset::add('jquery', 'js/jquery.js', null, ['defer']);
     * </code>
     *
     * @param  string $name
     * @param  string $source
     * @param  array $dependencies
     * @param  array $attributes
     * @return AssetContainer
     */
    protected function added($name, $source, $dependencies = [], $attributes = [])
    {
        if (is_array($source)) {
            foreach ($source as $path) {
                $name = $name . '-' . md5($path);

                $this->added($name, $path, $dependencies, $attributes);
            }
        } else {
            $type = File::extension($source) == 'css' ? 'style' : 'script';

            // Remove unnecessary slashes from internal path.
            if (!preg_match('|^//|', $source)) {
                $source = ltrim($source, '/');
            }

            return $this->$type($name, $source, $dependencies, $attributes);
        }
        return $this;
    }

    /**
     * Write a script to the container.
     *
     * @param  string $name
     * @param  string string
     * @param  string $source
     * @param  array $dependencies
     * @return AssetContainer
     */
    public function writeScript($name, $source, $dependencies = [])
    {
        $source = '<script>' . $source . '</script>';

        return $this->write($name, 'script', $source, $dependencies);
    }

    /**
     * Write a content to the container.
     *
     * @param  string $name
     * @param  string string
     * @param  string $source
     * @param  array $dependencies
     * @return AssetContainer
     */
    protected function write($name, $type, $source, $dependencies = [])
    {
        $types = [
            'script' => 'script',
            'style'  => 'style',
            'js'     => 'script',
            'css'    => 'style',
        ];

        if (array_key_exists($type, $types)) {
            $type = $types[$type];

            $this->register($type, $name, $source, $dependencies, []);
        }

        return $this;
    }

    /**
     * Add an asset to the array of registered assets.
     *
     * @param  string $type
     * @param  string $name
     * @param  string $source
     * @param  array $dependencies
     * @param  array $attributes
     * @return void
     */
    protected function register($type, $name, $source, $dependencies, $attributes)
    {
        $dependencies = (array)$dependencies;

        $attributes = (array)$attributes;

        $this->assets[$type][$name] = compact('source', 'dependencies', 'attributes');
    }

    /**
     * Write a style to the container.
     *
     * @param  string $name
     * @param  string string
     * @param  string $source
     * @param  array $dependencies
     * @return AssetContainer
     */
    public function writeStyle($name, $source, $dependencies = [])
    {
        $source = '<style>' . $source . '</style>';

        return $this->write($name, 'style', $source, $dependencies);
    }

    /**
     * Write a content without tag wrapper.
     *
     * @param  string $name
     * @param  string string
     * @param  string $source
     * @param  array $dependencies
     * @return AssetContainer
     */
    public function writeContent($name, $source, $dependencies = [])
    {
        return $this->write($name, 'script', $source, $dependencies);
    }

    /**
     * Add a CSS file to the registered assets.
     *
     * @param  string $name
     * @param  string $source
     * @param  array $dependencies
     * @param  array $attributes
     * @return AssetContainer
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function style($name, $source, $dependencies = [], $attributes = [])
    {
        if (!array_key_exists('media', $attributes)) {
            $attributes['media'] = 'all';
        }

        // Prepend path to theme.
        if ($this->isUsePath()) {
            $source = $this->evaluatePath($this->getCurrentPath() . $source);

            // Reset using path.
            $this->usePath(false);
        }

        $this->register('style', $name, $source, $dependencies, $attributes);

        return $this;
    }

    /**
     * Check using theme path.
     *
     * @return boolean
     */
    public function isUsePath()
    {
        return (boolean)$this->usePath;
    }

    /**
     * Evaluate path to current theme or force use theme.
     *
     * @param  string $source
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function evaluatePath($source)
    {
        static $theme;

        // Make theme to use few features.
        if (!$theme) {
            $theme = App::make('theme');
        }

        // Switch path to another theme.
        if (!is_bool($this->usePath) and $theme->exists($this->usePath)) {
            $currentTheme = $theme->getThemeName();

            $source = str_replace($currentTheme, $this->usePath, $source);
        }

        return $source;
    }

    /**
     * Force use a theme path.
     *
     * @param  boolean $use
     * @return AssetContainer
     */
    public function usePath($use = true)
    {
        $this->usePath = $use;

        return $this;
    }

    /**
     * Add a JavaScript file to the registered assets.
     *
     * @param  string $name
     * @param  string $source
     * @param  array $dependencies
     * @param  array $attributes
     * @return AssetContainer
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function script($name, $source, $dependencies = [], $attributes = [])
    {
        // Prepend path to theme.
        if ($this->isUsePath()) {
            $source = $this->evaluatePath($this->getCurrentPath() . $source);

            // Reset using path.
            $this->usePath(false);
        }

        $this->register('script', $name, $source, $dependencies, $attributes);

        return $this;
    }

    /**
     * Get the links to all of the registered CSS assets.
     *
     * @return  string
     * @throws Exception
     */
    public function styles()
    {
        return $this->group('style');
    }

    /**
     * Get all of the registered assets for a given type / group.
     *
     * @param  string $group
     * @return string
     * @throws Exception
     */
    protected function group($group)
    {
        if (!isset($this->assets[$group]) || count($this->assets[$group]) == 0) {
            return '';
        }

        $assets = '';

        foreach (array_keys($this->arrange($this->assets[$group])) as $name) {
            $assets .= $this->asset($group, $name);
        }

        return $assets;
    }

    /**
     * @param $group
     * @throws Exception
     */
    public function getAssets($group)
    {
        if (!isset($this->assets[$group])) {
            return [];
        }
        $assets = [];
        foreach (array_keys($this->arrange($this->assets[$group])) as $name) {
            $assets[] = $this->assetUrl($group, $name);
        }

        return $assets;
    }

    /**
     * @param $group
     * @param $name
     * @return string
     */
    protected function assetUrl($group, $name)
    {
        if (!isset($this->assets[$group][$name])) {
            return '';
        }

        $asset = $this->assets[$group][$name];

        // If the bundle source is not a complete URL, we will go ahead and prepend
        // the bundle's asset path to the source provided with the asset. This will
        // ensure that we attach the correct path to the asset.
        if (filter_var($asset['source'], FILTER_VALIDATE_URL) === false) {
            $asset['source'] = $this->path($asset['source']);
        }

        // If source is not a path to asset, render without wrap a HTML.
        if (strpos($asset['source'], '<') !== false) {
            return $asset['source'];
        }

        return $this->configAssetUrl($asset['source']);
    }

    /**
     * Sort and retrieve assets based on their dependencies
     *
     * @param   array $assets
     * @return  array
     * @throws Exception
     */
    protected function arrange($assets)
    {
        list($original, $sorted) = [$assets, []];

        while (count($assets) > 0) {
            foreach ($assets as $asset => $value) {
                $this->evaluateAsset($asset, $value, $original, $sorted, $assets);
            }
        }

        return $sorted;
    }

    /**
     * Evaluate an asset and its dependencies.
     *
     * @param  string $asset
     * @param  string $value
     * @param  array $original
     * @param  array $sorted
     * @param  array $assets
     * @return void
     * @throws Exception
     */
    protected function evaluateAsset($asset, $value, $original, &$sorted, &$assets)
    {
        // If the asset has no more dependencies, we can add it to the sorted list
        // and remove it from the array of assets. Otherwise, we will not verify
        // the asset's dependencies and determine if they've been sorted.
        if (count($assets[$asset]['dependencies']) == 0) {
            $sorted[$asset] = $value;

            unset($assets[$asset]);
        } else {
            foreach ($assets[$asset]['dependencies'] as $key => $dependency) {
                if (!$this->dependencyIsValid($asset, $dependency, $original, $assets)) {
                    unset($assets[$asset]['dependencies'][$key]);

                    continue;
                }

                // If the dependency has not yet been added to the sorted list, we can not
                // remove it from this asset's array of dependencies. We'll try again on
                // the next trip through the loop.
                if (!isset($sorted[$dependency])) {
                    continue;
                }

                unset($assets[$asset]['dependencies'][$key]);
            }
        }
    }

    /**
     * Verify that an asset's dependency is valid.
     * A dependency is considered valid if it exists, is not a circular reference, and is
     * not a reference to the owning asset itself. If the dependency doesn't exist, no
     * error or warning will be given. For the other cases, an exception is thrown.
     *
     * @param  string $asset
     * @param  string $dependency
     * @param  array $original
     * @param  array $assets
     *
     * @throws Exception
     * @return bool
     */
    protected function dependencyIsValid($asset, $dependency, $original, $assets)
    {
        if (!isset($original[$dependency])) {
            return false;
        } elseif ($dependency === $asset) {
            throw new Exception('Asset [' . $asset . '] is dependent on itself.');
        } elseif (isset($assets[$dependency]) && in_array($asset, $assets[$dependency]['dependencies'])) {
            throw new Exception('Assets [' . $asset . '] and [' . $dependency . '] have a circular dependency.');
        }

        return true;
    }

    /**
     * Get the HTML link to a registered asset.
     *
     * @param  string $group
     * @param  string $name
     * @return string
     */
    protected function asset($group, $name)
    {
        if (!isset($this->assets[$group][$name])) {
            return '';
        }

        $asset = $this->assets[$group][$name];

        // If the bundle source is not a complete URL, we will go ahead and prepend
        // the bundle's asset path to the source provided with the asset. This will
        // ensure that we attach the correct path to the asset.
        if (filter_var($asset['source'], FILTER_VALIDATE_URL) === false) {
            $asset['source'] = $this->path($asset['source']);
        }

        // If source is not a path to asset, render without wrap a HTML.
        if (strpos($asset['source'], '<') !== false) {
            return $asset['source'];
        }

        // This line fixing config path.
        $asset['source'] = $this->configAssetUrl($asset['source']);

        //return Html::$group($asset['source'], $asset['attributes']);
        return $this->html($group, $asset['source'], $asset['attributes']);
    }

    /**
     * Returns the full-path for an asset.
     *
     * @param  string $source
     * @return string
     */
    public function path($source)
    {
        return $source;
    }

    /**
     * Render asset as HTML.
     *
     * @param  string $group
     * @param  mixed $source
     * @param  array $attributes
     * @return string
     */
    public function html($group, $source, $attributes)
    {
        switch ($group) {
            case 'script':
                $attributes['src'] = $source;

                return '<script' . $this->attributes($attributes) . '></script>' . PHP_EOL;
            case 'style':
                $defaults = ['media' => 'all', 'type' => 'text/css', 'rel' => 'stylesheet'];

                $attributes = $attributes + $defaults;

                $attributes['href'] = $source;

                return '<link' . $this->attributes($attributes) . '>' . PHP_EOL;
        }
        return null;
    }

    /**
     * Build an HTML attribute string from an array.
     *
     * @param  array $attributes
     * @return string
     */
    public function attributes($attributes)
    {
        $html = [];

        // For numeric keys we will assume that the key and the value are the same
        // as this will convert HTML attributes such as "required" to a correct
        // form like required="required" instead of using incorrect numerics.
        foreach ((array)$attributes as $key => $value) {
            $element = $this->attributeElement($key, $value);

            if (!empty($element)) {
                $html[] = $element;
            }
        }

        return count($html) > 0 ? ' ' . implode(' ', $html) : '';
    }

    /**
     * Build a single attribute element.
     *
     * @param  string $key
     * @param  string $value
     * @return string
     */
    protected function attributeElement($key, $value)
    {
        if (is_numeric($key)) {
            $key = $value;
        }

        if (!empty($value)) {
            return $key . '="' . e($value) . '"';
        }

        return null;
    }

    /**
     * Get the links to all of the registered JavaScript assets.
     *
     * @return  string
     * @throws Exception
     */
    public function scripts()
    {
        return $this->group('script');
    }
}
