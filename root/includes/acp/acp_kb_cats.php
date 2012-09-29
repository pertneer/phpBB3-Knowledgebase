<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: acp_kb_cats.php 418 2010-01-13 14:12:50Z softphp $
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
* @package acp
*/
class acp_kb_cats
{
	var $u_action;
	var $parent_id = 0;

	function main($id, $mode)
	{
		global $db, $user, $auth, $template, $cache;
		global $config, $phpbb_admin_path, $phpbb_root_path, $phpEx, $table_prefix;
		
		include($phpbb_root_path . 'includes/constants_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/functions_kb.' . $phpEx);

		$user->add_lang('acp/forums');
		$user->add_lang('mods/kb');
		$this->tpl_name = 'acp_kb_cat';
		$this->page_title = 'ACP_MANAGE_CATS';

		$form_key = 'acp_cat';
		add_form_key($form_key);

		$action		= request_var('action', '');
		$update		= (isset($_POST['update'])) ? true : false;
		$cat_id		= request_var('c', 0);

		$this->parent_id	= request_var('parent_id', 0);
		$cat_data = $errors = array();
		if ($update && !check_form_key($form_key))
		{
			$update = false;
			$errors[] = $user->lang['FORM_INVALID'];
		}

		// Check additional permissions
		switch ($action)
		{
			case 'progress_bar':
				$start = request_var('start', 0);
				$total = request_var('total', 0);

				$this->display_progress_bar($start, $total);
				exit;
			break;
		}

		// Major routines
		if ($update)
		{
			switch ($action)
			{
				case 'delete':
					$action_subcats		= request_var('action_subcats', '');
					$subcats_to_id		= request_var('subcats_to_id', 0);
					$action_articles	= request_var('action_articles', '');
					$articles_to_id		= request_var('articles_to_id', 0);

					$errors = $this->delete_cat($cat_id, $action_articles, $action_subcats, $articles_to_id, $subcats_to_id);

					if (sizeof($errors))
					{
						break;
					}

					trigger_error($user->lang['CAT_DELETED'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id));
				break;
			
				case 'edit':
					$cat_data = array(
						'cat_id'		=>	(int) $cat_id,
					);

				// No break here

				case 'add':

					$cat_data += array(
						'parent_id'				=> request_var('cat_parent_id', $this->parent_id),
						'cat_name'				=> utf8_normalize_nfc(request_var('cat_name', '', true)),
						'cat_desc'				=> utf8_normalize_nfc(request_var('cat_desc', '', true)),
						'cat_desc_uid'			=> '',
						'cat_desc_options'		=> 7,
						'cat_desc_bitfield'		=> '',
						'cat_image'				=> request_var('cat_image', ''),
						
					);
					
					if($action == 'add')
					{
						$cat_data += array(
							'latest_ids'	=> serialize(array()),
						);
					}

					// Get data for forum description if specified
					if ($cat_data['cat_desc'] != '')
					{
						generate_text_for_storage($cat_data['cat_desc'], $cat_data['cat_desc_uid'], $cat_data['cat_desc_bitfield'], $cat_data['cat_desc_options'], true, true, true);
					}

					$errors = $this->update_cat_data($cat_data);

					if (!sizeof($errors))
					{
						// Copy permissions
						$cat_perm_from = request_var('cat_perm_from', 0);
						
						if (!empty($cat_perm_from) && $cat_perm_from != $cat_data['cat_id'] &&
							(($action != 'edit') || empty($cat_id) || ($auth->acl_get('a_fauth') && $auth->acl_get('a_authusers') && $auth->acl_get('a_authgroups') && $auth->acl_get('a_mauth'))))
						{
							$cat_perm_from = request_var('cat_perm_from', 0);
						
							// if we edit a forum delete current permissions first
							if ($action == 'edit')
							{
								// KB BEGIN
								$sql = 'DELETE FROM ' . KB_ACL_USERS_TABLE . '
									WHERE forum_id = ' . $cat_data['cat_id'];
								$db->sql_query($sql);

								$sql = 'DELETE FROM ' . KB_ACL_GROUPS_TABLE . '
									WHERE forum_id = ' . $cat_data['cat_id'];
								$db->sql_query($sql);
							}

							// From the mysql documentation:
							// Prior to MySQL 4.0.14, the target table of the INSERT statement cannot appear in the FROM clause of the SELECT part of the query. This limitation is lifted in 4.0.14.
							// Due to this we stay on the safe side if we do the insertion "the manual way"

							// Copy permisisons from/to the acl users table (only forum_id gets changed)
							$sql = 'SELECT user_id, auth_option_id, auth_role_id, auth_setting
								FROM ' . KB_ACL_USERS_TABLE . '
								WHERE forum_id = ' . $cat_perm_from;
							$result = $db->sql_query($sql);

							$users_sql_ary = array();
							while ($row = $db->sql_fetchrow($result))
							{
								$users_sql_ary[] = array(
									'user_id'			=> (int) $row['user_id'],
									'forum_id'			=> (int) $cat_data['cat_id'],
									'auth_option_id'	=> (int) $row['auth_option_id'],
									'auth_role_id'		=> (int) $row['auth_role_id'],
									'auth_setting'		=> (int) $row['auth_setting'],
								);
							}
							$db->sql_freeresult($result);

							// Copy permisisons from/to the acl groups table (only forum_id gets changed)
							$sql = 'SELECT group_id, auth_option_id, auth_role_id, auth_setting
								FROM ' . KB_ACL_GROUPS_TABLE . '
								WHERE forum_id = ' . $cat_perm_from;
							$result = $db->sql_query($sql);

							$groups_sql_ary = array();
							while ($row = $db->sql_fetchrow($result))
							{
								$groups_sql_ary[] = array(
									'group_id'			=> (int) $row['group_id'],
									'forum_id'			=> (int) $cat_data['cat_id'],
									'auth_option_id'	=> (int) $row['auth_option_id'],
									'auth_role_id'		=> (int) $row['auth_role_id'],
									'auth_setting'		=> (int) $row['auth_setting'],
								);
							}
							$db->sql_freeresult($result);

							// Now insert the data
							$db->sql_multi_insert(KB_ACL_USERS_TABLE, $users_sql_ary);
							$db->sql_multi_insert(KB_ACL_GROUPS_TABLE, $groups_sql_ary);
						}
						
						$auth->acl_clear_prefetch();
						
						$message = ($action == 'add') ? $user->lang['CAT_CREATED'] : $user->lang['CAT_UPDATED'];

						trigger_error($message . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id));
					}

				break;
			}
		}

		switch ($action)
		{
			case 'move_up':
			case 'move_down':

				if (!$cat_id)
				{
					trigger_error($user->lang['NO_CAT'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}

				$sql = 'SELECT *
					FROM ' . KB_CATS_TABLE . "
					WHERE cat_id = $cat_id";
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if (!$row)
				{
					trigger_error($user->lang['NO_CAT'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}

				$move_cat_name = $this->move_cat_by($row, $action, 1);

				if ($move_cat_name !== false)
				{
					add_log('admin', 'LOG_CAT_' . strtoupper($action), $row['cat_name'], $move_cat_name);
					$cache->destroy('sql', KB_CATS_TABLE);
				}

			break;

			case 'add':
			case 'edit':
				// Show form to create/modify a forum
				if ($action == 'edit')
				{
					$this->page_title = 'EDIT_CAT';
					$row = $this->get_cat_info($cat_id);

					if (!$update)
					{
						$cat_data = $row;
					}
					else
					{
						$cat_data['left_id'] = $row['left_id'];
						$cat_data['right_id'] = $row['right_id'];
					}

					// Make sure no direct child forums are able to be selected as parents.
					$exclude_cats = array();
					foreach (get_cat_branch($cat_id, 'children') as $row)
					{
						$exclude_cats[] = $row['cat_id'];
					}

					$parents_list = make_cat_select($cat_data['parent_id'], $exclude_cats, true);
				}
				else
				{
					$this->page_title = 'CREATE_CAT';

					$cat_id = $this->parent_id;
					$parents_list = make_cat_select($this->parent_id, false, true);

					// Fill cat data with default values
					if (!$update)
					{
						$cat_data = array(
							'parent_id'				=> $this->parent_id,
							'cat_name'				=> utf8_normalize_nfc(request_var('cat_name', '', true)),
							'cat_desc'				=> '',
							'cat_image'				=> '',
							'latest_ids'			=> serialize(array()),
						);
					}
				}

				$cat_desc_data = array(
					'text'			=> $cat_data['cat_desc'],
					'allow_bbcode'	=> true,
					'allow_smilies'	=> true,
					'allow_urls'	=> true
				);

				// Parse desciption if specified
				if ($cat_data['cat_desc'])
				{
					if (!isset($cat_data['cat_desc_uid']))
					{
						// Before we are able to display the preview and plane text, we need to parse our request_var()'d value...
						$cat_data['cat_desc_uid'] = '';
						$cat_data['cat_desc_bitfield'] = '';
						$cat_data['cat_desc_options'] = 0;

						generate_text_for_storage($cat_data['cat_desc'], $cat_data['cat_desc_uid'], $cat_data['cat_desc_bitfield'], $cat_data['cat_desc_options'], true, true, true);
					}

					// decode...
					$cat_desc_data = generate_text_for_edit($cat_data['cat_desc'], $cat_data['cat_desc_uid'], $cat_data['cat_desc_options']);
				}
				
				$sql = 'SELECT cat_id
					FROM ' . KB_CATS_TABLE . "
					WHERE cat_id <> $cat_id";
				$result = $db->sql_query($sql);

				if ($db->sql_fetchrow($result))
				{
					$template->assign_vars(array(
						'S_MOVE_FORUM_OPTIONS'		=> make_forum_select($cat_data['parent_id'], $cat_id, false, true, false))
					);
				}
				$db->sql_freeresult($result);

				// Subforum move options
				if ($action == 'edit')
				{
					$subforums_id = array();
					$subforums = get_cat_branch($cat_id, 'children');

					foreach ($subforums as $row)
					{
						$subforums_id[] = $row['cat_id'];
					}

					$cat_list = make_cat_select($cat_data['parent_id'], $subforums_id, true);

					$sql = 'SELECT cat_id
					FROM ' . KB_CATS_TABLE . "
						WHERE cat_id <> $cat_id";
					$result = $db->sql_query($sql);

					if ($db->sql_fetchrow($result))
					{
						$template->assign_vars(array(
							'S_MOVE_CAT_OPTIONS'		=> make_cat_select($cat_data['parent_id'], $subforums_id, true))
						);
					}
					$db->sql_freeresult($result);

					$template->assign_vars(array(
						'S_HAS_SUBFORUMS'		=> ($cat_data['right_id'] - $cat_data['left_id'] > 1) ? true : false,
						'S_CAT_LIST'			=> $cat_list)
					);
				}

				$template->assign_vars(array(
					'S_EDIT_CAT'		=> true,
					'S_ERROR'			=> (sizeof($errors)) ? true : false,
					'S_PARENT_ID'		=> $this->parent_id,
					'S_CAT_PARENT_ID'	=> $cat_data['parent_id'],
					'S_ADD_ACTION'		=> ($action == 'add') ? true : false,

					'U_BACK'		=> $this->u_action . '&amp;parent_id=' . $this->parent_id,
					'U_EDIT_ACTION'	=> $this->u_action . "&amp;parent_id={$this->parent_id}&amp;action=$action&amp;c=$cat_id",
					
					'L_TITLE'					=> $user->lang[$this->page_title],
					'ERROR_MSG'					=> (sizeof($errors)) ? implode('<br />', $errors) : '',

					'CAT_NAME'					=> $cat_data['cat_name'],
					
					'CAT_IMAGE'					=> $cat_data['cat_image'],
					'CAT_IMAGE_SRC'				=> ($cat_data['cat_image']) ? $phpbb_root_path . $cat_data['cat_image'] : '',

					'CAT_DESC'					=> $cat_desc_data['text'],

					'S_PARENT_OPTIONS'			=> $parents_list,
					'S_CAT_OPTIONS'				=> make_cat_select(false, false, true),
					'S_CAN_COPY_PERMISSIONS'	=> ($action != 'edit' || empty($cat_id) || ($auth->acl_get('a_fauth') && $auth->acl_get('a_authusers') && $auth->acl_get('a_authgroups') && $auth->acl_get('a_mauth'))) ? true : false,
					'S_CAT_OPTIONS'				=> make_cat_select(($action == 'add') ? $cat_data['parent_id'] : false, ($action == 'edit') ? $cat_data['cat_id'] : false, true),
				));

				return;

			break;

			case 'delete':
				if (!$cat_id)
				{
					trigger_error($user->lang['NO_CAT'] . adm_back_link($this->u_action . '&amp;parent_id=' . $this->parent_id), E_USER_WARNING);
				}

				$cat_data = $this->get_cat_info($cat_id);

				$subforums_id = array();
				$subforums = get_cat_branch($cat_id, 'children');

				foreach ($subforums as $row)
				{
					$subforums_id[] = $row['cat_id'];
				}

				$cat_list = make_cat_select($cat_data['parent_id'], $subforums_id, true);

				$sql = 'SELECT cat_id
					FROM ' . KB_CATS_TABLE . "
					WHERE cat_id <> $cat_id";
				$result = $db->sql_query($sql);

				if ($db->sql_fetchrow($result))
				{
					$template->assign_vars(array(
						'S_MOVE_CAT_OPTIONS'		=> make_cat_select($cat_data['parent_id'], $subforums_id, true))
					);
				}
				$db->sql_freeresult($result);

				$parent_id = ($this->parent_id == $cat_id) ? 0 : $this->parent_id;

				$template->assign_vars(array(
					'S_DELETE_CAT'			=> true,
					'S_CAT_ARTICLES'		=> $cat_data['cat_articles'],
					'U_ACTION'				=> $this->u_action . "&amp;parent_id={$parent_id}&amp;action=delete&amp;c=$cat_id",
					'U_BACK'				=> $this->u_action . '&amp;parent_id=' . $this->parent_id,

					'CAT_NAME'				=> $cat_data['cat_name'],
					'S_HAS_SUBFORUMS'		=> ($cat_data['right_id'] - $cat_data['left_id'] > 1) ? true : false,
					'S_CAT_LIST'			=> $cat_list,
					'S_ERROR'				=> (sizeof($errors)) ? true : false,
					'ERROR_MSG'				=> (sizeof($errors)) ? implode('<br />', $errors) : '')
				);

				return;
			break;
		}

		// Default management page
		if (!$this->parent_id)
		{
			$navigation = $user->lang['CAT_INDEX'];
		}
		else
		{
			$navigation = '<a href="' . $this->u_action . '">' . $user->lang['CAT_INDEX'] . '</a>';

			$cat_nav = get_cat_branch($this->parent_id, 'parents', 'descending');
			foreach ($cat_nav as $row)
			{
				if ($row['cat_id'] == $this->parent_id)
				{
					$navigation .= ' -&gt; ' . $row['cat_name'];
				}
				else
				{
					$navigation .= ' -&gt; <a href="' . $this->u_action . '&amp;parent_id=' . $row['cat_id'] . '">' . $row['cat_name'] . '</a>';
				}
			}
		}

		// Jumpbox
		$cat_box = make_cat_select($this->parent_id, false, true); //make_forum_select($this->parent_id);

		$sql = 'SELECT *
			FROM ' . KB_CATS_TABLE . "
			WHERE parent_id = $this->parent_id
			ORDER BY left_id";
		$result = $db->sql_query($sql);

		if ($row = $db->sql_fetchrow($result))
		{
			do
			{				
				$folder_image = ($row['left_id'] + 1 != $row['right_id']) ? '<img src="images/icon_subfolder.gif" alt="' . $user->lang['SUBFORUM'] . '" />' : '<img src="images/icon_folder.gif" alt="' . $user->lang['FOLDER'] . '" />';
						
				$url = $this->u_action . "&amp;parent_id=$this->parent_id&amp;c={$row['cat_id']}";

				$forum_title = $row['cat_name'];

				$template->assign_block_vars('cats', array(
					'FOLDER_IMAGE'		=> $folder_image,
					'CAT_IMAGE'			=> ($row['cat_image']) ? '<img src="' . $phpbb_root_path . $row['cat_image'] . '" alt="" />' : '',
					'CAT_IMAGE_SRC'		=> ($row['cat_image']) ? $phpbb_root_path . $row['cat_image'] : '',
					'CAT_NAME'			=> $row['cat_name'],
					'CAT_DESCRIPTION'	=> generate_text_for_display($row['cat_desc'], $row['cat_desc_uid'], $row['cat_desc_bitfield'], $row['cat_desc_options']),
					'CAT_ARTICLES'		=> $row['cat_articles'],

					'U_CAT'			=> $this->u_action . '&amp;parent_id=' . $row['cat_id'],
					'U_MOVE_UP'			=> $url . '&amp;action=move_up',
					'U_MOVE_DOWN'		=> $url . '&amp;action=move_down',
					'U_EDIT'			=> $url . '&amp;action=edit',
					'U_DELETE'			=> $url . '&amp;action=delete')
				);
			}
			while ($row = $db->sql_fetchrow($result));
		}
		else if ($this->parent_id)
		{
			$row = $this->get_cat_info($this->parent_id);

			$url = $this->u_action . '&amp;parent_id=' . $this->parent_id . '&amp;c=' . $row['cat_id'];

			$template->assign_vars(array(
				'S_NO_CATS'		=> true,

				'U_EDIT'			=> $url . '&amp;action=edit',
				'U_DELETE'			=> $url . '&amp;action=delete')
			);
		}
		$db->sql_freeresult($result);

		$template->assign_vars(array(
			'ERROR_MSG'		=> (sizeof($errors)) ? implode('<br />', $errors) : '',
			'NAVIGATION'	=> $navigation,
			'CAT_BOX'		=> $cat_box,
			'U_SEL_ACTION'	=> $this->u_action,
			'U_ACTION'		=> $this->u_action . '&amp;parent_id=' . $this->parent_id,

			'U_PROGRESS_BAR'	=> $this->u_action . '&amp;action=progress_bar',
			'UA_PROGRESS_BAR'	=> addslashes($this->u_action . '&amp;action=progress_bar'),
		));
	}

	/**
	* Get forum details
	*/
	function get_cat_info($cat_id)
	{
		global $db;

		$sql = 'SELECT *
			FROM ' . KB_CATS_TABLE . "
			WHERE cat_id = $cat_id";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$row)
		{
			trigger_error("Cat #$cat_id does not exist", E_USER_ERROR);
		}

		return $row;
	}

	/**
	* Update forum data
	*/
	function update_cat_data(&$cat_data)
	{
		global $db, $user, $cache, $config;

		$errors = array();

		if (!$cat_data['cat_name'])
		{
			$errors[] = $user->lang['CAT_NAME_EMPTY'];
		}

		if (utf8_strlen($cat_data['cat_desc']) > 4000)
		{
			$errors[] = $user->lang['CAT_DESC_TOO_LONG'];
		}

		// Unset data that are not database fields
		$cat_data_sql = $cat_data;

		// What are we going to do tonight Brain? The same thing we do everynight,
		// try to take over the world ... or decide whether to continue update
		// and if so, whether it's a new forum/cat/link or an existing one
		if (sizeof($errors))
		{
			return $errors;
		}

		if (!isset($cat_data_sql['cat_id']))
		{
			if ($cat_data_sql['parent_id'])
			{
				$sql = 'SELECT left_id, right_id
					FROM ' . KB_CATS_TABLE . '
					WHERE cat_id = ' . $cat_data_sql['parent_id'];
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if (!$row)
				{
					trigger_error($user->lang['PARENT_NOT_EXIST'] . adm_back_link($this->u_action . '&amp;' . $this->parent_id), E_USER_WARNING);
				}

				$sql = 'UPDATE ' . KB_CATS_TABLE . '
					SET left_id = left_id + 2, right_id = right_id + 2
					WHERE left_id > ' . $row['right_id'];
				$db->sql_query($sql);

				$sql = 'UPDATE ' . KB_CATS_TABLE . '
					SET right_id = right_id + 2
					WHERE ' . $row['left_id'] . ' BETWEEN left_id AND right_id';
				$db->sql_query($sql);

				$cat_data_sql['left_id'] = $row['right_id'];
				$cat_data_sql['right_id'] = $row['right_id'] + 1;
			}
			else
			{
				$sql = 'SELECT MAX(right_id) AS right_id
					FROM ' . KB_CATS_TABLE;
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				$cat_data_sql['left_id'] = $row['right_id'] + 1;
				$cat_data_sql['right_id'] = $row['right_id'] + 2;
			}

			$sql = 'INSERT INTO ' . KB_CATS_TABLE . ' ' . $db->sql_build_array('INSERT', $cat_data_sql);
			$db->sql_query($sql);

			$cat_data['cat_id'] = $db->sql_nextid();
			
			set_config('kb_total_cats', $config['kb_total_cats'] + 1);
			$cache->destroy('config');

			add_log('admin', 'LOG_CAT_ADD', $cat_data['cat_name']);
		}
		else
		{
			$row = $this->get_cat_info($cat_data_sql['cat_id']);

			// Setting the forum id to the forum id is not really received well by some dbs. ;)
			$cat_id = $cat_data_sql['cat_id'];
			unset($cat_data_sql['cat_id']);

			$sql = 'UPDATE ' . KB_CATS_TABLE . '
				SET ' . $db->sql_build_array('UPDATE', $cat_data_sql) . '
				WHERE cat_id = ' . $cat_id;
			$db->sql_query($sql);

			// Add it back
			$cat_data['cat_id'] = $cat_id;

			add_log('admin', 'LOG_CAT_EDIT', $cat_data['cat_name']);
		}

		set_config('kb_last_updated', time(), true);
		return $errors;
	}

	/**
	* Move forum position by $steps up/down
	*/
	function move_cat_by($cat_row, $action = 'move_up', $steps = 1)
	{
		global $db;

		/**
		* Fetch all the siblings between the module's current spot
		* and where we want to move it to. If there are less than $steps
		* siblings between the current spot and the target then the
		* module will move as far as possible
		*/
		$sql = 'SELECT cat_id, cat_name, left_id, right_id
			FROM ' . KB_CATS_TABLE . "
			WHERE parent_id = {$cat_row['parent_id']}
				AND " . (($action == 'move_up') ? "right_id < {$cat_row['right_id']} ORDER BY right_id DESC" : "left_id > {$cat_row['left_id']} ORDER BY left_id ASC");
		$result = $db->sql_query_limit($sql, $steps);

		$target = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$target = $row;
		}
		$db->sql_freeresult($result);

		if (!sizeof($target))
		{
			// The forum is already on top or bottom
			return false;
		}

		/**
		* $left_id and $right_id define the scope of the nodes that are affected by the move.
		* $diff_up and $diff_down are the values to substract or add to each node's left_id
		* and right_id in order to move them up or down.
		* $move_up_left and $move_up_right define the scope of the nodes that are moving
		* up. Other nodes in the scope of ($left_id, $right_id) are considered to move down.
		*/
		if ($action == 'move_up')
		{
			$left_id = $target['left_id'];
			$right_id = $cat_row['right_id'];

			$diff_up = $cat_row['left_id'] - $target['left_id'];
			$diff_down = $cat_row['right_id'] + 1 - $cat_row['left_id'];

			$move_up_left = $cat_row['left_id'];
			$move_up_right = $cat_row['right_id'];
		}
		else
		{
			$left_id = $cat_row['left_id'];
			$right_id = $target['right_id'];

			$diff_up = $cat_row['right_id'] + 1 - $cat_row['left_id'];
			$diff_down = $target['right_id'] - $cat_row['right_id'];

			$move_up_left = $cat_row['right_id'] + 1;
			$move_up_right = $target['right_id'];
		}

		// Now do the dirty job
		$sql = 'UPDATE ' . KB_CATS_TABLE . "
			SET left_id = left_id + CASE
				WHEN left_id BETWEEN {$move_up_left} AND {$move_up_right} THEN -{$diff_up}
				ELSE {$diff_down}
			END,
			right_id = right_id + CASE
				WHEN right_id BETWEEN {$move_up_left} AND {$move_up_right} THEN -{$diff_up}
				ELSE {$diff_down}
			END
			WHERE
				left_id BETWEEN {$left_id} AND {$right_id}
				AND right_id BETWEEN {$left_id} AND {$right_id}";
		$db->sql_query($sql);

		return $target['cat_name'];
	}
	
	/**
	* Remove complete cat
	*/
	function delete_cat($cat_id, $action_articles = 'delete', $action_subcats = 'delete', $articles_to_id = 0, $subcats_to_id = 0)
	{
		global $db, $user, $cache, $config;
		
		$cat_data = $this->get_cat_info($cat_id);

		$errors = array();
		$log_action_articles = $log_action_cats = $articles_to_name = $subcats_to_name = '';
		$cat_ids = array($cat_id);

		if ($action_articles == 'delete')
		{
			$log_action_articles = 'DELETE_ARTICLES';
			$errors = array_merge($errors, $this->delete_cat_content($cat_id));
		}
		else if ($action_articles == 'move')
		{
			if (!$articles_to_id)
			{
				$errors[] = $user->lang['NO_DESTINATION_CAT'];
			}
			else
			{
				$log_action_articles = 'MOVE_ARTICLES';

				$sql = 'SELECT cat_name
						FROM ' . KB_CATS_TABLE . '
						WHERE cat_id = ' . $articles_to_id;
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if (!$row)
				{
					$errors[] = $user->lang['NO_CAT'];
				}
				else
				{
					$articles_to_name = $row['cat_name'];
					$errors = array_merge($errors, $this->move_cat_content($cat_id, $articles_to_id));
				}
			}
		}

		if (sizeof($errors))
		{
			return $errors;
		}

		if ($action_subcats == 'delete')
		{
			$log_action_cats = 'DELETE_CATS';
			
			$rows = get_cat_branch($cat_id, 'children', 'descending', false);
			foreach ($rows as $row)
			{
				$cat_ids[] = $row['cat_id'];
				$errors = array_merge($errors, $this->delete_cat_content($row['cat_id']));
			}

			if (sizeof($errors))
			{
				return $errors;
			}

			$diff = sizeof($cat_ids) * 2;

			$sql = 'DELETE FROM ' . KB_CATS_TABLE . '
					WHERE ' . $db->sql_in_set('cat_id', $cat_ids);
			$db->sql_query($sql);
			
			// Delete auth stuff aswell
			$sql = 'DELETE FROM ' . KB_ACL_GROUPS_TABLE . "
					WHERE " . $db->sql_in_set('forum_id', $cat_ids);
			$db->sql_query($sql);

			$sql = 'DELETE FROM ' . KB_ACL_USERS_TABLE . "
					WHERE " . $db->sql_in_set('forum_id', $cat_ids);
			$db->sql_query($sql);
			
			set_config('kb_total_cats', $config['kb_total_cats'] - count($cat_ids));
			$cache->destroy('config');
		}
		else if ($action_subcats == 'move')
		{
			if (!$subcats_to_id)
			{
				$errors[] = $user->lang['NO_DESTINATION_CAT'];
			}
			else
			{
				$log_action_forums = 'MOVE_CATS';

				$sql = 'SELECT cat_name
						FROM ' . KB_CATS_TABLE . '
						WHERE cat_id = ' . $subcats_to_id;
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if (!$row)
				{
					$errors[] = $user->lang['NO_CAT'];
				}
				else
				{
					$subcats_to_name = $row['cat_name'];

					$sql = 'SELECT cat_id
							FROM ' . KB_CATS_TABLE . "
							WHERE parent_id = $cat_id";
					$result = $db->sql_query($sql);
					
					while ($row = $db->sql_fetchrow($result))
					{
						$this->move_cat($row['cat_id'], $subcats_to_id);
					}
					$db->sql_freeresult($result);

					// Grab new forum data for correct tree updating later
					$cat_data = $this->get_cat_info($cat_id);

					$sql = 'UPDATE ' . KB_CATS_TABLE . "
							SET parent_id = $subcats_to_id
							WHERE parent_id = $cat_id";
					$db->sql_query($sql);

					$diff = 2;
					$sql = 'DELETE FROM ' . KB_CATS_TABLE . "
							WHERE cat_id = $cat_id";
					$db->sql_query($sql);
					
					set_config('kb_total_cats', $config['kb_total_cats'] - 1);
					$cache->destroy('config');
					
					// Auth entries
					$sql = 'DELETE FROM ' . KB_ACL_GROUPS_TABLE . '
							WHERE forum_id = ' . $cat_id;
					$db->sql_query($sql);
					
					$sql = 'DELETE FROM ' . KB_ACL_USERS_TABLE . '
							WHERE forum_id = ' . $cat_id;
					$db->sql_query($sql);
				}
			}

			if (sizeof($errors))
			{
				return $errors;
			}
		}
		else
		{
			$diff = 2;
			$sql = 'DELETE FROM ' . KB_CATS_TABLE . "
					WHERE cat_id = $cat_id";
			$db->sql_query($sql);
			
			set_config('kb_total_cats', $config['kb_total_cats'] - 1);
			$cache->destroy('config');
			
			$sql = 'DELETE FROM ' . KB_ACL_GROUPS_TABLE . '
					WHERE forum_id = ' . $cat_id;
			$db->sql_query($sql);

			$sql = 'DELETE FROM ' . KB_ACL_USERS_TABLE . '
					WHERE forum_id = ' . $cat_id;
			$db->sql_query($sql);
		}

		// Resync tree
		$sql = 'UPDATE ' . KB_CATS_TABLE . "
				SET right_id = right_id - $diff
				WHERE left_id < {$cat_data['right_id']} AND right_id > {$cat_data['right_id']}";
		$db->sql_query($sql);

		$sql = 'UPDATE ' . KB_CATS_TABLE . "
				SET left_id = left_id - $diff, right_id = right_id - $diff
				WHERE left_id > {$cat_data['right_id']}";
		$db->sql_query($sql);

		$log_action = implode('_', array($log_action_articles, $log_action_cats));

		switch ($log_action)
		{
			case 'MOVE_ARTICLES_MOVE_CATS':
				add_log('admin', 'LOG_CAT_' . $log_action, $articles_to_name, $subcats_to_name, $cat_data['cat_name']);
			break;

			case 'DELETE_ARTICLES_MOVE_CATS':
				add_log('admin', 'LOG_CAT_' . $log_action, $subcats_to_name, $cat_data['cat_name']);
			break;

			case 'DELETE_ARTICLES_DELETE_CATS':
				add_log('admin', 'LOG_CAT_' . $log_action, $cat_data['cat_name']);
			break;

			case 'MOVE_ARTICLES_DELETE_CATS':
				add_log('admin', 'LOG_CAT_' . $log_action, $subcats_to_name, $cat_data['cat_name']);
			break;

			default:
				add_log('admin', 'LOG_CAT_DELETE_ARTICLES_DELETE_CATS', $cat_data['cat_name']);
			break;
		}

		return $errors;
	}
	
	/**
	* Move cat content
	* Luckily this should be fairly easy
	*/
	function move_cat_content($from_id, $to_id)
	{
		global $db, $user;
		
		$errors = $article_ids = array();
		$article_count = 0;
		
		// Retrieve all articles in category
		$sql = 'SELECT article_id
				FROM ' . KB_TABLE . ' 
				WHERE cat_id = ' . $from_id;
		$result = $db->sql_query($sql);
		while($row = $db->sql_fetchrow($result))
		{
			$article_ids[] = $row['article_id'];
		}
		$article_count = count($article_ids);
		$db->sql_freeresult($result);
		
		// Update article table
		$sql = 'UPDATE ' . KB_TABLE . '
				SET cat_id = ' . $to_id . '
				WHERE ' . $db->sql_in_set('article_id', $article_ids);
		$db->sql_query($sql);
		
		// Update count
		$sql = 'UPDATE ' . KB_CATS_TABLE . "
				SET cat_articles = cat_articles + $article_count
				WHERE cat_id = " . (int) $to_id;
		$db->sql_query($sql);
		
		return $errors;
	}
	
	/**
	* Deletes all articles in a category
	*/
	function delete_cat_content($cat_id)
	{
		global $db, $user, $config, $cache;
		
		$errors = $article_ids = array();
		
		// Retrieve all articles in category
		$sql = 'SELECT article_id
				FROM ' . KB_TABLE . ' 
				WHERE cat_id = ' . $cat_id;
		$result = $db->sql_query($sql);
		while($row = $db->sql_fetchrow($result))
		{
			$article_ids[] = $row['article_id'];
		}
		$db->sql_freeresult($result);
		
		if(sizeof($article_ids))
		{
			// Delete them en masse
			// Delete from rate table
			$sql = 'DELETE FROM ' . KB_RATE_TABLE . "
					WHERE " . $db->sql_in_set('article_id', $article_ids);
			$db->sql_query($sql);
			
			// Delete from comments table - no need to store these
			$comment_ids = array();
			$sql = 'SELECT comment_id
					FROM ' . KB_COMMENTS_TABLE . "
					WHERE " . $db->sql_in_set('article_id', $article_ids);
			$result = $db->sql_query($sql);
			while($row = $db->sql_fetchrow($result))
			{
				// Don't loop this: comment_delete($row['comment_id'], $article_id, false);
				$comment_ids[] = $row['comment_id'];
			}
			$db->sql_freeresult($result);
			
			if(sizeof($comment_ids))
			{
				// Delete comments
				$sql = 'DELETE FROM ' . KB_COMMENTS_TABLE . "
						WHERE " . $db->sql_in_set('comment_id', $comment_ids);
				$db->sql_query($sql);
				
				// Delete from attachments table, and delete attachment files
				kb_delete_attachments('comment', $comment_ids);
			}
			
			// Delete from edits table
			$sql = 'DELETE FROM ' . KB_EDITS_TABLE . "
					WHERE " . $db->sql_in_set('article_id', $article_ids);
			$db->sql_query($sql);
			
			// Delete from attachments table, and delete attachment files
			kb_delete_attachments('delete', $article_ids);
			
			// Delete from tags table
			$sql = 'DELETE FROM ' . KB_TAGS_TABLE . "
					WHERE " . $db->sql_in_set('article_id', $article_ids);
			$db->sql_query($sql);
			
			// Delete from tracking table
			$sql = 'DELETE FROM ' . KB_TRACK_TABLE . "
					WHERE " . $db->sql_in_set('article_id', $article_ids);
			$db->sql_query($sql);
			
			// Delete from article table
			$sql = 'DELETE FROM ' . KB_TABLE . "
					WHERE " . $db->sql_in_set('article_id', $article_ids);
			$db->sql_query($sql);
			
			// Unset requests...
			$sql = 'UPDATE ' . KB_REQ_TABLE . " 
					SET article_id = 0, request_accepted = 0, request_status = " . STATUS_REQUEST . "
					WHERE " . $db->sql_in_set('article_id', $article_ids);
			$db->sql_query($sql);
			
			//Resync Stats
			$total_articles = sizeof($article_ids);
			$total_comments = sizeof($comment_ids);
			
			set_config('kb_total_articles', $config['kb_total_articles'] - $total_articles, true);
			if ($total_comments)
			{
				set_config('kb_total_comments', $config['kb_total_comments'] - $total_comments, true);
			}
			set_config('kb_last_updated', time(), true);
			$cache->destroy('config');
		}
		
		return $errors;
	}
	
	/**
	* Move category
	*/
	function move_cat($from_id, $to_id)
	{
		global $db, $user;

		$moved_cats = $errors = array();
		$moved_cats = get_cat_branch($from_id, 'children', 'descending');
		$from_data = $moved_cats[0];
		$diff = sizeof($moved_cats) * 2;

		$moved_ids = array();
		for ($i = 0; $i < sizeof($moved_cats); ++$i)
		{
			$moved_ids[] = $moved_cats[$i]['cat_id'];
		}

		// Resync parents
		$sql = 'UPDATE ' . KB_CATS_TABLE . "
				SET right_id = right_id - $diff
				WHERE left_id < " . $from_data['right_id'] . "
				AND right_id > " . $from_data['right_id'];
		$db->sql_query($sql);

		// Resync righthand side of tree
		$sql = 'UPDATE ' . KB_CATS_TABLE . "
				SET left_id = left_id - $diff, right_id = right_id - $diff
				WHERE left_id > " . $from_data['right_id'];
		$db->sql_query($sql);

		if ($to_id > 0)
		{
			// Retrieve $to_data again, it may have been changed...
			$to_data = $this->get_cat_info($to_id);

			// Resync new parents
			$sql = 'UPDATE ' . KB_CATS_TABLE . "
					SET right_id = right_id + $diff
					WHERE " . $to_data['right_id'] . ' BETWEEN left_id AND right_id
					AND ' . $db->sql_in_set('cat_id', $moved_ids, true);
			$db->sql_query($sql);

			// Resync the righthand side of the tree
			$sql = 'UPDATE ' . KB_CATS_TABLE . "
					SET left_id = left_id + $diff, right_id = right_id + $diff
					WHERE left_id > " . $to_data['right_id'] . '
					AND ' . $db->sql_in_set('cat_id', $moved_ids, true);
			$db->sql_query($sql);

			// Resync moved branch
			$to_data['right_id'] += $diff;

			if ($to_data['right_id'] > $from_data['right_id'])
			{
				$diff = '+ ' . ($to_data['right_id'] - $from_data['right_id'] - 1);
			}
			else
			{
				$diff = '- ' . abs($to_data['right_id'] - $from_data['right_id'] - 1);
			}
		}
		else
		{
			$sql = 'SELECT MAX(right_id) AS right_id
					FROM ' . KB_CATS_TABLE . '
					WHERE ' . $db->sql_in_set('cat_id', $moved_ids, true);
			$result = $db->sql_query($sql);
			$right_id = $db->sql_fetchfield('right_id', $result);
			$db->sql_freeresult($result);

			$diff = '+ ' . ($right_id - $from_data['left_id'] + 1);
		}

		$sql = 'UPDATE ' . KB_CATS_TABLE . "
				SET left_id = left_id $diff, right_id = right_id $diff
				WHERE " . $db->sql_in_set('cat_id', $moved_ids);
		$db->sql_query($sql);

		return $errors;
	}

	/**
	* Display progress bar for syncinc forums
	*/
	function display_progress_bar($start, $total)
	{
		global $template, $user;

		adm_page_header($user->lang['SYNC_IN_PROGRESS']);

		$template->set_filenames(array(
			'body'	=> 'progress_bar.html')
		);

		$template->assign_vars(array(
			'L_PROGRESS'			=> $user->lang['SYNC_IN_PROGRESS'],
			'L_PROGRESS_EXPLAIN'	=> ($start && $total) ? sprintf($user->lang['SYNC_IN_PROGRESS_EXPLAIN'], $start, $total) : $user->lang['SYNC_IN_PROGRESS'])
		);

		adm_page_footer();
	}
}

/**
* Get cat branch
*/
function get_cat_branch($cat_id, $type = 'all', $order = 'descending', $include_cat = true)
{
	global $db;

	switch ($type)
	{
		case 'parents':
			$condition = 'f1.left_id BETWEEN f2.left_id AND f2.right_id';
		break;

		case 'children':
			$condition = 'f2.left_id BETWEEN f1.left_id AND f1.right_id';
		break;

		default:
			$condition = 'f2.left_id BETWEEN f1.left_id AND f1.right_id OR f1.left_id BETWEEN f2.left_id AND f2.right_id';
		break;
	}

	$rows = array();

	$sql = 'SELECT f2.*
		FROM ' . KB_CATS_TABLE . ' f1
		LEFT JOIN ' . KB_CATS_TABLE . " f2 ON ($condition)
		WHERE f1.cat_id = $cat_id
		ORDER BY f2.left_id " . (($order == 'descending') ? 'ASC' : 'DESC');
	$result = $db->sql_query($sql);

	while ($row = $db->sql_fetchrow($result))
	{
		if (!$include_cat && $row['cat_id'] == $cat_id)
		{
			continue;
		}

		$rows[] = $row;
	}
	$db->sql_freeresult($result);

	return $rows;
}

?>