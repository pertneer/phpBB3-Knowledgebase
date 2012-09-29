<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: mcp_kb.php 504 2010-06-21 14:38:48Z andreas.nexmann@gmail.com $
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

/**
* mcp_kb
* MCP Knowledge Base
* @package ucp
*/
class mcp_kb
{
	var $p_master;
	var $u_action;

	function mcp_kb(&$p_master)
	{
		$this->p_master = &$p_master;
	}

	function main($id, $mode)
	{
		global $config, $db, $user, $auth, $template, $cache;
		global $phpbb_root_path, $phpEx, $table_prefix;
		
		$user->add_lang('mods/kb');
		include($phpbb_root_path . 'includes/constants_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/functions_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/functions_plugins_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/kb.' . $phpEx);
		include($phpbb_root_path . 'includes/kb_auth.' . $phpEx);
		$kb_auth = new kb_auth;
		$kb_auth->acl($user->data, $auth);

		$submit = (isset($_POST['submit'])) ? true : false;
		$kb = new knowledge_base(0, false);
		
		// Not sure about auth being checked when we don't specify a mode?
		if(!$auth->acl_gets('m_kb_status', 'm_kb_comment'))
		{
			trigger_error('NO_MCP_PERM');
		}
		
		// "Hidden" modes which are called from kb.php to generate comment posting, viewing or alike.
		// Generally just modes we don't want in the nav bar.
		$hidden_mode = request_var('hmode', '');
		switch($hidden_mode)
		{
			case 'comment':
				$page_title = $kb->comment_posting('mcp');
				$this->tpl_name = 'kb/posting_body';
				$this->page_title = $page_title;
			break;
			
			case 'status':
				$submit = (isset($_POST['submit'])) ? true : false;
				$article_id = request_var('a', 0);
				if(!$article_id)
				{
					trigger_error('KB_NO_ARTICLE');
				}
				
				$sql = "SELECT *
						FROM " . KB_TABLE . "
						WHERE article_id = $article_id";
				$result = $db->sql_query($sql);
				if(!$article_data = $db->sql_fetchrow($result))
				{
					trigger_error('KB_NO_ARTICLE');
				}
				$db->sql_freeresult($result);
				
				// Alter status
				if($submit)
				{
					$status = request_var('status', 0);
					$reason = utf8_normalize_nfc(request_var('reason', '', true));
					$global = request_var('reason_global', false);
					
					if($status == STATUS_APPROVED && $article_data['article_status'] != STATUS_APPROVED) // Secure against inflation
					{
						$sql = 'UPDATE ' . KB_REQ_TABLE . '
								SET request_status = ' . STATUS_ADDED . '
								WHERE article_id = ' . $article_id;
						$db->sql_query($sql);
						
						$sql = 'UPDATE ' . KB_CATS_TABLE . '
								SET cat_articles = cat_articles + 1
								WHERE cat_id = ' . $article_data['cat_id'];
						$db->sql_query($sql);
						
						$sql = 'UPDATE ' . USERS_TABLE . '
								SET user_articles = user_articles + 1
								WHERE user_id = ' . $article_data['article_user_id'];
						$db->sql_query($sql);
						
						// handle latest article list for cat
						$late_articles = array(
							'article_id'		=> $article_id,
							'article_title'		=> $article_data['article_title'],
						);
						handle_latest_articles('add', $article_data['cat_id'], $late_articles, $config['kb_latest_articles_c']);
					
						set_config('kb_last_article', $article_id, true);
						set_config('kb_last_updated', time(), true);
						set_config('kb_total_articles', $config['kb_total_articles'] + 1, true);
					}
					else if($article_data['article_status'] == STATUS_APPROVED && $status != STATUS_APPROVED) // Reduce count when deactivating article
					{
						$sql = 'UPDATE ' . KB_CATS_TABLE . '
								SET cat_articles = cat_articles - 1
								WHERE cat_articles > 0
								AND cat_id = ' . $article_data['cat_id'];
						$db->sql_query($sql);
						
						$sql = 'UPDATE ' . USERS_TABLE . '
								SET user_articles = user_articles - 1
								WHERE user_articles > 0
								AND user_id = ' . $article_data['article_user_id'];
						$db->sql_query($sql);
						
						// Remove from latest article when deactivating
						$late_articles = array(
							'article_id'		=> $article_id,
							'article_title'		=> $article_data['article_title'],
						);
						handle_latest_articles('delete', $article_data['cat_id'], $late_articles, $config['kb_latest_articles_c']);
						
						set_config('kb_last_updated', time(), true);
						set_config('kb_total_articles', $config['kb_total_articles'] - 1, true);
					}
					
					// Generate an entry into the edit table, only for the status change
					// Build edits table to take care of old data
					$article_data += array(
						'edit_time'					=> time(),
						'edit_reason'				=> $reason,
						'edit_reason_global'		=> ($global) ? 1 : 0,
						'edit_type'					=> array(EDIT_TYPE_STATUS),
						'message'					=> $article_data['article_text'],
						'enable_urls'				=> $article_data['enable_magic_url'],
						'article_contribution'		=> 0,
					);
					$edit_id = edit_submit($article_data, true, $article_id);
					
					$data = array(
						'article_status'				=> $status,
						'article_last_edit_time'		=> time(),
						'article_last_edit_id'			=> $edit_id,
						'article_edit_type'				=> serialize(array(EDIT_TYPE_STATUS)),
					);
					
					$sql = 'UPDATE ' . KB_TABLE . ' 
							SET ' . $db->sql_build_array('UPDATE', $data) . '
							WHERE article_id = ' . $article_id;
					$db->sql_query($sql);
					
					// Notify on status change
					$notify_on = array(NOTIFY_STATUS_CHANGE);
					kb_handle_notification($article_id, $article_data['article_title'], $notify_on);
					
					$url = $this->u_action;
					$message = $user->lang['KB_SUCCESS_STATUS'] . '<br /><br />' . sprintf($user->lang['RETURN_PAGE'], '<a href="' . $url . '">', '</a>');
					meta_refresh(5, $url);
					trigger_error($message);
				}
				
				// Show form
				// build select, show reason, add style, build hidden
				$hidden_fields = build_hidden_fields(array(
					'a'		=> $article_id,
				));
				
				$status_ary = array(
					STATUS_UNREVIEW		=> 'KB_STATUS_UNREVIEW',
					STATUS_APPROVED		=> 'KB_STATUS_APPROVED',
					STATUS_DISAPPROVED	=> 'KB_STATUS_DISAPPROVED',
					STATUS_ONHOLD		=> 'KB_STATUS_ONHOLD',
				);
				
				$status_options = '';
				foreach($status_ary as $value => $name)
				{
					$selected = ($value == $article_data['article_status']) ? ' selected="selected"' : '';
					$status_options .= '<option value="' . $value . '"' . $selected . '>' . $user->lang[$name] . '</option>';
				}
				
				$template->assign_vars(array(
					'S_MCP_ACTION'			=> append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=kb&amp;hmode=status'),
					'STATUS_OPTIONS'		=> $status_options,
					'L_STATUS'				=> $user->lang['ARTICLE_STATUS'],
					'L_TITLE'				=> $user->lang['CHANGE_STATUS'],
					'L_REASON'				=> $user->lang['KB_EDIT_REASON'],
					'L_EDIT_REASON_GLOBAL'	=> $user->lang['KB_EDIT_REASON_GLOBAL'],
					
					'L_KB_STATUS_EXPLAIN'	=> $user->lang['MCP_KB_STATUS_EXPLAIN'],
					'S_HIDDEN_FIELDS'		=> $hidden_fields,
				));
				
				$this->tpl_name = 'kb/mcp_kb_status';
				$this->page_title = $user->lang['CHANGE_STATUS'];
			break;
			
			case 'view':
				$page_title = $kb->generate_article_page('mcp');
				$this->tpl_name = 'kb/view_article';
				$this->page_title = $page_title;
			break;
		}
		
		// There are 2 default modes which are queue and articles, they generate output
		if($hidden_mode == '')
		{
			$icons = $cache->obtain_icons();
			$types = $cache->obtain_article_types();
			
			switch($mode)
			{
				case 'queue':
					$sql = "SELECT a.* 
							FROM " . KB_TABLE . " a
							WHERE a.article_status != " . STATUS_APPROVED . "
							ORDER BY a.article_last_edit_time DESC";
					$result = $db->sql_query($sql);
					while($row = $db->sql_fetchrow($result))
					{
						$folder_img = ($row['article_last_edit_time'] > $user->data['user_lastvisit']) ? 'topic_unread' : 'topic_read';
						
						$article_status_ary = array(
							STATUS_UNREVIEW		=> 'KB_STATUS_UNREVIEW',
							STATUS_DISAPPROVED	=> 'KB_STATUS_DISAPPROVED',
							STATUS_ONHOLD		=> 'KB_STATUS_ONHOLD',
						);
						
						// Set article type
						$article_type = gen_article_type($row['article_type'], $row['article_title'], $types, $icons);
						
						// Send vars to template
						$template->assign_block_vars('articlerow', array(
							'ARTICLE_ID'				=> $row['article_id'],
							'ARTICLE_AUTHOR_FULL'		=> get_username_string('full', $row['article_user_id'], $row['article_user_name'], $row['article_user_color']),
							'FIRST_POST_TIME'			=> $user->format_date($row['article_time']),
				
							'ARTICLE_LAST_EDIT'			=> gen_kb_edit_string($row['article_id'], $row['article_last_edit_id'], $row['article_time'], $row['article_last_edit_time']),
							'ARTICLE_TITLE'				=> censor_text($article_type['article_title']),
							'ARTICLE_FOLDER_IMG'		=> $user->img($folder_img, censor_text($row['article_title'])),
							'ARTICLE_FOLDER_IMG_SRC'	=> $user->img($folder_img, censor_text($row['article_title']), false, '', 'src'),
							'ARTICLE_FOLDER_IMG_ALT'	=> censor_text($row['article_title']),
							'ARTICLE_FOLDER_IMG_WIDTH'  => $user->img($folder_img, '', false, '', 'width'),
							'ARTICLE_FOLDER_IMG_HEIGHT'	=> $user->img($folder_img, '', false, '', 'height'),
							'ARTICLE_STATUS'			=> $user->lang[$article_status_ary[$row['article_status']]],
							
							'ARTICLE_TYPE_IMG'			=> $article_type['type_image']['img'],
							'ARTICLE_TYPE_IMG_WIDTH'	=> $article_type['type_image']['width'],
							'ARTICLE_TYPE_IMG_HEIGHT'	=> $article_type['type_image']['height'],
							'ATTACH_ICON_IMG'			=> ($auth->acl_get('u_kb_download', $row['cat_id']) && $row['article_attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
							
							'U_VIEW_ARTICLE'			=> append_sid("{$phpbb_root_path}mcp.$phpEx", "i=kb&amp;hmode=view&amp;a=" . $row['article_id']),
							'U_STATUS'					=> append_sid("{$phpbb_root_path}mcp.$phpEx", "i=kb&amp;hmode=status&amp;a=" . $row['article_id']),
						));
					}
					$db->sql_freeresult($result);
					
					$template->assign_vars(array(
						'L_TITLE'					=> $user->lang['MCP_KB_QUEUE'],
						'L_KB_QUEUE_EXPLAIN'		=> $user->lang['MCP_KB_QUEUE_EXPLAIN'],
						'L_NO_QUEUED_ARTICLES'		=> $user->lang['KB_NO_QUEUED_ARTICLES'],
						'L_QUEUED_ARTICLES'			=> $user->lang['KB_QUEUED_ARTICLES'],
						'L_STATUS'					=> $user->lang['ARTICLE_STATUS'],
						'L_ALTER_STATUS'			=> $user->lang['CHANGE_STATUS'],
					));
				break;
				
				case 'articles':
					$sql = "SELECT a.* 
							FROM " . KB_TABLE . " a
							WHERE a.article_status = " . STATUS_APPROVED . "
							ORDER BY a.article_last_edit_time DESC";
					$result = $db->sql_query($sql);
					while($row = $db->sql_fetchrow($result))
					{
						$folder_img = ($row['article_last_edit_time'] > $user->data['user_lastvisit']) ? 'topic_unread' : 'topic_read';
						
						// Set article type
						$article_type = gen_article_type($row['article_type'], $row['article_title'], $types, $icons);
						
						// Send vars to template
						$template->assign_block_vars('articlerow', array(
							'ARTICLE_ID'				=> $row['article_id'],
							'ARTICLE_AUTHOR_FULL'		=> get_username_string('full', $row['article_user_id'], $row['article_user_name'], $row['article_user_color']),
							'FIRST_POST_TIME'			=> $user->format_date($row['article_time']),
				
							'ARTICLE_LAST_EDIT'			=> gen_kb_edit_string($row['article_id'], $row['article_last_edit_id'], $row['article_time'], $row['article_last_edit_time']),
							'ARTICLE_TITLE'				=> censor_text($article_type['article_title']),
							'ARTICLE_FOLDER_IMG'		=> $user->img($folder_img, censor_text($row['article_title'])),
							'ARTICLE_FOLDER_IMG_SRC'	=> $user->img($folder_img, censor_text($row['article_title']), false, '', 'src'),
							'ARTICLE_FOLDER_IMG_ALT'	=> censor_text($row['article_title']),
							'ARTICLE_FOLDER_IMG_WIDTH'  => $user->img($folder_img, '', false, '', 'width'),
							'ARTICLE_FOLDER_IMG_HEIGHT'	=> $user->img($folder_img, '', false, '', 'height'),
							
							'ARTICLE_TYPE_IMG'			=> $article_type['type_image']['img'],
							'ARTICLE_TYPE_IMG_WIDTH'	=> $article_type['type_image']['width'],
							'ARTICLE_TYPE_IMG_HEIGHT'	=> $article_type['type_image']['height'],
							'ATTACH_ICON_IMG'			=> ($auth->acl_get('u_kb_download', $row['cat_id']) && $row['article_attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
							
							'U_VIEW_ARTICLE'			=> append_sid("{$phpbb_root_path}mcp.$phpEx", "i=kb&amp;mode=articles&amp;hmode=view&amp;a=" . $row['article_id']), // Mode articles here for style continuity
							'U_STATUS'					=> append_sid("{$phpbb_root_path}mcp.$phpEx", "i=kb&amp;mode=articles&amp;hmode=status&amp;a=" . $row['article_id']),
						));
					}
					$db->sql_freeresult($result);
					
					$template->assign_vars(array(
						'L_TITLE'					=> $user->lang['MCP_KB_ARTICLES'],
						'L_KB_ARTICLES_EXPLAIN'		=> $user->lang['MCP_KB_ARTICLES_EXPLAIN'],
						'L_NO_APPROVED_ARTICLES'	=> $user->lang['KB_NO_APPROVED_ARTICLES'],
						'L_APPROVED_ARTICLES'		=> $user->lang['KB_APPROVED_ARTICLES'],
						'L_ALTER_STATUS'			=> $user->lang['CHANGE_STATUS'],
					));
				break;
			}
			
			$this->tpl_name = 'kb/mcp_kb_' . $mode;
			$this->page_title = $user->lang['MCP_KB_' . strtoupper($mode)];
		}
	}
}
?>