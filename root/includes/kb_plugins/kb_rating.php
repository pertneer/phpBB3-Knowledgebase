<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_rating.php 420 2010-01-13 14:36:10Z softphp $
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
	$acp_options['legend1'] 			= 'KB_SORT_RATING';
	$acp_options['kb_rating_menu']		= array('lang' => 'WHICH_MENU',			'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);

	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_RATE',
		'PLUGIN_DESC'			=> 'PLUGIN_RATE_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.0',
		'PLUGIN_MENU'			=> LEFT_MENU,
		'PLUGIN_PERM'			=> true,
		'PLUGIN_PAGES'			=> array('view_article'),
	);
}

// Get latest article
function rating()
{
	global $template;

	// Everything parsed in kb.php

	$content = kb_parse_template('rating', 'rating.html');

	return $content;
}

function rating_versions()
{
	$versions = array(
		'1.0.0'	=> array(
			'config_add'	=> array(
				array('kb_rating_menu', LEFT_MENU),
			),
		),
	);

	return $versions;
}
?>