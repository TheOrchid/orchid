<?php

namespace Orchid\Dashboard\Services\Log;

use Illuminate\Support\Facades\File;
use Psr\Log\LogLevel;
use ReflectionClass;
use Storage;

class LogViewer
{
    const MAX_FILE_SIZE = 10485760;
    /**
     * @var string file
     */
    private static $file;
    private static $levels_classes = [
        'debug' => 'info',
        'info' => 'info',
        'notice' => 'info',
        'warning' => 'warning',
        'error' => 'danger',
        'critical' => 'danger',
        'alert' => 'danger',
        'emergency' => 'danger',
    ];
    private static $levels_imgs = [
        'debug' => 'info',
        'info' => 'info',
        'notice' => 'info',
        'warning' => 'warning',
        'error' => 'warning',
        'critical' => 'warning',
        'alert' => 'warning',
        'emergency' => 'warning',
    ]; // Why? Uh... Sorry

    /**
     * @param string $file
     */
    public static function setFile($file)
    {
        if (File::exists(storage_path('logs/') . $file)) {
            self::$file = $file;
        }
    }

    /**
     * @param $file
     *
     * @return string
     *
     * @throws \Exception
     *
     * @deprecated
     */
    public static function pathToLogFile($file)
    {
        $logsPath = storage_path('logs/');
        if (!File::exists($file)) { // try the absolute path
            $file = $logsPath . '/' . $file;
        }
        // check if requested file is really in the logs directory
        if (dirname($file) !== $logsPath) {
            throw new \Exception('No such log file');
        }

        return $file;
    }

    /**
     * @return string
     */
    public static function getFileName()
    {
        return basename(self::$file);
    }

    /**
     * @return array
     */
    public static function all()
    {
        $log = array();
        $log_levels = self::getLogLevels();
        $pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\].*/';
        if (!self::$file) {
            $log_file = self::getFiles();
            if (!count($log_file)) {
                return [];
            }
            self::$file = $log_file[0];
        }

        if (File::size(storage_path('logs/') . self::$file) > self::MAX_FILE_SIZE) {
            return;
        }

        $file = File::get(storage_path('logs/') . self::$file);
        preg_match_all($pattern, $file, $headings);
        if (!is_array($headings)) {
            return $log;
        }
        $log_data = preg_split($pattern, $file);
        if ($log_data[0] < 1) {
            array_shift($log_data);
        }
        foreach ($headings as $h) {
            for ($i = 0, $j = count($h); $i < $j; ++$i) {
                foreach ($log_levels as $level_key => $level_value) {
                    if (strpos(strtolower($h[$i]), '.' . $level_value)) {
                        preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?\.' . $level_key . ': (.*?)( in .*?:[0-9]+)?$/',
                            $h[$i], $current);
                        if (!isset($current[2])) {
                            continue;
                        }
                        $log[] = array(
                            'level' => $level_value,
                            'level_class' => self::$levels_classes[$level_value],
                            'level_img' => self::$levels_imgs[$level_value],
                            'date' => $current[1],
                            'text' => $current[2],
                            'in_file' => isset($current[3]) ? $current[3] : null,
                            'stack' => preg_replace("/^\n*/", '', $log_data[$i]),
                        );
                    }
                }
            }
        }

        return array_reverse($log);
    }

    /**
     * @return array
     */
    private static function getLogLevels()
    {
        $class = new ReflectionClass(new LogLevel());

        return $class->getConstants();
    }

    /**
     * @return array
     */
    public static function getFiles()
    {
        $files = Storage::disk('logs')->allFiles();
        foreach ($files as $key => $value) {
            if (!stripos($value, '.log')) {
                unset($files[$key]);
            }
        }

        return array_values($files);
    }
}
