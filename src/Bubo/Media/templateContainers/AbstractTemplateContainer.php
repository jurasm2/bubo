<?php

namespace Bubo\Media\TemplateContainers;

use Nette;

/**
 * Description of AbstractTemplateContainer
 *
 */
abstract class AbstractTemplateContainer extends Nette\Object {

    protected $presenter;

    protected $paths;

    abstract public function getPath($index = 0);

    abstract public function pathExists($index = 0);

    abstract public function getDirPath($index = 0);

    abstract public function getDescription($name, $lang = NULL);

    protected function extractDescription($name, $lang, $ext)
    {
        $returnText = NULL;

        $_lang = $lang === NULL ? $this->presenter->getFullLang() : $lang;
        $description = \Bubo\Utils\MultiValues::unserialize($ext);

        if ($description) {
            if (isset($description[$_lang][$name])) {
                $returnText = $description[$_lang][$name];
            }
        }

        return $returnText;
    }
}
