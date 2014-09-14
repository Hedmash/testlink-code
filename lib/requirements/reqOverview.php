<?php

/**
 * 
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later.
 *
 * @package     TestLink
 * @author      Andreas Simon
 * @copyright   2010,2014 TestLink community
 * @filesource  reqOverview.php
 *
 * List requirements with (or without) Custom Field Data in an ExtJS Table.
 * See TICKET 3227 for a more detailed description of this feature.
 * 
 *    
 */

require_once("../../config.inc.php");
require_once("common.php");
require_once('exttable.class.php');
testlinkInitPage($db,false,false,"checkRights");

$cfield_mgr = new cfield_mgr($db);
$templateCfg = templateConfiguration();
$tproject_mgr = new testproject($db);
$req_mgr = new requirement_mgr($db);

$cfg = getCfg();
$args = init_args($tproject_mgr);

$gui = init_gui($args);
$gui->reqIDs = $tproject_mgr->get_all_requirement_ids($args->tproject_id);

$smarty = new TLSmarty();
if(count($gui->reqIDs) > 0) 
{
  $chronoStart = microtime(true);

  $imgSet = $smarty->getImages();
  $gui->warning_msg = '';

  // get type and status labels
  $type_labels = init_labels($cfg->req->type_labels);
  $status_labels = init_labels($cfg->req->status_labels);
  
  $labels2get = array('no' => 'No', 'yes' => 'Yes', 'not_aplicable' => null,'never' => null,
                      'req_spec_short' => null,'title' => null, 'version' => null, 'th_coverage' => null,
                      'frozen' => null, 'type'=> null,'status' => null,'th_relations' => null, 'requirements' => null,
                      'number_of_reqs' => null, 'number_of_versions' => null, 'requirement' => null,
                      'version_revision_tag' => null, 'week_short' => 'calendar_week_short');
          
  $labels = init_labels($labels2get);
  
  $gui->cfields4req = (array)$cfield_mgr->get_linked_cfields_at_design($args->tproject_id, 1, null, 'requirement', null, 'name');
  $gui->processCF = count($gui->cfields4req) > 0;

  $version_option = ($args->all_versions) ? requirement_mgr::ALL_VERSIONS : requirement_mgr::LATEST_VERSION; 

    // array to gather table data row per row
  $rows = array();    
  
  foreach($gui->reqIDs as $id) 
  {
    
    // now get the rest of information for this requirement
    $req = $req_mgr->get_by_id($id, $version_option);
    $tc_coverage = count($req_mgr->get_coverage($id));
    
    // create the link to display
    $title = htmlentities($req[0]['req_doc_id'], ENT_QUOTES, $cfg->charset) . $cfg->glue_char . 
             htmlentities($req[0]['title'], ENT_QUOTES, $cfg->charset);
    
    // reqspec-"path" to requirement
    $path = $req_mgr->tree_mgr->get_path($req[0]['srs_id']);
    foreach ($path as $key => $p) 
    {
      $path[$key] = $p['name'];
    }
    $path = htmlentities(implode("/", $path), ENT_QUOTES, $cfg->charset);
      
    foreach($req as $version) 
    {
      
      // get content for each row to display
      $result = array();
        
      /**
         * IMPORTANT: 
         * the order of following items in this array has to be
         * the same as column headers are below!!!
         * 
         * should be:
         * 1. path
         * 2. title
         * 3. version
         * 4. frozen (is_open attribute)
         * 5. coverage (if enabled)
         * 6. type
         * 7. status
         * 8. relations (if enabled)
         * 9. all custom fields in order of $fields
         */
        
        $result[] = $path;
        
        $edit_link = '<a href="javascript:openLinkedReqVersionWindow(' . $id . ',' . $version['version_id'] . ')">' . 
                     '<img title="' .$labels['requirement'] . '" src="' . $imgSet['edit'] . '" /></a> ';
      
        $result[] =  '<!-- ' . $title . ' -->' . $edit_link . $title;
        
        // version and revision number
        $version_revison = sprintf($labels['version_revision_tag'],$version['version'],$version['revision']);
        $padded_data = sprintf("%05d%05d", $version['version'], $version['revision']);
      
        // use html comment to sort properly by this columns (extjs)
        $result[] = "<!-- $padded_data -->{$version_revison}";
          
        // $dummy necessary to avoid warnings on event viewer because localize_dateOrTimeStamp expects
        // second parameter to be passed by reference
        $dummy = null;
          
        // use html comment to sort properly by this columns (extjs)
        $result[] = "<!--{$version['creation_ts']}-->" .
                    localize_dateOrTimeStamp(null, $dummy, 'timestamp_format', $version['creation_ts']) .
                    " ({$version['author']})";
      
        // on requirement creation motification timestamp is set to default value "0000-00-00 00:00:00"
        $never_modified = "0000-00-00 00:00:00";

        // use html comment to sort properly by this columns (extjs)
        $modification_ts = "<!-- 0 -->" . $labels['never'];
        if( !is_null($version['modification_ts']) && ($version['modification_ts'] != $never_modified) )
        {
          $modification_ts = "<!--{$version['modification_ts']}-->" .
                             localize_dateOrTimeStamp(null, $dummy, 'timestamp_format',$version['modification_ts']) . 
                             " ({$version['modifier']})";
        }
        $result[] = $modification_ts;
        
        // is it frozen?
        $result[] = ($version['is_open']) ? $labels['no'] : $labels['yes'];
        
        // coverage
        // use html comment to sort properly by this columns (extjs)
        if($cfg->req->expected_coverage_management) 
        {
          $expected = $version['expected_coverage'];
          $coverage_string = "<!-- -1 -->" . $labels['not_aplicable'] . " ($tc_coverage/0)";
          if ($expected > 0) 
          {
            $percentage = round(100 / $expected * $tc_coverage, 2);
            $padded_data = sprintf("%010d", $percentage); //bring all percentages to same length
            $coverage_string = "<!-- $padded_data --> {$percentage}% ({$tc_coverage}/{$expected})";
          }
          $result[] = $coverage_string;
        }
        
        $result[] = isset($type_labels[$version['type']]) ? $type_labels[$version['type']] : '';
        $result[] = isset($status_labels[$version['status']]) ? $status_labels[$version['status']] : '';
      
        if ($cfg->req->relations->enable) 
        {
          $relations = $req_mgr->count_relations($id);
          $result[] = "<!-- " . sprintf("%010d", $relations) . " -->" . $relations;
        }
     
      
        if($gui->processCF)
        {
          // get custom field values for this req version
          $linked_cfields = (array)$req_mgr->get_linked_cfields($id,$version['version_id']);

          foreach ($linked_cfields as $cf) 
          {
            $verbose_type = trim($req_mgr->cfield_mgr->custom_field_types[$cf['type']]);
            $value = preg_replace('!\s+!', ' ', htmlspecialchars($cf['value'], ENT_QUOTES, $cfg->charset));
            if( ($verbose_type == 'date' || $verbose_type == 'datetime') && is_numeric($value) && $value != 0 )
            {
              $value = strftime( $cfg->$verbose_type . " ({$label['week_short']} %W)" , $value);
            }  

            $result[] = $value;
          }
        }  
        
        $rows[] = $result;
      }
    }

    // -------------------------------------------------------------------------------------------------- 
    // Construction of EXT-JS table starts here    
    if(($gui->row_qty = count($rows)) > 0 ) 
    {
      $version_string = ($args->all_versions) ? $labels['number_of_versions'] : $labels['number_of_reqs'];
      $gui->pageTitle .= " - " . $version_string . ": " . $gui->row_qty;
        
     /**
       * get column header titles for the table
       * 
       * IMPORTANT: 
       * the order of following items in this array has to be
       * the same as row content above!!!
       * 
       * should be:
       * 1. path
       * 2. title
       * 3. version
       * 4. frozen
       * 5. coverage (if enabled)
       * 6. type
       * 7. status
       * 8. relations (if enabled)
       * 9. then all custom fields in order of $fields
       */
      $columns = array();
      $columns[] = array('title_key' => 'req_spec_short', 'width' => 200);
      $columns[] = array('title_key' => 'title', 'width' => 150);
      $columns[] = array('title_key' => 'version', 'width' => 30);
      $columns[] = array('title_key' => 'created_on', 'width' => 55);
      $columns[] = array('title_key' => 'modified_on','width' => 55);
        
      $frozen_for_filter = array($labels['yes'],$labels['no']);
      $columns[] = array('title_key' => 'frozen', 'width' => 30, 'filter' => 'list',
                         'filterOptions' => $frozen_for_filter);
        
      if($cfg->req->expected_coverage_management) 
      {
        $columns[] = array('title_key' => 'th_coverage', 'width' => 80);
      }
              
      $columns[] = array('title_key' => 'type', 'width' => 60, 'filter' => 'list',
                           'filterOptions' => $type_labels);
      $columns[] = array('title_key' => 'status', 'width' => 60, 'filter' => 'list',
                           'filterOptions' => $status_labels);
      
      if ($cfg->req->relations->enable) 
      {
        $columns[] = array('title_key' => 'th_relations', 'width' => 50, 'filter' => 'numeric');
      }
        
      foreach($gui->cfields4req as $cf) 
      {
        $columns[] = array('title' => htmlentities($cf['label'], ENT_QUOTES, $cfg->charset), 'type' => 'text',
                           'col_id' => 'id_cf_' .$cf['name']);
      }

      // create table object, fill it with columns and row data and give it a title
      $matrix = new tlExtTable($columns, $rows, 'tl_table_req_overview');
      $matrix->title = $labels['requirements'];
        
      // group by Req Spec
      $matrix->setGroupByColumnName($labels['req_spec_short']);
        
      // sort by coverage descending if enabled, otherwise by status
      $sort_name = ($cfg->req->expected_coverage_management) ? $labels['th_coverage'] : $labels['status'];
      $matrix->setSortByColumnName($sort_name);
      $matrix->sortDirection = 'DESC';
        
      // define toolbar
      $matrix->showToolbar = true;
      $matrix->toolbarExpandCollapseGroupsButton = true;
      $matrix->toolbarShowAllColumnsButton = true;
      $matrix->toolbarRefreshButton = true;
      $matrix->showGroupItemsCount = true;
      // show custom field content in multiple lines
      $matrix->addCustomBehaviour('text', array('render' => 'columnWrap'));
      $gui->tableSet= array($matrix);
    }

    $chronoStop = microtime(true);
    $gui->elapsedSeconds = round($chronoStop - $chronoStart);
} 


$smarty->assign('gui',$gui);
$smarty->display($templateCfg->template_dir . $templateCfg->default_template);


/**
 * initialize user input
 * 
 * @param resource &$tproject_mgr reference to testproject manager
 * @return array $args array with user input information
 */
function init_args(&$tproject_mgr)
{
  $args = new stdClass();

  $all_versions = isset($_REQUEST['all_versions']) ? true : false;
  $all_versions_hidden = isset($_REQUEST['all_versions_hidden']) ? true : false;
  if ($all_versions) {
    $selection = true;
  } else if ($all_versions_hidden) {
    $selection = false;
  } else if (isset($_SESSION['all_versions'])) {
    $selection = $_SESSION['all_versions'];
  } else {
    $selection = false;
  }
  $args->all_versions = $_SESSION['all_versions'] = $selection;
  
  $args->tproject_id = intval(isset($_SESSION['testprojectID']) ? $_SESSION['testprojectID'] : 0);
  $args->tproject_name = isset($_SESSION['testprojectName']) ? $_SESSION['testprojectName'] : '';
  if($args->tproject_id > 0) 
  {
    $tproject_info = $tproject_mgr->get_by_id($args->tproject_id);
    $args->tproject_name = $tproject_info['name'];
    $args->tproject_description = $tproject_info['notes'];
  }
  
  return $args;
}


/**
 * initialize GUI
 * 
 * @param stdClass $argsObj reference to user input
 * @return stdClass $gui gui data
 */
function init_gui(&$argsObj) 
{
  $gui = new stdClass();
  
  $gui->pageTitle = lang_get('caption_req_overview');
  $gui->warning_msg = lang_get('no_linked_req');
  $gui->tproject_name = $argsObj->tproject_name;
  $gui->all_versions = $argsObj->all_versions;
  $gui->tableSet = null;
  
  return $gui;
}


/**
 *
 */
function getCfg()
{
  $cfg = new stdClass();
  $cfg->glue_char = config_get('gui_title_separator_1');
  $cfg->charset = config_get('charset');
  $cfg->req = config_get('req_cfg');
  $cfg->date = config_get('date_format');
  $cfg->datetime = config_get('timestamp_format');


  $cfg->edit_img = TL_THEME_IMG_DIR . "edit_icon.png";
  return $cfg;
}


/*
 * rights check function for testlinkInitPage()
 */
function checkRights(&$db, &$user)
{
  return $user->hasRight($db,'mgt_view_req');
}

