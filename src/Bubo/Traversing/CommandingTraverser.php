<?php

namespace Bubo\Traversing;

/**
 * Page Traverser
 *
 * @author Marek Juras
 */
final class CommandingTraverser extends Traverser {

    private $commander;
    private $isSinglePage = FALSE;
    private $traverseOnlyClipboardedPages = FALSE;
    private $level = 1;


    public function setCommander($commander) {
        $this->commander = $commander;
        return $this;
    }

    public function setSinglePage($isSinglePage) {
        $this->isSinglePage = $isSinglePage;
        return $this;
    }

    public function setLevel($level) {
        $this->level = $level;
        return $this;
    }

    public function setTraverseOnlyClipboardedPages($bool) {
        $this->traverseOnlyClipboardedPages = $bool;
        return $this;
    }

    public function isTraverseOnlyClipboardedPages() {
        return $this->traverseOnlyClipboardedPages;
    }

    public function getCommander() {
        return $this->commander;
    }

    public function traverse() {
        // get root
        $roots = $this->getRoots();

//        dump('tohle jsou rooti', $roots);
//        die();

        $rootNode = NULL;

        foreach ($roots as $rootNode) {

            $getDescendantsParams = array(
                                        'lang'  =>  $rootNode->_lang,
                                        'searchGhosts'  =>  FALSE,
                                        'states'    =>  NULL,
            );


            if ($this->isSinglePage) {
                $this->_oneShotOperation($rootNode, $this->getAcceptedStates(), $this->getCommander(), $this->traverseOnlyClipboardedPages);
            } else {
                $this->_recursiveDFSWalk($rootNode, $getDescendantsParams, $this->getCommander(), $this->level, $this->traverseOnlyClipboardedPages, NULL);
            }

        }

    }


    private function _oneShotOperation($node, $getDescendantsParams, $acceptedStates, $commander) {
        $commander->performPageCommandCallback($node, $this->level, NULL);
    }

    private function _recursiveDFSWalk($node, $getDescendantsParams, $commander, $level, $returnValue) {

        $retValue = $commander->performPageCommandCallback($node, $level, $returnValue);

        $descendants = $node->getDescendants($getDescendantsParams);
        if (count($descendants) > 0) {
            $furtherLevel = $level + 1;
            foreach ($descendants as $descendant) {
                $this->_recursiveDFSWalk($descendant, $getDescendantsParams, $commander, $furtherLevel, $retValue);
            }
        }

    }

}