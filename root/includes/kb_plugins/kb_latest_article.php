<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_latest_article.php 504 2010-06-21 14:38:48Z andreas.nexmann@gmail.com $
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
	$acp_options['legend1'] 					= 'LATEST_ARTICLES';
	$acp_options['kb_latest_article_enable'] 	= array('lang' => 'ENABLE_LATEST_ARTICLES',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' 	=> false);
	$acp_options['kb_latest_article_menu']		= array('lang' => 'WHICH_MENU',					'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);
		
	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_LATEST',
		'PLUGIN_DESC'			=> 'PLUGIN_LATEST_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.2',
		'PLUGIN_MENU'			=> LEFT_MENU,
		'PLUGIN_PAGES'			=> array('all'),
	);
}

/**
* Extra details for the mod does it need to call functions on certain areas?
*/
$on_article_post[] = 'set_latest_article';
$on_article_approve[] = 'set_latest_article';

// Get latest article
function latest_article($cat_id = 0)
{
	global $config, $db, $template, $phpbb_root_path, $phpEx, $user;
	
	if (!$config['kb_latest_article_enable'])
	{
		return;
	}
	
	if ($config['kb_latest_article'] == '')
	{
		$template->assign_vars(array(
			'NO_LAST_ARTICLE'		=> true,
		));
		
		return;
	}

	$sql = 'SELECT article_title, article_desc, article_desc_bitfield, article_desc_options, article_desc_uid, article_views, article_comments 
		FROM ' . KB_TABLE . '
		WHERE article_id = ' . $config['kb_latest_article'] . '
		AND ' . $db->sql_in_set('cat_id', get_readable_cats());
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	if (!$db->sql_affectedrows())	
	{
		$template->assign_vars(array(
			'NO_LAST_ARTICLE'		=> true,
		));
		
		//return;
	}	
	$db->sql_freeresult($result);

	$template->assign_vars(array(
		'LAST_ARTICLE_TITLE'		=> $row['article_title'],
		'LAST_ARTICLE_DESC'			=> generate_text_for_display($row['article_desc'], $row['article_desc_uid'], $row['article_desc_bitfield'], $row['article_desc_options']),
		'LAST_ARTICLE_COMMENTS'		=> $row['article_comments'],
		'LAST_ARTICLE_VIEWS'		=> $row['article_views'],
		
		'U_LAST_ARTICLE'			=> kb_append_sid('article', array('id' => $config['kb_latest_article'], 'title' => censor_text($row['article_title']))),
		'U_RSS_LAST_ARTICLE'		=> append_sid("{$phpbb_root_path}kb.$phpEx", 'i=feed&amp;feed_type=latest'),
		
		'T_THEME_PATH'				=> "{$phpbb_root_path}styles/" . $user->theme['theme_path'] . '/theme',
	));
	
	$content = kb_parse_template('latest_article', 'latest_article.html');
	
	return $content;
}

function latest_article_versions()
{
	$versions = array(
		'1.0.0'	=> array(			
			'config_add'	=> array(
				array('kb_latest_article_enable', 1),
				array('kb_latest_article_menu', RIGHT_MENU),
				array('kb_latest_article', 0, true),
			),
		),
		
		// Just adding perm pages
		'1.0.1'	=> array(			
			array(),
		),
		
		// Just a change to perm pages
		'1.0.2'	=> array(			
			array(),
		),
	);

	return $versions;
}

function set_latest_article($data)
{
	set_config('kb_latest_article', $data['article_id'], true);
}
?>