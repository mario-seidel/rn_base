<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 Rene Nitzsche
 *  Contact: rene@system25.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 ***************************************************************/

/**
 * Replacement for tx_div
 */
class tx_rnbase {
	private static $loadedClasses = array();
	/**
	 * Load the class file
	 *
	 * Load the file for a given classname 'tx_key_path_file'
	 * or a given part of the filepath that contains enough information to find the class.
	 * 
	 * This method is taken from tx_div. There is an additional cache to avoid double calls. 
	 * This can saves a lot of time.
	 *
	 * @param	string		classname or path matching for the type of loader
	 * @return	boolean		true if successfull, false otherwise
	 * @see     tx_lib_t3Loader
	 * @see     tx_lib_pearLoader
	 */
	public static function load($classNameOrPathInformation) {
		if(array_key_exists($classNameOrPathInformation,self::$loadedClasses)) 
			return self::$loadedClasses[$classNameOrPathInformation];

		if(self::loadT3($classNameOrPathInformation)) {
			self::$loadedClasses[$classNameOrPathInformation] = true;
			return true;
		}
		print '<p>Trying Pear Loader: ' . $classNameOrPathInformation;
		require_once(t3lib_extMgm::extPath('lib') . 'class.tx_lib_pearLoader.php');
		if(tx_lib_pearLoader::load($classNameOrPathInformation)) {
			self::$loadedClasses[$classNameOrPathInformation] = true;
			return true;
		}
		self::$loadedClasses[$classNameOrPathInformation] = false;
		return false;
	}

	/**
	 * Load a t3 class
	 *
	 * Loads from extension directories ext, sysext, etc.
	 *
	 * Loading: '.../ext/key/subs/prefix.class.suffix
	 *
	 * The files are searched on two levels:
	 *
	 * <pre>
	 * tx_key           '.../ext/key/class.tx_key.php'
	 * tx_key_file      '.../ext/key/class.tx_key_file.php'
	 * tx_key_file      '.../ext/key/file/class.tx_key_file.php'
	 * tx_key_subs_file '.../ext/key/subs/class.tx_key_subs_file.php'
	 * tx_key_subs_file '.../ext/key/subs/file/class.tx_key_subs_file.php'
	 * </pre>
	 *
	 * @param	string		classname or speaking part of path
	 * @param	string		extension key that varies from classname
	 * @param	string		prefix of classname
	 * @param	string		ending of classname
	 * @return	boolean		TRUE if class was loaded
	 */
	function loadT3($minimalInformation, $alternativeKey='', $prefix = 'class.', $suffix = '.php') {
		$path = self::_findT3($minimalInformation, $alternativeKey, $prefix, $suffix);
		if($path) {
			require_once($path);
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Find path to load
	 * Method from tx_lib_t3Loader
	 *
	 * see load
	 *
	 * @param	string		classname
	 * @param	string		extension key that varies from classnames
	 * @param	string		prefix of classname
	 * @param	string		ending of classname
	 * @return	string		the path, FALSE if invalid
	 * @see		load()
	 */
	function _findT3($minimalInformation, $alternativeKey='', $prefix = 'class.', $suffix = '.php') {
		$info=trim($minimalInformation);
		$path = '';
		if(!$info) {
			$error = 'emptyParameter';
		}
		if(!$error) {
			$qSuffix = preg_quote ($suffix, '/');
			// If it is a path extract the key first.
			// Either the relevant part starts with a slash: xyz/[tx_].....php
			if(preg_match('/^.*\/([0-9A-Za-z_]+)' . $qSuffix . '$/', $info, $matches)) {
				$class = $matches[1];
			}elseif(preg_match('/^.*\.([0-9A-Za-z_]+)' . $qSuffix . '$/', $info, $matches)) {
				// Or it starts with a Dot: class.[tx_]....php

				$class = $matches[1];
			}elseif(preg_match('/^([0-9A-Za-z_]+)' . $qSuffix . '$/', $info, $matches)) {
				// Or it starts directly with the relevant part
				$class = $matches[1];
			}elseif(preg_match('/^[0-9a-zA-Z_]+$/', trim($info), $matches)) {
				// It may be the key itself
				$class = $info;
			}else{
				$error = 'classError';
			}
		}
		// With this a possible alternative Key is also validated
		if(!$error && !$key = self::guessKey($alternativeKey ? $alternativeKey : $class)) {
			$error = 'classError';
		}
		if(!$error) {
			if(preg_match('/^tx_[0-9A-Za-z_]*$/', $class)) {  // with tx_ prefix
				$parts=split('_', trim($class));
				array_shift($parts); // strip tx
			}elseif(preg_match('/^[0-9A-Za-z_]*$/', $class)) { // without tx_ prefix
				$parts=split('_', trim($class));
			}else{
				$error = 'classError';
			}
		}
		if(!$error) {

			// Set extPath for key (first element)
			$first = array_shift($parts);

			// Save last element of path
			if(count($parts) > 0) {
				$last = array_pop($parts) . '/';
			}

			$dir = '';
			// Build the relative path if any
			foreach((array)$parts as $part) {
				$dir .= $part . '/';
			}

			// if an alternative Key is given use that
			$ext = t3lib_extMgm::extPath($key);

			// First we try ABOVE last directory (dir and last may be empty)
			// ext(/dir)/last
			// ext(/dir)/prefix.tx_key_parts_last.php.
			if(!$path && !is_file($path =  $ext . $dir . $prefix . $class . $suffix)) {
				$path = FALSE;
			}

			// Now we try INSIDE the last directory (dir and last may be empty)
			// ext(/dir)/last
			// ext(/dir)/last/prefix.tx_key_parts_last.php.
			if(!$path && !is_file($path =  $ext . $dir . $last . $prefix . $class . $suffix)) {
				$path = FALSE;
			}
		}
		return $path;
	}

	/**
	 * Check if the given extension key is within the loaded extensions
	 *
	 * The key can be given in the regular format or with underscores stripped.
	 *
	 * @author Elmar Hinz
	 * @param	string		extension key to check
	 * @return	boolean		is the key valid?
	 */
	function getValidKey($rawKey) {
		$uKeys = array_keys($GLOBALS['TYPO3_LOADED_EXT']);
		foreach((array)$uKeys as $uKey) {
			if( str_replace('_', '', $uKey) == str_replace('_', '', $rawKey) ){
				$result =  $uKey;
			}
		}
		return $result ? $result : FALSE;
	}


	/**
	 * Guess the key from the given information
	 *
	 * Guessing has the following order:
	 *
	 * 1. A KEY itself is tried.
	 *    <pre>
	 *     Example: my_extension
	 *    </pre>
	 * 2. A classnmae of the pattern tx_KEY_something_else is tried.
	 *    <pre>
	 *     Example: tx_myextension_view
	 *    </pre>
	 * 3. A full classname of the pattern ' * tx_KEY_something_else.php' is tried.
	 *    <pre>
	 *     Example: class.tx_myextension_view.php
	 *     Example: brokenPath/class.tx_myextension_view.php
	 *    </pre>
	 * 4. A path that starts with the KEY is tried.
	 *    <pre>
	 *     Example: my_extension/class.view.php
	 *    </pre>
	 *
	 * @author Elmar Hinz
	 * @param	string		the minimal necessary information (see 1-4)
	 * @return	string		the guessed key, FALSE if no result
	 */
	function guessKey($minimalInformation) {
		$info=trim($minimalInformation);
		$key = FALSE;
		if($info){
			// Can it be the key itself?
			if(!$key && preg_match('/^([A-Za-z_]*)$/', $info, $matches ) ) {
				$key = $matches[1];
				$key = self::getValidKey($key);
			}
			// Is it a classname that contains the key?
			if(!$key && (preg_match('/^tx_([^_]*)(.*)$/', $info, $matches ) || preg_match('/^user_([^_]*)(.*)$/', $info, $matches )) ) {
				$key = $matches[1];
				$key = self::getValidKey($key);
			}
			// Is there a full filename that contains the key in it?
			if(!$key && (preg_match('/^.*?tx_([^_]*)(.*)\.php$/', $info, $matches ) || preg_match('/^.*?user_([^_]*)(.*)\.php$/', $info, $matches )) ) {
				$key = $matches[1];
				$key = self::getValidKey($key);
			}
			// Is it a path that starts with the key?
			if(!$key && $last = strstr('/',$info)) {
				$key = substr($info, 0, $last);
				$key = self::getValidKey($key);
			}
		}
		return $key ? $key : FALSE;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rn_base/action/class.tx_rnbase.php']) {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rn_base/action/class.tx_rnbase.php']);
}

?>
