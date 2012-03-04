<?php

/**********************************************************************

	Copyright 2011, 2012 Dennis Rogers

	This file is part of PhpMvcCore.

    PhpMvcCore is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    PhpMvcCore is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with PhpMvcCore.  If not, see <http://www.gnu.org/licenses/>.

************************************************************************/

class MvcSecurity {

	public static $userContextClassFile = "You need to set MvcSecurity::\$userContextClassFile";
	public static $userContextKey = "MvcUserContext";
	private static $lifespanKey = "Lifespan";

	public static function SetUserContext($userContext, $lifespan=0) {
		setcookie(MvcSecurity::$userContextKey, serialize($userContext), ($lifespan) ? (time()+$lifespan) : 0, "/");
		setcookie(MvcSecurity::$userContextKey . MvcSecurity::$lifespanKey, $lifespan, ($lifespan) ? (time()+$lifespan) : 0, "/");
	}

	public static function GetUserContext() {
		if (MvcSecurity::HasUserContext()) {
			$lifespan = (int)$_COOKIE[MvcSecurity::$userContextKey . MvcSecurity::$lifespanKey];

			require_once(MvcSecurity::$userContextClassFile);
			// see http://www.phpbuilder.com/board/showthread.php?t=10358820 for reason for stripslashes/str_replace
			$uContext = stripslashes($_COOKIE[MvcSecurity::$userContextKey]);
			$uContext = str_replace("\n","",$uContext); 
			$userContext = unserialize($uContext);

			// do this to keep cookie(s) alive
			if ($lifespan > 0)
			MvcSecurity::SetUserContext($userContext, $lifespan);

			return $userContext;
		}

		return null;
	}

	public static function UpdateUserContext($userContext) {
		if (MvcSecurity::HasUserContext())
			MvcSecurity::SetUserContext($userContext, (int)$_COOKIE[MvcSecurity::$userContextKey . MvcSecurity::$lifespanKey]);
	}

	public static function DeleteUserContext() {
		MvcSecurity::SetUserContext(null, -3600);
	}

	//***********************************************************************************
	// private

	private static function HasUserContext() {
		return (isset($_COOKIE[MvcSecurity::$userContextKey]) && isset($_COOKIE[MvcSecurity::$userContextKey . MvcSecurity::$lifespanKey]));
	}
}

?>