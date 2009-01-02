<?php

/**
 * Simple exception wrapper for OAuth
 * 
 * @version $Id: OAuthException.php 5 2008-02-13 12:29:12Z marcw@pobox.com $
 * @author Marc Worrell <marc@mediamatic.nl>
 * @copyright (c) 2007 Mediamatic Lab
 * @date  Nov 29, 2007 5:33:54 PM
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

// TODO: something with the HTTP return code matching to the problem

require_once dirname(__FILE__) . '/OAuthRequestLogger.php';

class OAuthException extends Exception
{
	function __construct ( $message )
	{
		Exception::__construct($message);
		OAuthRequestLogger::addNote('OAuthException: '.$message);
	}

}


/* vi:set ts=4 sts=4 sw=4 binary noeol: */

?>