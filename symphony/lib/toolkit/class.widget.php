<?php

	Class Widget{

		protected static $ready = false;
		protected static $Symphony;

		public function init() {
			self::$Symphony = Administration::instance()->Page;
			self::$ready = true;
		}

		public static function Label($name=null, SymphonyDOMElement $child=null, array $attributes = array()){
			if(!self::$ready) Widget::init();

			$label = Widget::$Symphony->createElement('label', $name, $attributes);

			if($child instanceof SymphonyDOMElement) $label->appendChild($child);

			return $label;
		}

		public static function Input($name, $value=null, $type='text', array $attributes = array()){
			if(!self::$ready) Widget::init();

			$obj = Widget::$Symphony->createElement('input', null, $attributes);
			$obj->setAttribute('name', $name);

			if($type) $obj->setAttribute('type', $type);

			if(strlen($value) != 0) $obj->setAttribute('value', $value);

			return $obj;
		}

		public static function Textarea($name, $value=null, array $attributes = array()){
			if(!self::$ready) Widget::init();

			$obj = Widget::$Symphony->createElement('textarea', $value, $attributes);

			$obj->appendChild(Widget::$Symphony->createTextNode(''));

			$obj->setAttribute('name', $name);

			return $obj;
		}

		public static function Anchor($value, $href, array $attributes = array()){
			if(!self::$ready) Widget::init();

			$a = Widget::$Symphony->createElement('a', $value, $attributes);
			$a->setAttribute('href', $href);

			return $a;
		}

		public static function Form($action, $method, array $attributes = array()){
			if(!Widget::$ready) Widget::init();

			$form = Widget::$Symphony->createElement('form', null, $attributes);
			$form->setAttribute('action', $action);
			$form->setAttribute('method', $method);

			return $form;
		}

		## First take at a generic fieldset builder for the new form layout
		public static function Fieldset($value=null, $help=null, array $attributes = array()){
			if(!self::$ready) Widget::init();

			$fieldset = Widget::$Symphony->createElement('fieldset', null, $attributes);

			if($title){
				$fieldset->appendChild(
					Widget::$Symphony->createElement('h3',$value)
				);
			}
			if($help){
				$fieldset->appendChild(
					Widget::$Symphony->createElement('p', $help, array(
							'class' => 'help'
					))
				);
			}

			return $fieldset;
		}

		###
		# Simple way to create generic Symphony table wrapper
		public static function Table($head=null, $foot=null, $body=null, array $attributes = array()){
			if(!self::$ready) Widget::init();

 			$table = Widget::$Symphony->createElement('table');

			$table->setAttributeArray($attributes);

			if($head) $table->appendChild($head);
			if($foot) $table->appendChild($foot);
			if($body) $table->appendChild($body);

			return $table;
		}

		## Provided with an array of colum names, this will return an XML object
		public static function TableHead($columns){
			if(!self::$ready) Widget::init();

			$thead = Widget::$Symphony->createElement('thead');
			$tr = Widget::$Symphony->createElement('tr');

			foreach($columns as $col){
				$th = Widget::$Symphony->createElement('th');

				$value = $scope = null;

				$value = $col[0];
				if(isset($col[1])) $scope = $col[1];
				if(isset($col[2])) $th->setAttributeArray($col[2]);

				$th->setValue($value);

				if($scope && $scope != '') $th->setAttribute('scope', $scope);

				$tr->appendChild($th);
			}

			$thead->appendChild($tr);

			return $thead;
		}

		public static function TableBody($rows, array $attributes = array()){
			if(!self::$ready) Widget::init();

			$tbody = Widget::$Symphony->createElement('tbody');
			$tbody->setAttributeArray($attributes);

			foreach($rows as $r) $tbody->appendChild($r);

			return $tbody;
		}

		public static function TableRow($data, array $attributes = array()){
			if(!self::$ready) Widget::init();

			$tr = Widget::$Symphony->createElement('tr');
			$tr->setAttributeArray($attributes);

			foreach($data as $d) $tr->appendChild($d);

			return $tr;
		}

		public static function TableData($value, array $attributes = array()){
			if(!self::$ready) Widget::init();

			$td = Widget::$Symphony->createElement('td');
			$td->setValue($value);

			$td->setAttributeArray($attributes);

			return $td;
		}

		public static function Select($name, $options, array $attributes = array()){
			if(!self::$ready) Widget::init();

			$obj = Widget::$Symphony->createElement('select', null, $attributes);
			$obj->setAttribute('name', $name);

			$obj->appendChild(Widget::$Symphony->createTextNode(''));

			if(!is_array($options) || empty($options)){
				if(!isset($attributes['disabled'])) $obj->setAttribute('disabled', 'disabled');

				return $obj;
			}

			foreach($options as $o){

				## Opt Group
				if(isset($o['label'])){

					$optgroup = Widget::$Symphony->createElement('optgroup');
					$optgroup->setAttribute('label', $o['label']);

					foreach($o['options'] as $opt){
						$optgroup->appendChild(
							Widget::__SelectBuildOption($opt)
						);
					}

					$obj->appendChild($optgroup);
				}

				## Non-Opt group
				else $obj->appendChild(Widget::__SelectBuildOption($o));

			}

			return $obj;
		}

		private static function __SelectBuildOption($option){
			if(!self::$ready) Widget::init();

			@list($value, $selected, $desc, $class, $id, $attr) = $option;
			if(!$desc) $desc = $value;

			$obj = Widget::$Symphony->createElement('option', "$desc");
			$obj->appendChild(Widget::$Symphony->createTextNode(''));
			$obj->setAttribute('value', (string)$value);

			if(!empty($class)) $obj->setAttribute('class', $class);
			if(!empty($id)) $obj->setAttribute('id', $id);
			if($selected) $obj->setAttribute('selected', 'selected');

			if(!empty($attr)) $obj->setAttributeArray($attr);

			return $obj;

		}

		public static function wrapFormElementWithError($element, $message=null){
			if(!self::$ready) Widget::init();

			$div = Widget::$Symphony->createElement('div');
			$div->setAttributeArray(array('id' => 'error', 'class' => 'invalid'));

			$div->appendChild($element);

			if(!is_null($message)){
				$div->appendChild(
					Widget::$Symphony->createElement('p', $message)
				);
			}

			return $div;

		}

	}

