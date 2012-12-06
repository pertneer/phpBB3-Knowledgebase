<?php
/**
*
* @package phpBB phpBB3-Knowledgebase Mod  (KB)
* @version $Id: kb_stats.php $
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
	$acp_options['legend1'] 			= 'KB_STATS';
	$acp_options['kb_stats_enable'] 	= array('lang' => 'ENABLE_STATS',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' 	=> false);
	$acp_options['kb_stats_menu']		= array('lang' => 'WHICH_MENU',			'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);

	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_STATISTICS',
		'PLUGIN_DESC'			=> 'PLUGIN_STATISTICS_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.0',
		'PLUGIN_MENU'			=> LEFT_MENU,
		'PLUGIN_PERM'			=> true,
		'PLUGIN_PAGES'			=> array('all'),
	);
}

// Get latest article
function stats($cat_id = 0)
{
	global $config, $template, $phpbb_root_path, $phpEx, $user;

	if (!$config['kb_stats_enable'])
	{
		return;
	}

	// Some default template variables
	$template->assign_vars(array(
		'TOTAL_KB_CAT'		=> $config['kb_total_cats'],
		'TOTAL_KB_ARTICLES'	=> $config['kb_total_articles'],
		'TOTAL_KB_COMMENTS'	=> $config['kb_total_comments'],
		'LAST_UPDATED'		=> $user->format_date($config['kb_last_updated']),
	));

	$content = kb_parse_template('stats', 'stats.html');

	return $content;
}

function stats_versions()
{
	$versions = array(
		'1.0.0'	=> array(
			'config_add'	=> array(
				array('kb_stats_enable', 1),
				array('kb_stats_menu', LEFT_MENU),
			),
		),
	);

	return $versions;
}
?>