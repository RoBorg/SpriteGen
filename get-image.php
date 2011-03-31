<?php

session_start();

require_once 'include/functions.php';

$token = empty($_REQUEST['token']) ? $_REQUEST['token'] : session_id();
$token = preg_replace('/[\/\\]/u', '', $name);

$name = empty($_REQUEST['n']) ? '' : $_REQUEST['n'];
$name = preg_replace('/[\/\\]/u', '', $name);

$path = 'uploads/u-' . $token . '/' . $name;



if (!is_file($path) || !isImage($path))
{
	header('404 - File Not Found');
	exit;
}

$info = getimagesize($path);

header('Content-type: image/' . $type);
readfile($path);
