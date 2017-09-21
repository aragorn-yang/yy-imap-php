<?php

namespace Imap;


class Helper
{
    public static function convertEncoding(string $string, string $fromEncoding, string $toEncoding): string
    {
        if ($string && $fromEncoding !== $toEncoding) {
            return @mb_convert_encoding($string, $toEncoding, $fromEncoding);
        }

        return $string;
    }
}