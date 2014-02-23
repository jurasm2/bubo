<?php

namespace Bubo\Traversersing\RenderingTraversers;

use Bubo\Traversing;

/**
 * Rendering Traverser
 *
 * @author Marek Juras
 */
final class AdminMenuTraverser extends Traversing\RenderingTraverser {

    private $presenter;


    public function __construct($menu) {
        parent::__construct();

        $this->setMenu($menu);
    }

    // DFS
    public function setPresenter($presenter) {
        $this->presenter = $presenter;
        return $this;
    }


    public function traverse() {

        $callback = callback($this, 'recursiveDFSRender');
        $topLevelContainer = $this->getRenderer()->createTopLevelContainer();

        $descendantsParams = array(
                                'lang'                  =>  $this->getLang(),
                                'entityConfig'          =>  $this->getEntityConfig(),
                                'groupName'             =>  $this->getGroupName(),
                                'searchGhosts'          =>  $this->canSearchGhosts(),
                                'states'                =>  $this->getAcceptedStates(),
                                'searchAllTimeZones'    =>  $this->canSearchAllTimeZones(),
                                'sortingCallback'       => $this->getSortingCallback(),
                                'filterCallback'        => $this->getFilterCallback()

        );

        $topLevelMenuItems = $this->presenter['pages']->getDescendants($descendantsParams);

        if (!empty($topLevelMenuItems)) {
            $numOfTopLevelItem = count($topLevelMenuItems);
            foreach ($topLevelMenuItems as $topLevelItem) {
                //dump($topLevelItem->_labels);
                $topLevelContainer = $callback->invoke(
                    $topLevelItem,
                    $descendantsParams,
                    $this->getRenderer(),
                    $topLevelContainer,
                    1,
                    1,
                    $numOfTopLevelItem);
            }
        }
        return $topLevelContainer;
    }


}