<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */
class ImportException extends Exception
{
    public function __construct(string $msg)
    {
        parent::__construct($msg);
    }
}

class ChecksRegister_Module_Model extends Vtiger_Module_Model
{
  /**
   * Get next batch number for ChecksRegister.
   * 
   * @return int
   */
  public static function getNextBatchNumber()
  {
    \App\Log::warning("ChecksRegister::getNextBatchNumber");

    // get batch number from DB as max( batch_number ) + 1 for ChecksRegister using QueryGenerator
    $batchNumber = ((new \App\QueryGenerator('ChecksRegister'))->createQuery()->max('batch_number') ?: 0) + 1;

    \App\Log::warning("ChecksRegister::getNextBatchNumber:$batchNumber");
    return $batchNumber;
  }

  /**
   * Process Check, either first time or after changes.
   * 
   * @param \Vtiger_Record_Model $recordModel
   */
  public static function processCheck(Vtiger_Record_Model $recordModel)
  {
    $id = $recordModel->getId() ?: "NEW";
    \App\Log::warning("ChecksRegister::processCheck:$id");

    $documentType = (new \App\QueryGenerator('DocumentTypes'))->addCondition('document_type', 'Settlement Checks', 'e')->createQuery()->scalar();

    if ($id !== 'NEW') {
      // clear relations
      $checksRegisterModule = $recordModel->getModule();
      $claimsRelations = \App\Relation::getByModule('ChecksRegister', true, 'Claims');
      $claimsRelationId = reset(array_filter($claimsRelations, function($row) { return strpos($row['label'], 'Similar') === false; }))['relation_id'];
      $relationModel = \Vtiger_Relation_Model::getInstance($checksRegisterModule, Vtiger_Module_Model::getInstance('Claims'), $claimsRelationId);
      foreach (VTWorkflowUtils::getAllRelatedRecords($recordModel, 'Claims') as $relations) {
        $relationModel->deleteRelation($id, $relations['id']);
      }
      $relationModel = \Vtiger_Relation_Model::getInstance($checksRegisterModule, Vtiger_Module_Model::getInstance('Portfolios'));
      foreach (VTWorkflowUtils::getAllRelatedRecords($recordModel, 'Portfolios') as $relations) {
        $relationModel->deleteRelation($id, $relations['id']);
      }
      $relationModel = \Vtiger_Relation_Model::getInstance($checksRegisterModule, Vtiger_Module_Model::getInstance('Documents'));
      foreach (VTWorkflowUtils::getAllRelatedRecords($recordModel, 'Documents', ['document_type' => $documentType]) as $relations) {
        $relationModel->deleteRelation($id, $relations['id']);
        $documentRecordModel = Vtiger_Record_Model::getInstanceById($relations['id']);
        $documentRecordModel->delete();
      }
    }

    $claimNumber = $recordModel->get('claim_number');
    $providerName = $recordModel->get('provider_user');
    $insured = $recordModel->get('insured');
    $insuranceCompanyName = $recordModel->get('insurance_company_user');

    $case = null;
    $warnings = [];

    // get/prepare claim/case cache
    $cacheNameCases = "CheckRegister:Cases";
    $cacheNameOutsideCases = "CheckRegister:OutsideCases";
    $cacheNameClaims = "CheckRegister:Claims";
    $cacheNameProviders = "CheckRegister:Providers";
    $cacheNameInsuranceCompanies = "CheckRegister:InsuranceCompanies";
    if (\App\Cache::has('ChecksRegister', $cacheNameCases)){
      $cases = \App\Cache::get('ChecksRegister', $cacheNameCases);
    } else {
      $queryGenerator = new \App\QueryGenerator('Cases');
      $insuredField = $queryGenerator->getQueryRelatedField('insured_name:Insureds:insured')->getRelated();
      $query = $queryGenerator
        ->setFields(['id', 'case_id', 'claim_number', 'provider', 'provider_2', 'provider_3', 'provider_4', 'provider_5'])
        ->addRelatedField($insuredField)
        ->createQuery();

      $cases = array_map(function ($row) {
        // replace 'claim_number' and 'provider' columns with values with non-alphanumeric characters removed
        $row['claim_number'] = preg_replace('/[^[:alnum:]]+/ui', '', $row['claim_number']);
        $row['insured'] = $row['insuredInsuredsinsured_name'];
        return $row;
      }, $query->all());
      \App\Cache::save('ChecksRegister', $cacheNameCases);
    }
    if (\App\Cache::has('ChecksRegister', $cacheNameOutsideCases)){
      $outsideCases = \App\Cache::get('ChecksRegister', $cacheNameOutsideCases);
    } else {
      $queryGenerator = new \App\QueryGenerator('OutsideCases');
      $insuredField = $queryGenerator->getQueryRelatedField('insured_name:Insureds:insured')->getRelated();
      $query = $queryGenerator
        ->setFields(['id', 'outside_case_id', 'claim_number', 'provider'])
        ->addRelatedField($insuredField)
        ->createQuery();
      $outsideCases = array_map(function ($row) {
        // replace 'claim_number' columns with values with non-alphanumeric characters removed
        $row['claim_number'] = preg_replace('/[^[:alnum:]]+/ui', '', $row['claim_number']);
        $row['insured'] = $row['insuredInsuredsinsured_name'];
        return $row;
      }, $query->all());
      \App\Cache::save('ChecksRegister', $cacheNameOutsideCases);
    }
    if (\App\Cache::has('ChecksRegister', $cacheNameClaims)){
      $claims = \App\Cache::get('ChecksRegister', $cacheNameClaims);
    } else {
      $queryGenerator = new \App\QueryGenerator('Claims');
      $portfolioField = $queryGenerator->getQueryRelatedField('portfolio_id:Portfolios:portfolio')->getRelated();
      $insuredField = $queryGenerator->getQueryRelatedField('insured_name:Insureds:insured')->getRelated();
      $query = $queryGenerator
        ->setFields(['id', 'claim_id', 'claim_number', 'case', 'outside_case', 'provider'])
        ->addRelatedField($portfolioField)
        ->addRelatedField($insuredField)
        ->createQuery();
      $claims = array_map(function ($row) {
        // replace 'claim_number' columns with values with non-alphanumeric characters removed
        $row['id'] = $row['id'];
        $row['claim_number'] = preg_replace('/[^[:alnum:]]+/ui', '', $row['claim_number']);
        $row['portfolio_id'] = $row['portfolioPortfoliosid'];
        $row['portfolio_name'] = $row['portfolioPortfoliosportfolio_id'];
        $row['insured'] = $row['insuredInsuredsinsured_name'];
        return $row;
      }, $query->all());
      \App\Cache::save('ChecksRegister', $cacheNameClaims);
    }
    if (\App\Cache::has('ChecksRegister', $cacheNameProviders)){
      $providers = \App\Cache::get('ChecksRegister', $cacheNameProviders);
    } else {
      $queryGenerator = new \App\QueryGenerator('Providers');
      $query = $queryGenerator
        ->setFields(['id', 'provider_abbreviation', 'provider_name'])
        ->createQuery();
      $providers = array_map(function ($row) {
        $row['provider_abbreviation'] = preg_replace('/[^[:alnum:]]+/ui', '', $row['provider_abbreviation']);
        $row['provider_name'] = preg_replace('/[^[:alnum:]]+/ui', '', $row['provider_name']);
        return $row;
      }, $query->all());
      \App\Cache::save('ChecksRegister', $cacheNameProviders);
    }
    if (\App\Cache::has('ChecksRegister', $cacheNameInsuranceCompanies)){
      $insuranceCompanies = \App\Cache::get('ChecksRegister', $cacheNameInsuranceCompanies);
    } else {
      $queryGenerator = new \App\QueryGenerator('InsuranceCompanies');
      $query = $queryGenerator
        ->setFields(['id', 'insurance_company_name'])
        ->createQuery();
      $insuranceCompanies = array_map(function ($row) {
        $row['insurance_company_name'] = preg_replace('/[^[:alnum:]]+/ui', '', $row['insurance_company_name']);
        return $row;
      }, $query->all());
      $queryGenerator = new \App\QueryGenerator('InsuranceCompanyAliases');
      $query = $queryGenerator
        ->setFields(['id', 'insurance_company_alias', 'insurance_company'])
        ->createQuery();
      // append aliases to insurance companies
      foreach ($query->all() as $row) {
        $insuranceCompanies[] = [
          'id' => $row['insurance_company'],
          'insurance_company_name' => preg_replace('/[^[:alnum:]]+/ui', '', $row['insurance_company_alias'])
        ];
      }
      \App\Cache::save('ChecksRegister', $cacheNameInsuranceCompanies);
    }

    // find match providers
    $processedProvider = preg_replace('/[^[:alnum:]]+/ui', '', $providerName);
    $matchedProviders = array_filter($providers, function($provider) use ($processedProvider) {
      return strcasecmp($provider['provider_abbreviation'], $processedProvider) === 0;
    });
    if (count($matchedProviders) === 0) {
      $matchedProviders = array_filter($providers, function($provider) use ($processedProvider) {
        return strcasecmp($provider['provider_name'], $processedProvider) === 0;
      });
    }
    // report if no provider or more than 1 provider
    if (count($matchedProviders) === 0) {
      $warnings[] = "Provider not found by abbreviation or name";
    } else if (count($matchedProviders) > 1) {
      $warnings[] = "Multiple providers found by abbreviation or name";
    } else {
      $provider = reset($matchedProviders)['id'];
    }

    //	find matching case and claims by provider + claim number
    $processedClaimNumber = preg_replace('/[^[:alnum:]]+/ui', '', $claimNumber);

    // find matching case by provider + claim number
    $cases = array_filter($cases, function($case) use ($processedClaimNumber, $provider) {
      return strcasecmp($case['claim_number'], $processedClaimNumber) === 0 && 
        (($case['provider'] && $case['provider'] === $provider)
        || ($case['provider_2'] && $case['provider_2'] === $provider)
        || ($case['provider_3'] && $case['provider_3'] === $provider)
        || ($case['provider_4'] && $case['provider_4'] === $provider)
        || ($case['provider_5'] && $case['provider_5'] === $provider)
      );
    });
    $outsideCases = array_filter($outsideCases, function($outsideCase) use ($processedClaimNumber, $provider) {
      return strcasecmp($outsideCase['claim_number'], $processedClaimNumber) === 0 && $outsideCase['provider'] === $provider;
    });

    // report if no or more than 1 case
    if (count($cases) === 0 && count($outsideCases) === 0) {
      $warnings[] = "Case (or Outside Case) not found by Provider and Claim Number";
    } else if (count($cases) > 1) {
      $warnings[] = "Multiple cases (" . \App\TextParser::textTruncate(implode(' ', array_map(function ($case) { return $case['case_id']; }, $cases)), 100) . ") found by Provider and Claim Number";
    } else if (count($outsideCases) > 1) {
      $warnings[] = "Multiple outside cases (" . \App\TextParser::textTruncate(implode(' ', array_map(function ($case) { return $case['outside_case_id']; }, $outsideCases)), 100) . ") found by Provider and Claim Number";
    } else if (count($cases) === 1 && count($outsideCases) === 1) {
      $warnings[] = "Both Case (" . reset($cases)['case_id'] . ") and Outside Case (" . reset($outsideCases)['outside_case_id'] . ") found by Provider and Claim Number";
    } else if (count($cases) === 1) {
      $case = reset($cases);
    } else if (count($outsideCases) === 1) {
      $outsideCase = reset($outsideCases);
    } else {
      $warnings[] = "Unexpected problem matching Case or Outside Case by Provider and Claim Number";
    }

    // find matching claims by provider + claim number
    $claims = array_filter($claims, function($claim) use ($processedClaimNumber, $provider) {
      return strcasecmp($claim['claim_number'], $processedClaimNumber) === 0 && $claim['provider'] === $provider;
    });

    // report if no claims or claims do not match case
    if (count($claims) === 0) {
      $warnings[] = "Claim not found by Provider and Claim Number";
    } else if ($case) {
      $mismatchedClaims = array_filter($claims, function($claim) use ($case) { return $claim['case'] != $case['id']; });
      foreach ($mismatchedClaims as $mismatchedClaim) {
        $warnings[] = "Claim '" . \App\Record::getLabel($mismatchedClaim['id']) . "' matched by Provider and Claim Number does not match Case";
      }
    } else if ($outsideCase) {
      $mismatchedClaims = array_filter($claims, function($claim) use ($outsideCase) { return $claim['outside_case'] != $outsideCase['id']; });
      foreach ($mismatchedClaims as $mismatchedClaim) {
        $warnings[] = "Claim '" . \App\Record::getLabel($mismatchedClaim['id']) . "' matched by Provider and Claim Number does not match Outside Case";
      }
    }

    // report if insured doesn't match case or claim
    if ($case) {
      $caseInsured = $case['insured'];
      // compare caseInsured to insured case-insensitive and accent-insensitive
      if (strcasecmp($caseInsured, $insured) !== 0) {
        $warnings[] = "Insured in Case does not match Insured in Check";
      }
    } else if ($outsideCase) {
      $outsideCaseInsured = $outsideCase['insured'];
      // compare outsideCaseInsured to insured case-insensitive and accent-insensitive
      if (strcasecmp($outsideCaseInsured, $insured) !== 0) {
        $warnings[] = "Insured in Outside Case does not match Insured in Check";
      }
    }
    $mismatchedClaims = array_filter($claims, function($claim) use ($insured) { return strcasecmp($claim['insured'], $insured) !== 0; });
    foreach ($mismatchedClaims as $mismatchedClaim) {
      $warnings[] = "Insured in Claim '" . \App\Record::getLabel($mismatchedClaim['id']) . "' does not match Insured in Check";
    }

    //	set claim ids to space separated list of claim labels, set portfolio to space separated list of portfolio labels from claims
    $claimIds = implode(' ', array_map(function($claim) { return $claim['claim_id']; }, $claims));
		$portfolios = implode(' ', array_unique(array_map(function($claim) { return $claim['portfolio_name']; }, $claims)));

    // match insurance company
    $processedInsuranceCompany = preg_replace('/[^[:alnum:]]+/ui', '', $insuranceCompanyName);
    $insuranceCompanies = array_unique(array_map(function ($row) { return $row['id']; }, array_filter($insuranceCompanies, function($insuranceCompany) use ($processedInsuranceCompany) {
      return strcasecmp($insuranceCompany['insurance_company_name'], $processedInsuranceCompany) === 0;
    })));

    // report if no insurance company or more than 1 insurance company
    if (count($insuranceCompanies) === 0) {
      $warnings[] = "Insurance Company not found by name or alias";
    } else if (count($insuranceCompanies) > 1) {
      $warnings[] = "Multiple insurance companies found by name or alias";
    } else {
      $insuranceCompany = reset($insuranceCompanies);
    }

    $recordModel->set('case_id', $case['id']);
    $recordModel->set('outside_case_id', $outsideCase['id']);
    $recordModel->set('claim_ids', \App\TextParser::textTruncate($claimIds, 252, true));
    $recordModel->set('portfolio', \App\TextParser::textTruncate($portfolios, 252, true));
    $recordModel->set('provider', $provider);
    $recordModel->set('insurance_company', $insuranceCompany);
    $recordModel->set('warnings', implode("\n", $warnings));

    $recordModel->save();

    //	setup relations to claims and to portfolios
    $checksRegisterModule = $recordModel->getModule();
    $claimsRelations = \App\Relation::getByModule('ChecksRegister', true, 'Claims');
    $claimsRelationId = reset(array_filter($claimsRelations, function($row) { return strpos($row['label'], 'Similar') === false; }))['relation_id'];
    $relationModel = \Vtiger_Relation_Model::getInstance($checksRegisterModule, Vtiger_Module_Model::getInstance('Claims'), $claimsRelationId);
    \App\Log::warning("ChecksRegister::processCheck:$id:setting relations to " . count($claims) . " claims in relation " . $relationModel->getId());
    foreach ($claims as $claim) {
      // \App\Log::warning("ChecksRegister::processCheck:$id:relation from " . $recordModel->getId() . " to " . $claim['id']);
      $relationModel->addRelation($recordModel->getId(), $claim['id']);
    }
    
    $relationModel = \Vtiger_Relation_Model::getInstance($checksRegisterModule, Vtiger_Module_Model::getInstance('Portfolios'));
    \App\Log::warning("ChecksRegister::processCheck:$id:setting relations to " . count($claims) . " claims portfolios in relation " . $relationModel->getId());
    foreach ($claims as $claim) {
      $relationModel->addRelation($recordModel->getId(), $claim['portfolio_id']);
    }

    // download file from URL in db_link
    $dbLink = $recordModel->get('db_link');
    if ($dbLink) {
      // remove GET parameter dl with it's value and append dl=1
      $dbLink = preg_replace('/\bdl=[^&]*&?/', '', $dbLink);
      $dbLink .= (strpos($dbLink, '?') === false ? '?' : '&') . 'dl=1';

      $params = [];
      $file = \App\Fields\File::loadFromUrl($dbLink, $params, true);
      if ($file && $file->validateAndSecure())
      {
        $params['document_type'] = $documentType;
        $params['checks_register'] = $recordModel->getId();
        ['crmid' => $fileId] = \App\Fields\File::saveFromContent($file, $params);

        // add relation to current module
        $relationModel = Vtiger_Relation_Model::getInstance($checksRegisterModule, Vtiger_Module_Model::getInstance('Documents'));
        if ($relationModel->getRelationType() != Vtiger_Relation_Model::RELATION_O2M || empty($relationModel->getRelationField())) {
          $relationModel->addRelation($recordModel->getId(), $fileId);
        }

        $recordModel->set('check', $fileId);
        $recordModel->save();
      } else if ($file) {
        $file->delete();
      } else {
        throw new ImportException('Error while downloading file');
      }
    }

    self::matchSimilar($recordModel);

    \App\Log::warning("ChecksRegister::processCheck:finished");
  }

  /**
   * Match similar claims and similar cases.
   * 
   * @param \Vtiger_Record_Model $recordModel
   */
  public static function matchSimilar(Vtiger_Record_Model $recordModel)
  {
    $id = $recordModel->getId();
    \App\Log::warning("ChecksRegister::matchSimilar:$id");

    // remove old matches
    $claimsRelations = \App\Relation::getByModule('ChecksRegister', true, 'Claims');
    $claimsRelationId = reset(array_filter($claimsRelations, function($row) { return strpos($row['label'], 'Similar') !== false; }))['relation_id'];
    $similarClaimsRelationModel = \Vtiger_Relation_Model::getInstance($recordModel->getModule(), Vtiger_Module_Model::getInstance('Claims'), $claimsRelationId);
    $similarCasesRelationModel = \Vtiger_Relation_Model::getInstance($recordModel->getModule(), Vtiger_Module_Model::getInstance('Cases'));

    $relationModel = Vtiger_RelationListView_Model::getInstance($recordModel, 'Claims', $claimsRelationId);
    $query = $relationModel->getRelationQuery();
    foreach ($query->all() as $row) {
      $similarClaimsRelationModel->deleteRelation($id, $row['id']);
    }

    $relationModel = Vtiger_RelationListView_Model::getInstance($recordModel, 'Cases');
    $query = $relationModel->getRelationQuery();
    foreach ($query->all() as $row) {
      $similarCasesRelationModel->deleteRelation($id, $row['id']);
    }

    // find new matches, if claim_number is set then use it, otherwise use insured name; remove non-alphanumeric characters for matching
    $claimNumber = $recordModel->get('claim_number');
    $insured = $recordModel->get('insured');
    $processedClaimNumber = preg_replace('/[^[:alnum:]]+/ui', '', $claimNumber);
    $processedInsured = strtolower(str_replace(' ', '', str_replace(',', '', $insured)));

    $claimsQueryGenerator = (new \App\QueryGenerator('Claims'))->setFields(['id', 'claim_id']);
    if (!empty($processedClaimNumber)) {
      $claims = $claimsQueryGenerator->createQuery()->andWhere(["regexp_replace(claim_number, '[^[:alnum:]]+', '')" => $processedClaimNumber])->all();
    } else {
      $insuredNameQueryField = $claimsQueryGenerator->getQueryRelatedField('insured_name:Insureds:insured');
      $insuredNameRelatedField = $insuredNameQueryField->getRelated();
      $claims = $claimsQueryGenerator->addRelatedField($insuredNameRelatedField)->createQuery()->andWhere(["lower(replace(replace({$insuredNameQueryField->getColumnName()}, ',', ''), ' ', ''))" => $processedInsured])->all();
    }
    $recordIds = array_column($claims, 'id');
    $claimIds = implode(' ', array_column($claims, 'claim_id'));
    $claimIds = substr($claimIds, 0, strrpos(substr($claimIds, 0, 255), ' '));
    foreach ($recordIds as $claimId) {
      $similarClaimsRelationModel->addRelation($id, $claimId);
    }

    $casesQueryGenerator = (new \App\QueryGenerator('Cases'))->setFields(['id', 'case_id']);
    if (!empty($processedClaimNumber)) {
      $cases = $casesQueryGenerator->createQuery()->andWhere(["regexp_replace(claim_number, '[^[:alnum:]]+', '')" => $processedClaimNumber])->all();
    } else {
      $insuredNameQueryField = $casesQueryGenerator->getQueryRelatedField('insured_name:Insureds:insured');
      $insuredNameRelatedField = $insuredNameQueryField->getRelated();
      $cases = $casesQueryGenerator->addRelatedField($insuredNameRelatedField)->createQuery()->andWhere(["lower(replace(replace({$insuredNameQueryField->getColumnName()}, ',', ''), ' ', ''))" => $processedInsured])->all();
    }
    $recordIds = array_column($cases, 'id');
    $caseIds = implode(' ', array_column($cases, 'case_id'));
    $caseIds = substr($caseIds, 0, strrpos(substr($caseIds, 0, 255), ' '));
    foreach ($recordIds as $caseId) {
      $similarCasesRelationModel->addRelation($id, $caseId);
    }

    $recordModel->set('similar_claims', $claimIds);
    $recordModel->set('similar_cases', $caseIds);
    $recordModel->save();

    \App\Log::warning("ChecksRegister::matchSimilar:finished");
  }
}
