<?php

/**
 * Condition main class.
 *
 * @package   App
 *
 * @copyright YetiForce Sp. z o.o
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Tomasz Kur <t.kur@yetiforce.com>
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */

namespace App;

/**
 * Condition main class.
 */
class Condition
{
	/**
	 * Data filter list.
	 */
	const DATE_OPERATORS = [
		'custom' => ['label' => 'LBL_CUSTOM'],
		'smallerthannow' => ['label' => 'LBL_SMALLER_THAN_NOW'],
		'greaterthannow' => ['label' => 'LBL_GREATER_THAN_NOW'],
		'prevfy' => ['label' => 'LBL_PREVIOUS_FY'],
		'thisfy' => ['label' => 'LBL_CURRENT_FY'],
		'nextfy' => ['label' => 'LBL_NEXT_FY'],
		'prevfq' => ['label' => 'LBL_PREVIOUS_FQ'],
		'thisfq' => ['label' => 'LBL_CURRENT_FQ'],
		'nextfq' => ['label' => 'LBL_NEXT_FQ'],
		'previousworkingday' => ['label' => 'LBL_PREVIOUS_WORKING_DAY'],
		'nextworkingday' => ['label' => 'LBL_NEXT_WORKING_DAY'],
		'yesterday' => ['label' => 'LBL_YESTERDAY'],
		'today' => ['label' => 'LBL_TODAY'],
		'untiltoday' => ['label' => 'LBL_UNTIL_TODAY'],
		'tomorrow' => ['label' => 'LBL_TOMORROW'],
		'lastweek' => ['label' => 'LBL_LAST_WEEK'],
		'thisweek' => ['label' => 'LBL_CURRENT_WEEK'],
		'nextweek' => ['label' => 'LBL_NEXT_WEEK'],
		'lastmonth' => ['label' => 'LBL_LAST_MONTH'],
		'thismonth' => ['label' => 'LBL_CURRENT_MONTH'],
		'nextmonth' => ['label' => 'LBL_NEXT_MONTH'],
		'last4days' => ['label' => 'LBL_LAST_4_DAYS'],
		'last5days' => ['label' => 'LBL_LAST_5_DAYS'],
		'last7days' => ['label' => 'LBL_LAST_7_DAYS'],
		'last15days' => ['label' => 'LBL_LAST_15_DAYS'],
		'last30days' => ['label' => 'LBL_LAST_30_DAYS'],
		'last60days' => ['label' => 'LBL_LAST_60_DAYS'],
		'last90days' => ['label' => 'LBL_LAST_90_DAYS'],
		'last120days' => ['label' => 'LBL_LAST_120_DAYS'],
		'last180days' => ['label' => 'LBL_LAST_180_DAYS'],
		'next15days' => ['label' => 'LBL_NEXT_15_DAYS'],
		'next30days' => ['label' => 'LBL_NEXT_30_DAYS'],
		'next60days' => ['label' => 'LBL_NEXT_60_DAYS'],
		'next90days' => ['label' => 'LBL_NEXT_90_DAYS'],
		'next120days' => ['label' => 'LBL_NEXT_120_DAYS'],
		'moreThanDaysAgo' => ['label' => 'LBL_DATE_CONDITION_MORE_THAN_DAYS_AGO'],
	];
	/**
	 * Supported advanced filter operations.
	 */
	const STANDARD_OPERATORS = [
		'e' => 'LBL_EQUALS',
		'n' => 'LBL_NOT_EQUAL_TO',
		's' => 'LBL_STARTS_WITH',
		'ew' => 'LBL_ENDS_WITH',
		'c' => 'LBL_CONTAINS',
		'ch' => 'LBL_CONTAINS_HIERARCHY',
		'k' => 'LBL_DOES_NOT_CONTAIN',
		'kh' => 'LBL_DOES_NOT_CONTAIN_HIERARCHY',
		'l' => 'LBL_LESS_THAN',
		'g' => 'LBL_GREATER_THAN',
		'm' => 'LBL_LESS_THAN_OR_EQUAL',
		'h' => 'LBL_GREATER_OR_EQUAL',
		'b' => 'LBL_BEFORE',
		'a' => 'LBL_AFTER',
		'bw' => 'LBL_BETWEEN',
		'y' => 'LBL_IS_EMPTY',
		'ny' => 'LBL_IS_NOT_EMPTY',
		'om' => 'LBL_CURRENTLY_LOGGED_USER',
		'nom' => 'LBL_USER_CURRENTLY_NOT_LOGGED',
		'ogr' => 'LBL_CURRENTLY_LOGGED_USER_GROUP',
		'wr' => 'LBL_IS_WATCHING_RECORD',
		'nwr' => 'LBL_IS_NOT_WATCHING_RECORD',
		'hs' => 'LBL_HAS_CHANGED',
		'hst' => 'LBL_HAS_CHANGED_TO',
		'ro' => 'LBL_IS_RECORD_OPEN',
		'rc' => 'LBL_IS_RECORD_CLOSED',
		'nco' => 'LBL_NOT_CREATED_BY_OWNER',
		'nco' => 'LBL_NOT_CREATED_BY_OWNER',
		're' => 'LBL_REFERENCE_EQUALS',
		'rne' => 'LBL_REFERENCE_NOT_EQUAL_TO',
	];
	/**
	 * Operators without values.
	 */
	const OPERATORS_WITHOUT_VALUES = [
		'y', 'ny', 'om', 'nom', 'ogr', 'wr', 'nwr', 'hs', 'ro', 'rc', 'nco',
		'smallerthannow',
		'greaterthannow',
		'prevfy',
		'thisfy',
		'nextfy',
		'prevfq',
		'thisfq',
		'yesterday',
		'today',
		'untiltoday',
		'tomorrow',
		'lastweek',
		'thisweek',
		'nextweek',
		'lastmonth',
		'thismonth',
		'nextmonth',
		'last4days',
		'last5days',
		'last7days',
		'last15days',
		'last30days',
		'last60days',
		'last90days',
		'last120days',
		'last180days',
		'next15days',
		'next30days',
		'next60days',
		'next90days',
		'next120days',
		'previousworkingday',
		'nextworkingday',
	];

	/**
	 * Vtiger_Record_Model instance cache.
	 *
	 * @var Vtiger_Record_Model[]
	 */
	private static $recordCache = [];

	/**
	 * Checks structure search_params.
	 *
	 * @param string $moduleName
	 * @param array  $searchParams
	 * @param bool   $convert
	 *
	 * @throws \App\Exceptions\IllegalValue
	 *
	 * @return array
	 */
	public static function validSearchParams(string $moduleName, array $searchParams, $convert = true): array
	{
		$searchParamsCount = \count($searchParams);
		if ($searchParamsCount > 2) {
			throw new Exceptions\IllegalValue("ERR_NUMBER_OF_ARGUMENTS_NOT_ALLOWED||{$searchParamsCount}|| > 2||" . Utils::varExport($searchParams, true), 406);
		}
		$fields = \Vtiger_Module_Model::getInstance($moduleName)->getFields();
		$result = [];
		foreach ($searchParams as $params) {
			$tempParam = [];
			foreach ($params as $param) {
				if (empty($param)) {
					continue;
				}
				$count = \count($param);
				if (3 !== $count && 4 !== $count) {
					throw new Exceptions\IllegalValue("ERR_NUMBER_OF_ARGUMENTS_NOT_ALLOWED||{$count}|| <> 3 or 4||" . Utils::varExport($param, true), 406);
				}
				[$relatedFieldName, $relatedModule, $referenceField] = array_pad(explode(':', $param[0]), 3, null);
				if ($relatedModule) {
					$relatedFields = \Vtiger_Module_Model::getInstance($relatedModule)->getFields();
					if (!isset($fields[$referenceField], $relatedFields[$relatedFieldName])) {
						throw new Exceptions\IllegalValue("ERR_FIELD_NOT_FOUND||{$param[0]}||" . Utils::varExport($param, true), 406);
					}
					$fieldModel = $relatedFields[$relatedFieldName];
				} else {
					if (!isset($fields[$param[0]])) {
						throw new Exceptions\IllegalValue("ERR_FIELD_NOT_FOUND||{$param[0]}||" . Utils::varExport($param, true), 406);
					}
					$fieldModel = $fields[$param[0]];
				}
				$value = $fieldModel->getUITypeModel()->getDbConditionBuilderValue($param[2], $param[1]);
				if ($convert) {
					$param[2] = $value;
				}
				$tempParam[] = $param;
			}
			$result[] = $tempParam;
		}
		return $result;
	}

	/**
	 * Checks value search_value.
	 *
	 * @param string $value
	 * @param string $moduleName
	 * @param string $fieldName
	 * @param string $operator
	 *
	 * @return string
	 */
	public static function validSearchValue(string $value, string $moduleName, string $fieldName, string $operator): string
	{
		if ('' !== $value) {
			\Vtiger_Module_Model::getInstance($moduleName)->getField($fieldName)->getUITypeModel()->getDbConditionBuilderValue($value, $operator);
		}
		return $value;
	}

	/**
	 * Return condition from request.
	 *
	 * @param array $conditions
	 *
	 * @return array
	 */
	public static function getConditionsFromRequest(array $conditions): array
	{
		if (isset($conditions['rules'])) {
			foreach ($conditions['rules'] as &$condition) {
				if (isset($condition['condition'])) {
					$condition = static::getConditionsFromRequest($condition);
				} else {
					$operator = $condition['operator'];
					$value = $condition['value'] ?? '';
					if (!\in_array($operator, self::OPERATORS_WITHOUT_VALUES + array_keys(self::DATE_OPERATORS))) {
						[$fieldName, $fieldModuleName,] = array_pad(explode(':', $condition['fieldname']), 3, false);
						if ($fieldName == 'special-current-role') {
							// value stays unchanged
						} else {
							$value = \Vtiger_Field_Model::getInstance($fieldName, \Vtiger_Module_Model::getInstance($fieldModuleName))
								->getUITypeModel()
								->getDbConditionBuilderValue($value, $operator);
						}
					}
					$condition['value'] = $value;
				}
			}
		}
		return $conditions;
	}

	/**
	 * Check all conditions.
	 *
	 * @param array                $conditions
	 * @param \Vtiger_Record_Model $recordModel
	 *
	 * @return bool
	 */
	public static function checkConditions(array $conditions, \Vtiger_Record_Model $recordModel): bool
	{
		return self::parseConditions($conditions, $recordModel);
	}

	/**
	 * Parse conditions.
	 *
	 * @param array|null           $conditions
	 * @param \Vtiger_Record_Model $recordModel
	 *
	 * @return bool
	 */
	private static function parseConditions(?array $conditions, \Vtiger_Record_Model $recordModel): bool
	{
		if (empty($conditions)) {
			return true;
		}
		$result = false;
		$andCondition = 'AND' === $conditions['condition'];
		if (!is_array($conditions['rules'])) {
			$conditions = [ 'rules' => [ $conditions ] ];
		}
		foreach ($conditions['rules'] as $rule) {
			if (isset($rule['condition'])) {
				$ruleResult = self::parseConditions($rule, $recordModel);
			} else {
				$ruleResult = self::checkCondition($rule, $recordModel);
			}
			if (!$andCondition && $ruleResult) {
				$result = true;
				break;
			}
			if ($andCondition && !$ruleResult) {
				$result = false;
				break;
			}
			$result = $ruleResult;
		}
		return $result;
	}

	/**
	 * Check one condition.
	 *
	 * @param array                $rule
	 * @param \Vtiger_Record_Model $recordModel
	 *
	 * @return bool
	 */
	public static function checkCondition(array $rule, \Vtiger_Record_Model $recordModel): bool
	{
		$fieldSplit = explode(':', $rule['fieldname']);
		$fieldName = $fieldSplit[0];
		$moduleName = $fieldSplit[1];
		$sourceFieldNames = array_slice($fieldSplit, 2);

		if ($fieldName == 'special-current-role') {
			// check if current user role is in specified hierarchy
			$user = \App\User::getCurrentUserModel();
			if (!empty($_REQUEST) && $user->isAdmin()) {
				return true;
			} else if ($rule['operator'] == 'e') {
				return false !== strpos(\Settings_Roles_Record_Model::getInstanceById($rule['value'])->get('parentrole'), $user->getRoleInstance()->get('parentrole'));
			} else {
				return false === strpos($user->getRoleInstance()->get('parentrole'), \Settings_Roles_Record_Model::getInstanceById($rule['value'])->get('parentrole'));
			}
		} else {
			if (!empty($sourceFieldNames)) {
				$sourceRecordModel = $recordModel;
				while (!empty($sourceFieldNames) && ($sourceFieldName = array_pop($sourceFieldNames))) {
					if ($sourceRecordModel->isEmpty($sourceFieldName)) {
						return false;
					}
					$sourceRecordModel = self::$recordCache[$sourceRecordModel->get($sourceFieldName)] ?? \Vtiger_Record_Model::getInstanceById($sourceRecordModel->get($sourceFieldName));
					$fieldModel = $sourceRecordModel->getField($fieldName);
				}
			} elseif ($recordModel->getModuleName() === $moduleName) {
				$fieldModel = $recordModel->getField($fieldName);
			}
			if (empty($fieldModel)) {
				Log::error("Not found field model | Field name: $fieldName | Module: $moduleName", 'Condition');
				throw new \App\Exceptions\AppException('ERR_NOT_FOUND_FIELD_MODEL|' . $fieldName);
			}
			$className = '\App\Conditions\RecordFields\\' . ucfirst($fieldModel->getFieldDataType()) . 'Field';
			if (!class_exists($className)) {
				Log::error("Not found record field condition | Field name: $fieldName | Module: $moduleName | FieldDataType: " . ucfirst($fieldModel->getFieldDataType()), 'Condition');
				throw new \App\Exceptions\AppException("ERR_NOT_FOUND_QUERY_FIELD_CONDITION|$fieldName|$className");
			}
			if (!empty($sourceFieldName)) {
				$recordField = new $className($sourceRecordModel, $fieldModel, $rule);
				$recordField->setSource($recordModel, $sourceFieldName);
			} else {
				$recordField = new $className($recordModel, $fieldModel, $rule);
			}
			return $recordField->check();
		}
	}

	/**
	 * Get field names from conditions.
	 *
	 * @param array $conditions
	 *
	 * @return array ['baseModule' => [], 'referenceModule' => []]
	 */
	public static function getFieldsFromConditions(array $conditions): array
	{
		$fields = ['baseModule' => [], 'referenceModule' => []];
		if (isset($conditions['rules'])) {
			foreach ($conditions['rules'] as &$condition) {
				if (isset($condition['condition'])) {
					$condition = static::getFieldsFromConditions($condition);
				} else {
					[$fieldName, $moduleName, $sourceFieldName] = array_pad(explode(':', $condition['fieldname']), 3, false);
					if ($sourceFieldName) {
						$fields['referenceModule'][$moduleName][$sourceFieldName] = $fieldName;
					} else {
						$fields['baseModule'][] = $fieldName;
					}
				}
			}
		}
		return $fields;
	}
}
