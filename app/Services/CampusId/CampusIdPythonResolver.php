<?php

namespace App\Services\CampusId;

use Illuminate\Support\Facades\Process;

class CampusIdPythonResolver
{
    private static ?string $resolvedBinary = null;

    public static function binary(): string
    {
        if (self::$resolvedBinary !== null) {
            return self::$resolvedBinary;
        }

        $configured = trim((string) config('services.campus_id.ocr_python', ''));
        if ($configured !== '') {
            return self::$resolvedBinary = $configured;
        }

        foreach (self::candidates() as $candidate) {
            if (self::hasOcrDependencies($candidate)) {
                return self::$resolvedBinary = $candidate;
            }
        }

        if (PHP_OS_FAMILY === 'Windows' && is_file('C:\\Python312\\python.exe')) {
            return self::$resolvedBinary = 'C:\\Python312\\python.exe';
        }

        return self::$resolvedBinary = PHP_OS_FAMILY === 'Windows' ? 'py' : 'python3';
    }

    /**
     * @return list<string>
     */
    private static function candidates(): array
    {
        $candidates = [];

        $projectVenv = base_path('.venv-campus-id-ocr/Scripts/python.exe');
        if (is_file($projectVenv)) {
            $candidates[] = $projectVenv;
        }

        $venvPython = base_path('hardware/ai_parking/.venv/Scripts/python.exe');
        if (is_file($venvPython)) {
            $candidates[] = $venvPython;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['312', '311', '310', '39'] as $version) {
                $path = "C:\\Python{$version}\\python.exe";
                if (is_file($path)) {
                    $candidates[] = $path;
                }
            }

            $candidates[] = 'py';
            $candidates[] = 'python3';
            $candidates[] = 'python';
        } else {
            $candidates[] = 'python3';
            $candidates[] = 'python';
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return list<string>
     */
    public static function commandPrefix(string $binary): array
    {
        if ($binary === 'py') {
            return ['py', '-3'];
        }

        if (str_contains($binary, ' ') && ! str_contains($binary, '\\')) {
            return preg_split('/\s+/', $binary) ?: [$binary];
        }

        return [$binary];
    }

    private static function hasOcrDependencies(string $binary): bool
    {
        static $cache = [];

        if (array_key_exists($binary, $cache)) {
            return $cache[$binary];
        }

        $command = array_merge(
            self::commandPrefix($binary),
            ['-c', 'import cv2; from rapidocr_onnxruntime import RapidOCR']
        );

        $result = Process::timeout(15)->run($command);
        $cache[$binary] = $result->successful();

        return $cache[$binary];
    }
}
