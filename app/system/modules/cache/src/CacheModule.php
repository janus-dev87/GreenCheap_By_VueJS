<?php

namespace GreenCheap\Cache;

use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\ArrayCache;
use GreenCheap\Application as App;
use GreenCheap\Module\Module;
use RuntimeException;

class CacheModule extends Module
{
    /**
     * {@inheritdoc}
     */
    public function main(App $app)
    {
        foreach ($this->config["caches"] as $name => $config) {
            $app[$name] = function () use ($config) {
                $supports = $this->supports();

                if (!isset($config["storage"])) {
                    throw new RuntimeException("Cache storage missing.");
                }

                if ($this->config["nocache"]) {
                    $config["storage"] = "array";
                } elseif ($config["storage"] == "auto" || !in_array($config["storage"], $supports)) {
                    $config["storage"] = end($supports);
                }

                $cache = match ($config["storage"]) {
                    "array" => new ArrayCache(),
                    "apc" => new ApcuCache(),
                    "file" => new FilesystemCache($config["path"]),
                    "phpfile" => new PhpFileCache($config["path"]),
                    default => throw new RuntimeException("Unknown cache storage."),
                };

                if ($prefix = $config["prefix"] ?? false) {
                    $cache->setNamespace($prefix);
                }

                return $cache;
            };
        }
    }

    /**
     * Returns list of supported caches or boolean for individual cache.
     *
     * @param  string $name
     * @return array|boolean
     */
    public static function supports($name = null)
    {
        $supports = ["phpfile", "array", "file"];

        if (extension_loaded("apc") && class_exists("\APCIterator")) {
            if (!extension_loaded("apcu") || version_compare(phpversion("apcu"), "4.0.2", ">=")) {
                $supports[] = "apc";
            }
        }

        if (extension_loaded("xcache") && ini_get("xcache.var_size")) {
            $supports[] = "xcache";
        }

        return $name ? in_array($name, $supports) : $supports;
    }

    /**
     * Clear cache on terminate event.
     */
    public function clearCache(array $options = [])
    {
        App::on(
            "terminate",
            function () use ($options) {
                $this->doClearCache($options);
            },
            -512
        );
    }

    /**
     * TODO: clear opcache
     * @param array $options
     */
    public function doClearCache(array $options = [])
    {
        // clear cache
        if (empty($options) || @$options["cache"]) {
            App::cache()->flushAll();

            foreach ((array) glob(App::get("path.cache") . "/*.cache") as $file) {
                @unlink($file);
                // opcache
                if (function_exists("opcache_invalidate")) {
                    opcache_invalidate($file);
                }
            }
        }

        // clear temp folder
        if (@$options["temp"]) {
            foreach (
                App::finder()
                    ->in(App::get("path.temp"))
                    ->depth(0)
                    ->ignoreDotFiles(true)
                as $file
            ) {
                App::file()->delete($file->getPathname());
                // opcache
                if (function_exists("opcache_invalidate")) {
                    opcache_invalidate($file);
                }
            }
        }
    }
}
