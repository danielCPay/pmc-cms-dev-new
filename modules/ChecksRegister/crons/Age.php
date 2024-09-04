<?php

/**
 * Ages records
 *
 * @package   Cron
 *
 * @copyright DOT Systems sp. z o.o
 * @license YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author Nichał Kamiński <mkaminski@dotsystems.pl>
 */

/**
 * ChecksRegister_Age_Cron class.
 */
class ChecksRegister_Age_Cron extends \App\CronHandler
{
  const SERVICE_NAME = 'LBL_CHECKSREGISTER_AGE';

  /**
   * {@inheritdoc}
   */
  public function process()
  {
    if (\App\Request::_get('service') === self::SERVICE_NAME) {
      \App\Log::warning("ChecksRegister::cron::ChecksRegister_Age_Cron");

      $db = \App\Db::getInstance();

      \App\Log::warning("ChecksRegister::cron::ChecksRegister_Age_Cron:updating status age");
      $numRows = $db->createCommand('UPDATE u_yf_checksregister SET check_age = datediff(now(), scan_date) WHERE checksregisterid IN ( SELECT crmid FROM vtiger_crmentity WHERE vtiger_crmentity.setype = \'ChecksRegister\' AND deleted = 0 ) and collection_created = 0 and scan_date != \'\' and scan_date is not null and check_age != coalesce(datediff(now(), scan_date), -1)')->execute();
      \App\Log::warning("ChecksRegister::cron::ChecksRegister_Age_Cron:updating $numRows rows");
    }
  }
}
