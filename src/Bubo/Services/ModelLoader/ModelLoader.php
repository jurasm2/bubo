<?php

namespace Bubo\Services;

use Nette;
use Model;

class ModelLoader extends BaseService {
        private $context;
        private $connection;

        public function __construct($context) {
                $this->context = $context;
                $this->connection = $context->getService('database');
        }

        public function loadModel($modelName, $modelNamespace = 'Model') {
                $class = "$modelNamespace\\$modelName";
                if (class_exists($class)) {
                    return new $class($this->context);
                } else {
                    throw new Nette\InvalidStateException("Model class $class not found");
                }
        }
}