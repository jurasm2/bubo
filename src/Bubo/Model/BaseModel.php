<?php

namespace Model;

use Nette;

class BaseModel extends Nette\Object {

    public $context;
    
    /**
     *
     * @var \DibiConnection
     */
    public $connection;
    
    // to allow call models between each other
    public $modelLoader;

    public function __construct($context) {
        $this->context = $context;
        $this->connection = $context->getService('database');
        $this->modelLoader = $context->getService('modelLoader');
    }

    public function __call($methodName, $args) {
        if (preg_match('|.*getModel([a-zA-Z0-9]+).*|', $methodName, $mtch)) {
            if (class_exists('Model\\' . $mtch[1] . 'Model')) {
                return $this->modelLoader->loadModel($mtch[1] . 'Model');
            }
        }

        return parent::__call($methodName, $args);
    }
    
    public function createSelectData($dbData, $valueItem) {
        $selectData = array();
        foreach ($dbData as $index => $dbDataItem) {
            if (is_array($valueItem)) {
                $finalValueChunks = array();
                foreach($valueItem as $i) {
                    $finalValueChunks[] = $dbDataItem[$i];
                }
                $selectData[$index] = implode(' ', $finalValueChunks);
            } else {
                $selectData[$index] = $dbDataItem[$valueItem];
            }

        }
        return $selectData;
    }

    public function getLastId() {
        return $this->connection->getInsertId();
    }
    
    public function extractTrueValues($array) {        
        $out = array();        
        if (!empty($array)) {
            foreach ($array as $key => $bool) {
                if ($bool) {
                    $out[$key] = $bool;
                }
            }
        }        
        return $out;        
    }
    
}