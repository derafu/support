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

/**
 * Debugging utilities.
 *
 * Provides methods for debugging variables and collecting debug information.
 */
final class Debug
{
    /**
     * Shows relevant debugging information for a variable.
     *
     * The file and the line are the ones where this method was called, and the
     * caller is the function or method that called it (`{main}` if it was
     * called from the code that is outside of any function, as PHP says it in
     * its traces).
     *
     * @param mixed $var Variable to inspect.
     * @param string|null $label Variable label (usually its name).
     * @return array Debug information collected.
     */
    public static function inspect(mixed $var, ?string $label = null): array
    {
        return self::collect($var, $label);
    }

    /**
     * Prints debug information in a formatted way.
     *
     * The file, the line and the caller are the ones of the call to this
     * method.
     *
     * @param mixed $var Variable to debug.
     * @param string|null $label Variable label.
     * @return void
     */
    public static function print(mixed $var, ?string $label = null): void
    {
        echo '<pre>';
        print_r(self::collect($var, $label));
        echo '</pre>';
    }

    /**
     * Collects the debug information of a variable.
     *
     * It must be called by the public method that was called by the user, so
     * the first frame of the trace is the call to this method, the second one is
     * the call of the user and the third one is the function that the user was
     * in.
     *
     * @param mixed $var Variable to inspect.
     * @param string|null $label Variable label.
     * @return array Debug information collected.
     */
    private static function collect(mixed $var, ?string $label): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $debugCall = $backtrace[1];
        $debugCaller = $backtrace[2] ?? null;

        if ($debugCaller === null) {
            $caller = '{main}';
        } elseif (isset($debugCaller['class'])) {
            $caller = "{$debugCaller['class']}::{$debugCaller['function']}()";
        } else {
            $caller = "{$debugCaller['function']}()";
        }

        $data = [
            'label' => $label ?? 'debug($var)',
            'type' => gettype($var),
            'length' => is_countable($var)
                ? count($var)
                : (is_string($var) ? strlen($var) : null),
            'file' => $debugCall['file'] ?? null,
            'line' => $debugCall['line'] ?? null,
            'caller' => $caller,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(),
            'value' => null,
        ];

        if (is_object($var)) {
            $data['type'] = get_class($var);
            $data['value'] = print_r($var, true);
        } elseif (is_null($var) || is_bool($var)) {
            $data['value'] = json_encode($var, JSON_PRETTY_PRINT);
        } else {
            $data['value'] = print_r($var, true);
        }

        return $data;
    }
}
