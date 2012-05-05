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

class MvcModelBinders {
	private static $modelBinders = array();

	public static function BindModels(&$controllerObject, $controllerAction, $data=array()) {
		$controllerObject->boundModels = array();

		// find the controller action parameters
		$reflMethod = new ReflectionMethod(get_class($controllerObject), $controllerAction);
		$methodParams = $reflMethod->getParameters();

		// bind each parameter of the controller action
		foreach ($methodParams as $methodParam) {
			$modelBinder = self::FindModelBinder($methodParam->getClass());
			$controllerObject->boundModels[$methodParam->getName()] = $modelBinder->BindModel($controllerObject->modelState, $methodParam, $data);
		}
	}

	private static function FindModelBinder($type) {
		if ($type == null) return new MvcDefaultModelBinder();
		$type = $type->name;

		if (isset(self::$modelBinders[$type])) {
			// TODO: test
			$t = self::$modelBinders[$type];
			return new $t;
		}
		else
			return new MvcDefaultModelBinder();
	}
}

abstract class MvcBaseModelBinder {

	abstract public function BindModel(&$modelState, $param, &$data=array());

	protected function FindValue($name, &$data) {
		// NOTE: See MvcRouteRequest definition for information about "$data."

		if (isset($data[$name]))
			return $data[$name];
		elseif (isset($_GET[$name]))
			return $_GET[$name];
		elseif (isset($_POST[$name]))
			return $_POST[$name];
		elseif (count($data) && array_values($data) === $data)
			return array_shift($data);
		else
			return null;
	}
}

class MvcDefaultModelBinder extends MvcBaseModelBinder {

	public function BindModel(&$modelState, $param, &$data=array()) {
		$model = null;

		if (@$param->getClass() !== null) {
			$model = $this->BindClassModel($param->getClass()->name, $data);
			if ($model instanceof IMvcModelValidator)
				$modelState = $this->ValidateClassModel($model);
		}
		else
			$model = $this->FindValue($param->getName(), $data);

		return $model;
	}

	private function BindClassModel($type, &$data) {
		$model = null;

		$reflectClass = new ReflectionClass($type);
		$classProperties = $reflectClass->getProperties();

		foreach($classProperties as $classProperty) {
			$value = $this->FindValue($classProperty->getName(), $data);

			if ($value !== null) {
				if ($model == null)
					$model = new $type;
				if ($classProperty->isPublic() == false)
					$classProperty->setAccessible(true);
				$classProperty->setValue($model, $value);
			}
		}

		return $model;
	}

	private function ValidateClassModel($model) {
		$modelState = new MvcModelState();

		$reflectClass = new ReflectionClass(get_class($model));
		$classProperties = $reflectClass->getProperties();

		foreach($classProperties as $classProperty) {
			$modelState->AddError($classProperty->getName(), $model->ValidateProperty($classProperty->getName()));
		}

		if ($modelState->IsValid())
			$modelState->AddError("", $model->ValidateModel());

		return $modelState;
	}
}

class MvcModelState {
	var $errors = array();

	public function AddError($key, $errorMsg) {
		$errorMsg = trim($errorMsg);
		if ($errorMsg)
			$this->errors[$key] = $errorMsg;
	}

	public function GetError($key="") {
		return (isset($this->errors[$key])) ? $this->errors[$key] : "";
	}

	public function Clear() {
		$this->errors = array();
	}

	public function IsValid() {
		return (count($this->errors) == 0);
	}
}

interface IMvcModelValidator {
	public function ValidateProperty($propertyName); // return string (error message)
	public function ValidateModel(); // return string (error message)
}

?>