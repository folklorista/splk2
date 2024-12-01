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
        if (self::$level === LogLevel::DEBUG && $level === LogLevel::DEBUG) {
            self::writeLog($message);
        } elseif ($level === LogLevel::INFO) {
            self::writeLog($message);
        } elseif ($level === LogLevel::WARNING
            && in_array(self::$level, [LogLevel::DEBUG, LogLevel::INFO, LogLevel::WARNING])) {
            self::writeLog($message);
        } elseif ($level === LogLevel::ERROR
            && in_array(self::$level, [LogLevel::DEBUG, LogLevel::INFO, LogLevel::WARNING, LogLevel::ERROR])) {
            self::writeLog($message);
        }
    }

    private static function writeLog($message)
    {
        $date = date('Y-m-d H:i:s');
        if (!is_string($message)) {
            $message = json_encode($message);
        }
        $log = "$date: $message\n";
        file_put_contents(self::$path, $log, FILE_APPEND);
    }
}
