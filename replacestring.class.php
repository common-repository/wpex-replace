<?php

if( ! class_exists( 'WP_List_Table' ) )
{
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

//
// class WPEXReplaceTextInTables 
//

function WPEXReplaceTable()
{
  static $_WPEXReplaceTable=null;
  if ($_WPEXReplaceTable==null)
  {
    $_WPEXReplaceTable = new WPEXReplaceTextInTables();
  }
  return $_WPEXReplaceTable;
}

class WPEXReplaceTextInTables extends WP_List_Table
{
	private $results = '';

	function getExcludedInternalUrls()
	{
		static $urls = null;
		if (!$urls)
			$urls = include(__DIR__.'/excluded-urls.php');
		return $urls;
	}

	function getExcludedUserDefinedUrls()
	{
		$urls = unserialize(get_option('wpex_replace_exclude_search_urls'));
		if (empty($urls))
			$urls = array();
		return $urls;
	}
	
	function getExcludedUrlList()
	{
		//return array_merge($this->getExcludedUserDefinedUrls(), $this->getExcludedInternalUrls());
		//return $this->getExcludedInternalUrls();
		return array();
	}

	function _addUrlToList(&$result, $table, $url, $idName, $idValue)
	{
		if (!array_key_exists($url, $result))
			$result[$url] = array();

		if (!array_key_exists($table, $result[$url]))
			$result[$url][$table] = array();

		foreach ($result[$url][$table] as $item)
		{
			if ($item['name']==$idName && $item['value']==$idValue)
				return;
		}
		
		$result[$url][$table][] = array('name'=>$idName, 'value'=>$idValue);
	}
	
	
	function findURls()
	{
		if (!isset($_POST['s']))
			return array();
		
		$replaceList = array(
			'http://'=>'http://',
			'https://'=>'https://',
		);
		
		$filter = $_POST['s'];
		
		$result = array();
		//$regex = "!((?:".implode('|', array_keys($replaceList)).")[^#?/\"\r\n]*)(\/wp-content)?!";
		$regex = "!((?:".implode('|', array_keys($replaceList)).")[^#?\"\r\n\s,:;(){}\[\]]*)(\/wp-content)?!";
		
		// search the urls in all tables
		$tables = $this->getTables();
		foreach ($tables as $table)
		{
			$columns = $this->getColumns($table);
			
			$rows = $this->getData($table, $replaceList);
			foreach ($rows as $row)
			{
				foreach ($columns as $column)
				{
					$matchResult = preg_match_all($regex, $row->$column, $matches, PREG_SET_ORDER);
					if ($matchResult > 0)
					{
						foreach ($matches as $match)
						{
							$url = trim($match[1], "/");
							if (strpos($url, ' '))
								continue;
							if (in_array($url, $this->getExcludedUrlList()))
								continue;

							if (empty($filter) || strripos($url, $filter) !== false)
								$this->_addUrlToList($result, $table, $url, $columns[0], $row->$columns[0]);
						}
					}
				}
			}
		}

		// create data rows for the result
		$data = array();
		foreach ($result as $url=>$tables)
		{
			$htmlContent = '';
			foreach ($tables as $table=>$ids)
			{
				$htmlContent .= "{$table}[{$ids[0]['name']}]=";
				foreach ($ids as $index=>$id)
				{
					if ($index > 0)
						$htmlContent .= ',';
					$htmlContent .= $id['value'];
				}
				$htmlContent .= ';';
			}

			$row = array(
				'found' => $url,
				'replace' => '',
				'tables' => $htmlContent,
			);
			$data[] = $row;
		}
		
		return $data;
	}
	
	
	function exclude($url)
	{
		$urls = $this->getExcludedUserDefinedUrls();
	
		if (!in_array($url, $urls))
			$urls[] = $url;
				
		update_option('wpex_replace_exclude_search_urls', serialize($urls));
	}

	function replace($data)
	{
    global $wpdb;
    
		$error = '';
		
		foreach($data as $item)
		{
			$replaceList = array($item['oldText'] => $item['newText']);

			preg_match_all('/([^\[]+)\[([^\]]+)\]=([^;]+);/', $item['ids'], $matches, PREG_SET_ORDER);
			foreach ($matches as $match)
			{
				// set array as variables
				list($dummy, $table, $idName, $idValues) = $match;

				// table columns
				$columns = $this->getColumns($table);

				// selected fields
				$fields = "";
				foreach ($columns as $column)
				{
					if ($fields)
						$fields .= ",";
					$fields .= "`{$column}`";
				}
		
				// get the rows that should be replaced
				$query = "select {$fields} from `{$table}` where `{$idName}` in ({$idValues})";
				$dbResult = $wpdb->get_results($query, OBJECT);

				// find columns that has changed
				foreach ($dbResult as $row)
				{
					$modifiedRow = new StdClass();
					$modifiedRow->$idName = $row->$idName;
					
					// loop over all columns
					foreach ($row as $key=>$value)
					{
						// ignore id
						if ($key == $idName)
							continue;

						// replace data
						$row->$key = $this->replaceValue($value, $replaceList);

						// remember results
						if (strcasecmp($value, $row->$key) != 0)
						{
							$modifiedRow->$key = $row->$key;
						}
					}

					// save changes
					if (!$this->saveData($table, $idName, $modifiedRow))
					{
						$error .= str_replace(
							array('{table}', '{id_name}', '{id}'),
							array($table, $idName, $row->id),
							__('table {table} {id_name}={id} could not be saved.', 'wpex-replace').'<br/>'
						);
					}
				}
			}
		}
		
		return $error;
	}
	
	function replaceValue($value, $replaceList)
	{
		if (is_array($value))
		{
			$a = array();
			foreach ($value as $k=>$v)
			{
				$a[$k] = $this->replaceValue($v, $replaceList);
			}
			return $a;
		}
    if (is_object($value))
		{
			$o = new stdClass();
			foreach ($value as $k=>$v)
			{
				$o->$k = $this->replaceValue($v, $replaceList);
			}
			return $o;
		}    
		if (strncasecmp($value, 'a:', 2) == 0)
		{
			$a = unserialize($value);
			foreach ($a as $k=>$v)
			{
				$a[$k] = $this->replaceValue($v, $replaceList);
			}
			return serialize($a);
		}
		if (strncasecmp($value, 'o:', 2) == 0)
		{
			$o = unserialize($value);
			foreach ($o as $k=>$v)
			{
				$o->$k = $this->replaceValue($v, $replaceList);
			}
			return serialize($o);
		}
	
		$oldValue = $value;
		$newValue = str_replace(array_keys($replaceList), array_values($replaceList), $value);
		
		if (strcasecmp($oldValue, $newValue) == 0)
			return $value;
		
		//// remember results
		//$displayOld = htmlspecialchars($oldValue);
		//$displayNew = htmlspecialchars($newValue);
		//foreach ($replaceList as $k=>$v)
		//{
		//	$displayOld = str_replace($k, '<span class="marked">'.$k.'</span>', $displayOld);
		//	$displayNew = str_replace($v, '<span class="marked">'.$v.'</span>', $displayNew);
		//}
		//$this->result .= '<div class="row value"><div class="old-site">'.$displayOld.'</div><div class="new-site">'.$displayNew.'</div><div class="clearfix"></div></div>';
		
		return $newValue;
	}
	
	function getTables()
	{
		global $wpdb;
		
		$query = "SHOW TABLES FROM `".DB_NAME."`";
    $dbResult = $wpdb->get_results($query, OBJECT);

		$tables = array();
		if ($dbResult)
    {
			$fieldName = "Tables_in_".DB_NAME;
      foreach ($dbResult as $table)
      {
				$tables[] = $table->$fieldName;
      };
		}
		return $tables;
	}
	
	function getColumns($tableName)
	{
		global $wpdb;
		
		$query = "SHOW COLUMNS FROM `{$tableName}` WHERE `type` LIKE '%char%' OR `type` LIKE '%text%' OR Extra = 'auto_increment'";
    $dbResult = $wpdb->get_results($query, OBJECT);

		$columns = array();
		if ($dbResult)
    {
      foreach ($dbResult as $column)
      {
				$columns[] = $column->Field;
      };
		}
		return $columns;
	}

	function getData($tableName, $replaceList)
	{
		global $wpdb;
		
		$rows = array();

		$columns = $this->getColumns($tableName);
		if (empty($columns) || count($columns)<=0)
			return $rows;

		// store the id to ignore it
		$id = $columns[0];

		// build where clause
		$where = "";
		foreach ($columns as $column)
		{
			// ignore id
			if ($column == $id)
				continue;
			
			foreach ($replaceList as $key=>$value)
			{
				if ($where)
					$where .= " OR ";
				$where .= "`{$column}` like '%{$key}%'";
			}
		}

		// get data
		$query = "SELECT `".implode("`,`", $columns)."` FROM `{$tableName}` WHERE {$where}";
    $dbResult = $wpdb->get_results($query, OBJECT);
			
		// build response array 
		if ($dbResult)
    {
      foreach ($dbResult as $row)
      {
				$rows[] = $row;
      };
		}
		return $rows;
	}
	
	function saveData($tableName, $idName, $row)
	{
		global $wpdb;

		// build set of query
		$set = '';
		foreach ($row as $key=>$value)
		{
			// ignore id
			if ($key == $idName)
				continue;
			
			if ($set)
				$set .= ",";
			$set .= "`{$key}`='".mysql_escape_string($value)."'";
		}
		if (empty($set))
			return true;
		
		$query = "UPDATE `{$tableName}` SET {$set} WHERE {$idName}={$row->$idName}";
		return ($wpdb->query($query) !== false);
	}

  //
  // WP_List_Table
  //
  
  function __construct( $args=array() )
  {
    //global $status, $page;

    parent::__construct( array_merge($args, array(
      'singular' => __( 'Url', 'wpex-replace' ), //singular name of the listed records
      'plural' => __( 'Urls', 'wpex-replace' ), //plural name of the listed records
      'ajax' => true //does this table support ajax?
    )));

    //add_action( 'admin_head', array( &$this, 'admin_header' ) );
  }
	
  function admin_header()
  {
    //$page = ( isset($_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : false;
    //if( $page != 'author' )
    //  return;
    //
    //echo '
    //<style type="text/css">
    //  //.wp-list-table .column-first_name { width: 25%; }
    //  //.wp-list-table .column-last_name { width: 25%; }
    //</style>
    //';
  }
	
  function no_items()
  {
    _e('No Urls found.', 'wpex-replace');
  }
	
  function get_columns()
  {
    $columns = array(
      'cb' => '<input type="checkbox" />',      
      'found' => __('Founded url', 'wpex-replace'),
      'replace' => __('Replace with url', 'wpex-replace'),
    );
		return $columns;
  }
	
  function prepare_items()
  {
    // Handle bulk actions 
    $this->process_bulk_action();
    
    // Column informations
    $this->_column_headers = $this->get_column_info();

    // page stuff
    $per_page = $this->get_items_per_page('urls_per_page', 20);
    $current_page = $this->get_pagenum();
    
    // the data
    $items = $this->findURls();
		$data = array();
		for ($i = ($current_page-1)*$per_page; $i < (($current_page-1)*$per_page) + $per_page; $i++)
		{
			if (isset($items[$i]))
				$data[] = $items[$i];
		}
		$this->items = $data;
    
    // pagination stuff
    $total_items = count($items);
    $this->set_pagination_args( array(
      'total_items' => $total_items,                  //WE have to calculate the total number of items
      'per_page' => $per_page,                        //WE have to determine how many items to show on a page
      'total_pages' => ceil($total_items/$per_page),  //WE have to calculate the total number of pages
    ));
  }
	
  function column_default( $item, $column_name )
  {
    switch( $column_name )
    {
      case 'found':
      case 'replace':
      case 'tables':
        return $item[$column_name];
      default:
        return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
    }
  }
	
  function get_sortable_columns()
  {
    $sortable_columns = array(
      //'found' => array('found',true),
    );
    return $sortable_columns;
  }
	
  function column_cb($item)
  {
    return sprintf('<input type="checkbox" name="url[]" value="%s" />', $item['found']);
    //return sprintf('<input type="checkbox" name="url[]" value="%s|%s" />', $item['found'], $item['tables']);
  }

  function column_found($item)
  {
    return '<input type="hidden" name="org_url[]" value="'.$item['found'].'" />'.
           '<input type="hidden" name="ids[]" value="'.$item['tables'].'" />'.
           '<a class="found-url" href="'.$item['found'].'" target="_blank">'.$item['found'].'</a>';
  }

  function column_replace($item)
  {
    return '<input type="text" name="replace[]" value="'.$item['replace'].'" />';
  }
	
  function get_bulk_actions()
  {
    $actions = array(
      //'exclude' => __('Exclude', 'wpex-replace'),
      'replace' => __('Replace', 'wpex-replace'),
    );
    return $actions;
  }
	
  function process_bulk_action()
  {
    //Exclude when a bulk action is being triggered...
    if( $this->current_action() === 'exclude' )
    {
      $url_ids = ( is_array( $_REQUEST['url'] ) ) ? $_REQUEST['url'] : array( $_REQUEST['url'] );
      foreach($url_ids as $url)
      {
        $this->exclude($url);
      }
    }

    //Replace when a bulk action is being triggered...
    else if( $this->current_action() === 'replace' )
    {
			$table_ids = ( is_array( $_REQUEST['ids'] ) ) ? $_REQUEST['ids'] : array( $_REQUEST['ids'] );
	    $replace_urls = ( is_array( $_REQUEST['replace'] ) ) ? $_REQUEST['replace'] : array( $_REQUEST['replace'] );
      $org_url_ids = ( is_array( $_REQUEST['org_url'] ) ) ? $_REQUEST['org_url'] : array( $_REQUEST['org_url'] );
      $url_ids = ( is_array( $_REQUEST['url'] ) ) ? $_REQUEST['url'] : array( $_REQUEST['url'] );
			
			$data = array();
			foreach ($url_ids as $url)
			{
				for ($i = 0; $i < count($org_url_ids); $i++)
				{
					$org_url = $org_url_ids[$i];
					if (strcasecmp($org_url, $url) == 0)
					{
						$data[] = array(
							'oldText' => $url,
							'newText' => trim($replace_urls[$i]),
							'ids' => $table_ids[$i],
						);
					}
				}
			}

			$error = $this->replace($data);
			if ($error)
				$_SESSION['wpex-replace-result'] = '<div class="error">'.$error.'<div>';
			else
				$_SESSION['wpex-replace-result'] = '<div class="updated"><p>'.__('Urls have been replaced', 'wpex-replace').'</p></div>';
    }
  }	
}
