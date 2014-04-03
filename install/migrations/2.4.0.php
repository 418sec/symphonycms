<?php

	Class migration_240 extends Migration {

		static $publish_filtering_disabled = false;

		static function getVersion(){
			return '2.4beta3';
		}

		static function getReleaseNotes(){
			return 'http://getsymphony.com/download/releases/version/2.4/';
		}

		static function upgrade() {
			// [#702] Update to include Admin Path configuration
			if(version_compare(self::$existing_version, '2.4beta2', '<=')) {
				// Add missing config value for index view string length
				Symphony::Configuration()->set('cell_truncation_length', '75', 'symphony');
				// Add admin-path to configuration
				Symphony::Configuration()->set('admin-path', 'symphony', 'symphony');
			}

			// [#1626] Update all tables to be UTF-8 encoding/collation
			// @link https://gist.github.com/michael-e/5789168
			$tables = Symphony::Database()->fetch("SHOW TABLES");
			if(is_array($tables) && !empty($tables)){
				foreach($tables as $table){
					$table = current($table);

					// If it's not a Symphony table, ignore it
					if(!preg_match('/^' . Symphony::Database()->getPrefix() . '/', $table)) continue;

					Symphony::Database()->query(sprintf(
						"ALTER TABLE `%s` CHARACTER SET utf8 COLLATE utf8_unicode_ci",
						$table
					));
					Symphony::Database()->query(sprintf(
						"ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci",
						$table
					));
				}
			}

			// [#1420] Change date field to be a varchar instead of an ENUM to support prepopulation
			try {
				Symphony::Database()->query('
					ALTER TABLE `tbl_fields_date`
					CHANGE `pre_populate` `pre_populate` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL;
				');
			}
			catch (Exception $ex) {}

			// [#1997] Add filtering column to the Sections table
			if(!Symphony::Database()->tableContainsField('tbl_sections', 'filter')) {
				Symphony::Database()->query("
					ALTER TABLE `tbl_sections`
					ADD `filter` enum('yes','no') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'yes';
				");
			}

			$installed_extensions = Symphony::ExtensionManager()->listInstalledHandles();
			if(in_array('publishfiltering', $installed_extensions)) {
				Symphony::ExtensionManager()->uninstall('publishfiltering');
				self::$publish_filtering_disabled = true;
			}

			// Update the version information
			Symphony::Configuration()->set('version', self::getVersion(), 'symphony');
			Symphony::Configuration()->set('useragent', 'Symphony/' . self::getVersion(), 'general');

			if(Symphony::Configuration()->write() === false) {
				throw new Exception('Failed to write configuration file, please check the file permissions.');
			}
			else {
				return true;
			}
		}

		static function preUpdateNotes(){
			return array(
				__("Symphony 2.4 is a major release that contains breaking changes from previous versions. It is highly recommended to review the releases notes and make a complete backup of your installation before updating as these changes may affect the functionality of your site."),
				__("This release will automatically convert all existing Symphony database tables to %s.", array("<code>utf8_unicode_ci</code>"))
			);
		}

		static function postUpdateNotes(){
			$notes = array();

			if(self::$publish_filtering_disabled) {
				$notes[] = __("As Symphony 2.4 adds the Publish Filtering extension into the core, the standalone extension has been uninstalled. You can remove it from your installation at any time.");
			}

			return $notes;
		}

	}
