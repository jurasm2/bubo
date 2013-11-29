<?php

namespace Model;

use Nette, Bubo;

final class MediaModel extends BaseModel {


    private function _getContentItems($dbTable, $where, $mappingCallback) {

        $items = $this->connection->fetchAll('SELECT * FROM %n WHERE %and', $dbTable, $where);

    }

    public function mapFolder($folder) {
        return array(
                'type'  =>  Bubo\Media::FOLDER,
                'name'  =>  $folder['name']
       );
    }

    public function mapFile($file) {
        return array(
                'type'  =>  Bubo\Media::FILE,
                'name'  =>  $file['name']
        );
    }

    /**
     * Returns folder content in specific array format
     *
     * folder item looks as follows:
     *
     * $folderItem = array(
     *                  'id'    => item id (folder or file or ...)
     *                  'type'  => folder / file / gallery
     *                  'name'  => name
     *                  'mime'  => cannot be provided
     *                  'thumb' => cannot be provided
     *              )
     *
     * @param int $folderId
     * @param string|null $section
     * @return array
     */
    private function _getFolderContent($folderId, $section = NULL)
    {

        $folderWhere = array(
                    'parent_folder%iN'  => $folderId
        );

        if ($section !== NULL) {
            $folderWhere['section%s'] = $section;
        }

        $fileWhere = array(
                    'folder_id%iN'  => $folderId
        );


        $folders = $this->connection->query('SELECT *,[folder_id] as [id] FROM [:media:folders] WHERE %and ORDER BY FIELD([type], \'folder\', \'gallery\')', $folderWhere)->fetchAssoc('folder_id');
        $files = $this->connection->query("SELECT *,[file_id] as [id], 'file' as [type] FROM [:media:files] WHERE %and ORDER BY [sortorder] ASC", $fileWhere)->fetchAssoc('file_id');

        return array(
                'folders'   =>  $folders,
                'files'     =>  $section == 'galleries' ? ($folderId ? $files : array()) : $files
        );

    }

    public function getGalleryFolderContent($folderId = NULL) {
        return $this->_getFolderContent($folderId, 'galleries');
    }

    public function getFileFolderContent($folderId) {
        return $this->_getFolderContent($folderId, 'files');
    }

    public function getFolder($folderId) {
        return $this->connection->fetch('SELECT *, [folder_id] as [id] FROM [:media:folders] WHERE [folder_id] = %i', $folderId);
    }

    public function getFile($fileId) {
        return $this->connection->fetch('SELECT
                                                [fi].*,
                                                [fi].[file_id] as [id],
                                                [fo].[path] as [folderpath]
                                            FROM
                                                [:media:files] [fi]
                                            LEFT JOIN
                                                [:media:folders] [fo]
                                            USING
                                                ([folder_id])
                                            WHERE
                                                [file_id] = %i
                                            ', $fileId);
    }

    public function getBreadcrumbs($folderId, $fileId) {

        $tree = array();

        if ($fileId !== NULL) {
            $file = $this->getFile($fileId);
            if ($file) {
                $file['type'] = 'file';
                $tree[] = $file;
            }
        }

        $fid = $folderId;

        for ($i = 1; $i <= 50; $i++) {
            $folder = $this->getFolder($fid);

            if ($folder) {
                $tree[] = $folder;
                $fid = $folder['parent_folder'];
            } else {
                break;
            }
        }


        return $tree;
    }


    /**
     * Returns the relative path to the folder
     * in the 'files' section
     *
     * @param type $folderId
     */
    public function getFolderDir($folderId, $section = 'files') {
        $folderDir = $section;
        if ($folderId === NULL) {
            $folderDir = $folderDir . '/root';
        } else {
            $folder = $this->getFolder($folderId);
            $folderDir = $folder['path'];
        }

        return $folderDir;
    }

    /**
     * Returns the relative path to the gallery
     * in the 'galleries' section.
     *
     * @param type $galleryId
     * @param type $subdir - any subdir in the gallery folder (e.g. originals)
     */
    public function getGalleryDir($galleryId, $subdir = NULL) {
        $folder = $this->getFolder($galleryId);
        return $subdir === NULL ? $folder['path'] : ($folder['path'] . '/' . $subdir);
    }


    private function _createFolderDirName($folderName, $folderId, $sectionName) {
        //$data['folderName']
        return $sectionName . '/' . Nette\Utils\Strings::webalize($folderName) . '_' . $folderId;
    }

    private function _createGalleryDirName($galleryName, $folderId, $sectionName, $galleryId) {
        //$data['folderName']

        $galleryDir = '';

        if ($folderId) {
            $folder = $this->getFolder($folderId);
            $folderDir = $folder['path'];
        } else {
            $folderDir = $sectionName . '/root';
        }

        $galleryDir = $folderDir . '/' . Nette\Utils\Strings::webalize($galleryName) . '_' . $galleryId;

        return $galleryDir;
    }

    private function _createFolder($data, $type) {
        $dbData = array(
                        'parent_folder%iN'  =>  $data['parentFolderId'],
                        'name%s'            =>  $data['folderName'],
                        'nicename%s'        =>  Nette\Utils\Strings::webalize($data['folderName']),
                        'section%s'         =>  $data['currentSection'],
                        'type%s'            =>  $type
        );

        $this->connection->query('INSERT INTO [:media:folders]', $dbData);
        $folderId = $this->connection->getInsertId();
        $path = $type == Bubo\Media::FOLDER ?
                                $this->_createFolderDirName($data['folderName'], $folderId, $data['currentSection']) :
                                $this->_createGalleryDirName($data['folderName'], $data['parentFolderId'], $data['currentSection'], $folderId);
        $this->connection->query('UPDATE [:media:folders] SET [path] = %s WHERE [folder_id] = %i', $path, $folderId);
        return $path;
    }


    public function createFolder($data) {
        return $this->_createFolder($data, Bubo\Media::FOLDER);
    }

    public function createGallery($data) {
        $folder = $this->_createFolder($data, Bubo\Media::GALLERY);
//        dump($folder);
//        die();
        return $folder;
    }

    private function _createFilename($name, $fileId) {

        $filename = NULL;

        if (preg_match('#(.*)\.(.+)#', $name, $matches)) {
            $filename = $matches[1] . '_' . $fileId . '.' . $matches[2];
        } else {
            $filename = $name . '_' . $fileId;
        }

        return $filename;
    }

    public function addFiles($data, $filterImages = FALSE) {

        $fsData = array();

        if ($data['upload']) {

            foreach ($data['upload'] as $fileUpload) {

                if (!$filterImages || ($filterImages && $fileUpload->isImage())) {

                    $dbData = array(
                                'folder_id%iN'  =>  $data['folderId'],
                                'name%s'        =>  $fileUpload->name,
                                'is_image'      =>  $fileUpload->isImage(),
                                'size%i'        =>  $fileUpload->getSize()

                    );

                    $this->connection->query('INSERT INTO [:media:files]', $dbData);

                    $fileId = $this->connection->getInsertId();
                    $filename = $this->_createFilename($fileUpload->getSanitizedName(), $fileId);

                    $updateData = array(
                                    'filename'  =>  $filename,
                                    'sortorder' =>  $fileId
                    );

                    $this->connection->query('UPDATE [:media:files] SET', $updateData ,' WHERE [file_id] = %i', $fileId);

                    $fsData[] = array(
                                    'file'      =>  $fileUpload,
                                    'filename'  =>  $filename
                    );

                }
            }

        }

        return $fsData;

//        $dbData = array_map(function($file) use ($data) {
//
//            return array(
//                        'folder_id%iN'   =>  $data['folderId'],
//                        'name%s'         =>  $file->name
//            );
//
//        },$data['upload']);
//
//        return $dbData ? $this->connection->query('INSERT INTO [:media:files] %ex', $dbData) : array();
    }


    public function getParentFolderId($folderId) {
        return $this->connection->fetchSingle('SELECT [parent_folder] FROM [:media:folders] WHERE [folder_id] = %i', $folderId);
    }

    public function deleteFolder($folderId) {
        return $this->connection->query('DELETE FROM [:media:folders] WHERE [folder_id] = %i', $folderId);
    }

    public function deleteFile($fileId) {
        return $this->connection->query('DELETE FROM [:media:files] WHERE [file_id] = %i', $fileId);
    }

    public function deleteImages($galleryId) {
        return $this->connection->query('DELETE FROM [:media:files] WHERE [folder_id] = %i', $galleryId);
    }

    public function getFolderContentItem($type, $id) {
        $resultSet = NULL;

        switch ($type) {
            case 'files':
                $resultSet = $this->connection->query('SELECT * FROM [:media:files] WHERE [file_id] = %i', $id);
                break;
            case 'folders':
                $resultSet = $this->connection->query('SELECT * FROM [:media:folders] WHERE [folder_id] = %i', $id);
                break;
        }

        return $resultSet === NULL ? NULL : $resultSet->fetch();
    }

    public function renameFolder($newName, $folderId) {

        $data = array(
                    'name'      =>  $newName,
                    'nicename'  => Nette\Utils\Strings::webalize($newName)
        );

        return $this->connection->query('UPDATE [:media:folders] SET', $data, 'WHERE [folder_id] = %i', $folderId);
    }

    public function renameFile($newName, $fileId) {
        $data = array(
                    'name'      =>  $newName
        );

        return $this->connection->query('UPDATE [:media:files] SET', $data, 'WHERE [file_id] = %i', $fileId);
    }

    public function saveImageTitles($titles, $fileId) {

        $data = array(
                'ext'   =>  serialize($titles)
        );

        return $this->connection->query('UPDATE [:media:files] SET', $data, 'WHERE [file_id] = %i', $fileId);

//        dump($titles);
//        die();
    }

    public function getGalleryThumb($folderId) {
        return $this->connection->fetchSingle('SELECT [filename] FROM [:media:files] WHERE [folder_id] = %i ORDER BY [sortorder] ASC LIMIT 1', $folderId);
    }


    /**
     * Folder tree has following structure
     *
     * $tree = array(
     *              type => folder
     *              path => <path>
     *              content => array(
     *                              0 => array(
     *                                      type => file
     *                                      path => filename
     *                                      )
     *                              1 => array(
     *                                      type => gallery
     *                                      path => foldername
     *                                      )
     *                              )
     *                              2 => array(
     *                                      type => folder
     *                                      path => folder
     *                                      content => NULL
     *                                      )
     *                              )
     *              );
     *
     */
    public function getFolderTree($folderId) {

        $folder = $this->getFolder($folderId);

        $tree = array(
                    'type'      =>  'folder',
                    'folderId'  =>  $folderId,
                    'path'      =>  $folder['path'],
                    'content'   =>  $this->_getFolderNode($folderId)
        );

        return $tree;
    }


    private function _isFolderContentEmpty($folderContent) {
        return empty($folderContent['files']) && empty($folderContent['folders']);
    }

    private function _getFolderNode($folderId) {

        $content = array();

        $folderContent = $this->_getFolderContent($folderId);

        if ($this->_isFolderContentEmpty($folderContent)) {
            return NULL;
        }

        foreach ($folderContent['files'] as $_fileId => $_file) {
            $content[] = array(
                            'type'      =>  'file',
                            'fileId'    =>  $_fileId,
                            'path'      =>  $_file['filename']
            );
        }

        foreach ($folderContent['folders'] as $_folderId => $_folder) {

            switch ($_folder['type']) {
                case 'folder':
                        $content[] = array(
                                        'type'      =>  $_folder['type'],
                                        'folderId'  =>  $_folderId,
                                        'path'      =>  $_folder['path'],
                                        'content'   =>  $this->_getFolderNode($_folderId)
                        );
                    break;
                case 'gallery':
                        $content[] = array(
                                        'type'      =>  $_folder['type'],
                                        'folderId'  =>  $_folderId,
                                        'path'      =>  $_folder['path']
                        );
                    break;
            }

        }

        return $content;

    }

    public function sortImages($data) {

        if ($data) {
            foreach ($data as $sortorder => $imageId) {
                $this->connection->query('UPDATE [:media:files] SET [sortorder] = %i WHERE [file_id] = %i', $sortorder, $imageId);
            }
        }

    }


    public function getLabelExtNameByIdentifier($identifier) {
        return $this->connection->fetchSingle('SELECT [name] FROM [:core:label_ext_definitions] WHERE [identifier] = %s', $identifier);
    }

    public function getFileSection($fileId) {
        $file = $this->getFile($fileId);

        $section = 'files';

        if ($file['folder_id'] !== NULL) {
            $folder = $this->getFolder($file['folder_id']);
            $section = $folder['section'];
        }

        return $section;
    }

    public function fileExists($fileId) {
        return $this->connection->fetchSingle('SELECT COUNT(*) FROM [:media:files] WHERE [file_id] = %i', $fileId);
    }

    public function folderExists($folderId) {
        return $this->connection->fetchSingle('SELECT COUNT(*) FROM [:media:folders] WHERE [folder_id] = %i', $folderId);
    }

    public function toggleSelectFile($fileId, $select) {
        return $this->connection->query('UPDATE [:media:files] SET [selected] = %i WHERE [file_id] = %i', $select, $fileId);
    }

    public function moveFile($sourceFileId, $destinationFolderId) {
        return $this->connection->query('UPDATE [:media:files] SET [folder_id] = %iN WHERE [file_id] = %i', $destinationFolderId, $sourceFileId);
    }

    public function moveGallery($sourceGalleryId, $data) {
        return $this->connection->query('UPDATE [:media:folders] SET', $data,'WHERE [folder_id] = %i', $sourceGalleryId);
    }
}