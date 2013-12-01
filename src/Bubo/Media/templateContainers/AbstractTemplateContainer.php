<?php

namespace Bubo\Media\TemplateContainers;

use Nette;

/**
 * Description of AbstractTemplateContainer
 *
 */
abstract class AbstractTemplateContainer extends Nette\Object {

    protected $paths;

    abstract public function getPath($index = 0);

    abstract public function pathExists($index = 0);

    abstract public function getDirPath($index = 0);
}
