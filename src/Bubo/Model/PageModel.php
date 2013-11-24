<?php

namespace Model;

final class PageModel extends BaseModel {

    const SINGLE_PAGE       =                       '=,labels,=,ext_identifier';
    const PARENT_PAGES      =   'parent,tree_node_id,=,labels,=,ext_identifier';
    const TREENODE_PAGE     =          'tree_node_id,=,labels,=,ext_identifier';
    const MULTILANG_PAGE    = 'lang,=,time_zone,status,labels,=,ext_identifier';



    private function _getLinksSqlQuery($attributes, $tables, $where) {
        return $this->connection->translate('SELECT
                                                1 as [is_link],
                                                [:core:page_tree].[pattern],
                                                NULL as [referenced_by],
                                                [:core:page_tree].[parent],
                                                [:core:page_tree].[sortorder],
                                                [:core:page_tree].[layout],
                                                [:core:page_tree].[tree_node_id],
                                                [:core:page_tree].[node_created],
                                                [:core:page_tree].[module],
                                                [:core:pages].[version],
                                                [:core:pages].[page_id],
                                                [:core:pages].[entity],
                                                [:core:pages].[lang],
                                                [:core:pages].[status],
                                                [:core:pages].[name],
                                                [:core:pages].[url_name],
                                                [:core:pages].[created],
                                                [:core:pages_labels].[label_id] as [labels],
                                                [:core:pages_labels].[active],
                                                [:core:urls].[url],
                                                [:core:extended_values].[identifier] as [ext_identifier],
                                                [:core:extended_values].[value] as [ext_value],
                                                ROUND(
                                                    (IF ([:core:pages].[start_public] IS NULL, -1, (([:core:pages].[start_public] > NOW())* 2) -1) +
                                                     IF ([:core:pages].[stop_public] IS NULL, 1, (([:core:pages].[stop_public] > NOW()) * 2) -1)) / 2
                                                ) as [time_zone]

                                                    %sql /* (optional) sql chunk containing list of custom properties from entity config file */

                                                FROM
                                                    [:core:page_tree]
                                                JOIN
                                                    [:core:pages]
                                                ON
                                                    [:core:pages].[tree_node_id] = [:core:page_tree].[pattern]
                                                LEFT JOIN
                                                    [:core:urls]
                                                ON
                                                    [:core:urls].[page_id_] = [:core:pages].[page_id]

                                                    %sql /* (optional) sql chunk containing list of tables for custom properties */

                                                LEFT JOIN
                                                    [:core:pages_labels]
                                                ON
                                                    [:core:pages_labels].[tree_node_id] = [:core:page_tree].[tree_node_id]
                                                    AND
                                                    [:core:pages_labels].[lang] = [:core:pages].[lang]
                                                LEFT JOIN
                                                    [:core:extended_values]
                                                USING
                                                    ([page_id])
                                                WHERE
                                                    %and'
                                               , $attributes, $tables, $where);
    }

    private function _getPagesSqlQuery($attributes, $tables, $where) {
        return $this->connection->translate('SELECT
                                            0 as [is_link],
                                            NULL as [pattern],
                                            [referencing_page_tree].[pattern_module] as [referenced_by],
                                            [:core:page_tree].[parent],
                                            [:core:page_tree].[sortorder],
                                            [:core:page_tree].[layout],
                                            [:core:page_tree].[tree_node_id],
                                            [:core:page_tree].[node_created],
                                            [:core:page_tree].[module],
                                            [:core:pages].[version],
                                            [:core:pages].[page_id],
                                            [:core:pages].[entity],
                                            [:core:pages].[lang],
                                            [:core:pages].[status],
                                            [:core:pages].[name],
                                            [:core:pages].[url_name],
                                            [:core:pages].[created],
                                            [:core:pages_labels].[label_id] as [labels],
                                            [:core:pages_labels].[active],
                                            [:core:urls].[url],
                                            [:core:extended_values].[identifier] as [ext_identifier],
                                            [:core:extended_values].[value] as [ext_value],
                                            ROUND(
                                                (IF ([:core:pages].[start_public] IS NULL, -1, (([:core:pages].[start_public] > NOW())* 2) -1) +
                                                 IF ([:core:pages].[stop_public] IS NULL, 1, (([:core:pages].[stop_public] > NOW()) * 2) -1)) / 2
                                            ) as [time_zone]

                                                %sql /* (optional) sql chunk containing list of custom properties from entity config file */

                                            FROM
                                                [:core:page_tree]
                                            JOIN
                                                [:core:pages]
                                            USING
                                                ([tree_node_id])
                                            LEFT JOIN
                                                [:core:urls]
                                            ON
                                                [:core:urls].[page_id_] = [:core:pages].[page_id]

                                                %sql /* (optional) sql chunk containing list of tables for custom properties */

                                            LEFT JOIN
                                                [:core:pages_labels]
                                            USING
                                                ([tree_node_id],[lang])
                                            LEFT JOIN
                                                [:core:extended_values]
                                            USING
                                                ([page_id])
                                            LEFT JOIN
                                                [:core:page_tree] [referencing_page_tree]
                                            ON
                                                [:core:page_tree].[tree_node_id] = [referencing_page_tree].[pattern]
                                            WHERE
                                                %and'
                                        ,$attributes, $tables, $where);
    }


    /**
     * Lower layer for obtaining pages.
     * Provides sql query for pages.
     *
     * Input array contains following keys
     *  - lang                  string
     *  - states                array | NULL
     *  - labelId               int | NULL
     *  - orderDirection        string | NULL
     *  - limit                 int | NULL
     *  - searchAllTimeZones    bool
     *  - ghostPriority         array
     *  - treeNodeId            int
     *  - sqlChunks             array
     *  - mode                  string (getPage, getPages, getDefaults, getLabelRoots)
     *
     * @param type $params
     * @return type
     */
    private function _queryPages($params, $test = FALSE) {
        $allParams = array(
                        'lang'                  => NULL,
                        'states'                => NULL,
                        'labelId'               => NULL,
                        'orderDirection'        => NULL,
                        'limit'                 => NULL,
                        'searchAllTimeZones'    => FALSE,
                        'ghostPriority'         => array(),
                        'treeNodeId'            => NULL,
                        'sqlChunks'             => array('attributes' => '', 'tables' => ''),
                        'mode'                  => 'getDescendants'

        );

//        dump($params);
//        throw new \Exception;

        $mergedParams = array_merge($allParams, $params);
//        dump($mergedParams);
        extract($mergedParams);

//        dump($module);
//        die();

//        if ($labelId == 3) {
//            dump($mode);
////            die();
//        }


        // $allLangs are injected when page multilang is called
        if (!isset($allLangs)) {

            $allLangs = array_keys($params['presenter']->langManagerService->getLangs());
        }

        //$allLangs[] = 'uk';
        //dump($allLangs);

        if (empty($allLangs)) {
            throw new \Nette\InvalidArgumentException('$allLangs cannot be empty');
        }

        if ($mode != 'getDefaults' && $lang === NULL) {
            throw new \Nette\InvalidArgumentException('$lang was not provided when querying pages');
        }



        if (is_array($ghostPriority)) {
            $ghostPriority = implode(',', $ghostPriority);
        }

        $where = array();

        // get all pages, only descendants of $treeNodeId or single page?
        switch ($mode) {
            case 'getDescendants':
                    $where[] = array('[:core:page_tree].[parent] = %i', $treeNodeId);
                    break;
            case 'getPages':
                    if ($treeNodeId !== NULL) {
                        $where[] = array('[:core:page_tree].[tree_node_id] IN %in', (array) $treeNodeId);
                    }
                    break;
            case 'getPage':
                    // quering page
                    // $treeNodeId must not be NULL
                    if ($treeNodeId === NULL) {
                        throw new \Nette\InvalidArgumentException('$treeNodeId was not provided when querying single page');
                    } else {
                        $where[] = array('[:core:page_tree].[tree_node_id] = %i', $treeNodeId);
                    }
                break;
            case 'getDefaults':
                    // quering page
                    // $treeNodeId must not be NULL
                    if ($treeNodeId === NULL) {
                        throw new \Nette\InvalidArgumentException('$treeNodeId was not provided when querying default pages');
                    } else {
                        $where[] = array('[:core:page_tree].[tree_node_id] = %i', $treeNodeId);
                    }
                    break;
            case 'getLabelRoots':
                    if ($labelId === NULL) {
                        throw new \Nette\InvalidArgumentException('$labelId was not provided when querying label roots');
                    }
                    //dump($labelId);
                    $where[] = array('[:core:pages_labels].[label_id] = %i', $labelId);
                    $where[] = array('[:core:pages_labels].[active] = %s', 'yes');
                    break;

        }

        // when ghosts are enabled (empty), lang query must be ommited
        if (empty($ghostPriority) && ($mode != 'getDefaults')) {
            $where[] = array('[:core:pages].[lang] = %s', $lang);
        }

        // any specific states to return?
        if ($states !== NULL) {
            $where[] = array('[:core:pages].[status] IN %in', (array) $states);
        }

//        throw new \Exception;
//        dump($where);
//        die();

        // when $searchAllTimeZones is FALSE, add query for PRESENT time zone
        if (!$searchAllTimeZones) {
            $where[] = '([:core:pages].[start_public] IS NULL
                              OR
                         [:core:pages].[start_public] <= NOW() )';
            $where[] = '( [:core:pages].[stop_public] IS NULL
                              OR
                         [:core:pages].[stop_public] >= NOW() )';
        }

        // always add restriction to only active languages
        $where[] = array('[:core:pages].[lang] IN %in', $allLangs);

        //$where[] = array('[:core:page_tree].[pattern] IS NOT NULL');

        if (!$module) {
            throw new \InvalidArgumentException('Module is not set');
        }

        $where[] = array('[:core:page_tree].[module] = %s', $module);

         $pagesSql = $this->_getPagesSqlQuery($sqlChunks['attributes'], $sqlChunks['tables'], $where);
         $linksSql = $this->_getLinksSqlQuery($sqlChunks['attributes'], $sqlChunks['tables'], $where);

         $contextParameters = $this->context->parameters;
         $useLinks = isset($contextParameters['useLinks']) ? $contextParameters['useLinks'] : TRUE;

         return $this->connection->query('%sql %if UNION ALL %sql %end
                                            ORDER BY
                                                %if
                                                    FIND_IN_SET([lang], %s),
                                                %end
                                                %if
                                                    FIND_IN_SET([time_zone], %s),
                                                %end
                                                [sortorder] %sql,
                                                [version] DESC
                                        ', $pagesSql,
                                           $useLinks,
                                           $linksSql,

                                           !empty($ghostPriority), $ghostPriority,
                                           $searchAllTimeZones,
                                           is_array($searchAllTimeZones) ? implode(',',$searchAllTimeZones) : '0,-1,1',
                                           strtoupper($orderDirection ?: 'ASC')
                                        );

         //die();
    }



    private function _getPageQuery($params) {

        extract($params);

        $queryBuilder = new QueryBuilders\EntityQueryBuilder($this->context);

        $queryDescendantsParams = $params;
        unset($queryDescendantsParams['entityConfig'], $queryDescendantsParams['groupName']);
        $queryDescendantsParams['sqlChunks'] = $queryBuilder->getEntitySelectChunks($entityConfig, $groupName);

        return $this->_queryPages($queryDescendantsParams);
    }


    /**
     * Middle layer for obtaining single page.
     * Utilizes EntityQuery builder to get $entitySelectChunks
     *
     * Input array contains following keys
     *  - lang                  string - language code to expand
     *  - states                array | NULL
     *  - labelId               int | NULL
     *  - entityConfig          array | NULL
     *  - groupName             string | NULL
     *  - orderDirection        string | NULL
     *  - limit                 int | NULL
     *  - searchAllTimeZones    bool
     *  - ghostPriority         array
     *  - treeNodeId            int
     *  - allLangs              array
     *
     *
     *
     * @param type $params
     * @return type
     */
    public function loadPage($params) {
        return $this->_getPageQuery($params)->fetchAssoc(self::SINGLE_PAGE);
    }


    /**
     * Middle layer for obtaining descendants.
     * Utilizes EntityQuery builder to get $entitySelectChunks
     *
     * Input array contains following keys
     *  - lang                  string - language code to expand
     *  - states                array | NULL
     *  - labelId               int | NULL
     *  - entityConfig          array | NULL
     *  - groupName             string | NULL
     *  - orderDirection        string | NULL
     *  - limit                 int | NULL
     *  - searchAllTimeZones    bool
     *  - ghostPriority         array
     *  - treeNodeId            int
     *  - allLangs              array
     *
     *
     *
     * @param type $params
     * @return type
     */
    public function loadDescendants($params) {
        return $this->_getPageQuery($params)->fetchAssoc(self::TREENODE_PAGE);
    }

    /**
     * Middle layer for obtaining label roots
     * Utilizes EntityQuery builder to get $entitySelectChunks
     *
     * @param type $params
     * @return type
     */
    public function loadLabelRoots($params) {
        return $this->_getPageQuery($params)->fetchAssoc(self::TREENODE_PAGE);
    }

    /**
     * Middle layer for obtaining all pages.
     * Utilizes EntityQuery builder to get $entitySelectChunks
     *
     *
     * Input array contains following keys
     *  - lang                  string - language code to expand
     *  - states                array | NULL
     *  - labelId               int | NULL
     *  - entityConfig          array | NULL
     *  - groupName             string | NULL
     *  - orderDirection        string | NULL
     *  - limit                 int | NULL
     *  - searchAllTimeZones    bool
     *  - ghostPriority         array
     *  - treeNodeId            int
     *  - allLangs              array
     *
     * @param type $lang
     * @return type
     */
    public function loadAllPages($params) {
        return $this->_getPageQuery($params)->fetchAssoc(self::PARENT_PAGES);
    }


    /**
     * Middle layer for obtaining multilang page.
     * Utilizes EntityQuery builder to get $entitySelectChunks
     *
     * @param type $params
     * @return type
     */
    public function loadPageMultiLang($params) {
        return $this->_getPageQuery($params)->fetchAssoc(self::MULTILANG_PAGE);
    }


    public function loadParent($parentTreeNodeId) {
        return $this->connection->fetch('SELECT
                                            [t].[parent]
                                            FROM
                                                [:core:page_tree] [t]
                                            WHERE [tree_node_id] = %i LIMIT 1', $parentTreeNodeId);
    }

    public function getEntity($treeNodeId) {
        return $this->connection->fetchSingle('SELECT
                                                    [entity]
                                                    FROM
                                                        [:core:page_tree]
                                                    JOIN
                                                        [:core:pages]
                                                    USING
                                                        ([tree_node_id])
                                                    WHERE
                                                        [:core:page_tree].[tree_node_id] = %i
                                           UNION ALL
                                                SELECT
                                                    [entity]
                                                    FROM
                                                        [:core:page_tree]
                                                    JOIN
                                                        [:core:pages]
                                                    ON
                                                        [:core:pages].[tree_node_id] = [:core:page_tree].[pattern]
                                                    WHERE
                                                        [:core:page_tree].[tree_node_id] = %i
                                            ', $treeNodeId, $treeNodeId);
    }




    /******************************
     * SAVING PROCEDURES
     ******************************/


    /**
     * In $saveData table [:core:pages] is at first position
     *
     * After insertion into [:core:pages], retrieve page_id
     * and insert it into every other table
     *
     * @param type $saveData
     * @param type $pageIdsToDelete
     */
    public function savePage($saveData) {

        $pageId = NULL;

        // add modifiers to date columns
//        $modifiers['[:core:pages]'] = array(
//                                        'start_public'  =>  '%d',
//                                        'stop_public'   =>  '%d'
//                                      );

        // create page (complex storage)
        $c = 1;

        foreach ($saveData as $tableName => $tableProperties) {
            $properties = $tableProperties;

            $insertAsExtended = FALSE;
            if ($c > 1) {
                if (is_array($properties)) {
                    $pKeys = array_keys($properties);
                    foreach ($pKeys as $pKey) {
                        $properties[$pKey]['page_id'] = $pageId;
                    }
                    $insertAsExtended = TRUE;
                } else {
                    $properties['page_id'] = $pageId;
                }
            }



            if (isset($modifiers[$tableName])) {
                foreach ($modifiers[$tableName] as $propertyName => $mod) {
                    $properties[$propertyName.$mod] = $properties[$propertyName] ?: NULL;
                    unset($properties[$propertyName]);
                }
            }

//            dump($tableName, $properties);
//            die();

            if ($insertAsExtended)
                $this->connection->query("INSERT INTO $tableName %ex", $properties);
            else
                $this->connection->query("INSERT INTO $tableName", $properties);

            if ($c == 1) {
                $pageId = $this->connection->getInsertId();
            }
            $c++;
        }

//        die();
        return $pageId;
    }


    public function removeOldPages($pageIdsToDelete) {
        // remove previous pages
        // TODO add hooks for delete published pages
        if (!empty($pageIdsToDelete)) {
            $this->connection->query('DELETE FROM [:core:pages] WHERE [page_id] IN %in', array_keys($pageIdsToDelete));
        }
    }

    /**
     * Returns list od pages associated in the following form
     *
     * <lang> => array(
     *              <time_zone> => array(
     *                              <status> => array('pageId' ... ),
     *                                        .
     *                                        .
     *                              ),
     *                          .
     *                          .
     *              ).
     *          .
     *          .
     *          .
     *
     * where time_zone is:
     *  0 : for pages with present publish range,
     *  1 : for pages with future publish range,
     *  -1: for pages with past publish range
     *
     * @param type $treeNodeId
     * @return type
     */
    public function getAllPageVersions($treeNodeId) {

        return $this->connection->query('SELECT
                                            [page_id],
                                            [lang],
                                            [tree_node_id],
                                            [status],
                                            [version],
                                            ROUND(
                                                (IF ([:core:pages].[start_public] IS NULL, -1, (([:core:pages].[start_public] > NOW())* 2) -1) +
                                                 IF ([:core:pages].[stop_public] IS NULL, 1, (([:core:pages].[stop_public] > NOW()) * 2) -1)) / 2
                                            ) as [time_zone]
                                            FROM
                                                [:core:pages]
                                            WHERE
                                                [tree_node_id] = %i
                                         ', $treeNodeId)->fetchAssoc('lang,time_zone,status,page_id');

    }

    public function getLatestPageVersions($treeNodeId) {

        return $this->connection->query('SELECT
                                            [lang],
                                            [version]
                                            FROM
                                                [:core:pages]
                                            WHERE
                                                [tree_node_id] = %i
                                            ORDER BY
                                                [version] DESC
                                        ', $treeNodeId)->fetchAssoc('lang');

    }




//    public function createPage($saveData) {
//
//        foreach ($saveData as $tableName => $tableProperties) {
//
//            $query = $this->connection->test("INSERT INTO $tableName", $tableProperties);
//
//            die();
//        }
//
//    }


    public function createTreeNodeId($parentData) {

        $this->connection->query('INSERT INTO [:core:page_tree]', $parentData);
        $treeNodeId = $this->connection->getInsertId();
        $this->connection->query('UPDATE [:core:page_tree] SET [sortorder] = %i WHERE [tree_node_id] = %i', 10*$treeNodeId, $treeNodeId);
        return $treeNodeId;

    }

    /**
     * Output structure as follows
     *
     * array(
     *          <treeNodeId> => ...
     *      );
     *
     *
     *
     * @param type $treeNodeIds
     * @param type $alienLanguages
     * @return string
     *
     */
    public function getAlienPagesUrls($referencingPages, $alienLanguages, $langManager) {


//        dump($referencingPages, $alienLanguages);
//        die();
        $inverseIndex = array();

//        dump($referencingPages);
//        die();

//        dump('jupi');
//        dump('want to find parent for following treeNodeIds', $referencingPages);

        $urls = array();

        $treeNodeIds = array();

        if (!empty($referencingPages)) {
            foreach($referencingPages as $moduleName => $aliens) {
//                dump(array_keys($aliens));
                $p = array_fill(0, count($aliens), $moduleName);
                $inverseIndex = $inverseIndex + array_combine(array_keys($aliens), $p);

                $treeNodeIds = array_merge($treeNodeIds, array_keys($aliens));
            }



//            dump($inverseIndex);
//            die();




                    $nonEmptyTreeNodeIds = array();

                    foreach ($treeNodeIds as $treeNodeId) {

                        if (empty($treeNodeId)) {
                            if (!empty($alienLanguages)) {
                                foreach ($alienLanguages as $langCode => $l) {
                                    $urls[$treeNodeId][$langCode] = array('parent_url' => '');
                                }
                            }
                        } else {
                            $nonEmptyTreeNodeIds[] = $treeNodeId;
                        }

                    }

                    if (!empty($nonEmptyTreeNodeIds)) {

//                        dump($nonEmptyTreeNodeIds);
//                        die();

                        $urlRecords = $this->connection->query("SELECT
                                                                    [:core:page_tree].[tree_node_id],
                                                                    [:core:pages].[lang],
                                                                    [:core:pages].[version],
                                                                    [:core:pages].[status],
                                                                    [:core:urls].[url],
                                                                    ROUND(
                                                                        (IF ([:core:pages].[start_public] IS NULL, -1, (([:core:pages].[start_public] > NOW())* 2) -1) +
                                                                         IF ([:core:pages].[stop_public] IS NULL, 1, (([:core:pages].[stop_public] > NOW()) * 2) -1)) / 2
                                                                    ) as [time_zone]
                                                                    FROM
                                                                        [:core:pages]
                                                                    JOIN
                                                                        [:core:page_tree]
                                                                    USING
                                                                        ([tree_node_id])
                                                                    JOIN
                                                                        [:core:urls]
                                                                    ON
                                                                        [:core:urls].[page_id_] = [:core:pages].[page_id]
                                                                    WHERE
                                                                        [:core:pages].[tree_node_id] IN %in
                                                                        AND
                                                                        [:core:pages].[lang] IN %in

                                                            UNION ALL

                                                                SELECT
                                                                    [:core:page_tree].[tree_node_id],
                                                                    [:core:pages].[lang],
                                                                    [:core:pages].[version],
                                                                    [:core:pages].[status],
                                                                    [:core:urls].[url],
                                                                    ROUND(
                                                                        (IF ([:core:pages].[start_public] IS NULL, -1, (([:core:pages].[start_public] > NOW())* 2) -1) +
                                                                         IF ([:core:pages].[stop_public] IS NULL, 1, (([:core:pages].[stop_public] > NOW()) * 2) -1)) / 2
                                                                    ) as [time_zone]

                                                                    FROM
                                                                        [:core:page_tree]
                                                                    JOIN
                                                                        [:core:pages]
                                                                    ON
                                                                        [:core:pages].[tree_node_id] = [:core:page_tree].[pattern]
                                                                    JOIN
                                                                        [:core:urls]
                                                                    ON
                                                                        [:core:urls].[page_id_] = [:core:pages].[page_id]
                                                                    WHERE
                                                                        [:core:page_tree].[tree_node_id] IN %in
                                                                        AND
                                                                        [:core:pages].[lang] IN %in



                                                                    ORDER BY
                                                                        FIELD([status], 'published', 'draft'),
                                                                        FIELD([time_zone], 0, -1, 1),
                                                                        [version] DESC
                                                                ", $nonEmptyTreeNodeIds, array_keys($alienLanguages), $nonEmptyTreeNodeIds, array_keys($alienLanguages))->fetchAssoc('tree_node_id,lang,status,time_zone');

//                        \Nette\Diagnostics\Debugger::$maxDepth = 6;
//                        dump($urlRecords);
//
//                        dump($alienLanguages);


                        if (!empty($alienLanguages)) {
                            foreach ($alienLanguages as $langCode => $l) {
                                foreach ($urlRecords as $treeNodeId => $urlData) {
                                    if (empty($treeNodeId)) {
                                        $urls[$treeNodeId][$langCode] = array('parent_url' => '');
                                    } else if (isset($urlData[$langCode])) {
                                        $_t = reset($urlData[$langCode]);
                                        $t = reset($_t);
                                        $urls[$treeNodeId][$langCode] = array('parent_url' => $t['url']);
                                    } else {
                                        $urls[$treeNodeId][$langCode] = array('parent_url' => '_undefin@_');
                                        //$urls[$langCode] = array('parent_url' => '');
                                    }
                                }

                            }
                        }


                    }



        }

        //$languages[] = 'pl';







//            \Nette\Diagnostics\Debugger::$maxDepth = 4;
//
//             dump($treeNodeId, $urlRecords);
//             die();



//            if (!empty($urlRecords)) {
//                foreach ($urlRecords as $langCode => $pageStatus) {
//
//                }
//            }


        // refine $urls
        foreach (array_keys($urls) as $treeNodeId) {
            $langs = $langManager->getLangs($inverseIndex[$treeNodeId]);
            $urls[$treeNodeId] = array_intersect_key($urls[$treeNodeId], $langs);
        }

        return $urls;
    }



    /**
     * Get pages' urls
     * with following priority:
     *
     * - published in timezone 0 (latest version)
     * - published in timezone -1 (latest version)
     * - published in timezone 1 (latest version)
     * - draft in timezone 0 (latest version)
     * - draft in timezone -1 (latest version)
     * - draft in timezone 1 (latest version)
     * - others (trashed etc...)
     *
     * @param type $treeNodeId
     * @param type $languages
     * @param type $defaultLanguage
     * @return type
     */
    public function getPagesUrls($treeNodeId, $languages, $defaultLanguage/*, $alienLanguages*/) {


        $urls = array();

        //$languages[] = 'pl';

        if (empty($treeNodeId)) {
            if (!empty($languages)) {
                foreach ($languages as $langCode) {
                    $urls[$langCode] = array('parent_url' => '');
                }
            }
        } else {

            $urlRecords = $this->connection->query("SELECT
                                                        [:core:pages].[lang],
                                                        [:core:pages].[version],
                                                        [:core:pages].[status],
                                                        [:core:pages].[entity],
                                                        [:core:urls].[url],
                                                        ROUND(
                                                            (IF ([:core:pages].[start_public] IS NULL, -1, (([:core:pages].[start_public] > NOW())* 2) -1) +
                                                             IF ([:core:pages].[stop_public] IS NULL, 1, (([:core:pages].[stop_public] > NOW()) * 2) -1)) / 2
                                                        ) as [time_zone]
                                                        FROM
                                                            [:core:pages]
                                                        JOIN
                                                            [:core:page_tree]
                                                        USING
                                                            ([tree_node_id])
                                                        JOIN
                                                            [:core:urls]
                                                        ON
                                                            [:core:urls].[page_id_] = [:core:pages].[page_id]
                                                        WHERE
                                                            [:core:pages].[tree_node_id] = %i
                                                            AND
                                                            [:core:pages].[lang] IN %in

                                                UNION ALL

                                                    SELECT
                                                        [:core:pages].[lang],
                                                        [:core:pages].[version],
                                                        [:core:pages].[status],
                                                        [:core:pages].[entity],
                                                        [:core:urls].[url],
                                                        ROUND(
                                                            (IF ([:core:pages].[start_public] IS NULL, -1, (([:core:pages].[start_public] > NOW())* 2) -1) +
                                                             IF ([:core:pages].[stop_public] IS NULL, 1, (([:core:pages].[stop_public] > NOW()) * 2) -1)) / 2
                                                        ) as [time_zone]

                                                        FROM
                                                            [:core:page_tree]
                                                        JOIN
                                                            [:core:pages]
                                                        ON
                                                            [:core:pages].[tree_node_id] = [:core:page_tree].[pattern]
                                                        JOIN
                                                            [:core:urls]
                                                        ON
                                                            [:core:urls].[page_id_] = [:core:pages].[page_id]
                                                        WHERE
                                                            [:core:page_tree].[tree_node_id] = %i
                                                            AND
                                                            [:core:pages].[lang] IN %in



                                                        ORDER BY
                                                            FIELD([status], 'published', 'draft'),
                                                            FIELD([time_zone], 0, -1, 1),
                                                            [version] DESC
                                                    ", $treeNodeId, $languages, $treeNodeId, $languages)->fetchAssoc('lang,status,time_zone');

//            \Nette\Diagnostics\Debugger::$maxDepth = 4;
//             dump($urlRecords);
//             die();

            if (!empty($languages)) {
                foreach ($languages as $langCode) {
                    if (empty($treeNodeId)) {
                        $urls[$langCode] = array('parent_url' => '');
                    } else if (isset($urlRecords[$langCode])) {
                        $_t = reset($urlRecords[$langCode]);
                        $t = reset($_t);
                        $urls[$langCode] = array('parent_url' => $t['url']);
                    } else {
                        //$urls[$langCode] = array('parent_url' => '_undefin@_');
                        $urls[$langCode] = array('parent_url' => '');
                    }

                }
            }

//            if (!empty($urlRecords)) {
//                foreach ($urlRecords as $langCode => $pageStatus) {
//
//                }
//            }

        }

        return $urls;
    }


    public function insertUrl($urlData) {
        return $this->connection->query('INSERT INTO [:core:urls]', $urlData);
    }

    public function insertUrls($urlData) {
        return $this->connection->query('INSERT INTO [:core:urls] %ex', $urlData);
    }

    public function setUrlAsTrashed($treeNodeId) {
        return $this->connection->query('UPDATE [:core:urls] SET [page_deleted] = NOW() WHERE [tree_node_id_] = %i', (int) $treeNodeId);
    }



    public function loadAllLabelExtensions() {
        return $this->connection->query('SELECT * FROM [:core:label_ext_definitions]')->fetchAssoc('identifier');
    }

    public function maybeChangeParent($treeNodeId, $parentData) {

        $currentParent = $this->connection->fetch('SELECT * FROM [:core:page_tree] WHERE [tree_node_id] = %i', $treeNodeId);

        $diff = FALSE;
        if ($currentParent !== FALSE) {
            foreach ($parentData as $k => $v) {
                if ($parentData[$k] != $currentParent[$k]) {
                    $diff = TRUE;
                    break;
                }
            }
        }



        if ($diff) {
            $this->connection->query('UPDATE [:core:page_tree] SET', $parentData, 'WHERE [tree_node_id] = %i', $treeNodeId);
        }

    }


    /**
     * Used for additional label loading (for label roots)
     *
     * @param type $treeNodeIds
     * @param type $lang
     */
    public function loadLabelings($treeNodeIds, $lang) {

        $where = array(
            'tree_node_id%in'	=>	$treeNodeIds,
            'lang%s'            =>	$lang
		);

        return $this->connection->query('SELECT * FROM [:core:pages_labels] WHERE %and', $where)->fetchAssoc('tree_node_id,label_id');

    }


    public function createLink($data) {

        $dbData = array(
                    'parent'            =>  $data['parent'],
                    'layout'            =>  $data['layout'],
                    'module'            =>  $data['image_module'],
                    'pattern'           =>  $data['pattern'],
                    'pattern_module'    =>  $data['pattern_module']
        );

        return $this->createTreeNodeId($dbData);

    }

    public function editLink($data, $image) {

        //$image = $data['image'];
        //unset($data['image']);

        $pageTreeData = array(
                        'parent'            =>  $data['parent'],
                        'layout'            =>  $data['layout'],
                        'pattern'           =>  $data['pattern'],
                        'pattern_module'    =>  $data['pattern_module']
        );

//        dump($data['pattern'], $data['pattern_module']);
//        die();

        return $this->maybeChangeParent($image, $pageTreeData);

    }

    public function getLink($image) {

        return $this->connection->fetch("SELECT
                                            [parent],
                                            CONCAT([pattern],'-x') as [pattern],
                                            [layout]
                                            FROM
                                                [:core:page_tree]
                                            WHERE
                                                [tree_node_id] = %i
                                         ", $image);

    }

    public function deleteLink($image) {

        $this->connection->query('DELETE FROM [:core:page_tree] WHERE [tree_node_id] = %i', $image);
        $this->connection->query('DELETE FROM [:core:pages_labels] WHERE [tree_node_id] = %i', $image);

    }

    /**
     *
     * @param type $treeNodeId
     * @return type
     */
    public function getReferencingPages($treeNodeId) {
        return $this->connection->query('SELECT [module], [parent] FROM [:core:page_tree] WHERE [pattern] = %i', $treeNodeId)->fetchAssoc('module,parent');
    }


    /**
     * Traverse an array of alienUrl and search for "_undefin@_" string
     * in all lang section
     *
     * @param type $alienLangs
     * @param type $alienUrls
     */
    public function getAlienTabUrls($alienLangs, $alienUrls) {
        $alienTabUrls = array();

//        dump($alienUrls);
//        die();

        foreach ($alienLangs as $langCode => $l) {
            $alienTabUrls[$langCode]['parent_url'] = "";
            foreach ($alienUrls as $treeNodeId => $langUrls) {
                if (isset($langUrls[$langCode]) && $langUrls[$langCode]['parent_url'] == '_undefin@_') {
                    $alienTabUrls[$langCode]['parent_url'] = '_undefin@_';
                }
            }
        }

        return $alienTabUrls;

    }


    /**
     * Traverse an array of alienUrl and search for "_undefin@_" string
     * in all lang section
     *
     * @param type $alienLangs
     * @param type $alienUrls
     */
    public function getAllAlienUrls($referencingPages, $alienUrls) {


        $allAlienUrls = array();

        $treeNodeIdIndex = array();
        // first create treeNodeId index
        foreach ($referencingPages as $moduleName => $aliens) {
            $treeNodeIdIndex = $treeNodeIdIndex + $aliens;
        }

//        dump('$referencingPages');
//        dump($referencingPages);
//
//        dump('$alienUrls');
//        dump($alienUrls);
//        die();

//        dump($treeNodeIdIndex);
//        die();

        foreach ($alienUrls as $treeNodeId => $langs) {
            foreach ($langs as $langCode => $parentUrl) {
//                dump($parentUrl);
//                die();
                $allAlienUrls[$langCode][$treeNodeId] = array(
                                                            'parent_url'        => $parentUrl['parent_url'],
                                                            'access_through'    => $treeNodeIdIndex[$treeNodeId]['module']
                );
            }
        }

//        \Nette\Diagnostics\Debugger::$maxDepth = 6;
//        dump($allAlienUrls);
//        die();

        return $allAlienUrls;

    }


    public function savePageSorting($items) {

        if (!empty($items)) {
            foreach ($items as $sortOrder => $treeNodeId) {
                $this->connection->query('UPDATE [:core:page_tree] SET [sortorder] = %i WHERE [tree_node_id] = %i', $sortOrder, $treeNodeId);
            }
        }

    }

    public function getModuleByTreeNodeId($treeNodeId) {
        return $this->connection->fetchSingle('SELECT [module] FROM [:core:page_tree] WHERE [tree_node_id] = %i', $treeNodeId);
    }


    public function getForeignModuleUrls($treeNodeId, $langs) {

        return $this->connection->query('SELECT
                                            [page_id_],
                                            [module_],
                                            [tree_node_id_],
                                            [lang_],
                                            [url]
                                            FROM
                                                [:core:urls]
                                            WHERE
                                                [tree_node_id_] = %i
                                                AND
                                                [lang_] IN %in
                                            ORDER BY [page_id_] DESC
                                        ', $treeNodeId, $langs)->fetchAssoc('lang_');
    }


    public function emptyTrash() {
        $r = $this->connection->query('SELECT [tree_node_id] FROM [:core:pages] WHERE [status] = %s GROUP BY [tree_node_id]', 'trashed')->fetchAssoc('tree_node_id');

        if ($r) {
            $treeNodeIds = array_keys($r);


    //        dump($treeNodeIds);
    //        die();


            $res = $this->connection->query('SELECT [page_id] FROM [:core:pages] WHERE [tree_node_id] IN %in', $treeNodeIds)->fetchAssoc('page_id');
            $pageIds = array_keys($res);

            // delete pages from cms_page_tree
            $this->connection->query('DELETE FROM [:core:page_tree] WHERE [tree_node_id] IN %in', $treeNodeIds);

            // delete from cms_urls
            $this->connection->query('DELETE FROM [:core:urls] WHERE [tree_node_id_] IN %in', $treeNodeIds);


            // delete from cms_pages_labels
            $this->connection->query('DELETE FROM [:core:pages_labels] WHERE [tree_node_id] IN %in', $treeNodeIds);

            // delete from cms_vd_pages_files
            $this->connection->query('DELETE FROM [:core:vd_pages_files] WHERE [page_id] IN %in', $pageIds);

            //dump($pageIds);
        }
//        die();
    }


    /***************************/
    public function getLastAddedFileInGallery($galleryId) {
        return $this->connection->fetchSingle('SELECT [added] FROM [:core:vd_files] WHERE [gallery_id] IN %in ORDER BY [added] DESC LIMIT 1', (array) $galleryId);
    }

}
