<?php
/**
 * sundb-v0.3.php - Database Class for PHP against SQL injection.
 *
 * @copyright	(c) 2009 Sun Web Dev, Inc. All Rights Reserved
 * @license	 http://creativecommons.org/licenses/GPL/
 *
 * You can use or modify and redistribute the package.
 *
 * https://soucrforge.net/projects/sundbclass
 *
 * To Donate to this project
 * https://sourceforge.net/project/project_donations.php?group_id=280332
 *
 * @package	 sundb-v0.3 Class
 *
 * @example     At the bottom of this page
 */

defined('VERSION') or die('Forbidden Attempt');

class sundb
{
	protected static $link;		       // Database Active Connection
	protected static $result;		     // Database Last Result
	public    static $affected = 0;	       // Affected rows in last query
	public    static $que_time = 0;	       // Query time

	public    static $debug = false;	      // Status of debugging
	public    static $die = true;		 // To die. If false then it throws new exception when error occurs

	public    static $errclass = null;	    // The variable for calling an error class if used.
	public    static $errname = null;	     // The name of the error function in error class

	/**
	 * Creates a connection to the Database (this is not used if there is only one connection to be maintained)
	 * @params	string	 $host	hostname address for MySQL database
	 * @params	string	$user	username
	 * @params	string	$pass	password
	 * @params	string	$name	name of database
	 * @params	int		$port	optional port for hostname
	 * @return	 void
	 */
	public function __construct($charset, $conn = array(), $error = array())
	{

		if(!empty($error[0]))
		{
			if(!empty($error[1]))
			{
				self::$errclass = $error[0];
				self::$errname = $error[1];
			}
			else
				self::$errname = $error[0];
		}

		$server = $conn[0].(($conn[4]) ? ':'.$conn[4] : '');
		self::$link = @mysql_connect($server,$conn[1],$conn[2]);

		if (!is_resource(self::$link))
			self::fatal('Cannot connect to the Server '.$server);

		self::setDB($conn[3]);
		self::charset($charset);
	}

	/**
	 * Deals with the error we receive
	 * @params  string  $msg    error message
	 * @return  void
	 */
	protected function fatal($msg)
	{
		if( !empty($msg) )
		{
			if(empty(self::$errclass) && empty(self::$errname))
			{
				if( self::$die )
					die( $msg );
				else
					throw new Exception( $msg );
			}
			else if(!empty(self::$errclass) && !empty(self::$errname))
				eval(self::$errclass."::".self::$errname."(\$msg);");
			else
				eval(self::$errname."(\$msg);");
		}
	}

	/**
	 * Sets the charset for the database
	 * @params	string	 $charset	charset for MySQL database
	 * @return	 void
	 */
	protected function charset($char)
	{
		$char = strtolower($char);

		switch($char)
		{
				case "utf-8":
				$char = "utf8";
				break;

				case "windows-1250":
				$char = "cp1250";
				break;

				case "iso-8859-1":
				$char = "latin1";
				break;

				case "iso-8859-2":
				$char = "latin2";
				break;

				default:
				$char = "";
				break;
		}

		    if( !empty($char) )
			mysql_query("SET NAMES '".$char."'");

	 }

	/**
	 * Set the database name if possible
	 * @params	string	$name	Name of Database
	 * @return	void
	 */
	public function setDB($name)
	{
		if (!@mysql_select_db($name, self::$link))
		{
			@mysql_close(self::$link);
			self::fatal('Unable to select Database named '.$name);
		}
	}

	/**
	 * Sanitizing the query
	 * @params      string	  The string to queried
	 * @params      array	   The array of params in query represented by '#'
	 * @return      void
	 */
	public function query( $query, $params = array(), $ret = false )
	{
		// No parameters presented, execute query
		if( empty($params) )
			return self::exec($query);

		$sql = "";
		$sqlt_ar = explode('@', $query);

		for( $i = 0; $i < sizeof($sqlt_ar) - 1; $i++)
		{
			$sql .= $sqlt_ar[$i];
			$sql .= "`".$params[$i]."`";
		}

		$sql_ary = explode('#', $sqlt_ar[$i]);

		for( $k = 0; $i < sizeof($params); $i++, $k++)
		{
			$val = $params[$i];
			$sql .= $sql_ary[$k];

			$type = gettype($val);

			if( $type == 'string' )
			{
				if( get_magic_quotes_gpc() )
				$val = stripslashes($val);

				$sql .= sprintf("'%s'", mysql_real_escape_string($val));
			}
			elseif( $type == 'boolean' )
				$sql .= ( $val ) ? 1 : 0;
			elseif( $val === null)
				$sql .=  "NULL";
			else
				$sql .= $val;
		}

		$sql .= $sql_ary[$k];

		if( $k + 1 != sizeof($sql_ary) )
			self::fatal('DB Error, Input array does not match:<br />'.htmlspecialchars($query));

		self::exec($sql);

		if($ret)
			return self::$result;

	}

	/**
	 * Executing the query after sanitization
	 * @params      string	  The sql statement
	 * @return      void
	 */
	protected function exec($sql)
	{
		$time_start = microtime(true);

		self::$result = @mysql_query($sql, self::$link);

		$time_stop = microtime(true);
		self::$que_time = self::$que_time + ($time_stop - $time_start)*1000 ;

		if (self::$debug == true)
			echo '<pre><span style="color: red;"><strong>Query:</strong> '. htmlentities($sql) .'</span><br></pre>';

		if(mysql_errno(self::$link))
			self::fatal(mysql_error(self::$link).'<br /><pre>'.$sql.'</pre>');

		self::$affected = @mysql_affected_rows(self::$link);
	}


	/**
	 * Getting the number of affected rows
	 * @return	 int	 Affected rows for last Insert, Update or Delete query
	 */
	public function affected()
	{
		return self::$affected;
	}

	/**
	 * Getting the incremented id of last query
	 * @return	 int		Auto-increment ID for last Insert query
	 */
	public function insert_id()
	{
		return @mysql_insert_id(self::$link);
	}

	/**
	 * Freeing the memory after big results
	 * @params      resource	  The sql resource
	 * @return      void
	 */
	public function free($rs = null)
	{
		if(empty($rs))
			$rs = self::$result;

		if (is_resource($rs))
			@mysql_free_result($rs);
	}

	/**
	 * getting the object of sql result
	 * @params      resource	  The sql resource
	 * @return      object
	 */
	public function obj($rs = null)
	{
		if(empty($rs))
			$rs = self::$result;

		if (is_resource($rs))
			return @mysql_fetch_object($rs);
		else
			return false;
	}

	/**
	 * getting the array of sql result
	 * @params      resource	  The sql resource
	 * @return      array
	 */
	public function row($rs = null )
	{
		if(empty($rs))
			$rs = self::$result;

		if (is_resource($rs))
			return @mysql_fetch_array($rs);
		else
			return false;
	}

	/**
	 * getting the number of rows of sql result
	 * @params      resource	  The sql resource
	 * @return      int	       Number of rows in resource
	 */
	public function count($rs = null )
	{
		if(empty($rs))
			$rs = self::$result;

		if (is_resource($rs))
			return @mysql_num_rows($rs);
		else
			return false;
	}

	/**
	 * Counting the records in a particular table with given conditions
	 * @params      string	  The sql condition
	 * @params      array	   The array of params in query represented by '#'
	 * @return      int	     Count
	 */
	public function countrecs($cond, $params=array() )
	{
		$ret = self::row(self::query('select count(*) from @ where '.$cond, $params, true));
		return $ret['count(*)'];
	}

	/**
	 * Getting only one row of the query after sanitization
	 * @params      string	  The sql statement
	 * @params      array	   The array of params in query represented by '#'
	 * @params      boolean	 to return object or array
	 * @return      object or array
	 */
	public function getone($query, $params = array(), $object = true)
	{
		self::query($query.' LIMIT 1', $params);
		return ( ($object) ? (self::obj()) : (self::row()) );
	}

	/**
	 * Getting only one field of one row of the query after sanitization
	 * @params      string	  The sql statement
	 * @params      array	   The array of params in query represented by '#'
	 * @params      boolean	 to return object or array
	 * @return      object or array
	 */
	public function getonef($query, $params = array(), $field)
	{
		self::query('select `'.$field.'` from @ where '.$query.' LIMIT 1', $params);
		return self::obj()->$field ;
	}

	/**
	 * Getting a certain row of the query after sanitization
	 * @params      string	  The sql statement
	 * @params      array	   The array of params in query represented by '#'
	 * @params      int	     The number of that certain query
	 * @params      boolean	 to return object or array
	 * @return      object or array
	 */
	public function getcertain($num, $query, $params = array(), $object = true)
	{
		self::query($query.' LIMIT '.($num-1).','.$num, $params);
		return ( ($object) ? (self::obj()) : (self::row()) );
	}

	/**
	 * Getting all rows of the query after sanitization
	 * @params      string	  The sql statement
	 * @params      array	   The array of params in query represented by '#'
	 * @params      boolean	 to return object or array
	 * @return      array of objects or arrays
	 */
	public function getall($query, $params = array(), $object = true)
	{
		$ret = array();
		self::query($query, $params);

		if(is_resource(self::$result))
		{
			for($i = 0;$i < self::count();$i++)
				$ret[$i] = ( ($object) ? (self::obj()) : (self::row()) );
		}

		return $ret;
	}

	/**
	 * Getting limit of rows of the query after sanitization
	 * @params      string	  The sql statement
	 * @params      array	   The array of params in query represented by '#'
	 * @params      boolean	 to return object or array
	 * @return      array of objects or arrays
	 */
	public function getlimit($num, $query, $params = array(), $num2=0, $object = true)
	{
		$ret = array();
		self::query($query." LIMIT ".$num2.",".$num, $params);

		if(is_resource(self::$result))
		{
			for($i = 0;$i < self::count();$i++)
				$ret[$i] = ( ($object) ? (self::obj()) : (self::row()) );
		}

		return $ret;
	}

	/**
	 * Destructor of php class
	 */
	public function __destruct(){}

}

/**
 * Documentation:
 *
 * //charset for your database
 * $charset = "utf-8";
 *
 * //connection params
 * $connection = array(DBHOST,DBUSER,DBPASS,DBNAME,DBPORT);
 *
 * //error handling
 *
 * //If you are using an error class named "errorclass" with a function in it name "set" to set error
 * $error = array("errorclass","set");
 *
 * //If you are using an error function (not a part of error class) named "errorfunction"
 * $error = array("errorfunction");
 *
 * //If you want the script to die set die flag in class to true. for throwing new exception set it to false.
 *
 * $db = new sundb($charset, $connection, $error);
 *
 * //To get a row in array after querying you need to set the third param as false.
 * $resarr = $db->getone("select * from table where field1= # and field2= #",array(var1,var2),false);
 *
 */
?>
