<?php
/* +**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * Contributor(s): YetiForce.com
 * ********************************************************************************** */

include_once 'modules/Vtiger/CRMEntity.php';

class ContactsDOT extends Vtiger_CRMEntity
{
	public $table_name = 'u_yf_contactsdot';
	public $table_index = 'contactsdotid';

	/**
	 * Mandatory table for supporting custom fields.
	 */
	public $customFieldTable = ['u_yf_contactsdotcf', 'contactsdotid'];

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	public $tab_name = ['vtiger_crmentity', 'u_yf_contactsdot', 'u_yf_contactsdotcf'];

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	public $tab_name_index = [
		'vtiger_crmentity' => 'crmid',
		'u_yf_contactsdot' => 'contactsdotid',
		'u_yf_contactsdotcf' => 'contactsdotid',
	];

	/**
	 * Mandatory for Listing (Related listview).
	 */
	public $list_fields = [
		// Format: Field Label => Array(tablename, columnname)
		'Name' => ['contactsdot', 'name'],
		'Assigned To' => ['crmentity', 'smownerid'],
	];
	public $list_fields_name = [
		// Format: Field Label => fieldname
		'Name' => 'name',
		'Assigned To' => 'assigned_user_id',
	];
	// Make the field link to detail view
	public $list_link_field = 'name';
	// For Popup listview and UI type support
	public $search_fields = [
		// Format: Field Label => Array(tablename, columnname)
		'Name' => ['contactsdot', 'name'],
		'Assigned To' => ['vtiger_crmentity', 'assigned_user_id'],
	];
	public $search_fields_name = [];
	// For Popup window record selection
	public $popup_fields = ['name'];
	// For Alphabetical search
	public $def_basicsearch_col = 'name';
	// Column value to use on detail view record text display
	public $def_detailview_recname = 'name';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	public $mandatory_fields = ['name', 'assigned_user_id'];
	public $default_order_by = '';
	public $default_sort_order = 'ASC';

	/**
	 * Invoked when special actions are performed on the module.
	 *
	 * @param string $moduleName Module name
	 * @param string $eventType  Event Type
	 */
	public function moduleHandler($moduleName, $eventType)
	{
		if ('module.postinstall' === $eventType) {
		} elseif ('module.disabled' === $eventType) {
		} elseif ('module.preuninstall' === $eventType) {
		} elseif ('module.preupdate' === $eventType) {
		} elseif ('module.postupdate' === $eventType) {
		}
	}
}
