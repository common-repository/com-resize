<?php
/*
Plugin Name: com_resize
Plugin URI: http://www.vanutsteen.nl
Description: Finds images in your posts and if the image sizes specified in the <img> tag are smaller then the images on disk, they are resized.
	Also works for remote linked images.
	It is so simple, it doesn't even need configuration. If it doesn't behave as excpected: mail me, or edit the source :)
Author: Leon Bogaert
Version: 0.1.6
Author URI: http://www.vanutsteen.nl
*/

/* There are three ways to define an image:
 1. http://blog.com/wordpress/imgs/test.jpg (remote)
 2. imgs/test.jpg (relative)
 3. /wordpress/imgs/test.jpg (absolute to docroot)
 
 I have to make sure all of them work
*/

function com_resize($text) {
	define('MAX_DIF', 20);
	define('SITE_URL', get_bloginfo('home'));
	$replacements = array();
	
	if (!is_dir(ABSPATH . "/wp-content/plugins/com-resize/phpthumb")) {
		return $text;
	}

	// simple performance check to determine whether bot should process further
	if ( strpos( $text, '<img' ) === false ) {
		return $text;
	}

	preg_match_all('#<img.*src=[\"\'](.*)[\"\'].*/>#isU', $text, $image_matches);
	
	#de src alleen
	foreach ($image_matches[1] as $key => $src) {
		$link = $image_matches[0][$key];
		$src = str_replace(SITE_URL . '/', '', $src);

        //Check, when it's a absolute url, if it matches the siteurl
        $regex = '#^' . SITE_URL . '#isU';
        preg_replace($regex, '', $src);
        
        //Check if it not already contains a phpThumb:
        if (strstr($src, 'wp-content/plugins/com-resize/phpthumb/phpThumb.php?')) {
        	continue;
        }
        
		$remote = strstr($src, 'http://') ? true : false;
		$absolute_to_docroot = substr($src, 0, 1) == '/' ? true : false; 
		$src_decoded = urldecode($src);

		$regex = '#width=[\"\'](.*)[\"\']#isU';
		preg_match($regex, $link, $matches);
		$width = $matches[1];
		$regex = '#height=[\"\'](.*)[\"\']#isU';
		preg_match($regex, $link, $matches);
		$height = $matches[1];

		if (!$height && !$width) {
			continue;
		}
		
		if ($height) {
			$dimensions = "&h=$height";
		}
		if ($width) {
			$dimensions .= "&w=$width";
		}

		if (!$remote) {
			if ($absolute_to_docroot) {
				//If it's relative to the server docroot (eg. /wordpress/img/test_image.jpg) treat it different
				$file = $_SERVER['DOCUMENT_ROOT'] . "$src_decoded";
				$src = substr($file, strlen(ABSPATH)); //this is to retrieve the relative url (img/test_image.jpg)
			} else {
				$file = ABSPATH . "$src_decoded";
			}

			//Checken of het resizen uberhaupt wel nodig is
			if (is_file(urldecode($file))) {
				$current_dimensions = getimagesize($file);
				
				$current_width = $current_dimensions[0];
				$current_height = $current_dimensions[1];

				$height_dif = ltrim(($current_height - $height), '-');
				$width_dif = ltrim(($current_width - $width), '-');

				//Als de breedte en hoogte van het origineel en het gewenste minder dan 20 scheelt: skippen
				if ($width_dif < MAX_DIF && $height_dif < MAX_DIF) {
					continue;
				}
			}
			
			$new_src = "wp-content/plugins/com-resize/phpthumb/phpThumb.php?src=/{$src}{$dimensions}";
		}

		//Overbodige if, maar wel duidelijker
		if ($remote) {
			$src_url_encoded = urlencode($src);
			$new_src = "wp-content/plugins/com-resize/phpthumb/phpThumb.php?src={$src_url_encoded}{$dimensions}";
		}

		//In beide gevallen: de replacements erin zetten
		$new_link = str_replace($src, $new_src, $link);
		$replacements[$link] = $new_link;
	}

	//Nu daadwerkelijk alles vervangen
	foreach ($replacements as $old_link => $new_link) {
		$text = str_replace($old_link, $new_link, $text);
	}
	
	return $text;

}

function imageExtensions() {
        return array(
        'gif',
        'jpeg',
        'png',
        'bmp',
        'jpg'
        );
}

function isImageExtension($ext) {
        return in_array(strtolower($ext), imageExtensions());
}

add_filter('the_content', 'com_resize');
?>
