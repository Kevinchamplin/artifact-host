<?php

namespace App\Services;

use App\Exceptions\PleskException;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Filesystem layer for site doc roots.
 *
 * V1 implementation: direct local FS writes — this service runs on the same VPS
 * as Plesk, executed by the subscription owner (typically the user that owns
 * /var/www/vhosts/<apex>/...). Future off-box deployment can swap this for
 * phpseclib3 SFTP without changing the public surface.
 */
class SftpService
{
    /**
     * Atomically replace the doc root contents with the supplied files.
     *
     * @param  array<int, array{path:string, content:string, encoding?:string}>  $files
     * @return array{file_count:int, bytes:int}
     */
    public function deploy(string $docRoot, array $files): array
    {
        $this->assertSafeDocRoot($docRoot);

        $staging = $docRoot.'.deploy-'.Str::random(8);
        $previous = $docRoot.'.old-'.Str::random(8);

        $this->mkdir($staging);

        try {
            [$count, $bytes] = $this->writeFiles($staging, $files);

            if (is_dir($docRoot)) {
                $this->run(['mv', $docRoot, $previous]);
            }
            $this->run(['mv', $staging, $docRoot]);

            if (is_dir($previous)) {
                $this->run(['rm', '-rf', $previous]);
            }

            return ['file_count' => $count, 'bytes' => $bytes];
        } catch (\Throwable $e) {
            if (is_dir($staging)) {
                @$this->run(['rm', '-rf', $staging]);
            }
            throw $e;
        }
    }

    public function teardown(string $docRoot): void
    {
        $this->assertSafeDocRoot($docRoot);
        if (is_dir($docRoot)) {
            $this->run(['rm', '-rf', $docRoot]);
        }
    }

    /**
     * @param  array<int, array{path:string, content:string, encoding?:string}>  $files
     * @return array{0:int, 1:int}
     */
    private function writeFiles(string $base, array $files): array
    {
        $maxFileBytes = (int) config('artifacthost.limits.max_file_bytes');
        $maxTotalBytes = (int) config('artifacthost.limits.max_total_bytes');

        $count = 0;
        $bytes = 0;

        foreach ($files as $file) {
            $rel = $this->normalizeRelativePath($file['path'] ?? '');
            $encoding = $file['encoding'] ?? 'utf8';
            $raw = $file['content'] ?? '';

            $data = match ($encoding) {
                'utf8' => $raw,
                'base64' => base64_decode($raw, true),
                default => throw new \InvalidArgumentException("unsupported encoding: {$encoding}"),
            };

            if ($data === false) {
                throw new \InvalidArgumentException("invalid base64 in file: {$rel}");
            }

            $size = strlen($data);
            if ($size > $maxFileBytes) {
                throw new \InvalidArgumentException("file exceeds max size ({$maxFileBytes} bytes): {$rel}");
            }
            $bytes += $size;
            if ($bytes > $maxTotalBytes) {
                throw new \InvalidArgumentException("total deploy exceeds {$maxTotalBytes} bytes");
            }

            $abs = $base.'/'.$rel;
            $this->mkdir(dirname($abs));

            if (file_put_contents($abs, $data) === false) {
                throw new \RuntimeException("failed to write file: {$rel}");
            }
            chmod($abs, 0644);
            $count++;
        }

        return [$count, $bytes];
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '') {
            throw new \InvalidArgumentException('empty file path');
        }

        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new \InvalidArgumentException("path traversal not allowed: {$path}");
            }
            if (! preg_match('/^[A-Za-z0-9._-]+$/', $segment)) {
                throw new \InvalidArgumentException("invalid path segment: {$segment}");
            }
            $parts[] = $segment;
        }

        if (empty($parts)) {
            throw new \InvalidArgumentException("invalid path: {$path}");
        }

        return implode('/', $parts);
    }

    private function assertSafeDocRoot(string $docRoot): void
    {
        $sub = config('artifacthost.subscription_domain') ?: config('artifacthost.apex');
        $expectedPrefix = '/var/www/vhosts/'.$sub.'/';
        if (! str_starts_with($docRoot, $expectedPrefix)) {
            throw new PleskException("doc root outside vhosts: {$docRoot}");
        }
    }

    private function mkdir(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new \RuntimeException("failed to create directory: {$path}");
        }
    }

    /** @param array<int,string> $argv */
    private function run(array $argv): void
    {
        $process = new Process($argv);
        $process->setTimeout(60);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                '%s failed: %s',
                $argv[0],
                trim($process->getErrorOutput() ?: $process->getOutput())
            ));
        }
    }
}
