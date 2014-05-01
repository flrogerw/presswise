<?php
/**
 * PresswiseDB
 *
 * @package Presswise
 * @subpackage Database
 * @author gWilli
 * @version 1.0
 * @copyright 2012
 * @name PresswiseDB
 */
/**
 * Presswise Database Methods
 *
 * Creates Presswise Singleton DataBase Object
 *
 * @uses Constants
 * @uses PDO
 * @package Presswise
 * @subpackage Database
 * @final Can NOT Extend
 */
final class PresswiseDB
{

	/**
	 * Singleton instance
	 * @access protected
	 * @staticvar PresswiseDB
	 */
	protected static $_instance = null;

	/**
	 * DataBase Resource
	 * @var object
	 * @access private
	 */
	private $_dbHandle;

	/**
	 * PressWise DataBase Resource
	 * @var object
	 * @access private
	 */
	private $_presswiseDB;

	/**
	 * DataBase Query Result Object
	 * @var object
	 * @access private
	 */
	private $_dbResults;

	/**
	 * Holds the DataBase results Array After Validation
	 * @var array
	 * @access private
	 */
	private $_dbResultsArray = array();

	/**
	 * Singleton pattern implementation makes "clone" unavailable
	 * @access protected
	 * @return void
	*/
	protected function __clone()
	{
	}

	/**
	 * Singleton pattern implementation makes "new" unavailable
	 * @access protected
	 * @return void
	 */
	private function __construct()
	{
		$this->_getDbHandle();
		$this->_getPresswiseDB();
	}

	/**
	 * Returns an instance of Class_Currency
	 * @static
	 * @access public
	 * @return Class_User Provides a fluent interface
	 */
	public static function getInstance()
	{
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Get the Local Database Resource
	 *
	 * @access private
	 * @return void
	 */
	private function _getDbHandle(){

		$this->_dbHandle = new PDO('mysql:host='.FOTOBAR_DB_HOST.';dbname='.FOTOBAR_DB_NAME, FOTOBAR_DB_USER, FOTOBAR_DB_AUTH );
	}

	/**
	 * Get the Presswise Database Resource
	 *
	 * @access private
	 * @return void
	 */
	private function _getPresswiseDB(){
		$this->_presswiseDB = new PDO('mysql:host='.PRESSWISE_DB_HOST.';dbname='. PRESSWISE_DB_NAME, PRESSWISE_DB_USER, PRESSWISE_DB_AUTH );
	}

	/**
	 * Closes Database Connections
	 *
	 * @access public
	 * @return void
	 */
	public function close_dbHandle() {
		$this->_dbHandle = null;
		$this->_presswiseDB = null;
	}

	/**
	 * Insert PQM Order Data Into the Database
	 *
	 * @access public
	 * @param array $aInsertData
	 */
	public function insertToQueue( array $aInsertData ){

		$sSql = "INSERT INTO queue (orderno, source, sku, sku_name,quantity,pdf,product_type,printer_type,printer_code,hot_folder,status,status_type,user,order_item_id,order_date,zipfile_name,code,size ) VALUES (:orderno, :source, :sku, :sku_name,:quantity,:pdf,:product_type,:printer_type,:printer_code,:hot_folder,:status,:status_type,:user,:order_item_id,:order_date,:zipfile_name,:code,:size)";
		$sth = $this->_dbHandle->prepare( $sSql);
		$sth->execute( $aInsertData );

	}

	/**
	 * Log Presswise Process Results into the Database
	 *
	 * @param int $iTotalOrders
	 * @param int $iErrorCount
	 * @param string $sResultXML
	 * @access public
	 * @return void
	 */
	public function logProcessResults( $iTotalOrders, $iErrorCount, $sResultXML ){

		$sql = "INSERT INTO presswise_process_log (process_total, process_errors, process_xml ) VALUES (:process_total, :process_errors, :process_xml)";
		$q = $this->_dbHandle->prepare( $sql );
		$q->execute( array(
				':process_total' => $iTotalOrders,
				':process_errors' => $iErrorCount,
				':process_xml' => $sResultXML));

	}

	/**
	 * Scheduled Task to Update Activa PQM Queue with Current Presswise Status
	 *
	 * @access public
	 * @return boolean
	 */
	public function updatePrintedOrders() {

		try{

			$sth = $this->_presswiseDB->prepare("SELECT sourceJobID FROM queue_job WHERE modified >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND sourceJobID <>''");

			$sth->execute();
			$aResults = $sth->fetchAll(PDO::FETCH_NUM);
			if( !empty( $aResults ) ) {
				$aFlattenedResults = implode("','", iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($aResults)), false) );
				$sql = "UPDATE queue SET status='Completed' WHERE id IN ('$aFlattenedResults')";
				$sth = $this->_dbHandle->prepare($sql);
				$sth->execute(array( $aFlattenedResults ) );
			}

			$sth = $this->_presswiseDB->prepare("SELECT DISTINCT(sourceJobID), queue_order.`status` FROM queue_job JOIN queue_order USING (orderID) WHERE queue_order.lastModified >= DATE_SUB(NOW(), INTERVAL 1 YEAR) AND queue_order.`status` LIKE \"%Exception%\" AND sourceJobID <> ''");

			$sth->execute();
			$aResults = $sth->fetchAll(PDO::FETCH_NUM);
			if( !empty( $aResults ) ) {
				$aFlattenedResults = implode("','", iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($aResults)), false) );
				$sql = "UPDATE queue SET status='Exception' WHERE id IN ('$aFlattenedResults')";
				$sth = $this->_dbHandle->prepare($sql);
				$sth->execute(array( $aFlattenedResults ) );
			}

			return( true );

		}catch( Exception $e ){
			self::logError( $e );
			// ERROR ACTION
			return( false );
		}
	}

	/**
	 * Returns Orders Based on Status of Pending or Engraving
	 *
	 * @access public
	 * @return array
	 */
	public function getOrders()
	{
		try{
			$sth = $this->_dbHandle->prepare("SELECT orderno as sourceOrderID,
					id as sourceJobId,
					source,
					sku as productID,
					sku_name as productDesc,
					quantity,
					pdf as fileF,
					size
					FROM queue WHERE status IN ('Pending','Engraving')");

			$sth->execute();
			$this->_dbResults = $sth->fetchAll(PDO::FETCH_ASSOC);

			$resultSet = $this->_dbResults;

			if( sizeof( $resultSet ) > 0 ){
				$this->_setOrdersSent( $resultSet );
			}
			return( $this->_dbResults );

		}catch( Exception $e ){
			self::logError( $e );
			throw new Exception( 'Could NOT Get Order Information From DB' );
		}
	}

	/**
	 * Sets status to Sent to eliminate duplicate orders
	 *
	 * @param array $resultSet
	 * @throws Exception
	 * @access private
	 */
	private function _setOrdersSent( array $resultSet ){

		$aOrdersToSet = array();

		foreach( $this->_dbResults as $aOrder ){
			$aOrdersToSet[] = $aOrder['sourceJobId'];
		}
		$sql = "UPDATE queue SET status='Completed' WHERE id IN ('" . implode("','", $aOrdersToSet ) . "')";
		$sth = $this->_dbHandle->prepare($sql);
		$sth->execute();
	}

	/**
	 * Returns true if the Parameter is in the Database table is_multi_image, false if not.
	 * Used to determin if the Order PDF needs a Page Count.
	 *
	 * @param int $iSku
	 * @throws Exception
	 * @access public
	 * @return boolean
	 */
	public function isMultiImageSku( $iSku ){

		try{
			$sth = $this->_dbHandle->prepare("SELECT sku FROM is_multi_image WHERE sku = ?");

			$sth->execute( array( $iSku ) );
			$this->_dbResults = $sth->fetchColumn();
			$bReturn = ( $this->_dbResults === false )? false: true;
			return( $bReturn );

		}catch( Exception $e ){
			self::logError( $e );
			throw new Exception( 'Could NOT SKU Information From DB' );
		}
	}

	/**
	 * Logs System Exceptions to DataBase
	 *
	 * @access public
	 * @param object $exception
	 * @return void
	 */
	public static function logError( $exception )
	{

		try{

			$connection = new PDO('mysql:host='.FOTOBAR_DB_HOST.';dbname='.FOTOBAR_DB_NAME, FOTOBAR_DB_USER, FOTOBAR_DB_AUTH );
			$sql = "INSERT INTO presswise_error_log (message, file, line, trace) VALUES (:message,:file,:line,:trace)";
			$q = $connection->prepare($sql);
			$q->execute(array(
					':message'=>$exception->getMessage(),
					':file'=>$exception->getFile(),
					':line'=>$exception->getLine(),
					':trace'=>$exception->getTraceAsString()));

		}catch( Exception $e){
			var_dump($e);
		}
	}

	/**
	 * Overrides Magic Method __get
	 *
	 * @access public
	 * @param mixed $param
	 * @return mixed
	 */
	public function __get( $param )
	{
		return $this->$param;
	}
}
