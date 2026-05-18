<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Phase-59 TENANT-ROUTING-1: decorates a Filesystem with a fixed
 * path prefix. Every method that takes a path prepends the prefix
 * before forwarding; methods that return file/directory listings
 * strip the prefix from the results so callers see unprefixed paths.
 *
 * The decorator is opt-in via config('filesystems.tenant_disk_prefix_template');
 * when null (default), TenantDiskResolver doesn't wrap the disk and
 * Phase-58 behaviour is preserved bit-for-bit.
 *
 * The decorator is a one-way contract: enabling the prefix shards
 * future writes under {landlord_id}/ but existing path strings stored
 * pre-enable can no longer be read through this disk. Operators
 * coordinate a backfill (aws s3 sync with prefix rewrite) before
 * flipping the env var. See docs/runbooks/storage.md.
 */
class PrefixedDisk implements Filesystem
{
    public function __construct(
        private readonly Filesystem $inner,
        private readonly string $prefix,
    ) {}

    private function prefix(string $path): string
    {
        return $this->prefix.ltrim($path, '/');
    }

    /**
     * @param  array<int, string>|string  $paths
     * @return array<int, string>|string
     */
    private function prefixMany(array|string $paths): array|string
    {
        return is_array($paths)
            ? array_map(fn (string $p) => $this->prefix($p), $paths)
            : $this->prefix($paths);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function unprefixListing(array $paths): array
    {
        $len = strlen($this->prefix);

        return array_map(
            fn (string $p) => str_starts_with($p, $this->prefix) ? substr($p, $len) : $p,
            $paths,
        );
    }

    public function path($path)
    {
        return $this->inner->path($this->prefix($path));
    }

    public function exists($path)
    {
        return $this->inner->exists($this->prefix($path));
    }

    public function get($path)
    {
        return $this->inner->get($this->prefix($path));
    }

    public function readStream($path)
    {
        return $this->inner->readStream($this->prefix($path));
    }

    public function put($path, $contents, $options = [])
    {
        return $this->inner->put($this->prefix($path), $contents, $options);
    }

    public function putFile($path, $file = null, $options = [])
    {
        $result = $this->inner->putFile($this->prefix($path), $file, $options);

        return is_string($result) && str_starts_with($result, $this->prefix)
            ? substr($result, strlen($this->prefix))
            : $result;
    }

    public function putFileAs($path, $file, $name = null, $options = [])
    {
        $result = $this->inner->putFileAs($this->prefix($path), $file, $name, $options);

        return is_string($result) && str_starts_with($result, $this->prefix)
            ? substr($result, strlen($this->prefix))
            : $result;
    }

    public function writeStream($path, $resource, array $options = [])
    {
        return $this->inner->writeStream($this->prefix($path), $resource, $options);
    }

    public function getVisibility($path)
    {
        return $this->inner->getVisibility($this->prefix($path));
    }

    public function setVisibility($path, $visibility)
    {
        return $this->inner->setVisibility($this->prefix($path), $visibility);
    }

    public function prepend($path, $data)
    {
        return $this->inner->prepend($this->prefix($path), $data);
    }

    public function append($path, $data)
    {
        return $this->inner->append($this->prefix($path), $data);
    }

    public function delete($paths)
    {
        return $this->inner->delete($this->prefixMany($paths));
    }

    public function copy($from, $to)
    {
        return $this->inner->copy($this->prefix($from), $this->prefix($to));
    }

    public function move($from, $to)
    {
        return $this->inner->move($this->prefix($from), $this->prefix($to));
    }

    public function size($path)
    {
        return $this->inner->size($this->prefix($path));
    }

    public function lastModified($path)
    {
        return $this->inner->lastModified($this->prefix($path));
    }

    public function files($directory = null, $recursive = false)
    {
        return $this->unprefixListing(
            $this->inner->files($directory === null ? rtrim($this->prefix, '/') : $this->prefix((string) $directory), $recursive),
        );
    }

    public function allFiles($directory = null)
    {
        return $this->unprefixListing(
            $this->inner->allFiles($directory === null ? rtrim($this->prefix, '/') : $this->prefix((string) $directory)),
        );
    }

    public function directories($directory = null, $recursive = false)
    {
        return $this->unprefixListing(
            $this->inner->directories($directory === null ? rtrim($this->prefix, '/') : $this->prefix((string) $directory), $recursive),
        );
    }

    public function allDirectories($directory = null)
    {
        return $this->unprefixListing(
            $this->inner->allDirectories($directory === null ? rtrim($this->prefix, '/') : $this->prefix((string) $directory)),
        );
    }

    public function makeDirectory($path)
    {
        return $this->inner->makeDirectory($this->prefix($path));
    }

    public function deleteDirectory($directory)
    {
        return $this->inner->deleteDirectory($this->prefix($directory));
    }
}
