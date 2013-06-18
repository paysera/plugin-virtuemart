<?php
defined('_JEXEC') or die('Restricted access');

/**
 * VirtueMart script file
 *
 * This file is executed during install/upgrade and uninstall
 *
 * @author Patrick Kohl, Max Milbers
 * @package VirtueMart
 */



// hack to prevent defining these twice in 1.6 installation
if (!defined('_VM_SCRIPT_INCLUDED')) {

	define('_VM_SCRIPT_INCLUDED', true);


	class com_virtuemart_allinoneInstallerScript {

		public function preflight(){
			//$this->vmInstall();
		}

		public function install(){
			//$this->vmInstall();
		}

		public function discover_install(){
			//$this->vmInstall();
		}

		public function postflight () {
			$this->vmInstall();
		}

		public function vmInstall () {
			
			$this->createIndexFolder(JPATH_ROOT .DS. 'plugins'.DS.'vmpayment');

			$this->path = JInstaller::getInstance()->getPath('extension_administrator');

			$this->installPlugin('VM - Payment, Paysera', 'plugin', 'paysera', 'vmpayment');

			// language auto move
			$src= $this->path .DS."languageBE" ;
			$dst= JPATH_ADMINISTRATOR . DS . "language" ;
			$this->recurse_copy( $src ,$dst );
			echo " Paysera language   moved to the joomla language BE folder   <br/ >" ;
			echo "<H3>Installing Paysera Virtuemart Plugin Success.</h3>";
			echo "<H3>You may directly uninstall this component. Your plugins will remain</h3>";

			return true;
		}

		/**
		 * Installs a vm plugin into the database
		 *
		 */
		private function installPlugin($name, $type, $element, $group){

			$data = array();

			if(version_compare(JVERSION,'1.7.0','ge')) {

				// Joomla! 1.7 code here
				$table = JTable::getInstance('extension');
				$data['enabled'] = 1;
				$data['access']  = 1;
				$tableName = '#__extensions';
				$idfield = 'extension_id';
			} elseif(version_compare(JVERSION,'1.6.0','ge')) {

				// Joomla! 1.6 code here
				$table = JTable::getInstance('extension');
				$data['enabled'] = 1;
				$data['access']  = 1;
				$tableName = '#__extensions';
				$idfield = 'extension_id';
			} else {

				// Joomla! 1.5 code here
				$table = JTable::getInstance('plugin');
				$data['published'] = 1;
				$data['access']  = 0;
				$tableName = '#__plugins';
				$idfield = 'id';
			}

			$data['name'] = $name;
			$data['type'] = $type;
			$data['element'] = $element;
			$data['folder'] = $group;

			$data['client_id'] = 0;


			$src= $this->path .DS. 'plugins' .DS. $group .DS.$element;

			if(version_compare(JVERSION,'1.6.0','ge')) {
				$data['manifest_cache'] = json_encode(JApplicationHelper::parseXMLInstallFile($src.DS.$element.'.xml'));
			}

			$db = JFactory::getDBO();
			$q = 'SELECT '.$idfield.' FROM `'.$tableName.'` WHERE `name` = "'.$name.'" ';
			$db->setQuery($q);
			$count = $db->loadResult();

			if(!empty($count)){
				$table->load($count);
			}

			if(!$table->bind($data)){
				$app = JFactory::getApplication();
				$app -> enqueueMessage('VMInstaller table->bind throws error for '.$name.' '.$type.' '.$element.' '.$group);
			}

			if(!$table->check($data)){
				$app = JFactory::getApplication();
				$app -> enqueueMessage('VMInstaller table->check throws error for '.$name.' '.$type.' '.$element.' '.$group);

			}

			if(!$table->store($data)){
				$app = JFactory::getApplication();
				$app -> enqueueMessage('VMInstaller table->store throws error for '.$name.' '.$type.' '.$element.' '.$group);
			}

			$errors = $table->getErrors();
			foreach($errors as $error){
				$app = JFactory::getApplication();
				$app -> enqueueMessage( get_class( $this ).'::store '.$error);
			}


			if(version_compare(JVERSION,'1.7.0','ge')) {
				// Joomla! 1.7 code here
				$dst= JPATH_ROOT . DS . 'plugins' .DS. $group.DS.$element;

			} elseif(version_compare(JVERSION,'1.6.0','ge')) {
				// Joomla! 1.6 code here
				$dst= JPATH_ROOT . DS . 'plugins' .DS. $group.DS.$element;
			} else {
				// Joomla! 1.5 code here
				$dst= JPATH_ROOT . DS . 'plugins' .DS. $group;
			}

			$this->recurse_copy( $src ,$dst );

		}

		/**
		 * copy all $src to $dst folder and remove it
		 * Enter description here ...
		 * @param String $src path
		 * @param String $dst path
		 * @param String $type modules, plugins, languageBE, languageFE
		 */
		private function recurse_copy($src,$dst ) {

			$dir = opendir($src);
			@mkdir($dst);

			if(is_resource($dir)){
				while(false !== ( $file = readdir($dir)) ) {
					if (( $file != '.' ) && ( $file != '..' )) {
						if ( is_dir($src .DS. $file) ) {

							$this->recurse_copy($src .DS. $file,$dst .DS. $file);
						}
						else {
							if(!JFile::move($src .DS. $file,$dst .DS. $file)){
								$app = JFactory::getApplication();
								$app -> enqueueMessage('Couldnt move '.$src .DS. $file.' to '.$dst .DS. $file);
							}
						}
					}
				}
				closedir($dir);
				//if (is_dir($src)) $this->RemoveDir($src, true);
				if (is_dir($src)) JFolder::delete($src);
			} else {
				$app = JFactory::getApplication();
				$app -> enqueueMessage('Couldnt read dir '.$dir.' source '.$src);
			}

		}


		public function uninstall() {

			return true;
		}

		/**
		 * creates a folder with empty html file
		 *
		 * @author Max Milbers
		 *
		 */
		public function createIndexFolder($path){

			if(JFolder::create($path)) {
				JFile::copy(JPATH_ROOT.DS.'components'.DS.'index.html', $path .DS. 'index.html');
			}

		}

	}


	// PLZ look in #vminstall.php# to add your plugin and module
	function com_install(){

		if(!version_compare(JVERSION,'1.6.0','ge')) {
			$vmInstall = new com_virtuemart_allinoneInstallerScript();
			$vmInstall->vmInstall();
		}
		return true;
	}

	function com_uninstall(){

		return true;
	}

}
