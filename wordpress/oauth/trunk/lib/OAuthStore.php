<?php

/**
 * Storage container for the oauth credentials, both server and consumer side.
 * This is the factory to select the store you want to use
 * 
 * @version $Id: OAuthStore.php 16 2008-06-17 08:19:49Z scherpenisse $
 * @author Marc Worrell <marc@mediamatic.nl>
 * @copyright (c) 2007 Mediamatic Lab
 * @date  Nov 16, 2007 4:03:30 PM
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

require_once dirname(__FILE__) . '/OAuthException.php';

class OAuthStore
{
	static private $instance = false;

	/**
	 * Request an instance of the OAuthStore
	 */
	public static function instance ( $store = 'MySQL', $options = array() )
	{
	    if (!OAuthStore::$instance)
	    {
			// Select the store you want to use
			if (strpos($store, '/') === false)
			{
				$class = 'OAuthStore'.$store;
				$file  = dirname(__FILE__) . '/store/'.$class.'.php';
			}
			else
			{
				$file  = $store;
				$store = basename($file, '.php');
				$class = $store;
			}

			if (is_file($file))
			{
				require_once $file;
				
				if (class_exists($class))
				{
					OAuthStore::$instance = new $class($options);
				}
				else
				{
					throw new OAuthException('Could not find class '.$class.' in file '.$file);
				}
			}
			else
			{
				throw new OAuthException('No OAuthStore for '.$store.' (file '.$file.')');
			}
	    }
	    return OAuthStore::$instance;	
	}
}


/* vi:set ts=4 sts=4 sw=4 binary noeol: */

?>