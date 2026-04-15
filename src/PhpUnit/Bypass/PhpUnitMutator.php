<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpUnit\Bypass;

use ParseError;
use ReflectionClass;

/**
 * Removes keyword 'final' & 'readonly' globally, and injects testing attributes locally.
 */
final class PhpUnitMutator
{
    /** @var string The attribute to inject above all intercepted test classes. */
    public static string $attributeToInject = "#[\\PHPUnit\\Framework\\Attributes\\AllowMockObjectsWithoutExpectations]\n";

    /** @var array<bool, list<string>> */
    private static array $accessRules = [];

    private static ?string $cacheDir = null;

    /** @var array<int, string> */
    private static array $tokens = [];

    /** @var list<array<string, mixed>> */
    private static array $enableCallStack = [];

    /**
     * @var list<string>
     * @phpstan-ignore property.onlyWritten
     */
    private static array $classesLoadedBeforeEnable = [];

    /** @var list<string> */
    private static array $modifiedFiles = [];

    public static function enable(
        bool $bypassReadOnly = true,
        bool $bypassFinal = true,
    ): void {
        if (self::$enableCallStack === []) {
            self::$enableCallStack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            self::$classesLoadedBeforeEnable = array_values(array_filter(
                get_declared_classes(),
                fn(string $class): bool => !(new ReflectionClass($class))->isInternal() && $class !== self::class,
            ));
        }

        if ($bypassReadOnly) {
            self::$tokens[T_READONLY] = 'readonly';
        }
        if ($bypassFinal) {
            self::$tokens[T_FINAL] = 'final';
        }

        $handle = fopen(__FILE__, 'r');
        if ($handle === false) {
            return;
        }
        $wrapperData = stream_get_meta_data($handle)['wrapper_data'] ?? null;
        fclose($handle);

        if ($wrapperData instanceof MutatingWrapper) {
            return;
        }

        MutatingWrapper::$underlyingWrapperClass = is_object($wrapperData) ? get_class($wrapperData) : NativeWrapper::class;
        stream_wrapper_unregister(NativeWrapper::Protocol);
        stream_wrapper_register(NativeWrapper::Protocol, MutatingWrapper::class);
    }

    /**
     * @param list<string> $masks
     */
    public static function setWhitelist(
        array $masks,
    ): void {
        self::$accessRules[true] = [];
        self::allowPaths($masks);
    }

    /**
     * @param list<string> $masks
     */
    public static function allowPaths(
        array $masks,
    ): void {
        foreach ($masks as $mask) {
            self::$accessRules[true][] = strtr($mask, '\\', '/');
        }
    }

    /**
     * @param list<string> $masks
     */
    public static function denyPaths(
        array $masks,
    ): void {
        foreach ($masks as $mask) {
            self::$accessRules[false][] = strtr($mask, '\\', '/');
        }
    }

    public static function setCacheDirectory(
        ?string $dir,
    ): void {
        self::$cacheDir = $dir;
    }

    public static function modifyCode(
        string $code,
        ?string $file = null,
    ): string {
        $needsMutation = stripos($code, 'class') !== false;
        if (!$needsMutation) {
            foreach (self::$tokens as $text) {
                if (stripos($code, $text) !== false) {
                    $needsMutation = true;

                    break;
                }
            }
        }

        if ($needsMutation) {
            $code = self::$cacheDir !== null ? self::mutateTokensCached($code) : self::mutateTokens($code);
        }

        if (self::isTestFile($file) && stripos($code, 'class') !== false) {
            $modifiedCode = self::injectSafeAttribute($code);
        } else {
            $modifiedCode = $code;
        }

        if ($modifiedCode !== $code && is_string($file)) {
            self::$modifiedFiles[] = $file;
        }

        return $modifiedCode;
    }

    public static function isPathAllowed(
        string $path,
    ): bool {
        $path = strtr($path, '\\', '/');
        foreach (self::$accessRules[true] ?? ['*'] as $mask) {
            if (fnmatch($mask, $path)) {
                foreach (self::$accessRules[false] ?? [] as $denyMask) {
                    if (fnmatch($denyMask, $path)) {
                        return false;
                    }
                }

                return true;
            }
        }

        return false;
    }

    public static function debugInfo(): void
    {
        echo "<xmp>\n";
        echo "PhpUnitMutator Debug Information\n";
        echo "------------------------------\n\n";
        echo "Files where modifications were applied:\n";
        if (self::$modifiedFiles !== []) {
            foreach (self::$modifiedFiles as $file) {
                echo "  - $file\n";
            }
        } else {
            echo "  no files were modified\n";
        }
    }

    private static function isTestFile(
        ?string $file,
    ): bool {
        if ($file === null) {
            return false;
        }

        $normalizedPath = strtr($file, '\\', '/');

        // Ignore temporary files generated by Rector from fixtures
        if (stripos($normalizedPath, '/fixture/') !== false || stripos($normalizedPath, '/fixtures/') !== false) {
            return false;
        }

        return stripos($normalizedPath, '/tests/') !== false
            || stripos($normalizedPath, '/test/') !== false
        ;
    }

    private static function injectSafeAttribute(
        string $code,
    ): string {
        $pattern = '/^([\t ]*)((?:abstract\s+|final\s+|readonly\s+)*)(class\s+[A-Za-z0-9_]+\b)/mi';

        return (string) preg_replace(
            $pattern,
            "$1" . self::$attributeToInject . "$1$2$3",
            $code,
        );
    }

    private static function mutateTokensCached(
        string $code,
    ): string {
        $wrapper = new NativeWrapper;
        $hash = sha1($code . implode(',', self::$tokens));
        $cacheFile = self::$cacheDir . '/' . $hash;

        if (@$wrapper->stream_open($cacheFile, 'r') && is_resource($wrapper->handle)) {
            flock($wrapper->handle, LOCK_SH);
            $res = stream_get_contents($wrapper->handle);
            if (is_string($res)) {
                return $res;
            }
        }

        $code = self::mutateTokens($code);

        if (@$wrapper->stream_open($cacheFile, 'x') && is_resource($wrapper->handle)) {
            flock($wrapper->handle, LOCK_EX);
            fwrite($wrapper->handle, $code);
        }

        return $code;
    }

    private static function mutateTokens(
        string $code,
    ): string {
        try {
            $tokens = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            return $code;
        }

        $code = '';
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (isset(self::$tokens[$token[0]])) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }
}
