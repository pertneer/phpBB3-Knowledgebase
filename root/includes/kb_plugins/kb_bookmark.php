<?php
/**
*
* @package phpBB phpBB3-Knowledgebase Mod (KB)
* @version $Id: kb_bookmark.php $
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
	$acp_options['kb_bookmark_menu']	= array('lang' => 'WHICH_MENU',			'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);
	$acp_options['legend2'] 			= 'KB_SOCBOOK_LIST';
	$acp_options['kb_soc_bookmarks']	= array('lang' => 'KB_ALLOW_SOCBOOK',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => true);
	$acp_options['kb_blogger']			= array('lang' => 'KB_ALLOW_BLOGGER', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_delicious']		= array('lang' => 'KB_ALLOW_DELICIOUS', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_digg']				= array('lang' => 'KB_ALLOW_DIGG', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_facebook']			= array('lang' => 'KB_ALLOW_FACEBOOK', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_friend']			= array('lang' => 'KB_ALLOW_FRIEND', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_google']			= array('lang' => 'KB_ALLOW_GOOGLE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_linked_in']		= array('lang' => 'KB_ALLOW_LINKED_IN', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_live']				= array('lang' => 'KB_ALLOW_LIVE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_mixx']				= array('lang' => 'KB_ALLOW_MIXX', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_myspace']			= array('lang' => 'KB_ALLOW_MYSPACE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_netvibes']			= array('lang' => 'KB_ALLOW_NETVIBES', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_reddit']			= array('lang' => 'KB_ALLOW_REDDIT', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_stumble']			= array('lang' => 'KB_ALLOW_STUMBLE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_technorati']		= array('lang' => 'KB_ALLOW_TECHNORATI', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_twitter']			= array('lang' => 'KB_ALLOW_TWITTER', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);
	$acp_options['kb_wordpress']		= array('lang' => 'KB_ALLOW_WORDPRESS', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => false);

	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_BOOK',
		'PLUGIN_DESC'			=> 'PLUGIN_BOOK_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.1',
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
		'1.0.1'	=> array(
			
		),
		'1.0.0'	=> array(
			'config_add'	=> array(
				array('kb_bookmark_menu', RIGHT_MENU),
			),
		),
	);

	return $versions;
}
?>