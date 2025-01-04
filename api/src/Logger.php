<?php

namespace App;

class Logger
{
    private static string $path;
    private static LogLevel $level;

    public function __construct($config)
    {
        self::$path = $config['path'];
        self::$level = $config['level'];
    }

    public static function log($message, $level = LogLevel::INFO)
    {
        // Získání informace o místě volání
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]) ? $backtrace[1] : $backtrace[0];
        $file = isset($caller['file']) ? $caller['file'] : 'unknown file';
        $line = isset($caller['line']) ? $caller['line'] : 'unknown line';
    
        if (self::$level === LogLevel::DEBUG && $level === LogLevel::DEBUG) {
            self::writeLog($message, $file, $line);
        } elseif ($level === LogLevel::INFO) {
            self::writeLog($message, $file, $line);
        } elseif ($level === LogLevel::WARNING
            && in_array(self::$level, [LogLevel::DEBUG, LogLevel::INFO, LogLevel::WARNING])) {
            self::writeLog($message, $file, $line);
        } elseif ($level === LogLevel::ERROR
            && in_array(self::$level, [LogLevel::DEBUG, LogLevel::INFO, LogLevel::WARNING, LogLevel::ERROR])) {
            self::writeLog($message, $file, $line);
        }
    }    

    private static function writeLog($message, $file, $line)
    {
        $date = date('Y-m-d H:i:s');
        if (!is_string($message)) {
            $message = json_encode($message);
        }
        $log = "$date [$file:$line]: $message\n";
        file_put_contents(self::$path, $log, FILE_APPEND);
    }
}
