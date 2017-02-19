<?php

namespace DDZ;

class Console
{
    public static function out($content) {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        if (!is_scalar($content)) {
            fwrite(STDOUT, print_r($content, true));
        } else {
            fwrite(STDOUT, $content . PHP_EOL);
        }
    }

    public static function error($content, $exit = 1, $print_backtrace = true) {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        fwrite(STDERR, $content . PHP_EOL);

        if ($print_backtrace) {
            debug_print_backtrace();
        }

        if ($exit) {
            exit($exit);
        }
    }
}