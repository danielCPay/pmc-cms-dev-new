<?php

use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * ImportChecks
 *
 * @package   Workflow
 *
 * @copyright DOT Systems
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Michał Kamiński <mkaminski@dotsystems.pl>
 * @author    Michał Jastrzębski <mjastrzebski@dotsystems.pl>
 */


class ImportChecks
{
	/**
	 * Import Checks from file
	 *
	 * @param \Documents_Record_Model $recordModel
	 */
	public static function importIncomingChecks( Vtiger_Record_Model $recordModel )
	{
		$id = $recordModel->getId();
		$path = $recordModel->getFileDetails()['path'];
		$fileName = $recordModel->get('filename');
		$attachmentId = $recordModel->getFileDetails()['attachmentsid'];
		\App\Log::warning("Documents::Workflows::importIncomingChecks:$id/$fileName/$path/$attachmentId");

		try {
			$fullPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $attachmentId;
			$checks = self::parseIncomingChecks($fullPath);
			\App\Log::warning("Documents::Workflows::importIncomingChecks:checks parsed = " . count($checks));

			// get batch number from DB as max( batch_number ) + 1 for ChecksRegister using QueryGenerator
			$batchNumber = ChecksRegister_Module_Model::getNextBatchNumber();
			$now = new DateTime(); // Current date and time
			// foreach check
			foreach ($checks as $check) {
				//	create CheckRegistry record
				$recordModel = Vtiger_Record_Model::getCleanInstance('ChecksRegister');
				$recordModel->set('check_number', $check['check_number']);
				$recordModel->set('claim_number', $check['claim_number']);
				$recordModel->set('provider_user', $check['provider']);
				$recordModel->set('insurance_company_user', $check['insurance_carrier']);
				$recordModel->set('amount', $check['amount']);
				$recordModel->set('scan_date', $check['scan_date']);
				$recordModel->set('db_link', $check['db_link']);
				$recordModel->set('attorney', $check['attorney']);
				$recordModel->set('insured', $check['insured']);
				$recordModel->set('batch_number', $batchNumber);

				$targetDate = new DateTime($check['scan_date']);
				$recordModel->set('check_age', $now->diff($targetDate)->days);
				
				ChecksRegister_Module_Model::processCheck($recordModel);

				$checksRegisterModule = $recordModel->getModule();
				$relationModel = \Vtiger_Relation_Model::getInstance($checksRegisterModule, Vtiger_Module_Model::getInstance('Documents'));
				$relationModel->addRelation($recordModel->getId(), $id);
			}
		} catch (Exception $ex) {
			\App\Log::error("Documents::Workflows::importIncomingChecks:Problem importing file $fullPath - " . $ex->getMessage());
			\App\Toasts::addToast( \App\User::getCurrentUserOriginalId(), "Problem importing file $fileName - " . $ex->getMessage(), "errorSticky");
			throw new \App\Exceptions\NoRethrowWorkflowException("Problem importing file $fileName - " . $ex->getMessage(), 0, $ex);
		}

		\App\Toasts::addToast( \App\User::getCurrentUserOriginalId(), "File $fileName imported", "successSticky");
	}

	public static function parseIncomingChecks(string $fullPath)
	{
		try
		{
			/**  Identify the type of $inputFileName  **/
			$inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($fullPath);
			/**  Create a new Reader of the type that has been identified  **/
			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
			$spreadSheet = $reader->load($fullPath);

			if ($spreadSheet->getSheetCount() == 0) {
				throw new ImportException("No sheets found in file");
			}
		}
		catch (Exception $ex)
		{
			\App\Log::error("Documents::Workflows::importChecks:Problem reading file $fullPath - " . $ex->getMessage());
			throw new ImportException("Problem reading file $fullPath - " . $ex->getMessage());
		}

		$columns = [
			"INSURED" => [ "fieldName" => "insured", "columnNumber" => -1 ],
			"CHECK NUMBER" => [ "fieldName" => "check_number", "columnNumber" => -1 ],
			"PROVIDER" => [ "fieldName" => "provider", "columnNumber" => -1 ],
			"ATTORNEY" => [ "fieldName" => "attorney", "columnNumber" => -1 ],
			"INSURANCE CARRIER" => [ "fieldName" => "insurance_carrier", "columnNumber" => -1 ],
			"CLAIM NUMBER" => [ "fieldName" => "claim_number", "columnNumber" => -1 ],
			"AMOUNT" => [ "fieldName" => "amount", "columnNumber" => -1 ],
			"SCAN DATE" => [ "fieldName" => "scan_date", "columnNumber" => -1 ],
			"DB LINK" => [ "fieldName" => "db_link", "columnNumber" => -1 ],
		];

		try
		{
			$workSheet = $spreadSheet->setActiveSheetIndex(0);

			$rowsChecked = 1;
			$rowIterator = $workSheet->getRowIterator();
			foreach ($rowIterator as $row)
			{
				$cellIterator = $row->getCellIterator();
				$cellIterator->setIterateOnlyExistingCells(true);

				$headerLocated = false;
				foreach ($cellIterator as $cell)
				{
					$cellValue = $cell->getValue();
					$cellValue = trim(strtoupper($cellValue));
					if (array_key_exists($cellValue, $columns) && $columns[$cellValue]["columnNumber"] === -1)
					{
						$columns[$cellValue]["columnNumber"] = $cell->getColumn();
						$headerLocated = true;
					}
				}

				if ($headerLocated)
				{
					// check if all headers have column number different from -1
					$missingHeaders = array_filter($columns, function($column) { return $column['columnNumber'] === -1; });
					if (!empty($missingHeaders)) {
						throw new ImportException("Not all headers found: " . implode(", ", array_keys($missingHeaders)));
					}

					break;
				}

				if ($rowsChecked++ > 10)
				{
					throw new ImportException("Header not found in first 10 rows");
				}
			}

			if (!$headerLocated)
			{
				throw new ImportException("Header row not found");
			}

			$checks = [];
			$rowIterator = $workSheet->getRowIterator($rowsChecked + 1);
			foreach ($rowIterator as $row)
			{
				$check = [];
				foreach ($columns as $column) 
				{
					$cell = $workSheet->getCell($column["columnNumber"] . $row->getRowIndex());
					if (Date::isDateTime($cell)) {
						$cellValue = Date::excelToDateTimeObject($cell->getValue())->format('Y-m-d');
					} else {
						$cellValue = trim($cell->getValue());
					}

					$check[$column["fieldName"]] = $cellValue;
				}
				$checks[] = $check;
			}
		}
		catch (Exception $ex)
		{
			throw new ImportException("Problem parsing file $fullPath - " . $ex->getMessage());
		}

		return $checks;
	}
}
	
