<?php

/*
 * Copyright (C) 2016 Iurii Prudius <hardwork.mouse@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Wtf\Core;

use Wtf\Core\Resource,
	Wtf\Helper\Common;

/**
 * Config access.
 * Lazy loading to request.
 * 
 * @author Iurii Prudius <hardwork.mouse@gmail.com>
 */
class Config implements \Wtf\Interfaces\Singleton, \Wtf\Interfaces\Invokable, \Wtf\Interfaces\Caller, \Wtf\Interfaces\GetterOnly, \Wtf\Interfaces\ArrayAccessRead {

	use \Wtf\Traits\Singleton,
	 \Wtf\Traits\GetterOnly,
	 \Wtf\Traits\ArrayAccessRead,
	 \Wtf\Traits\Invokable,
	 \Wtf\Traits\Caller;

	/**
	 * Unloaded resource
	 * 
	 * @var \Wtf\Core\Resource
	 */
	private $_resource = null;

	/**
	 * Loaded resource
	 * 
	 * @var type 
	 */
	private $_data = [];

	/**
	 * Prepare config
	 * 
	 * @param Resource|string $cfg
	 */
	private function __construct($cfg) {
		$this->_resource = Resource::produce($cfg);
	}

	/**
	 * Find the leaf by complex path:
	 * i.e. 'path/to/var' or '/path/to/var'
	 * 
	 * @param string $offset
	 * @return null|array wraps a found leaf
	 */
	private function _complexFind($offset) {
		if($this->_resource) {
			$this->_preload();
		}

		$path = array_filter(explode('/', strtolower($offset)));

		if($path) {
			$name = array_shift($path);

			if(isset($this->_data[$name])) {
				$branch = $this->_data[$name];
				while($path) {
					$step = array_shift($path);
					if(isset($branch[$step])) {
						$branch = $branch[$step];
					} else {
						return null;
					}
				}
				// wrap for understanding about null value
				return [$branch];
			}
		}
		return null;
	}

	/**
	 * Check if path found
	 * 
	 * @param string $offset
	 * @return boolean
	 */
	public function offsetExists($offset) {
		return is_array($this->_complexFind($offset));
	}

	/**
	 * Get the value by path
	 * 
	 * @param string $offset
	 * @return mixed
	 */
	public function offsetGet($offset) {
		$found = $this->_complexFind($offset);
		if(is_array($found)) {
			return reset($found);
		}
		return null;
	}

	/**
	 * Magic check.
	 * 
	 * @param string $name
	 * @return boolean
	 */
	public function __isset($name) {
		return $this->offsetExists($name);
	}

	/**
	 * Magic getter.
	 * 
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name) {
		return $this->offsetGet($name);
	}

	/**
	 * Preload branches as Config
	 * and files
	 * 
	 */
	private function _preload() {
		$data = [];
		if($this->_resource->isContainer()) {
			$ini = $this->_resource->getChild('.ini');
			if($ini) {
				$data = self::_load($ini);
			}
			foreach($this->_resource->get() as $name) {
				$res = $this->_resource->getChild($name);
				$data[$res->getName()] = new Config($res);
			}
		} else {
			$data = self::_load($this->_resource);
		}
		$this->_data = array_change_key_case($data);
		// clear preloaded resource
		$this->_resource = null;

		return $this->_data;
	}

	/**
	 * Internal config loading.
	 * 
	 * @param Resource $res
	 * @return array
	 */
	static protected function _load(Resource $res) {
		switch($res->getType()) {
			case 'php':
				try {
					// eval PHP file
					return Common::parsePhp($res->getContent());
				} catch(Exception $e) {
					throw new \Wtf\Exceptions\ParseException($res->getPath());
				}
			case 'json':
				// JSON object as array
				$ret = @json_decode($res->getContent(), true);
				if(is_null($ret)) {
					throw new \Wtf\Exceptions\ParseException($res->getPath());
				}
				return $ret;
			case 'ini':
				// INI array
				$ret = @parse_ini_string($res->getContent(), true, INI_SCANNER_TYPED);
				if(false === $ret) {
					throw new \Wtf\Exceptions\ParseException($res->getPath());
				}
				return $ret;
			case 'xml':
				// XML as array
				$ret = @simplexml_load_string($res->getContent());
				if(false === $ret) {
					throw new \Wtf\Exceptions\ParseException($res->getPath());
				}
				return json_decode(json_encode($ret), true);
		}
		return [];
	}

}
