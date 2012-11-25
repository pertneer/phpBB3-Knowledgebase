<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: kb_rated_articles.php 420 2010-01-13 14:36:10Z softphp $
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
	$acp_options['legend1'] 					= 'HIGHEST_RATED_ARTICLES';
	$acp_options['kb_rated_articles_enable'] 	= array('lang' => 'ENABLE_HIGHEST_RATED_ARTICLES',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' 	=> false);
	$acp_options['kb_rated_articles_menu']		= array('lang' => 'WHICH_MENU',						'validate' => 'int',	'type' => 'custom', 		'function' 	=> 'select_menu_check', 	'explain' 	=> false);
	$acp_options['kb_rated_articles_limit']		= array('lang' => 'HIGHEST_RATED_ARTICLES_LIMIT',	'validate' => 'int',	'type' => 'text:5:2',		'explain'	=> false);

	$details = array(
		'PLUGIN_NAME'			=> 'PLUGIN_HIGH_RATE',
		'PLUGIN_DESC'			=> 'PLUGIN_HIGH_RATE_DESC',
		'PLUGIN_COPY'			=> 'PLUGIN_COPY',
		'PLUGIN_VERSION'		=> '1.0.0',
		'PLUGIN_MENU'			=> RIGHT_MENU,
		'PLUGIN_PAGES'			=> array('all'),
	);
}

// Get highest rated articles
function rated_articles($cat_id)
{
	global $db, $template, $phpbb_root_path, $phpEx, $config;

	if (!$config['kb_rated_articles_enable'])
	{
		return;
	}

	$limit = $config['kb_rated_articles_limit'];
	$sql_where = ($cat_id) ? 'a.cat_id = ' . $cat_id : $db->sql_in_set('a.cat_id', get_readable_cats(), false, true);
	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> 'a.article_id, a.article_title, AVG(r.rating) AS rating',
		'FROM'		=> array(
			KB_TABLE => 'a'),
		'LEFT_JOIN'	=> array(
			array(
				'FROM' => array(KB_RATE_TABLE => 'r'),
				'ON' => 'a.article_id = r.article_id',
			),
		),
		'WHERE'		=> "a.article_status = " . STATUS_APPROVED . ' AND ' . $sql_where,
		'GROUP_BY'	=> 'a.article_id',
		'ORDER_BY'  => 'rating DESC',
	));

	$result = $db->sql_query_limit($sql, $limit);
	while($row = $db->sql_fetchrow($result))
	{
		$template->assign_block_vars('ratingrow', array(
			'ARTICLE_TITLE'		=> censor_text($row['article_title']),
			'U_VIEW_ARTICLE'	=> kb_append_sid('article', array('id' => $row['article_id'], 'title' => censor_text($row['article_title']))),
			'ARTICLE_RATING'	=> round($row['rating'], 1),
		));
	}
	$db->sql_freeresult($result);

	$content = kb_parse_template('rated_articles', 'rated_articles.html');

	return $content;
}

function rated_articles_versions()
{
	$versions = array(
		'1.0.0'	=> array(
			// Initial install, I suppose nothing is done here beside adding the config
			'config_add'	=> array(
				array('kb_rated_articles_enable', 1),
				array('kb_rated_articles_menu', RIGHT_MENU),
				array('kb_rated_articles_limit', 5),
			),
		),
	);

	return $versions;
}

?>