<?php

function i_amir_remote_wc_path($path = null)
{
    $path = trim($path, '/');
    return plugin_dir_path(__DIR__) . ($path ? "/$path" : '');
}

function i_amir_remote_wc_clear_text($text) {
    return strip_tags(preg_replace('/&(?:[a-z\d]+|#\d+|#x[a-f\d]+);/i', '', $text));
}

function i_amir_remote_wc_short_text($s, $max_length = 147) {
    if (strlen($s) > $max_length)
    {
        $offset = ($max_length - 3) - strlen($s);
        return substr($s, 0, strrpos($s, ' ', $offset)) . '...';
    }
    return $s;
}