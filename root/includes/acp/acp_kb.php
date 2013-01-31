<?php
/**
*
* @package phpBB phpBB3-Knowledgebase Mod (KB)
* @version $Id: acp_kb.php $
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

define('IN_KB_PLUGIN', true);

/**
* @package acp
*/
class acp_kb
{
	var $u_action;
	var $new_config = array();

	function main($id, $mode)
	{
		global $user, $template, $config, $phpbb_root_path, $phpEx, $table_prefix, $db, $cache, $phpbb_admin_path;

		$user->add_lang('mods/kb');
		include($phpbb_root_path . 'includes/constants_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/functions_kb.' . $phpEx);
		include($phpbb_root_path . 'includes/functions_plugins_kb.' . $phpEx);

		$action	= request_var('action', '');
		$submit = (isset($_POST['submit'])) ? true : false;
		$error = array();

		$form_key = 'acp_kb';
		add_form_key($form_key);

		// Set templates
		switch ($mode)
		{
			case 'settings':
				$this->tpl_name = 'acp_kb';
				$this->page_title = 'ACP_KB_' . strtoupper($mode);
				$config_show = true;
			break;

			case 'plugins':
				$this->tpl_name = 'acp_kb_plugins';
				$this->page_title = 'ACP_KB_' . strtoupper($mode);
				$config_show = false;
			break;

			case 'health_check':
				$this->tpl_name = 'acp_kb_health';
				$this->page_title = 'ACP_KB_HEALTH_CHECK';
				$config_show = false;
			break;
		}

		/**
		*	Validation types are:
		*		string, int, bool,
		*		script_path (absolute path in url - beginning with / and no trailing slash),
		*		rpath (relative), rwpath (realtive, writable), path (relative path, but able to escape the root), wpath (writable)
		*/
		switch ($mode)
		{
			case 'settings':
				$display_vars = array(
					'title'	=> 'ACP_KB_SETTINGS',
					'vars'	=> array(
						'legend1'				=> 'ACP_KB_SETTINGS',
						'kb_enable'				=> array('lang' => 'KB_ENABLE',			'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),
						//Items for kb.pertneer.net Site
						//'kb_git_link'			=> array('lang' => 'KB_ENABLE_GIT_LINK',	'validate' => 'int',	'type' => 'radio:yes_no', 	'explain' => true),
						//End here for kb.pertneer.net Site
						'kb_link_name'			=> array('lang' => 'KB_LINK_NAME',		'validate' => 'string',	'type' => 'text:40:50', 	'explain' => true),
						'kb_header_name'		=> array('lang' => 'KB_HEADER_NAME',	'validate' => 'string',	'type' => 'text:40:50', 	'explain' => true),
						'kb_header_desc'		=> array('lang' => 'KB_HEADER_DESC',	'validate' => 'string',	'type' => 'textarea:5:40', 	'explain' => true),
						'kb_copyright'			=> array('lang' => 'KB_PER_COPYRIGHT',	'validate' => 'string',	'type' => 'text:40:50', 	'explain' => true),
						'kb_copyright_symbol'	=> array('lang' => 'KB_COPYRIGHT_SYMBOL',	'validate' => 'bool',	'type' => 'radio:yes_no',	'explain' => true),
						'kb_default_rating'		=> array('lang' => 'KB_DEFAULT_RATING',	'validate' => 'int',	'type' => 'select', 'method' => 'select_default_rating', 'explain' => false),
						'kb_articles_per_page'	=> array('lang' => 'KB_ART_PER_PAGE',	'validate' => 'int',	'type' => 'text:3:5', 		'explain' => false),
						'kb_comments_per_page'	=> array('lang' => 'KB_COM_PER_PAGE',	'validate' => 'int',	'type' => 'text:3:5', 		'explain' => false),
						'kb_mod_notify'			=> array('lang' => 'KB_MOD_NOTIFY_ENABLE',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => true),
						'kb_seo'				=> array('lang' => 'KB_SEO_ENABLE',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => true),
						'kb_ajax_rating'		=> array('lang' => 'KB_AJAX_RATING',	'validate' => 'bool',	'type' => 'radio:yes_no',	'explain' => true),
						'kb_ext_article_header' => array('lang' => 'KB_EXT_HEADER',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => true),
						'kb_show_desc_cat'		=> array('lang' => 'KB_DESC_CAT',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),
						'kb_show_desc_article'	=> array('lang' => 'KB_DESC_ART',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),
						'kb_cats_per_row'		=> array('lang' => 'KB_CATS_PER_ROW',	'validate' => 'int',	'type' => 'text:3:5', 		'explain' => true),
						'kb_layout_style'		=> array('lang' => 'KB_LAYOUT_STYLE',	'validate' => 'int',	'type' => 'select',	'method' => 'select_style_layout', 'explain' => true),
						'kb_list_subcats'		=> array('lang' => 'KB_LIST_SUBCATS',	'validate' => 'int',	'type' => 'radio:yes_no', 	'explain' => false),
						'kb_latest_articles_c'	=> array('lang' => 'KB_ACP_LATEST_ART',	'validate' => 'int',	'type' => 'text:3:5', 		'explain' => true),

						'legend2'				=> 'ACP_KB_FEED_SETTINGS',
						'kb_feed_enable'		=> array('lang' => 'KB_FEED_ENABLE',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),

						'legend3'				=> 'ACP_KB_MENU_SETTINGS',
						'kb_disable_left_menu'	=> array('lang' => 'DISABLE_LEFT_MENU',		'validate' => 'bool',	'type' => 'radio:yes_no',	'explain' => true),
						'kb_left_menu_width'	=> array('lang' => 'KB_LEFT_MENU_WIDTH',	'validate' => 'int',	'type' => 'text:3:5',		'explain' => true),
						'kb_left_menu_type'		=> array('lang' => 'KB_LEFT_MENU_TYPE',		'validate' => 'int',	'type' => 'select',	'method' => 'select_menu_type', 'explain' => false),
						'kb_disable_right_menu'	=> array('lang' => 'DISABLE_RIGHT_MENU', 	'validate' => 'bool',	'type' => 'radio:yes_no',	'explain' => true),
						'kb_right_menu_width'	=> array('lang' => 'KB_RIGHT_MENU_WIDTH',	'validate' => 'int',	'type' => 'text:3:5',		'explain' => true),
						'kb_right_menu_type'	=> array('lang' => 'KB_RIGHT_MENU_TYPE',	'validate' => 'int',	'type' => 'select',	'method' => 'select_menu_type', 'explain' => false),

						'legend4'				=> 'ACP_KB_POST_SETTINGS',
						'kb_allow_attachments'	=> array('lang'	=> 'KB_ALLOW_ATTACH',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),
						'kb_allow_sig'			=> array('lang' => 'KB_ALLOW_SIG',		'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),
						'kb_allow_bbcode'		=> array('lang'	=> 'KB_ALLOW_BBCODE',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),
						'kb_allow_smilies'		=> array('lang' => 'KB_ALLOW_SMILES',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),
						'kb_allow_post_flash'	=> array('lang' => 'KB_ALLOW_FLASH',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),
						'kb_allow_post_links'	=> array('lang' => 'KB_ALLOW_LINKS',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),
						'kb_desc_min_chars'		=> array('lang' => 'KB_DESC_MIN_CHARS', 'validate' => 'int',	'type' => 'text:3:5',		'explain' => true),
						'kb_desc_max_chars'		=> array('lang' => 'KB_DESC_MAX_CHARS', 'validate' => 'int',	'type' => 'text:3:5',		'explain' => true),
						'kb_disable_desc'		=> array('lang' => 'KB_DISABLE_DESC',	'validate' => 'bool',	'type' => 'radio:yes_no', 	'explain' => false),
					)
				);
			break;

			case 'plugins':
				$filename	= request_var('filename', '');
				$plugin_loc = $phpbb_root_path . 'includes/kb_plugins/';

				switch ($action)
				{
					case 'install':
						if(confirm_box(true))
						{
							// Lets install the mod
							install_plugin($filename, $plugin_loc, $this->u_action);

							trigger_error($user->lang['PLUGIN_INSTALLED'] . adm_back_link($this->u_action));
						}
						else
						{
							$hidden_fields = build_hidden_fields(array(
								'action'	=> 'install',
								'filename'	=> $filename,
							));

							confirm_box(false, 'INSTALL_PLUGIN', $hidden_fields);
						}
					break;

					case 'uninstall':
						if (!file_exists($plugin_loc . 'kb_' . $filename . '.' . $phpEx))
						{
							trigger_error($user->lang['NO_PLUGIN_FILE'] . adm_back_link($u_action), E_USER_ERROR);
						}

						include($plugin_loc . 'kb_' . $filename . '.' . $phpEx);

						$continue = (empty($details['PLUGIN_PERM'])) ? false : $details['PLUGIN_PERM'];

						if(confirm_box(true))
						{
							// Uninstall the plugin
							uninstall_plugin($filename, $plugin_loc, $this->u_action);

							trigger_error($user->lang['PLUGIN_UNINSTALLED'] . adm_back_link($this->u_action));
						}
						else
						{
							if (!$continue)
							{
								$hidden_fields = build_hidden_fields(array(
									'action'	=> 'uninstall',
									'filename'	=> $filename,
								));

								confirm_box(false, 'UNINSTALL_PLUGIN', $hidden_fields);
							}
							else
							{
								trigger_error($user->lang['KB_NO_UNINSTALL'] . adm_back_link($this->u_action), E_USER_WARNING);
							}
						}
					break;

					case 'settings':
						if (!file_exists($plugin_loc . 'kb_' . $filename . '.' . $phpEx))
						{
							trigger_error($user->lang['NO_PLUGIN_FILE'] . adm_back_link($this->u_action));
						}

						include($plugin_loc . 'kb_' . $filename . '.' . $phpEx);

						if (version_compare($details['PLUGIN_VERSION'], $config['kb_' . $filename . '_version'], '>'))
						{
							// Uninstall the plugin
							update_plugin($filename, $plugin_loc, $this->u_action, $details);

							$template->assign_vars(array(
								'S_SUCCESS'			=> true,
								'SUCCESS_MSG'		=> $user->lang['PLUGIN_UPDATED'],
							));
						}

						$display_vars = array(
							'title'	=> 'ACP_KB_SETTINGS',
							'vars'	=> $acp_options,
						);

						$config_show = true;

						$template->assign_vars(array(
							'S_ERROR'			=> (sizeof($error)) ? true : false,
							'ERROR_MSG'			=> implode('<br />', $error),

							'S_SETTINGS'		=> true,
							'S_SHOW_PAGE'		=> ($details['PLUGIN_MENU'] == NO_MENU) ? false : true,
							'APPEND_MESSAGE'	=> (function_exists('append_to_kb_options')) ? append_to_kb_options() : false,

							'U_BACK_LINK'		=> adm_back_link($this->u_action),
							'U_ACTION_PLUG'		=> $this->u_action . '&amp;action=settings&amp;filename=' . $filename,
							'PAGE_OPTIONS'		=> make_page_list($filename, $details),
						));
					break;

					default:
						if ($action == 'move_up')
						{
							sort_plugin_order('update', '', $filename, 'move_up');
						}
						else if ($action == 'move_down')
						{
							sort_plugin_order('update', '', $filename, 'move_down');
						}

						$installed_plugins = array();

						$sql = 'SELECT *
							FROM ' . KB_PLUGIN_TABLE . '
							ORDER BY plugin_order ASC';
						$result = $db->sql_query($sql);
						$rows = $db->sql_fetchrowset($result);
						$db->sql_freeresult($result);

						foreach($rows as $row)
						{
							$installed_plugins[] = $row['plugin_filename'];

							switch ($row['plugin_menu'])
							{
								case LEFT_MENU:
									$template->assign_block_vars('left_menu', array(
										'PLUGIN_NAME'		=> $row['plugin_name'],
										'PLUGIN_DESC'		=> $row['plugin_desc'],
										'PLUGIN_COPY'		=> $row['plugin_copy'],
										'PLUGIN_VERSION'	=> $row['plugin_version'],
										'PLUGIN_PERM'		=> $row['plugin_perm'],
										'U_MOVE_UP'			=> $this->u_action . '&amp;action=move_up&amp;filename=' . $row['plugin_filename'],
										'U_MOVE_DOWN'		=> $this->u_action . '&amp;action=move_down&amp;filename=' . $row['plugin_filename'],
										'U_SETTINGS'		=> $this->u_action . '&amp;action=settings&amp;filename=' . $row['plugin_filename'],
										'U_UNINSTALL'		=> $this->u_action . '&amp;action=uninstall&amp;filename=' . $row['plugin_filename'],
									));
								break;

								case RIGHT_MENU:
									$template->assign_block_vars('right_menu', array(
										'PLUGIN_NAME'		=> $row['plugin_name'],
										'PLUGIN_DESC'		=> $row['plugin_desc'],
										'PLUGIN_COPY'		=> $row['plugin_copy'],
										'PLUGIN_VERSION'	=> $row['plugin_version'],
										'PLUGIN_PERM'		=> $row['plugin_perm'],
										'U_MOVE_UP'			=> $this->u_action . '&amp;action=move_up&amp;filename=' . $row['plugin_filename'],
										'U_MOVE_DOWN'		=> $this->u_action . '&amp;action=move_down&amp;filename=' . $row['plugin_filename'],
										'U_SETTINGS'		=> $this->u_action . '&amp;action=settings&amp;filename=' . $row['plugin_filename'],
										'U_UNINSTALL'		=> $this->u_action . '&amp;action=uninstall&amp;filename=' . $row['plugin_filename'],
									));
								break;

								case NO_MENU:
									$template->assign_block_vars('no_menu', array(
										'PLUGIN_NAME'		=> $row['plugin_name'],
										'PLUGIN_DESC'		=> $row['plugin_desc'],
										'PLUGIN_COPY'		=> $row['plugin_copy'],
										'PLUGIN_VERSION'	=> $row['plugin_version'],
										'PLUGIN_PERM'		=> $row['plugin_perm'],
										'U_SETTINGS'		=> $this->u_action . '&amp;action=settings&amp;filename=' . $row['plugin_filename'],
										'U_UNINSTALL'		=> $this->u_action . '&amp;action=uninstall&amp;filename=' . $row['plugin_filename'],
									));
								break;
							}
						}

						$all_plugins = available_plugins();

						if (!empty($all_plugins))
						{
							$available_plugins = array_diff($all_plugins, $installed_plugins);

							foreach ($available_plugins as $key => $data)
							{
								if (!file_exists($plugin_loc . 'kb_' . $data . '.' . $phpEx))
								{
									continue;
								}

								include($plugin_loc . 'kb_' . $data . '.' . $phpEx);

								$template->assign_block_vars('uninstalled', array(
									'PLUGIN_NAME'		=> $user->lang[$details['PLUGIN_NAME']],
									'PLUGIN_DESC'		=> $user->lang[$details['PLUGIN_DESC']],
									'PLUGIN_COPY'		=> $user->lang[$details['PLUGIN_COPY']],
									'PLUGIN_VERSION'	=> $details['PLUGIN_VERSION'],

									'U_INSTALL'			=> $this->u_action . '&amp;action=install&amp;filename=' . $data,
								));

								unset($details);
							}
						}

						$template->assign_var('S_PLUGIN_MENU', true);
				}
			break;

			case 'health_check':
				// Get current and latest version
				$errstr = '';
				$errno = 0;

				//Let setup some stuff to make sure if version server is unreachable module still loads
				$latest_version		= $user->lang['KB_NO_INFO'];
				$announcement_url	= $user->lang['KB_NO_INFO'];
				$download_url 		= $user->lang['KB_NO_INFO'];
				$up_to_date			= $user->lang['KB_NO_INFO'];
				$current_version 	= $config['kb_version'];
				$kb_path = generate_board_url() . '/kb.' . $phpEx;

				$info = get_remote_file('kb.pertneer.net', '/mods', 'knowledgebase.txt', $errstr, $errno);
				//$info = get_remote_file('www.local.com', '/mods', 'knowledgebase.txt', $errstr, $errno);
				//git repo ssl location
				//https://raw.github.com/pertneer/VersionCheck/master/phpBB3_KB/knowledgebase.txt
				//$info = get_remote_file('raw.github.com', '/pertneer/VersionCheck/master/phpBB3_KB', 'knowledgebase.txt', $errstr, $errno, 443);

				//if server was contacted we need to use the correct information
				if ($info)
				{

					$info = explode("\n", $info);

					// Update vars
					$latest_version = trim($info[0]);
					$announcement_url = trim($info[1]);
					$download_url = trim($info[2]);

					$current_version = $config['kb_version'];

					$kb_path = generate_board_url() . '/kb_install/';

					$up_to_date = (version_compare(str_replace('rc', 'RC', strtolower($current_version)), str_replace('rc', 'RC', strtolower($latest_version)), '<')) ? false : true;

				}

				$template->assign_vars(array(
					'S_UP_TO_DATE'		=> $up_to_date,
					'S_VERSION_CHECK'	=> true,
					'U_ACTION'			=> $this->u_action,

					'LATEST_VERSION'	=> $latest_version,
					'CURRENT_VERSION'	=> $current_version,

					'UPDATE_INSTRUCTIONS'	=> sprintf($user->lang['UPDATE_INSTRUCTIONS'], $announcement_url, $download_url, $kb_path),
				));

				$uninstall = (isset($_POST['uninstall']) || isset($_GET['uninstall'])) ? true : false;
				if ($uninstall)
				{
					header('Location: '. $phpbb_root_path . 'kb_install/' . ' ');//renamed includes/functions_install_kb.php to kb_install/index.php
				}

				$reset_db = (isset($_POST['reset_db'])) ? true : false;
				if ($reset_db)
				{
					if(confirm_box(true))
					{
						reset_db();
						// add log
						add_log('admin', 'LOG_KB_RESET_DB', KB_VERSION);

						trigger_error($user->lang['KB_RESET_DB'] . adm_back_link($this->u_action));
					}
					else
					{
						$hidden_fields = build_hidden_fields(array(
							'reset_db'	=> true,
						));
						confirm_box(false, 'RESET_DB', $hidden_fields);
					}
				}

				$reset_perms = (isset($_POST['reset_perms'])) ? true : false;
				if ($reset_perms)
				{
					if(confirm_box(true))
					{
						reset_perms();

						// add log
						add_log('admin', 'LOG_KB_RESET_PERMS', KB_VERSION);

						trigger_error($user->lang['KB_RESET_PERMS'] . adm_back_link($this->u_action));
					}
					else
					{
						$hidden_fields = build_hidden_fields(array(
							'reset_perms'	=> true,
						));
						confirm_box(false, 'RESET_PERMS', $hidden_fields);
					}
				}

				$reset_plugins = (isset($_POST['reset_plugins'])) ? true : false;
				if ($reset_plugins)
				{
					if(confirm_box(true))
					{
						$sql = 'TRUNCATE TABLE ' . KB_PLUGIN_TABLE;
						$db->sql_query($sql);

						kb_install_perm_plugins('reset', 'install');
						// add log
						add_log('admin', 'LOG_KB_RESET_PLUGINS', KB_VERSION);

						trigger_error($user->lang['KB_RESET_PLUGINS'] . adm_back_link($this->u_action));
					}
					else
					{
						$hidden_fields = build_hidden_fields(array(
							'reset_plugins'	=> true,
						));
						confirm_box(false, 'RESET_PLUGINS', $hidden_fields);
					}
				}
			break;

			default:
				trigger_error('NO_MODE', E_USER_ERROR);
			break;
		}

		// prevent CSRF attacks
		if ($submit && !check_form_key($form_key))
		{
			$error[] = $user->lang['FORM_INVALID'];
		}

		// Do not submit if there is an error
		if (sizeof($error))
		{
			$submit = false;
		}

		if ($config_show)
		{
			$this->new_config = $config;
			$cfg_array = (isset($_REQUEST['config'])) ? utf8_normalize_nfc(request_var('config', array('' => ''), true)) : $this->new_config;

			if ($submit && $mode == 'plugins')
			{
				if (request_var('all_pages', 0))
				{
					$pages = make_page_list('', $details, true);
				}
				else
				{
					$pages = request_var('page', array('' => ''));
				}

				$serial_page = serialize($pages);

				update_pages($filename, $serial_page);
			}

			// We validate the complete config if whished
			validate_config_vars($display_vars['vars'], $cfg_array, $error);

			// Do not submit if there is an error
			if (sizeof($error))
			{
				$submit = false;
			}

			// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
			foreach ($display_vars['vars'] as $config_name => $null)
			{
				if (!isset($cfg_array[$config_name]) || strpos($config_name, 'legend') !== false)
				{
					continue;
				}

				$this->new_config[$config_name] = $config_value = $cfg_array[$config_name];

				if ($submit)
				{
					set_config($config_name, $config_value);

					if ($mode == 'plugins')
					{
						if (($config_name == 'kb_' . $filename . '_menu') || (strpos($config_name, '_menu')))
						{
							update_plugin_menu($filename, $config_value);
						}
					}
				}
			}

			if ($submit)
			{
				add_log('admin', 'LOG_CONFIG_' . strtoupper($mode));

				$cache->destroy('config');

				trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
			}

			$template->assign_vars(array(
				'L_TITLE'			=> $user->lang[$display_vars['title']],
				'L_TITLE_EXPLAIN'	=> $user->lang[$display_vars['title'] . '_EXPLAIN'],

				'S_ERROR'			=> (sizeof($error)) ? true : false,
				'ERROR_MSG'			=> implode('<br />', $error),
				//'KB_GIT'			=> true,

				'U_ACTION'			=> $this->u_action)
			);

			// Output relevant page
			foreach ($display_vars['vars'] as $config_key => $vars)
			{
				if (!is_array($vars) && strpos($config_key, 'legend') === false)
				{
					continue;
				}

				if (strpos($config_key, 'legend') !== false)
				{
					$template->assign_block_vars('options', array(
						'S_LEGEND'		=> true,
						'LEGEND'		=> (isset($user->lang[$vars])) ? $user->lang[$vars] : $vars)
					);

					continue;
				}

				$type = explode(':', $vars['type']);

				$l_explain = '';
				if ($vars['explain'] && isset($vars['lang_explain']))
				{
					$l_explain = (isset($user->lang[$vars['lang_explain']])) ? $user->lang[$vars['lang_explain']] : $vars['lang_explain'];
				}
				else if ($vars['explain'])
				{
					$l_explain = (isset($user->lang[$vars['lang'] . '_EXPLAIN'])) ? $user->lang[$vars['lang'] . '_EXPLAIN'] : '';
				}

				$content = build_cfg_template($type, $config_key, $this->new_config, $config_key, $vars);

				if (empty($content))
				{
					continue;
				}

				$request_status = array('kb_request_status_added', 'kb_request_status_pending', 'kb_request_status_request');
				if(!in_array(strtolower($config_key), $request_status))
				{
					$swatch = false;
				}
				else
				{
					$swatch = append_sid("{$phpbb_admin_path}swatch.$phpEx", 'form=acp_board&amp;name=' . $config_key);
				}
				$template->assign_block_vars('options', array(
					'KEY'			=> $config_key,
					'TITLE'			=> (isset($user->lang[$vars['lang']])) ? $user->lang[$vars['lang']] : $vars['lang'],
					'S_EXPLAIN'		=> $vars['explain'],
					'TITLE_EXPLAIN'	=> $l_explain,
					'CONTENT'		=> $content,
					'U_SWATCH'		=> $swatch,
					)
				);

				unset($display_vars['vars'][$config_key]);
			}
		}
	}

	/**
	* Select menu type
	*/
	function select_menu_type($value, $key = '')
	{
		global $user;

		return '<option value="0"' . (($value == 0) ? ' selected="selected"' : '') . '>' . $user->lang['KB_PIXELS'] . ' (px)</option><option value="1"' . (($value == 1) ? ' selected="selected"' : '') . '>' . $user->lang['KB_PERCENT'] . ' (%)</option>';
	}

	/**
	* Select layout style
	*/
	function select_style_layout($value, $key = '')
	{
		global $user;

		return '<option value="0"' . (($value == 0) ? ' selected="selected"' : '') . '>' . $user->lang['KB_CLASSIC_LOOK'] . ' </option><option value="1"' . (($value == 1) ? ' selected="selected"' : '') . '>' . $user->lang['KB_MODERN_LOOK'] . '</option>';
	}

	/**
	* Select default rating
	*/
	function select_default_rating($value, $key = '')
	{
		global $user;
		$default_rating = '';
		$ratings = array('0','1','2','3','4','5','6');
		foreach($ratings as $rating){
			$default_rating .= '<option value="'.$rating.'"'.(($rating == $value) ? ' selected="selected"' : '').'>'.$rating.'</option>';
		}
		return $default_rating;
	}
}

/**
* Select menu
*/
function select_menu_check($value, $key = '')
{
	$radio_ary = array(LEFT_MENU => 'LEFT_MENU', RIGHT_MENU => 'RIGHT_MENU');

	return h_radio('config[' . $key . ']', $radio_ary, $value, $key);
}

/**
* Reset KB Database (Was more like delete all kb information in db)
* Reset puts back to installed state
*/
function reset_db()
{
	// Remove attach files
	// Empty database tables
	// Reset phpBB database fields
	global $db;

	$sql = 'SELECT attach_id
			FROM ' . KB_ATTACHMENTS_TABLE;
	$result = $db->sql_query($sql);

	$attach_ids = array();
	while($row = $db->sql_fetchrow($result))
	{
		$attach_ids[] = $row['attach_id'];
	}
	$db->sql_freeresult($result);

	kb_delete_attachments('attach', $attach_ids, false);

	$tables = array(KB_ATTACHMENTS_TABLE, KB_CATS_TABLE, KB_COMMENTS_TABLE, KB_EDITS_TABLE, KB_RATE_TABLE, KB_TAGS_TABLE, KB_TRACK_TABLE, KB_TABLE, KB_REQ_TABLE, KB_TYPE_TABLE, KB_PLUGIN_TABLE, KB_ACL_GROUPS_TABLE, KB_ACL_USERS_TABLE);
	foreach($tables as $table)
	{
		$sql = 'TRUNCATE TABLE ' . $table;
		$db->sql_query($sql);
	}

	$sql = "UPDATE " . USERS_TABLE . "
			SET user_articles = 0, user_kb_permissions = ''";
	$db->sql_query($sql);

	$sql = 'UPDATE ' . EXTENSION_GROUPS_TABLE . '
			SET allow_in_kb = 0';
	$db->sql_query($sql);

	insert_kb_data();
}

function insert_kb_data()
{
	global $db;

	kb_install_perm_plugins('reset', 'install');

	kb_install_first_information();

	reset_category_perm();

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

	//need to reset permissions on categories

}

function reset_category_perm()
{
	global $db;

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

}

function reset_perms()
{
	global $phpbb_root_path, $phpEx, $db;

	if(!class_exists('auth_admin'))
	{
		include($phpbb_root_path . 'includes/acp/auth.' . $phpEx);
	}
	$auth_admin = new auth_admin();

	$tables = array(KB_ACL_GROUPS_TABLE, KB_ACL_USERS_TABLE);
	foreach($tables as $table)
	{
		$sql = 'TRUNCATE TABLE ' . $table;
		$db->sql_query($sql);
	}

	$sql = "SELECT role_id
			FROM " . ACL_ROLES_TABLE . "
			WHERE role_type = 'u_kb_'";
	$result = $db->sql_query($sql);

	$role_ids = array();
	while($row = $db->sql_fetchrow($result))
	{
		$role_ids[] = $row['role_id'];
	}
	$db->sql_freeresult($result);

	if(sizeof($role_ids))
	{
		$sql = 'DELETE FROM ' . ACL_ROLES_DATA_TABLE . '
				WHERE ' . $db->sql_in_set('role_id', $role_ids);
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . ACL_ROLES_TABLE . '
				WHERE ' . $db->sql_in_set('role_id', $role_ids);
		$db->sql_query($sql);
	}

	$auth_admin->acl_clear_prefetch();

	if(!class_exists('auth_admin'))
	{
		include($phpbb_root_path . 'includes/acp/auth.' . $phpEx);
	}
	$auth_admin = new auth_admin();

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

	require($phpbb_root_path . 'includes/kb_permissions.' . $phpEx);
	remove_perms($umil, $versions);
	add_perms($umil, $versions);
	unset($versions);
}

function remove_perms($umil, $versions)
{
	$umil->run_actions('uninstall', $versions, 'kb_version');
}

function add_perms($umil, $versions)
{
	$umil->run_actions('install', $versions, 'kb_version');
}
?>