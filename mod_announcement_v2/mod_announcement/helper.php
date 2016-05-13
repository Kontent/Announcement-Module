<?php
/**
 * @version		$Id$
 * @package		Announcement
 * @copyright	(C) 2008 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License
 */

// no direct access
defined('_JEXEC') or die('Restricted access');


class modAnnouncmentHelper
{

	function &getFeedItems(&$params)
	{
		$db		= &JFactory::getDBO();
		$user	= &JFactory::getUser();
		$config	= &JFactory::getConfig();


		// module params
		$rssurl	= $params->get('rssurl', '');

		//  get RSS parsed object
		$options = array();
		$options['rssUrl'] = $rssurl;
		if ($params->get('cache')) {
			$options['cache_time']  = $params->get('cache_time', 60);
			$options['cache_time']	*= 60;
		} else {
			$options['cache_time'] = null;
		}

		$rssDoc =& JFactory::getXMLparser('RSS', $options);

		$feed = new stdclass();

		if ($rssDoc != false)
		{
			// channel header and link
			$feed->title = $rssDoc->get_title();
			$feed->link = $rssDoc->get_link();
			$feed->description = $rssDoc->get_description();

			// channel image if exists
			$feed->image->url = $rssDoc->get_image_url();
			$feed->image->title = $rssDoc->get_image_title();

			// items
			$items = $rssDoc->get_items();

			// feed elements
			$feed->items = array_slice($items, 0, (int)$params->get('num_items', 3));
		} else {
			$feed = false;
		}

		return $feed;

	}

	function &getCategoryItems(&$params)
	{
		// get user and access id values
		$db		= &JFactory::getDBO();
		$user	= &JFactory::getUser();
		$aid	= $user->get('aid');

		// get some date values for the query
		$date		= &JFactory::getDate();
		$now		= $date->toMySQL();
		$nullDate	= $db->getNullDate();
		
		//JXComments integration
		$jxcomments = (int)$params->get('jxcomments');

		// get and sanitize the category ids
		jimport('joomla.utilities.arrayhelper');
		$category_ids = (array)$params->get('cat_ids');
		JArrayHelper::toInteger($category_ids);

		// build the query to get article information
		$db->setQuery(
			'SELECT a.*, cc.description as catdesc, cc.title as cattitle, s.description as secdesc, s.title as sectitle,'.
			' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(":", a.id, a.alias) ELSE a.id END as slug,'.
			' CASE WHEN CHAR_LENGTH(cc.alias) THEN CONCAT_WS(":", cc.id, cc.alias) ELSE cc.id END as catslug,'.
			' CASE WHEN CHAR_LENGTH(s.alias) THEN CONCAT_WS(":", s.id, s.alias) ELSE s.id END as secslug'.
			' FROM #__content AS a'.
			' INNER JOIN #__categories AS cc ON cc.id = a.catid'.
			' INNER JOIN #__sections AS s ON s.id = a.sectionid'.
			' WHERE a.state = 1'.
			' AND a.access <= '.(int) $aid.' AND cc.access <= '.(int) $aid.' AND s.access <= '.(int) $aid .
			' AND (a.publish_up = '.$db->Quote($nullDate).' OR a.publish_up <= '.$db->Quote($now).' ) '.
			' AND (a.publish_down = '.$db->Quote($nullDate).' OR a.publish_down >= '.$db->Quote($now).' )'.
			' AND (a.catid = '.implode(' OR a.catid = ', $category_ids).' )' .
			' AND cc.section = s.id'.
			' AND cc.published = 1'.
			' AND s.published = 1'.
			' ORDER BY a.created DESC',
			0, (int)$params->get('num_items', 3)
		);
		$rows = $db->loadObjectList();
		
		// import library dependencies
		require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');

		for ($i=0,$n=count($rows); $i < $n; $i++)
		{
			$rows[$i]->article_link  = JRoute::_(ContentHelperRoute::getArticleRoute($rows[$i]->slug, $rows[$i]->catslug, $rows[$i]->sectionid));
			$rows[$i]->category_link = JRoute::_(ContentHelperRoute::getCategoryRoute($rows[$i]->catslug, $rows[$i]->sectionid));
			$rows[$i]->section_link  = JRoute::_(ContentHelperRoute::getSectionRoute($rows[$i]->sectionid));
			$rows[$i]->date = $rows[$i]->created;
			$rows[$i]->text = modAnnouncmentHelper::processText($rows[$i], $params);
			if($jxcomments)
			{
				$rows[$i]->num_comments = modAnnouncmentHelper::getNumComments($rows[$i]->id);
			}
		}

		return $rows;
	}

	function &getSectionItems(&$params)
	{
		// get user and access id values
		$db		= &JFactory::getDBO();
		$user	= &JFactory::getUser();
		$aid	= $user->get('aid');

		// get some date values for the query
		$date		= &JFactory::getDate();
		$now		= $date->toMySQL();
		$nullDate	= $db->getNullDate();
		
		//JXComments integration
		$jxcomments = (int)$params->get('jxcomments');

		// get and sanitize the category ids
		jimport('joomla.utilities.arrayhelper');
		$section_ids = (array)$params->get('sec_ids');
		JArrayHelper::toInteger($section_ids);

		// build the query to get article information
		$db->setQuery(
			'SELECT a.*, cc.description as catdesc, cc.title as cattitle, s.description as secdesc, s.title as sectitle,'.
			' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(":", a.id, a.alias) ELSE a.id END as slug,'.
			' CASE WHEN CHAR_LENGTH(cc.alias) THEN CONCAT_WS(":", cc.id, cc.alias) ELSE cc.id END as catslug,'.
			' CASE WHEN CHAR_LENGTH(s.alias) THEN CONCAT_WS(":", s.id, s.alias) ELSE s.id END as secslug'.
			' FROM #__content AS a'.
			' INNER JOIN #__categories AS cc ON cc.id = a.catid'.
			' INNER JOIN #__sections AS s ON s.id = a.sectionid'.
			' WHERE a.state = 1'.
			' AND a.access <= '.(int) $aid.' AND cc.access <= '.(int) $aid.' AND s.access <= '.(int) $aid .
			' AND (a.publish_up = '.$db->Quote($nullDate).' OR a.publish_up <= '.$db->Quote($now).' ) '.
			' AND (a.publish_down = '.$db->Quote($nullDate).' OR a.publish_down >= '.$db->Quote($now).' )'.
			' AND (a.sectionid = '.implode(' OR a.sectionid = ', $section_ids).' )' .
			' AND cc.section = s.id'.
			' AND cc.published = 1'.
			' AND s.published = 1'.
			' ORDER BY a.created DESC',
			0, (int)$params->get('num_items')
		);
		$rows = $db->loadObjectList();

		// import library dependencies
		require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');

		for ($i=0,$n=count($rows); $i < $n; $i++)
		{
			$rows[$i]->article_link  = JRoute::_(ContentHelperRoute::getArticleRoute($rows[$i]->slug, $rows[$i]->catslug, $rows[$i]->sectionid));
			$rows[$i]->category_link = JRoute::_(ContentHelperRoute::getCategoryRoute($rows[$i]->catslug, $rows[$i]->sectionid));
			$rows[$i]->section_link  = JRoute::_(ContentHelperRoute::getSectionRoute($rows[$i]->sectionid));
			$rows[$i]->date = $rows[$i]->created;
			$rows[$i]->text = modAnnouncmentHelper::processText($rows[$i], $params);
			if($jxcomments)
			{
				$rows[$i]->num_comments = modAnnouncmentHelper::getNumComments($rows[$i]->id);
			}
		}

		return $rows;
	}

	function &getFrontpageItems(&$params)
	{
		// get user and access id values
		$db		= &JFactory::getDBO();
		$user	= &JFactory::getUser();
		$aid	= $user->get('aid');

		// get some date values for the query
		$date		= &JFactory::getDate();
		$now		= $date->toMySQL();
		$nullDate	= $db->getNullDate();
		
		//JXComments integration
		$jxcomments = (int)$params->get('jxcomments');

		// build the query to get article information
		$db->setQuery(
			'SELECT a.*, cc.description as catdesc, cc.title as cattitle, s.description as secdesc, s.title as sectitle,'.
			' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(":", a.id, a.alias) ELSE a.id END as slug,'.
			' CASE WHEN CHAR_LENGTH(cc.alias) THEN CONCAT_WS(":", cc.id, cc.alias) ELSE cc.id END as catslug,'.
			' CASE WHEN CHAR_LENGTH(s.alias) THEN CONCAT_WS(":", s.id, s.alias) ELSE s.id END as secslug'.
			' FROM #__content AS a'.
			' INNER JOIN #__categories AS cc ON cc.id = a.catid'.
			' INNER JOIN #__sections AS s ON s.id = a.sectionid'.
			' INNER JOIN #__content_frontpage AS f ON f.content_id = a.id'.
			' WHERE a.state = 1'.
			' AND a.access <= '.(int) $aid.' AND cc.access <= '.(int) $aid.' AND s.access <= '.(int) $aid .
			' AND (a.publish_up = '.$db->Quote($nullDate).' OR a.publish_up <= '.$db->Quote($now).' ) '.
			' AND (a.publish_down = '.$db->Quote($nullDate).' OR a.publish_down >= '.$db->Quote($now).' )'.
			' AND cc.section = s.id'.
			' AND cc.published = 1'.
			' AND s.published = 1'.
			' ORDER BY f.ordering',
			0, (int)$params->get('num_items')
		);
		$rows = $db->loadObjectList();

		// import library dependencies
		require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');

		for ($i=0,$n=count($rows); $i < $n; $i++)
		{
			$rows[$i]->article_link  = JRoute::_(ContentHelperRoute::getArticleRoute($rows[$i]->slug, $rows[$i]->catslug, $rows[$i]->sectionid));
			$rows[$i]->category_link = JRoute::_(ContentHelperRoute::getCategoryRoute($rows[$i]->catslug, $rows[$i]->sectionid));
			$rows[$i]->section_link  = JRoute::_(ContentHelperRoute::getSectionRoute($rows[$i]->sectionid));
			$rows[$i]->date = $rows[$i]->created;
			$rows[$i]->text = modAnnouncmentHelper::processText($rows[$i], $params);
			if($jxcomments)
			{
				$rows[$i]->num_comments = modAnnouncmentHelper::getNumComments($rows[$i]->id);
			}
		}

		return $rows;
	}

	/**
	 *
	 */
	function processText(&$row, &$params)
	{
		// setup some variables
		$row->thumbnail = '';
		if (isset($row->text)) {
			$text = $row->text;
		} else {
			$text = $row->introtext.$row->fulltext;
		}

		// if we are supposed to show an article thumbnail, lets go get it.
		if ($params->get('show_thumb')) {

			// lets get the first image out of the article and use it for the main image
			preg_match("@<img[^>]*src=\"([^\"]*)\"[^>]*>@Usi", $text, $matches);
			$images = (count($matches)) ? $matches : array ();
			if (count($images)) {
				$image = $images[1];
			}

			// did we find an image?
			if ($image) {
				$align = ($params->get('thumb_align')) ? ' align="'.$params->get('thumb_align').'"' : '';
				if ($params->get('auto_thumbnail')
				  and function_exists('imagecreatetruecolor')
				  and ($thumb = modAnnouncmentHelper::_processThumbnail($image, $params->get('thumb_width'), $params->get('thumb_height')))) {
					$image = '<img src="'.$thumb.'" alt="'.$row->title.'"'.$align.' />';
				} else {
					$width	= ($params->get('thumb_width')) ? ' width="'.(int)$params->get('thumb_width').'"' : '';
					$height	= ($params->get('thumb_height')) ? ' height="'.(int)$params->get('thumb_height').'"' : '';
					$image	= '<img src="'.$image.'" alt="'.$row->title.'"'.$align.$height.$width.' />';
				}
				$row->thumbnail = $image;
			}
		}

		// strip tags and cleanup whitespace
		$text = strip_tags($text);
		$text = preg_replace('|  +|', ' ', $text);

		// word limit check
		$max_words = (int) $params->get('max_words');
		if ($max_words)
		{
			$words = explode(' ', $text);
			if (count($words) > $max_words) {
				$words = array_splice($words, 0, $max_words);
				$text = implode(' ', $words);
				$text .= '...';
			} else {
				$text = implode(' ', $words);
			}
		}

		return $text;
	}

	function _processThumbnail($image, $width, $height)
	{
		// if there is no image present to thumbnail, then just return
		if (!$image) {
			return;
		}

		// sanitize values
		$width = (int)$width;
		$height = (int)$height;


		$img = str_replace(JURI::base(), '', $image);
		$img = rawurldecode($img);

		$thumb = '';
		if (file_exists(JPATH_SITE.'/'.$img)) {
			//$thumb = modAnnouncmentHelper::_generateThumbnail($img, $width, $height);

			$imageSize   = getimagesize(JPATH_SITE.'/'.$img);
			$imageWidth  = $imageSize[0];
			$imageHeight = $imageSize[1];

			if (!$width and !$height) {
				$max_width	= $imageWidth;
				$max_height	= $imageHeight;
			} else {
				$max_width	= ($width) ? $width : 1000;
				$max_height	= ($height) ? $height : 1000;
			}

			$x_ratio = $max_width / $imageWidth;
			$y_ratio = $max_height / $imageHeight;

			if (($imageWidth <= $max_width) && ($imageHeight <= $max_height)) {
				// don't make any larger thumb than the orig.img
				$tn_width = $imageWidth;
				$tn_height = $imageHeight;
			} else {
				if (($x_ratio * $imageHeight) < $max_height) {
					$tn_height = ceil($x_ratio * $imageHeight);
					$tn_width = $max_width;
				} else {
					$tn_width = ceil($y_ratio * $imageWidth);
					$tn_height = $max_height;
				}
			}


			// generate thumbnail filename
			jimport('joomla.filesystem.file');
			$ext = JFile::getExt($img);
			$noExt = JFile::stripExt($img);
			$thumb = $noExt.'_'.$tn_width.'x'.$tn_height.'.'.$ext;

			$thumbPath = JPATH_SITE.'/images/thumbs/'.$thumb;
			if (file_exists($thumbPath)) {
				$smallImg = getimagesize($thumbPath);
				if (($smallImg[0] <= $tn_width and $smallImg[1] == $tn_height) or ($smallImg[1] <= $tn_height and $smallImg[0] == $tn_width)) {
					return 'images/thumbs/'.$thumb;
				}
			}

			// make sure the necessary folder is created for the thumbnail
			jimport('joomla.filesystem.folder');
			JFolder::create(dirname($thumbPath));

			switch ($ext)
			{
				case 'jpg' : // jpg
					$src = imagecreatefromjpeg(JPATH_SITE.'/'.$image);
					break;
				case 'png' : // png
					$src = imagecreatefrompng(JPATH_SITE.'/'.$image);
					imagesavealpha($src, true);
					break;
				case 'gif' : // gif
					$src = imagecreatefromgif(JPATH_SITE.'/'.$image);
					break;
				default :
			}
			$dst = imagecreatetruecolor($tn_width, $tn_height);
			imagealphablending($dst, false);
			imagesavealpha($dst, true);

			// set antialiasing to true if available
			if (function_exists('imageantialias')) {
				imageantialias($dst, true);
			}

			// create the thumbnail image resource
			imagecopyresampled($dst, $src, 0, 0, 0, 0, $tn_width, $tn_height, $imageWidth, $imageHeight);

			// write the thumbnail to the filesystem
			switch ($ext)
			{
				case 'jpg' : // jpg
					imagejpeg($dst, $thumbPath, 90);
					break;
				case 'png' : // png
					imagepng($dst, $thumbPath);
					break;
				case 'gif' : // gif
					imagegif($dst, $thumbPath);
					break;
				default :
			}

			return 'images/thumbs/'.$thumb;
		}

		return $thumb;
	}
	
	function getNumComments($context_id)
	{
		$db		= &JFactory::getDBO();
		
		$db->setQuery(
			'SELECT COUNT(*)'.
			' FROM `tgfa_jxcomments_comments` jxc'.
			' INNER JOIN `tgfa_jxcomments_threads` jxt'.
			' ON jxc.thread_id = jxt.id'.
			' WHERE jxt.context_id='.$db->Quote($context_id)
		);
		
		return $db->loadResult();
	}
}
