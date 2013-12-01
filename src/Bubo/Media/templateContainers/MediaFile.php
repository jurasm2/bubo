<?php

namespace Bubo\Media\TemplateContainers;

use Nette\Utils\Html;

/**
 * Representation of image file in front end template
 */
class MediaFile extends AbstractTemplateContainer {

    protected $file;

    public function __construct($file)
    {
        $this->file = $file;
    }

    public function setPaths($paths)
    {
        $this->paths = $paths;
    }

    public function getPaths()
    {
        return $this->paths;
    }

    public function pathExists($index = 0)
    {
        return isset($this->paths['urls'][$index]);
    }

    public function getAsImage($index = 0)
    {
        $img = Html::el('img');
        $img->src = $this->getPath($index);
        return $img;
    }

    public function getPath($index = 0)
    {
        return $this->pathExists($index) ? $this->paths['urls'][$index] : NULL;
    }

    public function __toString()
    {
        return '';
    }

    public function getDirPath($index = 0)
    {
        return $this->pathExists($index) ?  $this->paths['dirPaths'][$index] : NULL;
    }

    public function fileExists() {
        return $this->file !== FALSE;
    }
}