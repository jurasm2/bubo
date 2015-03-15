<?php

namespace Bubo\Database;

use DibiConnection;

class Connection extends DibiConnection
{
	/**
	 * @var DibiConnection
	 */
	protected $connection;

	/**
	 * @param array $dbParams
	 */
	public function __construct(array $dbParams)
	{
		parent::__construct($dbParams);
		//$this->connection = new DibiConnection($dbParams);
		$this->query('SET NAMES UTF8');

		// is this really needed???
		$substitutions = array(
			'core'  =>  'cms_',
			'media' =>  'media_',
		);

		foreach($substitutions as $sub => $prefix) {
			$this->getSubstitutes()->$sub = $prefix;
		}
		return $this;
	}

}