<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_bookmark.php 420 2010-01-13 14:36:10Z softphp $
* @copyright (c) 2009 Andreas Nexmann, Tom Martin
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

// Only add these options if in acp
if (defined('IN_KB_PLUGIN'))
{
	$acp_options['legend1'] 			= 'BOOKMARK_OPTIONS';
	$acp_options['kb_allow_subscribe']	= array('lang' => 'KB_ALLOW_SUB',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false);
	$acp_options['kb_allow_bookmarks']	= array('lang' => 'KB_ALLOW_BOOK',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false);
	$acp_options['kb_soc_bookmarks']	= array('lang' => 'KB_ALLOW_SOCBOOK',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false);
	$acp_options['kb_bookmark_menu']	= array('lang' => 'WHICH_MENU',			'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);

	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_BOOK',
		'PLUGIN_DESC'			=> 'PLUGIN_BOOK_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.0',
		'PLUGIN_MENU'			=> RIGHT_MENU,
		'PLUGIN_PERM'			=> true,
		'PLUGIN_PAGES'			=> array('view_article'),
	);
}

// Get latest article
function bookmark()
{
	global $template;

	// Parsed elsewhere

	$content = kb_parse_template('bookmark', 'bookmark.html');

	return $content;
}

function bookmark_versions()
{
	$versions = array(
		'1.0.0'	=> array(
			'config_add'	=> array(
				array('kb_bookmark_menu', RIGHT_MENU),
			),
		),
	);

	return $versions;
}
?>