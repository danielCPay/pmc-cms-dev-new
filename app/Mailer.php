<?php

namespace App;

/**
 * Mailer basic class.
 *
 * @package App
 *
 * @copyright YetiForce Sp. z o.o
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class Mailer
{
	/** @var string[] Queue status */
	public static $statuses = [
		0 => 'LBL_PENDING_ACCEPTANCE',
		1 => 'LBL_WAITING_TO_BE_SENT',
		2 => 'LBL_ERROR_DURING_SENDING',
		3 => 'LBL_SENDING',
	];

	/** @var string[] Columns list that require JSON formatting */
	public static $quoteJsonColumn = ['from', 'to', 'cc', 'bcc', 'attachments', 'params'];

	/** @var string[] Columns list available in the database */
	public static $quoteColumn = ['smtp_id', 'date', 'owner', 'status', 'from', 'subject', 'content', 'to', 'cc', 'bcc', 'attachments', 'priority'];

	/** @var \PHPMailer\PHPMailer\PHPMailer PHPMailer instance */
	protected $mailer;

	/** @var array SMTP configuration */
	protected $smtp;

	/** @var array Parameters for sending messages */
	protected $params = [];

	/** @var array Error logs */
	public static $error;

	/**
	 * Construct.
	 */
	public function __construct()
	{
		static::$error = [];
		$this->mailer = new \PHPMailer\PHPMailer\PHPMailer(false);
		if (\App\Config::debug('MAILER_DEBUG')) {
			$this->mailer->SMTPDebug = 2;
			$this->mailer->Debugoutput = function ($str, $level) {
				// starts with SERVER or Connection or "CLIENT-> SERVER: [4 uppercase letters]
				if (stripos(trim($str), 'SERVER') === 0 || stripos(trim($str), 'Connection:') === 0 || preg_match('/^CLIENT -> SERVER: \[/', trim($str)) === 1 || (preg_match('/^CLIENT -> SERVER: [A-Z]{4,}/', trim($str)) === 1 && strlen($str) < 70)) {
					Log::warning(trim($str), 'Mailer');
				}
				// if (false !== stripos($str, 'error') || false !== stripos($str, 'failed')) {
				// 	static::$error[] = $str;
				// 	Log::error(trim($str), 'Mailer');
				// } else {
				// 	Log::warning(trim($str), 'Mailer');
				// }
			};
		}
		$this->mailer->XMailer = 'YetiForceCRM Mailer';
		$this->mailer->Hostname = 'YetiForceCRM';
		$this->mailer->FromName = 'YetiForce Mailer';
		$this->mailer->CharSet = \App\Config::main('default_charset');
	}
	
	/**
	 * Load configuration smtp by id.
	 *
	 * @param int $smtpId Smtp ID
	 *
	 * @return $this mailer object itself
	 */
	public function loadSmtpByID($smtpId)
	{
		$this->smtp = Mail::getSmtpById($smtpId);
		$this->setSmtp();
		return $this;
	}

	/**
	 * Load configuration smtp.
	 *
	 * @param array $smtpInfo
	 *
	 * @return $this mailer object itself
	 */
	public function loadSmtp($smtpInfo)
	{
		$this->smtp = $smtpInfo;
		$this->setSmtp();
		return $this;
	}

	/**
	 * @param array $params
	 *
	 * @return bool
	 */
	public static function sendFromTemplate(array $params, &$error = null): bool
	{
		Log::trace('Send mail from template', 'Mailer');
		if (empty($params['template'])) {
			Log::warning('No templete', 'Mailer');
			return false;
		}
		$recordModel = false;
		if (empty($params['recordModel'])) {
			$moduleName = $params['moduleName'] ?? null;
			if (isset($params['recordId'])) {
				$recordModel = \Vtiger_Record_Model::getInstanceById($params['recordId'], $moduleName);
			}
		} else {
			$recordModel = $params['recordModel'];
			unset($params['recordModel']);
		}
		if ($recordModel) {
			$params['recordId'] = $recordModel->getId();
		}
		$template = Mail::getTemplate($params['template']);
		if (!$template) {
			Log::warning('No mail templete', 'Mailer');
			return false;
		}
		$textParser = $recordModel ? TextParser::getInstanceByModel($recordModel) : TextParser::getInstance($params['moduleName'] ?? '');
		if (!empty($params['language'])) {
			$textParser->setLanguage($params['language']);
		}
		if (!empty($params['sourceRecord'])) {
			$textParser->setSourceRecord($params['sourceRecord'], $params['sourceModule']);
		}
		if (preg_match('/(?<account>{ACCOUNT = (?<name>.*)})/', $template['subject'], $matches)) {
			$account = $matches['account'];
			$name = $textParser->setContent($matches['name'])->parse()->getContent();
			$smtp = \VTWorkflowUtils::getSmtpByName($name);
			if ($smtp) {
				$params['smtp_id'] = $smtp;
			}

			$template['subject'] = trim(str_replace($account, '', $template['subject']));
		} else if (!empty($template['email_from'])) {
			$from = \Vtiger_Record_Model::getInstanceById($template['email_from']);
			$params['smtp_id'] = $from->get('smtp');
		}
		$textParser->setParams(array_diff_key($params, array_flip(['subject', 'content', 'attachments', 'recordModel'])));
		$subject = $template['subject'];
		$subject = \App\Utils\Completions::processIfs($subject, $textParser);
		$subject = $textParser->setContent($subject)->parse()->getContent();
		$params['subject'] = $subject;
		
		$content = $template['content'];
		$content = \App\Utils\Completions::processIfs($content, $textParser);
		$content = $textParser->setContent($content)->parse()->getContent();
		$params['content'] = $params['header'] . \App\Utils\Completions::decode(\App\Purifier::purifyHtml($content)) . $params['footer'];
		unset($params['header']);
		unset($params['footer']);
		
		unset($textParser);
		if (isset($template['attachments'])) {
			$params['attachments'] = array_merge_recursive(empty($params['attachments']) ? [] : $params['attachments'], $template['attachments']);
		}
		if (!empty($template['email_template_priority'])) {
			$params['priority'] = $template['email_template_priority'];
		}
		$row = array_intersect_key($params, array_flip(self::$quoteColumn));
		$row['params'] = array_diff_key($params, $row);
		return static::addMail($row, $error);
	}

	/**
	 * @param array $params
	 *
	 * @return bool
	 */
	public static function sendDirect(array $params, &$error = null): bool
	{
		Log::trace('Send mail', 'Mailer');
		$row = array_intersect_key($params, array_flip(self::$quoteColumn));
		$row['params'] = array_diff_key($params, $row);
		return static::addMail($row, $error);
	}

	/**
	 * Add mail to quote for send.
	 *
	 * @param array $params
	 *
	 * @return bool
	 */
	public static function addMail(array $params, &$error = null): bool
	{
		$params['status'] = Config::component('Mail', 'MAILER_REQUIRED_ACCEPTATION_BEFORE_SENDING') ? 0 : 1;
		$params['date'] = date('Y-m-d H:i:s');
		if (empty($params['owner'])) {
			$owner = User::getCurrentUserRealId();
			$params['owner'] = $owner ?: 0;
		}
		if (empty($params['smtp_id'])) {
			$params['smtp_id'] = Mail::getDefaultSmtp();
		}
		if (empty($params['smtp_id'])) {
			unset($params['priority'], $params['status']);
			$params['error_code'] = 1;
			static::insertMail($params, 'log');
			$error = 'No SMTP configuration';
			Log::warning($error, 'Mailer');
			return false;
		}
		if (!\App\Mail::getSmtpById($params['smtp_id'])) {
			unset($params['priority'], $params['status']);
			$params['error_code'] = 2;
			static::insertMail($params, 'log');
			$error = 'SMTP configuration with provided id not exists';
			Log::warning($error, 'Mailer');
			return false;
		}
		if (empty($params['to'])) {
			unset($params['priority'], $params['status']);
			$params['error_code'] = 3;
			static::insertMail($params, 'log');
			$error = 'No target email address provided';
			Log::warning($error, 'Mailer');
			return false;
		}
		static::insertMail($params, 'admin');
		return true;
	}

	/**
	 * Save mail data in provided table.
	 *
	 * @param array  $params
	 * @param string $type   'admin' | 'log'
	 *
	 * @return void
	 */
	public static function insertMail(array $params, string $type): void
	{
		$eventHandler = new EventHandler();
		$eventHandler->setParams($params);
		$eventHandler->trigger('admin' === $type ? 'MailerAddToQueue' : 'MailerAddToLogs');
		$params = $eventHandler->getParams();

		foreach (static::$quoteJsonColumn as $key) {
			if (isset($params[$key])) {
				if (!\is_array($params[$key])) {
					$params[$key] = [$params[$key]];
				}
				$params[$key] = Json::encode($params[$key]);
			}
		}
		\App\Db::getInstance($type)->createCommand()->insert('admin' === $type ? 's_#__mail_queue' : 'l_#__mail', $params)->execute();
		Log::warning("Mailer::insertMail::$type:" . var_export(array_diff_key($params, array_flip(['content'])), true));
	}

	/**
	 * Get configuration smtp.
	 *
	 * @param string|null $key
	 *
	 * @return mixed
	 */
	public function getSmtp(?string $key = null)
	{
		if ($key && isset($this->smtp[$key])) {
			return $this->smtp[$key];
		}
		return $this->smtp;
	}

	/**
	 * Set configuration smtp in mailer.
	 */
	public function setSmtp(): void
	{
		if (!$this->smtp) {
			throw new \App\Exceptions\AppException('ERR_NO_SMTP_CONFIGURATION');
		}
		switch ($this->smtp['mailer_type']) {
			case 'smtp':
				$this->mailer->isSMTP();
				break;
				case 'smtpxoauth':
					$this->mailer->isSMTP();
					$this->mailer->AuthType = 'XOAUTH2';

					$domain = substr(strrchr(trim($this->smtp['username']), '@'), 1);;
					$oauthConfigs = \App\Config::module('OSSMail', 'oauth_configs');
        	$oauthConfig = $oauthConfigs[$domain] ?? [];
	
					$provider = new \League\OAuth2\Client\Provider\GenericProvider([
						'clientId' => $oauthConfig['client_id'],
						'clientSecret' => $oauthConfig['client_secret'],
						'urlAuthorize' => \App\Config::module('OSSMail', 'oauth_auth_uri'),
						'urlAccessToken' => \App\Config::module('OSSMail', 'oauth_token_uri'),
						'urlResourceOwnerDetails' => \App\Config::module('OSSMail', 'oauth_identity_uri'),
					]);
	
					$this->mailer->setOAuth(
						new \PHPMailer\PHPMailer\OAuth([
							'provider' => $provider,
							'clientId' => $oauthConfig['client_id'],
							'clientSecret' => $oauthConfig['client_secret'],
							'refreshToken' => Encryption::getInstance()->decrypt($this->smtp['password']),
							'userName' => $this->smtp['username'],
					]));
					break;
			case 'sendmail':
				$this->mailer->isSendmail();
				break;
			case 'mail':
				$this->mailer->isMail();
				break;
			case 'qmail':
				$this->mailer->isQmail();
				break;
			default:
				break;
		}
		$this->mailer->Host = $this->smtp['host'];
		if (!empty($this->smtp['port'])) {
			$this->mailer->Port = $this->smtp['port'];
		}
		$this->mailer->SMTPSecure = $this->smtp['secure'];
		$this->mailer->SMTPAuth = isset($this->smtp['authentication']) && (bool) $this->smtp['authentication'];
		$this->mailer->Username = trim($this->smtp['username']);
		$this->mailer->Password = trim(Encryption::getInstance()->decrypt($this->smtp['password']));
		if ($this->smtp['options']) {
			$this->mailer->SMTPOptions = Json::decode($this->smtp['options'], true);
		}
		$this->mailer->From = $this->smtp['from_email'] ?: $this->smtp['username'];
		if ($this->smtp['from_name']) {
			$this->mailer->FromName = $this->smtp['from_name'];
		}
		if ($this->smtp['reply_to']) {
			$this->mailer->addReplyTo($this->smtp['reply_to']);
		}
		if ($this->smtp['unsubscribe']) {
			$unsubscribe = '';
			foreach (\App\Json::decode($this->smtp['unsubscribe']) as $row) {
				$unsubscribe .= "<$row>,";
			}
			$unsubscribe = rtrim($unsubscribe, ',');
			$this->mailer->AddCustomHeader('List-Unsubscribe', $unsubscribe);
		}
		if ($this->smtp['priority']) {
			$priorityName = $priority = $priorityX = null;
			switch ($this->smtp['priority']) {
				case 'normal':
				case 'Normal':
					$priorityX = 3;
					$priority = $priorityName = 'Normal';
					break;
				case 'non-urgent':
				case 'Low':
					$priorityX = 5;
					$priority = 'Non-Urgent';
					$priorityName = 'Low';
					break;
				case 'urgent':
				case 'High':
						$priorityX = 1;
						$priority = 'Urgent';
						$priorityName = 'High';
					break;
			}
			if ($priority) {
				$this->mailer->Priority = $priorityX;
				$this->mailer->AddCustomHeader('Priority', $priority);
				$this->mailer->AddCustomHeader('X-MSMail-Priority', $priorityName);
				$this->mailer->AddCustomHeader('Importance', $priorityName);
			}
		}
		if ($this->smtp['confirm_reading_to']) {
			$this->mailer->ConfirmReadingTo = $this->smtp['confirm_reading_to'];
		}
		if ($this->smtp['organization']) {
			$this->mailer->AddCustomHeader('Organization', $this->smtp['organization']);
		}
	}

	/**
	 * Set subject.
	 *
	 * @param string $subject
	 *
	 * @return $this mailer object itself
	 */
	public function subject($subject)
	{
		$this->params['subject'] = $this->mailer->Subject = $subject;
		return $this;
	}

	/**
	 * Creates a message from an HTML string, making modifications for inline images and backgrounds and creates a plain-text version by converting the HTML.
	 *
	 * @param text $message
	 *
	 * @see \PHPMailer::MsgHTML()
	 *
	 * @return $this mailer object itself
	 */
	public function content($message)
	{
		$this->params['body'] = $message;
		// Modification of the following condition will violate the license!
		if (!\App\YetiForce\Shop::check('YetiForceDisableBranding')) {
			$message .= '<table style="font-size:9px;width:100%; margin: 0;"><tbody><tr><td style="width:50%;text-align: center;">Powered by YetiForce</td></tr></tbody></table>';
		}
		$this->mailer->isHTML(true);
		$this->mailer->msgHTML($message);
		return $this;
	}

	/**
	 * Set the From and FromName properties.
	 *
	 * @param string $address
	 * @param string $name
	 *
	 * @return $this mailer object itself
	 */
	public function from($address, $name = '')
	{
		$this->params['from'][$address] = $name;
		$this->mailer->From = $address;
		$this->mailer->FromName = $name;
		return $this;
	}

	/**
	 * Add a "To" address.
	 *
	 * @param string $address The email address to send to
	 * @param string $name
	 *
	 * @return $this mailer object itself
	 */
	public function to($address, $name = '')
	{
		$this->params['to'][$address] = $name;
		$this->mailer->addAddress($address, $name);
		return $this;
	}

	/**
	 * Add a "CC" address.
	 *
	 * @note: This function works with the SMTP mailer on win32, not with the "mail" mailer.
	 *
	 * @param string $address The email address to send to
	 * @param string $name
	 *
	 * @return $this mailer object itself
	 */
	public function cc($address, $name = '')
	{
		$this->params['cc'][$address] = $name;
		$this->mailer->addCC($address, $name);
		return $this;
	}

	/**
	 * Add a "BCC" address.
	 *
	 * @note: This function works with the SMTP mailer on win32, not with the "mail" mailer.
	 *
	 * @param string $address The email address to send to
	 * @param string $name
	 *
	 * @return $this mailer object itself
	 */
	public function bcc($address, $name = '')
	{
		$this->params['bcc'][$address] = $name;
		$this->mailer->addBCC($address, $name);
		return $this;
	}

	/**
	 * Add a "Reply-To" address.
	 *
	 * @param string $address The email address to reply to
	 * @param string $name
	 *
	 * @return $this mailer object itself
	 */
	public function replyTo($address, $name = '')
	{
		$this->params['replyTo'][$address] = $name;
		$this->mailer->addReplyTo($address, $name);
		return $this;
	}

	/**
	 * Add an attachment from a path on the filesystem.
	 *
	 * @param string $path Path to the attachment
	 * @param string $name Overrides the attachment name
	 *
	 * @return $this mailer object itself
	 */
	public function attachment($path, $name = '')
	{
		$this->params['attachment'][$path] = $name;
		$this->mailer->addAttachment($path, $name);
		return $this;
	}

	/**
	 * Add string attachment.
	 *
	 * @param string $string Attachment data (BASE64 encoded)
	 * @param string $name Attachment name
	 *
	 * @return $this mailer object itself
	 */
	public function stringAttachment($string, $name)
	{
		$this->params['attachment'][$name] = $string;
		$this->mailer->addStringAttachment($string, $name);
		return $this;
	}

	/**
	 * Create a message and send it.
	 *
	 * @return bool
	 */
	public function send(): bool
	{
		if (\App\Config::main('isActiveSendingMails')) { 
			$eventHandler = new EventHandler();
			$eventHandler->setParams(['mailer' => $this]);
			$eventHandler->trigger('MailerBeforeSend');
			$toAddresses = $this->mailer->From . ' >> ' . \print_r($this->mailer->getToAddresses(), true);
			\App\Log::beginProfile("Mailer::send|{$toAddresses}", 'Mail|SMTP');
			if ($this->mailer->send()) {
				\App\Log::endProfile("Mailer::send|{$toAddresses}", 'Mail|SMTP');
				if (!empty($this->smtp['save_send_mail'])) {
					$this->saveMail();
				}
				Log::trace('Mailer sent mail', 'Mailer');
				$eventHandler->trigger('MailerAfterSend');
				return true;
			}
			\App\Log::endProfile("Mailer::send|{$toAddresses}", 'Mail|SMTP');
			Log::error('Mailer Error: ' . \print_r($this->mailer->ErrorInfo, true), 'Mailer');
			if (!empty(static::$error)) {
				static::$error[] = '########################################';
			}
			if (\is_array($this->mailer->ErrorInfo)) {
				foreach ($this->mailer->ErrorInfo as $error) {
					static::$error[] = $error;
				}
			} else {
				static::$error[] = $this->mailer->ErrorInfo;
			}
			$eventHandler->trigger('MailerAfterSendError');
		}
		return false;
	}

	/**
	 * Check connection.
	 *
	 * @return array
	 */
	public function test()
	{
		$this->mailer->SMTPDebug = 2;
		static::$error = [];
		$this->mailer->Debugoutput = function ($str, $level) {
			if (false !== strpos(strtolower($str), 'error') || false !== strpos(strtolower($str), 'failed')) {
				static::$error[] = trim($str);
				Log::error(trim($str), 'Mailer');
			} else {
				Log::trace(trim($str), 'Mailer');
			}
		};
		$currentUser = \Users_Record_Model::getCurrentUserModel();
		$this->to($currentUser->get('email1'));
		$templateId = Mail::getTemplateIdFromSysName('TestMailAboutTheMailServerConfiguration');
		if (!$templateId) {
			return ['result' => false, 'error' => Language::translate('LBL_NO_EMAIL_TEMPLATE')];
		}
		$template = Mail::getTemplate($templateId);
		$textParser = TextParser::getInstanceById($currentUser->getId(), 'Users');
		$this->subject($textParser->setContent($template['subject'])->parse()->getContent());
		$this->content($textParser->setContent($template['content'])->parse()->getContent());
		return ['result' => $this->send(), 'error' => implode(PHP_EOL, static::$error)];
	}

	/**
	 * Send mail by row queue.
	 *
	 * @param array $rowQueue
	 *
	 * @return bool
	 */
	public static function sendByRowQueue($rowQueue, $ignoreStatus = false)
	{
		\App\Log::warning('Mailer::sendByRowQueue:' . var_export(array_diff_key($rowQueue, array_flip(['content'])), true) . ', ignore = ' . ($ignoreStatus ? 'YES' : 'NO'));
		if ('demo' === \App\Config::main('systemMode')) {
			return true;
		}

		$params = !empty($rowQueue['params']) ? \App\Json::decode($rowQueue['params']) : [];
		if (\array_key_exists('params', $params)) {
			$params = $params['params'];
		}
		$params['modified'] = date('Y-m-d H:i:s');

		$db = Db::getInstance('admin');
		$conditions = ['id' => $rowQueue['id']];
		if (!$ignoreStatus) {
			$conditions['status'] = 1;
		}
		$cnt = $db->createCommand()->update('s_#__mail_queue', [
			'status' => 3,
			'params' => \App\Json::encode($params),
		], $conditions)->execute();
		if (!$ignoreStatus && $cnt === 0) {
			\App\Log::error('Mailer::sendByRowQueue:wrong status or missing entry - stopping duplicate send');
			return false;
		}
		try {
			$mailer = (new self())->loadSmtpByID($rowQueue['smtp_id'])->subject($rowQueue['subject'])->content($rowQueue['content']);
			if ($rowQueue['from']) {
				$from = Json::decode($rowQueue['from']);
				$mailer->from($from['email'], $from['name']);
			}
			$status = false;
			$attachmentsToRemove = [];
			if ($rowQueue['attachments']) {
				$attachments = Json::decode($rowQueue['attachments']);
				if (isset($attachments['ids'])) {
					$attachments = array_merge($attachments, Mail::getAttachmentsFromDocument($attachments['ids']));
					unset($attachments['ids']);
				}
				$totalFileSize = 0;
				foreach ($attachments as $path => $name) {
					if (is_numeric($path)) {
						$path = $name;
						$name = '';
					}
					// check if size is not greater than limit specified in configuration and that GDrive integration is enabled
					$totalFileSize += filesize($path);
				}
				if (!empty(\App\Config::gdrive('credentialsFile')) && $totalFileSize > \App\Config::gdrive('maximumAttachmentSize')) {
					$driveLinks = [];
					foreach ($attachments as $path => $name) {
						if (is_numeric($path)) {
							$path = $name;
							$name = '';
						}
						// upload file to GDrive
						$client = new \App\GDrive\Api($mailer->mailer->From);
						['id' => $driveId, 'link' => $driveLink] = $client->uploadFile($path, ['fileName' => $name]);

						// share public 
						$client->shareFile($driveId);

						// and generate special attachments
						$mailer->stringAttachment("[InternetShortcut]\nURL=$driveLink", basename($name ?: $path) . '.url');
						$driveLinks[basename($name ?: $path)] = $driveLink;
					}

					if (!empty($driveLinks)) {
						$driveLinkSummary = "<!DOCTYPE html>
						<html>
						<head>
							<title>{$rowQueue['subject']} - Attachments</title>
							</head>
							<body>
								<ul>\n" . (implode("\n", array_map(function ($key, $val) { return "							<li><a href=\"{$val}\">{$key}</a></li>"; }, array_keys($driveLinks), array_values($driveLinks)))) . "
								</ul>
								</body>
								</html>";
	
						$mailer->stringAttachment($driveLinkSummary, 'attachments.html');
					}
				} else {
					foreach ($attachments as $path => $name) {
						if (is_numeric($path)) {
							$path = $name;
							$name = '';
						}
						// otherwise
						$mailer->attachment($path, $name);
						if (strpos(realpath($path), 'cache' . \DIRECTORY_SEPARATOR)) {
							$attachmentsToRemove[] = $path;
						}
					}
				}
			}
			if (!empty($params)) {
				$mailer->setCustomParams($params);
			}
			if ($mailer->getSmtp('individual_delivery')) {
				foreach (Json::decode($rowQueue['to']) as $email => $name) {
					if (is_numeric($email)) {
						$email = $name;
						$name = '';
					}
					foreach (preg_split('/[,;]/', $email) as $email) {
						$separateMailer = $mailer->cloneMailer();
						$separateMailer->to($email, $name);

						foreach (['cc', 'bcc'] as $key) {
							if ($rowQueue[$key]) {
								foreach (Json::decode($rowQueue[$key]) as $email => $name) {
									if (is_numeric($email)) {
										$email = $name;
										$name = '';
									}
									$separateMailer->{$key}($email, $name);
								}
							}
						}

						$status = $separateMailer->send();
						unset($separateMailer);
						if (!$status) {
							break;
						}
					}
				}
			} else {
				foreach (Json::decode($rowQueue['to']) as $email => $name) {
					if (is_numeric($email)) {
						$email = $name;
						$name = '';
					}
					foreach (preg_split('/[,;]/', $email) as $email) {
						$mailer->to($email, $name);
					}
				}

				foreach (['cc', 'bcc'] as $key) {
					if ($rowQueue[$key]) {
						foreach (Json::decode($rowQueue[$key]) as $email => $name) {
							if (is_numeric($email)) {
								$email = $name;
								$name = '';
							}
							foreach (preg_split('/[,;]/', $email) as $email) {
								$mailer->{$key}($email, $name);
							}
						}
					}
				}

				$status = $mailer->send();
				unset($mailer);
			}
			if ($status) {
				$db->createCommand()->delete('s_#__mail_queue', ['id' => $rowQueue['id']])->execute();
				foreach ($attachmentsToRemove as $file) {
					unlink($file);
				}
			} else {
				$handled = false;
				$error = implode(PHP_EOL, static::$error);
				// if error is quota 
				if (count(array_filter(\App\Config::component('Mail', 'retriableErrors', []), function ($patterns) use ($error) {
					$result = true;
					foreach ($patterns as $pattern) {
						if (stripos($error, $pattern) === false) {
							$result = false;
							break;
						}
					}
					return $result;
				}))) {
					// check if exists alternative SMTP, if so enqueue for that SMTP (add base account to params)
					$baseSmtp = $params['baseSmtp'] ?: $rowQueue['smtp_id'];
					$currentSmtp = $rowQueue['smtp_id'];
					$attemptedSmtps = $params['attemptedSmtps'] ?: [];
					$attemptedSmtps[] = $currentSmtp;

					$params['baseSmtp'] = $baseSmtp;
					$params['attemptedSmtps'] = $attemptedSmtps;

					$nextSmtpId = false;
					$smtps = Mail::getAll();
					$smtp = $smtps[$baseSmtp];
					$from = explode('@', $smtp['from_email']);
					foreach ($smtps as $smtpId => $smtp) {
						if (\in_array($smtpId, $attemptedSmtps)) {
							continue;
						}
						$fromPos1 = stripos($smtp['from_email'], $from[0]);
						$fromPos2 = stripos($smtp['from_email'], $from[1]);
						if ($fromPos1 === 0 && $fromPos2 !== false) {
							$nextSmtpId = $smtpId;
							break;
						}
					}

					if ($nextSmtpId) {
						\App\Log::warning("Retrying using SMTP $nextSmtpId, attempted = " . var_export($attemptedSmtps, true));
						
						$db->createCommand()->update('s_#__mail_queue', [
							'status' => 1,
							'error' => $error,
							'smtp_id' => $nextSmtpId,
							'params' => \App\Json::encode($params),
						], ['id' => $rowQueue['id']])->execute();

						$handled = true;
					}
				}
				
				// if other error or no more SMTPs, mark as failed + batch error
				if (!$handled) {
					// so retry (if applicable) starts from beginning
					$smtpId = $params['baseSmtp'] ?: $rowQueue['smtp_id'];
					unset($params['baseSmtp']);
					unset($params['attemptedSmtps']);

					$db->createCommand()->update('s_#__mail_queue', [
						'status' => 2,
						'error' => $error,
						'smtp_id' => $smtpId,
						'params' => \App\Json::encode($params),
					], ['id' => $rowQueue['id']])->execute();

					// report if matching pattern for mail that should be reported
					self::processMailError($rowQueue, $error, $smtpId);

					if (!empty($params['recordId']) && \App\Record::isExists($params['recordId'])) {
						$recordModel = \Vtiger_Record_Model::getInstanceById($params['recordId']);
						$templateLabel = "directly";
						if ($params['template'] && \App\Record::isExists($params['template'])) {
							$templateLabel = 'using ' . \App\Record::getLabel($params['template']);
						}

						$entry = \Vtiger_Record_Model::getCleanInstance('BatchErrors');
						$entry->set('task_type', 'Email Template');
						$entry->set('task_name', \App\Purifier::decodeHtml(\App\Purifier::purify("Send " . $recordModel->getDisplayName() . " $templateLabel")));
						$entry->set('mod_name', $recordModel->getModuleName());
						$entry->set('item', $recordModel->getId());
						$entry->set('email_template', \is_numeric($params['template']) ? $params['template'] : null);

						if (count(array_filter(\App\Config::component('Mail', 'retriableErrors', []), function ($patterns) use ($error) {
							$result = true;
							foreach ($patterns as $pattern) {
								if (stripos($error, $pattern) === false) {
									$result = false;
									break;
								}
							}
							return $result;
						}))) {
							$entry->set('error_message', \App\Purifier::encodeHtml("SMTP Error. System will retry sending this e-mail in some time."));
						} else {
							$entry->set('error_message', \App\Purifier::encodeHtml("SMTP Error"));
						}
						$entry->set('error_description', implode(PHP_EOL, static::$error));

						$entry->save();
					}
				}

			}
			return $status;
		} catch (\Throwable $e) {
			\App\Log::error("Mailer::sendByRowQueue:ERROR = " . $e->getMessage());
			\APp\Log::error(var_export($e, true));

			$error = $e->getMessage();

			$db->createCommand()->update('s_#__mail_queue', [
				'status' => 2,
				'error' => $error,
			], ['id' => $rowQueue['id']])->execute();

			// report retry attempt if matching pattern for mail that should be reported
			self::processMailError($rowQueue, $error);

			if (!empty($params['recordId']) && \App\Record::isExists($params['recordId'])) {
				$recordModel = \Vtiger_Record_Model::getInstanceById($params['recordId']);
				$templateLabel = "directly";
				if ($params['template'] && \App\Record::isExists($params['template'])) {
					$templateLabel = 'using ' . \App\Record::getLabel($params['template']);
				}

				$entry = \Vtiger_Record_Model::getCleanInstance('BatchErrors');
				$entry->set('task_type', 'Email Template');
				$entry->set('task_name', \App\Purifier::decodeHtml(\App\Purifier::purify("Send " . $recordModel->getDisplayName() . " $templateLabel")));
				$entry->set('mod_name', $recordModel->getModuleName());
				$entry->set('item', $recordModel->getId());
				$entry->set('email_template', $params['template']);

				$entry->set('error_message', \App\Purifier::encodeHtml("SMTP Error"));
				$entry->set('error_description', implode(PHP_EOL, static::$error));

				$entry->save();
			}
		}
	}

	private static function processMailError(array $rowQueue, string $error, ?int $smtpId = null): void {
		try {
			// match subject against \App\Config::component('Mail', 'notificationSubjects', []), case insensitive
			$subject = strtolower($rowQueue['subject']);
			$notificationSubjects = array_map('strtolower', \App\Config::component('Mail', 'notificationSubjects', []));
			$matchedSubject = array_filter($notificationSubjects, function ($pattern) use ($subject) {
				return stripos($subject, $pattern) !== false;
			});

			if (count($matchedSubject) > 0) {
				// prepare e-mail to send using default mailbox to address in \App\Config::component('Mail', 'notificationTarget', '')
				$target = \App\Config::component('Mail', 'notificationTarget', '');
				if ($target) {
					$target = explode(',', $target);
					$target = array_map('trim', $target);
					$target = array_filter($target, 'strlen');

					$smtpData = \App\Mail::getSmtpById($smtpId ?: $rowQueue['smtp_id']);

					$separator = '<br/>' . PHP_EOL;

					$mailer = 
						(new self())
						->loadSmtpByID(\App\Mail::getDefaultSmtp())
						->subject('Error while sending e-mail')
						->content('Error has occurred while sending e-mail. Error message: ' . $error . $separator . $separator
							. 'Subject: ' . $rowQueue['subject'] . $separator
							. 'To: ' . $rowQueue['to'] . $separator
							. 'SMTP: ' . $smtpData['name'] . $separator
							. $separator);
					$mailer->from($mailer->getSmtp('from_email'), $mailer->getSmtp('from_name'));
					foreach ($target as $email) {
						$mailer->to($email);
					}
					$mailer->send();
				}
			}
		} catch (\Throwable $e) {
			\App\Log::error("Mailer::processMailError:ERROR = " . $e->getMessage());
			\APp\Log::error(var_export($e, true));
		}
	}

	/**
	 * Adding additional parameters.
	 *
	 * @param array $params
	 *
	 * @return void
	 */
	public function setCustomParams(array $params): void
	{
		$this->params['params'] = $params;
		if (isset($this->params['ics'])) {
			$this->mailer->Ical = $this->params['ics'];
		}
	}

	/**
	 * Get additional parameters.
	 *
	 * @return array
	 */
	public function getCustomParams(): array
	{
		return $this->params;
	}

	/**
	 * Save sent email.
	 *
	 * @throws \App\Exceptions\AppException
	 *
	 * @return bool
	 */
	public function saveMail()
	{
		if (empty($this->smtp['smtp_username']) && empty($this->smtp['smtp_password']) && empty($this->smtp['smtp_host'])) {
			Log::error('Mailer Error: No smtp data entered', 'Mailer');
			return false;
		}
		$params = [
			'default_port' => $this->smtp['smtp_port'],
			'validate_cert' => !empty($this->smtp['smtp_validate_cert']),
			'imap_max_retries' => 0,
			'imap_params' => [],
			'imap_open_add_connection_type' => true,
		];
		if (\OSSMail_Record_Model::isOauth()) {
			$folder = $this->smtp['smtp_folder'];
			//  get refresh token for account, use imap setting from ossmail config
			$account = \OSSMail_Record_Model::getAccountByUserName($this->smtp['smtp_username']);
			if (!$account) {
				Log::error("Mailer error: OAUTH2 error - could not find account by username {$this->smtp['smtp_username']}");
				return false;
			}
			$mbox = \OSSMail_Record_Model::imapConnect($this->smtp['smtp_username'], Encryption::getInstance()->decrypt($this->smtp['smtp_password']), $this->smtp['smtp_host'], $folder);
			if ($mbox) {
				/** @var \Webklex\PHPIMAP\Folder $mbox */
				$sentMail = $this->mailer->getSentMIMEMessage();
				\App\Log::beginProfile(__METHOD__ . '|imap_append', 'Mail|IMAP');
				try {
					$mbox->appendMessage($sentMail, ["Seen"]);
				} catch (\Throwable $t) {
					Log::error("Mailer Error: OAUTH2 save message error - " . print_r($t, true));
				}
				\App\Log::endProfile(__METHOD__ . '|imap_append', 'Mail|IMAP');
			} else {
				Log::error("Mailer error: OAUTH2 error - could not open mailbox $folder");
				return false;
			}
		} else {
			$folder = Utils::convertCharacterEncoding($this->smtp['smtp_folder'], 'UTF-8', 'UTF7-IMAP');
			$mbox = \OSSMail_Record_Model::imapConnect($this->smtp['smtp_username'], Encryption::getInstance()->decrypt($this->smtp['smtp_password']), $this->smtp['smtp_host'], $folder, false, $params);
			if (false === $mbox && !imap_last_error()) {
				static::$error[] = 'IMAP error - ' . imap_last_error();
				Log::error('Mailer Error: IMAP error - ' . imap_last_error(), 'Mailer');
				return false;
			}
			\App\Log::beginProfile(__METHOD__ . '|imap_append', 'Mail|IMAP');
			imap_append($mbox, \OSSMail_Record_Model::$imapConnectMailbox, $this->mailer->getSentMIMEMessage(), '\\Seen');
			\App\Log::endProfile(__METHOD__ . '|imap_append', 'Mail|IMAP');
		}
		return true;
	}

	/**
	 * Clone the mailer object for individual shipment.
	 *
	 * @return \App\Mailer
	 */
	public function cloneMailer()
	{
		$clonedThis = clone $this;
		$clonedThis->mailer = clone $this->mailer;
		return $clonedThis;
	}
}
