<?php

namespace Bubo\Navigation\Rendering;

use Nette,
    Nette\Utils\Html;

class PageMenuRenderer extends Nette\Object {

    // callback
    public $onRenderMenuItem;

    // standard wrappers
    private $wrappers;

    private $prototypes;

    private $currentClassName;

    public function __construct() {
        // default setting of wrappers
        $this->wrappers['topLevel']['container'] = 'ul';
        $this->wrappers['topLevelItem']['container'] = 'li';
        $this->wrappers['innerLevel']['container'] = 'ul';
        $this->wrappers['innerLevelItem']['container'] = 'li';

        $this->currentClassName = 'active';

        $this->onRenderMenuItem = callback($this, 'getMenuItem');
        
        $this->_updatePrototypes();
    }

    private function _updatePrototypes() {
        foreach ($this->wrappers as $wrapperName => $wrapper) {
            $this->prototypes[$wrapperName] = Html::el($this->wrappers[$wrapperName]['container']);
        }
    }

    private function _getPrototype($name) {
        return $this->prototypes[$name];
    }

    public function getTopLevelPrototype() {
        return $this->_getPrototype('topLevel');
    }

    public function getTopLevelItemPrototype() {
        return $this->_getPrototype('topLevelItem');
    }

    public function getInnerLevelPrototype() {
        return $this->_getPrototype('innerLevel');
    }

    public function getInnerLevelItemPrototype() {
        return $this->_getPrototype('innerLevelItem');
    }


    public function setWrappers($wrappers) {
        foreach ($wrappers as $wrapperName => $container) {
            $this->wrappers[$wrapperName]['container'] = $container;
        }

        $this->_updatePrototypes();
    }


    public function createTopLevelContainer() {
        return clone $this->_getPrototype('topLevel');
    }

    public function createTopLevelItemContainer() {
        return clone $this->_getPrototype('topLevelItem');
    }

    public function createInnerLevelContainer() {
        return clone $this->_getPrototype('innerLevel');
    }

    public function createInnerLevelItemContainer() {
        return clone $this->_getPrototype('innerLevelItem');
    }



    public function renderMenuItem() {
        //$args = func_get_args();
        return $this->onRenderMenuItem->invokeArgs(func_get_args());
    }

    public function setCurrentClassName($currentClassName) {
        $this->currentClassName = $currentClassName;
    }

    public function getCurrentClassName() {
        return $this->currentClassName;
    }


    // default render implementation : <a href="link" title="link_title">title</a>
    public function getMenuItem($page, $descendantsParams, $menuItemContainer, $level, $horizontalLevel, $highlight) {

        $menuItemContainer->class($highlight ? $this->getCurrentClassName() : NULL);

        return $menuItemContainer->add(Html::el('a')
                                        ->href($page->_front_url)
                                        ->title($page->_link_title)
                                        ->setText($page->_title));

    }


}
