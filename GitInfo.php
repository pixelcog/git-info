<?php
/**
 * PixelCog Web Services
 *
 * @copyright Copyright 2012, PixelCog Inc. (http://pixelcog.com) All Rights Reserved
 */

namespace app\extensions;

class GitInfo {
	
	protected $_path = null;
	
	protected $_statuses = array(
		'-' => 'Not Initialized',
		'+' => 'Checkout Mismatch',
		'U' => 'Merge Conflicts',
	);
	
	public function __construct($path = __DIR__) {
		$this->_path = $path;
	}
	
	public function getInfo($path = null) {
		
		$data = array(
			'fullpath' => $this->_execute('pwd', $path, ''),
			'commit'   => $this->_execute('git rev-parse HEAD', $path, ''),
			'origin'   => $this->_execute('git config --get remote.origin.url', $path, ''),
			'branch'   => $this->getBranch($path),
			'comment'  => $this->getCommitMessage($path)
		);
		$data += $this->parseOriginInfo($data['origin'], $data['commit']);
		
		if ($modules = $this->getSubModules($path)) {
			$data['modules'] = $modules;
		}
		
		return $data;
	}
	
	public function getBranch($path = null) {
		$lines = $this->_execute('git branch', $path);
		foreach($lines as $line) {
			if ( strpos( $line, '*' ) === 0 ) {
				return ltrim( $line, '* ' );
			}
		}
		return null;
	}
	
	public function getCommitMessage($path = null) {
		$msg = $this->_execute('git log -1 --oneline', $path, '');
		
		if (preg_match('#^([0-9a-f]+?) (.*?)$#i', $msg, $match)) {
			$msg = $match[2];
		}
		
		return $msg;
	}
	
	public function getSubModules($path = null) {
		$modules = $this->_execute('git submodule status', $path);
		
		foreach($modules as &$module) {
			if (preg_match('#([\-\+U ])([0-9a-f]{40})\s([^\(]+?)\s\(([^\)]+?)\)#', $module, $match)) {
				$module = $this->getInfo($match[3]) + array(
					'path' => $match[3],
					'commit' => $match[2],
					'describe' => $match[4],
					'status' => isset($this->_statuses[$match[1]]) ? $this->_statuses[$match[1]] : 'Normal'
				);
				continue;
			}
			$module = null;
		}
		$modules = array_filter($modules);
		
		return $modules;
	}
	
	public function parseOriginInfo($origin, $commit) {
		$remote = array();
		
		if (preg_match('#^(?:(?:git|https?)://)?(?:[^@]*?@)?(bitbucket.org|github.com)[:/]([^/]*?)/([^/]*?).git$#i', $origin, $match)) {
			$remote['host'] = $match[1];
			$remote['repo'] = $match[2].'/'.$match[3];
			
			if ($remote['host'] == 'github.com') {
				$remote['link_commit'] = 'https://' . $remote['host'] . '/' . $remote['repo'] . '/commit/' . substr($commit,0,12);
				$remote['link_source'] = 'https://' . $remote['host'] . '/' . $remote['repo'] . '/tree/' . substr($commit,0,12);
			}
			if ($remote['host'] == 'bitbucket.org') {
				$remote['link_commit'] = 'https://' . $remote['host'] . '/' . $remote['repo'] . '/changeset/' . substr($commit,0,12);
				$remote['link_source'] = 'https://' . $remote['host'] . '/' . $remote['repo'] . '/src/' . substr($commit,0,12);
			}
		}
		
		return $remote;
	}
	
	protected function _execute($cmd, $path = null, $join = null) {
		chdir($this->_path);
		if ($path) {
			chdir($path);
		}
		exec($cmd, $output);
		return $join !== null ? join($join, $output) : $output;
	}
}

?>