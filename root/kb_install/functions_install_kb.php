<?php
/**
* @author Unknown Bliss (Michael Cullum of http://unknownbliss.co.uk)
* @package umil
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
define('UMIL_AUTO', true);
define('IN_PHPBB', true);
define('IN_INSTALL', true);

$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : '../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/constants_kb.' . $phpEx);
include($phpbb_root_path . 'includes/functions_plugins_kb.' . $phpEx);
include($phpbb_root_path . 'includes/functions_kb.' . $phpEx);

$user->session_begin();
$auth->acl($user->data);
$user->setup(); 

$auth_settings = array();
if (!file_exists($phpbb_root_path . 'umil/umil_auto.' . $phpEx))
{
	trigger_error('Please download the latest UMIL (Unified MOD Install Library) from: <a href="http://www.phpbb.com/mods/umil/">phpBB.com/mods/umil</a>', E_USER_ERROR);
}

// Some blog files we need
//require "{$phpbb_root_path}includes/mods/constants_blog.$phpEx";

// The name of the mod to be displayed during installation.
$mod_name = 'phpBB3-Knowledgebase';

/*
* The name of the config variable which will hold the currently installed version
* You do not need to set this yourself, UMIL will handle setting and updating the version itself.
*/
$version_config_name = 'kb_version';

/*
* The language file which will be included when installing
* Language entries that should exist in the language file for UMIL (replace $mod_name with the mod's name you set to $mod_name above)
*/
$language_file = 'mods/kb';

/*
* Options to display to the user (this is purely optional, if you do not need the options you do not have to set up this variable at all)
* Uses the acp_board style of outputting information, with some extras (such as the 'default' and 'select_user' options)
*/
/*$options = array(
	'kb_enable'				=> array('lang' => 'KB_ENABLE',			'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),
	'kb_link_name'			=> array('lang' => 'KB_LINK_NAME',		'validate' => 'string',	'type' => 'text:40:50', 	'explain' => true),
	'kb_header_name'		=> array('lang' => 'KB_HEADER_NAME',	'validate' => 'string',	'type' => 'text:40:50', 	'explain' => true),
	'kb_copyright'			=> array('lang' => 'KB_PER_COPYRIGHT',	'validate' => 'string',	'type' => 'text:40:50', 	'explain' => true),
);
*/

/*
* Optionally we may specify our own logo image to show in the upper corner instead of the default logo.
* $phpbb_root_path will get prepended to the path specified
* Image height should be 50px to prevent cut-off or stretching.
*/
$logo_img = "{T_THEME_PATH}/kb_bot.png";

/*
* The array of versions and actions within each.
* You do not need to order it a specific way (it will be sorted automatically), however, you must enter every version, even if no actions are done for it.
*
* You must use correct version numbering.  Unless you know exactly what you can use, only use X.X.X (replacing X with an integer).
* The version numbering must otherwise be compatible with the version_compare function - http://php.net/manual/en/function.version-compare.php
*/
$mod = array(
	'name'		=> 'phpBB3-Knowledgebase',
	'version'	=> '1.0.4a',
	'config'	=> 'phpbb3_knowledgebase_version',
	'enable'	=> 'phpbb3_knowledgebase_enable',
	'kb_header_name'	=> '',
);

// First article
$bitfield = $desc_bitfield = $uid = $desc_uid = '';
$options = $desc_options = 0;
$desc_text = $user->lang['KB_FIRST_ARTICLE_DESC'];
$text = $user->lang['KB_FIRST_ARTICLE_TEXT'];
generate_text_for_storage($desc_text, $desc_uid, $desc_bitfield, $desc_options, true, true, true);
generate_text_for_storage($text, $uid, $bitfield, $options, true, true, true);

$versions = array(
	'1.0.4a'	=>array(),
	'1.0.3'	=> array(
		'config_add' => array(
			array('kb_header_name', ''),
			array('kb_default_rating', 3),
		),
	),
	'1.0.2'	=> array(
		'table_add'	=> array(
				array('phpbb_article_attachments', array(
					'COLUMNS'		=> array(
						'attach_id'			=> array('UINT', NULL, 'auto_increment'),
						'article_id'		=> array('UINT', 0),
						'comment_id'		=> array('UINT', 0),
						'poster_id'			=> array('UINT', 0),
						'is_orphan'			=> array('BOOL', 1),
						'physical_filename'	=> array('VCHAR', ''),
						'real_filename'		=> array('VCHAR', ''),
						'download_count'	=> array('UINT', 0),
						'attach_comment'	=> array('TEXT_UNI', ''),
						'extension'			=> array('VCHAR:100', ''),
						'mimetype'			=> array('VCHAR:100', ''),
						'filesize'			=> array('UINT:20', 0),
						'filetime'			=> array('TIMESTAMP', 0),
						'thumbnail'			=> array('BOOL', 0),
					),
					'PRIMARY_KEY'	=> 'attach_id',
					'KEYS'			=> array(
						'filetime'			=> array('INDEX', 'filetime'),
						'article_id'		=> array('INDEX', 'article_id'),
						'comment_id'		=> array('INDEX', 'comment_id'),
						'poster_id'			=> array('INDEX', 'poster_id'),
						'is_orphan'			=> array('INDEX', 'is_orphan'),
					),
				)),

				array('phpbb_article_cats', array(
					'COLUMNS'		=> array(
						'cat_id'				=> array('UINT', NULL, 'auto_increment'),
						'parent_id'				=> array('UINT', 0),
						'left_id'				=> array('UINT', 0),
						'right_id'				=> array('UINT', 0),
						'cat_name'				=> array('VCHAR', ''),	
						'cat_desc'				=> array('TEXT_UNI', ''),
						'cat_desc_bitfield'		=> array('VCHAR', ''),
						'cat_desc_options'		=> array('UINT:11', 7),
						'cat_desc_uid'			=> array('VCHAR:8', ''),
						'cat_image'				=> array('VCHAR', ''),
						'cat_articles'			=> array('UINT', 0),
						'latest_ids'			=> array('TEXT_UNI', NULL),
					),
					'PRIMARY_KEY'	=> 'cat_id',
				)),

				array('phpbb_article_comments', array(
					'COLUMNS'		=> array(
						'comment_id'			=> array('UINT', NULL, 'auto_increment'),
						'article_id'			=> array('UINT', 0),
						'comment_title'			=> array('VCHAR', ''),
						'comment_text'			=> array('MTEXT_UNI', ''),
						'comment_checksum'		=> array('VCHAR:32', ''),
						'comment_type'			=> array('TINT:1', 0),
						'comment_user_id'		=> array('UINT', 0),
						'comment_user_name'		=> array('VCHAR_UNI:255', ''),
						'comment_user_color'	=> array('VCHAR:6', ''),
						'comment_time'			=> array('TIMESTAMP', 0),
						'comment_edit_time'		=> array('TIMESTAMP', 0),
						'comment_edit_id'		=> array('UINT', 0),
						'comment_edit_name'		=> array('VCHAR_UNI:255', ''),
						'comment_edit_color'	=> array('VCHAR:6', ''),
						'enable_bbcode'			=> array('BOOL', 1),
						'enable_smilies'		=> array('BOOL', 1),
						'enable_magic_url'		=> array('BOOL', 1),
						'enable_sig'			=> array('BOOL', 1),
						'bbcode_bitfield'		=> array('VCHAR', ''),
						'bbcode_uid'			=> array('VCHAR:8', ''),
						'comment_attachment'	=> array('BOOL', 0),
					),
					'PRIMARY_KEY'	=> 'comment_id'
				)),

				array('phpbb_article_edits', array(
					'COLUMNS'		=> array(
						'edit_id'						=> array('UINT', NULL, 'auto_increment'),
						'article_id'					=> array('UINT', 0),
						'edit_type'						=> array('VCHAR', 'a:0:{}'),
						'edit_cat_id'					=> array('UINT', 0),
						'parent_id'						=> array('UINT', 0),
						'edit_user_id'					=> array('UINT', 0),
						'edit_user_name'				=> array('VCHAR_UNI:255', ''),
						'edit_user_color'				=> array('VCHAR:6', ''),
						'edit_time'						=> array('TIMESTAMP', 0),
						'edit_article_title'			=> array('VCHAR', ''),
						'edit_article_desc'				=> array('TEXT_UNI', ''),
						'edit_article_desc_bitfield'	=> array('VCHAR', ''),
						'edit_article_desc_options'		=> array('UINT:11', 7),
						'edit_article_desc_uid'			=> array('VCHAR:8', ''),
						'edit_article_text'				=> array('MTEXT_UNI', ''),
						'edit_article_checksum'			=> array('VCHAR:32', ''),
						'edit_enable_bbcode'			=> array('BOOL', 1),
						'edit_enable_smilies'			=> array('BOOL', 1),
						'edit_enable_magic_url'			=> array('BOOL', 1),
						'edit_enable_sig'				=> array('BOOL', 1),
						'edit_bbcode_bitfield'			=> array('VCHAR', ''),
						'edit_bbcode_uid'				=> array('VCHAR:8', ''),
						'edit_article_tags'				=> array('VCHAR', ''),
						'edit_article_type'				=> array('UINT', 0),
						'edit_article_status'			=> array('TINT:1', 1),
						'edit_moderated'				=> array('TINT:1', 0),
						'edit_reason'					=> array('MTEXT_UNI', ''),
						'edit_reason_global'			=> array('BOOL', 0),
						'edit_contribution'				=> array('BOOL', 0),
					),
					'PRIMARY_KEY'	=> 'edit_id'
				)),
				
				array('phpbb_article_rate', array(
					'COLUMNS'		=> array(
						'article_id'	=> array('UINT', 0),
						'user_id'		=> array('UINT', 0),
						'rate_time'		=> array('TIMESTAMP', 0),
						'rating'		=> array('TINT:2', 0),
					),
				)),
				
				array('phpbb_article_tags', array(
					'COLUMNS'		=> array(
						'article_id'	=> array('UINT', 0),
						'tag_name'		=> array('VCHAR:30', ''),
						'tag_name_lc'	=> array('VCHAR:30', ''),
					),
				)),
				
				array('phpbb_article_track', array(
					'COLUMNS'		=> array(
						'article_id'	=> array('UINT', 0),
						'user_id'		=> array('UINT', 0),
						'subscribed'	=> array('TINT:1', 0),
						'bookmarked'	=> array('TINT:1', 0),
						'notify_by'		=> array('TINT:1', 0),
						'notify_on'		=> array('TINT:1', 0),
					),
				)),
				
				array('phpbb_article_types', array(
					'COLUMNS'		=> array(
						'type_id'					=> array('UINT', NULL, 'auto_increment'),
						'icon_id'					=> array('UINT', 0),
						'type_title'				=> array('VCHAR', ''),
						'type_before'				=> array('VCHAR', ''),
						'type_after'				=> array('VCHAR', ''),
						'type_image'				=> array('VCHAR', ''),
						'type_img_w'				=> array('TINT:4', 0),
						'type_img_h'				=> array('TINT:4', 0),
						'type_order'				=> array('TINT:4', 0),
					),
					'PRIMARY_KEY'	=> 'type_id'
				)),
				
				array('phpbb_article_plugins', array(
					'COLUMNS'		=> array(
						'plugin_id'					=> array('UINT', NULL, 'auto_increment'),
						'plugin_name'				=> array('VCHAR', ''),
						'plugin_filename'			=> array('VCHAR', ''),
						'plugin_desc'				=> array('TEXT_UNI', ''),
						'plugin_copy'				=> array('VCHAR', ''),
						'plugin_version'			=> array('VCHAR:20', ''),
						'plugin_menu'				=> array('BOOL', 0),
						'plugin_order'				=> array('BOOL', 0),
						'plugin_pages'				=> array('TEXT_UNI', 'a:0:{}'),
						'plugin_pages_perm'			=> array('TEXT_UNI', 'a:0:{}'),
						'plugin_perm'				=> array('BOOL', 0),
					),
					'PRIMARY_KEY'	=> 'plugin_id'
				)),
				
				array('phpbb_article_acl_groups', array(
						'COLUMNS'		=> array(
							'group_id'			=> array('UINT', 0),
							'forum_id'			=> array('UINT', 0),
							'auth_option_id'	=> array('UINT', 0),
							'auth_role_id'		=> array('UINT', 0),
							'auth_setting'		=> array('TINT:2', 0),
						),
						'KEYS'			=> array(
							'group_id'			=> array('INDEX', 'group_id'),
							'auth_opt_id'		=> array('INDEX', 'auth_option_id'),
							'auth_role_id'		=> array('INDEX', 'auth_role_id'),
						),
					),	  
				),
				
				array('phpbb_article_acl_users', array(
						'COLUMNS'		=> array(
							'user_id'			=> array('UINT', 0),
							'forum_id'			=> array('UINT', 0),
							'auth_option_id'	=> array('UINT', 0),
							'auth_role_id'		=> array('UINT', 0),
							'auth_setting'		=> array('TINT:2', 0),
						),
						'KEYS'			=> array(
							'user_id'			=> array('INDEX', 'user_id'),
							'auth_option_id'	=> array('INDEX', 'auth_option_id'),
							'auth_role_id'		=> array('INDEX', 'auth_role_id'),
						),
					),
				),
				
				array('phpbb_article_visits', array(
						'COLUMNS'		=> array(
							'user_id'			=> array('UINT', 0),
							'cat_id'			=> array('UINT', 0), 
							'article_id'		=> array('UINT', 0),
							'last_visit'		=> array('TIMESTAMP', 0), // used for comment new
						),
						'PRIMARY_KEY'	=> array('user_id', 'article_id'),
						'KEYS'			=> array(
							'cat_id'			=> array('INDEX', 'cat_id'),
						),
					),
				),
			),
			'table_column_add' => array(
				array(EXTENSION_GROUPS_TABLE, 'allow_in_kb', array('BOOL', 0)),
				array(USERS_TABLE, 'user_articles', array('UINT', 0)),
				array(USERS_TABLE, 'user_kb_permissions', array('MTEXT', '')),
				array(USERS_TABLE, 'kb_last_visit', array('TIMESTAMP', 0)),
				array(USERS_TABLE, 'kb_last_marked', array('TIMESTAMP', 0)),
			),	
			'permission_add' => array(
				// Global
				array('u_kb_request', true),
				
				// Local
				array('u_kb_read', false),
				array('u_kb_view', false),
				array('u_kb_add_op', false),
				array('u_kb_add_co', false),
				array('u_kb_add', false),
				array('u_kb_add_wa', false),
				array('u_kb_attach', false),
				array('u_kb_bbcode', false),
				array('u_kb_comment', false),
				array('u_kb_delete', false),
				array('u_kb_download', false),
				array('u_kb_edit', false),
				array('u_kb_flash', false),
				array('u_kb_img', false),
				array('u_kb_rate', false),
				array('u_kb_sigs', false),
				array('u_kb_smilies', false),
				array('u_kb_types', false),
				array('u_kb_search', false),
				array('u_kb_viewhistory', false),
				
				// Moderator
				array('m_kb_time', true),
				array('m_kb_author', true),
				array('m_kb_view', true),
				array('m_kb_comment', true),
				array('m_kb_edit', true),
				array('m_kb_delete', true),
				array('m_kb_req_edit', true),
				array('m_kb_status', true),
			),
			
			
			'module_add' => array(
				// ACP
				array('acp', 'ACP_CAT_DOT_MODS', 'ACP_KB'),
				array('acp', 'ACP_KB', array(
						'module_basename'		=> 'kb',
						'modes'					=> array('settings', 'health_check', 'plugins'),
					),
				),
				// Cats management
				array('acp', 'ACP_KB', array(
						'module_basename'		=> 'kb_cats',
						'modes'					=> array('manage'),
					),
				),
				// Permissions
				array('acp', 'ACP_KB', array(
						'module_basename'		=> 'kb_permissions',
						'modes'					=> array('set_permissions', 'set_roles'),
					),
				),
				// Types
				array('acp', 'ACP_KB', array(
						'module_basename'		=> 'kb_types',
						'modes'					=> array('manage'),
					),
				),
				
				// UCP
				array('ucp', '', 'UCP_KB'),
				array('ucp', 'UCP_KB', array(
						'module_basename'			=> 'kb',
						'modes'						=> array('front', 'subscribed', 'bookmarks', 'articles'),
					),
				),
				
				// MCP
				array('mcp', '', 'MCP_KB'),
				array('mcp', 'MCP_KB', array(
						'module_basename'	=> 'kb',
						'modes'				=> array('queue', 'articles'),
					),
				),
			),
			
			'config_add' => array(
				array('kb_ajax_rating', 1),
				array('kb_allow_attachments', 1),
				array('kb_allow_bbcode', 1),
				array('kb_allow_bookmarks', 1),
				array('kb_allow_post_flash', 0),
				array('kb_allow_post_links', 1),
				array('kb_allow_sig', 1),
				array('kb_allow_smilies', 1),
				array('kb_allow_subscribe', 1),
				array('kb_articles_per_page', 25),
				array('kb_cats_per_row', 3),
				array('kb_comments_per_page', 25),
				array('kb_copyright',''),
				array('kb_desc_max_chars', 0),
				array('kb_desc_min_chars', 0),
				array('kb_disable_desc', 0),
				array('kb_disable_left_menu', 0),
				array('kb_disable_right_menu', 0),
				array('kb_email_article', 1),
				array('kb_enable', 1),
				array('kb_export_article', 1),
				array('kb_ext_article_header', 1),
				array('kb_latest_articles_c', 5),
				array('kb_last_article', 0, true),
				array('kb_last_updated', time(), true),
				array('kb_layout_style'	, 1),
				array('kb_left_menu_type', 0),
				array('kb_left_menu_width', 240),
				array('kb_link_name', $user->lang['KB']),
				array('kb_list_subcats', 1),
				array('kb_related_articles', 5),
				array('kb_right_menu_type', 0),
				array('kb_right_menu_width', 240),
				array('kb_seo', 0),
				array('kb_show_contrib', 1),
				array('kb_show_desc_article', 1),
				array('kb_show_desc_cat', 1),
				array('kb_soc_bookmarks', 1),
				array('kb_total_articles', 0, true),
				array('kb_total_comments', 0, true),
				array('kb_total_cats', 0),
			),
		
		'custom'	=> 'kb_install_perm_plugins',//this works
		
		'table_row_insert'	=> array(KB_CATS_TABLE, array(
			'parent_id'		=> 0,
			'left_id'		=> 1,
			'right_id'		=> 2,
			'cat_name'		=> $user->lang['KB_FIRST_CAT'],
			'cat_desc'		=> $user->lang['KB_FIRST_CAT_DESC'],
			'cat_desc_bitfield'		=> '',
			'cat_desc_options'		=> 7,
			'cat_desc_uid'			=> '',
			'cat_image'				=> '',
			'cat_articles'			=> 0,
			'latest_ids'			=> serialize(array()),
		)
		
		'table_row_insert'	=> array(KB_TABLE, array(
			'cat_id'						=> 	1,
			'article_title'					=>	$user->lang['KB_FIRST_ARTICLE_TITLE'],
			'article_title_clean'			=>  utf8_clean_string($user->lang['KB_FIRST_ARTICLE_TITLE']),
			'article_desc'					=>	$desc_text,
			'article_desc_bitfield'			=>	$desc_bitfield,
			'article_desc_options'			=>	$desc_options,
			'article_desc_uid'				=>	$desc_uid,
			'article_checksum'				=>	md5($text),
			'article_status'				=>	STATUS_APPROVED,
			'article_attachment'			=>	0,
			'article_views'					=>	0,
			'article_user_id'				=>	$user->data['user_id'],
			'article_user_name'				=>	$user->data['username'],
			'article_user_color'			=>	$user->data['user_colour'],
			'article_time'					=>	time(),
			'article_tags'					=>	'',
			'article_type'					=>	0,
			'article_text'					=>  $text,
			'enable_bbcode'					=>	1,
			'enable_smilies'				=>	1,
			'enable_magic_url'				=>	1,
			'enable_sig'					=>	0,
			'bbcode_bitfield'				=>	$bitfield,
			'bbcode_uid'					=>	$uid,
			'article_last_edit_time'		=>	time(),
			'article_last_edit_id'			=>	0,
			'article_edit_reason'			=>	'',
			'article_edit_reason_global'	=>	0,
			'article_open'					=>  0,
			'article_edit_contribution'		=>  0,
			'article_edit_type'				=>  serialize(array()),
		)
		
		'permission_set'	=> array($auth_settings),//this not working so much
		
		// Give permissions to admins to moderate the KB and add requests
		'permission_set' => array(
			array('ADMINISTRATORS', array('m_kb_author', 'm_kb_comment', 'm_kb_edit', 'm_kb_delete', 'm_kb_req_edit', 'm_kb_status', 'm_kb_time', 'm_kb_view'), 'group'),
			array('ADMINISTRATORS', 'u_kb_request', 'group'),
			array('REGISTERED', 'u_kb_request', 'group'),
		),
		
		'permission_set' => array(
				// Global Role permissions
				array('ROLE_ADMIN_FULL', 'a_test_mod'),
				array('ROLE_USER_FULL', 'u_test_mod'),

				// Global Group permissions
				array('GUESTS', 'u_test_mod', 'group'),

				// Local Permissions (local permissions can not be set for groups)
				array('ROLE_FORUM_STANDARD', 'f_test_mod'),
			),
			
		
	)
	
	);
	
// Include the UMIF Auto file and everything else will be handled automatically.
include($phpbb_root_path . 'umil/umil_auto.' . $phpEx);


// Install permanent plugins on install or update
function kb_install_perm_plugins($action = 'install')
{
	global $phpbb_root_path;

	if ($action == 'uninstall')
	{
		return;
	}
	
	if(!defined('IN_KB_PLUGIN')) // Killing notice when updating through several versions all using this function
	{
		define('IN_KB_PLUGIN', true);
	}
	
	$plugin_pages = array();
	
	$plugins = array('author', 'contributors', 'rating', 'categories', 'stats', 'search', 'latest_article', 'request_list', 'bookmark', 'email_article', 'export_article', 'related_articles', 'rated_articles', 'random_article');
	$plugin_pages = array(
		'author'			=> array('view_article'),
		'contributors'		=> array('view_article'),
		'rating'			=> array('view_article'),
		'categories'		=> array('index', 'view_cat', 'view_tag', 'request', 'search', 'history'),
		'stats'				=> array('index', 'view_cat', 'view_tag', 'request', 'search', 'history'),
		'search'			=> array('index', 'view_cat', 'view_tag', 'request', 'view_article', 'history'),
		'latest_article'	=> array('index', 'view_cat', 'view_tag', 'request', 'search'),
		'request_list'		=> array('index', 'view_cat', 'view_tag', 'view_article', 'search'),
		'bookmark'			=> array('view_article'),
		'email_article'		=> array('view_article'),
		'export_article'	=> array('view_article'),
		'related_articles'	=> array('view_article'),
		'rated_articles'	=> array('index', 'view_cat', 'view_tag', 'request'),
		'random_article'	=> array('index', 'view_cat', 'view_tag'),
	);
	
	if (empty($plugins))
	{
		return;
	}
	
	foreach ($plugins as $plugin)
	{
		if(isset($plugin_pages[$plugin]))
		{
			install_plugin($plugin, $phpbb_root_path . 'includes/kb_plugins/', false, $plugin_pages[$plugin]);
		}
		else
		{
			install_plugin($plugin, $phpbb_root_path . 'includes/kb_plugins/');
		}
	}
}

function kb_install_first_information()
{
	global $db, $config, $user, $phpbb_root_path, $phpEx, $template, $auth_settings;
	
	
	// Data to insert:
	// First category
	$sql_ary = array(
		'parent_id'		=> 0,
		'left_id'		=> 1,
		'right_id'		=> 2,
		'cat_name'		=> $user->lang['KB_FIRST_CAT'],
		'cat_desc'		=> $user->lang['KB_FIRST_CAT_DESC'],
		'cat_desc_bitfield'		=> '',
		'cat_desc_options'		=> 7,
		'cat_desc_uid'			=> '',
		'cat_image'				=> '',
		'cat_articles'			=> 0,
		'latest_ids'			=> serialize(array()),
	);
	$sql = 'INSERT INTO ' . KB_CATS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
	$db->sql_query($sql);
	$cat_id = $db->sql_nextid();
	set_config('kb_total_cats', 1);
	
	// First article
	$bitfield = $desc_bitfield = $uid = $desc_uid = '';
	$options = $desc_options = 0;
	$desc_text = $user->lang['KB_FIRST_ARTICLE_DESC'];
	$text = $user->lang['KB_FIRST_ARTICLE_TEXT'];
	generate_text_for_storage($desc_text, $desc_uid, $desc_bitfield, $desc_options, true, true, true);
	generate_text_for_storage($text, $uid, $bitfield, $options, true, true, true);
	
	$sql_ary = array(
		'cat_id'						=> 	$cat_id,
		'article_title'					=>	$user->lang['KB_FIRST_ARTICLE_TITLE'],
		'article_title_clean'			=>  utf8_clean_string($user->lang['KB_FIRST_ARTICLE_TITLE']),
		'article_desc'					=>	$desc_text,
		'article_desc_bitfield'			=>	$desc_bitfield,
		'article_desc_options'			=>	$desc_options,
		'article_desc_uid'				=>	$desc_uid,
		'article_checksum'				=>	md5($text),
		'article_status'				=>	STATUS_APPROVED,
		'article_attachment'			=>	0,
		'article_views'					=>	0,
		'article_user_id'				=>	$user->data['user_id'],
		'article_user_name'				=>	$user->data['username'],
		'article_user_color'			=>	$user->data['user_colour'],
		'article_time'					=>	time(),
		'article_tags'					=>	'',
		'article_type'					=>	0,
		'article_text'					=>  $text,
		'enable_bbcode'					=>	1,
		'enable_smilies'				=>	1,
		'enable_magic_url'				=>	1,
		'enable_sig'					=>	0,
		'bbcode_bitfield'				=>	$bitfield,
		'bbcode_uid'					=>	$uid,
		'article_last_edit_time'		=>	time(),
		'article_last_edit_id'			=>	0,
		'article_edit_reason'			=>	'',
		'article_edit_reason_global'	=>	0,
		'article_open'					=>  0,
		'article_edit_contribution'		=>  0,
		'article_edit_type'				=>  serialize(array()),
	);
	$sql = 'INSERT INTO ' . KB_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
	$db->sql_query($sql);
	$article_id = $db->sql_nextid();
	
	$late_articles = array(
		'article_id'		=> $article_id,
		'article_title'		=> $user->lang['KB_FIRST_ARTICLE_TITLE'],
	);
	handle_latest_articles('add', $cat_id, $late_articles, $config['kb_latest_articles_c']);
	
	set_config('kb_last_updated', time(), true);
	
	$sql = 'UPDATE ' . KB_CATS_TABLE . '
			SET cat_articles = cat_articles + 1
			WHERE cat_id = ' . $cat_id;
	$db->sql_query($sql);
	
	$sql = 'UPDATE ' . USERS_TABLE . '
			SET user_articles = user_articles + 1
			WHERE user_id = ' . $user->data['user_id'];
	$db->sql_query($sql);
	
	set_config('kb_last_article', $article_id, true);
	set_config('kb_total_articles', $config['kb_total_articles'] + 1, true);
	
	// Add basic KB roles
	$roles = get_roles();
	$db->sql_multi_insert(ACL_ROLES_TABLE, $roles['roles']);
	
	$auth_settings = array();
	foreach($roles['auth'] as $role_name => $auth_ary)
	{
		$auth_settings[] = array('ROLE_KB_' . $role_name, $auth_ary);
	}
	
	
	$sql = 'SELECT role_id, role_name
			FROM ' . ACL_ROLES_TABLE . '
			WHERE ' . $db->sql_in_set('role_name', array('ROLE_KB_MOD', 'ROLE_KB_USER', 'ROLE_KB_GUEST'));
	$result = $db->sql_query($sql);
	
	$sql_ary = array();
	while($row = $db->sql_fetchrow($result))
	{
		switch($row['role_name'])
		{
			case 'ROLE_KB_MOD':
				$groups = array(5);
			break;
			
			case 'ROLE_KB_USER':
				$groups = array(2, 3);
			break;
			
			case 'ROLE_KB_GUEST':
				$groups = array(1, 6);
			break;
		}
		
		foreach($groups as $group_id)
		{
			$sql_ary[] = array(
				'group_id'			=> $group_id,
				'forum_id'			=> 1,
				'auth_option_id'	=> 0,
				'auth_role_id'		=> $row['role_id'],
				'auth_setting'		=> 0,
			);
		}
	}
	$db->sql_freeresult($result);
	$db->sql_multi_insert(KB_ACL_GROUPS_TABLE, $sql_ary);
	
	
	
	// Finish screen
	add_log('admin', 'LOG_KB_INSTALL', KB_VERSION);
	
}

function get_roles(){
	$permission_type = 'u_kb_';
	$roles = array(
		array(
			'role_name'			=> 'ROLE_KB_GUEST',
			'role_description'	=> 'ROLE_KB_GUEST_DESC',
			'role_type'			=> $permission_type,
		),
		
		array(
			'role_name'			=> 'ROLE_KB_USER',
			'role_description'	=> 'ROLE_KB_USER_DESC',
			'role_type'			=> $permission_type,
		),
		
		array(
			'role_name'			=> 'ROLE_KB_MOD',
			'role_description'	=> 'ROLE_KB_MOD_DESC',
			'role_type'			=> $permission_type,
		),
	);
	
	$roles_auth = array(
		'GUEST'		=> array('u_kb_bbcode', 'u_kb_comment', 'u_kb_download', 'u_kb_img', 'u_kb_read', 'u_kb_search', 'u_kb_smilies', 'u_kb_view'),
		'USER'		=> array('u_kb_add', 'u_kb_attach', 'u_kb_bbcode', 'u_kb_comment', 'u_kb_delete', 'u_kb_download', 'u_kb_edit', 'u_kb_icons', 'u_kb_img', 'u_kb_rate', 'u_kb_read', 'u_kb_search', 'u_kb_sigs', 'u_kb_smilies', 'u_kb_types', 'u_kb_view'),
		'MOD'		=> array('u_kb_add', 'u_kb_add_wa', 'u_kb_attach', 'u_kb_bbcode', 'u_kb_comment', 'u_kb_delete', 'u_kb_download', 'u_kb_edit', 'u_kb_icons', 'u_kb_img', 'u_kb_rate', 'u_kb_read', 'u_kb_search', 'u_kb_sigs', 'u_kb_smilies', 'u_kb_types', 'u_kb_view', 'u_kb_viewhistory'),
	);
	
return array('roles' => $roles, 'auth'	=> $roles_auth);
}