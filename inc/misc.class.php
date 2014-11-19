<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Mreporting plugin for GLPI
 Copyright (C) 2003-2011 by the mreporting Development Team.

 https://forge.indepnet.net/projects/mreporting
 -------------------------------------------------------------------------

 LICENSE

 This file is part of mreporting.

 mreporting is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 mreporting is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with mreporting. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginMreportingMisc {

   static function showNavigation() {
      echo "<div class='center'>";
      echo "<a href='central.php'>".__("Back")."</a>";
      echo "</div>";
   }


   /**
    * Transform a request var into a get string
    * @param  array $var the request string ($_REQUEST, $_POST, $_GET)
    * @return string the imploded array. Format : $key=$value&$key2=$value2...
    */
   static function getRequestString($var) {
      unset($var['submit']);

      return http_build_query($var);
   }


   /**
    * Show a date selector
    * @param  datetime $date1    date of start
    * @param  datetime $date2    date of ending
    * @param  string $randname random string (to prevent conflict in js selection)
    * @return nothing
    */
   static function showSelector($date1, $date2, $randname) {
      global $CFG_GLPI;
      
      $request_string = self::getRequestString($_GET);
      $has_selector   = (isset($_SESSION['mreporting_selector'][$_REQUEST['f_name']]));
      echo "<div class='center'><form method='POST' action='?$request_string' name='form'"
         ." id='mreporting_date_selector'>\n";
      echo "<table class='tab_cadre_fixe'><tr class='tab_bg_1'>";
      
      self::getReportSelectors();

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='2' class='center'>";
      if ($has_selector) {
         echo "<input type='submit' class='button' name='submit' 
                value=\"". _sx('button', 'Post') ."\">";
      }
      $_SERVER['REQUEST_URI'] .= "&date1".$randname."=".$date1;
      $_SERVER['REQUEST_URI'] .= "&date2".$randname."=".$date2;
      Bookmark::showSaveButton(Bookmark::URI);
      
      //If there's no selector for the report, there's no need for a reset button !              
      if ($has_selector) {
         echo "<a href='?$request_string&reset=reset' >";
         echo "&nbsp;&nbsp;<img title=\"".__s('Blank')."\" alt=\"".__s('Blank')."\" src='".
               $CFG_GLPI["root_doc"]."/pics/reset.png' class='calendrier'></a>";
      }
      echo "</td>\n";
      unset($_SESSION['mreporting_selector']);


      echo "</tr>";
      echo "</table>";
      Html::closeForm();
      echo "</div>\n";
   }

   /**
    * Parse and include selectors functions
    */
   static function getReportSelectors() {
      self::addToSelector();
      $graphname = $_REQUEST['f_name'];
      if(!isset($_SESSION['mreporting_selector'][$graphname]) 
         || empty($_SESSION['mreporting_selector'][$graphname])) return;

      $classname = 'PluginMreporting'.$_REQUEST['short_classname'];
      if(!class_exists($classname)) return;

      $i = 2;
      foreach ($_SESSION['mreporting_selector'][$graphname] as $selector) {
         if($i%4 == 0) echo '</tr><tr class="tab_bg_1">';
         $selector = 'selector'.ucfirst($selector);
         if(method_exists('PluginMreportingCommon', $selector)) {
            $classselector = 'PluginMreportingCommon';
         } elseif (method_exists($classname, $selector)) {
            $classselector = $classname;
         } else {
            continue;
         }
      
         $i++;
         echo '<td>';
         $classselector::$selector();
         echo '</td>';
      }
      while($i%4 != 0) {
         $i++;
         echo '<td>&nbsp;</td>';
      }
      
   }

   static function saveSelectors($graphname) {

      $remove = array('short_classname', 'f_name', 'gtype', 'submit');
      $values = array();
      $pref   = new PluginMreportingPreference();

      foreach ($_REQUEST as $key => $value) {
         if (!preg_match("/^_/", $key) && !in_array($key, $remove) ) {
            $values[$key] = $value;
         }
      }
      if (!empty($values)) {
          $id               = $pref->addDefaultPreference(Session::getLoginUserID());
          $tmp['id']        = $id;
          $pref->getFromDB($id);
          if (!is_null($pref->fields['selectors'])) {
            $selectors = $pref->fields['selectors'];
            $sel = json_decode(stripslashes($selectors), true);
            $sel[$graphname] = $values;
          } else {
             $sel = $values;
          }
          $tmp['selectors'] = addslashes(json_encode($sel));
          $pref->update($tmp);
      }
      $_SESSION['mreporting_values'] = $values;
   }

   static function getSelectorValuesByUser() {
      global $DB;

      $myvalues  = (isset($_SESSION['mreporting_values'])?$_SESSION['mreporting_values']:array());
      $selectors = PluginMreportingPreference::checkPreferenceValue('selectors', Session::getLoginUserID());
      if ($selectors) {
         $values = json_decode(stripslashes($selectors), true);
         if (isset($values[$_REQUEST['f_name']])) {
            foreach ($values[$_REQUEST['f_name']] as $key => $value) {
               $myvalues[$key] = $value;
            }
         }
      }
      $_SESSION['mreporting_values'] = $myvalues;
   }

   static function addToSelector() {
      foreach ($_REQUEST as $key => $value) {
         if (!isset($_SESSION['mreporting_values'][$key])) {
             $_SESSION['mreporting_values'][$key] = $value;
         }
      }
   }
   
   static function resetSelectorsForReport($report) {
      global $DB;
      
      $users_id  = Session::getLoginUserID();
      $selectors = PluginMreportingPreference::checkPreferenceValue('selectors', $users_id);
      if ($selectors) {
         $values = json_decode(stripslashes($selectors), true);
         if (isset($values[$report])) {
            unset($values[$report]);
         }
         $sel = addslashes(json_encode($values));
         $query = "UPDATE `glpi_plugin_mreporting_preferences` 
                   SET `selectors`='$sel' 
                   WHERE `users_id`='$users_id'";
         $DB->query($query);
      }
   }
   
   /**
    * Generate a SQL date test with $_REQUEST date fields
    * @param  string  $field     the sql table field to compare
    * @param  integer $delay     if $_REQUET date fields not provided,
    *                            generate them from $delay (in days)
    * @param  string $randname   random string (to prevent conflict in js selection)
    * @return string             The sql test to insert in your query
    */
   static function getSQLDate($field = "`glpi_tickets`.`date`", $delay=365, $randname) {

      if (!isset($_SESSION['mreporting_values']['date1'.$randname]))
         $_SESSION['mreporting_values']['date1'.$randname] = strftime("%Y-%m-%d", time() - ($delay * 24 * 60 * 60));
      if (!isset($_SESSION['mreporting_values']['date2'.$randname]))
         $_SESSION['mreporting_values']['date2'.$randname] = strftime("%Y-%m-%d");

      $date_array1=explode("-",$_SESSION['mreporting_values']['date1'.$randname]);
      $time1=mktime(0,0,0,$date_array1[1],$date_array1[2],$date_array1[0]);

      $date_array2=explode("-",$_SESSION['mreporting_values']['date2'.$randname]);
      $time2=mktime(0,0,0,$date_array2[1],$date_array2[2],$date_array2[0]);

      //if data inverted, reverse it
      if ($time1 > $time2) {
         list($time1, $time2) = array($time2, $time1);
         list($_SESSION['mreporting_values']['date1'.$randname], 
            $_SESSION['mreporting_values']['date2'.$randname]) = array(
            $_SESSION['mreporting_values']['date2'.$randname],
            $_SESSION['mreporting_values']['date1'.$randname]
         );
      }

      $begin=date("Y-m-d H:i:s",$time1);
      $end=date("Y-m-d H:i:s",$time2);

      return "($field >= '$begin' AND $field <= ADDDATE('$end' , INTERVAL 1 DAY) )";
   }


   /**
    * Get the max value of a multidimensionnal array
    * @param  array() $array the array to compute
    * @return number the sum
    */
   static function getArrayMaxValue($array) {
      $max = 0;

      if (!is_array($array)) return $array;

      foreach ($array as $value) {
         if (is_array($value)) {
            $sub_max = self::getArrayMaxValue($value);
            if ($sub_max > $max) $max = $sub_max;
         } else{
            if ($value > $max) $max = $value;
         }
      }

      return $max;
   }


   /**
    * Computes the sum of a multidimensionnal array
    * @param  array() $array the array where to seek
    * @return number the sum
    */
   static function getArraySum($array ) {
      $sum = 0;

      if (!is_array($array)) return $array;
      foreach ($array as $value) {
         if (is_array($value)) {
            $sum+= self::getArraySum($value);
         } else{
            $sum+= $value;
         }
      }

      return $sum;
   }


   /**
    * Get the depth of a multidimensionnal array
    * @param  array() $array the array where to seek
    * @return number the sum
    */
   static function getArrayDepth($array) {
      $max_depth = 1;

      foreach ($array as $value) {
         if (is_array($value)) {
            $depth = self::getArrayDepth($value) + 1;

            if ($depth > $max_depth) {
               $max_depth = $depth;
            }
         }
      }

      return $max_depth;
   }


   /**
    * Transform a flat array to a tree array
    * @param  array $flat_array the flat array. Format : array('id', 'parent', 'name', 'count')
    * @return array the tree array. Format : array(name => array(name2 => array(count), ...)
    */
   static function buildTree($flat_array) {
      $raw_tree = self::mapTree($flat_array);
      $tree = self::cleanTree($raw_tree);
      return $tree;
   }


   /**
    * Transform a flat array to a tree array (without keys changes)
    * @param  array $flat_array the flat array. Format : array('id', 'parent', 'name', 'count')
    * @return array the tree array. Format : array(orginal_keys, children => array(...)
    */
   static function mapTree(array &$elements, $parentId = 0) {
      $branch = array();

      foreach ($elements as $element) {
         if (isset($element['parent']) && $element['parent'] == $parentId) {
            $children = self::mapTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[$element['id']] = $element;
         }
      }
      return $branch;
   }


   /**
    * Transform a tree array to a tree array (with clean keyss)
    * @param  array $flat_array the tree array.
    *               Format : array('id', 'parent', 'name', 'count', children => array(...)
    * @return array the tree array.
    *               Format : array(name => array(name2 => array(count), ...)
    */
   static function cleanTree($raw_tree) {
      $tree = array();

      foreach ($raw_tree as $id => $node) {
         if (isset($node['children'])) {
            $sub = self::cleanTree($node['children']);

            if ($node['count'] > 0) {
               $current = array($node['name'] => intval($node['count']));
               $tree[$node['name']] = array_merge($current, $sub);
            } else {
               $tree[$node['name']] = $sub;
            }
         } else {
            $tree[$node['name']] = intval($node['count']);
         }
      }

      return $tree;
   }
}

