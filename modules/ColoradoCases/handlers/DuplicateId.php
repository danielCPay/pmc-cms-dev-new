<?php
/**
 * Duplicate id handler.
 *
 * @package Handler
 *
 * @copyright DOT Systems sp. z o.o
 * @license YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author Michał Kamiński <mkaminski@dotsystems.pl>
 */
/**
 * ColoradoCases_DuplicateId_Handler class.
 */
class ColoradoCases_DuplicateId_Handler extends Vtiger_DuplicateId_Handler
{
	protected $fieldName = 'case_id';
	protected $ignoreValues = ['(New)'];
}
