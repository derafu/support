<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Support;

use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Exception;
use finfo;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\MimeTypes;
use Throwable;
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
     * Writes content to a file atomically using a temporary file and rename
     * operation.
     *
     * This method ensures that file writing is atomic by:
     *
     *   1. Creating a temporary file in the same directory as the target file.
     *   2. Writing the content to the temporary file.
     *   3. Using rename() to atomically replace the target file.
     *
     * The atomic operation guarantees that:
     *
     *   - Other processes will see either the old file or the new file, never a
     *     partially written file.
     *   - If the process is interrupted, the original file remains intact.
     *   - Race conditions between multiple processes are handled safely.
     *
     * @param string $targetFile The path to the file where content should be written.
     * @param string $content The content to write to the file.
     * @param int $permissions Optional file permissions (default: 0666 & ~umask()).
     *
     * @throws RuntimeException If directory creation fails.
     * @throws RuntimeException If temporary file creation fails.
     * @throws RuntimeException If file writing fails.
     * @throws RuntimeException If file renaming fails.
     */
    public static function write(
        string $targetFile,
        string $content,
        ?int $permissions = null
    ): void {
        // Ensure target directory exists.
        $directory = dirname($targetFile);
        if (!is_dir($directory)) {
            if (false === @mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException([
                    'Unable to create directory ({directory}).',
                    'directory' => $directory,
                ]);
            }
        }

        // Create temporary file in the same directory.
        $tempFile = tempnam($directory, basename($targetFile)) ?: throw new RuntimeException(['Unable to create temporary file in directory ({directory}).', 'directory' => $directory]);

        try {
            // Write content to temporary file.
            false !== file_put_contents($tempFile, $content) ?: throw new RuntimeException(['Unable to write content to temporary file ({file}).', 'file' => $tempFile]);

            // Set file permissions if specified.
            if ($permissions !== null) {
                @chmod($tempFile, $permissions) ?: throw new RuntimeException(['Unable to set permissions on temporary file ({file}).', 'file' => $tempFile]);
            } else {
                @chmod($tempFile, 0666 & ~umask());
            }

            // Perform atomic rename operation.
            if (!@rename($tempFile, $targetFile)) {
                throw new RuntimeException([
                    'Unable to move temporary file to target location ({file}).',
                    'file' => $targetFile,
                ]);
            }
        } catch (Throwable $e) {
            // Clean up temporary file if anything goes wrong.
            @unlink($tempFile);
            throw $e;
        }
    }

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
                [
                    'Failed to remove directory {directory}: {error}',
                    'directory' => $dir,
                    'error' => $e->getMessage(),
                ],
                0,
                $e
            );
        }
    }

    /**
     * Gets the MIME type of a file.
     *
     * The type is detected by the content of the file, not by its name.
     *
     * @param string $file Path to the file.
     * @return string|false MIME type of the file, or false if the path is not a
     * regular file that can be read (it does not exist, or it is a directory,
     * or it has no permission to be read), or if the type cannot be determined.
     */
    public static function mimetype(string $file): string|false
    {
        if (!is_file($file) || !is_readable($file)) {
            return false;
        }

        $mimeTypes = new MimeTypes();
        return $mimeTypes->guessMimeType($file) ?: false;
    }

    /**
     * Gets the MIME type of a content that is in memory, as `mimetype()` does
     * with a file.
     *
     * The type is detected by the content, and only with `fileinfo` (the
     * detection of a file has the binary `file` as a fallback). The extension
     * `fileinfo` is required.
     *
     * Only the content that is given is read, so it can be just the start of a
     * file. That is enough for the formats that have a signature at the start
     * (images, PDF, ZIP), but not for those that need the end of the file to be
     * known (a `.docx` is detected as `application/zip` with only a start).
     *
     * @param string $content The content, or the start of it.
     * @return string|false MIME type of the content, or false if it cannot be
     * determined. An empty content is `application/x-empty`.
     */
    public static function mimetypeFromContent(string $content): string|false
    {
        return (new finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: false;
    }

    /**
     * Gets the MIME types that a file extension can have.
     *
     * An extension can have more than one type, as its aliases (`jpg` is
     * `image/jpeg` and `image/pjpeg`).
     *
     * @param string $extension The extension, with or without the leading point
     * and in upper or lower case (`csv`, `.csv` and `CSV` are the same).
     * @return list<string> The types of the extension, or an empty list if the
     * extension is empty or is not known.
     */
    public static function mimetypesFromExtension(string $extension): array
    {
        if (str_starts_with($extension, '.')) {
            $extension = substr($extension, 1);
        }

        if ($extension === '') {
            return [];
        }

        return array_values((new MimeTypes())->getMimeTypes($extension));
    }

    /**
     * Reads a size, as written by a person, and returns it in bytes.
     *
     * It is a whole number of bytes (`2048`), or a number with a unit, that can
     * have decimals with a point (`2M`, `1.5Mi`). The units are not case
     * sensitive:
     *
     *   - `k`, `m` and `g` are decimal: 1000, 1000^2 and 1000^3 bytes.
     *   - `ki`, `mi` and `gi` are binary: 1024, 1024^2 and 1024^3 bytes.
     *
     * The result is rounded down to a whole byte. The calculation is done with
     * integers, so the rounding does not depend on how a float is stored.
     *
     * Nothing else is a size: not a unit alone, a number with spaces, a
     * negative number, a decimal without a unit, a unit that is not in the list
     * (`MB`), a number with more than 9 decimals (without the zeros at the end)
     * or a size that does not fit in an integer.
     *
     * @param string $size The size.
     * @return int|null The size in bytes, or `null` if it is not a valid size.
     */
    public static function parseSize(string $size): ?int
    {
        $factors = [
            '' => 1,
            'k' => 1000,
            'ki' => 1 << 10,
            'm' => 1000 ** 2,
            'mi' => 1 << 20,
            'g' => 1000 ** 3,
            'gi' => 1 << 30,
        ];

        if (!preg_match('/^(\d+)(?:\.(\d+))?(k|ki|m|mi|g|gi)?$/i', $size, $matches)) {
            return null;
        }

        $factor = $factors[strtolower($matches[3] ?? '')];
        $fraction = rtrim($matches[2] ?? '', '0');

        // Bytes can not have decimals, not even zeros, and 9 digits of them is
        // the most that can be multiplied by a factor without leaving the
        // integers.
        if (isset($matches[2]) && ($factor === 1 || strlen($fraction) > 9)) {
            return null;
        }

        $whole = filter_var(ltrim($matches[1], '0') ?: '0', FILTER_VALIDATE_INT);
        if ($whole === false || $whole > intdiv(PHP_INT_MAX, $factor)) {
            return null;
        }

        $bytes = $whole * $factor;
        if ($fraction === '') {
            return $bytes;
        }

        $extra = intdiv((int) $fraction * $factor, 10 ** strlen($fraction));

        return $bytes > PHP_INT_MAX - $extra ? null : $bytes + $extra;
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
                ['Cannot read source file or directory: {source}', 'source' => $source]
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
                ['Cannot open destination file for writing: {destination}', 'destination' => $destination]
            );
        }

        try {
            // The archive goes to a file: the headers of a download are not
            // sent (they would be the ones of the response of whoever calls).
            $zip = new ZipStream(outputStream: $output, sendHttpHeaders: false);

            if (is_dir($source)) {
                // The name of each file is its path inside the directory, taken
                // from the path as it was given: the real path of the file is
                // not the same when the directory is reached through a symbolic
                // link (or has a trailing slash, or `..`), and then the names
                // would be cut at the wrong place.
                $directory = rtrim($source, '/\\');

                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    /** @var SplFileInfo $file */
                    if ($file->isDir()) {
                        continue;
                    }

                    $filePath = $file->getPathname();
                    $relativePath = substr($filePath, strlen($directory) + 1);

                    $zip->addFileFromPath($relativePath, $filePath);
                }
            } else {
                $zip->addFileFromPath(basename($source), $source);
            }

            $zip->finish();
        } catch (Exception $e) {
            throw new RuntimeException(
                ['Failed to create ZIP file: {error}', 'error' => $e->getMessage()],
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
        extension_loaded('zip') ?: throw new RuntimeException('ZIP extension is not available');

        if (!file_exists($zipFile)) {
            throw new RuntimeException(['ZIP file does not exist: {file}', 'file' => $zipFile]);
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipFile);

        if ($result !== true) {
            throw new RuntimeException(
                [
                    'Failed to open ZIP file: {file} (Error code: {code})',
                    'file' => $zipFile,
                    'code' => $result,
                ]
            );
        }

        try {
            if (!$overwrite) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $filepath = $destination . DIRECTORY_SEPARATOR . $filename;

                    if (file_exists($filepath)) {
                        throw new RuntimeException(
                            ['File already exists: {file}', 'file' => $filepath]
                        );
                    }
                }
            }

            if (!$zip->extractTo($destination)) {
                throw new RuntimeException(
                    ['Failed to extract ZIP file to: {destination}', 'destination' => $destination]
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
            throw new RuntimeException(['File does not exist: {file}', 'file' => $file]);
        }

        if (!is_readable($file)) {
            throw new RuntimeException(['Cannot read file: {file}', 'file' => $file]);
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

        readfile($file) !== false ?: throw new RuntimeException(['Failed to send file: {file}', 'file' => $file]);

        if ($delete) {
            unlink($file);
        }
    }
}
