<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_search.php 420 2010-01-13 14:36:10Z softphp $
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
	$acp_options['legend1'] 			= 'SEARCH';
	$acp_options['kb_search_enable'] 	= array('lang' => 'ENABLE_SEARCH',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' 	=> false);
	$acp_options['kb_search_menu']		= array('lang' => 'WHICH_MENU',			'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);

	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_SEARCH',
		'PLUGIN_DESC'			=> 'PLUGIN_SEARCH_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.0',
		'PLUGIN_MENU'			=> LEFT_MENU,
		'PLUGIN_PERM'			=> true,
		'PLUGIN_PAGES'			=> array('all'),
	);
}

// Get latest article
function search($cat_id = 0)
{
	global $config, $template, $phpbb_root_path, $phpEx;

	if (!$config['kb_search_enable'])
	{
		return;
	}

	// For search
	$cat_search = ($cat_id == 0) ? '' : '&amp;cat_ids[]=' . $cat_id;

	// Some default template variables
	$template->assign_vars(array(
		'U_KB_SEARCH'		=> append_sid("{$phpbb_root_path}kb.$phpEx", 'i=search' . $cat_search),
		'U_KB_SEARCH_ADV'	=> append_sid("{$phpbb_root_path}kb.$phpEx", 'i=search'),
	));

	$content = kb_parse_template('search', 'search.html');

	return $content;
}

function search_versions()
{
	$versions = array(
		'1.0.0'	=> array(
			'config_add'	=> array(
				array('kb_search_enable', 1),
				array('kb_search_menu', LEFT_MENU),
			),
		),
	);

	return $versions;
}
?>