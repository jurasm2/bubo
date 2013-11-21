<?php

namespace Bubo\Traversing;

use Nette;

/**
 * Rendering Traverser
 *
 * @author Marek Juras
 */
abstract class RenderingTraverser extends Traverser {

    private $renderer;

    private $currentPageTreeNodeId = NULL;
    private $highlightedTreeNodeIds = array();

    private $lang;

    private $menu;

    private $isHighlighted = FALSE;

    private $specifiedRoot;

    /**
     * Limit of pages in horizontal level
     *
     * default value: NULL - unlimited
     * usage: $this->setLimit(3);
     *
     * @var type
     */
    private $limit = NULL;


    /**
     * Select the subject of ordering
     *
     * is set indirectly by methods
     * $this->orderByRand()
     * $this->orderByDate($direction)
     *
     * default value: NULL - default ordering
     * usage: $this->orderByDate('desc');
     * @var type
     */
    private $orderBy = NULL;

    /**
     * Order of direction (only for date)
     * desc or asc
     *
     * default: desc
     * usage: $this->orderByDate('asc');
     * @var type
     */
    private $orderDirection = 'desc';


    private $goThroughActive = FALSE;

    private $skipLevel = NULL;


    private $sortingCallback;
    private $filterCallback;


    public function setRenderer($renderer) {
        $this->renderer = $renderer;
        return $this;
    }

    public function setLang($lang) {
        $this->lang = $lang;
        return $this;
    }

    public function isHighlighted() {
        return $this->isHighlighted;
    }


    public function getLang() {
        return $this->lang;
    }


    public function getOrderDirection() {
        return $this->orderDirection;
    }

    public function limit($limit) {
        $this->limit = (int) $limit;
        return $this;
    }

    public function getLimit() {
        return $this->limit;
    }

    public function skipFirst() {
        $this->_skipLevel(1);
        return $this;
    }

    private function _skipLevel($skipLevel) {
        $this->skipLevel = (int) $skipLevel;
    }

    public function getSkipLevel() {
        return $this->skipLevel;
    }


    public function setSortingCallback($callback) {
        $this->sortingCallback = $callback;
        return $this;
    }

    public function getSortingCallback() {
        return $this->sortingCallback;
    }

    public function setFilterCallback($callback) {
        $this->filterCallback = $callback;
        return $this;
    }

    public function getFilterCallback() {
        return $this->filterCallback;
    }

    public function setGoThroughActive() {
        $this->goThroughActive = TRUE;
        return $this;
    }

    public function getGoThroughActive() {
        return $this->goThroughActive;
    }

    public function orderByDate($direction = 'desc') {
        $this->orderDirection = $direction;
        $this->orderBy = 'date';
        return $this;
    }

    public function orderByRand() {
        $this->orderBy = 'rand';
        return $this;
    }

    public function getOrderBy() {
        return $this->orderBy;
    }


    public function highlight($highlightWholePath = FALSE) {

        $this->isHighlighted = TRUE;

        $this->currentPageTreeNodeId = NULL;
        if (isset($this->menu->presenter->currentTreeNodeId)) {
            $this->currentPageTreeNodeId = $this->menu->presenter->currentTreeNodeId;
        } else if (isset($this->menu->presenter->treeNodeId)) {
            $this->currentPageTreeNodeId = $this->menu->presenter->treeNodeId;
        }

        if (isset($this->menu->presenter->isLink) && $this->menu->presenter->isLink) {
            if ($this->menu->presenter->linkTreeNodeId !== NULL) {
                $this->currentPageTreeNodeId = $this->menu->presenter->linkTreeNodeId;
            }
        }

        $treeNodeIdIndex = $this->menu->presenter->pageManagerService->treeNodeIdIndex;

        if ($this->currentPageTreeNodeId !== NULL) {

            $p = $this->currentPageTreeNodeId;

            if ($highlightWholePath == FALSE) {
                $this->highlightedTreeNodeIds[] = $p;
            } else {

                for ($i = 1; $i < 20; $i++) {
                    $this->highlightedTreeNodeIds[] = $p;
                    $_page = $treeNodeIdIndex[$p];
                    $_parent = $_page->parent;
                    if ($_parent->name == 'pages') break;
                    $p = $_parent->treeNodeId;
                }
            }
        }
        return $this;
    }

    public function isPageHightlighted($treeNodeId) {
        return in_array($treeNodeId, $this->highlightedTreeNodeIds);
    }

    public function getRenderer() {
        return $this->renderer;
    }


    public function setMenu($menu) {
        $this->menu = $menu;
    }

    public function getMenu() {
        return $this->menu;
    }

    public function setEntity($entityName, $groupName = NULL) {
        $entityConfig = $this->menu->presenter->configLoaderService->loadEntityConfig($entityName);
        $this->configureLoading($entityConfig, $groupName);
        return $this;
    }

    /**
     * Traverse the page tree.
     *
     * Use $node as root.
     */
    public function recursiveDFSRender($node, $getDescendantsParams, $renderer, $currentContainer, $level, $horizontalLevel) {

        // create new menu item
        $menuItemContainer = $level == 1 ? $renderer->createTopLevelItemContainer() : $renderer->createInnerLevelItemContainer();

        $menuItemContainer = $renderer->renderMenuItem($node, $getDescendantsParams, $menuItemContainer, $level, $horizontalLevel, $this->isPageHightlighted($node->treeNodeId));
        $currentContainer->add($menuItemContainer);

        if (!$this->getGoThroughActive()) {

            $descendants = $node->getDescendants($getDescendantsParams);
            if (count($descendants) > 0) {

                $newMenuContainer = $renderer->createInnerLevelContainer();
                $furtherLevel = $level + 1;
                $_horizontalLevel = 1;
                foreach ($descendants as $descendant) {
                    $newMenuContainer = $this->recursiveDFSRender($descendant, $getDescendantsParams, $renderer, $newMenuContainer, $furtherLevel, $_horizontalLevel++);
                }
                $menuItemContainer->add($newMenuContainer);

            }
        }
        return $currentContainer;
    }



    public function BFSRender($node, $getDescendantsParams, $renderer, $currentContainer, $level, $horizontalLevel) {

        $headingContainer = NULL;

        if (!$currentContainer->offsetExists(0)) {
            $headingContainer = $renderer->createTopLevelContainer();
            $currentContainer->add($headingContainer);
        } else {
            $headingContainer = $currentContainer->offsetGet(0);
        }


        $contentContainer = NULL;
        if (!$currentContainer->offsetExists(1)) {
            $contentContainer = \Nette\Utils\Html::el('div');
            $contentContainer->class[] = 'content';
            $currentContainer->add($contentContainer);
        } else {
            $contentContainer = $currentContainer->offsetGet(1);
        }

        $subHeadingContainer = $renderer->createTopLevelItemContainer();

        // add tab name to $headingContainer
        $subHeadingContainer = $renderer->renderMenuItem($node, $getDescendantsParams, $subHeadingContainer, 1, $horizontalLevel, $this->isPageHightlighted($node->treeNodeId));
        $headingContainer->add($subHeadingContainer);

        // add tab content
        $tabContentContainer = $renderer->createInnerLevelContainer();
        //$currentContainer->add($tabContentContainer);
        $subTabContentContainer = $renderer->createInnerLevelItemContainer();
        $tabContentContainer->add($subTabContentContainer);

        $subTabContentContainer = $renderer->renderMenuItem($node, $getDescendantsParams, $subTabContentContainer, 2, $horizontalLevel, $this->isPageHightlighted($node->treeNodeId));

        $contentContainer->add($tabContentContainer);
        return $currentContainer;
    }

    public function loadRoots() {

    }

    private function _getSpecifiedRoot($labelRoots, $page) {

        if ($page->name == 'pages') return NULL;

        if (isset($labelRoots[$page->treeNodeId])) {
            return $labelRoots[$page->treeNodeId];
        } else {
            return $this->_getSpecifiedRoot($labelRoots, $page->parent);
        }

    }

    public function setUpSpecifiedRoot($page, $useCurrentPageAsLabelRoot, $ignorePage) {
        $this->loadRoots();
        $specifiedRoot = NULL;
        if ($page !== NULL && !$ignorePage) {

            if (!$useCurrentPageAsLabelRoot) {
                $indexedRoots = array();

                foreach ($this->roots as $root) {
                    $indexedRoots[$root->_tree_node_id] = $root;
                }
                $specifiedRoot = $this->_getSpecifiedRoot($this->roots, $page);
            } else {
                $specifiedRoot = $page;
            }

        }
        $this->specifiedRoot = $specifiedRoot;
        return $this;
    }

    public function getSpecifiedRoot() {
        return $this->specifiedRoot;
    }


}