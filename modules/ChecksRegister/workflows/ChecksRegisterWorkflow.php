<?php

/**
 * ChecksRegisterWorkflow
 *
 * @package   Workflow
 *
 * @copyright DOT Systems
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Michał Kamiński <mkaminski@dotsystems.pl>
 * @author    Michał Jastrzębski <mjastrzebski@dotsystems.pl>
 */

class ChecksRegisterWorkflow
{
	/**
	 * Assign Batch Number.
	 *
	 * @param \Vtiger_Record_Model $recordModel
	 */
  public static function assignNextBatchNumber( Vtiger_Record_Model $recordModel )
  {
    $id = $recordModel->getId();
    \App\Log::warning("ChecksRegister::Workflows::assignNextBatchNumber:$id");

    if (\App\Request::_get('_isDuplicateRecord') === 'true') {
      // read batch number from _duplicateRecord in request
      $duplicateRecordId = \App\Request::_get('_duplicateRecord');
      $sourceRecordModel = Vtiger_Record_Model::getInstanceById($duplicateRecordId, 'ChecksRegister');
      $batchNumber = $sourceRecordModel->get('batch_number');
    } else {
      $batchNumber = ChecksRegister_Module_Model::getNextBatchNumber();
    }

    $recordModel->set('batch_number', $batchNumber);
    $recordModel->save();

    \App\Log::warning("ChecksRegister::Workflows::assignNextBatchNumber:batch number = $batchNumber");
  }

  /**
   * Reprocess Check.
   * 
   * @param \Vtiger_Record_Model $recordModel
   */
  public static function reprocessCheck( Vtiger_Record_Model $recordModel )
  {
    $id = $recordModel->getId();
    \App\Log::warning("ChecksRegister::Workflows::reprocessCheck:$id");

    ChecksRegister_Module_Model::processCheck($recordModel);
    
    \App\Log::warning("ChecksRegister::Workflows::reprocessCheck:done");
  }
}
