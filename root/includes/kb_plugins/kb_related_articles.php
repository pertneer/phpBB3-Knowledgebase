<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_related_articles.php 420 2010-01-13 14:36:10Z softphp $
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
	$acp_options['legend1'] 			= 'RELATED_ARTICLES';
	$acp_options['kb_related_articles'] = array('lang' => 'KB_SHOW_RELART',	'validate' => 'int',	'type' => 'text:3:5', 		'explain' => true);
	$acp_options['kb_related_articles_menu']		= array('lang' => 'WHICH_MENU',			'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);
		
	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_RELATED',
		'PLUGIN_DESC'			=> 'PLUGIN_RELATED_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.1',
		'PLUGIN_MENU'			=> RIGHT_MENU,
		'PLUGIN_PERM'			=> true,
		'PLUGIN_PAGES'			=> array('view_article'),
	);
}

// Get latest article
function related_articles($cat_id = 0)
{
	global $template;
	
	// Everything parsed through kb.php and kb_functions.php
	$content = kb_parse_template('related_articles', 'related_articles.html');
	
	return $content;
}

function related_articles_versions()
{
	$versions = array(
		'1.0.0'	=> array(			
			'config_add'	=> array(
				//array('kb_related_articles', 5),
				array('kb_related_menu', RIGHT_MENU),
			),
		),
		
		'1.0.1'	=> array(			
			'config_remove'	=> array(
				array('kb_related_menu'),
			),
			
			'config_add'	=> array(
				array('kb_related_articles_menu', RIGHT_MENU),
			),
		),
	);

	return $versions;
}
?>