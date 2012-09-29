<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: acp_kb_types.php 416 2010-01-12 21:02:01Z softphp $
* @copyright (c) 2009 Andreas Nexmann, Tom Martin
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	// Avoid Hacking attempts.
	exit;
}

/**
* @package acp
*/
class acp_kb_types
{
	var $u_action;

	function main($id, $mode)
	{
		global $db, $user, $auth, $template, $cache;
		global $config, $phpbb_admin_path, $phpbb_root_path, $phpEx, $table_prefix;
		
		include($phpbb_root_path . 'includes/constants_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/functions_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);

		$user->add_lang('mods/kb');
		
		$this->tpl_name = 'acp_kb_types';
		$this->page_title = 'ACP_MANAGE_KB_TYPES';

		$form_key = 'acp_types';
		add_form_key($form_key);

		$action		= request_var('action', '');
		$submit		= (isset($_POST['submit'])) ? true : false;
		$type_id	= request_var('t', 0);
		
		// Might as well just init types this way
		$types = $cache->obtain_article_types();
		$types_count = count($types);

		$errors = array();
		if ($submit && !check_form_key($form_key))
		{
			$submit = false;
			$errors[] = $user->lang['FORM_INVALID'];
		}
		
		// Valid actions are: add|edit|delete|move_up|move_down
		switch($action)
		{
			case 'move_up':
			case 'move_down':
				// Reorder types
				if(!$type_id)
				{
					trigger_error('KB_NO_TYPE', E_USER_ERROR);
				}
				
				// Reorder types array
				$ordered_types = array();
				foreach($types as $id => $type)
				{
					$ordered_types[$type['order']] = $type;
				}
				
				$type_data = $types[$type_id];
				unset($types);
				$new_order = ($action == 'move_down') ? $type_data['order'] + 1 : $type_data['order'] - 1;
				
				if(isset($ordered_types[$new_order]))
				{
					$sql = 'UPDATE ' . KB_TYPE_TABLE . ' 
							SET type_order = ' . $new_order . ' 
							WHERE type_id = ' . $type_id;
					$db->sql_query($sql);
					
					$sql = 'UPDATE ' . KB_TYPE_TABLE . '
							SET type_order = ' . $type_data['order'] . '
							WHERE type_id = ' . $ordered_types[$new_order]['type_id'];
					$db->sql_query($sql);
					
					$cache->destroy('_kb_types');
					add_log('admin', 'LOG_TYPE_' . strtoupper($action), $type_data['title']);
				}
				
				redirect($this->u_action);
			break;
			
			case 'add':
			case 'edit':
				// Add or edit
				if($action == 'add')
				{
					$type_data = array(
						'type_id'		=> 0,
						'icon_id'		=> 0,
						'type_title'	=> request_var('title', ''),
						'type_before'	=> '',
						'type_after'	=> '',
						'type_image'	=> '',
						'type_img_w'	=> 0,
						'type_img_h'	=> 0,
						'type_order'	=> $types_count + 1,
					);
				}
				else
				{
					if(!isset($types[$type_id]))
					{
						trigger_error('KB_NO_TYPE', E_USER_ERROR);
					}
					
					$type_data = array(
						'icon_id'		=> $types[$type_id]['icon'],
						'type_title'	=> $types[$type_id]['title'],
						'type_before'	=> $types[$type_id]['prefix'],
						'type_after'	=> $types[$type_id]['suffix'],
						'type_image'	=> $types[$type_id]['img'],
						'type_img_w'	=> $types[$type_id]['width'],
						'type_img_h'	=> $types[$type_id]['height'],
						'type_order'	=> $types[$type_id]['order'],
					);
				}
				unset($types);
				
				if($submit)
				{
					// Submit changes
					// Request variables
					$type_data['type_title'] = truncate_string(utf8_normalize_nfc(request_var('title', '', true)));
					$type_data['type_before'] = truncate_string(utf8_normalize_nfc(request_var('prefix', '', true)));
					$type_data['type_after'] = truncate_string(utf8_normalize_nfc(request_var('suffix', '', true)));
					$img_type = request_var('img_type', '');
					
					if($img_type == 'custom')
					{
						$type_data['type_image'] = request_var('image', '');
						//echo $phpbb_root_path . $type_data['image'];
						if(!$image_info = @getimagesize($phpbb_root_path . $type_data['type_image']))
						{
							$errors[] = $user->lang['NO_TYPE_IMAGE'];
						}
						$type_data['type_img_w'] = $image_info[0];
						$type_data['type_img_h'] = $image_info[1];
						$type_data['icon_id'] = 0;
					}
					else if($img_type == 'icon')
					{
						$type_data['icon_id'] = request_var('icon', 0);
						$type_data['type_image'] = '';
						$type_data['type_img_w'] = 0;
						$type_data['type_img_h'] = 0;
					}
					
					if($type_data['type_title'] == '')
					{
						$errors[] = $user->lang['NO_TYPE_TITLE'];
					}
					
					if(!sizeof($errors))
					{
						// Insert
						if($action == 'add')
						{
							$sql = 'INSERT INTO ' . KB_TYPE_TABLE . ' ' . $db->sql_build_array('INSERT', $type_data);
							$db->sql_query($sql);
						}
						else if($action == 'edit')
						{
							$sql = 'UPDATE ' . KB_TYPE_TABLE . ' 
							SET ' . $db->sql_build_array('UPDATE', $type_data) . '
							WHERE type_id = ' . $type_id;
							$db->sql_query($sql);
						}
						
						$cache->destroy('_kb_types');
						add_log('admin', 'LOG_TYPE_' . strtoupper($action), $type_data['type_title']);
						
						$message = ($action == 'add') ? $user->lang['TYPE_CREATED'] : $user->lang['TYPE_UPDATED'];
						trigger_error($message . adm_back_link($this->u_action));
					}
				}
				
				// Init some vars to use for the template
				$custom_checked = ($type_data['type_image'] == '') ? '' : ' checked="checked"';
				$icon_checked = ($type_data['icon_id'] > 0) ? ' checked="checked"' : '';
				posting_gen_topic_icons('', $type_data['icon_id']);
				
				// Pass template variables
				$template->assign_vars(array(
					'S_EDIT_CAT'		=> true,
					'L_TITLE'			=> ($action == 'add') ? $user->lang['TYPE_ADD'] : $user->lang['TYPE_EDIT'],
					'TYPE_TITLE'		=> $type_data['type_title'],
					'TYPE_BEFORE'		=> $type_data['type_before'],
					'TYPE_AFTER'		=> $type_data['type_after'],
					'TYPE_IMAGE'		=> $type_data['type_image'],
					'TYPE_IMAGE_SRC'	=> ($type_data['type_image'] != '') ? $phpbb_root_path . $type_data['type_image'] : '',
					
					'S_ICON_CHECKED'	=> $icon_checked,
					'S_CUSTOM_CHECKED'	=> $custom_checked,
					'S_ERROR'			=> (sizeof($errors)) ? true : false,
					'ERROR_MSG'			=> implode('<br />', $errors),
				));
			break;
			
			case 'delete':
				// Delete
				if(!$type_id)
				{
					trigger_error('KB_NO_TYPE', E_USER_ERROR);
				}
				
				$s_hidden_fields = build_hidden_fields(array(
					't'		=> $type_id,
				));
				
				if(confirm_box(true))
				{
					// Delete it and reorder all other types
					$sql = 'DELETE FROM ' . KB_TYPE_TABLE . '
							WHERE type_id = ' . $type_id;
					$db->sql_query($sql);
					unset($types[$type_id]);
					
					$i = 1;
					foreach($types as $id => $type)
					{
						// Oh no I did the wrongest of wrong, sql in loop... how to get rid of it?
						$sql = 'UPDATE ' . KB_TYPE_TABLE . '
								SET type_order = ' . $i . ' 
								WHERE type_id = ' . $id;
						$db->sql_query($sql);
						$i++;
					}
								
					$cache->destroy('_kb_types');
					add_log('admin', 'LOG_TYPE_' . strtoupper($action));
					trigger_error($user->lang['TYPE_DELETED'] . adm_back_link($this->u_action));
				}
				else
				{
					confirm_box(false, 'TYPE_DELETE', $s_hidden_fields);
				}
				
				redirect($this->u_action);
			break;
			
			case '':
			default:
				// Show List
				$folder_img = '<img src="images/icon_folder.gif" alt="' . $user->lang['FOLDER'] . '" />';
				$icons = $cache->obtain_icons();
				foreach($types as $type_id => $type_data)
				{
					$type_img = ($type_data['img'] == '') ? (($type_data['icon'] > 0 && isset($icons[$type_data['icon']])) ? '<img src="' . $phpbb_root_path . 'images/icons/' . $icons[$type_data['icon']]['img'] . '" />' : '') : '<img src="' . $phpbb_root_path . $type_data['img'] . '" />';
					$before = ($type_data['prefix'] == '') ? '' : $type_data['prefix'] . ' ';
					$after = ($type_data['suffix'] == '') ? '' : ' ' . $type_data['suffix'];
					
					$template->assign_block_vars('types', array(
						'FOLDER_IMAGE'		=> $folder_img,
						'TYPE_IMAGE'		=> $type_img,
						'TYPE_TITLE'		=> $before . $type_data['title'] . $after,
						
						'U_EDIT'			=> $this->u_action . '&amp;action=edit&amp;t=' . $type_id,
						'U_DELETE'			=> $this->u_action . '&amp;action=delete&amp;t=' . $type_id,
						'U_MOVE_UP'			=> $this->u_action . '&amp;action=move_up&amp;t=' . $type_id,
						'U_MOVE_DOWN'		=> $this->u_action . '&amp;action=move_down&amp;t=' . $type_id,
					));
				}
				
				$template->assign_vars(array(
					'U_ACTION'		=> $this->u_action . '&amp;action=add',
				));
			break;
		}
	}
}

?>