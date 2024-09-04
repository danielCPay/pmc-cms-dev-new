INSERT INTO vtiger_fieldmodulerel 
SELECT 
  r.fieldid, r.module, 'ColoradoCases', r.status, r.sequence
FROM
  vtiger_fieldmodulerel r
WHERE
  r.relmodule = 'Cases'
  AND EXISTS ( SELECT NULL FROM vtiger_fieldmodulerel r1 WHERE r1.fieldid = r.fieldid AND r1.module = r.module AND r1.relmodule = 'CaliforniaCases' );
  
INSERT INTO s_yf_fields_dependency ( tabid, STATUS, NAME, views, gui, mandatory, FIELDS, conditions, conditionsFields )
SELECT
  ( SELECT tabid FROM vtiger_tab WHERE NAME = 'ColoradoCases' ) tabid, STATUS, NAME, views, gui, mandatory, FIELDS, replace(conditions, ':CaliforniaCases', ':ColoradoCases') conditions, conditionsFields
FROM
  s_yf_fields_dependency d
WHERE
  d.tabid = ( SELECT tabid FROM vtiger_tab WHERE NAME = 'CaliforniaCases' );

INSERT INTO com_vtiger_workflows ( module_name, summary, test, execution_condition, defaultworkflow, TYPE, filtersavedinnew, params )
SELECT
  'ColoradoCases', summary, test, execution_condition, defaultworkflow, TYPE, filtersavedinnew, params
FROM
  com_vtiger_workflows 
WHERE
  module_name = 'CaliforniaCases';

INSERT INTO com_vtiger_workflowtasks ( workflow_id, summary, task )
SELECT
  w2.workflow_id, t.summary, REPLACE(t.task, CONCAT(':', w1.workflow_id , ';'), CONCAT(':', w2.workflow_id , ';')) task
FROM 
  com_vtiger_workflows w1
  JOIN com_vtiger_workflows w2 ON ( w2.module_name = 'ColoradoCases' AND w2.summary = w1.summary )
  JOIN com_vtiger_workflowtasks t ON ( t.workflow_id = w1.workflow_id )
WHERE
  w1.module_name = 'CaliforniaCases';
