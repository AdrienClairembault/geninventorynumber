<?php

/**
 * -------------------------------------------------------------------------
 * GenInventoryNumber plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GenInventoryNumber.
 *
 * GenInventoryNumber is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * GenInventoryNumber is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GenInventoryNumber. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2008-2022 by GenInventoryNumber plugin team.
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/pluginsGLPI/geninventorynumber
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginGeninventorynumberConfigField extends CommonDBChild {

   var $dohistory          = true;
   static public $itemtype = 'PluginGeninventorynumberConfig';
   static public $items_id = 'plugin_geninventorynumber_configs_id';

   static function getTypeName($nb = 0) {
      return __('GLPI\'s inventory items configuration', 'geninventorynumber');
   }

   static function getConfigFieldByItemType($itemtype) {
      $infos = getAllDataFromTable(getTableForItemType(__CLASS__), ['itemtype' => $itemtype]);
      if (!empty($infos)) {
         return array_pop($infos);
      } else {
         return $infos;
      }
   }

   static function install(Migration $migration) {
      global $DB, $GENINVENTORYNUMBER_TYPES;

      $default_charset = DBConnection::getDefaultCharset();
      $default_collation = DBConnection::getDefaultCollation();
      $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

      $table = getTableForItemType(__CLASS__);

      if ($DB->tableExists("glpi_plugin_geninventorynumber_fields")) {
         //Only migrate itemtypes when it's only necessary, otherwise it breaks upgrade procedure !
         $migration->renameTable("glpi_plugin_geninventorynumber_fields", $table);
      }

      if (!$DB->tableExists($table)) {
         $query = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` int {$default_key_sign} NOT NULL auto_increment,
            `plugin_geninventorynumber_configs_id` int {$default_key_sign} NOT NULL default '0',
            `itemtype` varchar(255) DEFAULT '',
            `template` varchar(255) DEFAULT '',
            `is_active` tinyint NOT NULL default '0',
            `use_index` tinyint NOT NULL default '0',
            `index` bigint NOT NULL default '0',
            PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
         $DB->query($query);

      } else {
         $migration->changeField($table, 'ID', 'id', 'autoincrement');
         $migration->changeField($table, 'config_id', 'plugin_geninventorynumber_configs_id', "int {$default_key_sign} NOT NULL default '0'");
         if ($migration->changeField($table, 'device_type', 'itemtype', 'string')) {
            $migration->migrationOneTable($table);
            Plugin::migrateItemType([], ["glpi_displaypreferences"], [$table]);
         }
         $migration->changeField($table, 'enabled', 'is_active', 'boolean');
         $migration->changeField($table, 'use_index', 'use_index', 'boolean');
         $migration->migrationOneTable($table);
      }

      $field = new self();
      foreach ($GENINVENTORYNUMBER_TYPES as $type) {
         if (class_exists($type) && !countElementsInTable($table, ['itemtype' => $type])) {
            $input["plugin_geninventorynumber_configs_id"] = 1;
            $input["itemtype"]                             = $type;
            $input["template"]                             = "&lt;#######&gt;";
            $input["is_active"]                            = 0;
            $input["index"]                                = 0;
            $field->add($input);
         }
      }
   }

   static function uninstall(Migration $migration) {
      $migration->dropTable(getTableForItemType(__CLASS__));
   }

   static function showForConfig($id) {
      global $CFG_GLPI, $DB;

      $config = new PluginGeninventorynumberConfig();
      $config->getFromDB($id);
      $target = Toolbox::getItemTypeFormUrl(__CLASS__);

      echo "<form name='form_core_config' method='post' action=\"$target\">";
      echo "<div align='center'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='5'>" . __('GLPI\'s inventory items configuration', 'geninventorynumber') . "</th></tr>";

      echo "<input type='hidden' name='id' value='$id'>";

      echo "<tr><th colspan='2'>" . __('Generation templates', 'geninventorynumber');
      echo "</th><th>" . __('Active') . "</th>";
      echo "<th>" . __('Use global index', 'geninventorynumber') . "</th>";
      echo "<th colspan='2'>" . __('Global index position', 'geninventorynumber') . "</th></tr>";

      foreach (getAllDataFromTable(getTableForItemType(__CLASS__)) as $value) {
         $itemtype = $value['itemtype'];
         echo "<td class='tab_bg_1' align='center'>" . call_user_func([$itemtype, 'getTypeName']). "</td>";
         echo "<td class='tab_bg_1'>";
         echo "<input type='hidden' name='ids[$itemtype][id]' value='".$value["id"]."'>";
         echo "<input type='hidden' name='ids[$itemtype][itemtype]' value='$itemtype'>";
         echo "<input type='text' name='ids[$itemtype][template]' value=\"" . $value["template"] . "\">";
         echo "</td>";
         echo "<td class='tab_bg_1' align='center'>";
         Dropdown::showYesNo("ids[$itemtype][is_active]", $value["is_active"]);
         echo "</td>";
         echo "<td class='tab_bg_1' align='center'>";
         Dropdown::showYesNo("ids[$itemtype][use_index]", $value["use_index"]);
         echo "</td>";
         echo "<td class='tab_bg_1' align='center'>";
         if ($value["is_active"] && !$value["use_index"]) {
            echo "<input type='text' name='ids[$itemtype][index]' value='" .
            $value['index'] . "' size='12'>";
         }
         echo "</td>";
         echo "</tr>";
      }

      echo "<tr class='tab_bg_1'><td align='center' colspan='5'>";
      echo "<input type='submit' name='update_fields' value=\"" . _sx('button', 'Save') . "\" class='submit'>";
      echo "</td></tr>";

      echo "</table>";
      Html::closeForm();
   }

   static function getEnabledItemTypes() {
      global $DB;
      $query = "SELECT DISTINCT `itemtype`
                FROM `".getTableForItemType(__CLASS__)."`
                ORDER BY `itemtype` ASC";
      $types = [];
      foreach ($DB->request($query) as $data) {
         $types[] = $data['itemtype'];
      }
      return $types;
   }

   static function isActiveForItemType($itemtype) {
      global $DB;
      $query = "SELECT `is_active`
                FROM `".getTableForItemType(__CLASS__)."`
                WHERE `itemtype`='$itemtype'";
      $results = $DB->query($query);
      if ($DB->numrows($results)) {
         return $DB->result($results, 0, 'is_active');
      } else {
         return false;
      }
   }

   static function getNextIndex($itemtype) {
      global $DB;

      $query = "SELECT `index`
                FROM `".getTableForItemType(__CLASS__)."`
                WHERE `itemtype`='$itemtype'";
      $result = $DB->query($query);
      if (!$DB->numrows($result)) {
         return 0;
      } else {
         return ($DB->result($result, 0, "index") + 1);
      }
   }

   static function updateIndex($itemtype) {
      global $DB;
      $query = "UPDATE `".getTableForItemType(__CLASS__)."`
                SET `index`=`index`+1
                WHERE `itemtype`='$itemtype'";
      $DB->query($query);
   }

   static function registerNewItemType($itemtype) {
      if (!class_exists($itemtype)) {
         return;
      }

      if (!countElementsInTable(getTableForItemType(__CLASS__), ['itemtype' => $itemtype])) {
         $config = new self();
         $input["plugin_geninventorynumber_configs_id"] = 1;
         $input["itemtype"]                             = $itemtype;
         $input["template"]                             = "&lt;#######&gt;";
         $input["is_active"]                            = 0;
         $input["index"]                                = 0;
         $config->add($input);
      }
   }

   static function unregisterNewItemType($itemtype) {
      if (countElementsInTable(getTableForItemType(__CLASS__), ['itemtype' => $itemtype])) {
         $config = new self();
         $config->deleteByCriteria(['itemtype' => $itemtype]);
      }
   }
}
