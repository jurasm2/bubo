<?php

namespace Bubo\Media\TemplateContainers;

/**
 * Representation of image file in front end template
 */
class MediaImage extends AbstractTemplateContainer {

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
        return $this->extractDescription($name, $lang, $this->imageData['ext']);
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