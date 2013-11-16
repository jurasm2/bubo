<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
namespace Bubo\Services;

use Nette, Bubo, Nette\Caching\Cache;

class ConfigLoader extends BaseService {

    const CACHE_NAMESPACE = 'Bubo.ConfigLoader';

    private $context;

    public function __construct($context) {
        $this->context = $context;
    }

    public function load($configFile) {
        $loader = new Nette\Config\Loader;
        return $loader->load($configFile);
    }

    public function loadEntityConfig($entity, $mergeWithLabelExtensions = TRUE) {

        $cacheKey = $entity;
        $cache = new Cache($this->context->cacheStorage, self::CACHE_NAMESPACE);
        $val = $cache->load($cacheKey);

        if ($val === NULL) {

            $params = $this->context->getParameters();
            $configFile = $params['projectDir'] . '/config/entities/'.$entity.'.neon';

            if (!is_file($configFile)) {
                throw new \Nette\FileNotFoundException("Entity config file '$configFile' was not found");
            }

            $loader = new Nette\Config\Loader;
            $entityConfig = $loader->load($configFile);

            if ($mergeWithLabelExtensions) {
                $labelProperties = $this->loadLabelExtentsionProperties();

                array_walk($entityConfig['properties'], function(&$item) use ($labelProperties) {
                    // if entity params contains reference to ext, expand it
                    if (isset($item['extName'])) {
                        if (isset($labelProperties['properties'][$item['extName']])) {
                            $extParam = $labelProperties['properties'][$item['extName']];
                            $item = array_merge($extParam, $item);
                        }
                    }
                });

            }

            $dp = array(
                    Cache::FILES => array(
                                        $configFile,
                                        CONFIG_DIR . '/labels/labelExtensions.neon',
                                        $params['projectDir'] . '/config/labels/labelExtensions.neon'
                    )
            );

            $cache->save($cacheKey, $entityConfig, $dp);
            $val = $entityConfig;

        }

        return $val;
    }

    public function loadMandatoryProperties() {
        $configFile = CONFIG_DIR . '/pages/mandatory.neon';
        $loader = new Nette\Config\Loader;
        return $loader->load($configFile);
    }

    public function loadLabelExtentsionProperties() {
        $params = $this->context->getParameters();
        $loader = new Nette\Config\Loader;

        $commonConfigFile = CONFIG_DIR . '/labels/labelExtensions.neon';
        $projectConfigFile = $params['projectDir'] . '/config/labels/labelExtensions.neon';

        $config = $loader->load($commonConfigFile);

        if (is_file($projectConfigFile)) {
            $projectConfig = $loader->load($projectConfigFile);
            $config = \Nette\Utils\Arrays::mergeTree($projectConfig, $config);
        }

//        dump($config);

        return $config;
    }


    public function loadLayoutConfig() {
        $configFile = CONFIG_DIR . '/layouts/layouts.neon';
        $loader = new Nette\Config\Loader;
        return  $loader->load($configFile);
    }

    private function _findAllNamespacedModules($allModules, $namespace) {

        $output = array();

        foreach ($allModules as $moduleName => $module) {

            if (isset($module['namespace']) && $module['namespace'] == $namespace) {
                $output[$moduleName] = $module;
            }

        }

        return $output;
    }

    public function loadEntities($createUrl = TRUE) {
        $params = $this->context->getParameters();
        $entityConfigDir = $params['projectDir'] . '/config/entities';

        $loader = new Nette\Config\Loader;

        $entities = array();

        if (is_dir($entityConfigDir)) {
            foreach (\Nette\Utils\Finder::findFiles('*.neon')
                    ->in($entityConfigDir) as $key => $file) {

                        $load = $loader->load($key);

                        if (!isset($load['entityMeta'])) {
                            throw new Nette\InvalidStateException("Section 'entityMeta' is missing in entity config file '$key'");
                        }

                        if (isset($load['entityMeta']['createUrl'])) {
                            $simpleName = substr($file->getBaseName(), 0, -5);
                            if ($createUrl && $load['entityMeta']['createUrl']) {
                                $entities[$simpleName] = isset($load['entityMeta']['title']) ? $load['entityMeta']['title'] : $simpleName;
                            } else if (!$createUrl && !$load['entityMeta']['createUrl']) {
                                $entities[$simpleName] = isset($load['entityMeta']['title']) ? $load['entityMeta']['title'] : $simpleName;
                            }
                        } else {
                            throw new Nette\InvalidArgumentException("Missing parameter 'createUrl' in entity config file '$key'");
                        }


//                        $template = $file->getRealPath();
//                        break;
            }
        }

        return $entities;
    }

    public function loadModulesConfig($currentModule = NULL) {
        $params = $this->context->getParameters();

        $configFile = $params['projectDir'] . '/config/project.neon';
        $loader = new Nette\Config\Loader;
        $load = $loader->load($configFile);

//        dump($load);
//        die();

        if ($currentModule !== NULL) {

            if (isset($load['modules'][$currentModule])) {
                // module is present in config file
                // is this module namespaced?
                $namespace = NULL;
                if (isset($load['modules'][$currentModule]['namespace'])) {
                    $namespace = $load['modules'][$currentModule]['namespace'];
                }

                if ($namespace === NULL) {
                    return array('modules' => array($currentModule => $load['modules'][$currentModule]));
                } else {
                    return array('modules' => $this->_findAllNamespacedModules($load['modules'], $namespace));
                }

            }

        }

        return $load;
    }

}
