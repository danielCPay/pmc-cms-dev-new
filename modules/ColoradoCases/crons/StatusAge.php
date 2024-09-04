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
 * ColoradoCases_StatusAge_Cron class.
 */
class ColoradoCases_StatusAge_Cron extends \App\CronHandler
{
  const SERVICE_NAME = 'LBL_CALIFORNIACASES_STATUS_AGE';

  /**
   * {@inheritdoc}
   */
  public function process()
  {
    if (\App\Request::_get('service') === self::SERVICE_NAME) {
      \App\Log::warning("ColoradoCases::cron::ColoradoCases_StatusAge_Cron");

      // status age - jako różnica w dniach między aktualną datą (czasu EST) a datą ColoradoCase.Status Date (czasu EST). 
      // Przy porównaniu zignorować godzinę (ale data powinna być właściwa wg czasu EST).

      // settl_negot_demand_age - na podstawie settl_negot_demand_last_date
      // settl_negot_offer_age - na podstawie settl_negot_offer_last_date

      $db = \App\Db::getInstance();

      \App\Log::warning("ColoradoCases::cron::ColoradoCases_StatusAge_Cron:updating status age");
      $numRows = $db->createCommand('UPDATE u_yf_coloradocases SET status_age = datediff(now(), status_date) WHERE coloradocasesid IN ( SELECT crmid FROM vtiger_crmentity WHERE vtiger_crmentity.setype = \'ColoradoCases\' AND deleted = 0 ) and status != \'\' and status is not null and status_date != \'\' and status_date is not null and status_age != coalesce(datediff(now(), status_date), -1)')->execute();
      \App\Log::warning("ColoradoCases::cron::ColoradoCases_StatusAge_Cron:updating $numRows rows");

      \App\Log::warning("ColoradoCases::cron::ColoradoCases_StatusAge_Cron:updating case age");
      $numRows = $db->createCommand('UPDATE u_yf_coloradocases c SET c.case_age = datediff(NOW(), (SELECT createdtime FROM vtiger_crmentity WHERE crmid = c.coloradocasesid)) WHERE c.final_status != \'CLOSED\' AND c.coloradocasesid IN ( SELECT crmid FROM vtiger_crmentity WHERE vtiger_crmentity.setype = \'ColoradoCases\' AND deleted = 0 )')->execute();
      \App\Log::warning("ColoradoCases::cron::ColoradoCases_StatusAge_Cron:updating $numRows rows");

      \App\Log::warning("ColoradoCases::cron::ColoradoCases_StatusAge_Cron:updating settl_negot_demand age");
      $numRows = $db->createCommand('UPDATE u_yf_coloradocases SET settl_negot_demand_age = datediff(now(), settl_negot_demand_last_date) WHERE coloradocasesid IN ( SELECT crmid FROM vtiger_crmentity WHERE vtiger_crmentity.setype = \'CaliforaniaCases\' AND deleted = 0 ) and status != \'\' and status is not null and settl_negot_demand_last_date != \'\' and settl_negot_demand_last_date is not null and settl_negot_demand_age != coalesce(datediff(now(), settl_negot_demand_last_date), -1)')->execute();
      \App\Log::warning("ColoradoCases::cron::ColoradoCases_StatusAge_Cron:updating $numRows rows");

      \App\Log::warning("ColoradoCases::cron::ColoradoCases_StatusAge_Cron:updating settl_negot_offer_age age");
      $numRows = $db->createCommand('UPDATE u_yf_coloradocases SET settl_negot_offer_age = datediff(now(), settl_negot_offer_last_date) WHERE coloradocasesid IN ( SELECT crmid FROM vtiger_crmentity WHERE vtiger_crmentity.setype = \'ColoradoCases\' AND deleted = 0 ) and status != \'\' and status is not null and settl_negot_offer_last_date != \'\' and settl_negot_offer_last_date is not null and settl_negot_offer_age != coalesce(datediff(now(), settl_negot_offer_last_date), -1)')->execute();
      \App\Log::warning("ColoradoCases::cron::ColoradoCases_StatusAge_Cron:updating $numRows rows");
    }
  }
}
