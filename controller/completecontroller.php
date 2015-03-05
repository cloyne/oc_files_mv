<?php
/**
 * ownCloud - files_mv
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author eotryx <mhfiedler@gmx.de>
 * @copyright eotryx 2015
 */

namespace OCA\Files_Mv\Controller;

use \OCP\IRequest;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Http\DataResponse;
use \OCP\AppFramework\Controller;
use \OCP\IServerContainer;

class CompleteController extends Controller {
	private $userId;
	private $l;
	private $storage;
	private $showLayers = 2; // TODO: Move to settings, default value

	public function __construct($AppName, IRequest $request, $ServerContainer, $UserId){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->storage = $ServerContainer->getUserFolder($UserId);
		$this->l = \OC_L10N::get($AppName);
	}
	/**
	 * provide a list of directories based on the $startDir excluding all directories listed in $file(;sv)
	 * @param string $file - semicolon separated filenames
	 * @param string $startDir - Dir where to start with the autocompletion
	 * @return JSON list with all directories matching
	 *
	 * @NoAdminRequired
	 */
	public function index($file, $StartDir){
		$curDir = $StartDir;
		$files = $this->fixInputFiles($file);
		$dirs = array();
		
		// fix curDir, so it always start with leading / 
		if(empty($curDir)) $curDir = '/';
		else {
			if(strlen($curDir)>1 && substr($curDir,0,1)!=='/'){
				$curDir = '/'.$curDir;
			}
		}
		if(!($this->storage->nodeExists($curDir)
			&& $this->storage->get($curDir)->getType()===\OCP\Files\FileInfo::TYPE_FOLDER
			)
		){ // node should exist and be a directory, otherwise something terrible happened
			return array("status"=>"error","message"=>$this->l->t('No filesystem found'));
		}
		if(dirname($files[0])!=="/" && dirname($files[0])!==""){
			$dirs[] = '/';
		}
		$patternFile = '!('. implode(')|(',$files) .')!';
		if($curDir!="/" && !preg_match($patternFile,$curDir)) $dirs[] = $curDir;
		$tmp = $this->getDirList($curDir,$files,$this->showLayers);
		$dirs = array_merge($dirs,$tmp);
		
		return $dirs;
	}

	/**
	 * clean Input param $files so that it is returned as an array where each file has a full path
	 * @param String $files
	 * @return array
	 */
	private function fixInputFiles($files){
		$files = explode(';',$files);
		if(!is_array($files)) $files = array($files); // files can be one or many
		$rootDir = dirname($files[0]).'/';//first file has full path
		// expand each file in $files to full path to the user root directory
		for($i=0,$len=count($files); $i<$len; $i++){
			if($i>0) $files[$i] = $rootDir.$files[$i];
			if(strpos($files[$i],'//')!==false){
				$files[$i] = substr($files[$i],1); // drop leading slash, because there are two slashes
			}
		}
		return $files;
	}

	/**
	 * Recursively create a directory listing for the current directory $dir, ignoring $actFile with the depth $depth
	 *
	 * @param string $dir - current directory
	 * @param string $actFile - file to be ignored
	 * @param int $depth - which depth, -1=all (sub-)levels, 0=finish
	 */
	private function getDirList($dir,$actFile,$depth=-1){
		if($depth == 0) return array(); // Abbruch wenn depth = 0
		$ret = array();
		$patternFile = '!(('.implode(')|(',$actFile).'))$!';
		$folder = $this->storage->get($dir)->getDirectoryListing();
		foreach($folder as $i ){
			// ignore files other than directories
			if($i->getType()!==\OCP\Files\FileInfo::TYPE_FOLDER) continue;

			if(substr($dir,-1)=='/') $dir = substr($dir,0,-1); //remove ending '/'
			$path = $dir.'/'.$i->getName();
			
			// ignore directories that are within the files to be moved
			if(preg_match($patternFile,$path)) continue;

			if($i->isUpdateable()){
				$ret[] =  $path;
			}
			//recursion for all sub directories
			$ret = array_merge($ret,$this->getDirList($path,$actFile,$depth-1));
		}
		return $ret;
	}
}
