<?php

namespace Bubo\Media\TemplateContainers;

/**
 * Representation of image file in front end template
 */
class MediaImage extends AbstractTemplateContainer {

    public $presenter;

    protected $imageData;

    public function __construct($imageData, $presenter)
    {
        $this->imageData = $imageData;
        $this->presenter = $presenter;
    }

    public function setPaths($paths)
    {
        $this->paths = $paths;
    }

    public function pathExists($index = 0)
    {
        return isset($this->paths[$index]);
    }

    public function getDescription($name, $lang = NULL)
    {
        $returnText = NULL;

        $_lang = $lang === NULL ? $this->presenter->getFullLang() : $lang;
        $description = \Bubo\Utils\MultiValues::unserialize($this->imageData['ext']);

        if ($description) {
            if (isset($description[$_lang][$name])) {
                $returnText = $description[$_lang][$name];
            }
        }

        return $returnText;
    }

    public function getDirPath($index = 0)
    {
        return $this->pathExists($index) ? ($this->getMediaBaseDir() . '/' . $this->paths[$index]) : NULL;
    }

    public function getMediaBasePath()
    {
        return $this->presenter->mediaManagerService->getBasePath();
    }

    public function getMediaBaseDir()
    {
        return $this->presenter->mediaManagerService->getBaseDir();
    }

    public function getPath($index = 0)
    {
        return $this->pathExists($index) ? ($this->getMediaBasePath() . '/' . $this->paths[$index]) : NULL;
    }

}