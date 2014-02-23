<?php

namespace Bubo;

use Nette;
use Nette\Utils\Strings;

class MediaFileInput extends Nette\Forms\Controls\TextBase {

    public function __construct($label = NULL) {
        parent::__construct($label);
        $this->control->type = 'text';
    }

    public function getControl() {
        $control = parent::getControl();
        $form = $this->lookup('Nette\\Forms\\Form');
        $control->style = "display:none;";

        // resolve: Entity param or label extension?
        if (Nette\Utils\Strings::startsWith($this->name, 'ext_')) {
            // this is label extension
            $extIdentifier = substr($this->name, 4);

            $extName = $form->presenter->mediaManagerService->getLabelExtNameByIdentifier($extIdentifier);
            if (Strings::startsWith($extName, 'mediaFile')) {
                $extName = 'mediaFile';
            } else if (Strings::startsWith($extName, 'mediaGallery')) {
                $extName = 'mediaGallery';
            }
        } else {
            // this is entity param
            $entity = $form->presenter->getParam('entity');
            if ($entity === NULL) {
                // determine entity by $treeNodeId
                $treeNodeId = $form->presenter->getParam('id');
                if ($treeNodeId === NULL) {
                    throw new \Nette\InvalidArgumentException("Unable to determine entity");
                }
                $entity = $form->presenter->pageModel->getEntity($treeNodeId);
            }

            // load page configuration
            $entityConfig = $form->presenter->configLoaderService->loadEntityConfig($entity);
            $properties = $entityConfig['properties'];
            $extName = isset($properties[$this->name]['type']) ? $properties[$this->name]['type'] : null;
        }


        $control->value = $this->getValue() === '' ? $this->translate($this->emptyValue) : $this->value;

        $divContainer = Nette\Utils\Html::el('div');
        $divContainer->class('media-container');

        $divContent = $divContainer->create('div');
        $divContent->class('media-container-content');

        $thumbImage = Nette\Utils\Html::el('img');

        $buttonParams = array(
                            'cid'           =>  $control->id,
                            'media-extName' =>  $extName,
                            'media-trigger' =>  'container'
        );

        //dump(json_decode($control->value, TRUE));
        $encodedParams = json_decode($control->value, TRUE);

        $mediaType = NULL;
        $mediaId = NULL;
        $restoreUrl = NULL;
        if ($encodedParams !== NULL && is_array($encodedParams)) {
            $mediaType = isset($encodedParams['mediaType']) ? $encodedParams['mediaType'] : NULL;
            $mediaId = isset($encodedParams['mediaId']) ? $encodedParams['mediaId'] : NULL;
            $restoreUrl = isset($encodedParams['restoreUrl']) ? $encodedParams['restoreUrl'] : NULL;
        }

        if ($mediaType !== NULL && $mediaId !== NULL) {

            switch ($mediaType) {
                case 'file':
                    $file = $form->presenter->mediaManagerService->getFile($mediaId);

                    $section = $form->presenter->mediaManagerService->getFileSection($mediaId);

                    $iconSrc = $form->presenter->mediaManagerService->getFileIconSrc($file);
                    $thumbImage->src = $iconSrc;

                    //$buttonParams = array_merge($buttonParams, $this->_getFileDetailParams($matches[2], $file['folder_id'], $section));
                    $buttonParams = array_merge($buttonParams, $form->presenter->mediaManagerService->getFileRestoreParams($file['folder_id'], $mediaId, $section, FALSE));
                    break;
                case 'gallery':
                    $folder = $form->presenter->mediaManagerService->getFolder($mediaId);
                    $iconSrc = $form->presenter->mediaManagerService->getGalleryIconSrc($folder['folder_id']);
                    $thumbImage->src = $iconSrc;

                    //$buttonParams = array_merge($buttonParams, $this->_getGalleryDetailParams($matches[2], $folder['parent_folder']));
                    $buttonParams = array_merge($buttonParams, $form->presenter->mediaManagerService->getGalleryRestoreParams($mediaId, $folder['parent_folder'], FALSE));
                    break;
            }

        }

        $divContent->add($thumbImage);

        $actionButton = $divContainer->create('a');
        $actionButton->href($form->presenter->link('Tiny:pokus', $buttonParams))
                     ->class('colorbox vdColorbox')
                     ->setText('Otevřít disk');

        $sep = $divContainer->create(NULL);
        $sep->setText(' ');

        $removeButton = $divContainer->create('a');
        $removeButton->href('#')
                     ->class('remove-media-item')
                     ->cid($control->id)
                     ->setText('Smazat');

        if (!$control->value) {
            $removeButton->style('display:none;');
        }

        $divContainer->add($control);

        return $divContainer;
    }

}
