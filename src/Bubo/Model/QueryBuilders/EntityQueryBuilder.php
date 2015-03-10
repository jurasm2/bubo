<?php

namespace Model\QueryBuilders;

use Nette;

/**
 * Entity query bulider
 * 
 * Responsible for scalable maintenance of integration of other db tables that
 * should be connected to the main page table.
 */
final class EntityQueryBuilder extends Nette\Object {

    private $context;
    private $connection;
    
    private $defaultDbTable = '[:core:pages]';
    private $extValuesDbTable = '[:core:extended_values]';
    private $using = '[page_id]';
    
    public function __construct($context) {
        $this->context = $context;
        $this->connection = $context->getService('database');
    }
    
    public function getExtValuesDbTable() {
        return $this->extValuesDbTable;
    }
    
    /**
     * Takes list of properties from entity config file and (optionally)
     * name of the group and gathers properties belonging to same table together.
     * This information is stored and returned in $tableMapping.
     * 
     * $tableMapping =>  array(
     *                     'table1' => array(
     *                                       'propertyName1' => 'attribute1'
     *                                       'propertyName2' => 'attribute2'
     *                                   )
     *                  )
     * 
     * If the group is NULL, then all attributes are taken.
     * 
     * By default, the list of mandatory attributes is appended to 
     * $defaultDbTable
     * 
     * 
     * 
     * @param array $properties
     * @param array|NULL $group
     */
    private function _extractTableMapping($properties, $groupParams = NULL) {
        $tableMapping = array();
        
        $keys = ($groupParams !== NULL) ? array_intersect(array_keys($properties), $groupParams) : array_keys($properties);

        if (!empty($keys)) {
            foreach ($keys as $key) {
                $property = $properties[$key];
                
                if (isset($property['db'])) {
                    // change in db storage
                    $tableName = isset($property['db']['table']) ? $property['db']['table'] : $this->defaultDbTable;
                    $attribName = isset($property['db']['attribute']) ? $property['db']['attribute'] : $key;

                } else {
                    $tableName = $this->defaultDbTable;
                    $attribName = $key;
                }


                $tableMapping[$tableName][$key] = $attribName;
            }
        }
        
        uksort($tableMapping, array($this, '_compareTables'));
        return $tableMapping;
    }
    
    
    
    private function _compareTables($a, $b) {
        if ($a == $this->defaultDbTable) {
            return -1;
        } else if ($b == $this->defaultDbTable) {
            return 1;
        } else {
            return strcasecmp($a, $b);
        }
    }
    
    
    private function _extractTrueValues($array) {
        $trueValues = array();
        foreach ($array as $key => $bool) {
            if ($bool) {
                $trueValues[] = $key;
            }
        }
        return $trueValues;
    }
    
    /**
     * Accepts table list and returns sql chunk
     * joining all the tables using ($this->using) attribute
     * 
     * @param array $tableList
     * @return String sql
     */
    private function _createSqlTableJoins($tableList) {        
        $sql = '';
        
        if (!empty($tableList)) {
            foreach ($tableList as $table) {
                $sql .= " JOIN $table USING ($this->using) ";
            }
        }         
        return $sql;
    }

    /**
     * Returns list of attributes
     * 
     * @param array $attributeList
     * @return String sql
     */
    private function _createSqlAttributeList($attributeList) {
        return empty($attributeList) ? '' : ', '.implode(', ',$attributeList);
    }
    
    
    
    
    /**
     * Basic method for creating select queries for pages.
     * 
     * Entity config contains parsed neon configuration.
     * If $entityConfig is not provided, only mandatory attributed are loaded
     * = lite mode
     * 
     * GroupName specifies the group of parameters to be loaded.
     * If $groupName is not provided, then all parameters are loaded.
     * 
     * Returns 2 sql chunks
     * - tables: tables except [:core:pages] needed for loading all desired 
     *           attributes joined using [page_id]
     * - attributes: comma separated list of attributes
     * 
     * These 2 chunks can be directly inserted into prepared SQL queries
     * 
     * @param array|NULL $entityConfig
     * @param String|NULL $groupName
     * @return array
     * @throws \Nette\InvalidStateException
     * 
     */
    public function getEntitySelectChunks($entityConfig = NULL, $groupName = NULL) {
        
        $tables = array();
        
        // add mandatory parameters (mandatories)
        $mandatories = $this->context->getService('configLoader')->loadMandatoryProperties();
        if (!empty($mandatories)) {
            foreach ($mandatories as $name => $junk) {
                $attributes[] = $this->defaultDbTable.'.['.$name.']';
            }
        }

        if ($entityConfig !== NULL) {
        
            if ($groupName !== NULL && !isset($entityConfig['groups'][$groupName])) {
                throw new \Nette\InvalidStateException("Group '$groupName' does not exist in entityConfig file");
            }
            
            $entityConfigGroup = $groupName !== NULL ? $this->_extractTrueValues($entityConfig['groups'][$groupName]) : NULL;
            $tableMapping = $this->_extractTableMapping($entityConfig['properties'], $entityConfigGroup);

            
            // procees mapping
            foreach ($tableMapping as $table => $properties) {
                if ($table != $this->defaultDbTable) {
                    $tables[] = $table;
                }          

                foreach ($properties as $propertyName => $attribName) {                                
                    $attributes[] = $table.'.'.$attribName.' AS ['.$propertyName.']';
                }
            }
            
        }
        
        return array(
                'tables'        =>  $this->_createSqlTableJoins($tables),
                'attributes'    =>  $this->_createSqlAttributeList($attributes)
        ); 
        
    }
   
    /**
     * Return the list of db attributes associated by table names.
     * The list can be easily used as an input for model.
     * 
     * @param array $entityConfig
     * @param array $currentPageData
     * @return array list of db attributes associated by table names
     * @throws \Nette\InvalidArgumentException
     */
    public function getPageInserts($entityConfig, $currentPageData) {
        
        $mandatories = $this->context->getService('configLoader')->loadMandatoryProperties();
        // by default 
        $allProperties = array_merge($mandatories, $entityConfig['properties']);        
        $tableMapping = $this->_extractTableMapping($allProperties);
        
        $pageInserts = array();
        foreach ($tableMapping as $tableName => $tableAttribs) {
            
            $keys1 = array_intersect_key($tableAttribs, (array) $currentPageData);
            $keys2 = array_intersect_key((array) $currentPageData, $tableAttribs);
            $pageInserts[$tableName] = $result = array_combine($keys1, $keys2);
            
        }
        
        // add core properties       
        $coreProperties = array(
                            'entity',
                            'tree_node_id',
                            'version',
                            'lang',
                            'status'                            
        );
        
        foreach ($coreProperties as $coreProperty) {
            
            if (!isset($currentPageData[$coreProperty])) {
                throw new \Nette\InvalidArgumentException("Core property $coreProperty was not provided during saving page.");
            } else {
                $pageInserts[$this->defaultDbTable][$coreProperty] = $currentPageData[$coreProperty];
            }            
        }
        
        // process extended values
        foreach ($currentPageData as $key => $value) {
            if (preg_match('#ext\_(.*)#', $key, $matches)) {
                $_value = $value;
                
                if (is_array($value) || $value instanceof Nette\Http\FileUpload) {
                    $_value = TRUE;
                }
                $pageInserts[$this->extValuesDbTable][] = array(
                                                            'identifier'    =>  $matches[1],
                                                            'value'         =>  $_value
                );
            }
        }
        
        return $pageInserts;
        
    }
    
}