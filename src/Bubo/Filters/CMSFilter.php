<?php

namespace Bubo\Filters;

class CMSFilter extends \Nette\Object {

    private $galleryLayout;
    private $page;
    private $fileGet;

    private $replacements;

    public function __construct($filterParams = array()) {

        $this->galleryLayout = 'defaultGallery';
        if (isset($filterParams['gallery']) && isset($filterParams['gallery']['layout'])) {
            $this->galleryLayout = $filterParams['gallery']['layout'];
            $this->page = $filterParams['page'];
        }

        $this->fileGet = 'tag';
        if (isset($filterParams[0]['file']) && isset($filterParams[0]['file']['get'])) {
            $this->fileGet = $filterParams[0]['file']['get'];
        }

        $this->replacements = array(
                                'file'      =>  array(
                                                    'pattern'       => "|(\<div[^\>]*id=\"(\d+)\"[^\>]*class=\"mceCMSFile.*(?<!(?:\/div))\<\/div\>)|imsU",
                                                    'replacement'   => "{control file $2, $this->fileGet}"
                                                ),
                                'gallery'   =>  array(
                                                    'pattern'       => "|(\<div[^\>]*id=\"(\d+)\"[^\>]*class=\"mceGallery.*(?<!(?:\/div))\<\/div\>)|imsU",
                                                    'replacement'   => "{control $this->galleryLayout $2, ".'$_page'."}"
                                                ),
                                'block'     =>  array(
                                                    'pattern'       => "|(\<div[^\>]*id=\"([^\"]+)\"[^\>]*class=\"mceCMSBlock.*(?<!(?:\/div))\<\/div\>)|imsU",
                                                    'replacement'   => '$2'
                                                ),
                                'media'     =>  array(
                                                    'pattern'       => "|(\<img[^\>]*data-gallery-id=\"gallery\-([0-9a-zA-Y\-]+)\"[^\>]*\>)|imsU",
                                                    'replacement'   => "{control $this->galleryLayout $2, ".'$_page'.", '800x600-shrink_only|200x200-shrink_only'}"
                                                ),
        );

    }

    public function getPattern($name) {
        return isset($this->replacements[$name]) ? $this->replacements[$name]['pattern'] : NULL;
    }

    public function __invoke($s) {

        $replacements = array();


        if (!empty($this->replacements)) {
            foreach ($this->replacements as $rep) {
                $replacements[$rep['pattern']] = $rep['replacement'];
            }
        }
        return preg_replace(array_keys($replacements), array_values($replacements), $s);
    }


    public function setGalleryLayout($galleryLayout) {
        $this->galleryLayout = $galleryLayout;
    }

}
