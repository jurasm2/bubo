<?php

namespace Bubo\Media\Components;

use Bubo\Application\UI\Control;

class SectionSwitch extends Control {

    public function handleSwitchSection($section) {
        $this->parent->section = $section;
        $this->parent['content']->view = NULL;
        $this->parent['content']->actions = NULL;
        $this->parent->folderId = NULL;
        $this->parent->fileId = NULL;
        $this->parent->invalidateControl();
    }

    public function render() {

        $sections = $this->parent->getAllSections();
        $currentSection = $this->parent->getCurrentSection();

        $template = parent::initTemplate(__DIR__ . '/templates/default.latte');

        $template->sections = $sections;
        $template->currentSection = $currentSection;
        $template->render();

    }


}
