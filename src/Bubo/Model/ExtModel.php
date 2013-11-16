<?php

namespace Model;

use Nette;

/**
 * Ext model
 */
final class ExtModel extends BaseModel {

    /**
     *
     * @param string $key
     * @return mixed
     */
    public function _loadMeta($key)
    {
        $res = $this->connection->query('SELECT [value], [key] FROM [:core:ext_meta] WHERE [key] IN %in',(array) $key);
        return is_array($key) ? $res->fetchAssoc('key') : $res->fetchSingle();
    }

    /**
     *
     * @param string $key
     * @return int
     */
    private function _deleteMeta($key)
    {
        return $this->connection->query('DELETE FROM [:core:ext_meta] WHERE [key] IN %in', (array) $key);
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @return int
     */
    private function _insertMeta($key, $value)
    {
        $this->_deleteMeta($key);
        $data = array(
                'key'       =>  $key,
                'value'     =>  $value
        );
        return $this->connection->query('INSERT INTO [:core:ext_meta]', $data);
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     */
    private function _updateMeta($key, $value)
    {
        $this->_insertMeta($key, $value);
    }

    /**
     *
     * @param int $labelId
     * @param array $items
     */
    public function saveExtSorting($labelId, array $items)
    {
        $key = $labelId.'-extSortorder';
        if (!empty($items)) {
            $this->_updateMeta($key, serialize($items));
        }
    }

    /**
     *
     * @param int $labelId
     * @return mixed
     */
    public function loadExtSorting($labelId)
    {
        $key = $labelId.'-extSortorder';
        return $this->_loadMeta($key);
    }

    /**
     *
     * @param Nette\ArrayHash|array $data
     * @param int $labelId
     */
    public function saveEntityParamFormData($data, $labelId)
    {
        $key = $labelId.'-entityParams';
        $value = serialize($data);
        $this->_insertMeta($key, $value);
    }

    /**
     *
     * @param int $labelId
     * @param array $entityConfig
     * @return array
     */
    public function getDefaultsForEntityParamForm($labelId, array $entityConfig)
    {
        $key = $labelId.'-entityParams';
        $data = $this->_loadMeta($key);
        $defaults = array();

        if ($data !== FALSE) {
            $defaults = \Bubo\Utils\MultiValues::unserialize($data);
        } else {
            if ($entityConfig && isset($entityConfig['properties'])) {
                foreach ($entityConfig['properties'] as $entityParamName => $entityParam) {
                    $defaults[$entityParamName]['label'] = $entityParam['label'];
                }
            }
        }
        return $defaults;
    }

    /**
     *
     * @param array $properties
     * @param int $labelId
     * @return array
     */
    public function filterEntityProperties(array $properties, $labelId)
    {
        $key = $labelId.'-entityParams';
        $data = $this->_loadMeta($key);

        if ($data !== FALSE) {
            $defaults = \Bubo\Utils\MultiValues::unserialize($data);

            if ($defaults !== FALSE) {
                $newProperties = array();
                foreach ($properties as $propertyName => $property) {
                    if (isset($defaults[$propertyName])) {
                        if (!$defaults[$propertyName]['exclude']) {
                            $_p = $property;
                            $_p['label'] = $defaults[$propertyName]['label'];
                            $newProperties[$propertyName] = $_p;
                        }
                    }
                }
            }
            $properties = $newProperties;
        }

        return $properties;
    }
}