<?php

namespace Bubo\Services;

use Bubo;

use Nette;
use Nette\Utils\Finder;

// TODO remove
use Model;

/**
 * Media manager
 */
class MediaManager extends BaseService {


    private $context;
    private $presenter;

    private $baseDir;
    private $basePath;

    private $fsModel;

    public function __construct($context, $initialDir = 'media') {
        $this->context = $context;

        $url = $context->getByType('Nette\Http\IRequest')->getUrl();
	$this->basePath = rtrim($url->getBasePath(), '/').'/'.$initialDir;
        $this->baseDir = $context->parameters['wwwDir'].'/'.$initialDir;

        $this->fsModel = new Model\MediaFSModel($this);
    }

    public function isPresenterSet() {
        return $this->presenter !== NULL;
    }

    public function setPresenter($presenter) {
        $this->presenter = $presenter;
    }

    public function getBasePath() {
        return $this->basePath;
    }

    public function getBaseDir() {
        return $this->baseDir;
    }

    public function getConfig() {
        return $this->presenter->configLoaderService->load(__DIR__.'/config/config.neon');
    }

    public function getTempPath() {
        return $this->basePath . '/temp';
    }

    public function getTempDir() {
        return $this->baseDir . '/temp';
    }

    public function getIconBasePath() {
        $url = $this->context->getByType('Nette\Http\IRequest')->getUrl();
        return rtrim($url->getBasePath(), '/') . '/vd/images/icons';
    }

    public function getIconBaseDir() {
        return $this->context->parameters['wwwDir'] . '/vd/images/icons';
    }


    public function createStructure() {
        return $this->fsModel->createStructure();
    }


    public function getFolderContent($folderId, $section) {
        $folderContent = array();

        switch ($section) {
            case 'galleries':
                    $folderContent = $this->getGalleryFolderContent($folderId);
                    break;
            case 'files':
                    $folderContent = $this->getFileFolderContent($folderId);
                    break;
        }

        return $folderContent;
    }

    public function getInternalImageFilename($filename, $type) {
        return $this->fsModel->getInternalImageFilename($filename, $type);
    }


    public function getFolder($folderId) {
        return $this->presenter->mediaModel->getFolder($folderId);
    }

    public function getFileFolderContent($folderId) {
        return $this->presenter->mediaModel->getFileFolderContent($folderId);
    }

    public function getGalleryFolderContent($folderId) {
        return $this->presenter->mediaModel->getGalleryFolderContent($folderId);
    }

    public function getBreadcrumbs($folderId, $fileId) {
        return $this->presenter->mediaModel->getBreadcrumbs($folderId, $fileId);
    }

    public function getFolderContentItem($type, $folderId) {
        return $this->presenter->mediaModel->getFolderContentItem($type, $folderId);
    }

    public function createFolder($data) {
        $folderDir = $this->presenter->mediaModel->createFolder($data);
        return $this->fsModel->createFolder($folderDir);
    }

    public function createGallery($data) {
        $galleryDir = $this->presenter->mediaModel->createGallery($data);
        return $this->fsModel->createGallery($galleryDir);
    }

    public function deleteFolder($folderId, $section) {
        $folderTree = $this->presenter->mediaModel->getFolderTree($folderId);

        $this->_deleteTree($folderTree, $section);
    }


    private function _deleteTree($folderNode, $section) {

        if (!$folderNode) return NULL;

        $folderPath = $folderNode['path'];
        $folderId = $folderNode['folderId'];

        if ($folderNode['content']) {
            foreach ($folderNode['content'] as $folderItem) {

                switch ($folderItem['type']) {
                    case 'file':
                            $this->deleteFile($folderItem['fileId'], $section);
                            break;
                    case 'gallery':
                            $this->deleteGallery($folderItem['folderId'], $section);
                            break;
                    case 'folder':
                            $this->_deleteTree($folderItem, $section);
                            break;
                }

            }


        }

        $this->fsModel->deleteFolder($folderPath);
        $this->presenter->mediaModel->deleteFolder($folderId);


    }



    public function deleteGallery($galleryId) {
        $folder = $this->presenter->mediaModel->getFolder($galleryId);
        $folderContent = $this->presenter->mediaModel->getFileFolderContent($galleryId);

        // remove images
        $this->fsModel->deleteImages($folderContent['files'], $folder['path']);
        $this->presenter->mediaModel->deleteImages($galleryId);

        // remove tiny placeholders
        $config = $this->getConfig();
        $this->fsModel->deleteTinyPlaceholder($galleryId, $config['tinyPlaceholderPattern']);

        // remove folder
        return $this->deleteFolder($galleryId, 'galleries');
    }

    public function getFile($fileId) {
        return $this->presenter->mediaModel->getFile($fileId);
    }


    /**
     * Returns the location of the original file
     *
     * @param type $fileId
     *
     */
    public function getOriginalFileDirPath($fileId) {
        $originalFileDirPath = NULL;

        $file = $this->getFile($fileId);

        if ($file) {
            $section = $this->getFileSection($fileId);

            switch ($section) {
                case 'files':
                    $originalFileDirPath = $this->presenter->mediaModel->getFolderDir($file['folder_id']);
                    break;
                case 'galleries':
                    $originalFileDirPath = $this->presenter->mediaModel->getGalleryDir($file['folder_id'], 'originals');
                    break;
            }

            $originalFileDirPath = $originalFileDirPath . '/' . $file['filename'];
        }

        return $originalFileDirPath;
    }

    public function getGalleryThumb($galleryId) {
        return $this->presenter->mediaModel->getGalleryThumb($galleryId);
    }

    public function deleteFile($fileId, $section) {
        $file = $this->presenter->mediaModel->getFile($fileId);

        $folderPath = $file['folderpath'] ?: $section . '/root';

        if ($section == 'galleries') {
            $this->fsModel->deleteImage($file, $folderPath);
        } else {
            $this->fsModel->deleteFile($file, $folderPath);
        }

        return $this->presenter->mediaModel->deleteFile($fileId);
    }

    public function addFiles($data, $section) {
        //dump($data);
        $fsData = $this->presenter->mediaModel->addFiles($data);

        $folderPath = NULL;
        if ($data['folderId']) {
            $folder = $this->presenter->mediaModel->getFolder($data['folderId']);
            $folderPath = $folder['path'];
        } else {
            // files added to root folder
            $folderPath = $section . '/root';
        }

        return $this->fsModel->addFiles($fsData, $folderPath);
    }

    public function addImages($data) {
//        dump($data);
//        die();
        $fsData = $this->presenter->mediaModel->addFiles($data, TRUE);

        $folderPath = NULL;

        // get gallery basepath
        if ($data['folderId']) {
            $folder = $this->presenter->mediaModel->getFolder($data['folderId']);
            $folderPath = $folder['path'];
        } else {
            throw new \Exception('Gallery not found');
        }

        return $this->fsModel->addImages($fsData, $folderPath);

    }

    public function getParentFolderId($folderId) {
        return $this->presenter->mediaModel->getParentFolderId($folderId);
    }

    public function renameFolder($newName, $folderId) {
        return $this->presenter->mediaModel->renameFolder($newName, $folderId);
    }

    public function renameFile($newName, $fileId) {
        return $this->presenter->mediaModel->renameFile($newName, $fileId);
    }

    public function saveImageTitles($titles, $fileId) {

        $this->presenter->mediaModel->saveImageTitles($titles, $fileId);

    }

    public function sortImages($data) {
        $this->presenter->mediaModel->sortImages($data);
    }


    public function _createImagePaths($folderPath, $modes, $filename) {

        $that = $this;

        return array_map(function($mode) use ($that, $folderPath, $filename) {
            return $folderPath . '/' . $mode . '/' . $filename;
        }, $modes);

    }

    public function loadImages($galleryId, $mode) {
        $folder = $this->getFolder($galleryId);
        $galleryContent = $this->getGalleryFolderContent($galleryId);

        $this->fsModel->loadImages($folder['path'], $galleryContent['files'], $mode);

        $modes = $this->fsModel->parseModes($mode);
        array_unshift($modes, 'originals');
//        dump($modes);
//        die();
        $that = $this;
        $p = $this->presenter;
        $images = array_map(function($file) use ($that, $folder, $modes, $p) {
                        $a = (array) $file;

                        $mediaImage = new Bubo\Media\TemplateContainers\MediaImage($file, $p);
                        $paths = $that->_createImagePaths($folder['path'], $modes, $file['filename']);
                        $mediaImage->setPaths($paths);
//                        dump((array) $file);
//                        die();
                        return $mediaImage;
        }, $galleryContent['files']);


        return $images;
    }


    public function loadFile($fileId, $mode = NULL) {
        $p = $this->presenter;
        $file = $this->getFile($fileId);
        $mediaFile = new Bubo\Media\TemplateContainers\MediaFile($file, $p);

        $paths = array();

        // detect section -> file from files or from some gallery
        if ($file['folder_id'] === NULL) {
            // from files
            $paths = $this->fsModel->loadFileFromFiles('files/root', $file, $mode);
        } else {
            $folder = $this->getFolder($file['folder_id']);

            switch ($folder['section']) {
                case 'files':
                    // file is from files
                    $paths = $this->fsModel->loadFileFromFiles($folder['path'], $file, $mode);
                    break;
                case 'galleries':
                    // file is from galleries
                    $paths = $this->fsModel->loadFileFromGalleries($folder['path'], $file, $mode);
                    break;
            }

        }

        $mediaFile->setPaths($paths);

        return $mediaFile;
    }


    public function getFileIconSrc($fileFolderItem, $type = 'list') {

        //dump($fileFolderItem);




        $fileIconSrc = $this->getIconBasePath() . '/default.png';

        if ($fileFolderItem['is_image']) {

            $internalFileName = $this->getInternalImageFilename($fileFolderItem['filename'], $type);
            $tempFilename = $this->getTempDir() . '/' . $internalFileName;

            if (is_file($tempFilename)) {
                $tempPath = $this->getTempPath() . '/' . $internalFileName;
            } else {
                $tempPath = $this->getIconBasePath() . '/default.png';
            }

            $fileIconSrc = $tempPath;
        } else {

            $ext = NULL;

            if (preg_match('#.+\.([[:alnum:]]+)#', $fileFolderItem['filename'], $matches)) {
                $ext = $matches[1];

                foreach (Finder::findFiles($ext.'.*')->in($this->getIconBaseDir()) as $_key => $_file) {
//                    echo $key; // $key je řetězec s názvem souboru včetně cesty
//                    die();
                    $fileIconSrc = $this->getIconBasePath() . '/' . $_file->getFilename();
                    break;
                }

            }
        }

        return $fileIconSrc;
    }

    private function _getGalleryIcon($galleryId, $type = 'src') {

        $iconSrc = NULL;
        $imageFilepath = NULL;

        $filename = $this->getGalleryThumb($galleryId);

        if ($filename) {
            $image = $this->getInternalImageFilename($filename, 'list');
            if ($image) {
                $imageFilepath = $this->getTempDir() . '/' . $image;
                if (is_file($imageFilepath)) {
                    $iconSrc = $this->getTempPath() . '/' . $image;
                }
            }
        }

        if ($iconSrc == NULL) {
            $iconSrc = $this->getIconBasePath() . '/jpg.png';
            $imageFilepath = $this->getIconBaseDir() . '/jpg.png';
        }

        return $type == 'src' ? $iconSrc : $imageFilepath;

    }

    public function getGalleryIconPath($galleryId) {
        return $this->_getGalleryIcon($galleryId, 'filePath');
    }

    public function getGalleryIconSrc($galleryId) {
        return $this->_getGalleryIcon($galleryId, 'src');
    }

    private function _createTinyPlaceholderForGallery($galleryId, $fontFile, $fontSize) {

        $config = $this->getConfig();
        $pattern = $config['tinyPlaceholderPattern'];
        $placeholderFilename = sprintf($pattern, $galleryId);

        $placeholderFilePath = $this->getTempDir() . '/' . $placeholderFilename;

        if (!is_file($placeholderFilePath)) {
            // create placeholder
            $folder = $this->getFolder($galleryId);
            $filename = $this->getGalleryIconPath($galleryId);

            $image = Nette\Image::fromFile($filename);
            $blank = Nette\Image::fromBlank(350, 100, Nette\Image::rgb(220, 220, 220));
            $blank->place($image, 8, 8);


            $rgb = $blank->rgb(0,0,0);
            $imageResource = $blank->getImageResource();
            $color = imagecolorallocatealpha($imageResource, $rgb['red'], $rgb['green'], $rgb['blue'], $rgb['alpha']);
            $x = 100;
            $y = 8 + $fontSize;
            $text = 'Galerie';
            imagettftext($imageResource, $fontSize, 0, $x , $y, $color, $fontFile, $text);

            $x = 100;
            $y = 8 + 3*$fontSize;
            $text = $folder['name'];
            imagettftext($imageResource, $fontSize, 0, $x , $y, $color, $fontFile, $text);

            $resultImage = new Nette\Image($imageResource);
            $resultImage->save($placeholderFilePath);
        }

        return $this->getTempPath() . '/' . $placeholderFilename;
    }

    public function getTinyPlaceholderImageSrc($galleryId) {
        $contextParams = $this->presenter->context->parameters;
        $fontFile = $contextParams['wwwDir'] . '/fonts/arial.ttf';
        return $this->_createTinyPlaceholderForGallery($galleryId, $fontFile, 12);
    }

    public function getLabelExtNameByIdentifier($identifier) {
        return $this->presenter->mediaModel->getLabelExtNameByIdentifier($identifier);
    }

    public function createModChunk($params) {
        return $this->fsModel->createModchunk($params);
    }

    public function getFileSection($fileId) {
        return $this->presenter->mediaModel->getFileSection($fileId);
    }

    public function getGalleryRestoreParams($galleryId, $parentFolder, $snippetMode = TRUE) {
        return array(
                    'media-section'  =>  'galleries',
                    'media-folderId' => $parentFolder,
                    'media-content-contentItem-gallery_'.$galleryId.'-galleryId'    =>  $galleryId,
                    'media-content-contentItem-gallery_'.$galleryId.'-snippetMode'  =>  $snippetMode,
                    'do'    =>  'media-content-contentItem-gallery_'.$galleryId.'-enterGallery'
        );
    }


    public function getGalleryRestoreUrl($galleryId, $parentFolder, $snippetMode = TRUE) {
        $params = $this->getGalleryRestoreParams($galleryId, $parentFolder, $snippetMode);
        return $this->presenter->link('Tiny:default', $params);
    }


    public function getFileRestoreParams($folderId, $fileId, $section, $snippetMode = TRUE, $formValues = NULL) {

        $params = array(
                    'media-section'  =>  $section,
                    'media-folderId' => $folderId,
                    'media-content-contentItem-file_'.$fileId.'-fileId'    =>  $fileId,
                    'media-content-contentItem-file_'.$fileId.'-snippetMode'  =>  $snippetMode,
                    'do'    =>  'media-content-contentItem-file_'.$fileId.'-openFile'
        );

        if ($formValues !== NULL) {
            $params['media-formValues'] = json_encode((array) $formValues);
        }

        return $params;
    }

    public function getFileRestoreUrl($folderId, $fileId, $section, $snippetMode = TRUE, $formValues = NULL) {
        $params = $this->getFileRestoreParams($folderId, $fileId, $section, $snippetMode, $formValues);
        return $this->presenter->link('Tiny:default', $params);
    }

    public function fileExists($fileId) {
        return $this->presenter->mediaModel->fileExists($fileId);
    }

    public function folderExists($folderId) {
        return $this->presenter->mediaModel->folderExists($folderId);
    }

    public function toggleSelectFile($fileId, $select) {
        return $this->presenter->mediaModel->toggleSelectFile($fileId, $select);
    }

    public function getFolderDir($folderId, $section = 'files') {
        return $this->presenter->mediaModel->getFolderDir($folderId, $section);
    }

    public function getGalleryDir($galleryId) {
        return $this->presenter->mediaModel->getGalleryDir($galleryId);
    }


    /**
     * Moves file to folder
     * Retuns true if the operation is successfull
     * @param int $sourceFileId
     * @param int $destinationFolderId
     * @return bool
     */
    public function moveFile($sourceFileId, $destinationFolderId) {
        $success = $this->fsModel->moveFile($sourceFileId, $destinationFolderId);
        if ($success) {
            $this->presenter->mediaModel->moveFile($sourceFileId, $destinationFolderId);
        }

        return $success;
    }


    /**
     * Moves gallery to folder
     * @param int $sourceGalleryId
     * @param int $destinationFolderId
     */
    public function moveGallery($sourceGalleryId, $destinationFolderId)
    {
        $newPath = $this->fsModel->moveGallery($sourceGalleryId, $destinationFolderId);

        $data = array(
                    'parent_folder' =>  $destinationFolderId,
                    'path'          =>  $newPath
        );

        $this->presenter->mediaModel->moveGallery($sourceGalleryId, $data);
    }

}