<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita284b8440ce7cae45448aa4583a3feee
{
    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInita284b8440ce7cae45448aa4583a3feee::$classMap;

        }, null, ClassLoader::class);
    }
}
