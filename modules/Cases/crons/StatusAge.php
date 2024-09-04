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
 * Cases_StatusAge_Cron class.
 */
class Cases_StatusAge_Cron extends \App\CronHandler
{
  const SERVICE_NAME = 'LBL_CASES_STATUS_AGE';

  /**
   * {@inheritdoc}
   */
  public function process()
  {
    if (\App\Request::_get('service') === self::SERVICE_NAME) {
      \App\Log::warning("Cases::cron::Cases_StatusAge_Cron");

      // status age - jako różnica w dniach między aktualną datą (czasu EST) a datą Case.Status Date (czasu EST). 
      // Przy porównaniu zignorować godzinę (ale data powinna być właściwa wg czasu EST).

      // settl_negot_demand_age - na podstawie settl_negot_demand_last_date
      // settl_negot_offer_age - na podstawie settl_negot_offer_last_date

      $db = \App\Db::getInstance();

      \App\Log::warning("Cases::cron::Cases_StatusAge_Cron:updating status age");
      $numRows = $db->createCommand('UPDATE u_yf_cases SET status_age = case when final_status = \'CLOSED\' then null else datediff(now(), status_date) end WHERE casesid IN ( SELECT crmid FROM vtiger_crmentity WHERE vtiger_crmentity.setype = \'Cases\' AND deleted = 0 ) and ((final_status != \'CLOSED\' and status_age != datediff(now(), status_date)) or (final_status = \'CLOSED\' and status_age is not null))')->execute();
      \App\Log::warning("Cases::cron::Cases_StatusAge_Cron:updating $numRows rows");

      \App\Log::warning("Cases::cron::Cases_StatusAge_Cron:updating case age");
      $numRows = $db->createCommand('UPDATE u_yf_cases c SET c.case_age = datediff(NOW(), (SELECT createdtime FROM vtiger_crmentity WHERE crmid = c.casesid)) WHERE c.final_status != \'CLOSED\' AND c.casesid IN ( SELECT crmid FROM vtiger_crmentity WHERE vtiger_crmentity.setype = \'Cases\' AND deleted = 0 )')->execute();
      \App\Log::warning("Cases::cron::Cases_StatusAge_Cron:updating $numRows rows");

      \App\Log::warning("Cases::cron::Cases_StatusAge_Cron:updating settl_negot_demand age");
      $numRows = $db->createCommand('UPDATE u_yf_cases SET settl_negot_demand_age = datediff(now(), settl_negot_demand_last_date) WHERE final_status != \'CLOSED\' AND casesid IN ( SELECT crmid FROM vtiger_crmentity WHERE vtiger_crmentity.setype = \'Cases\' AND deleted = 0 ) and settl_negot_demand_age != coalesce(datediff(now(), settl_negot_demand_last_date), -1)')->execute();
      \App\Log::warning("Cases::cron::Cases_StatusAge_Cron:updating $numRows rows");

      \App\Log::warning("Cases::cron::Cases_StatusAge_Cron:updating settl_negot_offer_age age");
      $numRows = $db->createCommand('UPDATE u_yf_cases SET settl_negot_offer_age = datediff(now(), settl_negot_offer_last_date) WHERE final_status != \'CLOSED\' AND casesid IN ( SELECT crmid FROM vtiger_crmentity WHERE vtiger_crmentity.setype = \'Cases\' AND deleted = 0 ) and settl_negot_offer_age != coalesce(datediff(now(), settl_negot_offer_last_date), -1)')->execute();
      \App\Log::warning("Cases::cron::Cases_StatusAge_Cron:updating $numRows rows");
    }
  }
}
