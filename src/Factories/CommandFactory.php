<?php

namespace Turso\Driver\Laravel\Factories;

class CommandFactory
{
    public static function collect(): array
    {
        $classes = self::getClassesUnderNamespace();
        return $classes;
    }

    protected static function getClassesUnderNamespace(): array
    {
        $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Commands';

        if (!is_dir($directory)) {
            throw new \RuntimeException("Directory not found at $directory");
        }

        $classes = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname());

                $className = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);

                $fullClassName = "Turso\\Driver\\Laravel\\Commands\\$className";

                if (class_exists($fullClassName)) {
                    $classes[] = $fullClassName;
                }
            }
        }

        return $classes;
    }

}
