<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpUnit\Bypass;

/**
 * A stream wrapper class that mutates PHP source code by modifying 'final' and 'readonly' keywords.
 * @internal
 */
final class MutatingWrapper
{
    /** @var class-string Specifies the class of the underlying normal wrapper */
    public static string $underlyingWrapperClass;

    /** @var resource|null Stream context, which may be set by stream functions */
    public $context;

    /** @var object|null Instance of the actual underlying wrapper used for file operations */
    private ?object $wrapper = null;

    /**
     * Delegates the handling of file/directory operations to the underlying wrapper.
     *
     * @param array<int|string, mixed> $args
     */
    public function __call(
        string $method,
        array $args,
    ): mixed {
        $wrapper = $this->wrapper ?? $this->createUnderlyingWrapper();

        $callable = [$wrapper, $method];
        if (is_callable($callable)) {
            return $callable(...$args);
        }

        return false;
    }

    /**
     * Opens a stream resource and creates $wrapper property. It can modify PHP source files if allowed by the path rules.
     */
    public function stream_open(
        string $path,
        string $mode,
        int $options,
        ?string &$openedPath,
    ): bool {
        if (is_dir($path)) {
            return false;
        }

        $this->wrapper = $this->createUnderlyingWrapper();

        /** @var NativeWrapper $wrapper */
        $wrapper = $this->wrapper;

        if (!$wrapper->stream_open($path, $mode, $options, $openedPath)) {
            return false;
        }

        if ($mode === 'rb' && pathinfo($path, PATHINFO_EXTENSION) === 'php' && PhpUnitMutator::isPathAllowed($path)) {
            $content = '';
            while (!$wrapper->stream_eof()) {
                $chunk = $wrapper->stream_read(8192);
                if (is_string($chunk)) {
                    $content .= $chunk;
                } else {
                    break;
                }
            }

            $modified = PhpUnitMutator::modifyCode($content, $path);
            if ($modified === $content) {
                $wrapper->stream_seek(0);
            } else {
                $wrapper->stream_close();
                $this->wrapper = new NativeWrapper;

                /** @var NativeWrapper $newWrapper */
                $newWrapper = $this->wrapper;

                $tempHandle = tmpfile();
                if ($tempHandle !== false) {
                    $newWrapper->handle = $tempHandle;
                    $newWrapper->stream_write($modified);
                    $newWrapper->stream_seek(0);
                }
            }
        }

        return true;
    }

    /**
     * Delegates the handling of directory opening to the underlying wrapper and creates $wrapper property.
     */
    public function dir_opendir(
        string $path,
        int $options,
    ): bool {
        $this->wrapper = $this->createUnderlyingWrapper();

        /** @var NativeWrapper $wrapper */
        $wrapper = $this->wrapper;

        return $wrapper->dir_opendir($path, $options);
    }

    /**
     * Instantiates the underlying wrapper.
     */
    private function createUnderlyingWrapper(): object
    {
        $class = self::$underlyingWrapperClass;
        $wrapper = new $class();

        if (property_exists($wrapper, 'context')) {
            $wrapper->context = $this->context;
        }

        return $wrapper;
    }
}
