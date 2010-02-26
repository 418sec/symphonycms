<?php
	
	class Extension_DynamicXMLDS extends Extension {
		public function about() {
			return array(
				'name'			=> 'Dynamic XML Datasources',
				'version'		=> '1.0.0',
				'release-date'	=> '2010-02-26',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://rowanlewis.com/',
					'email'			=> 'me@rowanlewis.com'
				),
				'description'	=> 'Create data sources from XML fetched over HTTP or FTP.'
			);
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'NewDataSourceAction',
					'callback'	=> 'action'
				),
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'NewDataSourceForm',
					'callback'	=> 'form'
				),
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'EditDataSourceAction',
					'callback'	=> 'action'
				),
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'EditDataSourceForm',
					'callback'	=> 'form'
				)
			);
		}
		
		protected function getTemplate() {
			$file = EXTENSIONS . '/templates/datasource.php';
			
			if (!file_exists($file)) {
				throw new Exception(sprintf("Unable to find template '%s'.", $file));
			}
			
			return file_get_contents($file);
		}
		
		public function action($context = array()) {
			/*
			$context = array(
				'data'		=> array(),		// Array of post data
				'errors'	=> null			// Instance of MessageStack to be filled with errors
			);
			*/
		}
		
		public function form($context = array()) {
			/*
			$context = array(
				'data'		=> array(),		// Array of post data
				'errors'	=> null			// Instance of MessageStack to be checked for errors
				'wrapper'	=> null			// XMLElement so additional fieldsets can be added
			);
			*/
			$template = $this->getTemplate();
		}
	}
	
?>