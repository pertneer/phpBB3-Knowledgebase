<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: functions_install_kb.php 504 2010-06-21 14:38:48Z andreas.nexmann@gmail.com $
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

// New versions function
// Starts at 1.0.1
function kb_update_versions()
{
	// All versions after 1.0.1 go here
	$versions = array(
		'1.0.1'		=> array(
			'table_add'		=> array(
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
			
			'table_column_add'		=> array(
				array(USERS_TABLE, 'user_kb_permissions', array('MTEXT', '')),
				array(USERS_TABLE, 'kb_last_visit', array('TIMESTAMP', 0)),
				array(USERS_TABLE, 'kb_last_marked', array('TIMESTAMP', 0)),
			),
			
			'custom'		=> 'kb_update_1_0_0_to_1_0_1',
			
			'table_column_remove'	=> array(
				array(ACL_USERS_TABLE, 'kb_auth'),
				array(ACL_GROUPS_TABLE, 'kb_auth'),
			),
			
			'config_add'	=> array(
				array('kb_seo', 0),
			),
		),
		
		'1.0.2RC1' => array(
			'config_add'	=> array(
				array('kb_copyright', ''),
			),
		),
		
		'1.0.2RC2' => array(
			// Nothing here
		),
		
		'1.0.2RC3' => array(
			// Resync count, clear plugin cache
			'custom'	=> 'kb_update_1_0_2RC2_to_1_0_2RC3',
		),
		
		'1.0.2' => array(
			// Clear history table
			'custom'	=> 'kb_update_1_0_2RC3_to_1_0_2',
		),
	);
	
	return $versions;
}

// Install versions, just used to split it up into steps... don't mind the version numbering here
function kb_install_versions()
{
	global $user;
	
	// Database
	$versions[2] = array(
		'0.0.1'		=> array(
			'table_add'	=> array(
				array('phpbb_articles', array(
					'COLUMNS'		=> array(
						'article_id'					=> array('UINT', NULL, 'auto_increment'),
						'cat_id'						=> array('UINT', 0),
						'article_title'					=> array('VCHAR', ''),
						'article_title_clean'			=> array('VCHAR', ''),
						'article_desc'					=> array('TEXT_UNI', ''),
						'article_desc_bitfield'			=> array('VCHAR', ''),
						'article_desc_options'			=> array('UINT:11', 7),
						'article_desc_uid'				=> array('VCHAR:8', ''),
						'article_text'					=> array('MTEXT_UNI', ''),
						'article_checksum'				=> array('VCHAR:32', ''),
						'article_status'				=> array('TINT:1', 0),
						'article_attachment'			=> array('BOOL', 0),
						'article_views'					=> array('UINT', 0),
						'article_comments'				=> array('UINT', 0),
						'article_user_id'				=> array('UINT', 0),
						'article_user_name'				=> array('VCHAR_UNI:255', ''),
						'article_user_color'			=> array('VCHAR:6', ''),
						'article_time'					=> array('TIMESTAMP', 0),
						'article_tags'					=> array('VCHAR', ''),
						'article_type'					=> array('UINT', 0),
						'article_votes'					=> array('UINT', 0),
						'enable_bbcode'					=> array('BOOL', 1),
						'enable_smilies'				=> array('BOOL', 1),
						'enable_magic_url'				=> array('BOOL', 1),
						'enable_sig'					=> array('BOOL', 1),
						'bbcode_bitfield'				=> array('VCHAR', ''),
						'bbcode_uid'					=> array('VCHAR:8', ''),
						'article_open'					=> array('BOOL', 0),
						'article_last_edit_time'		=> array('TIMESTAMP', 0),
						'article_last_edit_id'			=> array('UINT', 0),
						'article_edit_reason'			=> array('MTEXT_UNI', ''),
						'article_edit_reason_global'	=> array('BOOL', 0),
						'article_edit_type'				=> array('VCHAR', 'a:0:{}'),
						'article_edit_contribution'		=> array('BOOL', 0),
					),
					'PRIMARY_KEY'	=> 'article_id'
				)),

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
				
				array('phpbb_article_requests', array(
					'COLUMNS'		=> array(
						'request_id'				=> array('UINT', NULL, 'auto_increment'),
						'article_id'				=> array('UINT', 0),
						'request_accepted'			=> array('UINT', 0),
						'request_title'				=> array('VCHAR', ''),
						'request_text'				=> array('MTEXT_UNI', ''),
						'request_checksum'			=> array('VCHAR:32', ''),
						'request_status'			=> array('TINT:1', 0),
						'request_user_id'			=> array('UINT', 0),
						'request_user_name'			=> array('VCHAR_UNI:255', ''),
						'request_user_color'		=> array('VCHAR:6', ''),
						'request_time'				=> array('TIMESTAMP', 0),
						'bbcode_bitfield'			=> array('VCHAR', ''),
						'bbcode_uid'				=> array('VCHAR:8', ''),
					),
					'PRIMARY_KEY'	=> 'request_id'
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
						'plugin_menu'				=> array('BOOL', NO_MENU),
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
			
			// Existing phpbb tables
			'table_column_add' => array(
				array(EXTENSION_GROUPS_TABLE, 'allow_in_kb', array('BOOL', 0)),
				array(USERS_TABLE, 'user_articles', array('UINT', 0)),
				array(USERS_TABLE, 'user_kb_permissions', array('MTEXT', '')),
				array(USERS_TABLE, 'kb_last_visit', array('TIMESTAMP', 0)),
				array(USERS_TABLE, 'kb_last_marked', array('TIMESTAMP', 0)),
			),	
		),
	);
	
	// Permissions
	$versions[3] = array(
		'0.0.2'		=> array(
			'permission_add'	=> array(
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
		),
	);
	
	// Modules
	$versions[4] = array(
		'0.0.3'		=> array(
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
		),
	);
	
	// Configuration options
	$versions[5] = array(
		'1.0.1'		=> array(
			'config_add'	=> array(
				array('kb_allow_attachments', 1),
				array('kb_allow_sig', 1),
				array('kb_allow_smilies', 1),
				array('kb_allow_bbcode', 1),
				array('kb_allow_post_flash', 1),
				array('kb_allow_post_links', 1),
				array('kb_enable', 1),
				array('kb_seo', 0),
				array('kb_articles_per_page', 25),
				array('kb_comments_per_page', 25),
				array('kb_allow_subscribe', 1),
				array('kb_allow_bookmarks', 1),
				array('kb_last_article', 0, true),
				array('kb_last_updated', time(), true),
				array('kb_total_articles', 0, true),
				array('kb_total_comments', 0, true),
				array('kb_total_cats', 0),
				array('kb_desc_min_chars', 0),
				array('kb_desc_max_chars', 0),
				array('kb_link_name', $user->lang['KB']),
				array('kb_ajax_rating', 1),
				array('kb_disable_left_menu', 0),
				array('kb_disable_right_menu', 0),
				array('kb_left_menu_width', 240),
				array('kb_left_menu_type', 0),
				array('kb_right_menu_width', 240),
				array('kb_right_menu_type', 0),	
				array('kb_show_contrib', 1),
				array('kb_related_articles', 5),
				array('kb_email_article', 1),
				array('kb_ext_article_header', 1),
				array('kb_soc_bookmarks', 1),
				array('kb_export_article', 1),
				array('kb_show_desc_cat', 1),
				array('kb_show_desc_article', 1),
				array('kb_disable_desc', 0),
				array('kb_cats_per_row', 3),
				array('kb_layout_style'	, 1),
				array('kb_list_subcats', 1),
				array('kb_latest_articles_c', 5),
			),
		),
	);
	
	return $versions;
}

// Install function for 1.0.1 for full install
// Versions after this will be updated with new function
// Steps:
// 1. Welcome and confirmation
// 2. Write DB tables
// 3. Add permissions
// 4. Insert ACP modules
// 5. Add configuration options
// 6. Update to latest version (after 1.0.1)
// 7. Insert some default configuration
// 8. Finish up installation
function kb_install()
{
	global $db, $config, $user, $phpbb_root_path, $phpEx, $template;
	
	if (!class_exists('umil'))
	{
		$umil_file = $phpbb_root_path . 'umil/umil.' . $phpEx;
		if (!file_exists($umil_file))
		{
			trigger_error('KB_UPDATE_UMIL', E_USER_ERROR);
		}

		include($umil_file);
	}
	$umil = new umil(true);
	$step = isset($config['kb_install_step']) ? $config['kb_install_step'] : 1;
	
	// Actions
	switch($step)
	{
		case 1:
			// Welcome screen
			// Set a config here which makes sure we can return to the page if our internet goes down or we accidently close the browser
			$umil->config_add('kb_install_step', '2', true);
		break;
		
		case 2:
		case 3:
		case 4:
		case 5:
			// Do UMIL actions
			set_config('kb_install_step', -1);
			
			$versions = kb_install_versions();
			$umil->run_actions('update', $versions[$step], 'kb_version');
			
			set_config('kb_install_step', $step + 1);
		break;
		
		case 6:
			// Update to latest post 1.0.1
			set_config('kb_install_step', -1);
			
			$versions = kb_update_versions();
			$umil->run_actions('update', $versions, 'kb_version');
			
			set_config('kb_install_step', 7);
		break;
		
		case 7:
			// Insert default data
			set_config('kb_install_step', -1);
			
			// Setup basic plugins
			kb_install_perm_plugins('install', 'install');
			
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
			$roles = get_install_info('roles');
			$db->sql_multi_insert(ACL_ROLES_TABLE, $roles['roles']);
			
			$auth_settings = array();
			foreach($roles['auth'] as $role_name => $auth_ary)
			{
				$auth_settings[] = array('ROLE_KB_' . $role_name, $auth_ary);
			}
			$umil->permission_set($auth_settings);
			
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
			
			// Give permissions to admins to moderate the KB and add requests
			$umil->permission_set(array(
				array('ADMINISTRATORS', array('m_kb_author', 'm_kb_comment', 'm_kb_edit', 'm_kb_delete', 'm_kb_req_edit', 'm_kb_status', 'm_kb_time', 'm_kb_view'), 'group'),
				array('ADMINISTRATORS', 'u_kb_request', 'group'),
				array('REGISTERED', 'u_kb_request', 'group'),
			));
			
			set_config('kb_install_step', 8);
		break;
		
		case 8:
			// Finish screen
			add_log('admin', 'LOG_KB_INSTALL', KB_VERSION);
			$umil->config_remove('kb_install_step');
		break;
		
		case -1:
			// Broken installation
			trigger_error('INSTALL_BROKEN');
		break;
	}
	
	$steps = get_install_info('steps');
	$completion = '';
	foreach($steps as $step_number => $step_ary)
	{
		$end = ($step_number == 8) ? '' : '&nbsp;&gt;&nbsp;';
		$b_open = ($step == $step_number) ? '<span style="color: #105289;">' : '';
		$b_close = ($step == $step_number) ? '</span>' : '';
		$completion .= $b_open . $user->lang[$step_ary['title']] . $b_close . $end;
	}
	
	// Template links
	$template->assign_vars(array(
		'MESSAGE'			=> ($step == 8) ? sprintf($user->lang[$steps[$step]['message']], KB_VERSION) : $user->lang[$steps[$step]['message']],
		'TITLE'				=> $user->lang['INSTALL_KB'] . ' - ' . $user->lang[$steps[$step]['title']],
		'STEPS'				=> $completion,
		'U_NEXT_STEP'		=> append_sid($phpbb_root_path . 'kb.' . $phpEx),
		'SHOW_SUBMIT'		=> ($step == 8) ? false : true,
	));
	
	page_header($user->lang['INSTALL_KB'] . ' - ' . $user->lang[$steps[$step]['title']]);
	
	$template->set_filenames(array(
		'body' => 'kb/install_body.html')
	);

	page_footer();
}

// Updates KB to latest version
// Remember to update to 1.0.0 before updating further
function kb_update($old_version)
{
	global $phpbb_root_path, $phpEx, $user;
	
	if (!class_exists('umil'))
	{
		$umil_file = $phpbb_root_path . 'umil/umil.' . $phpEx;
		if (!file_exists($umil_file))
		{
			trigger_error('KB_UPDATE_UMIL', E_USER_ERROR);
		}

		include($umil_file);
	}
	$umil = new umil(true);
	
	if(confirm_box(true))
	{
		// Log the action
		$message = sprintf($user->lang['KB_UPDATED'], KB_VERSION);
		add_log('admin', 'LOG_KB_UPDATED', KB_VERSION, $old_version);
			
		// Update to 1.0.0 first then further
		if(version_compare($old_version, '1.0.0', '<'))
		{
			$version = get_old_versions();
			
			$umil->run_actions('update', $versions, 'kb_version');
			unset($versions);
		}
		
		$versions = kb_update_versions();
		
		$umil->run_actions('update', $versions, 'kb_version');
		unset($versions);
		
		trigger_error($message);
	}
	else
	{
		confirm_box(false, 'UPDATE_KB');
	}
	
	redirect(append_sid("{$phpbb_root_path}index.$phpEx"));
}

// Uninstalls the KB
// Is function dependant of acp_kb.php
// Steps: 1 - Init + reset db, 2 - downgrade to 1.0.1, 3 remove perms, config & modules, 4 remove db
function kb_uninstall($step, $u_action)
{
	global $phpbb_root_path, $phpEx, $config, $db;
	
	if(!class_exists('umil'))
	{
		if (!file_exists($phpbb_root_path . 'umil/umil.' . $phpEx))
		{
			trigger_error('KB_UPDATE_UMIL', E_USER_ERROR);
		}
	
		include($phpbb_root_path . 'umil/umil.' . $phpEx);
	}
	$umil = new umil(true);
	
	switch($step)
	{
		case 1:
			// False versions to delete during the various uninstallations
			$umil->config_add('kb_uninstall_step', '1', true);
			$umil->config_add('kb_version_3', '0.0.3', true);
			$umil->config_add('kb_version_2', '0.0.2', true);
			$umil->config_add('kb_version_1', '0.0.1', true);
			$umil->config_add('kb_version_r', '1.0.1', true);
			
			if(version_compare($config['kb_version'], '1.0.1', '>'))
			{
				$umil->config_add('kb_version_dg', $config['kb_version']);
			}
			
			// Uninstall plugins
			$sql = 'SELECT plugin_filename
					FROM ' . KB_PLUGIN_TABLE . ' 
					ORDER BY plugin_order ASC';
			$result = $db->sql_query($sql);
			
			$plugin_loc = $phpbb_root_path . 'includes/kb_plugins/';
			while($row = $db->sql_fetchrow($result))
			{
				include($plugin_loc . 'kb_' . $row['plugin_filename'] . '.' . $phpEx);
				uninstall_plugin($row['plugin_filename'], $plugin_loc, $u_action);
			}
			$db->sql_freeresult($result);
			
			reset_db();
		break;
		
		case 2:
			if(version_compare($config['kb_version'], '1.0.1', '>'))
			{
				$versions = kb_update_versions();
				$umil->run_actions('uninstall', $versions, 'kb_version_dg');
				unset($versions);
			}
		break;
		
		case 3:
			$versions = kb_install_versions();
			$umil->run_actions('uninstall', $versions[5], 'kb_version_r');
			$umil->run_actions('uninstall', $versions[3], 'kb_version_2');
			unset($versions);
		break;
		
		case 4:
			$versions = kb_install_versions();
			$umil->run_actions('uninstall', $versions[2], 'kb_version_1');
			$umil->run_actions('uninstall', $versions[4], 'kb_version_3');
			unset($versions);
			
			$umil->config_remove('kb_version');
			$umil->config_remove('kb_uninstall_step');
			return;
		break;
	}
	
	set_config('kb_uninstall_step', $step + 1);					
}

// Install permanent plugins on install or update
function kb_install_perm_plugins($action , $version)
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
	switch ($version)
	{
		case '0.2.4':
			$plugins = array('search');
		break;
		
		case '0.2.5':
			$plugins = array('stats');
		break;
		
		case '0.2.6':
			$plugins = array('request_list');
		break;
		
		case '0.4.6':
			$plugins = array('author', 'bookmark', 'contributors', 'email_article', 'export_article', 'rating', 'related_articles');
		break;
		
		case 'install':
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
		break;
	}
	
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

// Update plugin info on update
function kb_update_plugins($action , $version)
{
	global $phpbb_root_path;

	if ($action != 'update')
	{
		return;
	}
	
	if(!defined('IN_KB_PLUGIN')) // Killing notice when updating through several versions all using this function
	{
		define('IN_KB_PLUGIN', true);
	}
	
	switch ($version)
	{
		case '1.0.0RC2':
			$plugins = array('contributors', 'export_article', 'related_articles');
		break;
	}
	
	if (empty($plugins))
	{
		return;
	}
	
	foreach ($plugins as $plugin)
	{
		update_plugin($plugin, $phpbb_root_path . 'includes/kb_plugins/', false, false, true);
	}
}

function get_install_info($type)
{
	global $db;
	
	switch($type)
	{
		case 'roles':
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
		
		case 'steps':
			$steps = array(
				1	=> array(
					'title'		=> 'INSTALL_WELCOME',
					'message'	=> 'INSTALL_KB_CONFIRM',
				),
				2	=> array(
					'title'		=> 'INSTALL_DB',
					'message'	=> 'INSTALL_DB_TEXT',
				),
				3	=> array(
					'title'		=> 'INSTALL_PERMS',
					'message'	=> 'INSTALL_PERMS_TEXT',
				),
				4	=> array(
					'title'		=> 'INSTALL_MODULES',
					'message'	=> 'INSTALL_MODULES_TEXT',
				),
				5	=> array(
					'title'		=> 'INSTALL_CONFIG',
					'message'	=> 'INSTALL_CONFIG_TEXT',
				),
				6	=> array(
					'title'		=> 'INSTALL_UPDATE',
					'message'	=> 'INSTALL_UPDATE_TEXT',
				),
				7	=> array(
					'title'		=> 'INSTALL_DATA',
					'message'	=> 'INSTALL_DATA_TEXT',
				),
				8	=> array(
					'title'		=> 'KB_INSTALLED',
					'message'	=> 'KB_INSTALLED_TEXT',
				),
			);
		return $steps;
	}
}

//
// ALL FUNCTIONS BELOW THIS LINE ARE CUSTOM UPDATE FUNCTIONS
// (expect long and annoying function names)
//
function kb_update_1_0_2RC3_to_1_0_2($action, $version)
{
	global $db, $table_prefix;

	if($action != 'update')
	{
		return;
	}
	
	$sql = 'DELETE FROM ' . $table_prefix . 'article_edits';
	$db->sql_query($sql);
}
	
function kb_update_1_0_2RC2_to_1_0_2RC3($action, $version)
{
	global $db, $cache, $table_prefix;
	
	if($action != 'update')
	{
		return;
	}
	
	$cache->destroy('_kb_plugin_left_menu');
	$cache->destroy('_kb_plugin_right_menu');
	$cache->destroy('_kb_plugin_no_menu');
	
	// Fix article count
	$articles_by_cats = $articles_by_users = array();
	$total_articles = 0;
	$sql = 'SELECT cat_id, article_user_id
			FROM ' . $table_prefix . 'articles
			WHERE article_status = ' . STATUS_APPROVED . '
			ORDER BY article_user_id';
	$result = $db->sql_query($sql);
	
	while($row = $db->sql_fetchrow($result))
	{
		if(isset($articles_by_cats[$row['cat_id']]))
		{
			$articles_by_cats[$row['cat_id']]++;
		}
		else
		{
			$articles_by_cats[$row['cat_id']] = 1;
		}
		
		if(isset($articles_by_users[$row['article_user_id']]))
		{
			$articles_by_users[$row['article_user_id']]++;
		}
		else
		{
			$articles_by_users[$row['article_user_id']] = 1;
		}
		
		$total_articles++;
	}
	$db->sql_freeresult($result);
	$articles_by_users = array_unique($articles_by_users);
	
	foreach($articles_by_users as $user_id => $article_count)
	{
		$sql = 'UPDATE ' . $table_prefix . 'users
				SET user_articles = ' . $article_count . '
				WHERE user_id = ' . $user_id;
		$db->sql_query($sql);
	}
	
	foreach($articles_by_cats as $cat_id => $article_count)
	{
		$sql = 'UPDATE ' . $table_prefix . 'article_cats
				SET cat_articles = ' . $article_count . '
				WHERE cat_id = ' . $cat_id;
		$db->sql_query($sql);
	}
	
	set_config('kb_total_articles', $total_articles, true);
}

function kb_update_1_0_0_to_1_0_1($action, $version)
{
	global $auth, $phpbb_root_path, $phpEx, $cache, $db;
	
	if($action != 'update')
	{
		return;
	}
	
	// To do here
	// Remove silly u_kb_request from roles
	// Fix permanent plugins activated on wrong pages
	// Move kb permission to new tables
	$sql = "SELECT auth_option_id
			FROM " . ACL_OPTIONS_TABLE . "
			WHERE auth_option = 'u_kb_request'";
	$result = $db->sql_query($sql);
	$req_auth_id = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
	
	$sql = 'DELETE FROM ' . ACL_ROLES_DATA_TABLE . '
			WHERE auth_option_id = ' . $req_auth_id['auth_option_id'];
	$db->sql_query($sql);
	
	$sql = 'SELECT plugin_id, plugin_filename, plugin_pages 
			FROM ' . KB_PLUGIN_TABLE;
	$result = $db->sql_query($sql);
	
	define('IN_KB_PLUGIN', true);
	while($plugin = $db->sql_fetchrow($result))
	{
		include($phpbb_root_path . 'includes/kb_plugins/kb_' . $plugin['plugin_filename'] . '.' . $phpEx);
		
		$plugin_pages = unserialize($plugin['plugin_pages']);
		$new_plugin_pages = array();
		
		if(count($plugin_pages))
		{
			foreach($plugin_pages as $page)
			{
				if(in_array($page, $details['PLUGIN_PAGES']) || in_array('all', $details['PLUGIN_PAGES']))
				{
					$new_plugin_pages[] = $page;
				}
			}
			
			$new_ary = serialize($new_plugin_pages);
			$sql = "UPDATE " . KB_PLUGIN_TABLE . "
					SET plugin_pages = '{$new_ary}'
					WHERE plugin_id = " . $plugin['plugin_id'];
			$db->sql_query($sql);
		}
	}
	$db->sql_freeresult($result);
	
	$cache->destroy('_kb_plugin_left_menu');
	$cache->destroy('_kb_plugin_right_menu');
	$cache->destroy('_kb_plugin_no_menu');
	
	$sql = 'SELECT *
			FROM ' . ACL_USERS_TABLE . '
			WHERE kb_auth = 1';
	$result = $db->sql_query($sql);
	$sql_data = array();
	while($row = $db->sql_fetchrow($result))
	{
		$sql_data['users'][] = array(
			'user_id'			=> $row['user_id'],
			'forum_id'			=> $row['forum_id'],
			'auth_option_id'	=> $row['auth_option_id'],
			'auth_role_id'		=> $row['auth_role_id'],
			'auth_setting'		=> $row['auth_setting'],
		);
	}
	$db->sql_freeresult($result);
	
	$sql = 'SELECT *
			FROM ' . ACL_GROUPS_TABLE . '
			WHERE kb_auth = 1';
	$result = $db->sql_query($sql);
	while($row = $db->sql_fetchrow($result))
	{
		$sql_data['groups'][] = array(
			'group_id'			=> $row['group_id'],
			'forum_id'			=> $row['forum_id'],
			'auth_option_id'	=> $row['auth_option_id'],
			'auth_role_id'		=> $row['auth_role_id'],
			'auth_setting'		=> $row['auth_setting'],
		);
	}
	$db->sql_freeresult($result);
	
	$db->sql_multi_insert(KB_ACL_USERS_TABLE, $sql_data['users']);
	$db->sql_multi_insert(KB_ACL_GROUPS_TABLE, $sql_data['groups']);
	
	$sql = 'DELETE FROM ' . ACL_GROUPS_TABLE . '
			WHERE kb_auth = 1';
	$db->sql_query($sql);
	
	$sql = 'DELETE FROM ' . ACL_USERS_TABLE . '
			WHERE kb_auth = 1';
	$db->sql_query($sql);
	
	if(!class_exists('auth_admin'))
	{
		include($phpbb_root_path . 'includes/acp/auth.' . $phpEx);
	}
	$auth_admin = new auth_admin;
	$auth_admin->acl_clear_prefetch();
	
	return;
}

function kb_delete_permission_roles($action , $version)
{
	global $phpbb_root_path, $db;

	if ($action == 'uninstall')
	{
		$sql = 'DELETE FROM ' . ACL_ROLES_TABLE . " 
				WHERE role_type " . $db->sql_like_expression('u_kb\_' . $db->any_char);;
		$db->sql_query($sql);
	}
	
	return;
}

function kb_update_0_0_11_to_0_0_12($action, $version)
{
	global $db, $table_prefix;
	
	// Only run function when updating
	if($action != 'update')
	{
		return;
	}
	
	$combination = 'kbdev69htygfhdslo908';
	$sql = 'SELECT cat_id, latest_ids, latest_titles 
			FROM ' . $table_prefix . 'article_cats
			ORDER BY cat_id DESC';
	$result = $db->sql_query($sql);
	while($row = $db->sql_fetchrow($result))
	{
		$new_data = array();
		if($row['latest_ids'] == '' || $row['latest_titles'] == '')
		{
			// Empty, continue
			continue;
		}
		
		$latest_ids = explode($combination, $row['latest_ids']);
		$latest_titles = explode($combination, $row['latest_titles']);
		
		for($i = 0; $i < count($latest_ids); $i++)
		{
			if(isset($latest_ids[$i]) && isset($latest_titles[$i]))
			{
				$new_data[] = array(
					'article_id'	=> $latest_ids[$i],
					'article_title'	=> $latest_titles[$i],
				);
			}
		}
		
		$sql = 'UPDATE ' . $table_prefix . "article_cats
				SET latest_ids = '" . serialize($new_data) . "'
				WHERE cat_id = '" . $row['cat_id'] . "'";
		$db->sql_query($sql);
	}
	$db->sql_freeresult($result);
}

/*
Write article titles into the new clean tag
*/
function kb_update_031_to_032($action, $version)
{
	global $db, $table_prefix;
	
	// Only run function when updating
	if($action != 'update')
	{
		return;
	}
	
	$sql = "SELECT article_id, article_title
			FROM " . $table_prefix . "articles";
	$result = $db->sql_query($sql);
	
	$articles = array();
	while($row = $db->sql_fetchrow($result))
	{
		$articles[$row['article_id']] = utf8_clean_string($row['article_title']);
	}
	$db->sql_freeresult($result);
	
	foreach($articles as $article_id => $clean_title)
	{
		$sql = 'UPDATE ' . $table_prefix . 'articles  
				SET article_title_clean = "' . $clean_title . '"
				WHERE article_id = ' . $article_id;
		$db->sql_query($sql);
	}

}

// Basicly clear history table due to revamp of it
function kb_update_033_to_034($action, $version)
{
	global $db, $table_prefix;
	
	// Only run function when updating
	if($action != 'update')
	{
		return;
	}
	
	$sql = "DELETE FROM " . $table_prefix . "article_edits";
	$db->sql_query($sql);
	
	// Reset edit info in main table
	$sql = "UPDATE " . $table_prefix . "articles
			SET article_last_edit_id = '0', article_last_edit_time = '0', article_edit_reason = '', article_edit_reason_global = '1'";
	$db->sql_query($sql);
}

// Resync cat count and latest articles
function kb_update_400_to_401($action, $version)
{
	global $db, $table_prefix, $cache;
	
	if($action != 'update')
	{
		return;
	}
	
	$sql = 'SELECT cat_id
			FROM ' . $table_prefix . 'article_cats
			ORDER BY left_id ASC';
	$result = $db->sql_query($sql);
	
	$cat_ids = array();
	while($row = $db->sql_fetchrow($result))
	{
		$cat_ids[] = $row['cat_id'];
	}
	$db->sql_freeresult($result);
	
	foreach($cat_ids as $cat_id)
	{
		$sql = 'SELECT article_id, article_title
				FROM ' . $table_prefix . 'articles
				WHERE cat_id = ' . (int) $cat_id . ' 
				ORDER BY article_last_edit_time DESC';
		$result = $db->sql_query_limit($sql, 4);
		
		$latest_articles = array();
		while($row = $db->sql_fetchrow($result))
		{
			$latest_articles[] = array(
				'article_id'	=> $row['article_id'],
				'article_title'	=> $row['article_title'],
			);
		}
		$db->sql_freeresult($result);
		
		$sql_ary = array('latest_ids' => serialize($latest_articles));
		$sql = 'UPDATE ' . $table_prefix . 'article_cats SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
				WHERE cat_id = ' . (int) $cat_id;
		$db->sql_query($sql);
	}
	
	// Resync cat count
	set_config('kb_total_cats', count($cat_ids));
	$cache->destroy('config');
}


/// For 042 we fix problems with history edit_type and resync user article count
function kb_update_401_to_402($action, $version)
{
	global $db, $table_prefix;
	
	if($action != 'update')
	{
		return;
	}
	
	// Fix article count for users
	$sql = 'SELECT article_user_id
			FROM ' . $table_prefix . 'articles
			ORDER BY article_user_id';
	$result = $db->sql_query($sql);
	
	$done = array();
	$users = array();
	while($row = $db->sql_fetchrow($result))
	{
		if(!isset($done[$row['article_user_id']]))
		{
			$done[$row['article_user_id']] = true;
			$users[] = $row['article_user_id'];
		}
	}
	$db->sql_freeresult($result);
	
	foreach($users as $user_id)
	{
		$sql = 'SELECT COUNT(article_id) AS num_articles
				FROM ' . $table_prefix . 'articles
				WHERE article_user_id = ' . $user_id;
		$result = $db->sql_query($sql);
		$articles_count = (int) $db->sql_fetchfield('num_articles', $result);
		$db->sql_freeresult($result);
		
		$sql = 'UPDATE ' . $table_prefix . 'users
				SET user_articles = ' . $articles_count . '
				WHERE user_id = ' . $user_id;
		$db->sql_query($sql);
	}
	
	// Fix history
	$sql = 'UPDATE ' . $table_prefix . 'article_edits
			SET edit_type = "a:1:{i:0;i:7;}"
			WHERE edit_type = 7';
	$db->sql_query($sql);
	
	$sql = 'UPDATE ' . $table_prefix . 'article_edits
			SET edit_type = "a:1:{i:0;i:7;}"
			WHERE edit_type = 7';
	$db->sql_query($sql);
}

//
// DEPLETED FUNCTION, DO NOT MODIFY, DO NOT USE, DO NOT REMOVE
//
function get_old_versions()
{
	global $user;

	$versions = array(
		'0.0.1'		=> array(
			'table_add'	=> array(
				array('phpbb_articles', array(
					'COLUMNS'		=> array(
						'article_id'				=> array('UINT', NULL, 'auto_increment'),
						'cat_id'					=> array('UINT', 0),
						'article_title'				=> array('VCHAR', ''),
						'article_desc'				=> array('TEXT_UNI', ''),
						'article_desc_bitfield'		=> array('VCHAR', ''),
						'article_desc_options'		=> array('UINT:11', 7),
						'article_desc_uid'			=> array('VCHAR:8', ''),
						'article_text'				=> array('MTEXT_UNI', ''),
						'article_checksum'			=> array('VCHAR:32', ''),
						'article_status'			=> array('TINT:1', 0),
						'article_attachment'		=> array('BOOL', 0),
						'article_views'				=> array('UINT', 0),
						'article_comments'			=> array('UINT', 0),
						'article_user_id'			=> array('UINT', 0),
						'article_user_name'			=> array('VCHAR_UNI:255', ''),
						'article_user_color'		=> array('VCHAR:6', ''),
						'article_time'				=> array('TIMESTAMP', 0),
						'article_tags'				=> array('VCHAR', ''),
						'article_icon'				=> array('UINT', 0),
						'enable_bbcode'				=> array('BOOL', 1),
						'enable_smilies'			=> array('BOOL', 1),
						'enable_magic_url'			=> array('BOOL', 1),
						'enable_sig'				=> array('BOOL', 1),
						'bbcode_bitfield'			=> array('VCHAR', ''),
						'bbcode_uid'				=> array('VCHAR:8', ''),
						'article_last_edit_time'	=> array('TIMESTAMP', 0),
						'article_last_edit_id'		=> array('UINT', 0),
					),
					'PRIMARY_KEY'	=> 'article_id'
				)),

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
					),
					'PRIMARY_KEY'	=> 'cat_id',
					/*
					'KEYS'			=> array(
						'cat_name'			=> array('INDEX', 'cat_name'),
						'cat_desc'			=> array('FULLTEXT', 'cat_desc'),
					),
					*/
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
						'edit_article_status'			=> array('TINT:1', 1),
						'comment_id'					=> array('UINT', 0),
						'edit_moderated'				=> array('TINT:1', 0),
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
			),
		),
		
		'0.0.2'		=> array(
			'permission_add'	=> array(
				array('u_kb_read', true),
				array('u_kb_download', true),
				array('u_kb_comment', true),
				array('u_kb_add', true),
				array('u_kb_delete', true),
				array('u_kb_edit', true),
				array('u_kb_bbcode', true),
				array('u_kb_flash', true),
				array('u_kb_smilies', true),
				array('u_kb_img', true),
				array('u_kb_sigs', true),
				array('u_kb_attach', true),
				array('u_kb_icons', true),
				array('u_kb_rate', true),
				array('m_kb', true),
			),
		),
		
		'0.0.3'		=> array(
			'config_add'	=> array(
				array('kb_allow_attachments', 1),
				array('kb_allow_sig', 1),
				array('kb_allow_smilies', 1),
				array('kb_allow_bbcode', 1),
				array('kb_allow_post_flash', 1),
				array('kb_allow_post_links', 1),
				array('kb_enable', 1),
				array('kb_articles_per_page', 25),
				array('kb_comments_per_page', 25),
				array('kb_allow_subscribe', 1),
				array('kb_allow_bookmarks', 1),
			),
		),
		
		'0.0.4' => array(
			// Alright, now lets add some modules to the ACP
			'module_add' => array(
				// First, lets add a new category
				array('acp', 'ACP_CAT_DOT_MODS', 'ACP_KB'),

				// Now we will add the settings and features modes.
				array('acp', 'ACP_KB', array(
						'module_basename'		=> 'kb',
						'modes'					=> array('settings'),
					),
				),
			),
		),

		'0.0.5' => array(
			'config_add'	=> array(
				array('kb_last_article', 0, true),
				array('kb_last_updated', time(), true),
				array('kb_total_articles', 0, true),
				array('kb_total_comments', 0, true),
				array('kb_total_cats', 0),
			),
			
			'module_add' => array(
				array('ucp', '', 'UCP_KB'),
				
				array('ucp', 'UCP_KB', array(
						'module_basename'			=> 'kb',
						'modes'						=> array('front', 'subscribed', 'bookmarks', 'articles'),
					),
				),
			),
		),
		
		'0.0.6' => array(
			// Alright, now lets add some modules to the ACP
			'module_add' => array(
				array('acp', 'ACP_KB', array(
						'module_basename'		=> 'kb',
						'modes'					=> array('health_check'),
					),
				),
			),
		),
		
		'0.0.7' => array(
			// Alright, now lets add some modules to the ACP
			'module_add' => array(
				array('acp', 'ACP_KB', array(
						'module_basename'		=> 'kb_cats',
						'modes'					=> array('manage'),
					),
				),
			),
		),
		
		'0.0.8' => array(
			'table_column_remove' => array(
				array(KB_EDITS_TABLE, 'comment_id'), // Unused column
			),
			'table_column_add' => array(
				array(KB_TABLE, 'article_edit_reason', array('MTEXT_UNI', '')),
				array(KB_TABLE, 'article_edit_reason_global', array('BOOL', 0)),
				array(KB_EDITS_TABLE, 'edit_reason', array('MTEXT_UNI', '')),
				array(KB_EDITS_TABLE, 'edit_reason_global', array('BOOL', 0)),
			),
			
			'module_add' => array(
				array('mcp', '', 'MCP_KB'),
				array('mcp', 'MCP_KB', array(
						'module_basename'	=> 'kb',
						'modes'				=> array('queue', 'articles'),
					),
				),
			),
		),
		
		'0.0.9' => array(
			'table_column_add' => array(
				array(KB_CATS_TABLE, 'latest_ids', array('TEXT_UNI', NULL)),
				array(KB_CATS_TABLE, 'latest_titles', array('TEXT_UNI', NULL)),
			),
		),
		
		'0.0.10' => array(
			'table_column_add' => array(
				array(EXTENSION_GROUPS_TABLE, 'allow_in_kb', array('BOOL', 0)),
			),
		),
		
		'0.0.11' => array(
			'table_column_add' => array(
				// Adding some new DB tables to lessen db queries
				array(USERS_TABLE, 'user_articles', array('UINT', 0)),
				array(KB_TABLE, 'article_votes', array('UINT', 0)),
			),
			// New permission to add articles without approval
			'permission_add' => array(
				array('u_kb_add_wa', true), // Add articles without approval
				array('u_kb_viewhistory', true), // View history of articles
			),
		),
		
		'0.0.12' => array(
			'custom'	=> 'kb_update_0_0_11_to_0_0_12',
						  
			'table_column_remove' => array(
				array(KB_CATS_TABLE, 'latest_titles'), // Now unused
				array(KB_TABLE, 'article_icon'),
			),
			
			'table_column_add' => array(
				array(KB_TABLE, 'article_type', array('UINT', 0)),
			),
			
			// Add permissions for types as well as requests
			'permission_add'	=> array(
				array('u_kb_request', true),
				array('u_kb_types', true),
			),
			
			// Add 2 new tables for requests and article types
			'table_add'	=> array(
				array('phpbb_article_requests', array(
					'COLUMNS'		=> array(
						'request_id'				=> array('UINT', NULL, 'auto_increment'),
						'article_id'				=> array('UINT', 0),
						'request_accepted'			=> array('UINT', 0),
						'request_title'				=> array('VCHAR', ''),
						'request_text'				=> array('MTEXT_UNI', ''),
						'request_checksum'			=> array('VCHAR:32', ''),
						'request_status'			=> array('TINT:1', 0),
						'request_user_id'			=> array('UINT', 0),
						'request_user_name'			=> array('VCHAR_UNI:255', ''),
						'request_user_color'		=> array('VCHAR:6', ''),
						'request_time'				=> array('TIMESTAMP', 0),
						'bbcode_bitfield'			=> array('VCHAR', ''),
						'bbcode_uid'				=> array('VCHAR:8', ''),
					),
					'PRIMARY_KEY'	=> 'request_id'
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
			),
			
			'module_add' => array(
				array('acp', 'ACP_KB', array(
						'module_basename'		=> 'kb_types',
						'modes'					=> array('manage'),
					),
				),
			),
		),
		
		// Updating to 0.1.0 with no changes since 0.0.12
		'0.1.0'	=> array(
			array(),
		),

		'0.1.1'	=> array(
			'module_add' => array(
				array('acp', 'ACP_KB', array(
						'module_basename'		=> 'kb',
						'modes'					=> array('plugins'),
					),
				),
			),
			
			'config_add'	=> array(
				array('kb_latest_article_enable', 1),
				array('kb_latest_article_menu', LEFT_MENU),
			),
		),			
		
		// Adding localized permissions, therefore 0.2.0 as it is major change
		'0.2.0' => array(
			'table_column_add'	=> array(
				array(ACL_USERS_TABLE, 'kb_auth', array('BOOL', 0)),
				array(ACL_GROUPS_TABLE, 'kb_auth', array('BOOL', 0)),
			),
			
			// Add ACP modules
			'module_add' => array(
				array('acp', 'ACP_KB', array(
						'module_basename'		=> 'kb_permissions',
						'modes'					=> array('set_permissions', 'set_roles'),
					),
				),
			),
			
			// Delete permissions and add localized ones later
			'permission_remove'	=> array(
				array('u_kb_read', true),
				array('u_kb_add', true),
				array('u_kb_add_wa', true),
				array('u_kb_attach', true),
				array('u_kb_bbcode', true),
				array('u_kb_comment', true),
				array('u_kb_delete', true),
				array('u_kb_download', true),
				array('u_kb_edit', true),
				array('u_kb_flash', true),
				array('u_kb_icons', true),
				array('u_kb_img', true),
				array('u_kb_rate', true),
				array('u_kb_sigs', true),
				array('u_kb_smilies', true),
				array('u_kb_types', true),
			),
			
			// Now add them as local permissions
			'permission_add'	=> array(
				array('u_kb_read', false),
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
			),
		),
	
		'0.2.1'	=> array(
			'table_add'	=> array(
				array('phpbb_article_plugins', array(
					'COLUMNS'		=> array(
						'plugin_id'					=> array('UINT', NULL, 'auto_increment'),
						'plugin_name'				=> array('VCHAR', ''),
						'plugin_filename'			=> array('VCHAR', ''),
						'plugin_desc'				=> array('TEXT_UNI', ''),
						'plugin_copy'				=> array('VCHAR', ''),
						'plugin_version'			=> array('VCHAR:20', ''),
						'plugin_menu'				=> array('BOOL', NO_MENU),
						'plugin_order'				=> array('BOOL', 0),
					),
					'PRIMARY_KEY'	=> 'plugin_id'
				)),
			),			
			
			'config_remove'	=> array(
				array('kb_latest_article_enable'),
				array('kb_latest_article_menu'),
				array('kb_last_article'),
			),
		),	
		
		'0.2.2' => array(
			'table_column_add'	=> array(
				array(KB_PLUGIN_TABLE, 'plugin_pages', array('TEXT_UNI', 'a:0:{}'))
			),
		),
		
		'0.2.3' => array(
			'table_column_add'	=> array(
				array(KB_PLUGIN_TABLE, 'plugin_pages_perm', array('TEXT_UNI', 'a:0:{}'))
			),
		),
		
		'0.2.4' => array(
			'table_column_add'	=> array(
				array(KB_PLUGIN_TABLE, 'plugin_perm', array('BOOL', 0))
			),
			
			'custom'	=> 'kb_install_perm_plugins',
		),
		
		'0.2.5' => array(
			'custom'	=> 'kb_install_perm_plugins',
		),
		
		'0.2.6' => array(
			'custom'	=> 'kb_install_perm_plugins',
		),
		
		'0.3.0' => array(
			// New release ;)
		),
		
		'0.3.1' => array(
			// Code change ;)
			'custom'	=> 'kb_delete_permission_roles',
		),
		
		'0.3.2' => array(
			// making search better, I hope
			'table_column_add' => array(
				array(KB_TABLE, 'article_title_clean', array('VCHAR', '')),
			),
			'custom' => 'kb_update_031_to_032',
		),
		
		'0.3.3' => array(
			// New acp settings & improved the somewhat confusing permission system a bit
			'config_add' => array(
				array('kb_desc_min_chars', 0),
				array('kb_desc_max_chars', 0),
			),
			
			'permission_remove' => array(
				array('u_kb_viewhistory', true),
			),
			
			'permission_add' => array(
				array('u_kb_viewhistory', false),
				array('u_kb_view', false),
			),
		),
		
		// New release includes revamp of moderator permissions
		'0.3.4' => array(
			'permission_remove' => array(
				array('m_kb', true),
			),
			
			'permission_add' => array(
				array('u_kb_add_op', false),
				array('u_kb_add_co', false),
				array('m_kb_time', true),
				array('m_kb_author', true),
				array('m_kb_view', true),
				array('m_kb_comment', true),
				array('m_kb_edit', true),
				array('m_kb_delete', true),
				array('m_kb_req_edit', true),
				array('m_kb_status', true),
			),
			
			// Need to add contributions table aswell I think, or perhaps just a field
			
		),
		
		'0.3.5' => array(
			// Remove and add moderation modules again due to new permission
			'module_remove' => array(
				array('mcp', 'MCP_KB', array(
						'module_basename'	=> 'kb',
						'modes'				=> array('queue', 'articles'),
					),
				),
				array('mcp', '', 'MCP_KB'),
			),
			
			// Add them again
			'module_add' => array(
				array('mcp', '', 'MCP_KB'),
				array('mcp', 'MCP_KB', array(
						'module_basename'	=> 'kb',
						'modes'				=> array('queue', 'articles'),
					),
				),
			),
			
			'table_column_add' => array(
				array(KB_EDITS_TABLE, 'edit_type', array('VCHAR', 'a:0:{}')),
				array(KB_EDITS_TABLE, 'edit_cat_id', array('UINT', 0)),
				array(KB_EDITS_TABLE, 'edit_article_tags', array('VCHAR', '')),
				array(KB_EDITS_TABLE, 'edit_article_type', array('UINT', 0)),
				array(KB_EDITS_TABLE, 'edit_contribution', array('BOOL', 0)),
				array(KB_TABLE, 'article_open', array('BOOL', 0)),
			),
			
			/* Not removing these anyways
			'table_column_remove' => array(
				array(KB_EDITS_TABLE, 'edit_enable_bbcode'),
				array(KB_EDITS_TABLE, 'edit_enable_smilies'),
				array(KB_EDITS_TABLE, 'edit_enable_magic_url'),
				array(KB_EDITS_TABLE, 'edit_enable_sig'),
			),
			*/
			
			// Clean history table
			'custom' => 'kb_update_033_to_034',
		),
		
		// Just spitting out versions as next release will be final beta release 0.4.0
		'0.3.6' => array(
			// Less queries when introducing these 2 columns... genious right?
			'table_column_add' => array(
				array(KB_TABLE, 'article_edit_type', array('VCHAR', 'a:0:{}')),
				array(KB_TABLE, 'article_edit_contribution', array('BOOL', 0)),
			),
		),
		
		'0.3.7' => array(
			'config_add' => array(
				array('kb_link_name', $user->lang['KB']),
			),
		),
		
		'0.4.0' => array(
			// Last version update for major changes
		),
		
		'0.4.1' => array(
			// Bug updates, resync latest articles and cat count
			'custom' => 'kb_update_400_to_401',
		),
		
		'0.4.2' => array(
			// New config, resync user articles count and update history
			'config_add' => array(
				array('kb_ajax_rating', 1),
			),
			
			'custom' => 'kb_update_401_to_402',
		),
		
		'0.4.3' => array(
			// More config
			'config_add' => array(
				array('kb_disable_left_menu', 0),
				array('kb_disable_right_menu', 0),
				array('kb_left_menu_width', 240),
				array('kb_left_menu_type', 0),
				array('kb_right_menu_width', 240),
				array('kb_right_menu_type', 0),	
			),
		),
		
		'0.4.4' => array(
			// Even more config options.... yay
			'config_add' => array(
				array('kb_show_contrib', 1),
				array('kb_related_articles', 5),
				array('kb_email_article', 1),
				array('kb_ext_article_header', 1),
				array('kb_soc_bookmarks', 1),
				array('kb_export_article', 1),
				array('kb_show_desc_cat', 1),
				array('kb_show_desc_article', 1),
				array('kb_disable_desc', 0),
			),
		),
		
		'0.4.5' => array(
			'config_add' => array(
				array('kb_cats_per_row', 3),
				array('kb_layout_style'	, 1),
				array('kb_list_subcats', 1),
				array('kb_latest_articles_c', 5),
			),
		),
		
		'0.4.6' => array(
			'custom'	=> 'kb_install_perm_plugins',
		),
		
		'1.0.0RC1'	=> array(
			// Tagging RC1
		),
		
		'1.0.0RC2'	=> array(
			'custom'	=> 'kb_update_plugins',
		),
		
		'1.0.0RC3'	=> array(
			// Tagging RC3
		),
		
		'1.0.0'	=> array(
			// Tagging 1.0.0
		),
		
		//
		// LAST VERSION USE NEW FUNCTION!!!!!!!!!!!!!!!!
		//
	);
	
	return $versions;
}
?>