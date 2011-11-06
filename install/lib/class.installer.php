<?php

	require_once(CORE . '/class.symphony.php');
	require_once(CORE . '/class.errorhandler.php');
	require_once(CORE . '/class.log.php');
	require_once(CORE . '/class.configuration.php');
	require_once(CORE . '/class.datetimeobj.php');

	require_once(TOOLKIT . '/class.general.php');
	require_once(TOOLKIT . '/class.lang.php');
	require_once(TOOLKIT . '/class.mysql.php');
	require_once(TOOLKIT . '/class.xmlelement.php');
	require_once(TOOLKIT . '/class.widget.php');

	require_once(INSTALL . '/lib/class.installerpage.php');

	Class Installer {

		private static $_page;

		private static $_log;

		private static $_conf;

		private static $_db;

		public static function run(){

			// Initialize everything that is needed
			self::__initialize();

			// Check if Symphony is already installed
			if(false || file_exists(DOCROOT . '/manifest/config.php')){
				self::$_log->pushToLog(
					sprintf('Installer - Existing Symphony Installation'),
					E_ERROR, true
				);

				self::__render(new InstallerPage('existing'));
			}

			// Check essential requirements
			$errors = self::__checkRequirements();

			if(!empty($errors)){
				self::$_log->pushToLog(
					sprintf('Installer - Missing requirements.'),
					E_ERROR, true
				);

				foreach($errors as $err){
					self::$_log->pushToLog(
						sprintf('Requirement - %s', $err['msg']),
						E_ERROR, true
					);
				}

				self::__render(new InstallerPage('requirements', array('errors' => $errors)));
			}

			// If the user switch language while compiling the form, make sure
			// the form values are preserved
			if(!isset($_POST['fields']) && file_exists(INSTALL . '/includes/config_tmp.php')){
				include_once(INSTALL . '/includes/config_tmp.php');
				$_POST['fields'] = $settings;
			}

			// Check for configuration errors and, if there are no errors, install Symphony!
			if(isset($_POST['fields'])) {
				$errors = self::__checkConfiguration();

				if(!empty($errors)){
					General::writeFile(INSTALL . '/includes/config_tmp.php', General::array_to_string($_POST['fields']));

					self::$_log->pushToLog(
						sprintf('Installer - Wrong configuration.'),
						E_ERROR, true
					);

					foreach($errors as $err){
						self::$_log->pushToLog(
							sprintf('Configuration - %s', $err['msg']),
							E_ERROR, true
						);
					}
				}
				else{

					// At this point form values don't need to be preserved anymore
					General::deleteFile(INSTALL . '/includes/config_tmp.php');

					$disabled_extensions = self::__install();

					self::__render(new InstallerPage('success', array(
						'disabled-extensions' => $disabled_extensions
					)));
				}
			}

			// Display the Installation page
			self::__render(new InstallerPage('configuration', array(
				'errors' => $errors,
				'default-config' => self::$_conf->get()
			)));
		}

		private static function __initialize(){

			// Initialize language
			$lang = 'en';

			if(!empty($_REQUEST['lang'])){
				$lang = preg_replace('/[^a-zA-Z\-]/', NULL, $_REQUEST['lang']);
			}

			Lang::initialize();
			Lang::set($lang, false);

			// Initialize configuration
			include_once(INSTALL . '/includes/defaultconfig.php'); // $conf
			Symphony::initialiseConfiguration($conf);
			self::$_conf = Symphony::Configuration();

			// Initialize log
			Symphony::initialiseLog(false);
			self::$_log = Symphony::Log();

			// Initialize Database
			self::$_db = new MySQL;

		}

		private static function __checkRequirements(){
			$errors = array();

			// Check for PHP 5.2+
			if(false || version_compare(phpversion(), '5.2', '<=')){
				$errors[] = array(
					'msg' => __('PHP Version is not correct'),
					'details' => __('Symphony requires %1$s or greater to work, however version %2$s was detected.', array('<code><abbr title="PHP: Hypertext Pre-processor">PHP</abbr> 5.2</code>', '<code>' . phpversion() . '</code>'))
				);
			}

			// Make sure the install.sql file exists
			if(false || !file_exists(INSTALL . '/includes/install.sql') || !is_readable(INSTALL . '/includes/install.sql')){
				$errors[] = array(
					'msg' => __('Missing install.sql file'),
					'details'  => __('It appears that %s is either missing or not readable. This is required to populate the database and must be uploaded before installation can commence. Ensure that PHP has read permissions.', array('<code>install.sql</code>'))
				);
			}

			// Is MySQL available?
			if(false || !function_exists('mysql_connect')){
				$errors[] = array(
					'msg' => __('MySQL extension not present'),
					'details'  => __('Symphony requires MySQL to work.')
				);
			}

			// Is ZLib available?
			if(!extension_loaded('zlib')){
				$errors[] = array(
					'msg' => __('ZLib extension not present'),
					'details' => __('Symphony needs the ZLib compression library to decompress data retrieved from the Symphony support server.')
				);
			}

			// Is libxml available?
			if(!extension_loaded('xml') && !extension_loaded('libxml')){
				$errors[] = array(
					'msg' => __('XML extension not present'),
					'details'  => __('Symphony needs the XML extension to pass data to the site frontend.')
				);
			}

			// Is libxslt available?
			if(!extension_loaded('xsl') && !extension_loaded('xslt') && !function_exists('domxml_xslt_stylesheet')){
				$errors[] = array(
					'msg' => __('XSLT extension not present'),
					'details'  => __('Symphony needs an XSLT processor such as %s or Sablotron to build pages.', array('Lib<abbr title="eXtensible Stylesheet Language Transformation">XSLT</abbr>'))
				);
			}

			return $errors;
		}

		private static function __checkConfiguration(){
			$errors = array();
			$fields = $_POST['fields'];

			// Invalid path
			if(!is_dir(rtrim($fields['docroot'], '/') . '/symphony')){
				$errors['no-symphony-dir'] = array(
					'msg' => 'Bad Document Root Specified: ' . $fields['docroot'],
					'details' => __('No %s directory was found at this location. Please upload the contents of Symphony’s install package here.', array('<code>/symphony</code>'))
				);
			}

			else{

				// Cannot write to root folder.
				if(!is_writable(rtrim($fields['docroot'], '/'))){
					$errors['no-write-permission-root'] = array(
						'msg' => 'Root folder not writable: ' . $fields['docroot'],
						'details' => __('Symphony does not have write permission to the root directory. Please modify permission settings on this directory. This is necessary only if you are not including a workspace, and can be reverted once installation is complete.')
					);
				}

				// Cannot write to workspace
				if(is_dir(rtrim($fields['docroot'], '/') . '/workspace') && !is_writable(rtrim($fields['docroot'], '/') . '/workspace')){
					$errors['no-write-permission-workspace'] = array(
						'msg' => 'Workspace folder not writable: ' . $fields['docroot'] . '/workspace',
						'details' => __('Symphony does not have write permission to the existing %1$s directory. Please modify permission settings on this directory and its contents to allow this, such as with a recursive %2$s command.', array('<code>/workspace</code>', '<code>chmod -R</code>'))
					);
				}

			}

			// Testing the database connection
			try{
				self::$_db->connect(
					$fields['database']['host'],
					$fields['database']['user'],
					$fields['database']['password'],
					$fields['database']['port']
				);
			}
			catch(DatabaseException $e){
				$errors['no-database-connection'] = array(
					'msg' => 'Could not establish database connection',
					'details' => __('Symphony was unable to establish a valid database connection. You may need to modify username, password, host or port settings.')
				);
			}

			try{

				// Looking for the given database name
				self::$_db->select($fields['database']['db']);

				// Incorrect MySQL version
				$version = self::$_db->fetchVar('version', 0, "SELECT VERSION() AS `version`;");

				if(version_compare($version, '5.0', '<')){
					$errors['database-incorrect-version']  = array(
						'msg' => 'MySQL Version is not correct. '. $version . ' detected.',
						'details' => __('Symphony requires %1$s or greater to work, however version %2$s was detected. This requirement must be met before installation can proceed.', array('<code>MySQL 5.0</code>', '<code>' . $version . '</code>'))
					);
				}

				else{

					// Existing table prefix
					$tables = self::$_db->fetch(sprintf(
						"SHOW TABLES FROM `%s` LIKE '%s'",
						mysql_escape_string($fields['database']['db']),
						mysql_escape_string($fields['database']['tbl_prefix']) . '%'
					));

					if(is_array($tables) && !empty($tables) && $fields['database']['drop-tables'] == 'no'){
						$errors['database-table-clash']  = array(
							'msg' => 'Database table prefix clash with ‘' . $fields['database']['db'] . '’',
							'details' =>  __('The table prefix %s is already in use. Please choose a different prefix to use with Symphony.', array('<code>' . $fields['database']['tbl_prefix'] . '</code>'))
						);
					}

				}

			}
			catch(DatabaseException $e){
					$errors['unknown-database']  = array(
						'msg' => 'Database ‘' . $fields['database']['db'] . '’ not found.',
						'details' =>  __('Symphony was unable to connect to the specified database.')
					);
			}

			// Website name not entered
			if(trim($fields['general']['sitename']) == ''){
				$errors['general-no-sitename']  = array(
					'msg' => 'No sitename entered.',
					'details' => __('You must enter a Site name. This will be shown at the top of your backend.')
				);
			}

			// Username Not Entered
			if(trim($fields['user']['username']) == ''){
				$errors['user-no-username']  = array(
					'msg' => 'No username entered.',
					'details' => __('You must enter a Username. This will be your Symphony login information.')
				);
			}

			// Password Not Entered
			if(trim($fields['user']['password']) == ''){
				$errors['user-no-password']  = array(
					'msg' => 'No password entered.',
					'details' => __('You must enter a Password. This will be your Symphony login information.')
				);
			}

			// Password mismatch
			elseif($fields['user']['password'] != $fields['user']['confirm-password']){
				$errors['user-password-mismatch']  = array(
					'msg' => 'Passwords did not match.',
					'details' => __('The password and confirmation did not match. Please retype your password.')
				);
			}

			// No Name entered
			if(trim($fields['user']['firstname']) == '' || trim($fields['user']['lastname']) == ''){
				$errors['user-no-name']  = array(
					'msg' => 'Did not enter First and Last names.',
					'details' =>  __('You must enter your name.')
				);
			}

			// Invalid Email
			if(!preg_match('/^\w(?:\.?[\w%+-]+)*@\w(?:[\w-]*\.)+?[a-z]{2,}$/i', $fields['user']['email'])){
				$errors['user-invalid-email']  = array(
					'msg' => 'Invalid email address supplied.',
					'details' =>  __('This is not a valid email address. You must provide an email address since you will need it if you forget your password.')
				);
			}

			return $errors;
		}

		private static function __abort($message, $start){
			self::$_log->pushToLog($message, E_ERROR, true);

			self::$_log->writeToLog(        '============================================', true);
			self::$_log->writeToLog(sprintf('INSTALLATION ABORTED: Execution Time - %d sec (%s)', 
				max(1, time() - $start),
				date('d.m.y H:i:s')
			), true);
			self::$_log->writeToLog(        '============================================' . PHP_EOL . PHP_EOL . PHP_EOL, true);

#			if($fields['database']['drop-tables'] == 'yes'){

#				// MySQL: Drop existing tables
#				self::$_log->pushToLog('MYSQL: Drop existing tables...', E_NOTICE, true, true);

#				try{
#					$tables = self::$_db->fetch(sprintf(
#						"SHOW TABLES FROM `%s` LIKE '%s'",
#						mysql_escape_string($fields['database']['name']),
#						mysql_escape_string($fields['database']['tbl_prefix']) . '%'
#					));

#					foreach($tables as $table){
#						$_db->query('DROP TABLE IF EXISTS ' . $table);
#					}
#				}
#				catch(DatabaseException $e){
#					$error = self::$_db->getLastError();
#					self::__abort(
#						'There was an error while trying to removing tables from the database. MySQL returned: ' . $error['num'] . ': ' . $error['msg'],
#					$start);
#				}
#			}

			self::__render(new InstallerPage('failure'));
		}

		private static function __install(){
			$fields = $_POST['fields'];
			$errors = array();
			$start = time();

			self::$_log->writeToLog(PHP_EOL . '============================================', true);
			self::$_log->writeToLog(          'INSTALLATION PROCESS STARTED (' . DateTimeObj::get('c') . ')', true);
			self::$_log->writeToLog(          '============================================', true);

			// MySQL: Establishing connection
			self::$_log->pushToLog('MYSQL: Establishing Connection', E_NOTICE, true, true);

			try{
				self::$_db->connect(
					$fields['database']['host'],
					$fields['database']['user'],
					$fields['database']['password'],
					$fields['database']['port']
				);
			}
			catch(DatabaseException $e){
				self::__abort(
					'There was a problem while trying to establish a connection to the MySQL server. Please check your settings.',
				$start);
			}

			// MySQL: Selecting database
			self::$_log->pushToLog('MYSQL: Selecting Database ‘' . $fields['database']['db'] . '’...', E_NOTICE, true, true);

			try{
				self::$_db->select($fields['database']['db']);
			}
			catch(DatabaseException $e){
				self::__abort(
					'Could not connect to specified database. Please check your settings.',
				$start);
			}

			// MySQL: Setting prefix & character encoding
			self::$_db->setPrefix($fields['database']['tbl_prefix']);
			self::$_db->setCharacterEncoding();
			self::$_db->setCharacterSet();

			// MySQL: Importing schema
			self::$_log->pushToLog('MYSQL: Importing Table Schema', E_NOTICE, true, true);

			try{
				self::$_db->import(
					file_get_contents(INSTALL . '/includes/install.sql'),
					($fields['database']['use-server-encoding'] != 'yes' ? true : false),
					true
				);
			}
			catch(DatabaseException $e){
				$error = self::$_db->getLastError();
				self::__abort(
					'There was an error while trying to import data to the database. MySQL returned: ' . $error['num'] . ': ' . $error['msg'],
				$start);
			}

			// MySQL: Creating default author
			self::$_log->pushToLog('MYSQL: Creating Default Author', E_NOTICE, true, true);

			try{
				self::$_db->insert(array(
					'id' 					=> 1,
					'username' 				=> self::$_db->cleanValue($fields['user']['username']),
					'password' 				=> sha1(self::$_db->cleanValue($fields['user']['password'])),
					'first_name' 			=> self::$_db->cleanValue($fields['user']['firstname']),
					'last_name' 			=> self::$_db->cleanValue($fields['user']['lastname']),
					'email' 				=> self::$_db->cleanValue($fields['user']['email']),
					'last_seen' 			=> NULL,
					'user_type' 			=> 'developer',
					'primary' 				=> 'yes',
					'default_area' 			=> NULL,
					'auth_token_active' 	=> 'no'
				), 'tbl_authors');
			}
			catch(DatabaseException $e){
				$error = self::$_db->getLastError();
				self::__abort(
					'There was an error while trying create the default author. MySQL returned: ' . $error['num'] . ': ' . $error['msg'],
				$start);
			}

			// Configuration: Populating array
			$conf = self::$_conf->get();

			foreach($conf as $group => $settings){
				foreach($settings as $key => $value){
					if(isset($fields[$group]) && isset($fields[$group][$key])){
						$conf[$group][$key] = $fields[$group][$key];
					}
				}
			}

			// Create manifest folder structure
			self::$_log->pushToLog('WRITING: Creating ‘manifest’ folder (/manifest)', E_NOTICE, true, true);
			if(!General::realiseDirectory(DOCROOT . '/manifest', $conf['directory']['write_mode'])){
				self::__abort(
					'Could not create ‘manifest’ directory. Check permission on the root folder.',
				$start);
			}

			self::$_log->pushToLog('WRITING: Creating ‘logs’ folder (/manifest/logs)', E_NOTICE, true, true);
			if(!General::realiseDirectory(DOCROOT . '/manifest/logs', $conf['directory']['write_mode'])){
				self::__abort(
					'Could not create ‘logs’ directory. Check permission on /manifest.',
				$start);
			}

			self::$_log->pushToLog('WRITING: Creating ‘cache’ folder (/manifest/cache)', E_NOTICE, true, true);
			if(!General::realiseDirectory(DOCROOT . '/manifest/cache', $conf['directory']['write_mode'])){
				self::__abort(
					'Could not create ‘cache’ directory. Check permission on /manifest.',
				$start);
			}

			self::$_log->pushToLog('WRITING: Creating ‘tmp’ folder (/manifest/tmp)', E_NOTICE, true, true);
			if(!General::realiseDirectory(DOCROOT . '/manifest/tmp', $conf['directory']['write_mode'])){
				self::__abort(
					'Could not create ‘tmp’ directory. Check permission on /manifest.',
				$start);
			}

			// Writing configuration file
			self::$_log->pushToLog('WRITING: Configuration File', E_NOTICE, true, true);

			self::$_conf->setArray($conf);

			if(!self::$_conf->write($conf['file']['write_mode'])){
				self::__abort(
					'Could not create config file ‘' . CONFIG . '’. Check permission on /manifest.',
				$start);
			}

			// Writing htaccess file
			self::$_log->pushToLog('CONFIGURING: Frontend', E_NOTICE, true, true);

			$rewrite_base = preg_replace('/\/install$/i', NULL, dirname($_SERVER['PHP_SELF']));
			$htaccess = str_replace(
				'<!-- REWRITE_BASE -->', $rewrite_base,
				file_get_contents(INSTALL . '/includes/htaccess.txt')
			);

			if(!General::writeFile(DOCROOT . "/.htaccess", $htaccess, $conf['file']['write_mode'], 'a')){
				self::__abort(
					'Could not write ‘.htaccess’ file. Check permission on ' . DOCROOT,
				$start);
			}

			if(!is_dir($fields['docroot'] . '/workspace')){

				// Create workspace folder structure
				self::$_log->pushToLog('WRITING: Creating ‘workspace’ folder (/workspace)', E_NOTICE, true, true);
				if(!General::realiseDirectory(DOCROOT . '/workspace', $conf['directory']['write_mode'])){
					self::__abort(
						'Could not create ‘workspace’ directory. Check permission on the root folder.',
					$start);
				}

				self::$_log->pushToLog('WRITING: Creating ‘data-sources’ folder (/workspace/data-sources)', E_NOTICE, true, true);
				if(!General::realiseDirectory(DOCROOT . '/workspace/data-sources', $conf['directory']['write_mode'])){
					self::__abort(
						'Could not create ‘workspace/data-sources’ directory. Check permission on the root folder.',
					$start);
				}

				self::$_log->pushToLog('WRITING: Creating ‘events’ folder (/workspace/events)', E_NOTICE, true, true);
				if(!General::realiseDirectory(DOCROOT . '/workspace/events', $conf['directory']['write_mode'])){
					self::__abort(
						'Could not create ‘workspace/events’ directory. Check permission on the root folder.',
					$start);
				}

				self::$_log->pushToLog('WRITING: Creating ‘pages’ folder (/workspace/pages)', E_NOTICE, true, true);
				if(!General::realiseDirectory(DOCROOT . '/workspace/pages', $conf['directory']['write_mode'])){
					self::__abort(
						'Could not create ‘workspace/pages’ directory. Check permission on the root folder.',
					$start);
				}

				self::$_log->pushToLog('WRITING: Creating ‘utilities’ folder (/workspace/utilities)', E_NOTICE, true, true);
				if(!General::realiseDirectory(DOCROOT . '/workspace/utilities', $conf['directory']['write_mode'])){
					self::__abort(
						'Could not create ‘workspace/utilities’ directory. Check permission on the root folder.',
					$start);
				}

			}

			else {

				self::$_log->pushToLog('An existing ‘workspace’ directory was found at this location. Symphony will use this workspace.', E_NOTICE, true, true);

				// MySQL: Importing workspace data
				self::$_log->pushToLog('MYSQL: Importing Workspace Data...', E_NOTICE, true, true);

				try{
					self::$_db->import(
						file_get_contents(DOCROOT . '/workspace/install.sql'),
						($fields['database']['use-server-encoding'] != 'yes' ? true : false),
						true
					);
				}
				catch(DatabaseException $e){
					$error = self::$_db->getLastError();
					self::__abort(
						'There was an error while trying to import data to the database. MySQL returned: ' . $error['num'] . ': ' . $error['msg'],
					$start);
				}

			}

			if(!is_dir($fields['docroot'] . '/extensions')){

				// Create extensions folder
				self::$_log->pushToLog('WRITING: Creating ‘extensions’ folder (/extensions)', E_NOTICE, true, true);
				if(!General::realiseDirectory(DOCROOT . '/extensions', $conf['directory']['write_mode'])){
					self::__abort(
						'Could not create ‘extension’ directory. Check permission on the root folder.',
					$start);
				}
			}

			// Loading existing extensions
			self::$_log->pushToLog('CONFIGURING: Installing existing extensions', E_NOTICE, true, true);

			$disabled_extensions = array();

			$extensions = new DirectoryIterator(EXTENSIONS);
			foreach($extensions as $extension) {
				$handle = $extension->getPathname();
				$ext = Administration::instance()->ExtensionManager()->create($handle);

				if(!Administration::instance()->ExtensionManager()->enable($handle)){
					$disabled_extensions[] = $handle;
					self::$_log->pushToLog('Could not enable the extension ‘' . $handle . '’.', E_NOTICE, true, true);
				}
			}

			// Loading default language
			if(isset($_REQUEST['lang']) && $_REQUEST['lang'] != 'en'){
				self::$_log->pushToLog('CONFIGURING: Default language', E_NOTICE, true, true);

				require_once(CORE . '/class.administration.php');

				$language = Lang::Languages();
				$language = $language[$_REQUEST['lang']];
				$extension = Administration::instance()->ExtensionManager()->create('lang_' . $language['handle']);

				// Is the language extension enabled?
				if(in_array('lang_' . $language['handle'], Administration::instance()->ExtensionManager()->listInstalledHandles())){
					self::$_conf->set('lang', $_REQUEST['lang'], 'symphony');
					if(!self::$_conf->write($conf['file']['write_mode'])){
						self::$_log->pushToLog('Could not write default language ‘' . $language['name'] . '’ to config file.', E_NOTICE, true, true);
					}
				}
				else{
					self::$_log->pushToLog('Could not enable the desired language ‘' . $language['name'] . '’.', E_NOTICE, true, true);
				}
			}

			// Installation completed. Woo-hoo!
			self::$_log->writeToLog(        '============================================', true);
			self::$_log->writeToLog(sprintf('INSTALLATION COMPLETED: Execution Time - %d sec (%s)',
				max(1, time() - $start),
				date('d.m.y H:i:s')
			), true);
			self::$_log->writeToLog(        '============================================' . PHP_EOL . PHP_EOL . PHP_EOL, true);

			return $disabled_extensions;
		}

		private static function __render(InstallerPage $page) {
			$output = $page->generate();

			header('Content-Type: text/html; charset=utf-8');
#			header(sprintf('Content-Length: %d', strlen($output)));

			echo $output;
			exit;
		}

	}
