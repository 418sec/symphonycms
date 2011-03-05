<?php
	
	/**
	 * @package toolkit
	 */
	/**
	 * XMLElement is a class used to simulate PHP's DOMElement
	 * class. Each object is a representation of a HTML element
	 * and can store it's children in an array. When an XMLElement
	 * is generated, it is output as an XML string.
	 */
	class XMLElement {
		static protected $document;
		static protected $reflection;
		static public $useUnstableSetValue;
		
		protected $element;
		protected $documentType;
		protected $includeHeader;
		protected $outputAsHTML;
		
		public function __construct($name, $value = null, array $attributes = null) {
			if (!isset(self::$document)) {
				self::$document = new DOMDocument();
				self::$document->recover = true;
				self::$document->resolveExternals = true;
				self::$document->strictErrorChecking = false;
				self::$document->formatOutput = false;
				self::$document->substituteEntities = true;
				
				self::$document->loadXML('<!DOCTYPE body SYSTEM "symphony/assets/entities.dtd"><data/>', LIBXML_DTDLOAD | LIBXML_NOBLANKS);
				self::$reflection = new ReflectionClass('DOMElement');
			}
			
			if ($name instanceof DOMElement) {
				$this->element = $name;
			}
			
			else if (is_string($name)) {
				$this->element = self::$document->createElement($name);
				$this->setValue($value);
				
				if (is_array($attributes)) {
					$this->setAttributeArray($attributes);
				}
			}
			
			else {
				throw new Exception('Expecting string or DOMElement.');
			}
			
			$this->includeHeader = false;
			$this->outputAsHTML = false;
		}
		
		public function __call($name, $args) {
			$method = self::$reflection->getMethod($name);
			
			foreach ($args as $index => $value) {
				if (!$value instanceof self) continue;
				
				$args[$index] = $value->element;
			}
			
			return $method->invokeArgs($this->element, $args);
		}
		
		public function __clone() {
			$this->element = clone $this->element;
		}
		
		public function __get($name) {
			return $this->element->{$name};
		}
		
		public function __set($name, $value) {
			$this->element->{$name} = $value;
		}
		
		/**
		 * A convenience method to add children to an XMLElement
		 * quickly.
		 *
		 * @param array $children
		 */
		public function appendChildArray(array $children) {
			foreach ($children as $child) {
				$this->appendChild($child);
			}
		}
		
		/**
		 * Return all child elements
		 *
		 * @return array
		 */
		public function getChildren() {
			$children = array();
			
			foreach ($this->childNodes as $node) {
				if (!$node instanceof DOMElement) continue;
				
				$children[] = new self($node);
			}
			
			return $children;
		}
		
		/**
		 * Return the inner element
		 *
		 * @return DOMElement
		 */
		public function getElement() {
			return $this->element;
		}
		
		/**
		 * Return the element name
		 *
		 * @return string
		 */
		public function getName() {
			return $this->nodeName;
		}
		
		/**
		 * Return the element value
		 *
		 * @return string
		 */
		public function getValue() {
			if (!$this->hasChildNodes()) return null;
			
			$value = null;
			
			foreach ($this->childNodes as $node) {
				$value .= self::$document->saveXML($node);
			}
			
			return $value;
		}
		
		/**
		 * This function will turn the XMLElement into a string
		 * representing the element as it would appear in the markup.
		 * It is valid XML.
		 *
		 * @param boolean $indent
		 *  Defaults to false. Not fully implemented.
		 * @return string
		 */
		public function generate($indent = false) {
			self::$document->formatOutput = $indent;
			$output = $this->element->ownerDocument->saveXML($this->element);
			self::$document->formatOutput = false;
			
			/**
			* @todo find a better way of handling this error:
			* "Couldn't fetch DOMElement. Node no longer exists"
			*/
			
			if ($this->documentType) {
				$output = $this->documentType
					. ($indent ? PHP_EOL : null)
					. $output;
			}
			
			if ($this->includeHeader) {
				$output = '<?xml version="1.0" encoding="utf-8" ?>'
					. ($indent ? PHP_EOL : null)
					. $output;
			}
			
			return $output;
		}
		
		/**
		 * Adds an XMLElement to the start of the children
		 * array, this will mean it is output before any other
		 * children when the XMLElement is generated
		 *
		 * @param XMLElement $child
		 */
		public function prependChild($child) {
			if (is_null($this->firstChild)) {
				$this->appendChild($child);
			}
			
			else {
				$this->insertBefore($child, $this->firstChild);
			}
		}
		
		/**
		 * Before passing onto the DOM Element we must decode
		 * all HTML entities.
		 *
		 * @param string $name
		 * @param string $value
		 */
		public function setAttribute($name, $value) {
			$this->element->setAttribute($name, html_entity_decode($value));
		}
		
		/**
		 * A convenience method to quickly add multiple attributes to
		 * an XMLElement
		 *
		 * @param array $attributes
		 *  Associative array with the key being the name and
		 *  the value being the value of the attribute.
		 */
		public function setAttributeArray(array $attributes) {
			foreach ($attributes as $name => $value) {
				$this->setAttribute($name, $value);
			}
		}
		
		/**
		 * Sets the DTD for this XMLElement
		 *
		 * @param string $dtd
		 */
		public function setDTD($value) {
			$this->documentType = $value;
		}
		
		/**
		 * Deprecated.
		 */
		public function setElementStyle($style = 'xml') {
			$this->outputAsHTML = ($style == 'html');
		}
		
		/**
		 * Sets whether this XMLElement needs to output an
		 * XML declaration or not. This normally is only set to
		 * true for the parent XMLElement, eg. 'html'.
		 *
		 * @param string $value (optional)
		 *  Defaults to false
		 */
		public function setIncludeHeader($value = false){
			$this->includeHeader = $value;
		}
		
		/**
		 * Deprecated.
		 */
		public function setSelfClosingTag($value = true) {
			
		}
		
		public function setValue($value) {
			if (is_null($value) || $value == '') return;
			
			foreach ($this->childNodes as $node) {
				if ($node instanceof DOMText || $node instanceof DOMElement) {
					$this->removeChild($node);
				}
			}
			
			/**
			* @todo Method 1: Determine if the following code causes segfaults.
			*/
			if (self::$useUnstableSetValue === true) {
				$fragment = self::$document->createDocumentFragment();
				$fragment->appendXML($value);
				$this->appendChild($fragment);
			}
			
			/**
			* @todo Method 2: Slower than method 1, but more stable.
			*/
			else {
				$document = clone self::$document;
				$document->loadXML('<!DOCTYPE data SYSTEM "symphony/assets/entities.dtd"><data>' . $value . '</data>', LIBXML_DTDLOAD);
				
				$this->nodeValue = '';
				
				foreach ($document->documentElement->childNodes as $node) {
					$node = self::$document->importNode($node, true);
					$this->appendChild($node);
				}
			}
		}
	}
	
?>