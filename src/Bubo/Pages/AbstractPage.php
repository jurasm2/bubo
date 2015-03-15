<?php

namespace Bubo\Pages;

use Bubo;
use Bubo\Application\UI\Control;

use Nette;
use Nette\Utils\Strings;
use Nette\Security\IResource;


/**
 * Abstract class representing page in CMS system
 * @author Marek Juras
 */
abstract class AbstractPage extends Control implements IResource
{

    private $context;

    private $treeNodeId;
    private $lang;


    private $entityName;
    private $entityConfigFilePath;
    private $entityConfig;

    private $isExpanded = FALSE;

    private $labels;
    /**
     * Basic data storage for page
     *
     * @var mixed
     */
    private $data;


    // loading states
    const FULL = 8;
    const CUSTOM = 4;   // in case of custom, $loadedGroups are populated
    const MANDATORY = 2;
    const BUBBLE = 1;
    const NOT_LOADED = 0;

    /**
     *
     * @var array
     */
    private $loadedGroups;

    /**
     *
     * @var int
     */
    private $loadingState;

    /* temporary */
    private $connection;

    public function __construct($context, $treeNodeId, $data = NULL, $entityName = 'page')
    {
        parent::__construct();

        $this->context = $context;
        $this->entityName = $entityName;
        $this->treeNodeId = $treeNodeId;

        $this->connection = $context->getService('database');

	    // TODO WTF??
        $this->entityConfigFilePath = CONFIG_DIR . '/pages/entities/page.neon';

        $this->data = $data;

        // init loading state
        $this->loadedGroups = array();
        $this->loadingState = self::NOT_LOADED;
        return $this;
    }


    public function __call_or_get($name, $args, $matches)
    {
        $filter = FALSE;

        $p = $this->lookup('Nette\\Application\\UI\\Presenter', FALSE);
        if ($p !== NULL) {
            $this->entityConfig = $this->presenter->configLoaderService->loadEntityConfig($this->data['entity']);
        }

        $nameWithoutPrefix = $matches[1];

        if (Strings::endsWith($nameWithoutPrefix, '_f')) {
             $nameWithoutPrefix = substr($nameWithoutPrefix, 0, strlen($nameWithoutPrefix) - 2);
             $filter = TRUE;
         }

         $retValue = NULL;

         if ($this->data !== NULL) {

             if (isset($this->data[$nameWithoutPrefix])) {
                 switch ($nameWithoutPrefix) {
                     case 'labels':
                         // when label is not assigned to page, then
                         // array(1) [0 => ""] is returned
                         // following code transforms array(1) [0 => ""] to NULL
                         $keys = array_keys($this->data[$nameWithoutPrefix]);
                         if (!(isset($keys[0]) && ($keys[0] == ""))) {
                             foreach ($keys as $key) {
                                 if ($key != '') {
                                     $retValue[] = array(
                                                     'labelId'   =>  $key,
                                                     'active'    =>  $this->data[$nameWithoutPrefix][$key]['active'] == 'yes'
                                     );
                                 }
                             }
                         }
                         break;
                     case 'url':
                         $retValue = $this->data[$nameWithoutPrefix];
                         if ($this->isHomepage()) {
                             $retValue = "";
                         }
                         break;
                     default:

                         if (isset($this->entityConfig['properties'][$nameWithoutPrefix]['engine'])) {
                             $retValue = $this->presenter->extManagerService->getExt($this, $nameWithoutPrefix, $args);
                         } else {
                             $retValue = $this->data[$nameWithoutPrefix];
                         }

                 }

             } else {
                 // $this->data[$nameWithoutPrefix]) is not set ->
                 // try to seek among extensions
                 if ($nameWithoutPrefix == 'front_url') {
                     $retValue = $this->presenter->link('Default:default', array('lang' => $this->getUrlLang(), 'url' => $this->_url));

                 } else if (Strings::startsWith($nameWithoutPrefix, 'ext_')) {
                     // request for extension
                     $retValue = $this->presenter->extManagerService->getExt($this, $nameWithoutPrefix, $args);
                 }
             }

             if ($filter) {
                 $retValue = $this->_avelancheTransform($retValue, $args);
             }


             return $retValue;

         }

    }


    public function __call($name, $args) {

        if (preg_match('#\_(.+)#', $name, $matches)) {
            $retVal = $this->__call_or_get($name, $args, $matches);
            return $retVal;
        }

        parent::__call($name, $args);
    }


    /**
     * Getter for properties prefixed with "_"
     * @param type $name
     * @return null
     */
    public function &__get($name) {
        if ($name != 'presenter') {
            //dump($name);

            if (preg_match('#\_(.+)#', $name, $matches)) {
                $retVal = $this->__call_or_get($name, NULL, $matches);
                return $retVal;
            }
        }
        return parent::__get($name);

    }


    public function setContent($content) {
        $this->data['content'] = $content;
        return $this;
    }


    public function isFullyLoaded() {
        return $this->fullyLoaded;
    }

    private function _setAsLoaded($loadingState) {
        if ($loadingState > $this->loadingState) {
            $this->loadingState = $loadingState;
        }
    }

    public function setAsFullyLoaded() {
        $this->_setAsLoaded(self::FULL);
    }

    public function setAsBubbleLoaded() {
        $this->_setAsLoaded(self::BUBBLE);
    }

    public function setAsMandatoryLoaded() {
        $this->_setAsLoaded(self::MANDATORY);
    }

    public function addLoadedGroup($groupName = NULL) {
        $this->loadedGroups[$groupName] = TRUE;
        $this->_setAsLoaded(self::CUSTOM);
        return $this;
    }

    public function getLoadingState() {
        return $this->loadingState;
    }

    public function getLoadedGroups() {
        return $this->loadedGroups;
    }

    private function _mergeData($data) {
        foreach ($data as $key => $value) {
            if (!isset($this->data[$key])) {
                $this->data[$key] = $value;
            }
        }
    }

    private function _mergeLoadedGroups($loadedGroups) {

        if (!empty($loadedGroups)) {
            $this->loadedGroups = array_merge($this->loadedGroups, $loadedGroups);
        }

    }

    /**
     * Merge data attributes between $this and $page.
     * Merging result is stored into $this and $this must be also returned
     *
     * Merging is performed only upon pages' data storage
     *
     * @param type $page
     */
    public function merge($page) {
        // merge $this <- $page

        //if ($this->loadingState < $page->loadingState) {
            $this->_mergeData($page->getData());
            $this->loadingState = $page->loadingState;
            if ($page->loadingState == self::CUSTOM) {
                $this->_mergeLoadedGroups($page->getLoadedGroups());
            }
        //}

        return $this;
    }

    public function refresh() {
        return $this->presenter->pageManagerService->refreshPage($this->treeNodeId, $this->_lang);
    }

    public function setTreeNodeId($treeNodeId) {
        $this->treeNodeId = $treeNodeId;
        return $this;
    }

    public function getTreeNodeId() {
        return $this->treeNodeId;
    }

    public function getLang($lang) {
        $this->lang = $lang();
        return $this;
    }

    public function getContext() {
        return $this->context;
    }

    public function getEntityConfig() {
        return $this->entityConfig;
    }


    public function loadEntityConfig() {
        $loader = new \Nette\Config\Loader();
        return $loader->load($this->entityConfigFilePath);
    }

    public function create($presenter, $savePageWithStatus) {

        $result = $this->modelLoader->loadModel('PageModel')->createPage($this, $presenter, $savePageWithStatus);


        $treeNodeId = NULL;
        if ($result['success'] && $result['tree_node_id'] !== NULL) {
            $treeNodeId = $result['tree_node_id'];
        }

        return $treeNodeId;
    }


    private function _getCurrentTimeZone() {
        // TODO
//        dump($this->data);

        $time = time();

//        dump(strtotime($this->_start_public), $time);
        //dump((int)( $this->_start_public ? (((strtotime($this->_start_public) > $time) * 2) - 1) : -88));
//        die();
        $start = (int)( $this->_start_public ? (((strtotime($this->_start_public) > $time) * 2) - 1) : -1);
        $stop = (int)( $this->_stop_public ? (((strtotime($this->_stop_public) > $time) * 2) - 1) : 1);

        //dump(strtotime($this->_stop_public));
//        dump($start);
//        dump($stop);
//        ROUND(
//                                                (IF ([:core:pages].[start_public] IS NULL, -1, (([:core:pages].[start_public] > NOW())* 2) -1) +
//                                                 IF ([:core:pages].[stop_public] IS NULL, 1, (([:core:pages].[stop_public] > NOW()) * 2) -1)) / 2
//                                            ) as [time_zone]

        $timeZone = ($start + $stop) / 2;
//        dump($timeZone);
//        die();
        return round($timeZone);
    }


    /**
     * Returns pageIds to be deleted according to rules specified by this table
     *
     *           P_0(t+1)  |   D_0(t+1) |   T_0(t+1)  |   P_1(t+1) |   W(t+1)
     *        |----------------------------------------------------------------|
     * P_-1(t)|   delete   |   delete   |    delete   |      x     |///////////|
     * D_-1(t)|   delete   |   delete   |    delete   |      x     |///////////|
     * P_0(t) |   delete   |      x     |    delete   |      x     |   delete! |
     * D_0(t) |   delete   |   delete   |    delete   |   delete   |///////////|
     * T_0(t) |   delete   |   delete   |/////////////|   delete   |///////////|
     * P_1(t) |      x     |      x     |   delete(?) |   delete   |///////////|
     *        |----------------------------------------------------------------|
     *
     * $timeZone and $status passed as input arguments represent column indices
     * of the table.
     *
     * Particular columns
     * ------------------
     *
     * first index:
     *  P_x - published
     *  D_x - draft
     *  T_x - trashed
     *  W_x - withdrawn
     *
     * second index:
     *  x_0 - timezone 0 (present)
     *  x_1 - timezone 1 (future)
     *
     * t is for TIME
     *
     * Flags:
     *  - delete : delete corresponding row
     *  -   x    : do nothing
     *  - ////// : not specified (cannot occur)
     *
     * @param type $timeZone
     * @param type $status
     * @param type $allPageVersions
     * @return array
     */
    private function _getPageIdsToDelete($timeZone, $status, $allPageVersions)
    {
        $P = 'published';
        $D = 'draft';
        $T = 'trashed';

        $pageIds = array();

        if (isset($allPageVersions[$this->_lang])) {

            $currentLangPageVersions = $allPageVersions[$this->_lang];

            $pairs = array();

            switch ($timeZone) {
                case 0:
                case -1:
                     switch ($status) {
                        case 'published': // 1st column
                            $pairs[] = array($P, -1);
                            $pairs[] = array($D, -1);
                            $pairs[] = array($P, 0);
                            $pairs[] = array($D, 0);
                            $pairs[] = array($T, 0);
                            break;
                        case 'draft': // 2nd column
                            $pairs[] = array($P, -1);
                            $pairs[] = array($D, -1);
                            $pairs[] = array($D, 0);
                            $pairs[] = array($T, 0);
                            break;
                        case 'trashed': // 3rd column
                            $pairs[] = array($P, -1);
                            $pairs[] = array($D, -1);
                            $pairs[] = array($P, 0);
                            $pairs[] = array($D, 0);
                            $pairs[] = array($P, 1);
                            break;
                     }
                     break;
                 case 1:
                     switch ($status) {
                        case 'published': // 4th column
                            $pairs[] = array($D, 0);
                            $pairs[] = array($T, 0);
                            $pairs[] = array($P, 1);
                            break;
                     }
            }
            $pageIds = $this->_extractPageIds($pairs, $currentLangPageVersions);
        }

        return $pageIds;
    }

    private function _extractPageIds($pairs, $currentLangPageVersions)
    {
        $pageIds = array();

        foreach ($pairs as $pair) {
            // $pair[0] represents state
            // $pair[1] represents timezone
            $timeZone = $pair[1];
            $state = $pair[0];

            if (isset($currentLangPageVersions[$timeZone][$state])) {
                foreach ($currentLangPageVersions[$timeZone][$state] as $pageId => $pageInfo) {
                    $pageIds[$pageId] = $pageInfo;
                }
            }
        }

        return $pageIds;
    }

    /**
     *
     * $allPageVersions is an array in following form
     *
     *   <lang> => array(
     *              <time_zone> => array(
     *                              <status> => array('pageId' ... ),
     *                                        .
     *                                        .
     *                              ),
     *                          .
     *                          .
     *              ).
     *          .
     *          .
     *          .
     *
     * where time_zone is:
     *  0 : for pages with present publish range,
     *  1 : for pages with future publish range,
     *  -1: for pages with past publish range
     *
     * @param type $presenter
     * @param type $allPageVersions
     */
    public function save($presenter, $allPageVersions = NULL, $allAlienUrls = NULL) {
        $entityConfig = $presenter->configLoaderService->loadEntityConfig($this->data['entity']);

        $queryBuilder = new \Model\QueryBuilders\EntityQueryBuilder($this->context);
        $insertData = $queryBuilder->getPageInserts($entityConfig, $this->getData());

        $currentTimeZone = $this->_getCurrentTimeZone();
        $pageId = NULL;

        if ($allPageVersions !== NULL) {
            // EDITING treeNodeId
            // based on $currentTimeZone and $this->_status
            $pageIdsToDelete = $this->_getPageIdsToDelete($currentTimeZone, $this->_status, $allPageVersions);

            $pageId = $presenter->pageModel->savePage($insertData);

            // post process data
            $this->_convertPdfs($insertData, $queryBuilder, $presenter);

            // fire delete signal
            $presenter->pageManagerService->onDelete($pageId, $pageIdsToDelete);


            $presenter->pageModel->removeOldPages($pageIdsToDelete);



        } else {
            // CREATING brand new treeNodeId
            $pageId = $presenter->pageModel->savePage($insertData);

            $this->_convertPdfs($insertData, $queryBuilder, $presenter);
        }

        if (isset($entityConfig['entityMeta']) && isset($entityConfig['entityMeta']['createUrl']) && $entityConfig['entityMeta']['createUrl']) {

            $urlData = array(
                            'lang_'             => $this->_lang,
                            'tree_node_id_'     => $this->treeNodeId,
                            'page_id_'          => $pageId,
                            'module_'           => $presenter->pageManagerService->getCurrentModule(),
                            'temporary'         => $this->_status == 'draft'
                            );

            // create url
            // if the page is an alien, the creation is different:
            // - for given language mutation - get all alien and create url
            //   with specified access_through data

            if ($allAlienUrls !== NULL && isset($allAlienUrls[$this->_lang])) {
                // well the page is alien
                $urls = array();

                foreach ($allAlienUrls[$this->_lang] as $_data) {
                    $_temp = $urlData;
                    $_url = $_data['parent_url']. '/' . Strings::webalize($this->_url_name);
                    if (Strings::startsWith($_url, '/')) {
                        $_url = substr($_url, 1);
                    }
                    $_temp['url'] = $_url;
                    $_temp['access_through'] = $_data['access_through'];
                    $urls[] = $_temp;
                }


                $presenter->pageModel->insertUrls($urls);
            } else {
                // page is a common page
                $_url = $this->_parent_url. '/' . Strings::webalize($this->_url_name);
                if (Strings::startsWith($_url, '/')) {
                    $_url = substr($_url, 1);
                }

                $urlData['url'] = $_url;
                $urlData['access_through'] = $presenter->pageManagerService->getCurrentModule();

                $presenter->pageModel->insertUrl($urlData);

            }

            if ($this->_status == 'trashed') {
                $presenter->pageModel->setUrlAsTrashed($this->treeNodeId);
            }
        }
//        $urlData = array(
//                        'url'               => $_url,
//                        'lang_'             => $this->_lang,
//                        'tree_node_id_'     => $this->treeNodeId,
//                        'page_id_'          => $pageId,
//                        'module_'           => $presenter->pageManagerService->getCurrentModule(),
//                        'access_through'    => $presenter->pageManagerService->getCurrentModule(),
//                        'temporary'         => $this->_status == 'draft'
//                        );


        $labelId = $presenter->getParam('labelId');

        $labelProperties = NULL;
        if ($labelId) {
            $allExtensions = $presenter->configLoaderService->loadLabelExtentsionProperties();
            $labelProperties = $presenter->pageManagerService->intersectLabelProperties($labelId, $allExtensions);
        }

        /**
         * INVALIDATE CACHE
         */
        $assignedLabels = $presenter->labelModel->getAllLabelsAssignedToPage($this->treeNodeId);
        $this->invalidatePageMenus($assignedLabels, $presenter);

        // invalidate contents
        $presenter->cacheStorageService->clean(array(
            Nette\Caching\Cache::TAGS => array('contents/'.$this->treeNodeId))
        );

    }

    protected function invalidatePageMenus($labels, $presenter = NULL) {
        $menus = array();

        // invalidate cache
        if (!empty($labels)) {
            foreach ((array) $labels as $label) {
                $menus[] = 'labels/'.$label['nicename'];
            }
        }

        $p = $presenter ?: $this->presenter;

        $p->cacheStorageService->clean(array(
            Nette\Caching\Cache::TAGS => $menus)
        );
    }


    public function attachToPresenter($presenter, $parentTreeNodeId) {
        $pageManager = $presenter->pageManagerService;

        $parent = $this->getAttachedParent($presenter, $parentTreeNodeId);
        $parent->addComponent($this, 'page'.$this->treeNodeId);
        $pageManager->indexPage($this);
    }



    public function attachToParent($parent) {
        $pageManager = $parent->presenter->pageManagerService;

        $page = NULL;

        if (!isset($parent['page'.$this->treeNodeId])) {
            $parent->addComponent($this, 'page'.$this->treeNodeId);
            $pageManager->indexPage($this);
            $page = $this;
        } else {
            $page = $parent['page'.$this->treeNodeId];
        }

        return $page;
    }

    public function setData($data) {
        $this->data = $data;
    }

    public function getData() {
        return $this->data;
    }

    public function getAttachedParent($presenter, $parentTreeNodeId)
    {
        $pageManager = $presenter->pageManagerService;

        if ($parentTreeNodeId === 0) {
            return $presenter['pages'];
        } else if ($pageManager->pageExists($parentTreeNodeId)) {
            // !! searching without ghosts and without timeZones
            $params = array('treeNodeId' => $parentTreeNodeId, 'lang' => $this->_lang);
            return $pageManager->getPage($params);
        } else {
            // parent is not connected and parent is not root
            // connected parent is needed

            $row = $presenter->pageModel->loadParent($parentTreeNodeId);
            $parent = new CMSPage($this->context, $parentTreeNodeId);
            $parent->setAsBubbleLoaded();

            $superParent = $this->getAttachedParent($presenter, $row['parent']);
            $superParent->addComponent($parent, 'page'.$parent->treeNodeId);
            $pageManager->indexPage($parent);
            return $parent;
        }

    }

	/**
	 * Basic method for loading and attaching page's descendants
	 * ---------------------------------------------------------
	 *
	 * Descendants can be loaded in 3 ways.
	 * Loading method depends on provided arguments
	 * - $entityConfig: needed for FULL or CUSTOM loading
	 * - $group: specifying CUSTOM loading
	 *
	 * ==========================================
	 * | entityConfig | groupName ||    mode    |
	 * |==============|===========||============|
	 * |     NULL     |     -     ||  mandatory |
	 * |--------------|-----------||------------|
	 * |   NOT NULL   |    NULL   ||    full    |
	 * |              |  NOT NULL ||   custom   |
	 * ==========================================
	 *
	 *
	 * @param array $params
	 * @return type
	 * @throws \Exception
	 * @internal param bool $test
	 */
    public function getDescendants($params) {
        $allParams = array(
            'labelId'               =>  NULL,
            'entityConfig'          =>  NULL,
            'groupName'             =>  NULL,
            'orderDirection'        =>  NULL,
            'limit'                 =>  NULL,
            'sortingCallback'       =>  NULL,
            'filterCallback'        =>  NULL,
            'searchGhosts'          =>  FALSE,
            'module'                =>  $this->presenter->pageManagerService->getCurrentModule()
        );

        $mergedParams = array_merge($allParams, $params);
        extract($mergedParams);

        $_pages = NULL;

        $ghostPriority = array();
        // compute ghost priority
        if ($searchGhosts) {
            $ghostPriority = $this->presenter->langManagerService->getGhostPriority($lang);
        }

        if ($lang === NULL) {
            throw new \Exception;
        }

        //$lang = $lang ?: $this->presenter->langManagerService->getDefaultLanguage();
        $loadDescendantsParams = $mergedParams;
        unset($loadDescendantsParams['searchGhosts']);
        $loadDescendantsParams['ghostPriority'] = $ghostPriority;
        $loadDescendantsParams['treeNodeId'] = $this->treeNodeId;

        $loadDescendantsParams['presenter'] = $this->presenter;
        //$loadDescendantsParams['allLangs'] = array_keys($this->presenter->langManagerService->getLangs());

//        Nette\Diagnostics\Debugger::timer('loadDescendants'); // zapne stopky
        // not fully expanded
        if ($entityConfig === NULL) {
            // EAGER or LAZY MODE?
            // descendants are loaded in mandatory mode if entityConfig if not provided
            switch ($this->presenter->pageManagerService->getPageLoadingMode()) {
                // EAGER MODE
                case 'eager':
                    $loadDescendantsParams['treeNodeId'] = NULL;
                    $loadDescendantsParams['mode'] = 'getPages';
                    $this->presenter->pageManagerService->loadAllPages($loadDescendantsParams);
                    $_pages = $this->presenter->pageManagerService->getDescendantsFromLoadedPages($this->treeNodeId, $module);
                    break;
                // LAZY MODE
                // descendants are loaded in mandatory mode
                case 'lazy':
                    $_pages = $this->presenter->pageModel->loadDescendants($loadDescendantsParams);
                    break;
            }
        } else {
            // entity configuration is provided
            // only LAZY MODE
            // descendants are loaded in custom / full mode
            $_pages = $this->presenter->pageModel->loadDescendants($loadDescendantsParams);

        }
//        $time = Nette\Diagnostics\Debugger::timer('loadDescendants'); // vypíše uplynulý čas v sekundách
//        dump('loadDescendants time[ms]: ' . $time*1000);


        /**
         * POST PROCESSING OF LOADED DESCENDANTS
         */

//        \Nette\Diagnostics\Debugger::$maxDepth = 6;
//
//        //throw new \Exception;
//        $onePage = $_pages;
//        dump($onePage);
//        die();


        // SUPER DUPER IMPORTANT FUNCTION
//        Nette\Diagnostics\Debugger::timer('attachDescendants'); // zapne stopky
        $descendants = $this->presenter->pageManagerService->attachDescendants($_pages, $this, $entityConfig, $groupName);
//        $time = Nette\Diagnostics\Debugger::timer('attachDescendants'); // vypíše uplynulý čas v sekundách
//        dump('attachDescendants time[ms]: ' . $time*1000);


       //$ps = $descendants;
        $filterCallback = isset($filterCallback) ? $filterCallback : NULL;

        // filter by label and status
        $descendants = array_filter($descendants, function($page) use ($states, $labelId) {
                                            $s = $states ? in_array($page->_status, (array) $states) : TRUE;
                                            $l = $labelId ? $page->isLabelledBy($labelId) : TRUE;
                                            return $s && $l;
        });

        // additional (custom filtering)
        $descendants = isset($filterCallback) ? array_filter($descendants, $filterCallback) : $descendants;

        //
        $desc = $this->_sort($descendants, $orderDirection, $sortingCallback);

        return $limit !== NULL ? array_slice($desc, 0, $limit) : $desc;
    }

    private function _sort(& $pages, $orderDirection, $sortingCallback) {
        $ps = array();
        if ($pages instanceof Nette\Iterators\InstanceFilter) {
            $ps = iterator_to_array($pages);
        } else {
            $ps = $pages;
        }

        if (is_callable($sortingCallback)) {
            //dump($sortingCallback);
            @usort($ps, $sortingCallback);
        } else {
            @usort($ps, function ($page1, $page2) {
                                        if ($page1->_sortorder < $page2->_sortorder) return -1;
                                        if ($page1->_sortorder > $page2->_sortorder) return 1;
                                        return 0;
            });
        }

        return $ps;
    }

    private function _getLabelPrivileges() {

        $labelPrivileges = array();

        foreach ($this->labels as $label) {
            $labelPrivileges['hideInTree'.$label->label_id] = 'Skrýt stránku se štítkem "'.$label->name.'" z levého menu';
            $labelPrivileges['denyEdit'.$label->label_id] = 'Zákaz editace stránky se štítkem "'.$label->name.'"';
        }

        return $labelPrivileges;
    }

    public function getResourceId() {
        return 'core_page';
    }

    public function getResource() {

        $privileges = array(
                            'edit'       =>  'Upravovat stránky'
                        );

        $privileges = $privileges + $this->_getLabelPrivileges();

        return array(
                    'resource'  =>  array(
                                        'name'  =>  $this->getResourceId(),
                                        'title' =>  'Stránky'
                                    ),
                    'privileges'   =>   $privileges
        );

    }



    public function assignActiveLabel($labelId) {
        return $this->_maintainLabel('assign', $labelId, 'yes');
    }

    public function assignPassiveLabel($labelId) {
        return $this->_maintainLabel('assign', $labelId, 'no');
    }

    public function removeActiveLabel($labelId) {
        return $this->_maintainLabel('remove', $labelId, 'yes');
    }

    public function removePassiveLabel($labelId) {
        return $this->_maintainLabel('remove', $labelId, 'no');
    }



    private function _maintainLabel($operation, $labelId, $active) {
        $label = $this->presenter->pageManagerService->getLabel($labelId);

        $data = array();
        foreach ($label['langs'] as $langCode => $bool) {
            $data[] = array(
                        'tree_node_id'  =>  $this->treeNodeId,
                        'label_id'      =>  $labelId,
                        'active%s'      =>  $active,
                        'lang'          =>  $langCode
            );

        }
        $returnValue = NULL;

        switch ($operation) {
            case 'assign':
                $returnValue = $this->connection->query('INSERT INTO [:core:pages_labels] %ex', $data);
                break;
            case 'remove':
                foreach ($data as $d)
                    $returnValue = $this->connection->query('DELETE FROM [:core:pages_labels] WHERE %and', $d);
                break;
        }

        $labels = array($label);
        $this->invalidatePageMenus($labels);
        return $returnValue;
    }

    public function isPassivelyLabelledBy($labelId) {
        return $this->_isLabelledBy($labelId, FALSE);
    }

    public function isActivelyLabelledBy($labelId) {
        return $this->_isLabelledBy($labelId, TRUE);
    }

    public function isLabelledBy($labelId) {
        return $this->_isLabelledBy($labelId, NULL);
    }

    private function _isLabelledBy($labelId, $active) {
        if ($this->_labels) {
            foreach ($this->_labels as $labelData) {
                if ($labelData['labelId'] == $labelId) {

                    if (($active === TRUE) && ($labelData['active'] === TRUE)) {
                        return TRUE;
                    } else if (($active === FALSE) && ($labelData['active'] === FALSE)) {
                        return TRUE;
                    } else if ($active === NULL) {
                        return TRUE;
                    }
                }
            }
        }
        return FALSE;
    }

    public function avelanche($text, $params = NULL) {
        return $this->_avelancheTransform($text, NULL, $params);
    }

    private function _avelancheTransform($text, $filterParams = NULL, $templateParams = NULL)
    {
        $_temp = $text;
        $hash = NULL;
        $oldHash = NULL;
        for ($i = 1; $i <= 10; $i++) {
	        $template = new Bubo\Templating\Template;
            $template->setTranslator($this->presenter->context->getService('translation.default'));
            $template->setSource($_temp);
            $template->add('_presenter', $this->presenter);
            $template->add('_control', $this->presenter);
            $template->add('_page', $this);

            if ($templateParams !== NULL) {
                foreach ($templateParams as $k => $v) {
                    $template->add($k, $v);
                }
            }

            // register CMS filter for expanding galleries and images into latte blocks
            $template->onPrepareFilters[] = function($template) use ($filterParams) {
                    $template->registerFilter(new Bubo\Filters\CMSFilter($filterParams));
            };

	        // register latte engine to finish the job
            $template->onPrepareFilters[] = function($template) {
                    $template->registerFilter(new Nette\Latte\Engine);
            };

            $_temp = $template->__toString();

            if (($hash = sha1($_temp)) == $oldHash) break;
            $oldHash = $hash;
        }

        return $_temp;
    }

    public function getFilteredContent() {
        $this->setContent($this->_avelancheTransform($this->_content));
        return $this;
    }

    public function getCacheID($prefix) {
        return Strings::webalize($prefix . '-' . $this->_tree_node_id . '-' . $this->_lang . '-' . $this->_module);
    }

    public function getPageCacheID($prefix) {
        return Strings::webalize($prefix . '-' . $this->_tree_node_id . '-' . $this->_lang . '-' . $this->_module);
    }

    public function getModuleCacheID($prefix) {
        return Strings::webalize($prefix . '-' . $this->_lang . '-' . $this->_module);
    }


    /**
     * Return page url without first slash
     * @return type
     */
    public function getUrl() {
        return Strings::startsWith($this->_url, '/') ? substr($this->_url, 1) : $this->_url;
    }

    public function getUrlLang() {
        return $this->_lang != $this->presenter->langManagerService->getDefaultLanguage() ? $this->_lang : NULL;
    }

    public function setLabels($labels) {
        $this->data['labels'] = $labels;
    }

    public function isHomepage() {
        $labels = $this->_labels;
        $homepageLabelId = $this->presenter->pageManagerService->getHomepageLabelId();
        $isHomepage = FALSE;

        if (!empty($labels) && !empty($homepageLabelId)) {

            foreach ($labels as $label) {
                if ($label['labelId'] == $homepageLabelId && $label['active'] == TRUE) {
                    $isHomepage = TRUE;
                    break;
                }
            }

        }

        return $isHomepage;

    }

    public function getLayout($layout) {
        return $this->presenter->projectManagerService->getLayout($layout);
    }

    public function getDoctype($doctype) {
        return $this->presenter->projectManagerService->getDoctype($doctype);
    }

    public function createsUrl() {

        $entityConfig = $this->presenter->configLoaderService->loadEntityConfig($this->_entity);


        if (isset($entityConfig['entityMeta']['createUrl'])) {
            return $entityConfig['entityMeta']['createUrl'];
        }

        return TRUE;
    }


    public function getBreadcrumbs() {
        $breadcrumbs = array();

        $getPageParams = array(
                            'states' => array('published'),
                            'lang'   => $this->_lang
        );


        $currentBreadcrumb = $this;


        if ($currentBreadcrumb->_parent == 0) {
            $breadcrumbs[] = $currentBreadcrumb;
        } else {

            for ($i = 0; $i <= 10; $i++) {
                if ($currentBreadcrumb->_parent > 0) {
                    $getPageParams['treeNodeId'] = $currentBreadcrumb->_parent;

                    $breadcrumbs[] = $currentBreadcrumb;
                    $currentBreadcrumb = $this->presenter->pageManagerService->getPage($getPageParams);

                } else {
                    $breadcrumbs[] = $currentBreadcrumb;
                    break;
                }
            }

        }

        return array_reverse($breadcrumbs);

    }

}
