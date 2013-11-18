<?php
namespace Bubo\Media\Components;

use Bubo\Application\UI\Control;
use Bubo\Media\Components\Forms;

use Nette;
use Nette\Utils\Html;

/**
 * Class representing the content pane of the virtual drive
 * Layout of the content is changed by $view property
 */
class Content extends Control
{

    /**
     * Name of the view
     * @persistent
     * @var string
     */
    public $view;

    /**
     * @persistent
     */
    public $actions;

    /**
     * Folder content storage
     * @var array|null
     */
    private $folderContent = null;

    /**
     * Constructor
     * @param type $parent
     * @param string $name
     */
    public function __construct($parent, $name)
    {
        parent::__construct($parent, $name);
    }

    /**
     *
     * @param string $type
     * @param int $id
     * @return array
     */
    public function getFolderContentItem($type, $id)
    {
	$folderContent = $this->getFolderContent();
        return $folderContent[$type][$id];
    }

    /**
     * Initializes and returns folder content
     * @return array
     */
    public function getFolderContent()
    {
	$this->initFolderContent();
	return $this->folderContent;
    }

    /**
     * Factory for popUp window
     * @param string $name
     * @return PopUp
     */
    public function createComponentPopUp($name)
    {
        return new Content\PopUp($this, $name);
    }

    /**
     * Factory for "load files" form
     * @param string $name
     * @return Forms\LoadFilesForm
     */
    public function createComponentLoadFilesForm($name)
    {
        return new Forms\LoadFilesForm($this, $name);
    }

    /**
     * Factory for "load images" form
     * @param string $name
     * @return Forms\LoadImagesForm
     */
    public function createComponentLoadImagesForm($name)
    {
        return new Forms\LoadImagesForm($this, $name);
    }

    /**
     * Generic factory for content items
     * @return \Nette\Application\UI\Multiplier
     */
    public function createComponentContentItem()
    {
        return new Nette\Application\UI\Multiplier(function ($itemId) {
            $itemChunks = explode('_',$itemId);
            $className = 'Bubo\\Media\\Components\\Items\\'.ucfirst($itemChunks[0]);
            $contentItem = new $className;
            $contentItem->setId($itemChunks[1]);
            return $contentItem;
        });
    }

    /**
     * Factory for pasteBin component
     * @param string $name
     * @return \Bubo\Media\Components\SessionManager\PasteBin
     */
    public function createComponentPasteBin($name)
    {
        return new SessionManager\PasteBin($this, $name);
    }

    /**
     * Retuns folderId
     * @return int
     */
    public function getFolderId()
    {
        return $this->parent->folderId;
    }

    /**
     * Returns fileId
     * @return type
     */
    public function getFileId()
    {
        return $this->parent->fileId;
    }

    /**
     * Returns currently selected section
     * @return string
     */
    public function getCurrentSection()
    {
        return $this->parent->getCurrentSection();
    }

    /**
     * Returns current media trigger
     * @return string
     */
    public function getMediaTrigger()
    {
        return $this->parent->getTrigger();
    }

    /**
     * Handler for opening "create folder" popup
     */
    public function handleOpenCreateFolderPopup()
    {
        $this['popUp']->setTemplate(NULL);
        $this->invalidateControl('popUp');
    }

    /**
     * Handler for opening "create gallery popup"
     */
    public function handleOpenCreateGalleryPopup()
    {
        $this['popUp']->setTemplate('createGallery');
        $this->invalidateControl('popUp');
    }

    /**
     * Handler for opening "rename folder" popup
     * @param int $folderId
     */
    public function handleOpenRenameFolderPopup($folderId)
    {
        $this['popUp']->setTemplate('renameFolder');
        $this['popUp']->setId($folderId);
        $this->invalidateControl('popUp');
    }

    /**
     * Handler for opening "rename file" popup
     * @param int $fileId
     */
    public function handleOpenRenameFilePopup($fileId)
    {
        $this['popUp']->setTemplate('renameFile');
        $this['popUp']->setId($fileId);
        $this->invalidateControl('popUp');
    }

    /**
     * Handler for opening "rename gallery"
     * @param int $galleryId
     */
    public function handleOpenRenameGalleryPopup($galleryId)
    {
        $this['popUp']->setTemplate('renameGallery');
        $this['popUp']->setId($galleryId);
        $this->invalidateControl('popUp');
    }

    /**
     * Handler for opening "delete folder" popup
     * @param int $folderId
     */
    public function handleOpenDeleteFolderPopup($folderId)
    {
        $this['popUp']->setTemplate('confirmDeleteFolder');
        $this['popUp']->setId($folderId);

        $folder = $this->presenter->mediaManagerService->getFolderContentItem('folders', $folderId);
        $this['popUp']->setMessage('Opravdu smazat složku "'.$folder['name'].'" ?');
        $this->invalidateControl('popUp');
    }

    /**
     * Handler for opening "delete file" popup
     * @param int $fileId
     */
    public function handleOpenDeleteFilePopup($fileId)
    {
        $this['popUp']->setTemplate('confirmDeleteFile');
        $this['popUp']->setId($fileId);

        $file = $this->presenter->mediaManagerService->getFolderContentItem('files', $fileId);

        $this['popUp']->setMessage('Opravdu smazat soubor "'.$file['name'].'" ?');
        $this->invalidateControl('popUp');
    }

    /**
     * Handler for opening "delete gallery" popup
     * @param int $galleryId
     */
    public function handleOpenDeleteGalleryPopup($galleryId)
    {
        $this['popUp']->setTemplate('confirmDeleteGallery');
        $this['popUp']->setId($galleryId);

        $gallery = $this->presenter->mediaManagerService->getFolderContentItem('folders', $galleryId);

        $this['popUp']->setMessage('Opravdu smazat galerii "'.$gallery['name'].'" ?');
        $this->invalidateControl('popUp');
    }

    /**
     * Handler for opening "edit titles" popup
     * @param int $fileId
     */
    public function handleOpenEditTitlesPopup($fileId)
    {
        $this['popUp']->setTemplate('editTitles');
        $this['popUp']->setId($fileId);

        $this->invalidateControl('popUp');
    }

//    public function getProgressBarCommand($command, $refreshValue = NULL) {
//
//        $data['command'] = $command;
//
//        switch ($command) {
//            case 'open';
//                break;
//            case 'close':
//                break;
//            case 'refresh':
//                $data['data'] = array('value' => $refreshValue);
//                break;
//        }
//
//        return json_encode(array('progressBar' => $data));
//
//    }

    /**
     *
     * @param type $fileId
     * @param type $select
     */
    public function handleToggleSelectFile($fileId, $select)
    {
        $this->presenter->mediaManagerService->toggleSelectFile($fileId, $select);
        $this->parent->invalidateControl();
    }

    public function handleInsertFileToContainer($fileId) {
        $extension = $this->presenter->configLoaderService->loadLabelExtentsionProperties();
        $this->presenter->mediaManagerService->loadFile($fileId,
                                                        $extension['properties'][$this->parent->extName]['mode']);

        $folderItem = $this->getFolderContentItem('files', $fileId);
        $section = $this->presenter->mediaManagerService->getFileSection($fileId);
        $restoreUrl = $this->presenter->mediaManagerService->getFileRestoreUrl($folderItem['folder_id'],
                                                                               $fileId,
                                                                               $section,
                                                                               FALSE);

        $array = array(
            'mediaType'     => 'file',
            'mediaId'       => $fileId,
            'restoreUrl'    => $restoreUrl
        );

        $this->presenter->payload->insertMedia = array(
            'cid'          =>  $this->presenter->getParam('cid'),
            'mediaid'      =>  json_encode($array),
            'mediathumb'   =>  $this->presenter->mediaManagerService->getFileIconSrc($folderItem)
        );

        $this->presenter->terminate();
    }

    /**
     * Handles entering the gallery
     * @param int $folderId
     */
    public function handleEnterGallery($folderId) {
        $this->parent->folderId = $folderId;
        $this->parent->invalidateControl();
    }

    /**
     * Changes view to loadFiles
     */
    public function handleLoadFiles()
    {
        $this->view = 'loadFiles';
        $this->parent->invalidateControl();
    }

    /**
     * Changes view to loadImages
     */
    public function handleLoadImages()
    {
        $this->view = 'loadImages';
        $this->parent->invalidateControl();
    }

    /**
     * Changes view to manageTitles??
     * @param int $fileId
     */
    public function handleManageTitles($fileId)
    {
        $this->view = 'manageTitles';
        $this->parent->invalidateControl();
    }

    /**
     * Handler for image sorting
     */
    public function handleSortImages()
    {
        parse_str($this->presenter->getParam('data'));
        $this->presenter->mediaManagerService->sortImages($file);
        //$this->invalidateControl(NULL);
        echo "ok";
        die();
        //$this->presenter->payload->data = NULL;
    }

    private function _loadImages($galleryId)
    {
        $extension = $this->presenter->configLoaderService->loadLabelExtentsionProperties();

        $config = $this->parent->getConfig('general');
        $galleryMode = $config['defaultGalleryMode'];

        if ($this->parent->extName !== NULL) {
            // use default size for gallery
            $galleryMode = $extension['properties'][$this->parent->extName]['mode'];
        }
        $this->presenter->mediaManagerService->loadImages($galleryId, $galleryMode);
    }

    /**
     * Inserts gallery to container
     * @param int $galleryId
     */
    public function handleInsertGalleryToContainer($galleryId)
    {
        $this->_loadImages($galleryId);

        $gallery = $this->presenter->mediaManagerService->getFolder($galleryId);
        $restoreUrl = $this->presenter->mediaManagerService->getGalleryRestoreUrl($galleryId,
                                                                                  $gallery['parent_folder'],
                                                                                  TRUE);

        $array = array(
            'mediaType'     => 'gallery',
            'mediaId'       => $galleryId,
            'restoreUrl'    => $restoreUrl
        );

        $this->presenter->payload->insertMedia = array(
            'cid'          =>  $this->presenter->getParam('cid'),
            'mediaid'      =>  json_encode($array),
            'mediathumb'   =>  $this->presenter->mediaManagerService->getGalleryIconSrc($galleryId)
        );
        $this->presenter->terminate();
    }

    /**
     * Inserts gallery to tiny
     * @param int $galleryId
     */
    public function handleInsertGalleryToTiny($galleryId)
    {
        $this->_loadImages($galleryId);
        $gallery = $this->presenter->mediaManagerService->getFolder($galleryId);

        $restoreUrl = $this->presenter->mediaManagerService->getGalleryRestoreUrl($galleryId,
                                                                                  $gallery['parent_folder'],
                                                                                  FALSE);

        $imageAttributes = array(
            'src'               =>  $this->presenter->mediaManagerService->getTinyPlaceholderImageSrc($galleryId),
            'data-restoreUrl'   =>  $restoreUrl,
            'data-gallery-id'   =>  'gallery-'.$galleryId
        );

        $el = Html::el('img');
        $el->addAttributes($imageAttributes);


        $tinyArgs = array(
                        'html'   => $el->__toString()
        );

        $this->parent->sendTinyMceCommand($tinyArgs);

    }

    /**
     * Handler for loading content
     * Loads limited number of content items and returns then as html snippet
     * @param int $offset
     * @param int $limit
     */
    public function handleLoadContent($offset, $limit = 24)
    {
        $folderId = $this->getFolderId();
        $parentFolderId = NULL;

        if ($folderId !== NULL) {
            $parentFolderId = $this->presenter->mediaManagerService->getParentFolderId($folderId);
        }

        $templateFile = $this->view ?: 'default';

        if ($templateFile == 'default') {

            $template = parent::initTemplate(__DIR__ . '/templates/loadContent.latte');
            $template->cid = $this->presenter->getParam('cid');

            $template->folderId = $folderId;

            $template->sortImagesLink = $this->link('sortImages!');

            $template->folderContent = $this->getFolderContent();
            $template->parentFolderId = $parentFolderId;

            $template->offset = $offset;
            $template->limit = $limit;

            $response = $template->__toString();

            $mediaContent = array(
                                'currentOffset' =>  $offset + $limit,
                                'html'          =>  $response
            );

            $this->presenter->payload->mediaContent = $mediaContent;
            $this->presenter->terminate();
        }

    }

    /**
     * Renders the content pane
     */
    public function render()
    {
	$folderId = $this->getFolderId();
        $parentFolderId = NULL;

        if ($folderId !== NULL) {
            $parentFolderId = $this->presenter->mediaManagerService->getParentFolderId($folderId);
        }

        $templateFile = $this->view ?: 'default';
        $template = parent::initTemplate(__DIR__ . '/templates/'.$templateFile.'.latte');
        $template->cid = $this->presenter->getParam('cid');

        $template->folderId = $folderId;
        switch ($templateFile) {
            case 'fileDetail':
                $template->fileId = $this->getFileId();
                break;
            default:
                $template->sortImagesLink = $this->link('sortImages!');
        }

        $template->folderContent = $this->getFolderContent();
        $template->parentFolderId = $parentFolderId;

        $template->loadLink = $this->link('loadContent!');
        $template->pasteBin = $this['pasteBin'];
        $template->currentSection = $this->getCurrentSection();

        $template->render();
    }

    /**
     * Renders tool bar
     */
    public function renderToolBar()
    {

        $template = parent::initTemplate(__DIR__ . '/templates/toolbar.latte');
        $template->showSortBar = $this->view === NULL && $this->actions !== 'gallery';

        $template->pasteBin = $this['pasteBin'];

//        $template->view = $this->view;
//        $template->actions = $this->actions;
        $template->render();
    }

    /**
     * Retuns created toolbar menu item
     * @param string $title
     * @param string $class
     * @param array $aParams
     * @return Html
     */
    public function createMenuItem($title, $class, $aParams = array())
    {

        $a = Html::el('a');

        $i = Html::el('i');
        $i->class($class);

        $label = Html::el();
        $label->setText($title);

        $a->add($i);
        $a->add($label);

        foreach ($aParams as $k => $v) {
            $a->$k = $v;
        }

        return $a;
    }

    /* CONCRETE ACTIONS */

    /**
     * Returns button "new folder"
     * @return Html
     */
    public function createNewFolderMenuItem()
    {
        return $this->createMenuItem('Vytvořit složku', 'icon-folder-close m5', array(
            'class' =>  'button ajax',
            'href'  =>  $this->link('openCreateFolderPopup!')
        ));
    }

    /**
     * Returns button "new gallery"
     * @return Html
     */
    public function createNewGalleryMenuItem()
    {
        return $this->createMenuItem('Vytvořit galerii', 'icon-picture m5', array(
            'class' =>  'button ajax',
            'href'  =>  $this->link('openCreateGalleryPopup!')
        ));
    }

    /**
     * Returns button "load files"
     * @return Html
     */
    public function createLoadFilesMenuItem()
    {
        return $this->createMenuItem('Nahrát soubory', 'icon-upload-alt m5', array(
            'class' =>  'button ajax',
            'href'  =>  $this->link('loadFiles!')
        ));
    }

    /**
     * Returns button "load images"
     * @return Html
     */
    public function createLoadImagesMenuItem()
    {
        return $this->createMenuItem('Nahrát obrázky', 'icon-upload-alt m5', array(
            'class' =>  'button ajax',
            'href'  =>  $this->link('loadImages!')
        ));
    }

    /**
     * @return Html
     */
    private function _createInsertGalleryToContainerMenuItem()
    {
        $galleryId = $this->getFolderId();

        return $this->createMenuItem('Vložit galerii', 'icon-paste m5', array(
            'href' => $this->link('insertGalleryToContainer!', array('galleryId' => $galleryId)),
            'class' => 'ajax button'
        ));
    }

    /**
     * @return Html
     */
    private function _createInsertGalleryToTinyMenuItem()
    {
        $galleryId = $this->getFolderId();

        return $this->createMenuItem('Vložit galerii do tiny', 'icon-paste m5', array(
            'href' => $this->link('insertGalleryToTiny!', array('galleryId' => $galleryId)),
            'class' => 'ajax button'
        ));
    }

    /**
     * Returns button for inserting gallery to container or tiny
     * based on the media trigger value
     * @param string $mediaTrigger
     * @return Html
     */
    public function createInsertGalleryMenuItem($mediaTrigger)
    {
        $menuItem = Html::el();

        switch ($mediaTrigger) {
            case 'container':
                if ($this->parent->extName == 'mediaGallery') {
                    $menuItem = $this->_createInsertGalleryToContainerMenuItem();
                }
                break;
            case 'tiny':
                $menuItem = $this->_createInsertGalleryToTinyMenuItem();
                break;
        }

        return $menuItem;
    }

    /**
     * @return Html
     */
    private function _createInsertFileToContainerMenuItem()
    {
        $fileId = $this->getFileId();

        return $this->createMenuItem('Vložit', 'icon-paste m5', array(
            'href'          => $this->link('insertFileToContainer!', array('fileId' => $fileId)),
            'class'         => 'button ajax'
        ));
    }

    /**
     * @return Html
     */
    private function _createInsertFileToTinyMenuItem()
    {
        return $this->createMenuItem('Vložit do tiny', 'icon-paste m5', array(
            'href'          => '#',
            'class'         => 'button trigger-media-image-setting-form'
        ));
    }

    /**
     * Returns button for inserting file to container or tiny
     * based on the media trigger value
     * @param string $mediaTrigger
     * @return Html
     */
    public function createInsertFileMenuItem($mediaTrigger)
    {
        $menuItem = Html::el();

        switch ($mediaTrigger) {
            case 'container':
                if ($this->parent->extName == 'mediaFile') {
                    $menuItem = $this->_createInsertFileToContainerMenuItem();
                }
                break;
            case 'tiny':
                $menuItem = $this->_createInsertFileToTinyMenuItem();
                break;
        }

        return $menuItem;
    }

    /**
     * Parse action list
     * @param string $actionList
     * @return array
     */
    public function parseActionList($actionList)
    {
        return explode('|', $actionList);
    }

    /**
     * @param string $operationName
     * @param Html $menu
     * @param string $mediaTrigger
     */
    private function _addOperationToMenu($operationName, &$menu, $mediaTrigger)
    {

        switch ($operationName) {
            case 'newFolder':
                $menu->add($this->createNewFolderMenuItem());
                break;
            case 'newGallery':
                $menu->add($this->createNewGalleryMenuItem());
                break;
            case 'loadFiles':
                $menu->add($this->createLoadFilesMenuItem());
                break;
            case 'loadImages':
                $menu->add($this->createLoadImagesMenuItem());
                break;
            case 'insertGallery':
                $menu->add($this->createInsertGalleryMenuItem($mediaTrigger));
                break;
            case 'insertFile':
                $menu->add($this->createInsertFileMenuItem($mediaTrigger));
                break;
        }

    }

    /**
     * Returns composed Html object represeting the button menu
     * @param array $actionMap
     * @return Html
     */
    public function createButtonMenu($actionMap)
    {
        $menu = Html::el(NULL);
        $mediaTrigger = $this->getMediaTrigger();

        $section = $this->parent->getCurrentSection();
        $actions = $this->actions ?: 'default';


        if (isset($actionMap[$section][$actions])) {

            $actionList = $this->parseActionList($actionMap[$section][$actions]);

            foreach ($actionList as $action) {
                $this->_addOperationToMenu($action, $menu, $mediaTrigger);
            }

        }
        return $menu;
    }

    /**
     * Initialization of folder content storage
     */
    protected function initFolderContent()
    {
        $folderId = $this->getFolderId();
        $section = $this->parent->getCurrentSection();

        if (empty($this->folderContent['folders']) && empty($this->folderContent['files'])) {
	    $this->folderContent = $this->presenter->mediaManagerService->getFolderContent($folderId, $section);
        }
    }

    /**
     * Renders action buttons
     */
    public function renderActions()
    {
        $folderId = $this->getFolderId();

        $config = $this->parent->getConfig();
        $mediaTrigger = $this->getMediaTrigger();

        $actionMap = $config['buttonMenu'][$mediaTrigger];

        $template = parent::initTemplate(__DIR__ . '/templates/actionMenu.latte');
        $template->buttonMenu = $this->createButtonMenu($actionMap);

        $template->cid = $this->presenter->getParam('cid'); ///$media->cid;

        $template->galleryId = $folderId;
        $template->render();
    }


    /* Cutting folder items */

    /**
     * Handler for "cut folder item" action and inserting the item into paste bin
     * @param int $folderItemId
     * @param int $folderItemType
     */
    public function handleCutFolderItem($folderItemId, $folderItemType)
    {
        $item = array(
                    'folderItemId'      =>  $folderItemId,
                    'folderItemType'    =>  $folderItemType
        );

        $this['pasteBin']->add($this->parent->folderId, $this->getCurrentSection(), $item);
        $this->parent->invalidateControl();
    }

    /**
     * Handler for clearing the paste bin
     */
    public function handleEmptyPasteBin()
    {
        $this['pasteBin']->clean();
        $this->parent->invalidateControl();
    }

    /**
     * Handler for pasting the content of paste bin into actual folder
     */
    public function handlePasteFolderContent()
    {
        $pastebinContent = $this['pasteBin']->getContents();

        if (is_array($pastebinContent['items'])) {
            foreach ($pastebinContent['items'] as $item) {
                switch ($item['folderItemType']) {
                    case 'file':
                        // moving file
                        $sourceFileId = $item['folderItemId'];
                        $destinationFolderId = $this->parent->folderId;
                        $this->presenter->mediaManagerService->moveFile($sourceFileId, $destinationFolderId);
                        break;
                    case 'gallery':
                        // moving galleries
                        $sourceGalleryId = $item['folderItemId'];
                        $destinationFolderId = $this->parent->folderId;
                        $this->presenter->mediaManagerService->moveGallery($sourceGalleryId, $destinationFolderId);
                        break;
                }
            }
        }

        $this['pasteBin']->clean();
        $this->parent->invalidateControl();
    }

}
