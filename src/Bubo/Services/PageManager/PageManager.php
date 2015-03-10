<?php

namespace Bubo\Services;

use Nette, Bubo;


/**
 * Page manager
 *
 * V cms systému je zaregistrován jako služba.
 *
 *
 *
 * @author Marek Juras
 */
final class PageManager extends BaseService {

    private $context;

    private $presenter = NULL;

    /* Indexers */
    private $treeNodeIdIndex;
    private $labelIndex;
    private $labelNameIndex;
    private $labelExtensionIndex;

    private $homepageLabelId;

    private $allPages;
    private $loaded;

    private $pageLoadingMode;

    /** @var array */
    public $onDelete;

    public function __construct($context, $pageLoadingMode) {
        $this->context = $context;

        $this->pageLoadingMode = $pageLoadingMode;

        $this->loaded = FALSE;

    }


    // load all pages from all WEB modules
    public function loadAllPages($params) {
        $params['presenter'] = $this->presenter;
        if (!$this->loaded && $this->getPageLoadingMode() == 'eager') {
            $modulesConfig = $this->presenter->configLoaderService->loadModulesConfig($this->getCurrentModule());

            if (isset($modulesConfig['modules'])) {
                foreach ($modulesConfig['modules'] as $moduleName => $module) {
                    $params['module'] = $moduleName;
                    $this->allPages[$moduleName] = $this->presenter->pageModel->loadAllPages($params);
                }
            }

            $this->loaded = TRUE;
        }
//        \Nette\Diagnostics\Debugger::$maxDepth = 7;
//        dump($params);
//        die();
    }

    public function getLabel($labelId) {
        $this->getAllLabels();
//        dump($this->labelIndex[$labelId]);
//        die();
        return $this->labelIndex[$labelId];
    }

    public function getAllLabelExtensions() {
        if ($this->labelExtensionIndex === NULL) {
            $this->labelExtensionIndex = $this->presenter->pageModel->loadAllLabelExtensions();
        }

        return $this->labelExtensionIndex;
    }

    public function getLabelByName($labelName) {
        $this->getAllLabels();

        foreach ($this->labelIndex as $labelId => $label) {
            if ($label['name'] == $labelName) {
                return $label;
            }
        }

        return NULL;

    }

    public function isPresenterSet() {
        return $this->presenter !== NULL;
    }

    public function setPresenter($presenter) {
        $this->presenter = $presenter;
    }

    public function getPresenter() {
        return $this->presenter;
    }

    public function getAllLabels($lang = NULL) {
        if ($this->labelIndex === NULL) {
            $this->labelIndex = $this->presenter->labelModel->getAllLabels($this->getCurrentModule());
            if (!empty($this->labelIndex)) {
                foreach ($this->labelIndex as $labelId => $label) {
                    if ($label['name'] == 'Homepage') {
                        $this->homepageLabelId = $labelId;
                        break;
                    }
                }
            }

        }

        if ($lang !== NULL) {
            // find only allowed labels
            $allowedLabels = array();
            foreach ($this->labelIndex as $labelId => $label) {
                if (array_key_exists($lang, $label['langs'])) {
                    $allowedLabels[$labelId] = $label;
                }
            }

            return $allowedLabels;
        }

        return $this->labelIndex;
    }

    public function getDescendantsFromLoadedPages($parent, $module = NULL) {
        if ($module === NULL) $module = $this->getCurrentModule();

        $pages = isset($this->allPages[$module][$parent]) ? $this->allPages[$module][$parent] : array();

        return $pages;




//        throw new \Exception;

//        dump($descendants);
//        die();

//        return $descendants;
    }


    public function getPageLoadingMode() {
        $moduleName = strtolower($this->presenter->moduleName);
        return array_key_exists($moduleName, $this->pageLoadingMode) ? $this->pageLoadingMode[$moduleName] : 'lazy';
    }


    public function pageExists($treeNodeId) {
        return isset($this->treeNodeIdIndex[$treeNodeId]);
    }


    /**
     * Create pages with IDs in $ids and attach them to parent
     *
     *
     * @param type $ids
     * @param type $lang
     * @param type $acceptedState
     * @param type $label
     * @param type $entity
     * @param type $group
     */
    public function attachDescendants($ids, $parent, $entityConfig = NULL, $groupName = NULL) {
        $pages = array();

        foreach($ids as $treeNodeId => $data) {
            $page = new Bubo\Pages\CMSPage($this->context, $treeNodeId, $data);
            if ($entityConfig === NULL) {
                $page->setAsMandatoryLoaded();
            } else {
                if ($groupName === NULL) {
                    $page->setAsFullyLoaded();
                } else {
                    $page->addLoadedGroup($groupName);
                }
            }
            $attachedPage = $page->attachToParent($parent);
            $pages[$treeNodeId] = $attachedPage->merge($page);
        }

        return $pages;

    }


    public function getTreeNodeIdIndex() {
        return $this->treeNodeIdIndex;
    }

    public function getLabelRoots($labelId, $lang, $states = 'published', $entityConfig = NULL) {

        $params = array(
                        'treeNodeId'            =>  NULL,
                        'lang'                  =>  $lang,
                        'states'                =>  'published',
                        'labelId'               =>  $labelId,
                        'entityConfig'          =>  $entityConfig,
                        'groupName'             =>  NULL,
                        'orderDirection'        =>  NULL,
                        'limit'                 =>  NULL,
                        'searchGhosts'          =>  FALSE,
                        'searchAllTimeZones'    =>  FALSE,
                        'mode'                  =>  'getLabelRoots',
                        'module'                =>  $this->getCurrentModule()
        );

        extract($params);
        $params['presenter'] = $this->presenter;


        $_roots = $this->presenter->pageModel->loadLabelRoots($params);


        $label = $this->getLabel($labelId);
        $pageSorting = $label['page_order'];

        $pageSet = array();

        if ($_roots) {

            $params['mode'] = 'getPages';
            $params['treeNodeId'] = array_keys($_roots);

            //$params['labelId'] = 'getPages';
            $roots = $this->presenter->pageModel->loadDescendants($params);

//            dump(array_keys($roots));
//            die();

//            dump($roots);
//            die();
            foreach ($roots as $root) {
                $page = new Bubo\Pages\CMSPage($this->context, $root['tree_node_id'], $root, $root['entity']);

                $page->setAsFullyLoaded();

                // this is WRONG!!!
                // cannot

                if (isset($this->treeNodeIdIndex[$root['tree_node_id']])) {
                    $page = $this->treeNodeIdIndex[$root['tree_node_id']]->merge($page);
                } else {
                    $page->attachToPresenter($this->presenter, $root['parent']);
                }
                $pageSet[$page->treeNodeId] = $page;

            }

        }


        $rootSorting = NULL;
        if (isset($pageSorting[0]) && is_array($pageSorting[0])) {
            $rootSorting = $pageSorting[0];
        }

        if ($rootSorting !== NULL) {
            uksort($pageSet, function ($a, $b) use ($rootSorting) {
                    $aIndex = array_search($a, $rootSorting);
                    $bIndex = array_search($b, $rootSorting);

                    if ($aIndex !== FALSE && $bIndex !== FALSE) {
                        return $aIndex - $bIndex;
                    } else if ($aIndex !== FALSE) {
                        return -1;
                    } else {
                        return 0;
                    }

            });
        }


        return $pageSet;

    }



    /**
     * Get page
     * --------
     *
     * Always returns fully loaded page.
     *
     * If page exists (is indexed in $treeNodeIdIndex) AND IS FULLY LOADED
     * then just return indexed page
     * Else
     *  - create page
     *  - connect it to presenter via its parents (bubble up)
     *
     * @param type $treeNodeId
     * @param type $langs
     */
    public function getPage($params) {
        $allParams = array(
                        'treeNodeId'            =>  NULL,
                        'lang'                  =>  NULL,
                        'states'                =>  NULL,
                        'labelId'               =>  NULL,
                        'entityConfig'          =>  NULL,   // is loaded here
                        'groupName'             =>  NULL,
                        'orderDirection'        =>  NULL,
                        'limit'                 =>  NULL,
                        'searchGhosts'          =>  FALSE,
                        'searchAllTimeZones'    =>  FALSE,
                        'mode'                  =>  'getPage',
                        'module'                =>  $this->getCurrentModule()
        );


        $mergedParams = array_merge($allParams, $params);

        //dump($mergedParams);

        extract($mergedParams);

//        dump($mergedParams);
//        die();

        // if EAGER mode enabled -> load all pages
        switch ($this->presenter->pageManagerService->getPageLoadingMode()) {
            // EAGER MODE
            case 'eager':
                // load all pages
                $loadAllPageParams = $mergedParams;
                $loadAllPageParams['treeNodeId'] = NULL;
                $loadAllPageParams['mode'] = 'getPages';
                $loadAllPageParams['searchGhosts'] = TRUE;
                //$loadAllPageParams['allLangs'] = array_keys($this->presenter->langManagerService->getLangs());


                // compute ghost priority
                $loadAllPageParams['ghostPriority'] = $this->presenter->langManagerService->getGhostPriority($lang);


                $this->loadAllPages($loadAllPageParams);
                break;
        }


        if (isset($this->treeNodeIdIndex[$treeNodeId]) && $this->treeNodeIdIndex[$treeNodeId]->getLoadingState() == Bubo\Pages\AbstractPage::FULL) {
//            dump('stranka je plne nactena');
//            die();
            return $this->treeNodeIdIndex[$treeNodeId];
        } else {

            $entity = $this->presenter->pageModel->getEntity($treeNodeId);
            if (!$entity) {
//                dump($entity);
//                die();
                dump($treeNodeId);
                throw new \Exception;
            }
            $entityConfig = $this->presenter->configLoaderService->loadEntityConfig($entity);

            $mergedParams['entityConfig'] = $entityConfig;
            $mergedParams['presenter'] = $this->presenter;
            //$mergedParams['allLangs'] = array_keys($this->presenter->langManagerService->getLangs());

//            throw new \Exception;
//            dump($mergedParams);
//            die();
            // select full configuration
//            dump($mergedParams);


            $row = $this->presenter->pageModel->loadPage($mergedParams);

            $page = NULL;

            if ($row) {
                $page = new Bubo\Pages\CMSPage($this->context, $treeNodeId, $row, $entity);
                $page->setAsFullyLoaded();

                if (isset($this->treeNodeIdIndex[$treeNodeId])) {
                    $page = $this->treeNodeIdIndex[$treeNodeId]->merge($page);
                } else {
                    $page->attachToPresenter($this->presenter, $row['parent']);
                }
            }



            return $page;
        }
    }


    public function refreshPage($treeNodeId, $lang) {

        $params = array(
                    'treeNodeId'            => $treeNodeId,
                    'lang'                  => $lang,
                    'searchAllTimeZones'    => TRUE
        );

        $page = $this->getPage($params);

        //$params['allLangs'] = array_keys($this->presenter->langManagerService->getLangs());
        $entityConfig = $this->presenter->configLoaderService->loadEntityConfig('page');
        $params['entityConfig'] = $entityConfig;
        $params['groupName'] = NULL;
        $params['mode'] = 'getPage';
        $params['module'] = $this->getCurrentModule();
        $params['presenter'] = $this->presenter;

        $row = $this->presenter->pageModel->loadPage($params);

        if (!isset($row['entity'])) {
            dump($params);
            die();
        }

        if (!$row['entity']) {

            throw new \Nette\InvalidStateException('Page with treeNodeId = '.$treeNodeId.' does not exist.');
        }

        $page->setData($row);

        return $page;

    }



    public function indexPage($page) {
        $this->treeNodeIdIndex[$page->treeNodeId] = $page;
    }



    /**
     * Get defaults of page in all languages
     *
     * @param type $treeNodeId
     */
    public function getDefaults($treeNodeId, $waiting = FALSE, $alienLangs = array()) {

        $entity = $this->presenter->pageModel->getEntity($treeNodeId);

//        dump($entity);
//        die();

        $entityConfig = $this->presenter->configLoaderService->loadEntityConfig($entity);

        $allLangs = array_keys($this->presenter->langManagerService->getLangs());

        foreach ($alienLangs as $l) {
            $allLangs[] = $l;
        }

        //dump($alienLangs);

//        if (!empty($alienLangs)) {
//            dump($alienLangs);
//            dump($allLangs);
//            die();
//        }

        $params = array(
                    'searchAllTimeZones'    => $waiting ? array(1, 0 , -1) : TRUE,
                    'searchGhosts'          => FALSE,
                    'entityConfig'          => $entityConfig,
                    'treeNodeId'            => $treeNodeId,
                    'groupName'             =>  NULL,
                    'allLangs'              => $allLangs,
                    'mode'                  => 'getDefaults',
                    'module'                => $this->getCurrentModule()
        );

        $pages = $this->presenter->pageModel->loadPageMultiLang($params);

        //collect pageIds
        if (!$pages) {
            throw new \Nette\InvalidStateException('Multilang pages with treeNodeId = '.$treeNodeId.' were not found');
        }


        return $pages;

    }



    public function getSelectData($langCode, $treeNodeId = NULL) {
        // get lang root
        $langRoot = $this->getLangRoot($langCode);
        return $this->_getSelectDataRecursive(array(), $langRoot, $treeNodeId);
    }


    private function _getSelectDataRecursive($selectData, $page, $treeNodeId) {

        if ($page->getProperty('tree_node_id') != $treeNodeId)


            $option = 'Nejvyšší úroveň';
            if ($page->getLevel() > 0) {
                $option = str_repeat('-', $page->getLevel()-1).' '.$page->getProperty('name');
                if (strlen($option) > 20) {
                    $option = substr($option, 0, 20) . '...';
                }
            }

            $selectData[$page->getProperty('tree_node_id')] = $option;

        $descendants = $page->getDescendants();
        if (count($descendants) > 0 && $page->getProperty('tree_node_id') != $treeNodeId) {

            foreach ($descendants as $descendant) {
                $selectData = $this->_getSelectDataRecursive($selectData, $descendant, $treeNodeId);
            }

        }

        return $selectData;

    }




    /**
     * Setřídí 1D pole $unsorted podle receptu, který poskytne
     * štítek.
     *
     * Třídění pracuje na principu zachování všech položek z $unsorted.
     *
     * $parent
     *  - buď 0 -> nejvyšší úroveň
     *  - jinak -> tree_node_id parenta (pokud je potřeba ld místo tree_node_id,
     *                                   tak si ho lze vyrobit ... opačně to nelze)
     *
     *
     * @param type $unsorted
     * @param type $label
     * @param type $parent
     * @return type
     *
     */

    public function applyLabelSorting($unsorted, $label, $parent = 0) {

        $pageOrder = NULL;
        if (@unserialize($label->page_order)) {
            $pageOrder = unserialize($label->page_order);
        }

        $sortedPages = array();

        if ($pageOrder !== NULL) {

            $parentIndex = 0;
            if ($parent > 0) {
                // pokud se jedna o globalni stitek, tak preved $parent (z tree_node_id -> lg)
                $parentIndex = $label->is_global ? $this->treeNodeIdIndex[$parent]->getProperty('lg') : $parent;
            }
            if (isset($pageOrder[$parentIndex])) {
                foreach ($pageOrder[$parentIndex] as $pageIndex) {

                    $index = array_search($pageIndex, $unsorted);

                    if ($index !== FALSE) {
                        $sortedPages[] = $pageIndex;
                        unset($unsorted[$index]);
                    }

                }
            }
        }

        if (!empty($unsorted)) {
            foreach ($unsorted as $pageIndex) {

                $sortedPages[] = $pageIndex;
            }
        }

        return $sortedPages;
        //dump($sortedPages);
    }


    /**
     * Returns page's url
     * ------------------
     *
     * Try to get page's url from pageIndex by $treeNodeId.
     * If $treeNodeId is NULL -> create url by glueing parent's url and urlChunk
     *
     * @param type $treeNodeId
     * @param type $parentTreeNodeId
     * @param type $urlChunk
     */
    public function getPageUrl($treeNodeId, $parentTreeNodeId = NULL, $urlChunk = NULL) {

        $url = '';

        if ($treeNodeId !== NULL && isset($this->treeNodeIdIndex[$treeNodeId])) {
            $url = $this->treeNodeIdIndex[$treeNodeId]->getUrl();
        } else {
            if ($parentTreeNodeId !== NULL && isset($this->treeNodeIdIndex[$parentTreeNodeId])) {
                $url = $this->treeNodeIdIndex[$parentTreeNodeId] . $urlChunk;
            }
        }

        return $url;
    }



    public function getHomepageTreeNodeId($lang) {

        // find label named as "Homepage"

        $labels = $this->getAllLabels();

//        dump($labels);
//        die();

        $homepageTreeNodeId = NULL;
        $homepageLabelId = NULL;

        if (!empty($labels)) {
            foreach ($labels as $labelId => $label) {
                if ($label['name'] == 'Homepage') {
                    $homepageLabelId = $labelId;
                    break;
                }
            }
        }

        if ($homepageLabelId !== NULL) {
            $homepageTreeNodeId = $this->presenter->labelModel->getLabelRootTreeNodeId($homepageLabelId, $lang);
        }


        return $homepageTreeNodeId;


    }


    public function loadSortedLabelExtensions($labelId, $entity = NULL) {

        $label = $this->presenter->pageManagerService->getLabel($labelId);


        if ($entity !== NULL) {
            $entityConfig = $this->presenter->configLoaderService->loadEntityConfig($entity);
            if (isset($entityConfig['properties']) && $entityConfig['properties']) {

                $properties = $this->presenter->extModel->filterEntityProperties($entityConfig['properties'], $labelId);

                foreach ($properties as $propertyName => $property) {
                    $items[str_replace('_','*',$propertyName)] = $property['label'].'*';
                }
            }
        }

        if (isset($label['extensions'])) {
            foreach ($label['extensions'] as $extId => $labelExtension) {
                $items[str_replace('_','*','ext_'.$labelExtension['ext_identifier'])] = $labelExtension['ext_title'];

            }
        }

        $extSortOrder = $this->presenter->extModel->loadExtSorting($labelId);
        if ($extSortOrder) {
            $extSorting = NULL;
            if ($extSorting = \Bubo\Utils\MultiValues::unserialize($extSortOrder)) {
//                die();

                uksort($items, function($a, $b) use($extSorting) {

                                    $aPos = array_search($a, $extSorting);
                                    $bPos = array_search($b, $extSorting);

                                    if (($aPos !== FALSE) && ($bPos !== FALSE)) {
                                        return $aPos - $bPos;
                                    } else if ($aPos === FALSE) {
                                        return -1;
                                    } else {
                                        return 1;
                                    }

                                });

            }




        }

        return $items;
    }

    /**
     * Take all the extensions from all of the labels
     * @param type $labelId
     * @param type $allExtensions
     * @return type
     */
    public function intersectLabelProperties($labelId, $allExtensions) {

        $intersectedProperties = array();
        $label = $this->getLabel($labelId);

//        dump($allExtensions);

//        dump($label['extensions']);
//        die();

        foreach ($label['extensions'] as $extId => $extension) {

            if (array_key_exists($extension['ext_name'], $allExtensions['properties'])) {
                if ($this->presenter->getAction() == 'default'
                        && isset($allExtensions['properties'][$extension['ext_name']]['storage'])
                            &&($allExtensions['properties'][$extension['ext_name']]['storage'] == 'gallery')
                        ) {                            continue; } else {


                $identifier = 'ext_'.$extension['ext_identifier'];
                $intersectedProperties[$identifier] = $allExtensions['properties'][$extension['ext_name']];
                $intersectedProperties[$identifier]['label'] = $extension['ext_title'];
                        }
            }
        }




//        dump($intersectedProperties);
//        die();
        return $intersectedProperties;

//        dump($label, $allExtensions);
//        die();

    }


    /**
     * Get current module
     *
     * In admin use session variable to switch between modules.
     * Otherwise -> use url host as module detector
     *
     * @return type
     * @throws Nette\Application\BadRequestException
     */
    public function getCurrentModule() {

        $retValue = NULL;

        $url = $this->presenter->httpRequestService->getUrl();
        $host = $url->host;
        $modules = $this->presenter->configLoaderService->loadModulesConfig();


        foreach ($modules['modules'] as $moduleName => $moduleData) {
            $res = preg_grep("#.*$host.*#", $moduleData['hosts']);

            //if (isset())
            if (!empty($res)) {
                $retValue = $moduleName;
            }
        }

        //dump($retValue);
        //die();

        if ($retValue === NULL) {
            throw new Nette\Application\BadRequestException('CMS module was not found');
        }


        if ($this->presenter->moduleName == 'Admin') {
            // use session
            // if session is not created
            //echo "já jsem admin, a session neni zalozena, tak ji zalozim jako: ".  $retValue;
            $m = $this->presenter['moduleSwitch']->getActualModule();


            $retValue  = $m ?: $retValue;
            //dump($this->presenter['moduleSwitch']);
            //die();

        }


        return $retValue;
    }

    public function getHomepageLabelId() {
        return $this->homepageLabelId;
    }


    // TODO
    public function isAllowedModule($module) {
        return TRUE;
    }



    public function getAllPageEntities() {
        return $this->presenter->configLoaderService->loadEntities();
    }

    public function getAllScrapEntities() {
        return $this->presenter->configLoaderService->loadEntities(FALSE);
    }

    public function getExtPropertiesByIdentifier($identifier) {
        $ret = NULL;

        $extProperties = $this->presenter->configLoaderService->loadLabelExtentsionProperties();
        $ext = $this->getAllLabelExtensions();

        if (isset($ext[$identifier])) {
            $extName = $ext[$identifier]['name'];

            $ret = $extProperties['properties'][$extName];

        }

        return $ret;

    }

}