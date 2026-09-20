<?php

namespace Arpan\DatabaseDumper\Compression;

use ZipArchive;

class BackupCompressor
{
    public function compress($sourcePath, $destinationPath)
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Zip Compression requires the PHP zip extension.');
        }

        $zip = new ZipArchive();

        $result = $zip->open($destinationPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new \RuntimeException(
                'Unable to create ZIP archive.'
            );
        }

        if (!$zip->addFile(
            $sourcePath,
            basename($sourcePath)
        )) {
            $zip->close();

            throw new \RuntimeException(
                'Unable to add database dump to ZIP archive.'
            );
        }

        if (!$zip->close()) {
            throw new \RuntimeException(
                'Unable to finalize ZIP archive.'
            );
        }

        return $destinationPath;
    }
}
