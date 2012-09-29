<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_random_article.php 420 2010-01-13 14:36:10Z softphp $
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
	$acp_options['legend1'] 					= 'KB_RAN_ART';
	$acp_options['kb_random_article_enable'] 	= array('lang' => 'ENABLE_RANDOM_ARTICLES',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' 	=> false);
	$acp_options['kb_random_article_menu']		= array('lang' => 'WHICH_MENU',					'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);
		
	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_RAND',
		'PLUGIN_DESC'			=> 'PLUGIN_RAND_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.0',
		'PLUGIN_MENU'			=> RIGHT_MENU,
		'PLUGIN_PAGES'			=> array('all'),
	);
}

/**
* Extra details for the mod does it need to call functions on certain areas?
*/

function random_article($cat_id = false, $second_pass = false)
{
	global $db, $template, $phpbb_root_path, $phpEx, $config;
	
	if (!$config['kb_random_article_enable'])
	{
		return;
	}
	
	$random_where = ($cat_id == 0) ? ' WHERE ' . $db->sql_in_set('cat_id', get_readable_cats()) . ' AND article_status = ' . STATUS_APPROVED : ' WHERE cat_id = ' . $cat_id . ' AND article_status = ' . STATUS_APPROVED;
	
	$all_ids = array();
	
	// Check if we need to cache the results only if over 10 articles
	$cache_sql = ($config['kb_total_articles'] >= 10) ? 3600 : 0;

	$sql = 'SELECT article_id 
			FROM ' . KB_TABLE . "
			$random_where";
	$result = $db->sql_query($sql, $cache_sql);
	while($row = $db->sql_fetchrow($result))
	{
		$all_ids[] = $row['article_id'];
	}
		
	if (!$db->sql_affectedrows())	
	{
		if (!$second_pass)
		{
			// Call again with second pass saves writing another sql
			random_article($cat_id, true);
		}
		else
		{
			$template->assign_vars(array(
				'NO_ARTICLE'		=> true,
			));
		}
		
		return;
	}
	$db->sql_freeresult($result);	
	
	if (empty($all_ids))
	{
		$template->assign_vars(array(
			'NO_ARTICLE'		=> true,
		));
		
		return;
	}
	
	$value = array_rand($all_ids);
	$article_id = $all_ids[$value];

	$sql = 'SELECT article_title, article_desc, article_desc_bitfield, article_desc_options, article_desc_uid, article_views, article_comments 
			FROM ' . KB_TABLE . '
			WHERE article_id = ' . $article_id;
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	$template->assign_vars(array(
		'RAN_ARTICLE_TITLE'		=> $row['article_title'],
		'RAN_ARTICLE_DESC'		=> generate_text_for_display($row['article_desc'], $row['article_desc_uid'], $row['article_desc_bitfield'], $row['article_desc_options']),
		'RAN_ARTICLE_COMMENTS'	=> $row['article_comments'],
		'RAN_ARTICLE_VIEWS'		=> $row['article_views'],
		
		'U_RAN_ARTICLE'			=> kb_append_sid('article', array('id' => $article_id, 'title' => censor_text($row['article_title']))),
	));
	
	$content = kb_parse_template('random_article', 'random_article.html');
	
	return $content;
}


function random_article_versions()
{
	$versions = array(
		'1.0.0'	=> array(			
			'config_add'	=> array(
				array('kb_random_article_enable', 1),
				array('kb_random_article_menu', RIGHT_MENU),
			),
		),
	);

	return $versions;
}
?>