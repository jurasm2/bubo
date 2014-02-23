<?php

namespace Bubo\Traversing\RenderingTraversers;

use Bubo\Traversing;

use Nette\Utils\Html;

/**
 * Label traverser
 *
 * - for creating label menus
 *
 * @author Marek Juras
 */
final class LabelTraverser extends Traversing\RenderingTraverser {

    private $label;

    // menu or tabs?
    private $type;
    private $traversingMethod;

    // how to sort label roots
    /**
     * Select the subject of ordering
     *
     * is set indirectly by methods
     * $this->rootsOrderByRand()
     * $this->rootsOrderByDate($direction)
     *
     * default value: NULL - default ordering
     * usage: $this->rootsOrderByDate('desc');
     * @var type
     */
    private $rootsOrderBy = NULL;

    /**
     * Order of direction (only for date)
     * desc or asc
     *
     * default: desc
     * usage: $this->rootsOrderByDate('asc');
     * @var type
     */
    private $rootsOrderDirection = 'desc';


    private $rootsSortingCallback;
    private $rootsFilterCallback;

    private $rootsLimit;

    public function __construct($menu, $type = 'menu') {
        parent::__construct();
        $this->type = $type;
        $this->traversingMethod = $type == 'menu' ? 'recursiveDFSRender' : 'BFSRender';

        $this->setMenu($menu);
        $this->setLabel($menu->labelName);
        $this->setLang($menu->lang);
    }

    public function setLabel($labelName) {

        $label = $this->menu->presenter->pageManagerService->getLabelByName($labelName);

        if (empty($label)) {
            throw new \Nette\InvalidStateException("Label ".$labelName." was not found");
        }

        $this->label = $label;
        return $this;
    }

    public function getLabel() {
        return $this->label;
    }

    public function rootsLimit($rootsLimit) {
        $this->rootsLimit = $rootsLimit;
        return $this;
    }

    public function getRootsLimit() {
        return $this->rootsLimit;
    }

    public function setRootsSortingCallback($callback) {
        $this->rootsSortingCallback = $callback;
        return $this;
    }

    public function getRootsSortingCallback() {
        return $this->rootsSortingCallback;
    }

    public function setRootsFilterCallback($callback) {
        $this->rootsFilterCallback = $callback;
        return $this;
    }

    public function getRootsFilterCallback() {
        return $this->rootsFilterCallback;
    }

    public function rootsOrderByDate($direction = 'desc') {
        $this->rootsOrderDirection = $direction;
        $this->rootsOrderBy = 'date';
        return $this;
    }

    public function getRootsOrderDirection() {
        return $this->rootsOrderDirection;
    }

    public function rootsOrderByRand() {
        $this->rootsOrderBy = 'rand';
        return $this;
    }

    public function getRootsOrderBy() {
        return $this->rootsOrderBy;
    }


    public function labelSort($a, $b) {
        $pageSort = $this->label['page_order'];

        if ($pageSort && isset($pageSort[$a->_parent])) {

            $rootSorting = $pageSort[$a->_parent];

            $aIndex = array_search($a->_tree_node_id, $rootSorting);
            $bIndex = array_search($b->_tree_node_id, $rootSorting);

            if ($aIndex !== FALSE && $bIndex !== FALSE) { return $aIndex - $bIndex; }
            else if ($aIndex !== FALSE) { return -1; }
            else if ($bIndex !== FALSE) { return 1; }
        }

        return $a->_sortorder - $b->_sortorder;
    }

    /**
     *
     * Choose descendant sorting method
     * --------------------------------
     *
     * Proirity
     * 1) custom sort (if provided)
     * 2) order by date or rand (if provided)
     * 3) order by manual label sorting (default)
     *
     */
    private function _setSortingCallbackForDescendants() {
        if (!is_callable($this->getSortingCallback())) {
        $orderBy = $this->getOrderBy();
            switch ($orderBy) {
                case 'date':
                    $direction = $this->getOrderDirection();
                    $desc = 'desc';
                    $this->setSortingCallback(function($a, $b) use($direction, $desc) {
                        $cmp = 0;
                        if ($a->_node_created < $b->_node_created) { $cmp = -1; }
                        else if ($a->_node_created > $b->_node_created) { $cmp = 1; }
                        return $direction == $desc ? -$cmp : $cmp;
                    });
                    break;
                case 'rand':
                    $this->setSortingCallback(function($a, $b) {
                        return rand(-1, 1);
                    });
                    break;
                default:
                    $this->setSortingCallback(callback($this, 'labelSort'));
            }
        }
    }


    /**
     *
     * Choose roots sorting method
     * --------------------------------
     *
     * Proirity
     * 1) custom sort (if provided)
     * 2) order by date or rand (if provided)
     * 3) order by manual label sorting (default)
     *
     */
    private function _setSortingCallbackForRoots() {

        $orderBy = $this->getRootsOrderBy();
        switch ($orderBy) {
            case 'date':
                $direction = $this->getRootsOrderDirection();
                $desc = 'desc';
                $this->setRootsSortingCallback(function($a, $b) use($direction, $desc) {
                    $cmp = 0;
                    if ($a->_node_created < $b->_node_created) { $cmp = -1; }
                    else if ($a->_node_created > $b->_node_created) { $cmp = 1; }
                    return $direction == $desc ? -$cmp : $cmp;
                });
                break;
            case 'rand':
                $this->setRootsSortingCallback(function($a, $b) {
                    $ret = rand(-10, 10);
                    return $ret;
                });
                break;
            default:
                $this->setRootsSortingCallback(NULL);
        }

    }


    public function loadRoots() {
        if (!$this->roots) {
            $this->roots = $this->menu->presenter->pageManagerService->getLabelRoots($this->label['label_id'], $this->lang, $this->getAcceptedStates(), $this->getEntityConfig());

            $this->_setSortingCallbackForRoots();
            $sc = $this->getRootsSortingCallback();

            if ($this->rootsOrderBy == 'rand') {
                $r = $this->roots;
                shuffle($r);
                $this->roots = $r;
            } else {
                if (is_callable($sc)) {
                    @usort($this->roots, $sc);
                }
            }

            if (!empty($this->rootsLimit)) {
                $this->roots = array_slice($this->roots, 0, $this->rootsLimit, TRUE);
            }
        }
    }

    public function traverse() {
        $callback = callback($this, $this->traversingMethod);

        $topLevelContainer = $this->type == 'menu' ? $this->getRenderer()->createTopLevelContainer() : Html::el();
        $this->_setSortingCallbackForDescendants();

        $descendantsParams = array(
                                'lang'                  =>  $this->getLang(),
                                'labelId'               =>  $this->label['label_id'],
                                'entityConfig'          =>  $this->getEntityConfig(),
                                'groupName'             =>  $this->getGroupName(),
                                'searchGhosts'          =>  $this->canSearchGhosts(),
                                'states'                =>  $this->getAcceptedStates(),
                                'searchAllTimeZones'    =>  $this->canSearchAllTimeZones(),
                                'orderDirection'        =>  $this->getOrderDirection(),
                                'limit'                 =>  $this->getLimit(),
                                'sortingCallback'       =>  $this->getSortingCallback(),
                                'filterCallback'        =>  $this->getFilterCallback()
        );
        $roots = $this->roots;

        if (!empty($roots)) {

            $sr = $this->getSpecifiedRoot();
            $horizontalLevel = 1;
            foreach ($sr ? array(0 => $sr) : $roots as $root) {

                if ($this->getSkipLevel() == 1) {
                    $descendants = $root->getDescendants($descendantsParams);
                    $numOfDescendants = count($descendants);
                    if ($numOfDescendants > 0) {
                        foreach ($descendants as $descendant) {
                            $topLevelContainer = $callback->invoke(
                                $descendant,
                                $descendantsParams,
                                $this->getRenderer(),
                                $topLevelContainer,
                                1,
                                $horizontalLevel++,
                                $numOfDescendants
                            );
                        }
                    }
                } else {
                    $topLevelContainer = $callback->invoke(
                        $root,
                        $descendantsParams,
                        $this->getRenderer(),
                        $topLevelContainer,
                        1,
                        $horizontalLevel++,
                        1);
                }

            }
        }
        return $topLevelContainer;
    }


}