<?php

// This should be 8 or 16
define('CSSSPRITES_JPEG_BOUNDARY', 16);

/**
 * Build a CSS Sprite
 */
class CssSprite
{
	/**
	 * Information about the source files
	 *
	 * @var array
	 */
	protected $inputFiles = array();
	
	/**
	 * The GD image resource
	 *
	 * @var resource
	 */
	protected $img;
	
	/**
	 * The padding, in pixels, between images
	 *
	 * @var integer
	 */
	protected $padding;
	
	/**
	 * Whether to reduce JPEG artefacts or not
	 *
	 * @var bool
	 */
	protected $reduceArtefacts;
	
	/**
	 * The total width of the output sprite
	 *
	 * @var integer
	 */
	protected $width = 0;
	
	/**
	 * The total height of the output sprite
	 *
	 * @var integer
	 */
	protected $height = 0;
	
	/**
	 * A prefix for the CSS classes
	 *
	 * @var string
	 */
	protected $prefix;
	
	/**
	 * Constructor
	 *
	 * @param integer the padding between images
	 * @param bool reduce JPEG artefacts on output sprite
	 * @param string CSS class name prefix
	 */
	public function __construct($padding = 3, $reduceArtefacts = false, $prefix = '.')
	{
		$this->reduceArtefacts = $reduceArtefacts;
		$this->padding = $this->toJpegBoundary($padding);
		$this->prefix = $prefix;
	}
	
	/**
	 * Align images to CSSSPRITES_JPEG_BOUNDARY pixels if reduceArtefacts is true.
	 * This alignment will help stop different component images bleeding into each other,
	 * but will increase the size of the sprite
	 *
	 * @param integer the pixel offset
	 *
	 * @return integer the aligned pixel offset
	 */
	public function toJpegBoundary($num)
	{
		if (!$this->reduceArtefacts || !($num % CSSSPRITES_JPEG_BOUNDARY))
			return $num;
		
		return $num + CSSSPRITES_JPEG_BOUNDARY - ($num % CSSSPRITES_JPEG_BOUNDARY);
	}
	
	/**
	 * Destructor
	 */
	public function __destruct()
	{
		if ($this->img)
			imagedestroy($this->img);
	}
	
	/**
	 * Add a file to the sprite
	 *
	 * @param string the filesystem path of the image
	 */
	public function addFile($file)
	{
		$info = getimagesize($file);
		$pathInfo = pathinfo($file);
		$types = array
		(
			IMAGETYPE_GIF => 'gif',
			IMAGETYPE_PNG => 'png',
			IMAGETYPE_JPEG => 'jpeg',
		);
		
		if (empty($types[$info[2]]))
			throw new Exception('Invalid image file - ' . $pathInfo['basename']);
		
		$this->inputFiles[] = array
		(
			'path' => $file,
			'pathInfo' => $pathInfo,
			'width' => $info[0],
			'height' => $info[1],
			'jpegWidth' => $this->toJpegBoundary($info[0]),
			'jpegHeight' => $this->toJpegBoundary($info[1]),
			'type' => $types[$info[2]],
			'x' => 0,
			'y' => 0,
		);
	}
	
	/**
	 * Lay out the component images into a sprite
	 */
	public function pack()
	{
		// Sort by height
		usort($this->inputFiles, array($this, 'sort'));
		
		// Find the widest image - this will be used to size the output image
		$width = 0;
		foreach ($this->inputFiles as $image)
			$width = max($image['jpegWidth'], $width);
		
		// Add on the padding on each side
		$width += $this->padding + $this->padding;
		$this->width = $width;
		
		// If all the images are the same width, make the image square
		// This makes large icon sets look better
		$allSameWidth = true;
		$width = 0;
		foreach ($this->inputFiles as $image)
		{
			if (!$width)
				$width = $image['jpegWidth'];
			
			if ($image['jpegWidth'] != $width)
			{
				$allSameWidth = false;
				break;
			}
		}
		
		if ($allSameWidth)
			$this->width *= floor(sqrt(count($this->inputFiles)));
		
		// This holds an array of arrays of right-hand edges
		$rowsRightEdges = array();
		$rowsLeftEdges = array();
		
		foreach ($this->inputFiles as &$image)
			$this->packImage($image, $rowsRightEdges, $rowsLeftEdges);
		
		$this->buildImage();
	}
	
	/**
	 * Lay out a single component image
	 *
	 * @param array information about the component image
	 * @param integer[] a list of right-hand edges of previously packed images
	 * @param integer[] a list of left-hand edges of previously packed images
	 */
	protected function packImage(&$image, &$rowsRightEdges, &$rowsLeftEdges)
	{
		$y = -1;
		$maxX = $this->width - $this->padding - $this->padding - $image['jpegWidth'];
		
		foreach ($rowsRightEdges as $y => $rightHandEdges)
		{
			if ($this->reduceArtefacts && ($y % CSSSPRITES_JPEG_BOUNDARY))
				continue;
			
			foreach ($rightHandEdges as $rowX)
			{
				// If the top-left corner is not OK then skip to the next row
				if ($rowX > $maxX)
					continue;
				
				$imgLeft = $rowX;
				$imgRight = $rowX + $image['jpegWidth'] + $this->padding;
				$imgTop = $y;
				$imgBottom = $y + $image['jpegHeight'];
				
				// Check the whole height is clear so we don't overlap those lower down
				$canPlace = true;
				
				for ($y2 = $y; $y2 <= $imgBottom; $y2++)
				{
					if (!isset($rowsRightEdges[$y2]))
						break;
					
					// Find the last right-hand edge after our image's left hand edge
					foreach ($rowsRightEdges[$y2] as $rowImageNumber => $right)
					{
						if ($right <= $imgLeft)
							continue;
						
						// If the left-hand edge is overlapping our image then we can't place it
						if ($rowsLeftEdges[$y2][$rowImageNumber] <= $imgRight)
						{
							$canPlace = false;
							break;
						}
					}
					
					if (!$canPlace)
						break;
				}
				
				if ($canPlace)
				{
					$this->placeImage($image, $rowsRightEdges, $rowsLeftEdges, $rowX, $y);
					return;
				}
			}
		}
		
		$this->placeImage($image, $rowsRightEdges, $rowsLeftEdges, 0, $y + 1);
	}
	
	/**
	 * Put a component position in a specific place
	 *
	 * @param array information about the component image
	 * @param integer[] a list of right-hand edges of previously packed images
	 * @param integer[] a list of left-hand edges of previously packed images
	 * @param integer the x offset
	 * @param integer the y offset
	 */
	protected function placeImage(&$image, &$rowsRightEdges, &$rowsLeftEdges, $x, $y)
	{
		if (!$x)
			$x = $this->padding;
		
		$image['x'] = $x;
		$image['y'] = $y;
		$image['class'] = $this->filenameToSelector($image['pathInfo']['filename']);
		$image['selector'] = $this->prefix . $image['class'];
		$image['css'] = $image['selector'] . " { background-position: -" . $x . "px -" . $y . "px; width: " . $image['width'] . "px; height: " . $image['height'] . "px; }\n";
		
		$right = $x + $image['jpegWidth'] + $this->padding;
		$bottom = $y + $this->padding + $image['jpegHeight'];
		
		$this->height = max($this->height, $bottom);
		
		for ($i = $y; $i < $bottom; $i++)
		{
			if (empty($rowsRightEdges[$i]))
			{
				$rowsRightEdges[$i] = array(0);
				$rowsLeftEdges[$i] = array(0);
			}
			
			$rowsRightEdges[$i][] = $this->toJpegBoundary($right);
			$rowsLeftEdges[$i][] = $x;
		}
	}
	
	/**
	 * Helper function to sort images by height
	 *
	 * @param mixed image one
	 * @param mixed image two
	 *
	 * @return integer sort order
	 */
	protected function sort($a, $b)
	{
		if ($a['height'] > $b['height'])
			return -1;
		
		if ($a['height'] < $b['height'])
			return 1;
		
		
		return 0;
	}
	
	/**
	 * Create the GD image resource
	 */
	protected function buildImage()
	{
		$img = imagecreatetruecolor($this->width, $this->height);
		
		// Fill with transparency
		imagealphablending($img, false);
		$c = imagecolorallocatealpha($img, 255, 255, 255, 127);
		imagefilledrectangle($img, 0, 0, $this->width - 1, $this->height - 1, $c);
		
		// Copy in the images
		foreach ($this->inputFiles as $image)
		{
			$f = 'imagecreatefrom' . $image['type'];
			$src = $f($image['path']);
			
			imagecopy($img, $src, $image['x'], $image['y'], 0, 0, $image['width'], $image['height']);
			
			if ($this->reduceArtefacts)
			{
				for ($x = $image['width']; $x < $image['jpegWidth']; $x++)
					imagecopy($img, $src, $image['x'] + $x, $image['y'], $image['width'] - 1, 0, 1, $image['height']);
				
				for ($y = $image['height']; $y < $image['jpegHeight']; $y++)
					imagecopy($img, $img, $image['x'], $image['y'] + $y, $image['x'], $image['y'] + $image['height'] - 1, $image['jpegWidth'], 1);
			}
		}
		
		imagesavealpha($img, true);
		$this->image = $img;
	}
	
	/**
	 * Get an output image from the GD resource
	 *
	 * @param string the image type - one of the imagexxx() GD functions
	 * @param array options to pass to the GD function
	 *
	 * @return string the image file data
	 */
	public function getImage($type = 'png', $options = array())
	{
		$f = 'image' . $type;
		ob_start();
		
		array_unshift($options, null);
		array_unshift($options, $this->image);
		call_user_func_array($f, $options);
		
		$image = ob_get_clean();
		
		//$image = $this->smush($url, $type);
		
		return $image;
	}
	
	/**
	 * Compress a file using the smushit API
	 * smushit API has been removed at the moment :(
	 *
	 * @param string the filesystem path of the image
	 * @param string the URL the image can be loaded from
	 * @param string the filesystem path to save the new image to - defaults to overwriting the existing file
	 */
	public function smush($filepath, $url, $newFilepath = '')
	{
		if (!$newFilepath)
			$newFilepath = $filepath;
		
		$json = file_get_contents('http://smush.it/ws.php?img=' . urlencode($url));
		$json = json_decode($json);
		
		if ($json->dest_size != -1)
		{
			$img = file_get_contents('http://smush.it/' . $json->dest);
			file_put_contents($newFilepath, $img);
		}
		
		return true;
	}
	
	/**
	 * Get all the information stored about the source images
	 *
	 * @return array
	 */
	public function getInfo()
	{
		return $this->inputFiles;
	}
	
	/**
	 * Get CSS rules to display the sprite
	 *
	 * @return string
	 */
	public function getCss()
	{
		$css = '';
		$baseRule = '';
		
		foreach ($this->inputFiles as $i => $image)
		{
			$baseRule .= ($baseRule ? ', ' : '') . ($i % 5 ? '' : "\n") . $image['selector'];
			$css .= $image['css'];
		}
		
		$css = "/* Generated by http://css.spritegen.com CSS Sprite Generator */\n"
			. $baseRule . "\n{ display: block; background: url('tmp.png') no-repeat; }\n\n" . $css;
		
		return $css;
	}
	
	/**
	 * Convert a filename to a CSS-friendly selector fragment
	 *
	 * @param string the filename
	 *
	 * @return string the selector fragment
	 */
	protected function filenameToSelector($str)
	{
		$str = mb_strtolower($str);
		$str = preg_replace('/\'/u', '', $str);
		$str = preg_replace('/[^a-z0-9]/u', '-', $str);
		$str = preg_replace('/-+/u', '-', $str);
		$str = preg_replace('/(^-)|(-$)/u', '', $str);
		
		return $str;
	}
	
	/**
	 * Add all the image files in a directory to the sprite
	 *
	 * @param string the filesystem path of the directory
	 */
	public function addDirectory($dir)
	{
		foreach (new DirectoryIterator($dir) as $file)
		{
			if (preg_match('/\.(png|jpe?g|gif)$/ui', $file->getFilename()))
				$this->addFile($file->getPathname());
		}
	}
}
