<?php
/**
*
* @package phpBB phpBB3-Knowledgebase Mod (KB)
* @version $Id: functions_plugins_kb.php $
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

// KB roles
$permission_type = 'u_kb_';

$versions = array(
	'1.0.6'	=> array(
		'table_row_insert'	=> array(

			array(ACL_ROLES_TABLE, array(
				'role_name'			=> 'ROLE_KB_GUEST',
				'role_description'	=> 'ROLE_KB_GUEST_DESC',
				'role_type'			=> $permission_type,
				)
			),
			array(ACL_ROLES_TABLE, array(
				'role_name'			=> 'ROLE_KB_USER',
				'role_description'	=> 'ROLE_KB_USER_DESC',
				'role_type'			=> $permission_type,
				)
			),
			array(ACL_ROLES_TABLE, array(
				'role_name'			=> 'ROLE_KB_MOD',
				'role_description'	=> 'ROLE_KB_MOD_DESC',
				'role_type'			=> $permission_type,
				)
			),
		),

			'permission_add' => array(
				// Global
				array('a_kb_settings', true),
				array('a_kb_maintenance', true),
				array('a_kb_plugins', true),
				array('a_kb_category', true),
				array('a_kb_perm', true),
				array('a_kb_roles', true),
				array('a_kb_types', true),
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

		'permission_set' => array(
			//moderator full permissions
			array('ROLE_MOD_FULL', 'm_kb_author'),
			array('ROLE_MOD_FULL', 'm_kb_comment'),
			array('ROLE_MOD_FULL', 'm_kb_edit'),
			array('ROLE_MOD_FULL', 'm_kb_delete'),
			array('ROLE_MOD_FULL', 'm_kb_req_edit'),
			array('ROLE_MOD_FULL', 'm_kb_status'),
			array('ROLE_MOD_FULL', 'm_kb_time'),
			array('ROLE_MOD_FULL', 'm_kb_view'),
			array('ROLE_MOD_FULL', 'u_kb_request'),
			//moderator standard permissions
			array('ROLE_MOD_STANDARD', 'm_kb_comment'),
			array('ROLE_MOD_STANDARD', 'm_kb_edit'),
			array('ROLE_MOD_STANDARD', 'm_kb_delete'),
			array('ROLE_MOD_STANDARD', 'm_kb_status'),
			array('ROLE_MOD_STANDARD', 'm_kb_view'),
			array('ROLE_MOD_STANDARD', 'u_kb_request'),
			//moderator queue permissions
			array('ROLE_MOD_QUEUE', 'm_kb_comment'),
			array('ROLE_MOD_QUEUE', 'm_kb_status'),
			array('ROLE_MOD_QUEUE', 'm_kb_view'),
			array('ROLE_MOD_QUEUE', 'u_kb_request'),
			//moderator simple permissions
			array('ROLE_MOD_SIMPLE', 'm_kb_comment'),
			//user roles
			array('ROLE_USER_FULL', 'u_kb_request'),
			array('ROLE_USER_NOAVATAR', 'u_kb_request'),
			array('ROLE_USER_NOPM', 'u_kb_request'),
			array('ROLE_USER_STANDARD', 'u_kb_request'),
			// Global Role permissions
			array('ROLE_ADMIN_FULL', 'a_kb_settings'),
			array('ROLE_ADMIN_FULL', 'a_kb_plugins'),
			array('ROLE_ADMIN_FULL', 'a_kb_category'),
			array('ROLE_ADMIN_FULL', 'a_kb_perm'),
			array('ROLE_ADMIN_FULL', 'a_kb_roles'),
			array('ROLE_ADMIN_FULL', 'a_kb_types'),
			array('ROLE_ADMIN_FULL', 'a_kb_maintenance'),
			array('ROLE_ADMIN_STANDARD', 'a_kb_category'),
			array('ROLE_ADMIN_STANDARD', 'a_kb_perm'),
			array('ROLE_ADMIN_STANDARD', 'a_kb_roles'),
			array('ROLE_ADMIN_STANDARD', 'a_kb_types'),
			//kb_guest
			array('ROLE_KB_GUEST','u_kb_bbcode'),
			array('ROLE_KB_GUEST','u_kb_comment'),
			array('ROLE_KB_GUEST','u_kb_download'),
			array('ROLE_KB_GUEST','u_kb_img'),
			array('ROLE_KB_GUEST','u_kb_read'),
			array('ROLE_KB_GUEST','u_kb_search'),
			array('ROLE_KB_GUEST','u_kb_smilies'),
			array('ROLE_KB_GUEST','u_kb_view'),
			//kb_user
			array('ROLE_KB_USER','u_kb_add'),
			array('ROLE_KB_USER','u_kb_attach'),
			array('ROLE_KB_USER','u_kb_bbcode'),
			array('ROLE_KB_USER','u_kb_comment'),
			array('ROLE_KB_USER','u_kb_delete'),
			array('ROLE_KB_USER','u_kb_download'),
			array('ROLE_KB_USER','u_kb_edit'),
			array('ROLE_KB_USER','u_kb_icons'),
			array('ROLE_KB_USER','u_kb_img'),
			array('ROLE_KB_USER','u_kb_rate'),
			array('ROLE_KB_USER','u_kb_read'),
			array('ROLE_KB_USER','u_kb_search'),
			array('ROLE_KB_USER','u_kb_sigs'),
			array('ROLE_KB_USER','u_kb_smilies'),
			array('ROLE_KB_USER','u_kb_types'),
			array('ROLE_KB_USER','u_kb_view'),
			//kb_mod
			array('ROLE_KB_MOD','u_kb_add'),
			array('ROLE_KB_MOD','u_kb_add_wa'),
			array('ROLE_KB_MOD','u_kb_attach'),
			array('ROLE_KB_MOD','u_kb_bbcode'),
			array('ROLE_KB_MOD','u_kb_comment'),
			array('ROLE_KB_MOD','u_kb_delete'),
			array('ROLE_KB_MOD','u_kb_download'),
			array('ROLE_KB_MOD','u_kb_edit'),
			array('ROLE_KB_MOD','u_kb_icons'),
			array('ROLE_KB_MOD','u_kb_img'),
			array('ROLE_KB_MOD','u_kb_rate'),
			array('ROLE_KB_MOD','u_kb_read'),
			array('ROLE_KB_MOD','u_kb_search'),
			array('ROLE_KB_MOD','u_kb_sigs'),
			array('ROLE_KB_MOD','u_kb_smilies'),
			array('ROLE_KB_MOD','u_kb_types'),
			array('ROLE_KB_MOD','u_kb_view'),
			array('ROLE_KB_MOD','u_kb_viewhistory'),
		),

		'custom'	=> 'reset_category_perm',
	),

);
