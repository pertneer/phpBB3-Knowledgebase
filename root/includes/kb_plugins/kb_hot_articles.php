<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_hot_articles.php 420 2010-01-13 14:36:10Z softphp $
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
	$acp_options['legend1'] 					= 'MOST_VIEWED_ARTICLES';
	$acp_options['kb_hot_articles_enable'] 		= array('lang' => 'ENABLE_MOST_VIEWED_ARTICLES',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' 	=> false);
	$acp_options['kb_hot_articles_menu']		= array('lang' => 'WHICH_MENU',						'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);
	$acp_options['kb_hot_articles_limit']		= array('lang' => 'HOT_ARTICLES_LIMIT',				'validate' => 'int',	'type' => 'text:5:2',			'explain'	=> false);
	
	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_HOT',
		'PLUGIN_DESC'			=> 'PLUGIN_HOT_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.0',
		'PLUGIN_MENU'			=> RIGHT_MENU,
		'PLUGIN_PAGES'			=> array('all'),
	);
}

// Get most popular articles, based on views
function hot_articles($cat_id)
{
	global $db, $template, $phpbb_root_path, $phpEx, $config;
	
	if (!$config['kb_hot_articles_enable'])
	{
		return;
	}
	
	$limit = $config['kb_hot_articles_limit'];
	$sql_where = ($cat_id) ? 'cat_id = ' . $cat_id : $db->sql_in_set('cat_id', get_readable_cats());
	$sql = 'SELECT article_id, article_title, article_views
			FROM ' . KB_TABLE . "
			WHERE article_status = " . STATUS_APPROVED . " 
			AND $sql_where 
			ORDER BY article_views DESC";
	$result = $db->sql_query_limit($sql, $limit);
	while($row = $db->sql_fetchrow($result))
	{
		$template->assign_block_vars('hotrow', array(
			'ARTICLE_TITLE'		=> censor_text($row['article_title']),
			'U_VIEW_ARTICLE'	=> kb_append_sid('article', array('id' => $row['article_id'], 'title' => censor_text($row['article_title']))),
			'ARTICLE_VIEWS'		=> $row['article_views'],
		));
	}
	$db->sql_freeresult($result);
	
	$content = kb_parse_template('hot_articles', 'hot_articles.html');
	
	return $content;
}

function hot_articles_versions()
{
	$versions = array(
		'1.0.0'	=> array(			
			// Initial install, I suppose nothing is done here beside adding the config
			'config_add'	=> array(
				array('kb_hot_articles_enable', 1),
				array('kb_hot_articles_menu', RIGHT_MENU),
				array('kb_hot_articles_limit', 5),
			),
		),
	);

	return $versions;
}

?>