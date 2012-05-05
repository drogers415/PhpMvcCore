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

abstract class MvcController {
	public $routeRequest = null;
	public $viewData = array();
	public $modelState = null;
	public $boundModels = null;

	function __construct() {
    }

	abstract public function Index();

	public function ExecuteRouteRequest($request) {
		$this->routeRequest = new MvcRouteRequest();
		$this->modelState = new MvcModelState();

		$this->routeRequest = $request;

		// bind data
		MvcModelBinders::BindModels($this, $request->action, $request->data);

		// execute request
		return call_user_func_array(array($this, $request->action), $this->boundModels);
	}

	public static function Redirect($controllerName="", $action="", $data=array()) {
		$location = MvcRouter::GetActionUrl($controllerName, $action, $data);

		header("Location: " . $location);
		exit;
	}

	protected function ControllerName() {
		return MvcRouter::GetControllerName($this->routeRequest->controllerType);
	}

	protected function Action() {
		return $this->routeRequest->action;
	}

	protected function View($view="", $model=null) {
		if (!$view) $view = $this->routeRequest->action;

		$r = new MvcControllerViewResult();
		$r->routeRequest = $this->routeRequest;
		$r->view = $view;
		$r->viewData = $this->viewData;
		$r->model = $model;
		$r->modelState = $this->modelState;

		return $r;
	}

	protected function Json($model) {
		$r = new MvcControllerJsonResult();
		$r->routeRequest = $this->routeRequest;
		$r->json = json_encode($model);

		return $r;
	}
}


abstract class MvcControllerResult {
	var $routeRequest = null;

	function __construct() {
	}
}

class MvcControllerViewResult extends MvcControllerResult {
	var $view = "";
	var $viewData = array();
	var $model = null;
	var $modelState = null;

	function __construct() { 
        parent:: __construct();  
    } 
}

class MvcControllerJsonResult extends MvcControllerResult {
	var $json = "";

	function __construct() { 
        parent:: __construct();  
    }
}

?>