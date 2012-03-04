<?php

/**********************************************************************

	Copyright 2011, 2012 Dennis Rogers

	This file is part of PhpMvcCore.

    MvcCore is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    MvcCore is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Foobar.  If not, see <http://www.gnu.org/licenses/>.

************************************************************************/

class MvcHtml {
	
	public static $css = array("validationError"=>"validationError");

	public static function ActionLink($text, $controllerType, $action, $data=array(), $attributes=array()) {
		$html = "<a href=\"" 
			  . MvcRouter::GetActionUrl($controllerType, $action, $data)
			  . "\" " . self::ParseAttributes($attributes)
			  . ">" . $text . "</a>";

		return $html;
	}

	public static function HiddenFor($obj, $property) {
		return "<input type='hidden' id='$property' name='$property' value=\"" . $obj->$property . "\"/>";
	}

	public static function TextBoxFor($obj, $property, $attributes=array()) {
		return "<input type='text' id='$property' name='$property' value=\"" . $obj->$property . "\"  " . self::ParseAttributes($attributes) . "/>";
	}

	public static function PasswordFor($obj, $property, $attributes=array()) {
		return "<input type='password' id='$property' name='$property' value=\"" . $obj->$property . "\"  " . self::ParseAttributes($attributes) . "/>";
	}

	public static function CheckBoxFor($obj, $property, $attributes=array()) {
		$isCheck = $obj->$property == "on";

		return "<input type='checkbox' id='$property' name='$property'" . ($obj->$property == "on" ? "checked" : "") . "  " . self::ParseAttributes($attributes) . "/>";
	}

	public static function ValidationMessageFor(MvcViewContext $viewContext, $property, $customMsg=null, $attributes=array()) {
		if (!isset($attributes["class"])) $attributes["class"] = self::$css["validationError"];

		$errorMsg = $viewContext->modelState->GetError($property);
		if ($errorMsg) {
			if ($customMsg !== null) $errorMsg = $customMsg;
			return "<span " . self::ParseAttributes($attributes) . ">$errorMsg</span>";
		}
		return "";
	}

	public static function ValidationSummary($viewContext, $bShowPropertyErrors=true, $customMsg=null, $attributes=array()) {
		if ($viewContext->modelState->IsValid()) return "";
		if (!isset($attributes["class"])) $attributes["class"] = self::$css["validationError"];
		if ($customMsg === null) $customMsg = "Please correct the following error(s): ";

		$html = "<div " . self::ParseAttributes($attributes) . ">";
		$html .= $customMsg;

		if (isset($viewContext->modelState->errors[""]))
			$html .= $viewContext->modelState->errors[""];

		if ($bShowPropertyErrors) {
			$html2 = "";
			foreach($viewContext->modelState->errors as $key=>$propertyError) {
				if ($key)
					$html2 .= "<li>" . $propertyError . "</li>";
			}
			if ($html) $html .= "<ul>$html2</ul>";
		}

		$html .= "</div>";

		return $html;
	}

	//********************************************************************************** 

	public static function RenderControllerResult($result) {
		switch (get_class($result)) {
			case "MvcControllerViewResult":
				self::RenderControllerViewResult($result);
				break;
			case "MvcControllerJsonResult":
				self::RenderControllerJsonResult($result);
				break;
			default:
				break;
		}
	}

	public static function RenderControllerViewResult($result) {
		$__viewContext = new MvcViewContext();
		$__viewContext->routeRequest = $result->routeRequest;
		$__viewContext->viewData = $result->viewData;
		$__viewContext->modelState = $result->modelState;

		$__userContext = MvcSecurity::GetUserContext();

		$__model = $result->model;

		include(MvcRouter::GetViewFilePath($result->routeRequest->controllerType, $result->view));
	}

	public static function RenderControllerJsonResult($result) {
		//ob_start();
		echo $result->json;
		//header("Cache-Control: no-cache"); header("Pragma: no-cache"); 
		//header('Content-Length: ' . ob_get_length());
		//header('Content-type: application/json');
		//ob_end_flush();
	}

	public static function RenderAction($controllerName, $action, $data=array()) {
		$request = new MvcRouteRequest();
		$request->controllerType = MvcRouter::GetControllerType($controllerName);
		$request->action = $action;
		$request->data = $data;

		$result = MvcRouter::ExecuteRouteRequest($request);
		self::RenderControllerResult($result);
	}

	public static function RenderPartialView(MvcViewContext &$viewContext, $view, $__model=null) {
		include(MvcRouter::GetViewFilePath($viewContext->routeRequest->controllerType, $view));
	}

	private static function ParseAttributes($attributes) {
		$html = "";
		foreach($attributes as $key=>$value) {
			$html .= $key . "=\"" . $value . "\" ";
		}
		return trim($html);
	}
}

?>