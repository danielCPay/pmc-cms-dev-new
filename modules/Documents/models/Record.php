<?php

 /* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * Contributor(s): YetiForce.com
 * *********************************************************************************** */

/**
 * Class Documents_Record_Model.
 */
class Documents_Record_Model extends Vtiger_Record_Model
{
	/** @var string[] Types included in the preview of the file. */
	public $filePreview = [
		'application/pdf', 'image/png', 'image/jpeg', 'image/jpeg', 'image/jpeg', 'image/gif', 'image/bmp', 'image/vnd.microsoft.icon', 'image/tiff', 'image/tiff'
	];

	/**
	 * Get download file url.
	 *
	 * @return string
	 */
	public function getDownloadFileURL()
	{
		if ('I' == $this->getValueByField('filelocationtype')) {
			$fileDetails = $this->getFileDetails();

			return 'file.php?module=' . $this->getModuleName() . '&action=DownloadFile&record=' . $this->getId() . '&fileid=' . $fileDetails['attachmentsid'];
		}
		return $this->get('filename');
	}

	/**
	 * Get file DAV url.
	 *
	 * @return string|null
	 */
	public function getFileDAVURL()
	{
		if ('I' == $this->getValueByField('filelocationtype')) {
			$fileDetails = $this->getFileDetails();

			$mimeTypes = require 'config/mimetypes.php';
			$extension = array_search($fileDetails['type'], $mimeTypes) ?: 'docx';

			$basePath = preg_replace('/^storage\//', '', "{$fileDetails['path']}{$fileDetails['attachmentsid']}.{$extension}");

			$data = [
				'usr' => \App\User::getCurrentUserId(),
				'pth' => preg_replace('/^Documents\//', '', $basePath),
				'exp' => strtotime('+1 day'),
			];
			$secret = \App\Config::api('FILE_DAV_KEY');
			$jwt = new \Ahc\Jwt\JWT($secret);
			$token = $jwt->encode($data);
			// remove first part of token (before first dot)
			$token = substr($token, strpos($token, '.') + 1);

			$path = \App\Config::main('site_URL') . "filedav.php/{$token}/" . $basePath;

			switch ($fileDetails['type']) {
				case 'application/msword':
				case 'application/rtf':
				case 'application/vnd.oasis.opendocument.text':
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.ms-word.document.macroEnabled.12':
					return "ms-word:ofe|u|$path";
				case 'application/vnd.ms-excel':
				case 'application/vnd.oasis.opendocument.spreadsheet':
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
				case 'application/vnd.ms-excel.sheet.macroEnabled.12':
					return "ms-excel:ofe|u|$path";
				case 'application/vnd.ms-powerpoint':
				case 'application/vnd.ms-powerpoint.presentation.macroEnabled.12':
				case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
					return "ms-powerpoint:ofe|u|$path";
					break;
			}
		}

		return null;
	}

	/** {@inheritdoc} */
	public function getRecordRelatedListViewLinksLeftSide(Vtiger_RelationListView_Model $viewModel)
	{
		$links = [];
		if (!$this->isReadOnly() && \in_array($this->getValueByField('filetype'), $this->filePreview)) {
			$links['LBL_PREVIEW_FILE'] = Vtiger_Link_Model::getInstanceFromValues([
				'linklabel' => 'LBL_PREVIEW_FILE',
				'linkhref' => true,
				'linkurl' => $this->getDownloadFileURL() . '&show=1',
				'linkicon' => 'fas fa-binoculars',
				'linkclass' => 'btn-sm btn-light',
				'linktarget' => '_blank'
			]);
		}
		return array_merge($links, parent::getRecordRelatedListViewLinksLeftSide($viewModel));
	}

	/**
	 * Check file integrity url.
	 *
	 * @return string
	 */
	public function checkFileIntegrityURL()
	{
		return "javascript:Documents_Detail_Js.checkFileIntegrity('index.php?module=" . $this->getModuleName() . '&action=CheckFileIntegrity&record=' . $this->getId() . "')";
	}

	/**
	 * Check file integrity.
	 *
	 * @return bool
	 */
	public function checkFileIntegrity()
	{
		$returnValue = false;
		if ('I' === $this->get('filelocationtype') && ($fileDetails = $this->getFileDetails())) {
			$fileName = html_entity_decode($fileDetails['name'], ENT_QUOTES, \App\Config::main('default_charset'));
			$savedFile = $fileDetails['path'] . $fileDetails['attachmentsid'];
			$returnValue = (file_exists($savedFile) && fopen($savedFile, 'r')) || (file_exists("{$savedFile}_{$fileName}") && fopen("{$savedFile}_{$fileName}", 'r'));
		}
		return $returnValue;
	}

	/**
	 * Get file details.
	 *
	 * @return array
	 */
	public function getFileDetails()
	{
		if (!isset($this->fileDetails)) {
			$this->fileDetails = (new \App\Db\Query())->from('vtiger_attachments')
				->innerJoin('vtiger_seattachmentsrel', 'vtiger_seattachmentsrel.attachmentsid = vtiger_attachments.attachmentsid')
				->where(['crmid' => $this->get('id')])
				->one();
		}
		return $this->fileDetails;
	}

	/**
	 * Download file.
	 */
	public function downloadFile()
	{
		$fileContent = false;
		if ($fileDetails = $this->getFileDetails()) {
			$filePath = ROOT_DIRECTORY . DIRECTORY_SEPARATOR . $fileDetails['path'];
			$fileName = $fileDetails['name'];
			if ('I' === $this->get('filelocationtype')) {
				$fileName = html_entity_decode($fileName, ENT_QUOTES, \App\Config::main('default_charset'));
				if (file_exists($filePath . $fileDetails['attachmentsid'])) {
					$savedFile = $fileDetails['attachmentsid'];
				} else {
					$savedFile = $fileDetails['attachmentsid'] . '_' . $fileName;
				}
				if (file_exists($filePath . $savedFile)) {
					if ($this->get('return')) {
						return \App\Fields\File::loadFromInfo([
							'path' => $filePath . $savedFile,
							'name' => $fileDetails['name'],
							'mimeType' => $fileDetails['type'],
						]);
					}
					$fileSize = filesize($filePath . $savedFile);
					$fileSize = $fileSize + ($fileSize % 1024);
					if (fopen($filePath . $savedFile, 'r')) {
						if ($fileSize) {
							$fileContent = fread(fopen($filePath . $savedFile, 'r'), $fileSize);
						} else {
							$fileContent = '';
						}
						$fileName = $this->get('filename');
						header('content-type: ' . $fileDetails['type']);
						header('pragma: public');
						header('cache-control: private');
						if ($this->get('show')) {
							header('content-disposition: inline');
						} else {
							header("content-disposition: attachment; filename=\"$fileName\"");
						}
					}
				}
			}
		}
		echo $fileContent;
	}

	/**
	 * Download files.
	 *
	 * @param int[] $recordsIds
	 */
	public static function downloadFiles($recordsIds)
	{
		$zip = new ZipArchive();
		$postfix = time() . '_' . random_int(0, 1000);
		$zipPath = ROOT_DIRECTORY . '/cache/';
		$fileName = $zipPath . "documentsZipFile_{$postfix}.zip";
		if (true !== $zip->open($fileName, ZIPARCHIVE::CREATE)) {
			\App\Log::error("cannot open <$fileName>\n");
			throw new \App\Exceptions\NoPermitted("cannot open <$fileName>");
		}

		$cleanPath = function ($str) {
			return preg_replace("/[^[:alnum:] _.-]/u", ' ', $str);
		};

		$cleanId = function ($str) {
			return preg_replace("/[^[:alnum:] _.-]/u", '_', $str);
		};

		foreach ($recordsIds as $recordId) {
			/** @var Documents_Record_Model $documentModel */
			$documentModel = self::getInstanceById($recordId);

			$archivePath = '';
			$documentType = $documentModel->get('document_type');
			if (!empty($documentType) && \App\Record::isExists($documentType)) {
				$documentType = Vtiger_Record_Model::getInstanceById($documentType);
				foreach(explode('/', $documentType->get('document_type_path')) as $pathElement) {
					$archivePath .= $cleanPath(trim($pathElement)) . DIRECTORY_SEPARATOR;
				}
			}

			if ('I' === $documentModel->get('filelocationtype') && ($fileDetails = $documentModel->getFileDetails())) {
				$filePath = $fileDetails['path'];
				if (file_exists($filePath . $fileDetails['attachmentsid'])) {
					$savedFile = $fileDetails['attachmentsid'];
				} else {
					$savedFile = $fileDetails['attachmentsid'] . '_' . html_entity_decode($fileDetails['name'], ENT_QUOTES, \App\Config::main('default_charset'));
				}

				if (file_exists($filePath . $savedFile)) {
					$parentId = $documentModel->get('case') ?: $documentModel->get('claim') ?: $documentModel->get('portfolio_purchase') ?: $documentModel->get('portfolio');
					if (\App\Record::isExists($parentId)) {
						$parentRecord = Vtiger_Record_Model::getInstanceById($parentId);
						$prefix = ($parentRecord->get('case_id') ?: $parentRecord->get('claim_id') ?: $parentRecord->get('portfolio_purchase_name') ?: $parentRecord->get('portfolio_id')) . ' - ';
					} else { 
						$prefix = '';
					}

					$zip->addFile($filePath . $savedFile, $archivePath . $cleanId($prefix) . $cleanPath($documentModel->get('note_no') . ' - ' . basename($documentModel->get('filename'))));
					$documentModel->updateDownloadCount();
				} else {
					$zip->addFromString($archivePath . $documentModel->getDisplayName(), '');
				}
			} else {
				$zip->addFromString($archivePath . $documentModel->getDisplayName(), '');
			}
		}
		$zip->close();
		header('content-type: ' . \App\Fields\File::getMimeContentType($fileName));
		header('content-disposition: attachment; filename="' . basename($fileName) . '";');
		header('accept-ranges: bytes');
		header('content-length: ' . filesize($fileName));

		readfile($fileName);
		unlink($fileName);
	}

	/**
	 * Update file status.
	 *
	 * @param int $status
	 */
	public function updateFileStatus($status)
	{
		App\Db::getInstance()->createCommand()->update('vtiger_notes', ['filestatus' => $status], ['notesid' => $this->get('id')])->execute();
	}

	/**
	 * Update download count.
	 */
	public function updateDownloadCount()
	{
		$notesId = $this->get('id');
		$downloadCount = (new \App\Db\Query())->select(['filedownloadcount'])->from('vtiger_notes')->where(['notesid' => $notesId])->scalar();
		\App\Db::getInstance()->createCommand()->update('vtiger_notes', ['filedownloadcount' => ++$downloadCount], ['notesid' => $notesId])->execute();
	}

	/**
	 * Get download count update url.
	 *
	 * @return string
	 */
	public function getDownloadCountUpdateUrl()
	{
		return 'index.php?module=Documents&action=UpdateDownloadCount&record=' . $this->getId();
	}

	/**
	 * Get reference module by doc id.
	 *
	 * @param int $record
	 *
	 * @return array
	 */
	public static function getReferenceModuleByDocId($record)
	{
		return (new App\Db\Query())->select(['vtiger_crmentity.setype'])->from('vtiger_crmentity')->innerJoin('vtiger_senotesrel', 'vtiger_senotesrel.crmid = vtiger_crmentity.crmid')->where(['vtiger_crmentity.deleted' => 0, 'vtiger_senotesrel.notesid' => $record])->distinct()->column();
	}

	public static function getFileIconByFileType($fileType)
	{
		return \App\Layout\Icon::getIconByFileType($fileType);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isMandatorySave()
	{
		return parent::isMandatorySave() || $_FILES;
	}

	/**
	 * Sets field value for save.
	 *
	 * @param string $fieldName
	 * @param mixed  $value
	 *
	 * @return $this
	 */
	public function setFieldValue(string $fieldName, $value)
	{
		$fieldModel = $this->getField($fieldName);
		if ($fieldModel) {
			$this->set($fieldModel->getName(), $value);
			$this->setDataForSave([$fieldModel->getTableName() => [$fieldModel->getColumnName() => $value]]);
		}
		return $this;
	}

	/**
	 * Function to save record.
	 */
	public function saveToDb()
	{
		$db = \App\Db::getInstance();
		$fileNameByField = 'filename';
		if ('I' === $this->get('filelocationtype')) {
			if (isset($this->file)) {
				$file = $this->file;
			} else {
				$file = (array_key_exists($fileNameByField, $_FILES) && !$_FILES[$fileNameByField]['uploaded'] ? $_FILES[$fileNameByField] : null) ?? [];
			}
			if (!empty($file['name']) && isset($file['error'])) {
				if (UPLOAD_ERR_OK === $file['error'] && ($fileInstance = \App\Fields\File::loadFromRequest($file)) && $fileInstance->validateAndSecure()) {
					$this->setFieldValue('filename', \App\Purifier::decodeHtml(App\Purifier::purify($file['name'])))
						->setFieldValue('filetype', $fileInstance->getMimeType())
						->setFieldValue('filesize', $fileInstance->getSize())
						->setFieldValue('filedownloadcount', 0);
				} else {
					\App\Log::error("Error while saving a file, saving failed. | ID: {$this->getId()} | File: {$file['name']} | Error: " . \App\Fields\File::getErrorMessage($file['error']));

					if (empty($file['name'])) {
						throw new \App\Exceptions\DangerousFile('ERR_FILE_EMPTY_NAME');
					}
					if (0 === $file['size']) {
						throw new \App\Exceptions\DangerousFile('ERR_FILE_WRONG_SIZE');
					}
					throw new \App\Exceptions\DangerousFile('ERR_CREATE_FILE_FAILURE');
				}
			} else {
				$file = [];
			}
		} elseif ('E' === $this->get('filelocationtype')) {
			$fileName = $this->get($fileNameByField);
			// If filename does not has the protocol prefix, default it to http://
			// Protocol prefix could be like (https://, smb://, file://, \\, smb:\\,...)
			if (!empty($fileName) && !preg_match('/^\w{1,5}:\/\/|^\w{0,3}:?\\\\\\\\/', trim($fileName), $match)) {
				$fileName = "http://$fileName";
			}
			$this->setFieldValue('filename', $fileName)
				->setFieldValue('filesize', 0)
				->setFieldValue('filetype', '')
				->setFieldValue('filedownloadcount', null);
		}
		parent::saveToDb();
		//Inserting into attachments table
		if ('I' === $this->get('filelocationtype')) {
			if ($file) {
				$file['original_name'] = \App\Request::_get('0_hidden');
				$this->uploadAndSaveFile($file);
				$_FILES[$fileNameByField]['uploaded'] = true;
			}
		} else {
			$db->createCommand()->delete('vtiger_seattachmentsrel', ['crmid' => $this->getId()])->execute();
		}
	}

	/**
	 * This function is used to upload the attachment in the server and save that attachment information in db.
	 *
	 * @param array $fileDetails - array which contains the file information(name, type, size, tmp_name and error)
	 *
	 * @return bool
	 */
	public function uploadAndSaveFile($fileDetails)
	{
		$id = $this->getId();
		$moduleName = $this->getModuleName();
		$result = false;
		\App\Log::trace("Entering into uploadAndSaveFile($id,$moduleName) method.");
		$fileInstance = \App\Fields\File::loadFromRequest($fileDetails);
		$this->ext['attachmentsName'] = $fileName = empty($fileDetails['original_name']) ? $fileDetails['name'] : $fileDetails['original_name'];
		$db = \App\Db::getInstance();
		$uploadFilePath = \App\Fields\File::initStorageFileDirectory($moduleName);
		$db->createCommand()->insert('vtiger_attachments', [
			'name' => ltrim(App\Purifier::purify($fileName)),
			'type' => $fileDetails['type'],
			'path' => $uploadFilePath
		])->execute();
		$currentId = $db->getLastInsertID('vtiger_attachments_attachmentsid_seq');
		if ($fileInstance->moveFile(ROOT_DIRECTORY . DIRECTORY_SEPARATOR . $uploadFilePath . $currentId)) {
			$db->createCommand()->delete('vtiger_seattachmentsrel', ['crmid' => $id])->execute();
			$db->createCommand()->insert('vtiger_seattachmentsrel', ['crmid' => $id, 'attachmentsid' => $currentId])->execute();
			$this->ext['attachmentsId'] = $currentId;
			$result = true;
		} else {
			$db->createCommand()->delete('vtiger_attachments', ['attachmentsid' => $currentId])->execute();
		}
		\App\Log::trace('Skip the uploadAndSaveFile process.');
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete()
	{
		parent::delete();
		$dbCommand = \App\Db::getInstance()->createCommand();
		$attachmentsIds = (new \App\Db\Query())->select(['attachmentsid'])->from('vtiger_seattachmentsrel')->where(['crmid' => $this->getId()])->column();
		if (!empty($attachmentsIds)) {
			$dataReader = (new \App\Db\Query())->select(['path', 'attachmentsid'])->from('vtiger_attachments')->where(['attachmentsid' => $attachmentsIds])->createCommand()->query();
			while ($row = $dataReader->read()) {
				$fileName = $row['path'] . $row['attachmentsid'];
				if (file_exists($fileName)) {
					unlink($fileName);
				}
			}
			$dataReader->close();
			$dbCommand->delete('vtiger_attachments', ['attachmentsid' => $attachmentsIds])->execute();
		}
	}
}
