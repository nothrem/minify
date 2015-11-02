<?php
/**
 * Groups configuration for default Minify implementation
 * @package Minify
 */

/** 
 * You may wish to use the Minify URI Builder app to suggest
 * changes. http://yourdomain/min/builder/
 *
 * See https://github.com/mrclay/minify/blob/master/docs/CustomServer.wiki.md for other ideas
 **/

$groups = array();

$css = (include '../../application/configs/css.php');
$js  = (include '../../application/configs/js.php');

unset($css['_dont_preload']);
unset($js['_dont_preload']);

foreach (array($css, $js) as $list) {
	foreach ($list as $group => $files) {
		if (is_array($files)) {
			$groups[$group] = $files;
		} //$files is string and that means it's from CDN and is not needed in Minify
	}
}

return $groups;
