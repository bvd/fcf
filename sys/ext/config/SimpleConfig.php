<?php
/*
 * 
 * Copyright (c) 2010, Patrick Forget - http://geekpad.ca
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions 
 * are met:
 * 
 * Redistributions of source code must retain the above copyright 
 * notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright 
 * notice, this list of conditions and the following disclaimer in the 
 * documentation and/or other materials provided with the distribution.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT 
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS 
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE 
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, 
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, 
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; 
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER 
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT 
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN 
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE 
 * POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Singleton with configuration info
 * 
 * @author Patrick Forget - http://geekpad.ca
 * @since Sun Jan 17 03:29:42 GMT 2010
 * @copyright 2010 Patrick Forget
 */
class SimpleConfig implements ArrayAccess, Countable, IteratorAggregate
{
    
    /**
     * instance
     * @var SimpleConfig
     */
    protected static $_instance = null;
    
    /**
     * instance
     * @var SimpleConfig
     */
    protected static $_configFile = '';
    
    /**
     * config array
     * @var array
     */
    protected $_values = array();
    
    /**
     * retreive one and only instance of config
     *
     * @author Patrick Forget - http://geekpad.ca
     * @since Sun Jan 17 03:30:44 GMT 2010 
     */
    public static function getInstance() 
    {
        //echo "get instance function<br>";
    	if (self::$_instance === null) {
            //echo "instantiating<br>";
    		$c = __CLASS__;
            self::$_instance = new $c;
            //echo "instantiated<br>";
        } //if
        //echo "returning<br>";
        return self::$_instance;
    } // getInstance()
    
    /**
     * sets the path to the config file
     *
     * @author digit
     * @since Mon Jan 18 05:50:43 GMT 2010 
     */
    public static function setFile($filePath) 
    {
        /* make sure instance doesn't exist yet */
        if (self::$_instance !== null) {
            throw new Exception('You need to set the path before calling '. __CLASS__ .'::getInstance() method', 0);
        } else {
            self::$_configFile = $filePath;
        } //if
    } // setFile()
    
    
    /**
     * class constructor
     */
    protected function __construct()
    {
        
        $values = @include( self::$_configFile );
        if (is_array($values)) {
            $this->_values = &$values;
        } //if
        
    } // __construct()
    
    /**
     * prevent cloning
     *
     * @author Patrick Forget - http://geekpad.ca
     * @since Sun Jan 17 03:32:57 GMT 2010 
     */
    final protected function __clone() { 
        // no cloning allowed
    } // __clone()
    
    /**
     * returns number of elements iside config
     *
     * @author Patrick Forget - http://geekpad.ca
     * @since Sun Jan 17 03:53:00 GMT 2010 
     * @return integer number of elements inside config
     */
    public function count() 
    {
        return sizeof($this->_values);
    } // count()
    
    /**
     * checks if a given key exists
     *
     * @author Patrick Forget - http://geekpad.ca
     * @since Sun Jan 17 03:56:35 GMT 2010 
     * @param mixed $offset key of item to check
     * @return boolean true if key exisits, false otherwise
     */
    public function offsetExists($offset) 
    {
        return key_exists($offset, $this->_values);
    } // offsetExists()
    
    /**
     * retreive the value of a given key
     *
     * @author Patrick Forget - http://geekpad.ca
     * @since Sun Jan 17 03:57:08 GMT 2010 
     * @param mixed $offset key of item to fetch
     * @return mixed value of the matched element
     */
    public function offsetGet($offset) 
    {
        return $this->_values[$offset];
    } // offsetGet()
    
    /**
     * assigns a new value to a key
     *
     * @author Patrick Forget - http://geekpad.ca
     * @since Sun Jan 17 03:58:20 GMT 2010 
     * 
     * @param mixed $offset key of the element to set
     * @param mixed $value value to assign
     */
    public function offsetSet($offset, $value) 
    {
        $this->_values[$offset] = $value;
    } // offsetSet()
    
    /**
     * removes an item from the config
     *
     * @author Patrick Forget - http://geekpad.ca
     * @since Sun Jan 17 03:58:54 GMT 2010 
     * 
     * @param mixed $offset key of the elment to remove
     */
    public function offsetUnset($offset) 
    {
        unset($this->_values[$offset]);
    } // offsetUnset()
    
    /**
     * retrive an iterator for config values
     *
     * @author Patrick Forget - http://geekpad.ca
     * @since Sun Jan 17 03:59:56 GMT 2010 
     * @return Iterator iterator of config values
     */
    public function getIterator() 
    {
        return new ArrayIterator($this->_values);
    } // getIterator()
    
    /**
     * enables to set values using the object notation i.e. $config->myValue = 'something';
     *
     * @author Patrick Forget - http://geekpad.ca
     * @since Sun Jan 17 04:00:48 GMT 2010 
     */
    public function __set($key, $value) 
    {
        $this->_values[$key] = $value;
    } // __set()
    
    /**
     * enables to get values using the object notation i.e. $config->myValue;
     *
     * @author Patrick Forget - http://geekpad.ca
     * @since Sun Jan 17 04:03:33 GMT 2010 
     */
    public function __get($key) 
    {
        return $this->_values[$key];
    } // __get()
    
} // SimpleConfig class
?>