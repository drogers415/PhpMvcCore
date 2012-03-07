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

	public static function BindModels(&$controllerObject, $controllerAction, $data=array()) {
		$controllerObject->context->boundModels = array();

		// find the controller action parameters
		$reflMethod = new ReflectionMethod(get_class($controllerObject), $controllerAction);
		$methodParams = $reflMethod->getParameters();

		// bind each parameter of the controller action
		foreach ($methodParams as $methodParam) {
			$modelBinder = self::FindModelBinder($methodParam->getClass());
			$controllerObject->context->boundModels[$methodParam->getName()] = $modelBinder->BindModel($controllerObject->modelState, $methodParam, $data);
		}
	}

}

abstract class MvcBaseModelBinder {

	abstract public function BindModel(&$modelState, $param, &$data=array());

	public static function Unbind($x) {
		if (is_array($x)) return $x;

		if (is_object($x)) {
			$data = array();
			$type = get_class($x);
			$reflectClass = new ReflectionClass($type);
			$classProperties = $reflectClass->getProperties();

			foreach($classProperties as $property) {
				$value = $property->getValue($x);

				if (is_object($value))
					continue; // not supported

				$name = $property->getName();

				if (is_array($value))
					foreach($value as $k=>$v)
						$data[$name . "[" . $k . "]"] = $v;
				else
					$data[$name] = $value;
			}

			return $data;
		}
		else
			return array($x);
	}

	// bind value of function parameter
	protected function BindParameter(ReflectionParameter $param, &$data) {
		if (@$param->getClass() !== null)
			return null; // not supported
		else if ($param->isArray())
			return self::BindArray($param->getName(), $data);
		else
			return self::BindSimpleType($param->getName(), $data);
	}

	// bind value of class property
	protected function BindProperty($type, ReflectionProperty $property, &$data) {
		$value = $property->getValue(new $type);

		if (is_object($value))
			return null; // not supported
		else if (is_array($value))
			return self::BindArray($property->getName(), $data);
		else
			return self::BindSimpleType($property->getName(), $data);
	}

	// bind value of simple data type (int, string, boolean)
	private function BindSimpleType($name, &$data) {
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

	// bind 1-dimensional array
	private function BindArray($name, &$data) {
		$callback = create_function('$key', 'return strpos($key, "' . $name . '[") === 0;');

		$array = null;
		if (count($array = self::FilterArrayKeys($data, $callback)) < 1)
			if (count($array = self::FilterArrayKeys($_GET, $callback)) < 1)
				if (count($array = self::FilterArrayKeys($_POST, $callback)) < 1)
					return null;

		foreach($array as $key=>$value) {
			unset($array[$key]);
			$index = strtok($key, "[");
			$index = strtok("]");
			$array[$index] = $value;
		}

		return $array;
	}

	private function FilterArrayKeys($array, $callback) {
		if (!is_array($array)) {
			trigger_error( 'array_filter_key() expects parameter 1 to be array, ' . gettype($array) . ' given', E_USER_WARNING );
			return null;
		}
		
		if (empty($array)) return $array;
		
		$array = array_flip($array);
		$array = array_filter($array,$callback);
		if (empty($array)) return array();
		
		$array = array_flip($array);

		return $array;
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
			$model = $this->BindParameter($param, $data);

		return $model;
	}

	private function BindClassModel($type, &$data) {
		$model = null;

		$reflectClass = new ReflectionClass($type);
		$classProperties = $reflectClass->getProperties();

		foreach($classProperties as $classProperty) {
			$value = $this->BindProperty($type, $classProperty, $data);

			if ($value !== null) {
				if ($model == null) $model = new $type;
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