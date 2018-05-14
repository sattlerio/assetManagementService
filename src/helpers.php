<?php
/**
 * Created by PhpStorm.
 * User: ghovat
 * Date: 14.05.18
 * Time: 13:06
 */

function validateBucketList($bucketName, $bucketList) {

    try {
        $buckets = $bucketList["Buckets"];

        foreach ($buckets as $bucket) {
            if ($bucket["Name"] === $bucketName) {
                return true;
                break;
            }
        }
        return false;
    } catch (Exception $exception) {
        return $exception;
    }
}

function get_extension($file) {
    $extension = end(explode(".", $file));
    return $extension ? $extension : false;
}

function uuid(){
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}