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
		$controllerType = strtolower($request->controllerType);
		$action = strtolower($request->action);

		// ReRoute specific Controller/Action
		if (isset(self::$reRoutes[$controllerType][$action])) {
			$reRoute = self::$reRoutes[$controllerType][$action];
			$request->controllerType = $reRoute->controllerType;
			$request->action = $reRoute->action;
		}

		// ReRoute Controller (any Action)
		elseif (isset(self::$reRoutes[$controllerType]["*"])) {
			$reRoute = self::$reRoutes[$controllerType]["*"];
			$request->controllerType = $reRoute->controllerType;
		}
		
		// Default Action (any Controller)
		if (!$request->action && isset(self::$reRoutes["*"][$action])) {
			$reRoute = self::$reRoutes["*"][$action];
			$request->action = $reRoute->action;
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

		if (!method_exists($controllerObject, $request->action))
			throw new ErrorException("controller type [" . $request->controllerType . "] does not contain the action [" . $request->action . "]", 404);

		return $controllerObject->ExecuteRouteRequest($request);
	}

	public static function AddReRoute($request, $reRoute) {
		self::$reRoutes[strtolower($request->controllerType)][strtolower($request->action)] = $reRoute;
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
		if (!is_array($data) && !is_object($data))
			return self::GetActionUrlFriendly($controllerType, $action, array($data));
		elseif (is_array($data) && array_values($data) === $data)
			return self::GetActionUrlFriendly($controllerType, $action, $data);

		$path = self::$appRoot . ($controllerType ? self::GetControllerName($controllerType) . "/" : "") . ($action ? $action . "/" : "");
		$parameters = "?" . http_build_query($data);
		
		return (self::$writeLowercaseUrls ? strtolower($path) : $path) . $parameters;
	}

	private static function GetActionUrlFriendly($controllerType, $action, $data=array()) {
		$path = self::$appRoot . ($controllerType ? self::GetControllerName($controllerType) . "/" : "") . ($action ? $action . "/" : "");

		$parameters = "";
		foreach($data as $value) {
			if (!trim($value)) continue;
			// don't encode, but replace spaces
			// this could create problems
			$parameters .= str_replace(" ","+",trim($value)) . "/";
		}
		
		return self::$writeLowercaseUrls ? strtolower($path . $parameters) : ($path . $parameters);
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
}

MvcRouter::$appRoot = str_ireplace("index.php","",$_SERVER["PHP_SELF"]);

MvcRouter::AddReRoute(new MvcRouteRequest(), new MvcRouteRequest("home"));
MvcRouter::AddReRoute(new MvcRouteRequest("*",""), new MvcRouteRequest("*","index"));	// default action for any controller

?>