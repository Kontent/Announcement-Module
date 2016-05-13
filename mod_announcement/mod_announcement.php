<?php
/**
 * @version		$Id$
 * @package		Announcement
 * @copyright	(C) 2008 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

// import library dependencies
require_once (dirname(__FILE__).DS.'helper.php');

switch ($params->get('mode', 'category'))
{
	case 'feed':
		$items = &modAnnouncmentHelper::getFeedItems($params);

		break;

	case 'section':
		$items = &modAnnouncmentHelper::getSectionItems($params);

		break;

	case 'category':
		$items = &modAnnouncmentHelper::getCategoryItems($params);

		break;

	default:
	case 'frontpage':
		$items = &modAnnouncmentHelper::getFrontpageItems($params);

		break;
}

$path = JModuleHelper::getLayoutPath('mod_announcement', 'default');
if (file_exists($path)) {
	require ($path);
}
