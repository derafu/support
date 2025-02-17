<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Support;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\MimeTypes;
use ZipArchive;
use ZipStream\ZipStream;

/**
 * File system operations and utilities.
 *
 * Provides functionality for common file operations including:
 *
 *   - Directory removal.
 *   - MIME type detection.
 *   - ZIP compression.
 *   - File downloads.
 *
 * Uses Symfony Filesystem and MimeTypes components for robust file operations,
 * and ZipStream for efficient ZIP file handling.
 */
final class File
{
    /**
     * Recursively removes a directory and its contents.
     *
     * @param string $dir Directory path to remove.
     * @return void
     * @throws RuntimeException If directory cannot be removed.
     */
    public static function rmdir(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }

        try {
            $filesystem = new Filesystem();
            $filesystem->remove($dir);
        } catch (Exception $e) {
            throw new RuntimeException(
                "Failed to remove directory {$dir}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Gets the MIME type of a file.
     *
     * @param string $file Path to the file.
     * @return string|false MIME type of the file or false if it cannot be determined.
     */
    public static function mimetype(string $file): string|false
    {
        if (!file_exists($file)) {
            return false;
        }

        $mimeTypes = new MimeTypes();
        return $mimeTypes->guessMimeType($file) ?: false;
    }

    /**
     * Compresses a file or directory into a ZIP archive.
     *
     * @param string $source File or directory to compress.
     * @param bool $download Whether to send the file through the browser.
     * @param bool $delete Whether to delete the original file after compression.
     * @return void
     * @throws RuntimeException If compression fails.
     */
    public static function compress(
        string $source,
        bool $download = false,
        bool $delete = false
    ): void {
        if (!is_readable($source)) {
            throw new RuntimeException(
                "Cannot read source file or directory: {$source}"
            );
        }

        $zipPath = $source . '.zip';

        self::zip($source, $zipPath);

        if ($download) {
            self::send($zipPath, true);
        }

        if ($delete) {
            self::rmdir($source);
        }
    }

    /**
     * Creates a ZIP file from a file or directory.
     *
     * @param string $source File or directory to compress.
     * @param string $destination Path for the resulting ZIP file.
     * @return void
     * @throws RuntimeException If ZIP creation fails.
     */
    public static function zip(string $source, string $destination): void
    {
        $output = fopen($destination, 'wb');
        if ($output === false) {
            throw new RuntimeException(
                "Cannot open destination file for writing: {$destination}"
            );
        }

        try {
            $zip = new ZipStream(outputStream: $output);

            if (is_dir($source)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($source),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if ($file->isDir()) {
                        continue;
                    }

                    $filePath = $file->getRealPath();
                    $relativePath = substr(
                        $filePath,
                        strlen($source) + 1
                    );

                    $zip->addFileFromPath($relativePath, $filePath);
                }
            } else {
                $zip->addFileFromPath(basename($source), $source);
            }

            $zip->finish();
        } catch (Exception $e) {
            throw new RuntimeException(
                "Failed to create ZIP file: {$e->getMessage()}",
                0,
                $e
            );
        } finally {
            fclose($output);
        }
    }

    /**
     * Extracts a ZIP archive.
     *
     * @param string $zipFile Path to the ZIP file.
     * @param string $destination Directory where files will be extracted.
     * @param bool $overwrite Whether to overwrite existing files.
     * @return void
     * @throws RuntimeException If extraction fails.
     */
    public static function unzip(
        string $zipFile,
        string $destination,
        bool $overwrite = false
    ): void {
        if (!extension_loaded('zip')) {
            throw new RuntimeException('ZIP extension is not available');
        }

        if (!file_exists($zipFile)) {
            throw new RuntimeException("ZIP file does not exist: {$zipFile}");
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipFile);

        if ($result !== true) {
            throw new RuntimeException(
                "Failed to open ZIP file: {$zipFile} (Error code: {$result})"
            );
        }

        try {
            if (!$overwrite) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $filepath = $destination . DIRECTORY_SEPARATOR . $filename;

                    if (file_exists($filepath)) {
                        throw new RuntimeException(
                            "File already exists: {$filepath}"
                        );
                    }
                }
            }

            if (!$zip->extractTo($destination)) {
                throw new RuntimeException(
                    "Failed to extract ZIP file to: {$destination}"
                );
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Sends a file through the browser as a download.
     *
     * @param string $file Path to the file to send.
     * @param bool $delete Whether to delete the file after sending (default: false).
     * @param bool $sendHeaders Whether to send HTTP headers (default: true).
     * @return void
     * @throws RuntimeException If file cannot be sent.
     */
    public static function send(
        string $file,
        bool $delete = false,
        bool $sendHeaders = true
    ): void {
        if (!file_exists($file)) {
            throw new RuntimeException("File does not exist: {$file}");
        }

        if (!is_readable($file)) {
            throw new RuntimeException("Cannot read file: {$file}");
        }

        if ($sendHeaders) {
            if (headers_sent()) {
                throw new RuntimeException('Headers have already been sent.');
            }

            $mimetype = self::mimetype($file);
            if ($mimetype) {
                header('Content-Type: ' . $mimetype);
            }

            $filename = basename($file);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($file));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        if (readfile($file) === false) {
            throw new RuntimeException("Failed to send file: {$file}");
        }

        if ($delete) {
            unlink($file);
        }
    }
}
