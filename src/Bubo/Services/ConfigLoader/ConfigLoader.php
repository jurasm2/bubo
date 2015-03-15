<?php

namespace Bubo\Services;

use Bubo;
use Nette;
use Nette\Caching\Cache;
use Nette\FileNotFoundException;

/**
 * Class ConfigLoader
 * @package Bubo\Services
 */
class ConfigLoader extends BaseService
{
    const CACHE_NAMESPACE = 'Bubo.ConfigLoader';

	/**
	 * @var string
	 */
	protected $projectDir;

	/**
	 * @var Nette\DI\Config\Adapters\NeonAdapter
	 */
	protected $loader;

	/**
	 * @var Nette\Caching\IStorage
	 */
	protected $cacheStorage;

	/**
	 * Constructor
	 * @param string $projectDir
	 */
    public function __construct(Nette\Caching\IStorage $cacheStorage, $projectDir)
    {
	    $this->cacheStorage = $cacheStorage;
		$this->projectDir = $projectDir;

	    $this->loader = new Nette\DI\Config\Adapters\NeonAdapter();
    }

	/**
	 * Returns parsed neon file
	 * @param string $configFile
	 * @return array
	 */
    public function load($configFile)
    {
        return $this->loader->load($configFile);
    }

	/**
	 * Loads entity configuration
	 * @param string $entity
	 * @param bool $mergeWithLabelExtensions
	 * @return array|mixed|NULL
	 */
    public function loadEntityConfig($entity, $mergeWithLabelExtensions = TRUE)
    {
        $cacheKey = $entity;
        $cache = new Cache($this->cacheStorage, self::CACHE_NAMESPACE);
        $val = $cache->load($cacheKey);

        if ($val === NULL) {

	        // get config file of given project (module)
            $configFile = $this->projectDir . '/config/entities/'.$entity.'.neon';

            if (!is_file($configFile)) {
                throw new FileNotFoundException("Entity config file '$configFile' was not found");
            }

            $entityConfig = $this->loader->load($configFile);

            if ($mergeWithLabelExtensions) {
	            // what the hell???
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
                                        $this->projectDir . '/config/labels/labelExtensions.neon'
                    )
            );

            $cache->save($cacheKey, $entityConfig, $dp);
            $val = $entityConfig;

        }

        return $val;
    }

    public function loadMandatoryProperties() {
        $configFile = CONFIG_DIR . '/pages/mandatory.neon';
        return $this->loader->load($configFile);
    }

    public function loadLabelExtentsionProperties() {
        $commonConfigFile = CONFIG_DIR . '/labels/labelExtensions.neon';
        $projectConfigFile = $this->projectDir . '/config/labels/labelExtensions.neon';

        $config = $this->loader->load($commonConfigFile);

        if (is_file($projectConfigFile)) {
            $projectConfig = $this->loader->load($projectConfigFile);
            $config = \Nette\Utils\Arrays::mergeTree($projectConfig, $config);
        }

//        dump($config);

        return $config;
    }


    public function loadLayoutConfig() {
        $configFile = CONFIG_DIR . '/layouts/layouts.neon';
        return  $this->loader->load($configFile);
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
	    // project DIR
        $entityConfigDir = $this->projectDir . '/config/entities';

        $entities = array();

        if (is_dir($entityConfigDir)) {
            foreach (\Nette\Utils\Finder::findFiles('*.neon')
                    ->in($entityConfigDir) as $key => $file) {

                        $load = $this->loader->load($key);

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

    public function loadModulesConfig($currentModule = NULL)
    {
        $configFile = $this->projectDir . '/config/project.neon';
        $load = $this->loader->load($configFile);

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
