<?php

/**
 * Build a CSS Sprite
 */
class CssSprite
{
    /**
     * Align images to n-pixel boundaries to reduce artefacts
     * This should be 8 or 16
     */
    const JPEG_BOUNDARY = 16;

    /**
     * Information about the source files
     *
     * @var array
     */
    protected $inputFiles = [];

    /**
     * The GD image resource
     *
     * @var resource
     */
    protected $image;

    /**
     * The padding, in pixels, between images
     *
     * @var int
     */
    protected $padding = 0;

    /**
     * Whether to reduce JPEG artefacts or not
     *
     * @var bool
     */
    protected $reduceArtefacts = false;

    /**
     * The total width of the output sprite
     *
     * @var int
     */
    protected $width = 0;

    /**
     * The total height of the output sprite
     *
     * @var int
     */
    protected $height = 0;

    /**
     * A prefix for the CSS classes
     *
     * @var string
     */
    protected $cssPrefix;

    /**
     * The output file type: png, jpeg or gif
     *
     * @var string
     */
    protected $outputType = 'png';

    /**
     * The quality for JPEG files (0 - 100)
     *
     * @var int
     */
    protected $jpegQuality = 75;

    /**
     * Create a sprite
     *
     * @param array the images
     * @param array options
     */
    public function run($images, $options = [])
    {
        if (empty($images)) {
            throw new Exception('No images supplied');
        }

        if (!empty($options['output_type'])) {
            $this->outputType = $options['output_type'];
        }

        if (isset($options['jpeg_quality'])) {
            $this->jpegQuality = (int)$options['jpeg_quality'];
        }

        if (!empty($options['css_prefix'])) {
            $this->cssPrefix = $options['css_prefix'];
        }

        if (!empty($options['padding'])) {
            $this->padding = (int)$options['padding'];
        }

        if (!empty($options['jpeg_reduce_artefacts'])) {
            $this->reduceArtefacts = (bool)$options['jpeg_reduce_artefacts'];
        }

        foreach ($images as $image) {
            $this->addImage($image);
        }

        // Sort the images by width and name
        usort($this->inputFiles, [$this, 'sort']);

        $this->pack();
        $this->createImage();
    }

    /**
     * Align images to self::JPEG_BOUNDARY pixels if reduceArtefacts is true.
     * This alignment will help stop different component images bleeding into each other,
     * but will increase the size of the sprite
     *
     * @param int the pixel offset
     *
     * @return int the aligned pixel offset
     */
    public function toJpegBoundary($num)
    {
        if (!$this->reduceArtefacts || !($num % self::JPEG_BOUNDARY)) {
            return $num;
        }

        return $num + self::JPEG_BOUNDARY - ($num % self::JPEG_BOUNDARY);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->image) {
            imagedestroy($this->image);
        }
    }

    /**
     * Add a file to the sprite
     *
     * @param string the filesystem path of the image
     */
    public function addImage($image)
    {
        $file = $image['tmp_name'];

        $info = getimagesize($file);
        $pathInfo = pathinfo($file);
        $types = [
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpeg',
        ];

        if (empty($types[$info[2]])) {
            throw new Exception('Invalid image file - ' . $image['name']);
        }

        $this->inputFiles[] = [
            'name' => $image['name'],
            'path' => $file,
            'pathInfo' => $pathInfo,
            'width' => $info[0],
            'height' => $info[1],
            'jpegWidth' => $this->toJpegBoundary($info[0]),
            'jpegHeight' => $this->toJpegBoundary($info[1]),
            'type' => $types[$info[2]],
            'x' => 0,
            'y' => 0,
            'css' => '',
        ];
    }

    /**
     * Lay out the component images into a sprite
     */
    public function pack()
    {
        // Sort by height
        usort($this->inputFiles, [$this, 'sort']);

        // Find the widest image - this will be used to size the output image
        $width = 0;
        foreach ($this->inputFiles as $image) {
            $width = max($image['jpegWidth'], $width);
        }

        // Add on the padding on each side
        $width += $this->padding + $this->padding;
        $this->width = $width;

        // If all the images are the same width, make the image square
        // This makes large icon sets look better
        $allSameWidth = true;
        $width = 0;
        foreach ($this->inputFiles as $image) {
            if (!$width) {
                $width = $image['jpegWidth'];
            }

            if ($image['jpegWidth'] != $width) {
                $allSameWidth = false;
                break;
            }
        }

        if ($allSameWidth) {
            $this->width *= floor(sqrt(count($this->inputFiles)));
        }

        // This holds an array of arrays of right-hand edges
        $rowsRightEdges = [];
        $rowsLeftEdges = [];

        foreach ($this->inputFiles as &$image) {
            $this->packImage($image, $rowsRightEdges, $rowsLeftEdges);
        }

        $this->createImage();
    }

    /**
     * Lay out a single component image
     *
     * @param array information about the component image
     * @param int[] a list of right-hand edges of previously packed images
     * @param int[] a list of left-hand edges of previously packed images
     */
    protected function packImage(&$image, &$rowsRightEdges, &$rowsLeftEdges)
    {
        $y = -1;
        $maxX = $this->width - $this->padding - $this->padding - $image['jpegWidth'];

        foreach ($rowsRightEdges as $y => $rightHandEdges) {
            if ($this->reduceArtefacts && ($y % self::JPEG_BOUNDARY)) {
                continue;
            }

            foreach ($rightHandEdges as $rowX) {
                // If the top-left corner is not OK then skip to the next row
                if ($rowX > $maxX) {
                    continue;
                }

                $imgLeft = $rowX;
                $imgRight = $rowX + $image['jpegWidth'] + $this->padding - 1;
                $imgTop = $y;
                $imgBottom = $y + $image['jpegHeight'];

                // Check the whole height is clear so we don't overlap those lower down
                $canPlace = true;

                for ($y2 = $y; $y2 <= $imgBottom; $y2++) {
                    if (!isset($rowsRightEdges[$y2])) {
                        break;
                    }

                    // Find the last right-hand edge after our image's left hand edge
                    foreach ($rowsRightEdges[$y2] as $rowImageNumber => $right) {
                        if ($right <= $imgLeft) {
                            continue;
                        }

                        // If the left-hand edge is overlapping our image then we can't place it
                        if ($rowsLeftEdges[$y2][$rowImageNumber] <= $imgRight) {
                            $canPlace = false;
                            break;
                        }
                    }

                    if (!$canPlace) {
                        break;
                    }
                }

                if ($canPlace) {
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
     * @param int[] a list of right-hand edges of previously packed images
     * @param int[] a list of left-hand edges of previously packed images
     * @param int the x offset
     * @param int the y offset
     */
    protected function placeImage(&$image, &$rowsRightEdges, &$rowsLeftEdges, $x, $y)
    {
        if (!$x) {
            $x = $this->padding;
        }

        $image['x'] = $x;
        $image['y'] = $y;
        $image['class'] = $this->filenameToSelector($image['name']);
        $image['selector'] = $this->cssPrefix . $image['class'];
        $image['css'] = '.' . $image['selector'] . ' { background-position: -' . $x . 'px -' . $y . 'px; width: ' . $image['width'] . 'px; height: ' . $image['height'] . "px; }\n";

        $right = $x + $image['jpegWidth'] + $this->padding;
        $bottom = $y + $this->padding + $image['jpegHeight'];

        $this->height = max($this->height, $bottom);

        for ($i = $y; $i < $bottom; $i++) {
            if (empty($rowsRightEdges[$i])) {
                $rowsRightEdges[$i] = [0];
                $rowsLeftEdges[$i] = [0];
            }

            $rowsRightEdges[$i][] = $this->toJpegBoundary($right);
            $rowsLeftEdges[$i][] = $x;
        }
    }

    /**
     * Helper function to sort images by width and name
     *
     * @param mixed image one
     * @param mixed image two
     *
     * @return int sort order
     */
    protected function sort($a, $b)
    {
        if ($a['jpegWidth'] > $b['jpegWidth']) {
            return -1;
        }

        if ($a['jpegWidth'] < $b['jpegWidth']) {
            return 1;
        }

        return strcmp($a['name'], $b['name']);
    }

    /**
     * Create the GD image resource
     */
    protected function createImage()
    {
        $img = imagecreatetruecolor($this->width, $this->height);

        // Fill with transparency
        imagealphablending($img, false);
        $c = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefilledrectangle($img, 0, 0, $this->width - 1, $this->height - 1, $c);

        // Copy in the images
        foreach ($this->inputFiles as $image) {
            $f = 'imagecreatefrom' . $image['type'];
            $src = $f($image['path']);

            imagecopy($img, $src, $image['x'], $image['y'], 0, 0, $image['width'], $image['height']);

            if ($this->reduceArtefacts) {
                for ($x = $image['width']; $x < $image['jpegWidth']; $x++) {
                    imagecopy($img, $src, $image['x'] + $x, $image['y'], $image['width'] - 1, 0, 1, $image['height']);
                }

                for ($y = $image['height']; $y < $image['jpegHeight']; $y++) {
                    imagecopy($img, $img, $image['x'], $image['y'] + $y, $image['x'], $image['y'] + $image['height'] - 1, $image['jpegWidth'], 1);
                }
            }

            imagedestroy($src);
        }

        imagesavealpha($img, true);
        $this->image = $img;
    }

    /**
     * Get an output image from the GD resource
     *
     * @return string the image file data
     */
    public function getImage()
    {
        $f = 'image' . $this->outputType;

        $options = [];

        if ($this->outputType == 'jpeg') {
            $options['quality'] = $this->jpegQuality;
        }

        ob_start();

        array_unshift($options, null);
        array_unshift($options, $this->image);
        call_user_func_array($f, $options);

        $image = ob_get_clean();

        return $image;
    }

    /**
     * Get CSS rules to display the sprite
     *
     * @return string
     */
    public function getCss($url = '')
    {
        $css = '';
        $baseRule = '';

        if ($url == '') {
            $url = $this->outputType . '.' . ($this->outputType === 'jpeg' ? 'jpg' : $this->outputType);
        }

        foreach ($this->inputFiles as $i => $image) {
            $baseRule .= ($baseRule ? ', ' : '') . ($i % 5 ? '' : "\n") . '.' . $image['selector'];
            $css .= $image['css'];
        }

        $css = "/* Generated by http://css.spritegen.com CSS Sprite Generator */\n"
            . $baseRule . "\n{ display: inline-block; background: url('" . $url . "') no-repeat; overflow: hidden; text-indent: -9999px; text-align: left; }\n\n" . $css;

        return $css;
    }

    /**
     * Get the HTML to display the sprites
     *
     * @return string
     */
    public function getHtml()
    {
        $html = '';

        foreach ($this->inputFiles as $image) {
            $html .= '<div class="' . $image['selector'] . '"></div>' . "\n";
        }

        return $html;
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
        $str = preg_replace('/\.[^.]*$/u', '', $str);
        $str = preg_replace('/\'/u', '', $str);
        $str = preg_replace('/[^a-z0-9]/u', '-', $str);
        $str = preg_replace('/-+/u', '-', $str);
        $str = preg_replace('/(^-)|(-$)/u', '', $str);

        if (($this->cssPrefix === '') && preg_match('/^[0-9]/', $str)) {
            $str = 'img-' . $str;
        }

        return $str;
    }

    /**
     * Get the image as a data URI
     */
    public function getDataUri()
    {
        return 'data:image/' . $this->outputType . ';base64,' . base64_encode($this->getImage());
    }
}
