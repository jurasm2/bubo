<?php

/**
 * @author     Marek Juras
 */

namespace Bubo\Services;

use Bubo;

class TraverserFactory extends BaseService {

    private $context;

    private $presenter;

    public function __construct($context) {
        $this->context = $context;
    }

    public function isPresenterSet() {
        return $this->presenter !== NULL;
    }

    public function setPresenter($presenter) {
        $this->presenter = $presenter;
    }


    public function createLabelTraverser($menu) {
        $labelTraverser = new Bubo\Traversing\RenderingTraversers\LabelTraverser($menu);
        return $labelTraverser;
    }

    public function createTraverser($class, $menu) {
        $className = "Bubo\Traversing\RenderingTraversers\\" .$class;
        $t = new $className($menu);

        return $t;
    }


}