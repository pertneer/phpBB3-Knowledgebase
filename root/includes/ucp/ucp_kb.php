<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: ucp_kb.php 405 2009-12-14 16:27:17Z softphp $
* @copyright (c) 2009 Andreas Nexmann
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
* ucp_kb
* UCP Knowledge Base
* @package ucp
*/
class ucp_kb
{
	var $p_master;
	var $u_action;

	function ucp_kb(&$p_master)
	{
		$this->p_master = &$p_master;
	}

	function main($id, $mode)
	{
		global $config, $db, $user, $auth, $template;
		global $cache, $phpbb_root_path, $phpEx, $table_prefix;
		
		$user->add_lang('mods/kb');
		include($phpbb_root_path . 'includes/constants_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/functions_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/functions_plugins_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/kb_auth.' . $phpEx);
		$kb_auth = new kb_auth;
		$kb_auth->acl($user->data, $auth);

		$action	= request_var('action', '');
		$submit = (isset($_POST['submit'])) ? true : false;

		$form_key = 'ucp_kb';
		add_form_key($form_key);
		
		// Get icons and types from cache
		$icons = $cache->obtain_icons();
		$types = $cache->obtain_article_types();
		
		switch ($mode)
		{
			case 'front':
				// Generate a bit of user stats and link for the user
				$articles = $wait_articles = $mod_comments = $comments = 0;
				$article_ids = array(); // used for last query
				
				$sql = 'SELECT article_id, article_status
						FROM ' . KB_TABLE . ' 
						WHERE article_user_id = ' . $user->data['user_id'];
				$result = $db->sql_query($sql);
				
				while($row = $db->sql_fetchrow($result))
				{
					if($row['article_status'] == STATUS_APPROVED)
					{
						$articles++;
					}
					else
					{
						$wait_articles++;
					}
					
					$article_ids[] = $row['article_id'];
				}
				$db->sql_freeresult($result);
				
				// comments
				$sql = 'SELECT COUNT(comment_id) as total_comments
						FROM ' . KB_COMMENTS_TABLE . ' 
						WHERE comment_user_id = ' . $user->data['user_id'];
				$result = $db->sql_query($sql);
				$comments = $db->sql_fetchfield('total_comments', $result);
				$db->sql_freeresult($result);
				
				// mod comments not read
				if(sizeof($article_ids))
				{
					$sql = 'SELECT COUNT(comment_id) as mod_comments
							FROM ' . KB_COMMENTS_TABLE . '
							WHERE comment_type = ' . COMMENT_MOD . ' 
							AND comment_time > ' . $user->data['user_lastvisit'] . '
							AND ' . $db->sql_in_set('article_id', $article_ids) . '
							AND NOT comment_user_id = ' . $user->data['user_id']; // Don't include own comments just in case
					$result = $db->sql_query($sql);
					$mod_comments = $db->sql_fetchfield('mod_comments', $result);
					$db->sql_freeresult($result);
				}
				
				// Assign template vars
				$template->assign_vars(array(
					'L_TITLE'					=> $user->lang['UCP_KB_FRONT'],
					'L_UCP_KB_WELCOME'			=> $user->lang['UCP_KB_FRONT_EXPLAIN'],
					'L_YOUR_KB_DETAILS'			=> $user->lang['YOUR_KB_DETAILS'],
					'L_APPROVED_ARTICLES'		=> $user->lang['APPROVED_ARTICLES'],
					'L_WAITING_ARTICLES'		=> $user->lang['WAITING_ARTICLES'],
					'L_COMMENTS'				=> $user->lang['COMMENTS'],
					'L_WAITING_MOD_COMMENTS'	=> $user->lang['WAITING_MOD_COMMENTS'],
					'L_SEARCH_YOUR_ARTICLES'	=> $user->lang['SEARCH_YOUR_ARTICLES'],
					'L_ARTICLE_STATUS_PAGE'		=> $user->lang['ARTICLE_STATUS_PAGE'],
					'L_RULE'					=> $user->lang['RULE'],
					
					'ARTICLES'					=> $articles,
					'WAITING_ARTICLES'			=> $wait_articles,
					'COMMENTS'					=> $comments,
					'MOD_COMMENTS'				=> $mod_comments,
					
					'U_SEARCH_ARTICLES'			=> append_sid("{$phpbb_root_path}kb.$phpEx", 'i=search&amp;author_id=' . $user->data['user_id']),
					'U_UCP_ARTICLES'			=> append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=kb&amp;mode=articles'),
				));
			break;
			
			// handles add, delete, edit and list of subscriptions
			case 'subscribed':
				switch($action)
				{
					case 'add':
					case 'edit':
						$article_id = request_var('a', 0);
						if(!$article_id)
						{
							trigger_error('KB_NO_ARTICLE');
						}
						
						if($submit && check_form_key($form_key))
						{
							// The user has submitted the data, insert it into the db
							$notify_by = request_var('notify_by', 0);
							$notify_on = request_var('notify_on', 0);
							$bookmarked = request_var('bookmarked', 0);
							$update = request_var('update', false);
							
							$sql_data = array(
								'article_id' 	=> $article_id,
								'user_id'	 	=> $user->data['user_id'],
								'subscribed'	=> 1,
								'bookmarked'	=> $bookmarked,
								'notify_by'		=> $notify_by,
								'notify_on'		=> $notify_on,
							);
							
							if($action == 'add' && !$update)
							{
								$sql = 'INSERT INTO ' . KB_TRACK_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data);
							}
							else
							{
								$sql = 'UPDATE ' . KB_TRACK_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_data) . "
										WHERE article_id = $article_id
										AND user_id = {$user->data['user_id']}";
							}
							$db->sql_query($sql);
							
							$url = $this->u_action;
							meta_refresh(3, $url);
							$message = $user->lang['KB_SUCCESS_SUBSCRIBE_' . strtoupper($action)] . '<br /><br />' . sprintf($user->lang['RETURN_UCP'], '<a href="' . $url . '">', '</a>');
							trigger_error($message);
						}
						
						switch($action)
						{
							case 'add':
								$sql = 'SELECT article_title
										FROM ' . KB_TABLE . "  
										WHERE article_id = $article_id
										AND article_status = " . STATUS_APPROVED;
							break;
							
							case 'edit':
								$sql = 'SELECT a.article_id, a.article_title, n.* 
										FROM ' . KB_TABLE . ' a, ' . KB_TRACK_TABLE . " n
										WHERE a.article_id = n.article_id
										AND a.article_status = " . STATUS_APPROVED . "
										AND n.user_id = {$user->data['user_id']}
										AND n.article_id = $article_id
										AND n.subscribed = 1";
							break;
						}
						$result = $db->sql_query($sql);
						$notify_data = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);
						
						if(empty($notify_data))
						{
							trigger_error('KB_NO_ARTICLE');
						}
						
						// Generate dropdown menus
						// Notify on
						$notify_on = array(
							'KB_NO_NOTIFY'					=> NO_NOTIFY,
							'KB_NOTIFY_EDIT_CONTENT'		=> NOTIFY_EDIT_CONTENT,
							'KB_NOTIFY_EDIT_ALL'			=> NOTIFY_EDIT_ALL,
							'KB_NOTIFY_COMMENT'				=> NOTIFY_COMMENT,
							'KB_NOTIFY_AUTHOR_COMMENT'		=> NOTIFY_AUTHOR_COMMENT,
							'KB_NOTIFY_MOD_COMMENT_GLOBAL'	=> NOTIFY_MOD_COMMENT_GLOBAL,
						);
						
						$notify_on_options = '';
						foreach($notify_on as $name => $value)
						{
							$selected = (($action == 'edit' && $notify_data['notify_on'] == $value) || ($action == 'add' && $value == NO_NOTIFY)) ? ' selected="selected"' : '';
							$notify_on_options .= '<option value="' . $value . '"' . $selected . '>' . $user->lang[$name] . '</option>';
						}
						
						// Notify by
						$notify_by = array(
							'KB_NOTIFY_UCP'					=> NOTIFY_UCP,
							'KB_NOTIFY_MAIL'				=> NOTIFY_MAIL,
							'KB_NOTIFY_PM'					=> NOTIFY_PM,
							'KB_NOTIFY_POPUP'				=> NOTIFY_POPUP,
						);
						
						$notify_by_options = '';
						foreach($notify_by as $name => $value)
						{
							$selected = (($action == 'edit' && $notify_data['notify_by'] == $value) || ($action == 'add' && $value == NOTIFY_UCP)) ? ' selected="selected"' : '';
							$notify_by_options .= '<option value="' . $value . '"' . $selected . '>' . $user->lang[$name] . '</option>';
						}
						
						// Check if there is a bookmark entry... just update that then
						$sql = "SELECT bookmarked 
								FROM " . KB_TRACK_TABLE . "
								WHERE article_id = $article_id
								AND user_id = {$user->data['user_id']}";
						$result = $db->sql_query($sql);
						$hidden_fields = array();
						if(!$update_data = $db->sql_fetchrow($result))
						{
							$update_data['bookmarked'] = 0;
						}
						else
						{
							$hidden_fields['update'] = 1;
						}
						$db->sql_freeresult($result);
						
						$hidden_fields = array_merge($hidden_fields, array(
							'a' 			=> $article_id,
							'bookmarked' 	=> $update_data['bookmarked'],
							'action'		=> $action,
						));
						$s_hidden_fields = build_hidden_fields($hidden_fields);
						
						unset($notify_by, $notify_on, $hidden_fields);
						$template->assign_vars(array(
							'L_TITLE'					=> $user->lang['UCP_KB_SUBSCRIBED'],
							'L_KB_SUBSCRIBE_EXPLAIN'	=> $user->lang['UCP_KB_SUBSCRIBED_EXPLAIN'],
							'L_NOTIFY_ON'				=> $user->lang['KB_NOTIFY_ON'],
							'L_NOTIFY_BY'				=> $user->lang['KB_NOTIFY_BY'],
							'L_STATUS_STRING'			=> ($action == 'edit') ? sprintf($user->lang['KB_SUBSCRIBE_STATUS_EDIT'], censor_text($notify_data['article_title'])) : sprintf($user->lang['KB_SUBSCRIBE_STATUS_ADD'], censor_text($notify_data['article_title'])),
							'NOTIFY_ON_OPTIONS'			=> $notify_on_options,
							'NOTIFY_BY_OPTIONS'			=> $notify_by_options,
							'S_SHOW_FORM'				=> true,
							'S_HIDDEN_FIELDS'			=> $s_hidden_fields,
							'S_UCP_ACTION'				=> $this->u_action,
						));
					break;
						
					case 'delete':
						// Show confirm box then remove
						$article_id = request_var('a', array(0 => 0)); // No array keys here, we want it passed along just as it is
						if(empty($article_id))
						{
							trigger_error('KB_NO_ARTICLE');
						}
						
						if(confirm_box(true))
						{
							$article_id = array_keys($article_id);
							// Retain bookmark info, then just delete
							$sql = "SELECT article_id, bookmarked 
									FROM " . KB_TRACK_TABLE . "
									WHERE " . $db->sql_in_set('article_id', $article_id) . "
									AND user_id = {$user->data['user_id']}";
							$result = $db->sql_query($sql);
							$articles_count = 0;
							$delete_data = array();
							while($row = $db->sql_fetchrow($result))
							{
								$articles_count++;
								if($row['bookmarked'] > 0)
								{
									$delete_data['update'][] = $row['article_id'];
								}
								else
								{
									$delete_data['delete'][] = $row['article_id'];
								}
							}
							$db->sql_freeresult($result);
							
							if(!$articles_count)
							{
								trigger_error('KB_NO_ARTICLES');
							}
							
							if(!empty($delete_data['update']))
							{
								$sql_data = array(
									'subscribed'	=> 0,
									'bookmarked'	=> 1,
									'notify_by'		=> 0,
									'notify_on'		=> 0,
								);
								
								$sql = 'UPDATE ' . KB_TRACK_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_data) . "
										WHERE " . $db->sql_in_set('article_id', $delete_data['update']) . "
										AND user_id = {$user->data['user_id']}";
								$db->sql_query($sql);
							}
							
							if(!empty($delete_data['delete']))
							{
								$sql = "DELETE FROM " . KB_TRACK_TABLE . "
										WHERE " . $db->sql_in_set('article_id', $delete_data['delete']) . "
										AND user_id = {$user->data['user_id']}";
								$db->sql_query($sql);
							}
							
							$url = $this->u_action;
							meta_refresh(3, $url);
							$message = $user->lang['KB_SUCCESS_SUBSCRIBE_' . strtoupper($action)] . '<br /><br />' . sprintf($user->lang['RETURN_UCP'], '<a href="' . $url . '">', '</a>');
							trigger_error($message);
						}
						else
						{
							$hidden_fields = build_hidden_fields(array(
								'action'	=> 'delete',
								'a'			=> $article_id,
							));
							confirm_box(false, 'KB_DELETE_SUBSCRIBE', $hidden_fields);
							redirect($this->u_action);
						}
					break;
						
					case '':
					default:
					$sql = "SELECT n.*, a.* 
							FROM " . KB_TRACK_TABLE . " n, " . KB_TABLE . " a
							WHERE n.subscribed > 0
							AND n.article_id = a.article_id
							AND n.user_id = {$user->data['user_id']}
							AND a.article_status = " . STATUS_APPROVED . "
							ORDER BY a.article_last_edit_time DESC";
					$result = $db->sql_query($sql);
					
					while($row = $db->sql_fetchrow($result))
					{
						$l_edit = '';
						$folder_img = ($row['subscribed'] == 2) ? 'topic_unread' : 'topic_read';
						if($row['article_last_edit_id'] && $row['article_last_edit_time'] != $row['article_time'])
						{
							$sql = "SELECT e.edit_id, u.user_id, u.username, u.user_colour
									FROM " . KB_EDITS_TABLE . " e, " . USERS_TABLE . " u
									WHERE e.edit_id = {$row['article_id']}
									AND u.user_id = e.edit_user_id";
							$result = $db->sql_query($sql);
							$edit_data = $db->sql_fetchrow($result);
							$db->sql_freeresult($result);
							
							$l_edit = sprintf($user->lang['KB_EDITED_BY'], '<a href="' . append_sid("{$phpbb_root_path}kb.$phpEx", "e={$row['article_id']}") . '">', '</a>', get_username_string('full', $edit_data['user_id'], $edit_data['username'], $edit_data['user_colour']), $user->format_date($row['article_last_edit_time'], false, true));
						}
						
						// Set article type
						$article_type = gen_article_type($row['article_type'], $row['article_title'], $types, $icons);
						
						// Send vars to template
						$template->assign_block_vars('articlerow', array(
							'ARTICLE_ID'				=> $row['article_id'],
							'ARTICLE_AUTHOR_FULL'		=> get_username_string('full', $row['article_user_id'], $row['article_user_name'], $row['article_user_color']),
							'FIRST_POST_TIME'			=> $user->format_date($row['article_time']),
				
							'ARTICLE_LAST_EDIT'			=> $l_edit,
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
							
							'U_VIEW_ARTICLE'			=> append_sid("{$phpbb_root_path}kb.$phpEx", "a=" . $row['article_id']),
						));
					}
					$db->sql_freeresult($result);
					
					$s_hidden_fields = build_hidden_fields(array(
						'action'		=> 'delete',
					));
					
					$template->assign_vars(array(
						'L_SUBSCRIBED_ARTICLES'		=> $user->lang['KB_SUBSCRIBED_ARTICLES'],
						'L_TITLE'					=> $user->lang['UCP_KB_SUBSCRIBED'],
						'L_KB_SUBSCRIBE_EXPLAIN'	=> $user->lang['UCP_KB_SUBSCRIBED_EXPLAIN'],
						'L_NO_SUBSCRIBED_ARTICLES'	=> $user->lang['KB_NO_SUBSCRIBED_ARTICLES'],
						'S_UCP_ACTION'				=> $this->u_action,
						'S_HIDDEN_FIELDS'			=> $s_hidden_fields,
						'L_UNSUBSCRIBE_MARKED'		=> $user->lang['KB_UNSUBSCRIBE_MARKED'],
					));
					break;
				}		
			break;
			
			// handles bookmarks
			case 'bookmarks':
				switch($action)
				{
					case 'add':
						$article_id = request_var('a', 0);
						if(!$article_id)
						{
							trigger_error('KB_NO_ARTICLE');
						}
						
						$sql = "SELECT article_id, subscribed
								FROM " . KB_TRACK_TABLE . "
								WHERE article_id = $article_id
								AND user_id = {$user->data['user_id']}";
						$result = $db->sql_query($sql);
						
						// The user has submitted the data, insert it into the db
						$sql_data = array(
							'article_id' 	=> $article_id,
							'user_id'	 	=> $user->data['user_id'],
							'subscribed'	=> 0,
							'bookmarked'	=> 1,
						);
						
						if($notify_data = $db->sql_fetchrow($result))
						{
							$sql_data['subscribed'] = $notify_data['subscribed'];
							$sql = 'UPDATE ' . KB_TRACK_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_data) . "
									WHERE article_id = $article_id
									AND user_id = {$user->data['user_id']}";
						}
						else
						{
							$sql = 'INSERT INTO ' . KB_TRACK_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data);
						}
						$db->sql_freeresult($result);
						$db->sql_query($sql);
						
						$url = $this->u_action;
						meta_refresh(3, $url);
						$message = $user->lang['KB_SUCCESS_BOOKMARK_' . strtoupper($action)] . '<br /><br />' . sprintf($user->lang['RETURN_UCP'], '<a href="' . $url . '">', '</a>');
						trigger_error($message);
					break;
						
					case 'delete':
						// Show confirm box then remove
						$article_id = request_var('a', array(0 => 0)); // No array keys here, we want it passed along just as it is
						if(empty($article_id))
						{
							trigger_error('KB_NO_ARTICLE');
						}
						
						if(confirm_box(true))
						{
							$article_id = array_keys($article_id);
							// Retain subscription info, then just delete
							$sql = "SELECT article_id, subscribed 
									FROM " . KB_TRACK_TABLE . "
									WHERE " . $db->sql_in_set('article_id', $article_id) . "
									AND user_id = {$user->data['user_id']}";
							$result = $db->sql_query($sql);
							$articles_count = 0;
							$delete_data = array();
							while($row = $db->sql_fetchrow($result))
							{
								$articles_count++;
								if($row['subscribed'] > 0)
								{
									$delete_data['update'][] = $row['article_id'];
								}
								else
								{
									$delete_data['delete'][] = $row['article_id'];
								}
							}
							$db->sql_freeresult($result);
							
							if(!$articles_count)
							{
								trigger_error('KB_NO_ARTICLES');
							}
							
							if(!empty($delete_data['update']))
							{
								$sql_data = array(
									'bookmarked'	=> 0,
								);
								
								$sql = 'UPDATE ' . KB_TRACK_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_data) . "
										WHERE " . $db->sql_in_set('article_id', $delete_data['update']) . "
										AND user_id = {$user->data['user_id']}";
								$db->sql_query($sql);
							}
							
							if(!empty($delete_data['delete']))
							{
								$sql = "DELETE FROM " . KB_TRACK_TABLE . "
										WHERE " . $db->sql_in_set('article_id', $delete_data['delete']) . "
										AND user_id = {$user->data['user_id']}";
								$db->sql_query($sql);
							}
							
							$url = $this->u_action;
							meta_refresh(3, $url);
							$message = $user->lang['KB_SUCCESS_BOOKMARK_' . strtoupper($action)] . '<br /><br />' . sprintf($user->lang['RETURN_UCP'], '<a href="' . $url . '">', '</a>');
							trigger_error($message);
						}
						else
						{
							$hidden_fields = build_hidden_fields(array(
								'action'	=> 'delete',
								'a'			=> $article_id,
							));
							confirm_box(false, 'KB_DELETE_BOOKMARK', $hidden_fields);
							redirect($this->u_action);
						}
					break;
						
					case '':
					default:
					$sql = "SELECT n.*, a.* 
							FROM " . KB_TRACK_TABLE . " n, " . KB_TABLE . " a
							WHERE n.bookmarked = 1
							AND a.article_status = " . STATUS_APPROVED . "
							AND n.article_id = a.article_id
							AND n.user_id = {$user->data['user_id']}
							ORDER BY a.article_last_edit_time DESC";
					$result = $db->sql_query($sql);
					while($row = $db->sql_fetchrow($result))
					{
						$l_edit = '';
						$folder_img = ($row['article_last_edit_time'] > $user->data['user_lastvisit']) ? 'topic_unread' : 'topic_read';
						if($row['article_last_edit_id'] && $row['article_last_edit_time'] != $row['article_time'])
						{
							$sql = "SELECT e.edit_id, u.user_id, u.username, u.user_colour
									FROM " . KB_EDITS_TABLE . " e, " . USERS_TABLE . " u
									WHERE e.edit_id = {$row['article_id']}
									AND u.user_id = e.edit_user_id";
							$result = $db->sql_query($sql);
							$edit_data = $db->sql_fetchrow($result);
							$db->sql_freeresult($result);
							
							$l_edit = sprintf($user->lang['KB_EDITED_BY'], '<a href="' . append_sid("{$phpbb_root_path}kb.$phpEx", "e={$row['article_id']}") . '">', '</a>', get_username_string('full', $edit_data['user_id'], $edit_data['username'], $edit_data['user_colour']), $user->format_date($row['article_last_edit_time'], false, true));
						}
						
						// Get article types
						$article_type = gen_article_type($row['article_type'], $row['article_title'], $types, $icons);
						
						// Send vars to template
						$template->assign_block_vars('articlerow', array(
							'ARTICLE_ID'				=> $row['article_id'],
							'ARTICLE_AUTHOR_FULL'		=> get_username_string('full', $row['article_user_id'], $row['article_user_name'], $row['article_user_color']),
							'FIRST_POST_TIME'			=> $user->format_date($row['article_time']),
				
							'ARTICLE_LAST_EDIT'			=> $l_edit,
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
							
							'U_VIEW_ARTICLE'			=> append_sid("{$phpbb_root_path}kb.$phpEx", "a=" . $row['article_id']),
						));
					}
					$db->sql_freeresult($result);
					
					$s_hidden_fields = build_hidden_fields(array(
						'action'		=> 'delete',
					));
					
					$template->assign_vars(array(
						'L_BOOKMARKED_ARTICLES'		=> $user->lang['KB_BOOKMARKED_ARTICLES'],
						'L_TITLE'					=> $user->lang['UCP_KB_BOOKMARKS'],
						'L_KB_BOOKMARKS_EXPLAIN'		=> $user->lang['UCP_KB_BOOKMARKS_EXPLAIN'],
						'L_NO_BOOKMARKED_ARTICLES'	=> $user->lang['KB_NO_BOOKMARKED_ARTICLES'],
						'S_UCP_ACTION'				=> $this->u_action,
						'S_HIDDEN_FIELDS'			=> $s_hidden_fields,
						'L_UNBOOKMARK_MARKED'		=> $user->lang['KB_UNBOOKMARK_MARKED'],
					));
					break;
				}
			break;
			
			case 'articles':
				// Handle all article stuff in external file
				include($phpbb_root_path . 'includes/kb.' . $phpEx);
				$kb = new knowledge_base(0, false);
				$module_action = request_var('ma', '');
				
				switch($module_action)
				{
					case 'comment':
						$page_title = $kb->comment_posting('ucp');
						$this->tpl_name = 'kb/posting_body';
						$this->page_title = $page_title;
					break;
					
					case 'view':
						$page_title = $kb->generate_article_page('ucp');
						$this->tpl_name = 'kb/view_article';
						$this->page_title = $page_title;
					break;
					
					case '':
					default:
						$sql = "SELECT a.* 
								FROM " . KB_TABLE . " a
								WHERE a.article_status != " . STATUS_APPROVED . "
								AND a.article_user_id = " . $user->data['user_id'] . "
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
							
							// Get article types
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
								
								'U_VIEW_ARTICLE'			=> append_sid("{$phpbb_root_path}ucp.$phpEx", "i=kb&amp;mode=articles&amp;ma=view&amp;a=" . $row['article_id']), // Mode articles here for style continuity
							));
						}
						$db->sql_freeresult($result);
						
						$template->assign_vars(array(
							'L_TITLE'					=> $user->lang['UCP_KB_ARTICLES'],
							'L_KB_ARTICLES_EXPLAIN'		=> $user->lang['UCP_KB_ARTICLES_EXPLAIN'],
							'L_NO_QUEUED_ARTICLES'		=> $user->lang['KB_NO_QUEUED_ARTICLES'],
							'L_QUEUED_ARTICLES'			=> $user->lang['KB_QUEUED_ARTICLES'],
							'L_STATUS'					=> $user->lang['ARTICLE_STATUS'],
						));
						$this->tpl_name = 'kb/ucp_kb_articles';
						$this->page_title = 'UCP_KB_ARTICLES';
					break;
				}
			break;
		}
		
		if($mode != 'articles' && $mode != '')
		{
			$this->tpl_name = 'kb/ucp_kb_' . $mode;
			$this->page_title = 'UCP_KB_' . strtoupper($mode);
		}
	}
}

?>