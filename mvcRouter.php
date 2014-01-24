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

class MvcRouter {
	public static $appRoot = "";
	private static $reRoutes = array();

	public static $routeRequestIgnore = array("[-]", "/\s/"); // controller/action only (does not apply to data)
	public static $controllersRoot = "controllers/";
	public static $controllerTypeSuffix = "Controller";
	public static $viewsRoot = "views/";

	public static $writeLowercaseUrls = true;

	public static function FindRouteRequest() {
		$request = new MvcRouteRequest();

		$uri = parse_url($_SERVER["REQUEST_URI"]);
		$path = self::$appRoot == "/"
		      ? @split("/", substr($uri["path"], 1))
			  : @split("/", str_ireplace(self::$appRoot, "", $uri["path"]));

		// controller
		if (count($path) && strlen($path[0]) > 0)
			$request->controllerType = urldecode(array_shift($path));
		$request->controllerType = self::GetControllerType($request->controllerType);

		// action
		if (count($path) && strlen($path[0]) > 0)
			$request->action = urldecode(array_shift($path));

		// data
		while(count($path)) {
			$d = urldecode(array_shift($path));
			if (trim($d)) $request->data[] = $d;
		}

		return $request;
	}

	public static function ReRouteRequest($request) {
		$route = $request->GetRoute();
		//echo "route  = $route</br/>";
		$_route = explode("/", $route);

		foreach(self::$reRoutes as $reRouteKey=>$reRoute) {
			$_reRouteKey = explode("/", $reRouteKey);
			$totalComparisons = count($_reRouteKey);

			// check for match
			for ($i = 0; $i < $totalComparisons; $i++) {
				if ($i >= count($_route))
					break;

				if ($_reRouteKey[$i] == "*") {
					// get reRouteKey prepared for str_replace
					if ($i == $totalComparisons-1)
						unset($_reRouteKey[$i]);
					else
						$_reRouteKey[$i] = $_route[$i];
				}
				else if ($_reRouteKey[$i] != "*" && $_reRouteKey[$i] != $_route[$i])
					break;
			}

			// did we find a match?
			if ($i == $totalComparisons) {
				$route = $route == "" ? $reRoute : str_replace(trim(join("/",$_reRouteKey),"/")."/", $reRoute, $route);
				//echo "route = $route<br/>";
				$_route = explode("/", $route);

				// controllerType and action can be wildcards
				$controllerType = MvcRouter::GetControllerType(array_shift($_route));
				$action = array_shift($_route);
				$data = is_array($_route) && count($_route) ? array_values(array_diff($_route, array(""))) : array();

				$request->controllerType = substr($controllerType,0,1) != "*" ? $controllerType : $request->controllerType;
				$request->action = substr($action,0,1) != "*" ? $action : $request->action;

				// try to preserve request data from source other than query string (e.g. manual request)
				$request->data = count($data) ? $data: (array_values($request->data) === $request->data ? array() : $request->data);
				break;
			}
		}

		$request->controllerType = preg_replace(self::$routeRequestIgnore, "", $request->controllerType);
		$request->action = preg_replace(self::$routeRequestIgnore, "", $request->action);

		return $request;
	}

	public static function ExecuteRouteRequest($request) {
		$request = self::ReRouteRequest($request);

		$controllerFilePath = self::GetControllerFilePath($request->controllerType);

		if (!file_exists($controllerFilePath))
			throw new ErrorException("controller file not found [" . $controllerFilePath . "]", 404);

		require_once($controllerFilePath);
		$controllerObject = new $request->controllerType;

		if (!$request->action) $request->action = "index";
		if (!method_exists($controllerObject, $request->action))
			throw new ErrorException("controller type [" . $request->controllerType . "] does not contain the action [" . $request->action . "]", 404);

		return $controllerObject->ExecuteRouteRequest($request);
	}

	public static function AddReRoute($route, $reRoute) {
		$route = strtolower(trim($route));
		if ($route == "/") $route = ""; // i.e. - for default controller/index
		if ($route && $route[strlen($route)-1] != "*") // allow for wildcards (ex: don't add '/' to end of "controller/action/*")
			$route = trim($route,"/")."/";

		$reRoute = strtolower(trim($reRoute));
		if ($reRoute && $reRoute[strlen($reRoute)-1] != "*")
			$reRoute = trim($reRoute,"/")."/";

		self::$reRoutes[$route] = $reRoute;
	}

	public static function GetControllerName($controllerType) {
		return str_ireplace(self::$controllerTypeSuffix,"",$controllerType);
	}

	public static function GetControllerType($controllerType) {
		return ($controllerType != "" && $controllerType != "*") ? self::GetControllerName($controllerType) . self::$controllerTypeSuffix : $controllerType;
	}

	public static function GetControllerFilePath($controllerType) {
		return self::$controllersRoot . self::GetControllerName(strtolower($controllerType)) . ".php";
	}

	public static function GetViewFilePath($controllerType, $view) {
		return self::$viewsRoot . self::GetControllerName(strtolower($controllerType)) . "/" . $view . ".php";
	}
	
	public static function GetActionUrl($controllerType, $action, $data=array()) {
		$path = self::$appRoot;

		$path .= $controllerType ? (self::GetControllerName($controllerType) . "/") : "";
		$path .= $action ? ($action . "/") : "";

		// don't let data turn to lowercase
		// could mess up $_GET keys, names, etc.
		if (self::$writeLowercaseUrls) $path = strtolower($path);

		if ($data) {
			if (!is_array($data) && !is_object($data))
				$data = array($data);

			if (is_array($data) && array_values($data) === $data) {
				$data = array_diff($data, array("")); // remove empty elements
				$data = join("/", $data);
				$path .= trim($data, "/") . "/";
			}
			else
				$path .= "?" . http_build_query($data);
		}
		
		return $path;
	}
}

class MvcRouteRequest {
	var $controllerType = "";
	var $action = "";
	var $data = array(); // Note: populated in 2 scenarios (not populated with GET or POST data, see FindValue in MvcBaseModelBinder):
						 //		#1: /controller/action/paramValue1 (no param name)
	                     //		#2: passed in code, for example: $data = array("p1"=>"paramValue1"), see MvcHtml::RenderAction

	var $custom = array();

	function __construct($controllerType="", $action="", $data=array(), $custom=array()) {
		$this->controllerType = MvcRouter::GetControllerType($controllerType);
		$this->action = $action;
		$this->data = $data;

		$this->custom = $custom;
	}

	function GetRoute() {
		$route = "";

		// controller
		if (!$this->controllerType)
			return $route; // don't continue, even if you have action or data
		else
			$route .= MvcRouter::GetControllerName($this->controllerType) . "/";

		// action
		if (!$this->action)
			return $route; // don't continue, even if you have data
		else
			$route .= $this->action . "/";

		// data
		$route .= (is_array($this->data) && count($this->data) && array_values($this->data) === $this->data)
			      ? trim(join("/",$this->data),"/") . "/"
			      : "";

		return $route;
	}
}

MvcRouter::$appRoot = str_ireplace("index.php","",$_SERVER["PHP_SELF"]);

//MvcRouter::AddReRoute("","home/index/"); // home is default controller
//MvcRouter::AddReRoute("*/", "*/index/"); // index is default action
?>