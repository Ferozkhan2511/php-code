<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-code-coverage.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace SebastianBergmann\CodeCoverage\StaticAnalysis;

use const DIRECTORY_SEPARATOR;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function hash;
use function serialize;
use function unserialize;
use SebastianBergmann\CodeCoverage\Directory;
use SebastianBergmann\LinesOfCode\LinesOfCode;

/**
 * @todo Refactor how Node\File objects are created so a Singleton of this is not required
 */
final class CachingExecutedFileAnalyser implements ExecutedFileAnalyser
{
    /**
     * @var ExecutedFileAnalyser
     */
    private static $instance;

    /**
     * @var string
     */
    private $directory;

    /**
     * @var ExecutedFileAnalyser
     */
    private $executedFileAnalyser;

    public static function createInstance(string $directory, bool $useAnnotationsForIgnoringCode, bool $ignoreDeprecatedCode): ExecutedFileAnalyser
    {
        self::$instance = new self(
            $directory,
            new ParsingExecutedFileAnalyser(
                $useAnnotationsForIgnoringCode,
                $ignoreDeprecatedCode
            )
        );

        return self::$instance;
    }

    public static function getInstance(): ExecutedFileAnalyser
    {
        return self::$instance;
    }

    public function __construct(string $directory, ExecutedFileAnalyser $executedFileAnalyser)
    {
        Directory::create($directory);

        $this->directory            = $directory;
        $this->executedFileAnalyser = $executedFileAnalyser;
    }

    public function classesIn(string $filename): array
    {
        if ($this->cacheHas($filename, __METHOD__)) {
            return $this->cacheRead($filename, __METHOD__);
        }

        $data = $this->executedFileAnalyser->classesIn($filename);

        $this->cacheWrite($filename, __METHOD__, $data);

        return $data;
    }

    public function traitsIn(string $filename): array
    {
        if ($this->cacheHas($filename, __METHOD__)) {
            return $this->cacheRead($filename, __METHOD__);
        }

        $data = $this->executedFileAnalyser->traitsIn($filename);

        $this->cacheWrite($filename, __METHOD__, $data);

        return $data;
    }

    public function functionsIn(string $filename): array
    {
        if ($this->cacheHas($filename, __METHOD__)) {
            return $this->cacheRead($filename, __METHOD__);
        }

        $data = $this->executedFileAnalyser->functionsIn($filename);

        $this->cacheWrite($filename, __METHOD__, $data);

        return $data;
    }

    public function linesOfCodeFor(string $filename): LinesOfCode
    {
        if ($this->cacheHas($filename, __METHOD__)) {
            return $this->cacheRead($filename, __METHOD__, [LinesOfCode::class]);
        }

        $data = $this->executedFileAnalyser->linesOfCodeFor($filename);

        $this->cacheWrite($filename, __METHOD__, $data);

        return $data;
    }

    public function ignoredLinesFor(string $filename): array
    {
        if ($this->cacheHas($filename, __METHOD__)) {
            return $this->cacheRead($filename, __METHOD__);
        }

        $data = $this->executedFileAnalyser->ignoredLinesFor($filename);

        $this->cacheWrite($filename, __METHOD__, $data);

        return $data;
    }

    private function cacheFile(string $filename, string $method): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $filename . $method);
    }

    private function cacheHas(string $filename, string $method): bool
    {
        $cacheFile = $this->cacheFile($filename, $method);

        if (!file_exists($cacheFile)) {
            return false;
        }

        if (filemtime($cacheFile) < filemtime($filename)) {
            return false;
        }

        return true;
    }

    /**
     * @psalm-param list<string> $allowedClasses
     *
     * @return mixed
     */
    private function cacheRead(string $filename, string $method, array $allowedClasses = [])
    {
        $options = ['allowed_classes' => false];

        if (!empty($allowedClasses)) {
            $options = ['allowed_classes' => $allowedClasses];
        }

        return unserialize(
            file_get_contents(
                $this->cacheFile($filename, $method)
            ),
            [
                'allowed_classes' => $allowedClasses,
            ]
        );
    }

    /**
     * @param mixed $data
     */
    private function cacheWrite(string $filename, string $method, $data): void
    {
        file_put_contents(
            $this->cacheFile($filename, $method),
            serialize($data)
        );
    }
}