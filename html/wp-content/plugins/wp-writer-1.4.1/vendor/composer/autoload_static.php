<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit033cfe0194f91aa257d776aa59857191
{
    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'WP_Writer\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'WP_Writer\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit033cfe0194f91aa257d776aa59857191::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit033cfe0194f91aa257d776aa59857191::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit033cfe0194f91aa257d776aa59857191::$classMap;

        }, null, ClassLoader::class);
    }
}
