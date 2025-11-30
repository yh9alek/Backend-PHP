<?php

namespace app\helpers;

class Asset {

    public static function getSource(string $path, string $type): string {
        $webPath = "/assets/$type/modules/" . ltrim($path, '/') . '.' . $type;
        $filePath = __DIR__ . "/../../public" . $webPath;

        return file_exists($filePath) ? $webPath : "";
    }
}