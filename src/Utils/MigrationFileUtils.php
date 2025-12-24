<?php

namespace Articulate\Utils;

class MigrationFileUtils
{
    public static function getNamespaceFromFile(string $filePath): ?string
    {
        $namespace = null;
        $handle = fopen($filePath, 'r');

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^namespace\s+(.+?);$/', trim($line), $matches)) {
                    $namespace = $matches[1];

                    break;
                }
            }
            fclose($handle);
        }

        return $namespace;
    }
}


