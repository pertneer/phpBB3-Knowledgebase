<?php
/**
*
* @package phpBB Knowledge Base Mod (KB)
* @version $Id: functions_kb.php 514 2010-06-23 12:32:18Z andreas.nexmann@gmail.com $
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

//
// This file holds functions not within the kb class
//

/**
* Fill smiley templates (or just the variables) with smilies, either in a window or inline
* Modified phpBB function
*/
function kb_generate_smilies($mode)
{
	global $auth, $db, $user, $config, $template;
	global $phpEx, $phpbb_root_path;

	if ($mode == 'window')
	{
		$user->setup('posting');

		page_header($user->lang['SMILIES']);

		$template->set_filenames(array(
			'body' => 'posting_smilies.html')
		);
	}

	$display_link = false;
	if ($mode == 'inline')
	{
		$sql = 'SELECT smiley_id
			FROM ' . SMILIES_TABLE . '
			WHERE display_on_posting = 0';
		$result = $db->sql_query_limit($sql, 1, 0, 3600);

		if ($row = $db->sql_fetchrow($result))
		{
			$display_link = true;
		}
		$db->sql_freeresult($result);
	}

	$last_url = '';

	$sql = 'SELECT *
		FROM ' . SMILIES_TABLE .
		(($mode == 'inline') ? ' WHERE display_on_posting = 1 ' : '') . '
		ORDER BY smiley_order';
	$result = $db->sql_query($sql, 3600);

	$smilies = array();
	while ($row = $db->sql_fetchrow($result))
	{
		if (empty($smilies[$row['smiley_url']]))
		{
			$smilies[$row['smiley_url']] = $row;
		}
	}
	$db->sql_freeresult($result);

	if (sizeof($smilies))
	{
		foreach ($smilies as $row)
		{
			$template->assign_block_vars('smiley', array(
				'SMILEY_CODE'	=> $row['code'],
				'A_SMILEY_CODE'	=> addslashes($row['code']),
				'SMILEY_IMG'	=> $phpbb_root_path . $config['smilies_path'] . '/' . $row['smiley_url'],
				'SMILEY_WIDTH'	=> $row['smiley_width'],
				'SMILEY_HEIGHT'	=> $row['smiley_height'],
				'SMILEY_DESC'	=> $row['emotion'])
			);
		}
	}

	if ($mode == 'inline' && $display_link)
	{
		$template->assign_vars(array(
			'S_SHOW_SMILEY_LINK' 	=> true,
			'U_MORE_SMILIES' 		=> append_sid("{$phpbb_root_path}kb.$phpEx", 'i=smilies')
		));
	}

	if ($mode == 'window')
	{
		page_footer();
	}
}

/**
* Show upload popup (progress bar)
*/
function kb_upload_popup()
{
	global $template, $user;

	$user->setup('posting');

	page_header($user->lang['PROGRESS_BAR']);

	$template->set_filenames(array(
		'popup'	=> 'posting_progress_bar.html')
	);

	$template->assign_vars(array(
		'PROGRESS_BAR'	=> $user->img('upload_bar', $user->lang['UPLOAD_IN_PROGRESS']))
	);

	$template->display('popup');

	garbage_collection();
	exit_handler();
}

/**
* Upload Attachment - filedata is generated here
* Uses upload class
* Slightly modified phpBB class
*/
function kb_upload_attachment($form_name, $cat_id, $local = false, $local_storage = '', $local_filedata = false)
{
	global $auth, $user, $config, $db, $cache;
	global $phpbb_root_path, $phpEx;

	$filedata = array(
		'error'	=> array()
	);
	
	if(!class_exists('filespec'))
	{
		include($phpbb_root_path . 'includes/functions_upload.' . $phpEx);
	}
	$upload = new fileupload();

	if ($config['check_attachment_content'])
	{
		$upload->set_disallowed_content(explode('|', $config['mime_triggers']));
	}

	if (!$local)
	{
		$filedata['post_attach'] = ($upload->is_valid($form_name)) ? true : false;
	}
	else
	{
		$filedata['post_attach'] = true;
	}

	if (!$filedata['post_attach'])
	{
		$filedata['error'][] = $user->lang['NO_UPLOAD_FORM_FOUND'];
		return $filedata;
	}

	$extensions = $cache->obtain_attach_extensions('kb');
	$upload->set_allowed_extensions(array_keys($extensions['_allowed_']));

	$file = ($local) ? $upload->local_upload($local_storage, $local_filedata) : $upload->form_upload($form_name);

	if ($file->init_error)
	{
		$filedata['post_attach'] = false;
		return $filedata;
	}

	$ext_cat_id = (isset($extensions[$file->get('extension')]['display_cat'])) ? $extensions[$file->get('extension')]['display_cat'] : ATTACHMENT_CATEGORY_NONE;

	// Make sure the image category only holds valid images...
	if ($ext_cat_id == ATTACHMENT_CATEGORY_IMAGE && !$file->is_image())
	{
		$file->remove();

		// If this error occurs a user tried to exploit an IE Bug by renaming extensions
		// Since the image category is displaying content inline we need to catch this.
		trigger_error($user->lang['ATTACHED_IMAGE_NOT_IMAGE']);
	}

	// Do we have to create a thumbnail?
	$filedata['thumbnail'] = ($ext_cat_id == ATTACHMENT_CATEGORY_IMAGE && $config['img_create_thumbnail']) ? 1 : 0;

	// Check Image Size, if it is an image
	if (!$auth->acl_get('a_') && !$auth->acl_get('m_kb_view') && $ext_cat_id == ATTACHMENT_CATEGORY_IMAGE)
	{
		$file->upload->set_allowed_dimensions(0, 0, $config['img_max_width'], $config['img_max_height']);
	}

	// Admins and mods are allowed to exceed the allowed filesize
	if (!$auth->acl_get('a_') && !$auth->acl_get('m_kb_view'))
	{
		if (!empty($extensions[$file->get('extension')]['max_filesize']))
		{
			$allowed_filesize = $extensions[$file->get('extension')]['max_filesize'];
		}
		else
		{
			$allowed_filesize = $config['max_filesize'];
		}

		$file->upload->set_max_filesize($allowed_filesize);
	}

	$file->clean_filename('unique', $user->data['user_id'] . '_');

	// Are we uploading an image *and* this image being within the image category? Only then perform additional image checks.
	$no_image = ($ext_cat_id == ATTACHMENT_CATEGORY_IMAGE) ? false : true;

	$file->move_file($config['upload_path'], false, $no_image);

	if (sizeof($file->error))
	{
		$file->remove();
		$filedata['error'] = array_merge($filedata['error'], $file->error);
		$filedata['post_attach'] = false;

		return $filedata;
	}

	$filedata['filesize'] = $file->get('filesize');
	$filedata['mimetype'] = $file->get('mimetype');
	$filedata['extension'] = $file->get('extension');
	$filedata['physical_filename'] = $file->get('realname');
	$filedata['real_filename'] = $file->get('uploadname');
	$filedata['filetime'] = time();

	// Check our complete quota
	if ($config['attachment_quota'])
	{
		if ($config['upload_dir_size'] + $file->get('filesize') > $config['attachment_quota'])
		{
			$filedata['error'][] = $user->lang['ATTACH_QUOTA_REACHED'];
			$filedata['post_attach'] = false;

			$file->remove();

			return $filedata;
		}
	}

	// Check free disk space
	if ($free_space = @disk_free_space($phpbb_root_path . $config['upload_path']))
	{
		if ($free_space <= $file->get('filesize'))
		{
			$filedata['error'][] = $user->lang['ATTACH_QUOTA_REACHED'];
			$filedata['post_attach'] = false;

			$file->remove();

			return $filedata;
		}
	}

	// Create Thumbnail
	if ($filedata['thumbnail'])
	{
		$source = $file->get('destination_file');
		$destination = $file->get('destination_path') . '/thumb_' . $file->get('realname');

		if (!create_thumbnail($source, $destination, $file->get('mimetype')))
		{
			$filedata['thumbnail'] = 0;
		}
	}

	return $filedata;
}

/**
* Delete Attachments
*
* @param string $mode can be: post|message|topic|attach|user
* @param mixed $ids can be: post_ids, message_ids, topic_ids, attach_ids, user_ids
* @param bool $resync set this to false if you are deleting posts or topics
*/
function kb_delete_attachments($mode, $ids, $resync = true)
{
	global $db, $config, $phpbb_root_path, $phpEx;

	if (is_array($ids) && sizeof($ids))
	{
		$ids = array_unique($ids);
		$ids = array_map('intval', $ids);
	}
	else
	{
		$ids = array((int) $ids);
	}

	if (!sizeof($ids))
	{
		return false;
	}

	switch ($mode)
	{
		case 'add':
		case 'edit':
		case 'delete':
			$sql_id = 'article_id';
		break;

		case 'comment':
			$sql_id = 'comment_id';
		break;

		case 'attach':
		default:
			$sql_id = 'attach_id';
			$mode = 'attach';
		break;
	}

	$article_ids = $comment_ids = $physical = array();
	if(!function_exists('phpbb_unlink'))
	{
		include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
	}

	// Collect post and topic ids for later use if we need to touch remaining entries (if resync is enabled)
	$sql = 'SELECT article_id, comment_id, physical_filename, thumbnail, filesize, is_orphan
			FROM ' . KB_ATTACHMENTS_TABLE . '
			WHERE ' . $db->sql_in_set($sql_id, $ids);
	$result = $db->sql_query($sql);

	while ($row = $db->sql_fetchrow($result))
	{
		// We only need to store post/message/topic ids if resync is enabled and the file is not orphaned
		if ($resync && !$row['is_orphan'])
		{
			if($mode == 'add' || $mode == 'delete' || $mode == 'edit')
			{
				$article_ids[] = $row['article_id'];
			}
			else if($mode == 'comment')
			{
				$comment_ids[] = $row['comment_id'];
			}
		}

		$physical[] = array('filename' => $row['physical_filename'], 'thumbnail' => $row['thumbnail'], 'filesize' => $row['filesize'], 'is_orphan' => $row['is_orphan']);
	}
	$db->sql_freeresult($result);

	// Delete attachments
	$sql = 'DELETE FROM ' . KB_ATTACHMENTS_TABLE . '
			WHERE ' . $db->sql_in_set($sql_id, $ids);
	$db->sql_query($sql);
	$num_deleted = $db->sql_affectedrows();

	if (!$num_deleted)
	{
		return 0;
	}

	// Delete attachments from filesystem
	$space_removed = $files_removed = 0;
	foreach ($physical as $file_ary)
	{
		if (phpbb_unlink($file_ary['filename'], 'file', true) && !$file_ary['is_orphan'])
		{
			// Only non-orphaned files count to the file size
			$space_removed += $file_ary['filesize'];
			$files_removed++;
		}

		if ($file_ary['thumbnail'])
		{
			phpbb_unlink($file_ary['filename'], 'thumbnail', true);
		}
	}

	if ($space_removed || $files_removed)
	{
		set_config('upload_dir_size', $config['upload_dir_size'] - $space_removed, true);
		set_config('num_files', $config['num_files'] - $files_removed, true);
	}

	// If we do not resync, we do not need to adjust any message, post, topic or user entries
	if (!$resync)
	{
		return $num_deleted;
	}

	// No more use for the original ids
	unset($ids);

	// Now, we need to resync posts, messages, topics. We go through every one of them
	$article_ids = array_unique($article_ids);
	$comment_ids = array_unique($comment_ids);

	// Update post indicators for posts now no longer having attachments
	if (sizeof($article_ids))
	{
		$sql = 'UPDATE ' . KB_TABLE . '
			SET article_attachment = 0
			WHERE ' . $db->sql_in_set('article_id', $article_ids);
		$db->sql_query($sql);
	}

	// Update message table if messages are affected
	if (sizeof($comment_ids))
	{
		$sql = 'UPDATE ' . KB_COMMENTS_TABLE . '
			SET comment_attachment = 0
			WHERE ' . $db->sql_in_set('comment_id', $comment_ids);
		$db->sql_query($sql);
	}

	return $num_deleted;
}

/*
* Check edit type
* Function checks for the edit types and returns array ready to be serialized
*/
function check_edit_type($data, $old_data, $update_message = false)
{
	$edit_type = array();
	if(utf8_clean_string($data['article_title']) != $old_data['article_title_clean'])
	{
		$edit_type[] = EDIT_TYPE_TITLE;
	}

	if($data['article_desc'] != $old_data['article_desc'])
	{
		$edit_type[] = EDIT_TYPE_DESC;
	}
	
	if($update_message)
	{
		$edit_type[] = EDIT_TYPE_CONTENT;
	}
	
	if($data['article_tags'] != $old_data['article_tags'])
	{
		$edit_type[] = EDIT_TYPE_TAGS;
	}
	
	if($data['article_type'] != $old_data['article_type'])
	{
		$edit_type[] = EDIT_TYPE_TYPE;
	}
	
	if($data['cat_id'] != $old_data['cat_id'])
	{
		$edit_type[] = EDIT_TYPE_CAT;
	}
	
	if($data['article_status'] != $old_data['article_status'])
	{
		$edit_type[] = EDIT_TYPE_STATUS;
	}
	
	return $edit_type;
}

/**
* Submit edit
* submits all information about article edits to the 
*/
function edit_submit($data, $edit_moderated, $article_id)
{
	global $db, $user;
	
	// Build edits table to take care of old data
	$sql_data[KB_EDITS_TABLE]['sql'] = array(
		'article_id'						=> 		$article_id,
		'parent_id'							=>		$data['article_last_edit_id'], // So silly of me, no need for a function here
		'edit_user_id'						=>		$user->data['user_id'], // Data of the user doing the edit
		'edit_user_name'					=>		$user->data['username'],
		'edit_user_color'					=>		$user->data['user_colour'],
		'edit_time'							=>		$data['article_time'],
		'edit_article_title'				=>		$data['article_title'],
		'edit_article_desc'					=>		$data['article_desc'],
		'edit_article_desc_bitfield'		=>		$data['article_desc_bitfield'],
		'edit_article_desc_options'			=>		$data['article_desc_options'],
		'edit_article_desc_uid'				=>		$data['article_desc_uid'],
		'edit_article_checksum'				=>		$data['article_checksum'],
		'edit_article_text'					=>		$data['message'],
		'edit_enable_bbcode'                =>      $data['enable_bbcode'],
		'edit_enable_smilies'               =>      $data['enable_smilies'],
		'edit_enable_magic_url'             =>      $data['enable_urls'],
		'edit_enable_sig'                   =>      $data['enable_sig'],
		'edit_bbcode_bitfield'				=>		$data['bbcode_bitfield'],
		'edit_bbcode_uid'					=>		$data['bbcode_uid'],
		'edit_article_status'				=>		$data['article_status'],
		'edit_reason'						=>		$data['article_edit_reason'],
		'edit_reason_global'				=>		$data['article_edit_reason_global'],
		'edit_moderated'					=>		($edit_moderated) ? 1 : 0,
		'edit_type'							=>		serialize($data['edit_type']), //serialize($edit_type),
		'edit_cat_id'						=>		$data['cat_id'],
		'edit_article_tags'					=>		$data['article_tags'],
		'edit_article_type'					=>		$data['article_type'],
		'edit_contribution'					=>		$data['article_contribution'],
	);
	
	$sql = 'INSERT INTO ' . KB_EDITS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data[KB_EDITS_TABLE]['sql']);
	$db->sql_query($sql);
	$edit_id = $db->sql_nextid();
	
	return $edit_id;
}

/**
* Submit Article
*/
function article_submit($mode, &$data, $update_message = true, $article_id = 0)
{
	global $db, $auth, $user, $config, $phpEx, $template, $phpbb_root_path;
	
	if($mode == 'delete')
	{
		// No delete from this function
		return false;
	}
	
	if($mode == 'add')
	{
		$update_message = true;
	}
	
	// Begin sql transaction and build needed sql data
	$db->sql_transaction('begin');
	
	$sql_data = array();
	$data['article_title'] = truncate_string($data['article_title']);
	//$data['article_desc'] = truncate_string($data['article_desc'], 300, 500);
	
	// Build sql data for articles table
	$sql_data[KB_TABLE]['sql'] = array(
		'cat_id'						=> 	$data['cat_id'],
		'article_title'					=>	$data['article_title'],
		'article_title_clean'			=>  utf8_clean_string($data['article_title']),
		'article_desc'					=>	$data['article_desc'],
		'article_desc_bitfield'			=>	$data['article_desc_bitfield'],
		'article_desc_options'			=>	$data['article_desc_options'],
		'article_desc_uid'				=>	$data['article_desc_uid'],
		'article_checksum'				=>	$data['message_md5'],
		'article_status'				=>	$data['article_status'],
		'article_attachment'			=>	(!empty($data['attachment_data'])) ? 1 : 0,
		'article_views'					=>	$data['article_views'],
		'article_user_id'				=>	$data['article_user_id'],
		'article_user_name'				=>	$data['article_user_name'],
		'article_user_color'			=>	$data['article_user_color'],
		'article_time'					=>	$data['article_time'],
		'article_tags'					=>	$data['article_tags'],
		'article_type'					=>	$data['article_type'],
		'enable_bbcode'					=>	$data['enable_bbcode'],
		'enable_smilies'				=>	$data['enable_smilies'],
		'enable_magic_url'				=>	$data['enable_urls'],
		'enable_sig'					=>	$data['enable_sig'],
		'bbcode_bitfield'				=>	$data['bbcode_bitfield'],
		'bbcode_uid'					=>	$data['bbcode_uid'],
		'article_last_edit_time'		=>	$data['current_time'],
		'article_last_edit_id'			=>	$data['article_last_edit_id'],
		'article_edit_reason'			=>	$data['article_edit_reason'],
		'article_edit_reason_global'	=>	$data['article_edit_reason_global'],
		'article_open'					=>  $data['article_open'],
		'article_edit_contribution'		=>  $data['article_contribution'],
		'article_edit_type'				=>	serialize($data['edit_type']),
	);
	$data['article_edit_type'] = $sql_data[KB_TABLE]['sql']['article_edit_type'];
	
	// Only update text if needed
	if($update_message)
	{
		$sql_data[KB_TABLE]['sql']['article_text'] = $data['message'];
	}
	
	// Submit an edit based on this edit
	if($mode == 'edit')
	{
		$edit_moderated = ($auth->acl_get('m_kb_edit')) ? true : false;
		$edit_id = edit_submit($data, $edit_moderated, $article_id);
		$sql_data[KB_TABLE]['sql']['article_last_edit_id'] = $edit_id;
	}
	
	// Submit article
	if($mode == 'add')
	{
		$sql = 'INSERT INTO ' . KB_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data[KB_TABLE]['sql']);
		$db->sql_query($sql);
		$data['article_id'] = $db->sql_nextid();
		
		if ($data['article_status'] == STATUS_APPROVED)
		{
			on_posting('article', 'add', $data);
		}
	}
	else if($mode == 'edit')
	{
		$sql = 'UPDATE ' . KB_TABLE . ' 
				SET ' . $db->sql_build_array('UPDATE', $sql_data[KB_TABLE]['sql']) . '
				WHERE article_id = ' . $data['article_id'];
		$db->sql_query($sql);
		
		if ($data['article_status'] == STATUS_APPROVED)
		{
			on_posting('article', 'edit', $data);
		}
		
		set_config('kb_last_updated', time(), true);
	}
	
	// Synchronize tables when moving category
	if($mode == 'edit' && $data['article_status'] == STATUS_APPROVED && $data['edit_data']['article_status'] == STATUS_APPROVED && $data['cat_id'] != $data['edit_data']['cat_id'])
	{
		$sql = 'UPDATE ' . KB_CATS_TABLE . '
				SET cat_articles = cat_articles + 1
				WHERE cat_id = ' . (int) $data['cat_id'];
		$db->sql_query($sql);
		
		$sql = 'UPDATE ' . KB_CATS_TABLE . '
				SET cat_articles = cat_articles - 1
				WHERE cat_articles > 0
				AND cat_id = ' . (int) $old_data['cat_id'];
		$db->sql_query($sql);
	}
	
	// Handle tags
	if($data['create_tags'])
	{
		// Delete old tag entries
		if($mode == 'edit')
		{
			$sql = 'DELETE FROM ' . KB_TAGS_TABLE . ' WHERE article_id = ' . $data['article_id'];
			$db->sql_query($sql);
		}
		
		// Seperate tags by , then build them into the tags table
		$data['article_tags'] = truncate_string($data['article_tags']);
		$tags = explode(',', $data['article_tags']);
		
		foreach($tags as $tag)
		{
			// Strip starting spaces
			$str = $tag;
			if(strpos($str, ' ') === 0)
			{
				$tag = utf8_substr($tag, 1);
			}
			
			$sql_data[KB_TAGS_TABLE]['sql'][] = array(
				'article_id'		=> $data['article_id'],
				'tag_name'			=> $tag,
				'tag_name_lc'		=> utf8_strtolower($tag),
			);
		}
		
		$db->sql_multi_insert(KB_TAGS_TABLE, $sql_data[KB_TAGS_TABLE]['sql']);
	}
	
	// Assign request to this article
	if($data['request_id'])
	{
		$sql = 'UPDATE ' . KB_REQ_TABLE . '
				SET article_id = ' . $data['article_id'] . '
				WHERE request_id = ' . $data['request_id'];
		$db->sql_query($sql);
	}
	
	// Don't update cats table until the article is shown.
	// Only thing left is attachments
	// Submit Attachments
	if (!empty($data['attachment_data']) && $data['article_id'] && in_array($mode, array('add', 'edit')))
	{
		$space_taken = $files_added = 0;
		$orphan_rows = array();

		foreach ($data['attachment_data'] as $pos => $attach_row)
		{
			$orphan_rows[(int) $attach_row['attach_id']] = array();
		}

		if (sizeof($orphan_rows))
		{
			$sql = 'SELECT attach_id, filesize, physical_filename
				FROM ' . KB_ATTACHMENTS_TABLE . '
				WHERE ' . $db->sql_in_set('attach_id', array_keys($orphan_rows)) . '
				AND is_orphan = 1
				AND poster_id = ' . $user->data['user_id'];
			$result = $db->sql_query($sql);

			$orphan_rows = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$orphan_rows[$row['attach_id']] = $row;
			}
			$db->sql_freeresult($result);
		}

		foreach ($data['attachment_data'] as $pos => $attach_row)
		{
			if ($attach_row['is_orphan'] && !isset($orphan_rows[$attach_row['attach_id']]))
			{
				continue;
			}

			if (!$attach_row['is_orphan'])
			{
				// update entry in db if attachment already stored in db and filespace
				$sql = 'UPDATE ' . KB_ATTACHMENTS_TABLE . "
					SET attach_comment = '" . $db->sql_escape($attach_row['attach_comment']) . "'
					WHERE attach_id = " . (int) $attach_row['attach_id'] . '
						AND is_orphan = 0';
				$db->sql_query($sql);
			}
			else
			{
				// insert attachment into db
				if (!@file_exists($phpbb_root_path . $config['upload_path'] . '/' . utf8_basename($orphan_rows[$attach_row['attach_id']]['physical_filename'])))
				{
					continue;
				}

				$space_taken += $orphan_rows[$attach_row['attach_id']]['filesize'];
				$files_added++;

				$attach_sql = array(
					'article_id'		=> $data['article_id'],
					'comment_id'		=> 0,
					'is_orphan'			=> 0,
					'poster_id'			=> $data['article_user_id'],
					'attach_comment'	=> $attach_row['attach_comment'],
				);

				$sql = 'UPDATE ' . KB_ATTACHMENTS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $attach_sql) . '
					WHERE attach_id = ' . $attach_row['attach_id'] . '
						AND is_orphan = 1
						AND poster_id = ' . $user->data['user_id'];
				$db->sql_query($sql);
			}
		}

		if ($space_taken && $files_added)
		{
			set_config('upload_dir_size', $config['upload_dir_size'] + $space_taken, true);
			set_config('num_files', $config['num_files'] + $files_added, true);
		}
	}
	
	return $data['article_id'];
}

/**
* Submit Comment
*/
function comment_submit($mode, &$data, $update_message = true)
{
	global $db, $auth, $user, $config, $phpEx, $template, $phpbb_root_path;
	
	if($mode == 'delete')
	{
		// No delete from this function
		return false;
	}
	
	if($mode == 'add')
	{
		$update_message = true;
	}
	
	// Begin sql transaction and build needed sql data
	$db->sql_transaction('begin');
	
	$sql_data = array();
	$data['comment_title'] = truncate_string($data['comment_title']);
	
	// Build sql data for articles table
	$sql_data[KB_COMMENTS_TABLE]['sql'] = array(
		'article_id'				=> 		$data['article_id'],
		'comment_title'				=>		$data['comment_title'],
		'comment_checksum'			=>		$data['message_md5'],
		'comment_type'				=>		$data['comment_type'],
		'comment_attachment'		=>		(isset($data['attachment_data'])) ? 1 : 0, //$data['comment_attachment'],
		'comment_user_id'			=>		$data['comment_user_id'],
		'comment_user_name'			=>		$data['comment_user_name'],
		'comment_user_color'		=>		$data['comment_user_color'],
		'comment_time'				=>		$data['current_time'],
		'enable_bbcode'				=>		$data['enable_bbcode'],
		'enable_smilies'			=>		$data['enable_smilies'],
		'enable_magic_url'			=>		$data['enable_urls'],
		'enable_sig'				=>		$data['enable_sig'],
		'bbcode_bitfield'			=>		$data['bbcode_bitfield'],
		'bbcode_uid'				=>		$data['bbcode_uid'],
		'comment_edit_time'			=>		$data['comment_edit_time'],
		'comment_edit_id'			=>		$data['comment_edit_id'],
		'comment_edit_name'			=>		$data['comment_edit_name'],
		'comment_edit_color'		=>		$data['comment_edit_color'],
	);
	
	// Only update text if needed
	if($update_message)
	{
		$sql_data[KB_COMMENTS_TABLE]['sql']['comment_text'] = $data['message'];
	}
	
	// Submit comment
	if($mode == 'add')
	{
		$sql = 'INSERT INTO ' . KB_COMMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data[KB_COMMENTS_TABLE]['sql']);
		$db->sql_query($sql);
		$data['comment_id'] = $db->sql_nextid();
		
		set_config('kb_last_updated', time(), true);		
		
		if($data['comment_type'] == COMMENT_GLOBAL)
		{
			// Update article table comment count
			$sql = 'UPDATE ' . KB_TABLE . "
					SET article_comments = article_comments + 1
					WHERE article_id = {$data['article_id']}";
			$db->sql_query($sql);
			
			set_config('kb_total_comments', $config['kb_total_comments'] + 1, true);
		}
	}
	// Else edit it
	else if($mode == 'edit')
	{
		$sql = 'UPDATE ' . KB_COMMENTS_TABLE . ' 
				SET ' . $db->sql_build_array('UPDATE', $sql_data[KB_COMMENTS_TABLE]['sql']) . '
				WHERE comment_id = ' . $data['comment_id'];
		$db->sql_query($sql);
		
		set_config('kb_last_updated', time(), true);
	}
	
	// Only thing left is attachments
	// Submit Attachments
	if (!empty($data['attachment_data']) && $data['comment_id'] && in_array($mode, array('add', 'edit')))
	{
		$space_taken = $files_added = 0;
		$orphan_rows = array();

		foreach ($data['attachment_data'] as $pos => $attach_row)
		{
			$orphan_rows[(int) $attach_row['attach_id']] = array();
		}

		if (sizeof($orphan_rows))
		{
			$sql = 'SELECT attach_id, filesize, physical_filename
				FROM ' . KB_ATTACHMENTS_TABLE . '
				WHERE ' . $db->sql_in_set('attach_id', array_keys($orphan_rows)) . '
					AND is_orphan = 1
					AND poster_id = ' . $user->data['user_id'];
			$result = $db->sql_query($sql);

			$orphan_rows = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$orphan_rows[$row['attach_id']] = $row;
			}
			$db->sql_freeresult($result);
		}

		foreach ($data['attachment_data'] as $pos => $attach_row)
		{
			if ($attach_row['is_orphan'] && !isset($orphan_rows[$attach_row['attach_id']]))
			{
				continue;
			}

			if (!$attach_row['is_orphan'])
			{
				// update entry in db if attachment already stored in db and filespace
				$sql = 'UPDATE ' . KB_ATTACHMENTS_TABLE . "
					SET attach_comment = '" . $db->sql_escape($attach_row['attach_comment']) . "'
					WHERE attach_id = " . (int) $attach_row['attach_id'] . '
						AND is_orphan = 0';
				$db->sql_query($sql);
			}
			else
			{
				// insert attachment into db
				if (!@file_exists($phpbb_root_path . $config['upload_path'] . '/' . utf8_basename($orphan_rows[$attach_row['attach_id']]['physical_filename'])))
				{
					continue;
				}

				$space_taken += $orphan_rows[$attach_row['attach_id']]['filesize'];
				$files_added++;

				$attach_sql = array(
					'article_id'		=> 0,
					'comment_id'		=> $data['comment_id'],
					'is_orphan'			=> 0,
					'poster_id'			=> $data['comment_user_id'],
					'attach_comment'	=> $attach_row['attach_comment'],
				);

				$sql = 'UPDATE ' . KB_ATTACHMENTS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $attach_sql) . '
					WHERE attach_id = ' . $attach_row['attach_id'] . '
						AND is_orphan = 1
						AND poster_id = ' . $user->data['user_id'];
				$db->sql_query($sql);
			}
		}

		if ($space_taken && $files_added)
		{
			set_config('upload_dir_size', $config['upload_dir_size'] + $space_taken, true);
			set_config('num_files', $config['num_files'] + $files_added, true);
		}
	}
	
	return $data['comment_id'];
}

/**
* Submit request
* handle take on as well
*/
function request_submit($mode, &$data, $update_message = true)
{
	global $db, $auth, $user, $config, $phpEx, $template, $phpbb_root_path;
	
	if($mode == 'delete')
	{
		// No delete from this function
		return false;
	}
	
	if($mode == 'add')
	{
		$update_message = true;
	}
	
	// Begin sql transaction and build needed sql data
	$db->sql_transaction('begin');
	
	$sql_data = array();
	$data['request_title'] = truncate_string($data['request_title']);
	
	// Build sql data for articles table
	$sql_data[KB_REQ_TABLE]['sql'] = array(
		'request_id'				=> $data['request_id'],
		'article_id'				=> $data['article_id'],
		'request_accepted'			=> $data['request_accepted'],
		'request_title'				=> $data['request_title'],
		'request_checksum'			=> $data['message_md5'],
		'request_status'			=> $data['request_status'],
		'request_user_id'			=> $data['request_user_id'],
		'request_user_name'			=> $data['request_user_name'],
		'request_user_color'		=> $data['request_user_color'],
		'request_time'				=> $data['request_time'],
		'bbcode_bitfield'			=> $data['bbcode_bitfield'],
		'bbcode_uid'				=> $data['bbcode_uid'],
	);
	
	// Only update text if needed
	if($update_message)
	{
		$sql_data[KB_REQ_TABLE]['sql']['request_text'] = $data['request_text'];
	}
	
	// Submit article
	if($mode == 'add')
	{
		$sql = 'INSERT INTO ' . KB_REQ_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data[KB_REQ_TABLE]['sql']);
		$db->sql_query($sql);
		$data['request_id'] = $db->sql_nextid();
	}
	else if($mode == 'edit')
	{
		$sql = 'UPDATE ' . KB_REQ_TABLE . ' 
				SET ' . $db->sql_build_array('UPDATE', $sql_data[KB_REQ_TABLE]['sql']) . '
				WHERE request_id = ' . $data['request_id'];
		$db->sql_query($sql);
	}
	
	return $data['request_id'];
}

/**
* Generate inline attachment entry
*/
function kb_posting_gen_attachment_entry($attachment_data, &$filename_data, $show_attach_box = true)
{
	global $template, $config, $phpbb_root_path, $phpEx, $user, $auth;

	// Some default template variables
	$template->assign_vars(array(
		'S_SHOW_ATTACH_BOX'	=> $show_attach_box,
		'S_HAS_ATTACHMENTS'	=> sizeof($attachment_data),
		'FILESIZE'			=> $config['max_filesize'],
		'FILE_COMMENT'		=> (isset($filename_data['filecomment'])) ? $filename_data['filecomment'] : '',
	));

	if (sizeof($attachment_data))
	{
		// We display the posted attachments within the desired order.
		($config['display_order']) ? krsort($attachment_data) : ksort($attachment_data);

		foreach ($attachment_data as $count => $attach_row)
		{
			$hidden = '';
			$attach_row['real_filename'] = utf8_basename($attach_row['real_filename']);

			foreach ($attach_row as $key => $value)
			{
				$hidden .= '<input type="hidden" name="attachment_data[' . $count . '][' . $key . ']" value="' . $value . '" />';
			}

			$download_link = append_sid("{$phpbb_root_path}download/file.$phpEx", 'mode=view&amp;kb=1&amp;id=' . (int) $attach_row['attach_id'], true, ($attach_row['is_orphan']) ? $user->session_id : false);

			$template->assign_block_vars('attach_row', array(
				'FILENAME'			=> utf8_basename($attach_row['real_filename']),
				'A_FILENAME'		=> addslashes(utf8_basename($attach_row['real_filename'])),
				'FILE_COMMENT'		=> $attach_row['attach_comment'],
				'ATTACH_ID'			=> $attach_row['attach_id'],
				'S_IS_ORPHAN'		=> $attach_row['is_orphan'],
				'ASSOC_INDEX'		=> $count,

				'U_VIEW_ATTACHMENT'	=> $download_link,
				'S_HIDDEN'			=> $hidden)
			);
		}
	}

	return sizeof($attachment_data);
}

/**
* Notify user
*/
function kb_handle_notification($article_id, $article_title, $notify_on)
{
	global $db, $user, $phpEx, $phpbb_root_path, $config;
	
	if(!is_array($notify_on))
	{
		$notify_on = array($notify_on);
	}
	
	$sql_query = array(
		'SELECT'	=> 'n.user_id, n.notify_by, u.user_lang, u.user_email, u.username',
		'FROM'		=> array(
			KB_TRACK_TABLE	=> 'n',
		),
		'LEFT_JOIN'	=> array(
			array(
				  'FROM'	=> array(USERS_TABLE => 'u'),
				  'ON'		=> 'n.user_id = u.user_id'
			),
		),
		'WHERE' => "n.article_id = $article_id AND n.subscribed > 0 AND " . $db->sql_in_set('n.notify_on', $notify_on),
	);
	$sql = $db->sql_build_query('SELECT', $sql_query);
	$result = $db->sql_query($sql);
	$update_ids = array();
	$pm_list = array();
	$lang_ary = array(); // Supporting multiple languages
	
	if(!class_exists('messenger'))
	{
		include($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
	}
	
	if(!function_exists('submit_pm'))
	{
		include($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
	}
	
	while($row = $db->sql_fetchrow($result))
	{
		switch($row['notify_by'])
		{
			case NOTIFY_MAIL:
				// Handle mail
				$messenger = new messenger(false);

				$messenger->template('kb_notify_email', $row['user_lang']);

				$messenger->to($row['user_email'], $row['username']);

				$messenger->headers('X-AntiAbuse: Board servername - ' . $config['server_name']);
				$messenger->headers('X-AntiAbuse: User_id - ' . $user->data['user_id']);
				$messenger->headers('X-AntiAbuse: Username - ' . $user->data['username']);
				$messenger->headers('X-AntiAbuse: User IP - ' . $user->ip);

				$messenger->assign_vars(array(
					'ARTICLE_TITLE'	=> htmlspecialchars_decode($article_title),
					'USERNAME'		=> htmlspecialchars_decode($row['username']),
					'U_ARTICLE'		=> htmlspecialchars_decode(generate_board_url() . "/kb.$phpEx?a=" . $article_id),
				));

				$messenger->send(NOTIFY_EMAIL);
			break;
				
			case NOTIFY_PM:
				// Handle pm
				$pm_list[$row['user_lang']]['u'][$row['user_id']] = 'to';
				
				// Parse message here to avoid lang probs
				if(!isset($lang_ary[$row['user_lang']]))
				{
					$message_text = file_get_contents($phpbb_root_path . 'language/' . $row['user_lang'] . '/email/kb_notify_pm.txt');
					$message_parser = new parse_message();
					$message_parser->message = $message_text;
					// Parse the url here as it doesn't contain lang vars
					$message_parser->message = str_replace('{U_ARTICLE}', '[url]' . generate_board_url() . "/kb.$phpEx?a=" . $article_id . '[/url]', $message_parser->message);
					unset($message_text);
					
					// Parse message. no imgs allowed, parse urls, smileys and common bbcode, no quote or flash.
					$message_parser->parse(true, true, true, false, false, false, true);
					
					$lang_ary[$row['user_lang']] = array(
						'bbcode_bitfield'		=> $message_parser->bbcode_bitfield,
						'bbcode_uid'			=> $message_parser->bbcode_uid,
						'message'				=> $message_parser->message,
					);
				}
			break;
		}
		$update_ids[] = $row['user_id'];
	}
	$db->sql_freeresult($result);
	
	if(sizeof($lang_ary))
	{
		// Do one pm for each language
		foreach($lang_ary as $language => $message)
		{
			if(sizeof($pm_list[$language]))
			{
				// Include language file for this lang
				unset($lang);
				include($phpbb_root_path . 'language/' . $language . '/mods/kb.' . $phpEx);
				
				$message['message'] = str_replace('{ARTICLE_TITLE}', $article_title, $message['message']);
				$pm_data = array(
					'from_user_id'			=> $user->data['user_id'],
					'from_user_ip'			=> $user->ip,
					'from_username'			=> $user->data['username'],
					'reply_from_root_level'	=> 0,
					'reply_from_msg_id'		=> 0,
					'icon_id'				=> 0,
					'enable_sig'			=> false,
					'enable_bbcode'			=> true,
					'enable_smilies'		=> true,
					'enable_urls'			=> true,
					'bbcode_bitfield'		=> $message['bbcode_bitfield'],
					'bbcode_uid'			=> $message['bbcode_uid'],
					'message'				=> $message['message'],
					'attachment_data'		=> array(),
					'filename_data'			=> array(),
					'address_list'			=> $pm_list[$language]
				);
				$msg_id = submit_pm('post', $lang['NOTIFY_PM_SUBJECT'], $pm_data);
			}
		}
	}
	
	// Update tables to tell people there is a new notification
	// This should enable popups as well
	if(!empty($update_ids))
	{
		$sql = 'UPDATE ' . KB_TRACK_TABLE . "
				SET subscribed = 2
				WHERE article_id = $article_id
				AND " . $db->sql_in_set('user_id', $update_ids);
		$db->sql_query($sql);
	}
}

/**
* Generate popups
*/
function kb_generate_popups()
{
	global $user, $db, $template;
	
	if(!$user->data['is_registered'])
	{
		return array('generate' => false);
	}
	
	$sql = 'SELECT article_id
			FROM ' . KB_TRACK_TABLE . '
			WHERE user_id = ' . $user->data['user_id'] . '
			AND subscribed = 2
			AND notify_by = ' . NOTIFY_POPUP;
	$result = $db->sql_query($sql);
	if(!$article_data = $db->sql_fetchrow($result))
	{
		return array('generate' => false);
	}
	$db->sql_freeresult($result);
	
	return array('generate' => true, 'article_id' => $article_data['article_id']);
}

/**
* Delete an article
*/
function article_delete($article_id, $cat_id, $article_data)
{
	global $phpEx, $user, $phpbb_root_path, $auth, $db, $config;
	
	$s_hidden_fields = build_hidden_fields(array(
		'a'		=> $article_id,
		'c'		=> $cat_id,
		'i'		=> 'delete')
	);
	
	if(confirm_box(true))
	{
		// Delete article
		// Delete from rate table
		$sql = 'DELETE FROM ' . KB_RATE_TABLE . "
				WHERE article_id = $article_id";
		$db->sql_query($sql);
		
		$comment_ids = array();
		$global_comments = 0;
		$sql = 'SELECT comment_id, comment_type
				FROM ' . KB_COMMENTS_TABLE . "
				WHERE article_id = $article_id";
		$result = $db->sql_query($sql);
		while($row = $db->sql_fetchrow($result))
		{
			// Don't loop this: comment_delete($row['comment_id'], $article_id, false);
			$comment_ids[] = $row['comment_id'];
			if($row['comment_type'] == COMMENT_GLOBAL)
			{
				$global_comments++;
			}
		}
		$db->sql_freeresult($result);
		
		if(sizeof($comment_ids))
		{
			$sql = 'DELETE FROM ' . KB_COMMENTS_TABLE . "
					WHERE " . $db->sql_in_set('comment_id', $comment_ids);
			$db->sql_query($sql);
			
			// Delete from attachments table, and delete attachment files
			kb_delete_attachments('comment', $comment_ids);
			
			set_config('kb_total_comments', $config['kb_total_comments'] - $global_comments, true);
		}
		
		// Delete from edits table
		$sql = 'DELETE FROM ' . KB_EDITS_TABLE . "
				WHERE article_id = $article_id";
		$db->sql_query($sql);
		
		// Delete from attachments table, and delete attachment files
		kb_delete_attachments('delete', array($article_id));
		
		// Delete from tags table
		$sql = 'DELETE FROM ' . KB_TAGS_TABLE . "
				WHERE article_id = $article_id";
		$db->sql_query($sql);
		
		// Delete from tracking table
		$sql = 'DELETE FROM ' . KB_TRACK_TABLE . "
				WHERE article_id = $article_id";
		$db->sql_query($sql);
		
		// Delete from article table
		$sql = 'DELETE FROM ' . KB_TABLE . "
				WHERE article_id = $article_id";
		$db->sql_query($sql);
		
		// Unset requests...
		$sql = 'UPDATE ' . KB_REQ_TABLE . " 
				SET article_id = 0, request_accepted = 0, request_status = " . STATUS_REQUEST . "
				WHERE article_id = $article_id";
		$db->sql_query($sql);
		
		// Resync cat table if needed
		if($article_data['article_status'] == STATUS_APPROVED)
		{
			$sql = 'UPDATE ' . KB_CATS_TABLE . "
					SET cat_articles = cat_articles - 1
					WHERE cat_articles > 0
					AND cat_id = $cat_id";
			$db->sql_query($sql);
			
			$sql = 'UPDATE ' . USERS_TABLE . " 
					SET user_articles = user_articles - 1
					WHERE user_articles > 0
					AND user_id = {$article_data['article_user_id']}";
			$db->sql_query($sql);
			
			set_config('kb_total_articles', $config['kb_total_articles'] - 1, true);
		}
		
		// Delete from latest articles list
		handle_latest_articles('delete', $cat_id, array('article_id' => $article_id), $config['kb_latest_articles_c']);
		
		$meta_info = append_sid("{$phpbb_root_path}kb.$phpEx", "c=$cat_id");
		$message = $user->lang['ARTICLE_DELETED'] . '<br /><br />' . sprintf($user->lang['RETURN_KB_CAT'], '<a href="' . $meta_info . '">', '</a>');
		meta_refresh(5, $meta_info);
		trigger_error($message);
	}
	else
	{
		confirm_box(false, 'DELETE_ARTICLE', $s_hidden_fields);
	}
	
	redirect(append_sid("{$phpbb_root_path}kb.$phpEx", "a=$article_id"));
}

/*
* Delete a comment
*/
function comment_delete($comment_id, $article_id, $type = COMMENT_GLOBAL)
{
	global $phpEx, $user, $phpbb_root_path;
	global $config, $auth, $db;
	
	$s_hidden_fields = build_hidden_fields(array(
		'comment_id' => $comment_id,
		'action' => 'delete',
		'i'		=> 'comment')
	);
	
	if(confirm_box(true))
	{
		// Delete comment
		$sql = 'DELETE FROM ' . KB_COMMENTS_TABLE . "
				WHERE comment_id = $comment_id";
		$db->sql_query($sql);
		
		// Delete from attachments table, and delete attachment files
		kb_delete_attachments('comment', array($comment_id));
		
		if($type == COMMENT_GLOBAL)
		{
			// Update article table comment count
			$sql = 'UPDATE ' . KB_TABLE . " 
					SET article_comments = article_comments - 1
					WHERE article_id = $article_id";
			$db->sql_query($sql);
			
			set_config('kb_total_comments', $config['kb_total_comments'] - 1, true);
		}
		
		$meta_info = append_sid("{$phpbb_root_path}kb.$phpEx", "a=$article_id");
		$message = $user->lang['COMMENT_DELETED'] . '<br /><br />' . sprintf($user->lang['RETURN_KB_ARTICLE'], '<a href="' . $meta_info . '">', '</a>');
		meta_refresh(5, $meta_info);
		trigger_error($message);
	}
	else
	{
		confirm_box(false, 'DELETE_COMMENT', $s_hidden_fields);
	}
	
	redirect(append_sid("{$phpbb_root_path}kb.$phpEx", "a=$article_id"));
}

/*
* Request delete
*/
function request_delete($request_id)
{
	global $db, $phpbb_root_path, $phpEx, $user;
	
	$s_hidden_fields = build_hidden_fields(array(
		'r'	=> $request_id,
	));
	
	if(confirm_box(true))
	{
		// Delete request
		$sql = 'DELETE FROM ' . KB_REQ_TABLE . ' 
				WHERE request_id = '. $request_id;
		$db->sql_query($sql);
		
		$meta_info = append_sid("{$phpbb_root_path}kb.$phpEx", 'i=request&amp;action=list');
		$message = $user->lang['REQUEST_DELETED'] . '<br /><br />' . sprintf($user->lang['RETURN_KB'], '<a href="' . append_sid("{$phpbb_root_path}kb.$phpEx") . '">', '</a>');
		meta_refresh(5, $meta_info);
		trigger_error($message);
	}
	else
	{
		confirm_box(false, 'DELETE_REQUEST', $s_hidden_fields);
	}
	
	redirect(append_sid("{$phpbb_root_path}kb.$phpEx", "i=request&amp;action=view&amp;r=$request_id"));
}

/*
* KB handling of user deletion
* This function is called when a user is deleted and will take care of deleting all KB related user stuff
*/
function kb_user_delete($mode, $user_id, $post_username)
{
	global $db, $user, $config, $cache;
	
	if(!defined('KB_TABLE'))
	{
		global $phpEx, $phpbb_root_path, $table_prefix;
		include($phpbb_root_path . 'includes/constants_kb.' . $phpEx);
	}
	
	// Clear other tables from his info
	$sql = 'DELETE FROM ' . KB_ACL_USERS_TABLE . '
			WHERE user_id = ' . $user_id;
	$db->sql_query($sql);
	
	// Delete from comments table - no need to store these
	$comment_ids = array();
	$article_ids = array();
	$sql = 'SELECT comment_id, article_id
			FROM ' . KB_COMMENTS_TABLE . "
			WHERE comment_user_id = " . $user_id;
	$result = $db->sql_query($sql);
	while($row = $db->sql_fetchrow($result))
	{
		// Don't loop this: comment_delete($row['comment_id'], $article_id, false);
		$comment_ids[] = $row['comment_id'];
		
		if(!isset($article_ids[$row['article_id']]))
		{
			$article_ids[$row['article_id']] = 1;
		}
		else
		{
			$article_ids[$row['article_id']]++;
		}
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
		
		// Update comment count for articles
		foreach($article_ids as $article_id => $comments_num)
		{
			$sql = 'UPDATE ' . KB_TABLE . '
					SET article_comments = article_comments - ' . $comments_num . '
					WHERE article_id = ' . $article_id;
			$db->sql_query($sql);
		}
		
		set_config('kb_total_comments', $config['kb_total_comments'] - count($comment_ids), true);
	}

	$sql = 'DELETE FROM ' . KB_RATE_TABLE . '
			WHERE user_id = ' . $user_id;
	$db->sql_query($sql);
	
	$sql = 'DELETE FROM ' . KB_REQ_TABLE . '
			WHERE request_user_id = ' . $user_id;
	$db->sql_query($sql);
	
	$sql = 'DELETE FROM ' . KB_TRACK_TABLE . '
			WHERE user_id = ' . $user_id;
	$db->sql_query($sql);
	
	// Retrieve all articles in category
	$sql = 'SELECT article_id
			FROM ' . KB_TABLE . ' 
			WHERE article_user_id = ' . $user_id;
	$result = $db->sql_query($sql);
	while($row = $db->sql_fetchrow($result))
	{
		$article_ids[] = $row['article_id'];
	}
	$db->sql_freeresult($result);
	
	// Delete his articles
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
	
	// Set all edits by this user to guest status
	$sql = 'UPDATE ' . KB_COMMENTS_TABLE . "
			SET comment_edit_id = " . ANONYMOUS . ", comment_edit_name = '" . $user->lang['GUEST'] . "', comment_edit_color = ''
			WHERE comment_edit_id = " . $user_id;
	$db->sql_query($sql);
	
	$sql = 'UPDATE ' . KB_EDITS_TABLE . "
			SET edit_user_id = " . ANONYMOUS . ", edit_user_name = '" . $user->lang['GUEST'] . "', edit_user_color = ''
			WHERE edit_user_id = " . $user_id;
	$db->sql_query($sql);
}

/*
* Show request list
* shows list of requests so it can be used both in and outside of menus
* in menu = true|false, num = shown requests, start = start var
*/
function show_request_list($in_menu, $num, $start = 0)
{
	global $phpEx, $user, $auth;
	global $db, $phpbb_root_path, $template;
	
	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> 'r.*',
		'FROM'		=> array(
			KB_REQ_TABLE	=> 'r',
		),
		'ORDER_BY'	=> 'r.request_time DESC',
	));
	
	if($in_menu)
	{
		$result = $db->sql_query_limit($sql, $num);
	}
	else
	{
		$result = $db->sql_query('SELECT COUNT(request_id) as num_requests FROM ' . KB_REQ_TABLE);
		$requests_count = (int) $db->sql_fetchfield('num_requests', $result);
		$db->sql_freeresult($result);
		
		if ($start < 0 || $start > $requests_count)
		{
			$start = ($start < 0) ? 0 : floor(($requests_count - 1) / $num) * $num;
		}
		
		$result = $db->sql_query_limit($sql, $num, $start);
	}
	
	while($row = $db->sql_fetchrow($result))
	{
		// Walk on by, walk on through and replace naugthy words
		$row['request_title'] = censor_text($row['request_title']);
					
		// Check status and acceptance
		switch($row['request_status'])
		{
			// Has been added
			case STATUS_ADDED:
				$title = '[' . $user->lang['STATUS_ADDED'] . '] ' . $row['request_title'];
			break;
			
			// Has been accepted by someone
			case STATUS_PENDING:
				$title = '[' . $user->lang['STATUS_PENDING'] . '] ' . $row['request_title'];
			break;
			
			case STATUS_REQUEST:
			default:
				$title = '[' . $user->lang['STATUS_REQUEST'] . '] ' . $row['request_title'];
			break;
		}
		
		$template->assign_block_vars('req_list', array(
			'REQ_TITLE'			=> $title,
			'REQ_DATE'			=> ($in_menu) ? '' : $user->format_date($row['request_time']),
			'REQ_AUTHOR_FULL'	=> ($in_menu) ? '' : get_username_string('full', $row['request_user_id'], $row['request_user_name'], $row['request_user_color'], $row['request_user_name']),
			'U_VIEW_REQ'		=> append_sid("{$phpbb_root_path}kb.$phpEx", 'i=request&amp;action=view&amp;r=' . $row['request_id']),
		));	
	}
	$db->sql_freeresult($result);
	
	$template->assign_vars(array(
		'U_ADD_REQ'			=> ($auth->acl_get('u_kb_request')) ? append_sid("{$phpbb_root_path}kb.$phpEx", 'i=request&amp;action=add') : '',
		'ADD_REQ_IMG'		=> ($in_menu) ? '' : $user->img('button_request_new', 'KB_ADD_REQUEST'),
		'U_VIEW_ALL'		=> ($in_menu) ? append_sid("{$phpbb_root_path}kb.$phpEx", 'i=request&amp;action=list') : '',
		'S_SHOW_REQ_LIST'	=> ($in_menu) ? true : false,
		'S_IN_MAIN'			=> ($in_menu) ? false : true,
	));
	
	// Moved here due to complications with pagination
	if(!$in_menu)
	{
		$template->assign_vars(array(
			'PAGINATION'		=> kb_generate_pagination(append_sid("{$phpbb_root_path}kb.$phpEx", "i=request&amp;action=list"), $requests_count, $num, $start),
			'PAGE_NUMBER'		=> on_page($requests_count, $num, $start),
			'TOTAL_REQUESTS' 	=> $requests_count,
		));
	}
}
	
/*
* Generate KB Navigation Bar
*/
function generate_kb_nav($page_title = '', $data = array())
{
	global $phpEx, $user, $phpbb_root_path, $template, $config;
	
	// Knowledge Base link
	$template->assign_block_vars('navlinks', array(
		'S_IS_CAT'		=> true,
		'FORUM_NAME'	=> $config['kb_link_name'],
		'FORUM_ID'		=> 0,
		'U_VIEW_FORUM'	=> append_sid("{$phpbb_root_path}kb.$phpEx"))
	);
	
	// Parent Categories
	$cat_parents = (!empty($data)) ? kb_get_cat_parents($data) : array();
	if(!empty($cat_parents))
	{
		foreach ($cat_parents as $parent_cat_id => $parent_name)
		{
			$template->assign_block_vars('navlinks', array(
				'S_IS_CAT'		=> true,
				'FORUM_NAME'	=> $parent_name,
				'FORUM_ID'		=> $parent_cat_id,
				'U_VIEW_FORUM'	=> kb_append_sid('cat', array('id' => $parent_cat_id, 'title' => $parent_name)))
			);
		}
	}
	
	// Current Category
	if(!empty($data))
	{
		$template->assign_block_vars('navlinks', array(
			'S_IS_CAT'		=> true,
			'FORUM_NAME'	=> $data['cat_name'],
			'FORUM_ID'		=> $data['cat_id'],
			'U_VIEW_FORUM'	=> kb_append_sid('cat', array('id' => $data['cat_id'], 'title' => $data['cat_name'])))
		);
	}
	
	if($page_title == '')
	{
		return;
	}
	
	$template->assign_block_vars('navlinks', array(
		'S_IS_CAT'		=> true,
		'FORUM_NAME'	=> $page_title,
		'FORUM_ID'		=> 0,
		'U_VIEW_FORUM'	=> '', // This is for the last page, it will link to the page itself
	));
	
	return;
}

/**
* Returns cat parents as an array.
*/
function kb_get_cat_parents(&$cat_data)
{
	global $db;

	$cat_parents = array();

	if ($cat_data['parent_id'] > 0)
	{
		$sql = 'SELECT cat_id, cat_name
			FROM ' . KB_CATS_TABLE . '
			WHERE left_id < ' . $cat_data['left_id'] . '
				AND right_id > ' . $cat_data['right_id'] . '
			ORDER BY left_id ASC';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$cat_parents[$row['cat_id']] = $row['cat_name'];
		}
		$db->sql_freeresult($result);
	}

	return $cat_parents;
}

/*
* Returns children of cat id
*/
function kb_get_cat_children($cat_id)
{
	global $db;
	
	$cats = array();
	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> 'c2.*',
		'FROM'		=> array(
			KB_CATS_TABLE	=> 'c1',
		),
		'LEFT_JOIN'	=> array(
			array(
				'FROM'	=> array(KB_CATS_TABLE => 'c2'),
				'ON'	=> 'c2.left_id >= c1.left_id AND c2.left_id <= c1.right_id',
			),
		),
		'WHERE'		=> 'c1.cat_id = ' . $cat_id,
			
		'ORDER_BY'	=> 'c2.left_id ASC',
	));
	
	$result = $db->sql_query($sql);
	while($row = $db->sql_fetchrow($result))
	{
		$cats[] = $row;
	}
	$db->sql_freeresult($result);
	
	return $cats;
}

/**
* General attachment parsing
*
* @param mixed $forum_id The forum id the attachments are displayed in (false if in private message)
* @param string &$message The post/private message
* @param array &$attachments The attachments to parse for (inline) display. The attachments array will hold templated data after parsing.
* @param array &$update_count The attachment counts to be updated - will be filled
* @param bool $preview If set to true the attachments are parsed for preview. Within preview mode the comments are fetched from the given $attachments array and not fetched from the database.
*/
function kb_parse_attachments(&$message, &$attachments, &$update_count, $preview = false)
{
	if (!sizeof($attachments))
	{
		return;
	}

	global $template, $cache, $user;
	global $extensions, $config, $phpbb_root_path, $phpEx;

	//
	$compiled_attachments = array();

	if (!isset($template->filename['attachment_tpl']))
	{
		$template->set_filenames(array(
			'attachment_tpl'	=> 'attachment.html')
		);
	}

	if (empty($extensions) || !is_array($extensions))
	{
		$extensions = $cache->obtain_attach_extensions('kb');
	}

	// Look for missing attachment information...
	$attach_ids = array();
	foreach ($attachments as $pos => $attachment)
	{
		// If is_orphan is set, we need to retrieve the attachments again...
		if (!isset($attachment['extension']) && !isset($attachment['physical_filename']))
		{
			$attach_ids[(int) $attachment['attach_id']] = $pos;
		}
	}

	// Grab attachments (security precaution)
	if (sizeof($attach_ids))
	{
		global $db;

		$new_attachment_data = array();

		$sql = 'SELECT *
			FROM ' . KB_ATTACHMENTS_TABLE . '
			WHERE ' . $db->sql_in_set('attach_id', array_keys($attach_ids));
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			if (!isset($attach_ids[$row['attach_id']]))
			{
				continue;
			}

			// If we preview attachments we will set some retrieved values here
			if ($preview)
			{
				$row['attach_comment'] = $attachments[$attach_ids[$row['attach_id']]]['attach_comment'];
			}

			$new_attachment_data[$attach_ids[$row['attach_id']]] = $row;
		}
		$db->sql_freeresult($result);

		$attachments = $new_attachment_data;
		unset($new_attachment_data);
	}

	// Sort correctly
	if ($config['display_order'])
	{
		// Ascending sort
		krsort($attachments);
	}
	else
	{
		// Descending sort
		ksort($attachments);
	}

	foreach ($attachments as $attachment)
	{
		if (!sizeof($attachment))
		{
			continue;
		}

		// We need to reset/empty the _file block var, because this function might be called more than once
		$template->destroy_block_vars('_file');

		$block_array = array();

		// Some basics...
		$attachment['extension'] = strtolower(trim($attachment['extension']));
		$filename = $phpbb_root_path . $config['upload_path'] . '/' . utf8_basename($attachment['physical_filename']);
		$thumbnail_filename = $phpbb_root_path . $config['upload_path'] . '/thumb_' . utf8_basename($attachment['physical_filename']);

		$upload_icon = '';

		if (isset($extensions[$attachment['extension']]))
		{
			if ($user->img('icon_topic_attach', '') && !$extensions[$attachment['extension']]['upload_icon'])
			{
				$upload_icon = $user->img('icon_topic_attach', '');
			}
			else if ($extensions[$attachment['extension']]['upload_icon'])
			{
				$upload_icon = '<img src="' . $phpbb_root_path . $config['upload_icons_path'] . '/' . trim($extensions[$attachment['extension']]['upload_icon']) . '" alt="" />';
			}
		}

		$filesize = get_formatted_filesize($attachment['filesize'], false);

		$comment = bbcode_nl2br(censor_text($attachment['attach_comment']));

		$block_array += array(
			'UPLOAD_ICON'		=> $upload_icon,
			'FILESIZE'			=> $filesize['value'],
			'SIZE_LANG'			=> $filesize['unit'],
			'DOWNLOAD_NAME'		=> utf8_basename($attachment['real_filename']),
			'COMMENT'			=> $comment,
		);

		$denied = false;

		if (!extension_allowed(false, $attachment['extension'], $extensions))
		{
			$denied = true;

			$block_array += array(
				'S_DENIED'			=> true,
				'DENIED_MESSAGE'	=> sprintf($user->lang['EXTENSION_DISABLED_AFTER_POSTING'], $attachment['extension'])
			);
		}

		if (!$denied)
		{
			$l_downloaded_viewed = $download_link = '';
			$display_cat = $extensions[$attachment['extension']]['display_cat'];

			if ($display_cat == ATTACHMENT_CATEGORY_IMAGE)
			{
				if ($attachment['thumbnail'])
				{
					$display_cat = ATTACHMENT_CATEGORY_THUMB;
				}
				else
				{
					if ($config['img_display_inlined'])
					{
						if ($config['img_link_width'] || $config['img_link_height'])
						{
							$dimension = @getimagesize($filename);

							// If the dimensions could not be determined or the image being 0x0 we display it as a link for safety purposes
							if ($dimension === false || empty($dimension[0]) || empty($dimension[1]))
							{
								$display_cat = ATTACHMENT_CATEGORY_NONE;
							}
							else
							{
								$display_cat = ($dimension[0] <= $config['img_link_width'] && $dimension[1] <= $config['img_link_height']) ? ATTACHMENT_CATEGORY_IMAGE : ATTACHMENT_CATEGORY_NONE;
							}
						}
					}
					else
					{
						$display_cat = ATTACHMENT_CATEGORY_NONE;
					}
				}
			}

			// Make some descisions based on user options being set.
			if (($display_cat == ATTACHMENT_CATEGORY_IMAGE || $display_cat == ATTACHMENT_CATEGORY_THUMB) && !$user->optionget('viewimg'))
			{
				$display_cat = ATTACHMENT_CATEGORY_NONE;
			}

			if ($display_cat == ATTACHMENT_CATEGORY_FLASH && !$user->optionget('viewflash'))
			{
				$display_cat = ATTACHMENT_CATEGORY_NONE;
			}

			$download_link = append_sid("{$phpbb_root_path}download/file.$phpEx", 'id=' . $attachment['attach_id'] . '&amp;kb=1');

			switch ($display_cat)
			{
				// Images
				case ATTACHMENT_CATEGORY_IMAGE:
					$l_downloaded_viewed = 'VIEWED_COUNT';
					$inline_link = append_sid("{$phpbb_root_path}download/file.$phpEx", 'id=' . $attachment['attach_id'] . '&amp;kb=1');
					$download_link .= '&amp;mode=view';

					$block_array += array(
						'S_IMAGE'		=> true,
						'U_INLINE_LINK'		=> $inline_link,
					);

					$update_count[] = $attachment['attach_id'];
				break;

				// Images, but display Thumbnail
				case ATTACHMENT_CATEGORY_THUMB:
					$l_downloaded_viewed = 'VIEWED_COUNT';
					$thumbnail_link = append_sid("{$phpbb_root_path}download/file.$phpEx", 'id=' . $attachment['attach_id'] . '&amp;t=1&amp;kb=1');
					$download_link .= '&amp;mode=view';

					$block_array += array(
						'S_THUMBNAIL'		=> true,
						'THUMB_IMAGE'		=> $thumbnail_link,
					);
				break;

				// Windows Media Streams
				case ATTACHMENT_CATEGORY_WM:
					$l_downloaded_viewed = 'VIEWED_COUNT';

					// Giving the filename directly because within the wm object all variables are in local context making it impossible
					// to validate against a valid session (all params can differ)
					// $download_link = $filename;

					$block_array += array(
						'U_FORUM'		=> generate_board_url(),
						'ATTACH_ID'		=> $attachment['attach_id'],
						'S_WM_FILE'		=> true,
					);

					// Viewed/Heared File ... update the download count
					$update_count[] = $attachment['attach_id'];
				break;

				// Real Media Streams
				case ATTACHMENT_CATEGORY_RM:
				case ATTACHMENT_CATEGORY_QUICKTIME:
					$l_downloaded_viewed = 'VIEWED_COUNT';

					$block_array += array(
						'S_RM_FILE'			=> ($display_cat == ATTACHMENT_CATEGORY_RM) ? true : false,
						'S_QUICKTIME_FILE'	=> ($display_cat == ATTACHMENT_CATEGORY_QUICKTIME) ? true : false,
						'U_FORUM'			=> generate_board_url(),
						'ATTACH_ID'			=> $attachment['attach_id'],
					);

					// Viewed/Heared File ... update the download count
					$update_count[] = $attachment['attach_id'];
				break;

				// Macromedia Flash Files
				case ATTACHMENT_CATEGORY_FLASH:
					list($width, $height) = @getimagesize($filename);

					$l_downloaded_viewed = 'VIEWED_COUNT';

					$block_array += array(
						'S_FLASH_FILE'	=> true,
						'WIDTH'			=> $width,
						'HEIGHT'		=> $height,
					);

					// Viewed/Heared File ... update the download count
					$update_count[] = $attachment['attach_id'];
				break;

				default:
					$l_downloaded_viewed = 'DOWNLOAD_COUNT';

					$block_array += array(
						'S_FILE'		=> true,
					);
				break;
			}

			$l_download_count = (!isset($attachment['download_count']) || $attachment['download_count'] == 0) ? $user->lang[$l_downloaded_viewed . '_NONE'] : (($attachment['download_count'] == 1) ? sprintf($user->lang[$l_downloaded_viewed], $attachment['download_count']) : sprintf($user->lang[$l_downloaded_viewed . 'S'], $attachment['download_count']));

			$block_array += array(
				'U_DOWNLOAD_LINK'		=> $download_link,
				'L_DOWNLOAD_COUNT'		=> $l_download_count
			);
		}

		$template->assign_block_vars('_file', $block_array);

		$compiled_attachments[] = $template->assign_display('attachment_tpl');
	}

	$attachments = $compiled_attachments;
	unset($compiled_attachments);

	$tpl_size = sizeof($attachments);

	$unset_tpl = array();

	preg_match_all('#<!\-\- ia([0-9]+) \-\->(.*?)<!\-\- ia\1 \-\->#', $message, $matches, PREG_PATTERN_ORDER);

	$replace = array();
	foreach ($matches[0] as $num => $capture)
	{
		// Flip index if we are displaying the reverse way
		$index = ($config['display_order']) ? ($tpl_size-($matches[1][$num] + 1)) : $matches[1][$num];

		$replace['from'][] = $matches[0][$num];
		$replace['to'][] = (isset($attachments[$index])) ? $attachments[$index] : sprintf($user->lang['MISSING_INLINE_ATTACHMENT'], $matches[2][array_search($index, $matches[1])]);

		$unset_tpl[] = $index;
	}

	if (isset($replace['from']))
	{
		$message = str_replace($replace['from'], $replace['to'], $message);
	}

	$unset_tpl = array_unique($unset_tpl);

	// Needed to let not display the inlined attachments at the end of the post again
	foreach ($unset_tpl as $index)
	{
		unset($attachments[$index]);
	}
}

/**
* Get related articles 
* Maybe includes tags in function as should really show in article page
* Functioned instead so can use anywhere
*/
function handle_related_articles($article_id, $article_title, $article_title_clean, $show_num = 5)
{
	global $phpbb_root_path, $phpEx, $db, $template, $user;
	
	$ra_start = request_var('ra', 0);
	
	if(!$show_num)
	{
		return;
	}
	
	// Get the tags first
	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> 't.tag_name_lc',
		'FROM'		=> array(
			KB_TAGS_TABLE => 't'),

		'WHERE'		=> 't.article_id = ' . $article_id,
	));
	$result = $db->sql_query($sql);
	
	$tags = array();	
	while($row = $db->sql_fetchrow($result))
	{
		$tags[] = "'" . $db->sql_escape($row['tag_name_lc']) . "'";
	}
	$db->sql_freeresult($result);
	
	// Check what prefix we need
	$tag = '';
	$num_tags = sizeof($tags);
	if ($num_tags == 1)
	{
		$tag = 't.tag_name_lc = ' . $tags[0];
	}
	else if($num_tags > 0)
	{
		foreach($tags as $value)
		{
			if($tag == '')
			{
				$tag .= 't.tag_name_lc = ' . $value;
			}
			else
			{
				$tag .= ' OR t.tag_name_lc = ' . $value;
			}
		}
	}
	
	// Check for title relations
	$split_title = explode(' ', $article_title);
	//$split_title = array_merge($split_title, explode(' ', $article_title_clean));
	
	$words = array();
	//Remove standard words
	if (file_exists($user->lang_path . $user->lang_name . "/search_ignore_words.$phpEx"))
	{
		// include the file containing ignore words
		include($user->lang_path . $user->lang_name . "/search_ignore_words.$phpEx");
	}
	
	foreach($split_title as $term)
	{
		if (in_array($term, $words))
		{
			continue;
		}
	
		$tag .= ($tag == '') ? '' : ' OR ';
		$tag .= "(a.article_title " . $db->sql_like_expression($db->any_char . utf8_clean_string($term) . $db->any_char) . " OR a.article_title_clean " . $db->sql_like_expression($db->any_char . utf8_clean_string($term) . $db->any_char) . ")";
	}
	
	// Get the titles
	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> 'a.article_id, a.article_title',
		'FROM'		=> array(
			KB_TABLE => 'a'),
		'LEFT_JOIN'	=> array(
			array(
				'FROM' => array(KB_TAGS_TABLE => 't'),
				'ON' => 'a.article_id = t.article_id',
			),
		),
		'WHERE'		=> 'a.article_id <> ' . $article_id . '
			AND a.article_status = ' . STATUS_APPROVED . '
			AND (' . $tag . ')',	
	));
	$result = $db->sql_query($sql);
	
	$related_articles = $articles_found = array();
	$shown = $article_count = 0;
	while($row = $db->sql_fetchrow($result))
	{
		if($shown < $show_num && $shown >= $ra_start)
		{
			$related_articles[$row['article_id']] = $row['article_title'];
			$shown++;
		}
		$articles_found[] = $row['article_id'];
	}
	$db->sql_freeresult($result);
	$articles = array_unique($related_articles);
	$article_count = count(array_unique($articles_found));
	
	foreach ($articles as $article_id_ra => $article_title_ra)
	{
		$template->assign_block_vars('related_articles', array(
			'U_VIEW_ARTICLE'	=> kb_append_sid('article', array('id' => $article_id_ra, 'title' => $article_title_ra)),
			'TITLE'				=> $article_title_ra,
		));
	}
	
	// Generate Pagination
	$template->assign_vars(array(
		'KB_PAGINATION'		=> kb_generate_pagination(kb_append_sid('article', array('id' => $article_id, 'title' => $article_title)), $article_count, $show_num, $ra_start, 'ra', true),
		'KB_PAGE_NUMBER'	=> on_page($article_count, $show_num, $ra_start),
		'KB_TOTAL_RA' 		=> $article_count,
		'KB_S_TOTAL_RA'		=> ($article_count == 1) ? $user->lang['MATCH_FOUND'] : $user->lang['MATCHS_FOUND'],
	));
}

/**
* Get/Add/Delete latest articles of cat
* Using this instead of doing multiple sql queries which will increase server load, as it could be running more than 25 extra queries depending how many cats they had perpage
* Should actually work quickly but more likely to fail if a manual delete happenes from database as doesn't check topic is there
*/
function handle_latest_articles($mode, $cat_id, $data, $show)
{
	global $phpbb_root_path, $phpEx, $db;
	
	if(!$show)
	{
		return '';
	}
	
	switch ($mode)
	{
		case 'get':
			$latest_articles = unserialize($data);
			// Goes article_id => $article_title
			$article_links = array();
			
			if (!empty($latest_articles))
			{				
				$latest_articles = array_reverse($latest_articles); // Revert array so newest articles are on top
				$show = (count($latest_articles) < $show) ? count($latest_articles) : $show; // Make sure that we don't try to show more articles than there are
				for($i = 0; $i < $show; $i++)
				{
					$article = $latest_articles[$i];
					
					$info = array(
						'id'	=> $article['article_id'],
						'title'	=> $article['article_title'],
					);
					
					$article_links[] = '<a href="' . kb_append_sid('article', $info) . '">' . $article['article_title'] . '</a>';
				}
			}	
			
			return implode(', ', $article_links);
		break;
		
		case 'edit': // On edit we check if it's already there, remove it and put it to the top
			$sql = $db->sql_build_query('SELECT', array(
				'SELECT'	=> 'c.latest_ids',
				'FROM'		=> array(
					KB_CATS_TABLE => 'c'),

				'WHERE'		=> 'cat_id = ' . $cat_id,
			));
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			
			$latest_articles = unserialize($row['latest_ids']);
			foreach($latest_articles as $key => $article)
			{
				if($article['article_id'] == $data['article_id'])
				{
					unset($latest_articles[$key]);
					break;
				}
			}
			
			// No break
		
		case 'edit':
		case 'add':
			if($mode == 'add')
			{
				$sql = $db->sql_build_query('SELECT', array(
					'SELECT'	=> 'c.latest_ids',
					'FROM'		=> array(
						KB_CATS_TABLE => 'c'),
	
					'WHERE'		=> 'cat_id = ' . $cat_id,
				));
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				$latest_articles = unserialize($row['latest_ids']);
			}
			
			// Build new array
			if(count($latest_articles) == $show)
			{
				// The database table is filled.. cycle through them and add the new one at the end
				for($i = 0; $i < $show; $i++)
				{
					if(isset($latest_articles[$i]))
					{
						unset($latest_articles[$i]);
						break;
					}
				}
			}
			
			// Just add it at the end of the table
			$latest_articles[] = array(
				'article_id'	=> $data['article_id'],
				'article_title'	=> $data['article_title'],
			);
			
			$sql_ary = array('latest_ids' => serialize($latest_articles));
			$sql = 'UPDATE ' . KB_CATS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
					WHERE cat_id = ' . $cat_id;
			$db->sql_query($sql);
		break;
		
		case 'delete':
			// Call delete here only used it article id thats getting deleted is is recent articles for that cat
			$sql = $db->sql_build_query('SELECT', array(
				'SELECT'	=> 'c.latest_ids',
				'FROM'		=> array(
					KB_CATS_TABLE => 'c'),

				'WHERE'		=> 'cat_id = ' . $cat_id,
			));
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			
			$latest_articles = unserialize($row['latest_ids']);
			for($i = 0; $i < count($latest_articles); $i++)
			{
				if(isset($latest_articles[$i]) && ($data['article_id'] == $latest_articles[$i]['article_id']))
				{
					unset($latest_articles[$i]);
					continue;
				}
			}
			
			$sql_ary = array('latest_ids' => serialize($latest_articles));
			$sql = 'UPDATE ' . KB_CATS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
					WHERE cat_id = ' . $cat_id;
			$db->sql_query($sql);
		break;
	}
}

/**
* Generates feeds for display
* $feed = RSS or ATOM
* $feed_type = latest or user or cat or popular
* $feed_data (only used by user and cat) = user_id or cat_id
* Credit to EXreaction, Lithium Studios for some of the code used here and the idea for the function workings
*/
function feed_output($feed, $feed_type, $feed_data = false)
{
	global $template, $phpbb_root_path, $phpEx, $config, $db, $user;
	
	switch ($feed_type)
	{
		case 'latest':
			$title = $user->lang['KB_LATEST'];
			$sql_where = 'a.article_status = ' . STATUS_APPROVED;
			$sql_order = 'a.article_time DESC';
		break;
		
		case 'user':
			$title = $feed_data['USERNAME'] .  ' ' . $user->lang['ARTICLE'];
			$sql_where = 'a.article_user_id = ' . $feed_data['USER_ID'] . ' AND a.article_status = ' . STATUS_APPROVED;
			$sql_order = 'a.article_time DESC';
		break;
		
		case 'cat':
			$title = $user->lang['KB_SORT_CAT'] . ' - ' . $feed_data['CAT_NAME'] .  ' ' . $user->lang['ARTICLE'];
			$sql_where = 'a.cat_id = ' . $feed_data['CAT_ID'] . ' AND a.article_status = ' . STATUS_APPROVED;
			$sql_order = 'a.article_time DESC';
		break;
		
		case 'popular':
			$title = $user->lang['POPULAR_ART'];
			$sql_where = 'a.article_status = ' . STATUS_APPROVED;
			$sql_order = 'a.article_views DESC';
		break;	
	}
	
	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> 'a.article_id, a.article_title, a.article_desc, a.article_desc_uid, a.article_user_name, a.article_time',
		'FROM'		=> array(
			KB_TABLE => 'a'),		
		'WHERE'		=> "$sql_where",
		'ORDER_BY'	=> "$sql_order",
	));
	$result = $db->sql_query_limit($sql, 10);
	while ($row = $db->sql_fetchrow($result))
	{
		strip_bbcode($row['article_desc'], $row['article_desc_uid']);
		$template->assign_block_vars('item', array(
			'TITLE'				=> $row['article_title'],
			'URL'				=> generate_board_url() . '/kb.' . $phpEx . '?a=' . $row['article_id'],
			'USERNAME'			=> $row['article_user_name'],
			'MESSAGE'			=> str_replace("'", '&#039;', $row['article_desc']),
			'PUB_DATE'			=> date('r', $row['article_time']),
			'DATE_3339'			=> ($feed_type == 'ATOM') ? date3339($row['article_time']) : '',
		));
	}
	$db->sql_freeresult($result);

	$template->assign_vars(array(
		'FEED'				=> $feed,
		'SELF_FULL_URL'		=> generate_board_url() . '/kb.' . $phpEx . '?i=feed&amp;feed=' . $feed . '&amp;feed_type=' . $feed_type,
		'TITLE'				=> $config['sitename'] . ' ' . $title . ' ' . $user->lang['FEED'],
		'SITE_URL'			=> generate_board_url() . '/kb.' . $phpEx,
		'SITE_DESC'			=> $config['site_desc'],
		'SITE_LANG'			=> $config['default_lang'],
		'CURRENT_TIME'		=> ($feed_type == 'ATOM') ? date3339() : date('r'),
	));

	// Output time
	header('Content-type: application/xml; charset=UTF-8');

	header('Cache-Control: private, no-cache="set-cookie"');
	header('Expires: 0');
	header('Pragma: no-cache');

	$template->set_template();
	$template->set_filenames(array(
		'body' => 'kb/kb_feed.xml'
	));

	$template->display('body');

	garbage_collection();
	exit_handler();
}

/**
* Pagination routine, generates page number sequence
* tpl_prefix is for using different pagination blocks at one page
* for kb, change start to what you want so can be used multiple times on a page :)
*/
function kb_generate_pagination($base_url, $num_items, $per_page, $start_item, $pass = 'start', $add_prevnext_text = false, $tpl_prefix = 'KB_')
{
	global $template, $user;

	// Make sure $per_page is a valid value
	$per_page = ($per_page <= 0) ? 1 : $per_page;

	$seperator = '<span class="page-sep">' . $user->lang['COMMA_SEPARATOR'] . '</span>';
	$total_pages = ceil($num_items / $per_page);

	if ($total_pages == 1 || !$num_items)
	{
		return false;
	}

	$on_page = floor($start_item / $per_page) + 1;
	$url_delim = (strpos($base_url, '?') === false) ? '?' : '&amp;';

	$page_string = ($on_page == 1) ? '<strong>1</strong>' : '<a href="' . $base_url . '">1</a>';

	if ($total_pages > 5)
	{
		$start_cnt = min(max(1, $on_page - 4), $total_pages - 5);
		$end_cnt = max(min($total_pages, $on_page + 4), 6);

		$page_string .= ($start_cnt > 1) ? ' ... ' : $seperator;

		for ($i = $start_cnt + 1; $i < $end_cnt; $i++)
		{
			$page_string .= ($i == $on_page) ? '<strong>' . $i . '</strong>' : '<a href="' . $base_url . "{$url_delim}$pass=" . (($i - 1) * $per_page) . '">' . $i . '</a>';
			if ($i < $end_cnt - 1)
			{
				$page_string .= $seperator;
			}
		}

		$page_string .= ($end_cnt < $total_pages) ? ' ... ' : $seperator;
	}
	else
	{
		$page_string .= $seperator;

		for ($i = 2; $i < $total_pages; $i++)
		{
			$page_string .= ($i == $on_page) ? '<strong>' . $i . '</strong>' : '<a href="' . $base_url . "{$url_delim}$pass=" . (($i - 1) * $per_page) . '">' . $i . '</a>';
			if ($i < $total_pages)
			{
				$page_string .= $seperator;
			}
		}
	}

	$page_string .= ($on_page == $total_pages) ? '<strong>' . $total_pages . '</strong>' : '<a href="' . $base_url . "{$url_delim}$pass=" . (($total_pages - 1) * $per_page) . '">' . $total_pages . '</a>';

	if ($add_prevnext_text)
	{
		if ($on_page != 1)
		{
			$page_string = '<a href="' . $base_url . "{$url_delim}$pass=" . (($on_page - 2) * $per_page) . '">' . $user->lang['PREVIOUS'] . '</a>&nbsp;&nbsp;' . $page_string;
		}

		if ($on_page != $total_pages)
		{
			$page_string .= '&nbsp;&nbsp;<a href="' . $base_url . "{$url_delim}$pass=" . ($on_page * $per_page) . '">' . $user->lang['NEXT'] . '</a>';
		}
	}

	$template->assign_vars(array(
		$tpl_prefix . 'BASE_URL'		=> $base_url,
		'A_' . $tpl_prefix . 'BASE_URL'	=> addslashes($base_url),
		$tpl_prefix . 'PER_PAGE'		=> $per_page,

		$tpl_prefix . 'PREVIOUS_PAGE'	=> ($on_page == 1) ? '' : $base_url . "{$url_delim}$pass=" . (($on_page - 2) * $per_page),
		$tpl_prefix . 'NEXT_PAGE'		=> ($on_page == $total_pages) ? '' : $base_url . "{$url_delim}$pass=" . ($on_page * $per_page),
		$tpl_prefix . 'TOTAL_PAGES'		=> $total_pages,
	));

	return $page_string;
}

/**
* Display Categories
*/
function kb_display_cats($root_data = '')
{
	global $db, $auth, $user, $template;
	global $phpbb_root_path, $phpEx, $config;

	$cats = $cat = $subcats = $cat_ids = $visible_subcats = array();
	$parent_id = 0;
	$sql_where = '';
	
	if($root_data == '')
	{
		$root_data = array('cat_id' => 0);
	}
	else
	{
		$sql_where = 'c.left_id > ' . $root_data['left_id'] . ' AND c.left_id < ' . $root_data['right_id'];
	}	
	
	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> 'c.*',
		'FROM'		=> array(
			KB_CATS_TABLE => 'c'),

		'WHERE'		=> $sql_where,

		'ORDER_BY'	=> 'c.left_id',
	));

	$result = $db->sql_query($sql);
	
	while($row = $db->sql_fetchrow($result))
	{
		// Only show cats you can read
		if ($auth->acl_get('u_kb_view', $row['cat_id']))
		{
			$cat_id = $row['cat_id'];
			$cat_ids[] = $cat_id;
			
			if ($row['parent_id'] == $root_data['cat_id'])
			{
				// Direct child of current branch
				$parent_id = $cat_id;
				$cat[] = $row;
				$cats[$cat_id] = $row;
			}
			else
			{
				$cats[$parent_id]['cat_articles'] += $row['cat_articles'];
				$subcats[$parent_id][$cat_id]['name'] = $row['cat_name'];
				$subcats[$parent_id][$cat_id]['children'] = array();

				if (isset($subforums[$parent_id][$row['parent_id']]))
				{
					$subcats[$parent_id][$row['parent_id']]['children'][] = $cat_id;
				}
			}
		}
	}
	$db->sql_freeresult($result);
	
	// Begin math to calculate amount of cats per row and then create dummies to fill it out
	$cats_per_row = ($config['kb_layout_style']) ? $config['kb_cats_per_row'] : 1; // If normal style we only want 1 cat per row
	
	for ($i = 0; $i < count($cats); $i += $cats_per_row)
	{
		$template->assign_block_vars('catrow', array());
		
		for ($j = $i; $j < ($i + $cats_per_row); $j++)
		{
			if ($j >= count($cats))
			{
				// Assign dummies
				$template->assign_block_vars('catrow.dummy', array());
				continue;
			}
			
			$cat_id = $cat[$j]['cat_id'];
			$subcats_list = $s_subcats_list = array();
			$visible_subcats[$cat_id] = 0;
			
			if(isset($subcats[$cat_id]) && $config['kb_list_subcats'])
			{
				foreach($subcats[$cat_id] as $subcat_id => $subcat)
				{
					$visible_subcats[$cat_id]++;
					$subcats_list[] = array(
						'name' => $subcat['name'],
						'link' => kb_append_sid('cat', array('id' => $subcat_id, 'title' => $subcat['name'])),
					);
					$s_subcats_list[] = '<a href="' . kb_append_sid('cat', array('id' => $subcat_id, 'title' => $subcat['name'])) . '">' . $subcat['name'] . '</a>';
				}
				$s_subcats_list = implode(', ', $s_subcats_list);
			}
			
			$folder_img = (sizeof($subcats_list)) ? 'forum_read_subforum' : 'forum_read';
			
			$latest_articles = handle_latest_articles('get', $cat_id, $cats[$cat_id]['latest_ids'], $config['kb_latest_articles_c']);
			
			$template->assign_block_vars('catrow.cat', array(
				'CAT_ID'				=> $cats[$cat_id]['cat_id'],
				'CAT_NAME'				=> $cats[$cat_id]['cat_name'],
				'CAT_DESC'				=> generate_text_for_display($cats[$cat_id]['cat_desc'], $cats[$cat_id]['cat_desc_uid'], $cats[$cat_id]['cat_desc_bitfield'], $cats[$cat_id]['cat_desc_options']),
				'ARTICLES'				=> $cats[$cat_id]['cat_articles'],
				'CAT_FOLDER_IMG'		=> $user->img($folder_img, $cats[$cat_id]['cat_name']),
				'CAT_FOLDER_IMG_SRC'	=> $user->img($folder_img, $cats[$cat_id]['cat_name'], false, '', 'src'),
				'CAT_FOLDER_IMG_ALT'	=> $cats[$cat_id]['cat_name'],
				'CAT_IMAGE'				=> ($cats[$cat_id]['cat_image']) ? '<img src="' . $phpbb_root_path . $cats[$cat_id]['cat_image'] . '" alt="' . $user->lang['KB_CATS'] . '" />' : '',
				'CAT_IMAGE_SRC'			=> ($cats[$cat_id]['cat_image']) ? $phpbb_root_path . $cats[$cat_id]['cat_image'] : '',
				'SUBCATS'				=> $s_subcats_list,
				'LATEST_ARTICLE'		=> $latest_articles,

				'L_SUBCAT'				=> ($visible_subcats[$cat_id] == 1) ? $user->lang['SUBCAT'] : $user->lang['SUBCATS'],
				'U_VIEWCAT'				=> kb_append_sid('cat', array('id' => $cats[$cat_id]['cat_id'], 'title' => $cats[$cat_id]['cat_name'])),
				'U_RSS_CAT'				=> append_sid("{$phpbb_root_path}kb.$phpEx", 'i=feed&amp;feed_type=cat&amp;feed_cat_id=' . $cats[$cat_id]['cat_id'] . '&amp;feed_cat_name=' . $cats[$cat_id]['cat_name']),
			));		
		}
	}	
	return;
}

// Generate edit string, used so many places i found it better to put it here
function gen_kb_edit_string($article_id, $last_edit_id, $article_time, $edit_time, $reason = '', $reason_global = false)
{
	global $db, $user, $phpbb_root_path, $phpEx;
	
	if($last_edit_id && $edit_time != $article_time)
	{
		$sql = "SELECT e.edit_id, e.edit_user_id, e.edit_user_name, e.edit_user_color
				FROM " . KB_EDITS_TABLE . " e
				WHERE e.edit_id = $last_edit_id";
		$result = $db->sql_query($sql);
		$edit_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		$l_edit = sprintf($user->lang['KB_EDITED_BY'], '<a href="' . append_sid("{$phpbb_root_path}kb.$phpEx", "i=history&amp;e=$article_id") . '">', '</a>', get_username_string('full', $edit_data['edit_user_id'], $edit_data['edit_user_name'], $edit_data['edit_user_color']), $user->format_date($edit_time, false, true));
		
		if($reason != '' && $reason_global)
		{
			$l_edit .= '<br />' . $user->lang['REASON'] . ': ' . $reason;
		}
		return $l_edit;
	}
	
	return '';
}

// Make select list for posting article
function make_req_select()
{
	global $db, $user;
	
	$sql = 'SELECT request_id, request_title
			FROM ' . KB_REQ_TABLE . '
			WHERE request_accepted = ' . $user->data['user_id'] . '
			AND request_status = ' . STATUS_PENDING . '
			ORDER BY request_title DESC';
	$result = $db->sql_query($sql);
	
	$options = '';
	while($row = $db->sql_fetchrow($result))
	{
		$options .= '<option value="' . $row['request_id'] . '">' . $row['request_title'] . '</option>';
	}
	$db->sql_freeresult($result);
	
	if($options == '')
	{
		return '';
	}
	
	return '<option value="0" selected="selected">' . $user->lang['KB_NOT_ADD_REQUEST'] . '</option>' . $options;
}

// Make article type list
function make_article_type_select($type_id = 0)
{
	global $user, $cache;
	
	$options = '<option value="0"' . ((!$type_id) ? ' selected="selected"' : '') . '>' . $user->lang['SELECT_TYPE'] . '</option>';
	$types = $cache->obtain_article_types();
	
	foreach($types as $id => $type_data)
	{
		$selected = ($type_id == $id) ? ' selected="selected"' : '';
		$options .= '<option value="' . $id . '"' . $selected . '>' . $type_data['title'] . '</option>';
	}
	
	return $options;
}	

//Make cat list
function make_cat_select($select_id = false, $ignore_id = false, $in_acp = false, $return_array = false)
{
	global $db, $user, $auth;

	// This query is identical to the jumpbox one
	$sql = 'SELECT cat_id, cat_name, cat_desc, parent_id, left_id, right_id
		FROM ' . KB_CATS_TABLE . '
		ORDER BY left_id ASC';
	$result = $db->sql_query($sql);

	$right = 0;
	$padding_store = array('0' => '');
	$padding = '';
	$cat_list = ($return_array) ? array() : '';

	// Sometimes it could happen that forums will be displayed here not be displayed within the index page
	// This is the result of forums not displayed at index, having list permissions and a parent of a forum with no permissions.
	// If this happens, the padding could be "broken"
	while ($row = $db->sql_fetchrow($result))
	{
		if (!$auth->acl_get('u_kb_view', $row['cat_id']) && !$in_acp)
		{
			// if no permission, skip branch
			$right = $row['right_id'];
			continue;
		}
		
		if ($row['left_id'] < $right)
		{
			$padding .= '&nbsp; &nbsp;';
			$padding_store[$row['parent_id']] = $padding;
		}
		else if ($row['left_id'] > $right + 1)
		{
			$padding = (isset($padding_store[$row['parent_id']])) ? $padding_store[$row['parent_id']] : '';
		}

		$right = $row['right_id'];
		$disabled = false;

		if (((is_array($ignore_id) && in_array($row['cat_id'], $ignore_id)) || $row['cat_id'] == $ignore_id))
		{
			$disabled = true;
		}

		if ($return_array)
		{
			// Include some more information...
			$selected = (is_array($select_id)) ? ((in_array($row['cat_id'], $select_id)) ? true : false) : (($row['cat_id'] == $select_id) ? true : false);
			$cat_list[$row['cat_id']] = array_merge(array('padding' => $padding, 'selected' => ($selected && !$disabled), 'disabled' => $disabled), $row);
		}
		else
		{
			$selected = (is_array($select_id)) ? ((in_array($row['cat_id'], $select_id)) ? ' selected="selected"' : '') : (($row['cat_id'] == $select_id) ? ' selected="selected"' : '');
			$cat_list .= '<option value="' . $row['cat_id'] . '"' . (($disabled) ? ' disabled="disabled" class="disabled-option"' : $selected) . '>' . $padding . $row['cat_name'] . '</option>';
		}
	}
	$db->sql_freeresult($result);
	unset($padding_store);

	return $cat_list;
}

// Returns array with select options for date, month, year, hours and minutes.
function make_time_selects($current_time)
{
	global $user;
	
	// Set up a helping month array for the lang files
	$month_names = array(
		1	=> 'January',
		2	=> 'February',
		3	=> 'March',
		4	=> 'April',
		5	=> 'May',
		6	=> 'June',
		7	=> 'July',
		8	=> 'August',
		9 	=> 'September',
		10	=> 'October',
		11	=> 'November',
		12	=> 'December',
	);
	
	// Convert current timestamp to a date and split it up so it will become, date:month:year:hour:min
	$date = date('j:n:Y:G:i', $current_time);
	$date = explode(':', $date);
	
	$month_options = "";
	for($i = 1; $i <= 12; $i++)
	{
		$selected = ($date[1] == $i) ? ' selected="selected"' : '';
		$month_options .= '<option value="' . $i . '"' . $selected . '>' . $user->lang['datetime'][$month_names[$i]] . '</option>';
	}
	
	$day_options = "";
	for($i = 1; $i <= 31; $i++)
	{
		$selected = ($date[0] == $i) ? ' selected="selected"' : '';
		$day_options .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
	}
	
	$year_options = "";
	for($i = $date[2] - 10; $i < ($date[2] + 10); $i++)
	{
		$selected = ($date[2] == $i) ? ' selected="selected"' : '';
		$year_options .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
	}
	
	$hour_options = "";
	for($i = 0; $i < 24; $i++)
	{
		$o = "";
		if($i < 10 )
		{
			$o = "0";
		}
		$selected = ($date[3] == $i) ? ' selected="selected"' : '';
		$hour_options .= '<option value="' . $i . '"' . $selected . '>' . $o . $i . '</option>';
	}
	
	$min_options = "";
	for($i = 0; $i < 60; $i++)
	{
		$o = "";
		if($i < 10)
		{
			$o = "0";
		}
		$minute = $o . $i;
		$selected = ($date[4] == $minute) ? ' selected="selected"' : '';
		$min_options .= '<option value="' . $i . '"' . $selected . '>' . $minute . '</option>';
	}
	
	return array(
		'day'	=> $day_options,
		'month' => $month_options,
		'year'	=> $year_options,
		'hour'	=> $hour_options,
		'min'	=> $min_options,
	);
}

// Insert article type into article data
// Mode can be both|img|title
function gen_article_type($article_type, $article_title, $types, $icons)
{
	global $phpbb_root_path;
	
	// Article type standard action
	$type_info = array(
		'article_title'		=> $article_title,
		'type_image'		=> array(
			'img'		=> '',
			'width'		=> 0,
			'height'	=> 0,
		),
	);
	
	if($article_type == STATUS_UNREVIEW || !isset($types[$article_type]))
	{
		return $type_info;
	}
	
	$type_data = $types[$article_type];
	$prefix = ($type_data['prefix'] == '') ? '' : $type_data['prefix'] . ' ';
	$suffix = ($type_data['suffix'] == '') ? '' : ' ' . $type_data['suffix'];
	$article_title = $prefix . $article_title . $suffix;

	$article_image = array(
		'img'		=> (!isset($type_data['img']) || $type_data['img'] == '') ? ((isset($icons[$type_data['icon']])) ? $phpbb_root_path . 'images/icons/' . $icons[$type_data['icon']]['img'] : '') : $phpbb_root_path . $type_data['img'],
		'width'		=> (!isset($type_data['img']) || $type_data['img'] == '') ? ((isset($icons[$type_data['icon']])) ? $icons[$type_data['icon']]['width'] : 0) : $type_data['width'],
		'height'	=> (!isset($type_data['img']) || $type_data['img'] == '') ? ((isset($icons[$type_data['icon']])) ? $icons[$type_data['icon']]['height'] : 0) : $type_data['height'],
	);
	
	return array(
		'type_image'		=> $article_image,
		'article_title'		=> $article_title,
	);
}

// Show what user can do, $cat_id not used at the moment as nothing is local!
function gen_kb_auth_level($cat_id = false)
{
	global $template, $auth, $user, $config;

	$rules = array(
		($auth->acl_get('u_kb_add', $cat_id)) ? $user->lang['KB_PERM_CAN_ADD'] : $user->lang['KB_PERM_CANNOT_ADD'],
		($auth->acl_get('u_kb_comment', $cat_id)) ? $user->lang['KB_PERM_CAN_COM'] : $user->lang['KB_PERM_CANNOT_COM'],
		($user->data['is_registered'] && $auth->acl_gets(array('u_kb_edit', 'm_kb_edit'), $cat_id)) ? $user->lang['KB_PERM_CAN_EDIT'] : $user->lang['KB_PERM_CANNOT_EDIT'],
		($user->data['is_registered'] && $auth->acl_gets(array('u_kb_delete', 'm_kb_delete'), $cat_id)) ? $user->lang['KB_PERM_CAN_DEL'] : $user->lang['KB_PERM_CANNOT_DEL'],
		($auth->acl_get('u_kb_rate', $cat_id)) ? $user->lang['KB_PERM_CAN_RATE'] : $user->lang['KB_PERM_CANNOT_RATE'],
		($auth->acl_get('u_kb_viewhistory', $cat_id)) ? $user->lang['KB_PERM_CAN_HIST'] : $user->lang['KB_PERM_CANNOT_HIST'],
	);

	if ($config['kb_allow_attachments'])
	{
		$rules[] = ($auth->acl_get('u_kb_attach', $cat_id)) ? $user->lang['KB_PERM_CAN_ATTACH'] : $user->lang['KB_PERM_CANNOT_ATTACH'];
	}
	
	$rules[] = ($auth->acl_get('u_kb_download', $cat_id)) ? $user->lang['KB_PERM_CAN_DOWN'] : $user->lang['KB_PERM_CANNOT_DOWN'];

	foreach ($rules as $rule)
	{
		$template->assign_block_vars('rules', array('RULE' => $rule));
	}

	return;
}

// Gets ids of the cats the user can read
// Sets it in $user->data to cache the function
function get_readable_cats()
{
	global $db, $user, $auth;
	
	if(!isset($user->data['kb_readable_cats']))
	{
		$sql = 'SELECT cat_id
				FROM ' . KB_CATS_TABLE . '
				ORDER BY cat_id ASC';
		$result = $db->sql_query($sql);
		
		$allowed_cats = array();
		while($row = $db->sql_fetchrow($result))
		{
			if($auth->acl_get('u_kb_view', $row['cat_id']))
			{
				$allowed_cats[] = $row['cat_id'];
			}
		}
		$db->sql_freeresult($result);
		
		$user->data['kb_readable_cats'] = $allowed_cats;
	}	
	
	if (empty($user->data['kb_readable_cats']))
	{
		$user->data['kb_readable_cats'] = array(0);
	}
	
	return $user->data['kb_readable_cats'];
}

/**
* Replaces message_parser lang vars with KB's own
*/
function fix_error_vars($mode, $error)
{
	global $user;
	
	// needle => replacement
	switch($mode)
	{
		case 'article':
			$replacement = array(
				$user->lang['TOO_FEW_CHARS'] 			=> $user->lang['KB_TOO_FEW_CHARS_ARTICLE'],
				$user->lang['TOO_MANY_CHARS_POST'] 		=> $user->lang['KB_TOO_MANY_CHARS_ARTICLE'],
			);
		break;
		
		case 'desc':
			$replacement = array(
				$user->lang['TOO_FEW_CHARS'] 			=> $user->lang['KB_TOO_FEW_CHARS_DESC'],
				$user->lang['TOO_MANY_CHARS_POST'] 		=> $user->lang['KB_TOO_MANY_CHARS_DESC'],
			);
		break;
		
		case 'comment':
			$replacement = array(
				$user->lang['TOO_FEW_CHARS'] 			=> $user->lang['KB_TOO_FEW_CHARS_COMMENT'],
				$user->lang['TOO_MANY_CHARS_POST'] 		=> $user->lang['KB_TOO_MANY_CHARS_COMMENT'],
			);
		break;
		
		case 'request':
			$replacement = array(
				$user->lang['TOO_FEW_CHARS'] 			=> $user->lang['KB_TOO_FEW_CHARS_REQUEST'],
				$user->lang['TOO_MANY_CHARS_POST'] 		=> $user->lang['KB_TOO_MANY_CHARS_REQUEST'],
			);
		break;
	}
	
	foreach($replacement as $needle => $new_str)
	{
		$error = str_replace($needle, $new_str, $error);
	}
	
	return $error;
}

function export_data($type = 'word', $article_id)
{
	global $db, $phpbb_root_path, $user;
	
	if(!$article_id)
	{
		trigger_error('KB_NO_ARTICLE');
	}
	
	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> 'a.*',
		'FROM'		=> array(
			KB_TABLE => 'a'
		),
		'WHERE'		=> 'a.article_id = ' . $article_id,
	));
	
	$result = $db->sql_query($sql);
	$article_data = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
		
	if(!$article_data)
	{
		trigger_error('KB_NO_ARTICLE');
	}
	
	$text = generate_text_for_display($article_data['article_text'], $article_data['bbcode_uid'], $article_data['bbcode_bitfield'], 7);
	$desc = generate_text_for_display($article_data['article_desc'], $article_data['article_desc_uid'], $article_data['article_desc_bitfield'], 7);
	
	$output = '';
	
	switch ($type)
	{
		case 'word':
			$output .= "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\"";
			$output .= "   xmlns:w=\"urn:schemas-microsoft-com:office:word\"";
			$output .= "   xmlns=\"http://www.w3.org/TR/REC-html40\">";
			$output .= "<head>
							<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
							<meta name=\"ProgId\" content=\"Word.Document\">
							<style>
							@page Section1
							{
								mso-page-orientation: portrait;
								margin: 3cm 2.5cm 3cm 2.5cm;
								mso-header-margin: 36pt;
								mso-footer-margin: 36pt;
								mso-paper-source: 0;
							}
							</style>
						</head>
						<body>
						<div class=\"Section1\">";
			$output .= "<span style=\"font-weight: bold; text-align: center; font-size: 150%; \">" . $article_data['article_title'] . "</span><br /><br />";
			$output .= "<span style=\"font-weight: bold; \">" . $user->lang['ARTICLE_ID'] . ": </span>" . $article_data['article_id'] . "<br />";
			$output .= "<span style=\"font-weight: bold; \">" . $user->lang['WRITTEN_BY'] . ": </span>" . $article_data['article_user_name'] . "<br />";
			$output .= "<span style=\"font-weight: bold; \">" . $user->lang['WRITTEN_ON'] . ": </span>" . $user->format_date($article_data['article_time']) . "<br />";
			$output .= "<span style=\"font-weight: bold; \">" . $user->lang['DESCRIPTION'] . ": </span>" . $desc . "<hr /><br />";
			$output .= "<span style=\"font-weight: bold; \">" . $user->lang['ARTICLE_CONTENT'] . "</span><br />";
			$output .= $text;
			$output .= "</div></body></html>";
			
			//Set some formal stuff
			$extension = '.doc';
		break;
		
		// not working need to find a new way of doing this or just leave it as word?
		/*
		case 'pdf':
			$output .= "<html>";
			$output .= "<head>
							<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
							<meta name=\"ProgId\" content=\"Pdf.Document\">
							<style>
							@page Section1
							{
								margin: 3cm 2.5cm 3cm 2.5cm;
							}
							</style>
						</head>
						<body>
						<div class=\"Section1\">";
			$output .= "<span style=\"font-weight: bold; text-align: center; font-size: 150%; \">" . $article_data['article_title'] . "</span><br /><br />";
			$output .= "<span style=\"font-weight: bold; \">" . $user->lang['ARTICLE_ID'] . ": </span>" . $article_data['article_id'] . "<br />";
			$output .= "<span style=\"font-weight: bold; \">" . $user->lang['WRITTEN_BY'] . ": </span>" . $article_data['article_user_name'] . "<br />";
			$output .= "<span style=\"font-weight: bold; \">" . $user->lang['WRITTEN_ON'] . ": </span>" . $user->format_date($article_data['article_time']) . "<br />";
			$output .= "<span style=\"font-weight: bold; \">" . $user->lang['DESCRIPTION'] . ": </span>" . $desc . "<hr /><br />";
			$output .= "<span style=\"font-weight: bold; \">" . $user->lang['ARTICLE_CONTENT'] . "</span><br />";
			$output .= $text;
			$output .= "</div></body></html>";
			
			//Set some formal stuff
			$extension = '.pdf';
		break;
		*/
	}
	
	$save_as = str_replace(' ', '_', $article_data['article_title']);
	$location = $phpbb_root_path . 'store/' . $save_as . $extension;
	
	$fp = fopen($location, 'w+'); 
	fwrite($fp, $output);
    fclose($fp);
	
	set_download($save_as, $location, $extension);
	
	unlink($location);
}

function set_download($filename, $location, $extension)
{
	header("Content-disposition: attachment; filename=$filename" . $extension);
	header('Content-type: application/vnd.$extension');
	readfile($location);
}

function make_sort_select($mode, $default)
{
	global $user;
	
	// Sort options
	if($mode == 'articles')
	{
		$s_sort_options = array(
			'time'		=> $user->lang['KB_SORT_ATIME'],
			'etime'		=> $user->lang['KB_SORT_ETIME'],
			'title'		=> $user->lang['KB_SORT_TITLE'],
			'author'	=> $user->lang['KB_SORT_AUTHOR'],
			'views'		=> $user->lang['KB_SORT_VIEWS'],
			'rating'	=> $user->lang['KB_SORT_RATING'],
		);
	}
	else if($mode == 'comments')
	{
		$s_sort_options = array(
			'etime'		=> $user->lang['KB_SORT_ETIME'],
			'name'		=> $user->lang['KB_SORT_CTITLE'],
			'author'	=> $user->lang['KB_SORT_CAUTHOR'],
			'time'		=> $user->lang['KB_SORT_CTIME'],
		);
	}
	
	$select = '<select name="sort" id="sort">';
	foreach($s_sort_options as $value => $name)
	{
		$selected = ($value == $default) ? ' selected="selected"' : '';
		$select .= '<option value="' . $value . '"' . $selected . '>' . $name . '</option>';
		
	}
	$select .= '</select>';
	
	return $select;
}

function make_direction_select($direction)
{
	global $user;
	
	$s_dir_options = array(
		'DESC'		=> $user->lang['KB_SORT_DESCENDING'],
		'ASC'		=> $user->lang['KB_SORT_ASCENDING'],
	);
	
	$select = '<select name="dir" id="dir">';
	foreach($s_dir_options as $value => $name)
	{
		$selected = ($value == $direction) ? ' selected="selected"' : '';
		$select .= '<option value="' . $value . '"' . $selected . '>' . $name . '</option>';
		
	}
	$select .= '</select>';
	
	return $select;
}

// Titania function to show rating stars, slightly tweaked
function get_rating_stars($article_id, $cat_id, $has_rated, $can_rate, $rating, $votes)
{
	global $auth, $user, $template, $config;
	global $phpbb_root_path, $phpEx;
	
	//$rate_url = titania_url::build_url('rate', array('type' => $this->rating_type, 'id' => $this->rating_object_id));
	$rate_url = append_sid($phpbb_root_path . "kb.$phpEx", "i=rate&a=$article_id");
	
	// If it has not had any ratings yet, give it 1/2 the max for the rating
	if ($votes == 0)
	{
		// Change default rating
		$rating = 3;
	}

	$template->set_filenames(array(
		'rate'	=> 'kb/rate.html',
	));

	$theme_path = "{$phpbb_root_path}styles/" . $user->theme['theme_path'] . '/theme';
	$template->assign_vars(array(
		'ARTICLE_ID'			=> $article_id,
		'ARTICLE_RATING'		=> $rating,

		'RATE_URL'				=> $rate_url,

		'S_HAS_RATED'			=> ($has_rated > 0) ? true : false,
		'S_CAN_RATE'			=> $can_rate,
		'S_AJAX'				=> $config['kb_ajax_rating'],

		'UA_GREY_STAR_SRC'		=> $theme_path . '/images/star_grey.gif',
		'UA_GREEN_STAR_SRC'		=> $theme_path . '/images/star_green.gif',
		'UA_RED_STAR_SRC'		=> $theme_path . '/images/star_red.gif',
		'UA_ORANGE_STAR_SRC'	=> $theme_path . '/images/star_orange.gif',
		'UA_REMOVE_STAR_SRC'	=> $theme_path . '/images/star_remove.gif',
		'UA_MAX_RATING'			=> 6, // Not configurable as of yet
	));

	// reset the stars block
	$template->destroy_block_vars('stars');
	$template->destroy_block_vars('reset_stars');
	
	for ($i = 1; $i <= 6; $i++)
	{
		$user_rating = (!$can_rate) ? $rating : (($has_rated) ? $has_rated : $i);
		$template->assign_block_vars('stars', array(
			'ALT'		=> $user_rating . '/' . 6,
			'ID'		=> $i,
			'RATE_URL'	=> ($config['kb_ajax_rating']) ? 'javascript:rateArticle(' . $i . ')' : $rate_url . '&amp;rating=' . $i,
		));
		
		$template->assign_block_vars('reset_stars', array(
			'RATING'	=> $i,
			'JS_VAR'	=> ($rating >= $i) ? 'orange_star.src' : 'grey_star.src',
		));
	}

	return $template->assign_display('rate', '', true);
}

/*
KB Fetch All Function
Description: This function is meant to be a help for developers to easily retrieve info from the KB. It is dependant that the file already
has included and initialized normal phpbb files. 
More information on how to do this can be found here: http://www.phpbb.com/kb/article/add-a-new-custom-page-to-phpbb/
The function syntax is:
kb_fetch_all($mode, $submode, $data = array())
The $data & $submode are dependant on the $mode, which can be specified as one of the following
- article
	- Available submodes:
		list - Retrieves a list of articles
			data - 
- cat or category
- tag
- request

function kb_fetch_all($mode, $data = array())
{
	
}
*/
?>