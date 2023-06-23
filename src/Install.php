<?php

namespace Gzqsts\Core;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * @var array
     */
    protected static $pathRelation = array(
        'config/plugin/gzqsts/core' => 'config/plugin/gzqsts/core',
        'resource/translations' => 'resource/translations',
    );

    /**
     * Install
     * @return void
     */
    public static function install()
    {
        static::installByRelation();
        if (!is_dir(app_path() . '/queue/redis')){
            mkdir(app_path() . '/queue/redis', 0777, true);
        }
    }

    /**
     * Uninstall
     * @return void
     */
    public static function uninstall()
    {
        self::uninstallByRelation();
    }

    /**
     * installByRelation
     * @return void
     */
    public static function installByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            if ($pos = strrpos($dest, '/')) {
                $parent_dir = base_path() . '/' . substr($dest, 0, $pos);
                if (!is_dir($parent_dir)) {
                    mkdir($parent_dir, 0777, true);
                }
            }
            //symlink(__DIR__ . "/$source", base_path()."/$dest");
            copy_dir(__DIR__ . "/$source", base_path() . "/$dest");
            echo "Create $dest";
        }
    }

    /**
     * uninstallByRelation
     * @return void
     */
    public static function uninstallByRelation()
    {
        static::uninstalllang();
        foreach (static::$pathRelation as $source => $dest) {
            if($source == 'resource/translations'){
                continue;
            }
            $path = base_path() . "/$dest";
            if (!is_dir($path) && !is_file($path)) {
                continue;
            }
            echo "Remove $dest";
            if (is_file($path) || is_link($path)) {
                unlink($path);
                continue;
            }
            remove_dir($path);
        }
    }

    //卸载语言包
    public static function uninstalllang()
    {
        $path = base_path();
        $validate_lang = [
            $path . '/resource/translations/en/validate.php',
            $path . '/resource/translations/zh-Hans/validate.php',
            $path . '/resource/translations/zh-Hant/validate.php'
        ];
        foreach ($validate_lang as $langPath) {
            if (is_file($langPath)) {
                unlink($langPath);
                continue;
            }
        }
    }

}