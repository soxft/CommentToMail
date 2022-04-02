<?php
/**
 * CommentToMail
 * 调试日志工具
 * 
 * Copied from https://stackoverflow.com/a/47235990
 */

function get_log($log_msg)
{
    $log_filename = "log";
    if (!file_exists($log_filename)) 
    {
        // create directory/folder uploads.
        mkdir($log_filename, 0777, true);
    }
    $log_file_data = $log_filename.'/log.txt';
    // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
    file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
} 

// call to function
// get_log("this is my log message");
