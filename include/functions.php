<?php

mb_internal_encoding('UTF-8');
putenv('LANG=en_US.UTF-8');
header('Content-type: text/html; charset=UTF-8');
set_error_handler('errorHandler');

/**
 * Print a string with a newline at the end
 *
 * @param string the string to print
 */
function println($str = '')
{
	echo $str, "\n";
}

/**
 * Make a string safe for outputting into UTF-8 HTML / XML
 *
 * @param string the string to encode
 */
function html($str, $mode = ENT_COMPAT)
{
	return htmlspecialchars($str, $mode, mb_internal_encoding());
}

/**
 * Output a variable in a readable format
 *
 * @param mixed  the variable to print
 * @param string a CSS class name
 */
function printR($str, $class='')
{
	print('<pre class="' . $class . '" style="text-align: left; background-color: #ffffff !important; color: #000000 !important;">');
	ob_start();
	print_r($str);
	print(html(ob_get_clean()));
	println('</pre>');
}

/**
 * Check if a file is an image
 *
 * @param string the path on the filesystem
 *
 * @return bool
 */
function isImage($path)
{
	try
	{
		$info = getimagesize($path);
		if (($info[2] != IMAGETYPE_GIF) && ($info[2] != IMAGETYPE_JPEG) && ($info[2] != IMAGETYPE_PNG))
			return false;
	}
	catch (Exception $e)
	{
		return false;
	}
	
	return true;
}

/**
 * Empty then delete a directory
 *
 * @param string the directory to delete
 */
function deleteDir($dir)
{
	foreach (glob($dir . '/*') as $file)
		unlink($file);
	
	rmdir($dir);
}

/**
 * Throw an exception instead of producing a warning
 *
 * @param integer error number
 * @param string error string
 * @param string error file
 * @param integer error line
 * @param array error context
 *
 * @return bool false for notices and strict
 */
function errorHandler($number, $string, $file = 'Unknown', $line = 0, $context = array())
{
	if (($number == E_NOTICE) || ($number == E_STRICT))
		return false;
		
	if (!error_reporting())
		return false;
	
	throw new Exception($string, $number);
	
	return true;
}
