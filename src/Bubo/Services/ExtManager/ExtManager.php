<?php

namespace Bubo\Services;

use Bubo\Application\UI\Presenter;
use Bubo\Pages\AbstractPage;

use Nette\DI\Container;
use Nette\Utils\Strings;

/**
 * Ext Manager
 */
class ExtManager extends BaseService {

    /**
     *
     * @var Container
     */
    private $context;

    /**
     *
     * @var Presenter
     */
    private $presenter;

    /**
     * Constructor
     * @param Container $context
     */
    public function __construct(Container $context)
    {
        $this->context = $context;
    }

    /**
     *
     * @param Presenter $presenter
     */
    public function setPresenter(Presenter $presenter)
    {
        $this->presenter = $presenter;
    }

    /**
     *
     * @return bool
     */
    public function isPresenterSet()
    {
        return $this->presenter !== NULL;
    }

    /**
     *
     * @param string $nameWithoutPrefix
     * @return string
     */
    private function _getRealName($nameWithoutPrefix)
    {
        return Strings::startsWith($nameWithoutPrefix, 'ext_') ? Strings::substring($nameWithoutPrefix, 4) : $nameWithoutPrefix;
    }

    /**
     * Return value from any kind of extension (label of entity) using correct type of ExtEngine
     * @param AbstractPage $page
     * @param bool $nameWithoutPrefix
     * @param array|NULL $args
     * @return string
     * @throws \Nette\InvalidStateException
     */
    public function getExt(AbstractPage $page, $nameWithoutPrefix, $args = NULL)
    {
        $retValue = NULL;

        // label extensions
        $labelExtensions = $this->presenter->pageManagerService->getAllLabelExtensions();
        // label data types
        $labelExtensionsProperties = $this->presenter->configLoaderService->loadLabelExtentsionProperties();
        $realName = $this->_getRealName($nameWithoutPrefix);
        $isEntityParam = FALSE;

        $name = NULL;
        $entity = $page->_entity;
        if ($entity) {
            $entityConfig = $this->presenter->configLoaderService->loadEntityConfig($entity);

            if (isset($entityConfig['properties'][$realName])) {
                //$realName = $entityConfig['properties'][$realName]['extName'];
                $name = $entityConfig['properties'][$realName]['extName'];
                $isEntityParam = TRUE;
            }
        }

        if (isset($labelExtensions[$realName]) || $name !== NULL) {
            // extension exists
            // what type is it?? get is by name
            $name = $name ?: $labelExtensions[$realName]['name'];
            if (isset($labelExtensionsProperties['properties'][$name])) {
                // identifier exists
                $extensionConfig = $labelExtensionsProperties['properties'][$name];
                $extEngineName = isset($extensionConfig['engine']) ? $extensionConfig['engine'] : 'default';
                $engineClassName = 'Bubo\\ExtEngines\\' . ucfirst($extEngineName) . 'ExtEngine';

                if (class_exists($engineClassName)) {
                    $reflect  = new \ReflectionClass($engineClassName);
                    $engine = $reflect->newInstanceArgs(array('page' => $page));
                    $retValue = $engine->getExt($realName, $extensionConfig, $args, $isEntityParam);
                } else {
                    throw new \Nette\InvalidStateException('Class '.$engineClassName.' not found');
                }
            }
        }
        return $retValue;
    }

}
