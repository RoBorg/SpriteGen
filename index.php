<?php

require 'include/functions.php';
require 'include/CssSprite.php';

ini_set('memory_limit', '512M');

$done = false;
$errors = [
    'images' => '',
    'sprite' => '',
];

if (!empty($_POST['save'])) {
    if (empty($_FILES['images']) || !is_array($_FILES['images']['name']) || (count($_FILES['images']['name']) < 2)) {
        $errors['images'] = 'Please select at least 2 image files';
    } else {
        $images = [];
        $imageErrors = [];

        foreach ($_FILES['images']['name'] as $i => $name) {
            switch ($_FILES['images']['error'][$i]) {
                case UPLOAD_ERR_OK:
                    $images[] = [
                        'name' => $_FILES['images']['name'][$i],
                        'file' => $_FILES['images']['tmp_name'][$i],
                    ];
                    break;

                case UPLOAD_ERR_INI_SIZE:
                    $imageErrors[] = $name . ': The uploaded file exceeds the upload_max_filesize directive in php.ini.';
                    break;

                case UPLOAD_ERR_FORM_SIZE:
                    $imageErrors[] = $name . ': The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
                    break;

                case UPLOAD_ERR_PARTIAL:
                    $imageErrors[] = $name . ': The uploaded file was only partially uploaded.';
                    break;

                case UPLOAD_ERR_NO_FILE:
                    break;

                case UPLOAD_ERR_NO_TMP_DIR:
                    $imageErrors[] = $name . ': Missing a temporary folder.';
                    break;

                case UPLOAD_ERR_CANT_WRITE:
                    $imageErrors[] = $name . ': Failed to write file to disk.';
                    break;

                case UPLOAD_ERR_EXTENSION:
                    $imageErrors[] = $name . ': File upload stopped by extension.';
                    break;

                default:
                    throw new Exception('Unexpected file error number "' . $_FILES['images']['error'][$i] . '"');
            }
        }

        if (empty($imageErrors)) {
            if (count($images) < 2) {
                $errors['images'] = 'Please select at least 2 image files';
            } else {
                $options = [
                    'output_type' => empty($_POST['output_type']) ? 'png' : $_POST['output_type'],
                    'jpeg_quality' => !isset($_POST['jpeg_quality']) ? 75 : (int)$_POST['jpeg_quality'],
                    'css_prefix' => empty($_POST['css_prefix']) ? '' : $_POST['css_prefix'],
                    'padding' => empty($_POST['padding']) ? 0 : (int)$_POST['padding'],
                    'jpeg_reduce_artefacts' => !empty($_POST['jpeg_reduce_artefacts']),
                ];

                $sprite = new CssSprite();

                try {
                    $sprite->run($images, $options);
                    $done = true;
                } catch (Exception $e) {
                    $errors['sprite'] = 'There was an error creating your sprite: ' . $e->getMessage();
                }
            }
        } else {
            $errors['images'] = implode(' ', $imageErrors);
        }
    }
}

if ($done) {
    $spriteDataUri = $sprite->getDataUri();
}

?><!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>CSS Sprite Generator</title>
        <link rel="shortcut icon" href="/favicon.ico">
        <link type="text/css" rel="stylesheet" href="assets/syntaxhighlighter/styles/shCoreDefault.css">
        <link type="text/css" rel="stylesheet" href="assets/style.css">

        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
        <script type="text/javascript" src="assets/syntaxhighlighter/scripts/shCore.js"></script>
        <script type="text/javascript" src="assets/syntaxhighlighter/scripts/shBrushCss.js"></script>
        <script type="text/javascript" src="assets/syntaxhighlighter/scripts/shBrushXml.js"></script>
        <script type="text/javascript">
            SyntaxHighlighter.defaults['quick-code'] = false;
            SyntaxHighlighter.all();

            (function($)
            {
                $(function()
                {
                    $('#tabs').on('click', 'li', function()
                    {
                        $('#tabs li.selected').removeClass('selected');
                        $(this).addClass('selected');

                        $('div.tab.selected').removeClass('selected');
                        $($(this).find('a').attr('href')).addClass('selected');

                        return false;
                    });
                });
            })(jQuery);
        </script>
        <?php

        if ($done) {
            print('<style type="text/css">' . $sprite->getCss($spriteDataUri) . '</style>');
        }

        ?>
    </head>
    <body>
        <div id="header">
            <div id="header-inner">
                <h1>
                    <a href="http://<?=htmlentities($_SERVER['HTTP_HOST']);?>">CSS Sprite Generator</a>
                </h1>
                <p>
                    Optimize your website using CSS Sprites!
                    Generate CSS Sprites to speed up your website by reducing HTTP requests.
                </p>
            </div>
        </div>
        <div id="page">
            <ul id="tabs">
                <li class="selected">
                    <a href="#sprites">CSS Sprites</a>
                </li>
                <li>
                    <a href="#about">About</a>
                </li>
                <li>
                    <a href="#faq">FAQ</a>
                </li>
                <li>
                    <a href="#news">News</a>
                </li>
            </ul>
            <div class="tab selected" id="sprites">
                <?php

                if ($done) {
                    ?>
                    <h2>Your Image</h2>
                    <p>Right-click to save this image.</p>
                    <img src="<?=htmlspecialchars($spriteDataUri); ?>" alt="" id="sprite">

                    <h2>Your CSS</h2>
                    <p>Save this to your CSS file.</p>
                    <pre class="brush: css;"><?=htmlspecialchars($sprite->getCss()); ?></pre>

                    <h2>Your HTML</h2>
                    <p>Use this to insert your sprite images.</p>
                    <pre class="brush: xml;"><?=htmlspecialchars($sprite->getHtml()); ?></pre>

                    <h2>Demo</h2>
                    <p>Here's what your images will look like.</p>
                    <div id="demo">
                        <?=$sprite->getHtml(); ?>
                    </div>

                    <h2>New Sprite</h2>
                    <p><a href="">Start a new sprite</a></p>
                    <?php

                } else {
                    if ($errors['sprite']) {
                        ?>
                        <p class="error first"><?=htmlspecialchars($errors['sprite']); ?></p>
                        <?php

                    } ?>
                    <form method="post" enctype="multipart/form-data">
                        <h2>CSS Sprites</h2>
                        <p>
                            <b>CSS sprites</b> allow you to combine multiple images into a single file.
                            This reduces the number of HTTP requests, speeding up page loading.
                        </p>
                        <p>If you need your sprites to be responsive, use the <a href="http://responsive-css.spritegen.com">Responsive CSS Sprite Generator</a>.</p>
                        <h2>1: Upload Your Images</h2>
                        <input type="file" name="images[]" multiple="multiple">
                        <p class="error"><?=htmlspecialchars($errors['images']); ?></p>
                        <p class="note">Select up to <?=ini_get('max_file_uploads'); ?> files, total <?=ini_get('post_max_size'); ?>B.</p>

                        <h2>2: Choose Options</h2>
                        <label>
                            Output Type:
                            <select name="output_type" id="output_type" onchange="document.getElementById('jpeg-settings').style.display = this.options[this.selectedIndex].value === 'jpeg' ? 'block' : 'none';">
                                <option value="png">PNG - Recommended</option>
                                <option value="jpeg">JPEG</option>
                                <option value="gif">GIF</option>
                            </select>
                        </label>

                        <fieldset id="jpeg-settings">
                            <legend>JPEG Settings</legend>
                            <label>
                                JPEG Artefact Removal:
                                <input type="checkbox" name="jpeg_reduce_artefacts" id="jpeg_reduce_artefacts">
                            </label>
                            <label>
                                JPEG Quality:
                                <input type="range" name="jpeg_quality" id="jpeg_quality" min="0" max="100" value="75" step="1">
                            </label>
                        </fieldset>

                        <fieldset id="other-settings">
                            <legend>Other Settings</legend>
                            <label>
                                CSS Class Prefix:
                                <input type="text" name="css_prefix" id="css_prefix">
                            </label>
                            <label>
                                Padding between images:
                                <select name="padding" id="padding">
                                    <option value="0" selected="selected">0px</option>
                                    <option value="1">1px</option>
                                    <option value="2">2px</option>
                                    <option value="3">3px</option>
                                    <option value="4">4px</option>
                                    <option value="5">5px</option>
                                    <option value="10">10px</option>
                                    <option value="20">20px</option>
                                </select>
                                (This will make your file slightly larger but can prevent images bleeding into each other)
                            </label>
                        </fieldset>

                        <h2>3: Create Your Sprite</h2>
                        <input type="hidden" name="save" value="1">
                        <input type="submit" value="Create Sprite">
                    </form>
                    <?php

                }

                ?>
            </div>
            <div class="tab" id="about">
                <h2>About</h2>
                By <a href="http://twitter.com/RoBorg" target="_blank">RoBorg</a>

                <h3>What is a CSS Sprite?</h3>
                <p>
                    A CSS Sprite is a load of images lumped together into a single image file.
                    They're used as a technique to make your websites load faster, by decreasing the number of HTTP requests your users have to make.
                    Each request will contain the overhead of HTTP headers (including cookies) and the connection's latency.
                    By using a single image file instead of many, you can dramatically decrease the time it take your pages to load.
                </p>

                <h3>What do I get and how do I use it?</h3>
                <p>This tool generates:</p>
                <ul>
                    <li>An image file</li>
                    <li>A block of CSS code</li>
                </ul>
                <p>
                    First upload the image file and add the CSS to your stylesheet.
                    Then replace your images with code to load the sprite.
                    CSS classes are generated from the image filenames you upload, so for example:
                    <code>&lt;img src="icon.png"&gt;</code>
                    might become
                    <code>&lt;div class="icon"&gt;&lt;/div&gt;</code>
                </p>
            </div>
            <div class="tab" id="faq">
                <h2>Frequently Asked Questions</h2>

                <h3>Who wrote this?</h3>
                <p>
                    Greg, AKA <a href="http://www.roborg.co.uk/" target="_blank">RoBorg</a> did - I'm a professional PHP programmer for <a href="http://www.justsayplease.co.uk/">Just Say Please</a>.<br>
                    You can <a href="http://twitter.com/RoBorg" target="_blank">follow me on Twitter</a>
                </p>
                <p>
                    <a href="http://stackoverflow.com/users/24181/greg">
                        <img src="http://stackoverflow.com/users/flair/24181.png?theme=clean" width="208" height="58" alt="profile for Greg at Stack Overflow, Q&amp;A for professional and enthusiast programmers" title="profile for Greg at Stack Overflow, Q&amp;A for professional and enthusiast programmers">
                    </a>
                </p>

                <h3>How do I report a bug?</h2>
                <p>At the moment just <a href="http://twitter.com/RoBorg" target="_blank">via Twitter</a>.</p>

                <h3>How long do you store my source images and sprite for?</h3>
                <p>They're not stored on the server.</p>

                <h3>Are images I upload private?</h3>
                <p>Yes.</p>

                <h3>Is there an API?</h3>
                <p>Yes - see the <a href="api.php?help">CSS Sprites API</a> page.</p>

                <h3>Is this project open source</h3>
                <p>Not at the moment, but if I receive enough interest I might clean up the code and release it.</p>

                <h3>How is this website written?</h3>
                <p>The sprite generator is written in PHP, using the GD image functions. The transparent PNGs are manually generated.</p>
            </div>
            <div class="tab" id="news">
                <h2>Latest News</h2>

                <h3>Aug 2017</h3>
                <ul>
                    <li>Improved API</li>
                </ul>

                <h3>May 2014</h3>
                <ul>
                    <li>Improved CSS</li>
                    <li>Improved error handling</li>
                    <li>Increased memory limit</li>
                </ul>

                <h3>Jan 2014</h3>
                <ul>
                    <li>New UI - HTML5 uploader instead of Flash</li>
                    <li>New API</li>
                    <li>Use data URIs instead of storing files</li>
                    <li>Sub-sort files by name</li>
                </ul>

                <h3>Jul 2011</h3>
                <ul>
                    <li>Improved error handling</li>
                    <li>Upgraded to YUI 2.9.0</li>
                    <li>Added Chrome warning</li>
                </ul>

                <h3>Nov 2010</h3>
                <ul>
                    <li>Fixed off-by-one error in certain circumstances</li>
                    <li>Added padding option</li>
                    <li>Added CSS class prefix</li>
                    <li>Changed layout algorithm for when all images are the same width - now uploading lots of icons will result in a square image instead of a giant column</li>
                    <li>Upgraded to PHP 5.3 - now grayscale PNGs are loaded correctly!</li>
                    <li>Added PNG compression and filters</li>
                </ul>
            </div>
        </div>
        <div id="footer"></div>
        <script type="text/javascript">
        var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
        document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
        </script>
        <script type="text/javascript">
        try {
        var pageTracker = _gat._getTracker("UA-280848-6");
        pageTracker._trackPageview();
        } catch(err) {}</script>
    </body>
</html>
