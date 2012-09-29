<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_contributors.php 420 2010-01-13 14:36:10Z softphp $
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
	$acp_options['legend1'] 			= 'CONTRIBUTORS';
	$acp_options['kb_show_contrib']		= array('lang' => 'KB_SHOW_CONTRIB',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => true);
	$acp_options['kb_contributors_menu']		= array('lang' => 'WHICH_MENU',			'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);
	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_CONTRIB',
		'PLUGIN_DESC'			=> 'PLUGIN_CONTRIB_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.1',
		'PLUGIN_MENU'			=> LEFT_MENU,
		'PLUGIN_PERM'			=> true,
		'PLUGIN_PAGES'			=> array('view_article'),
	);
}

// Get latest article
function contributors()
{
	global $template;
	
	// Everything parsed in kb.php
	
	$content = kb_parse_template('contributors', 'contributors.html');
	
	return $content;
}

function contributors_versions()
{
	$versions = array(
		'1.0.0'	=> array(			
			'config_add'	=> array(
				//array('kb_show_contrib', 1),
				array('kb_contrib_menu', LEFT_MENU),
			),
		),
		
		'1.0.1'	=> array(			
			'config_remove'	=> array(
				array('kb_contrib_menu'),
			),
			
			'config_add'	=> array(
				array('kb_contributors_menu', LEFT_MENU),
			),
		),
	);

	return $versions;
}
?>