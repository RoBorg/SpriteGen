<?php

ini_set('html_errors', false);

$response = new StdClass();
$response->message = '';
$response->error = false;

try
{
	session_start();

	require_once 'include/functions.php';
	require_once 'include/CssSprite.php';

	/*
	// Debugging
	ob_start();
	printR($_REQUEST);
	print_R($_FILES);
	file_put_contents('tmp.log', ob_get_clean());
	*/

	$token = isset($_REQUEST['token']) ? $_REQUEST['token'] : session_id();
	$dir = 'uploads/u-' . $token;

	if (isset($_REQUEST['getToken']))
	{
		$response->token = $token;
	}
	elseif (isset($_REQUEST['upload']))
	{
		if (preg_match('/[^a-z0-9]|^$/', $token))
			throw new Exception('Invalid token');
		
		if (!is_dir($dir))
		{
			if (!@mkdir($dir))
			{
				// It could have been created by another thread
				if (!is_dir($dir))
					throw new Exception('Error creating directory');
			}
		}
		
		receiveUpload($response, $dir);
	}
	elseif (isset($_REQUEST['create']))
	{
		ini_set('memory_limit', '64M');
		
		if (!is_dir($dir))
			throw new Exception('Images not found - Either you didn\'t upload any or your session expired');
		
		$padding = isset($_REQUEST['padding']) ? (int)$_REQUEST['padding'] : 3;
		$padding = min(max(0, $padding), 25);
		
		$type = 'png';
		if (!empty($_REQUEST['output_type']) && in_array($_REQUEST['output_type'], array('jpeg', 'gif')))
			$type = $_REQUEST['output_type'];
		$reduceArtefacts = !empty($_REQUEST['jpeg_reduce_artefacts']) && ($type == 'jpeg');
		$prefix = '.';
		if (!empty($_REQUEST['css_prefix']))
			$prefix .= preg_replace('/[^a-z0-9_-]/ui', '', $_REQUEST['css_prefix']);
		
		$s = new CssSprite($padding, $reduceArtefacts, $prefix);
		$s->addDirectory($dir);
		$s->pack();
		
		$options = array();
		if (($type == 'jpeg') && isset($_REQUEST['jpeg_quality']))
			$options = array(min(100, max(0, (int)$_REQUEST['jpeg_quality'])));
		elseif ($type == 'png')
			$options = array(9, PNG_ALL_FILTERS);
		
		$filepath = 'uploads/' . $token . '.' . $type;
		file_put_contents($filepath, $s->getImage($type, $options));
		
		$server = $_SERVER['HTTP_HOST'] == 'localhost:96' ? 'dev.justsayplease.co.uk:96' : $_SERVER['HTTP_HOST'];
		$response->url = 'http://' . $server . dirname($_SERVER['PHP_SELF']) . '/uploads/' . $token . '.' . $type;
		$response->css = $s->getCss();
		
		//$s->smush($filepath, $response->url);
		
		$oldSize = 0;
		$response->info = array();
		foreach ($s->getInfo() as $image)
		{
			$response->info[] = array
			(
				'file' => $image['pathInfo']['basename'],
				'x' => $image['x'],
				'y' => $image['y'],
				'width' => $image['width'],
				'height' => $image['height'],
				'class' => $image['class'],
				'selector' => $image['selector'],
				'css' => $image['css'],
			);
			
			$oldSize += filesize($image['path']);
		}
		
		$response->oldSize = $oldSize;
		$response->newSize = filesize($filepath);
		
		$info = getimagesize($filepath);
		$response->width = $info[0];
		$response->height = $info[1];
	}
	elseif (isset($_REQUEST['cleanup']))
	{
		if (isset($_REQUEST['token']))
		{
			if (preg_match('/^[a-z0-9]+$/i', $_REQUEST['token']))
			{
				deleteDir('uploads/u-' . $_REQUEST['token']);
				
				foreach (glob('uploads/u-' . $_REQUEST['token'] . '.*') as $file)
					unlink($file);
			}
		}
		else
		{
			$cutoff = time() - (0 * 60);
			
			foreach (new DirectoryIterator('uploads') as $f)
			{
				if ($f->isDot() || !$f->isDir() || !preg_match('/^u-(.+)$/', $f->getFilename(), $matches))
					continue;
				
				if ($f->getCTime() < $cutoff)
				{
					deleteDir($f->getPathname());
					
					foreach (glob('uploads/' . $matches[1] . '.*') as $file)
						unlink($file);
				}
			}
		}
		
		$response->message = 'Cleanup complete';
	}
	elseif (isset($_REQUEST['help']))
	{
		print(file_get_contents('include/help.html'));
		exit;
	}
}
catch (Exception $e)
{
	$response->error = true;
	$response->message = $e->getMessage();
}

header('Content-type: application/json');
print(json_encode($response));






/**
 * Receive an image or zip of images in $_FILES['img'] and save the image(s) to the specified directory
 *
 * @param object object to store response messages
 * @param string filesystem directory to save image(s) to
 *
 * @return bool true on success
 */
function receiveUpload($response, $dir)
{
	if (empty($_FILES['img']))
	{
		$response->message = 'No file specified';
		$response->error = true;
		
		return false;
	}
	
	$errors = array
	(
		'Success',
		'File too large (exceeds upload_max_filesize in php.ini)',
		'File too large (exceeds MAX_FILE_SIZE)',
		'Partial file received',
		'No file specified',
		'No tmp dir',
		'Could not write to disk',
	);
	
	if ($_FILES['img']['error'])
	{	
		$response->message = $errors[$_FILES['img']['error']];
		$response->error = true;
		
		return false;
	}
	
	$zip = zip_open($_FILES['img']['tmp_name']);
	if ($zip && is_resource($zip))
	{
		$i = 0;
		
		while ($zip_entry = zip_read($zip))
		{
			$path = zip_entry_name($zip_entry);
			$pathInfo = pathinfo($path);
			$filename = $pathInfo['basename'];
			$dest = $dir . '/' . $filename;
			
			if (empty($pathInfo['extension']))
				continue;
			
			if (!in_array(mb_strtolower($pathInfo['extension']), array('png', 'gif', 'jpg', 'jpeg')))
				continue;
			
			if (zip_entry_open($zip, $zip_entry, "r"))
			{
				file_put_contents($dest, zip_entry_read($zip_entry, zip_entry_filesize($zip_entry)));
				zip_entry_close($zip_entry);
				
				if (!isImage($dest))
					unlink($dest);
				else
					$i++;
			}
		}
		
		zip_close($zip);
		
		$response->message = 'Unzipped ' . $i . ' images';
		return true;
	}
	
	// Check the file is OK
	if (!isImage($_FILES['img']['tmp_name']))
	{
		$response->message = 'Invalid image file: Please use PNG, JPEG and GIF files only';
		$response->error = true;
		
		return false;
	}
	
	if (!move_uploaded_file($_FILES['img']['tmp_name'], $dir . '/' . $_FILES['img']['name']))
	{
		$response->message = 'Error moving uploaded file';
		$response->error = true;
		
		return false;
	}
	
	$response->message = 'Uploaded successfully';
	return true;
}
