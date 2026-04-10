<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpUnit\Bypass;

use RuntimeException;

/**
 * A stream wrapper class that uses native PHP functions for file and directory operations.
 * @internal
 */
final class NativeWrapper
{
    public const Protocol = 'file';

    /** @var class-string Reference to the outer wrapper class for re-registration */
    public string $outerWrapper = MutatingWrapper::class;

    /** @var resource|null Stream context, which may be set by stream functions */
    public $context;

    /** @var resource|null File handle, which may be set by stream functions */
    public $handle;

    public function dir_closedir(): void
    {
        if (is_resource($this->handle)) {
            closedir($this->handle);
        }
    }

    public function dir_opendir(
        string $path,
        int $options,
    ): bool {
        $result = $this->context !== null
            ? $this->native('opendir', $path, $this->context)
            : $this->native('opendir', $path)
        ;

        $this->handle = is_resource($result) ? $result : null;

        return $this->handle !== null;
    }

    public function dir_readdir(): mixed
    {
        return is_resource($this->handle) ? readdir($this->handle) : false;
    }

    public function dir_rewinddir(): bool
    {
        if (is_resource($this->handle)) {
            rewinddir($this->handle);
        }

        return true;
    }

    public function mkdir(
        string $path,
        int $mode,
        int $options,
    ): bool {
        $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);

        return (bool) ($this->context !== null
            ? $this->native('mkdir', $path, $mode, $recursive, $this->context)
            : $this->native('mkdir', $path, $mode, $recursive));
    }

    public function rename(
        string $pathFrom,
        string $pathTo,
    ): bool {
        return (bool) ($this->context !== null
            ? $this->native('rename', $pathFrom, $pathTo, $this->context)
            : $this->native('rename', $pathFrom, $pathTo));
    }

    public function rmdir(
        string $path,
        int $options,
    ): bool {
        return (bool) ($this->context !== null
            ? $this->native('rmdir', $path, $this->context)
            : $this->native('rmdir', $path));
    }

    public function stream_cast(
        int $castAs,
    ): mixed {
        return $this->handle;
    }

    public function stream_close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    public function stream_eof(): bool
    {
        return is_resource($this->handle) ? feof($this->handle) : true;
    }

    public function stream_flush(): bool
    {
        return is_resource($this->handle) ? fflush($this->handle) : false;
    }

    /**
     * @param int<0, 7> $operation
     */
    public function stream_lock(
        int $operation,
    ): bool {
        if (!is_resource($this->handle)) {
            return false;
        }

        return $operation !== 0 ? flock($this->handle, $operation) : true;
    }

    public function stream_metadata(
        string $path,
        int $option,
        mixed $value,
    ): bool {
        switch ($option) {
            case STREAM_META_TOUCH:
                $time1 = is_array($value) && isset($value[0]) && is_numeric($value[0]) ? (int) $value[0] : time();
                $time2 = is_array($value) && isset($value[1]) && is_numeric($value[1]) ? (int) $value[1] : time();

                return (bool) $this->native('touch', $path, $time1, $time2);
            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
                return (bool) $this->native('chown', $path, $value);
            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
                return (bool) $this->native('chgrp', $path, $value);
            case STREAM_META_ACCESS:
                return (bool) $this->native('chmod', $path, $value);
            default:
                return false;
        }
    }

    public function stream_open(
        string $path,
        string $mode,
        int $options = 0,
        ?string &$openedPath = null,
    ): bool {
        $usePath = (bool) ($options & STREAM_USE_PATH);
        $result = $this->context !== null
            ? $this->native('fopen', $path, $mode, $usePath, $this->context)
            : $this->native('fopen', $path, $mode, $usePath)
        ;

        $this->handle = is_resource($result) ? $result : null;

        return $this->handle !== null;
    }

    public function stream_read(
        int $count,
    ): mixed {
        return is_resource($this->handle) && $count > 0 ? fread($this->handle, $count) : false;
    }

    public function stream_seek(
        int $offset,
        int $whence = SEEK_SET,
    ): bool {
        return is_resource($this->handle) && fseek($this->handle, $offset, $whence) === 0;
    }

    public function stream_set_option(
        int $option,
        int $arg1,
        ?int $arg2,
    ): bool {
        if (!is_resource($this->handle)) {
            return false;
        }

        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                return stream_set_blocking($this->handle, (bool) $arg1);
            case STREAM_OPTION_READ_BUFFER:
                return stream_set_read_buffer($this->handle, (int) $arg2) === 0;
            case STREAM_OPTION_WRITE_BUFFER:
                return stream_set_write_buffer($this->handle, (int) $arg2) === 0;
            case STREAM_OPTION_READ_TIMEOUT:
                return stream_set_timeout($this->handle, $arg1, (int) $arg2);
            default:
                return false;
        }
    }

    public function stream_stat(): mixed
    {
        return is_resource($this->handle) ? fstat($this->handle) : false;
    }

    public function stream_tell(): mixed
    {
        return is_resource($this->handle) ? ftell($this->handle) : false;
    }

    public function stream_truncate(
        int $newSize,
    ): bool {
        return is_resource($this->handle) && $newSize >= 0 ? ftruncate($this->handle, $newSize) : false;
    }

    public function stream_write(
        string $data,
    ): mixed {
        return is_resource($this->handle) ? fwrite($this->handle, $data) : false;
    }

    public function unlink(
        string $path,
    ): bool {
        return (bool) $this->native('unlink', $path);
    }

    public function url_stat(
        string $path,
        int $flags,
    ): mixed {
        if (($flags & STREAM_URL_STAT_QUIET) === STREAM_URL_STAT_QUIET) {
            set_error_handler(fn() => true);
        }

        try {
            $func = ($flags & STREAM_URL_STAT_LINK) === STREAM_URL_STAT_LINK ? 'lstat' : 'stat';

            return $this->native($func, $path);
        } catch (RuntimeException $e) {
            return false;
        } finally {
            if (($flags & STREAM_URL_STAT_QUIET) === STREAM_URL_STAT_QUIET) {
                restore_error_handler();
            }
        }
    }

    /**
     * Temporarily restores the native protocol handler to perform operations.
     *
     * @param callable-string $func
     */
    private function native(
        string $func,
        mixed ...$args,
    ): mixed {
        stream_wrapper_restore(self::Protocol);

        try {
            // PHPStan guarantees $func is a valid callable here!
            return $func(...$args);
        } finally {
            stream_wrapper_unregister(self::Protocol);
            stream_wrapper_register(self::Protocol, $this->outerWrapper);
        }
    }
}
