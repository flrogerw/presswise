<?php
/**
 * Presswise
 *
 * @package Presswise
 * @subpackage Processing
 * @author gWilli
 * @version 1.0
 * @name Presswise
 * @copyright 2013
 */
/**
 * Factory Class for Processing Single Presswise Order
 *
 * Creates XML Document for Presswise API
 *
 * @uses Constants
 * @uses PDF
 * @uses PresswiseDB
 * @package Presswise
 * @subpackage Processing
 * @final Can NOT Extend
 */
final class Presswise
{
	/**
	 * Current Order Id
	 * @access public
	 * @var string
	 */
	public $orderId;

	/**
	 * Final Order XML
	 * @access public
	 * @var string
	 */
	public $orderXML;

	/**
	 * Flags Errors Encountered in the Proccess
	 * @access public
	 * @var boolean
	 */
	public $isErrors = false;

	/**
	 * Flags Errors Encountered in the Proccess
	 * @access public
	 * @var boolean
	 */
	public $errorCount = 0;

	/**
	 * Source of the Order [Fotobar Web, Fotobar Store #, DYR, etc...]
	 * @access private
	 * @var string
	 */
	private $_orderSource;

	/**
	 * Presswise XML Object
	 * @access private
	 * @var DomDocument Object
	 */
	private $_presswiseXML;

	/**
	 * Array of Error Messages
	 * @access private
	 * @var array
	 */
	private $_errors = array();

	/**
	 * Holds the Order Data Array
	 * @access private
	 * @var array
	*/
	private $_orderArray = array();

	/**
	 * Object Constructor
	 * Set to Private to Force Use of the Factory Method
	 *
	 * @param array $aOrderData - Order Array
	 * @access private
	 * @return void
	*/
	private function __construct( array $aOrderData )
	{
		$this->_orderSource = $aOrderData['source'];
		$this->_orderArray = $aOrderData;
		$this->_setPageCount();
		$this->_presswiseXML = new DomDocument('1.0', 'ISO-8859-1');
		$this->_presswiseXML->formatOutput = TRUE;
		$this->_presswiseXML->preserveWhiteSpace = false;
		$this->_getOrderXML();
	}

	/**
	 * Factory Method for Constructing Presswise Objects
	 *
	 * @static
	 * @access public
	 * @param array $aOrderData - Item Order Data
	 * @return Presswise Object
	 */
	public static function factory( array $aOrderData )
	{
		return new Presswise( $aOrderData );
	}

	/**
	 * Returns Process Errors
	 *
	 * @access public
	 * @return array
	 */
	public function getErrors() {
		return( $this->_errors );
	}

	/**
	 * For SOAP - Post Order Directly to Presswise
	 *
	 * @access public
	 * @return void
	 */
	public function postSoapOrder() {

		try{

			$oAuth = $this->_getSoapAuthObj();
			$aItem = $this->_getSoapItemObj();

			$oSoapAuthObj = new SoapVar($oAuth, SOAP_ENC_OBJECT, "auth");
			$oSoapOrderObj = new SoapVar($aItem, SOAP_ENC_OBJECT, "data");

			$client = new SoapClient(NULL, array('location' => PRESSWISE_ENDPOINT,
					'trace' => true,
					'encoding' => 'ISO-8859-1',
					'uri' => "http://test-uri/"));

			$result = $client->add_purchase_order($oSoapAuthObj, $oSoapOrderObj);

			if( $result['result']['status']['code'] != 0 ){
				$this->isErrors = true;
				$this->_errors['API Error'] =  $result['result']['status']['text'];
			}
		} catch( Exception $e ) {
			PresswiseDb::logError( $e );
			$this->isErrors = true;
			$this->_errors = $e->getMessage();
			return( false );
		}
	}

	/**
	 * Builds Authentication Array Object for SOAP Post
	 *
	 * @access private
	 * @return ArrayObject
	 */
	private function _getSoapAuthObj() {

		$aAuthArray = array(
				'user' => PRESSWISE_USER,
				'pass' => PRESSWISE_PASS,
				'code' => PRESSWISE_CODE);

		return( new ArrayObject($aAuthArray) );
	}

	/**
	 * Builds array object used for SOAP Post XML
	 *
	 * @access private
	 * @return ArrayObject
	 */
	private function _getSoapItemObj() {

		$aItemArray = array(
				'source' => $this->_orderArray['source'],
				'sourceOrderID' => $this->_orderArray['sourceOrderID'],
				'shipMethod' => 'Alternative',
				'item' => array(
						'quantity' => $this->_orderArray['quantity'],
						'productID' => $this->_orderArray['productID'],
						'productDesc' => $this->_orderArray['productDesc'],
						'fileF' => array( 'href' => $this->_orderArray['fileF']),
						'customerApproval' => 'accepted',
						'sourceJobID' => $this->_orderArray['sourceJobId'],
						'numPages' => $this->_orderArray['pageCount'],
				));

		return( new ArrayObject($aItemArray) );
	}

	/**
	 * Sets Number of Pages Contained Within the Order PDF.  The Functions Looks
	 * Up the SKU in the multi_page_sku Database Table, if Present, Downloads the PDF
	 * and Gets the Page Count.  If the SKU is Not in the Table, Defaults to 1.
	 *
	 *  @access private
	 *  @return void
	 */
	private function _setPageCount(){

		$this->_orderArray['pageCount'] = 1;

		if( PresswiseDB::getInstance()->isMultiImageSku( $this->_orderArray['productID'] ) === true ) {

			file_put_contents( PDF_TMP_DIR . DIRECTORY_SEPARATOR . PDF_CURRENT_PDF, file_get_contents( $this->_orderArray['fileF'] ) );

			$p = PDF_new();
			PDF_set_parameter($p, 'errorpolicy', 'return' );
			PDF_set_parameter($p, 'SearchPath', PDF_TMP_DIR );

			$doc = PDF_open_pdi_document( $p, PDF_CURRENT_PDF, 'requiredmode=minimum' );

			if ($doc == 0) {
				$this->isErrors = true;
				$this->_errors = 'Error Counting Pages: ' . PDF_get_errmsg( $p );
			} else {
				$this->_orderArray['pageCount'] = PDF_pcos_get_number( $p, $doc, 'length:pages' );
			}

		}
	}

	/**
	 * Create Presswise XML from DataBase Array
	 *
	 * @access private
	 * @return void
	 */
	private function _getOrderXML() {

		$this->_validateOrder();
		if($this->isErrors){
			$this->_logErrorsXML();
		}
		$this->orderId = $this->_orderArray['sourceOrderID'];
		$this->_setXML();
		$this->orderXML = $this->_presswiseXML->saveXML();
	}

	/**
	 * Validate Order Fields for NULL values
	 *
	 * @access private
	 * @return void
	 */
	private function _validateOrder() {

		$aIgnore = array( 'size', 'fileF' );

		foreach( $this->_orderArray as $key => $value ) {
			if( empty( $value ) && !in_array( $key, $aIgnore ) ){
				$this->isErrors = true;
				$this->errorCount += 1;
			}
		}
		if( $this->isErrors ){
			$this->_errors = $this->_orderArray;
		}
	}

	/**
	 * Create XML from Order Array for Reporting Purposes Only.  This is NOT where
	 * the XML for the actual soap call is generated.
	 *
	 * @access private
	 * @return void
	 */
	private function _setXML() {

		$order = $this->_presswiseXML->createElement('content');
		$source = $this->_presswiseXML->createElement('source', $this->_orderArray['source']);
		$order->appendChild($source);
		$item = $this->_presswiseXML->createElement('item');
		$sourceJobId = $this->_presswiseXML->createElement('sourceJobId', $this->_orderArray['sourceJobId']);
		$item->appendChild($sourceJobId);
		$productDesc = $this->_presswiseXML->createElement('productDesc', htmlspecialchars( $this->_orderArray['productDesc'] ) );
		$item->appendChild($productDesc);
		$sku = $this->_presswiseXML->createElement('sku', $this->_orderArray['productID']);
		$item->appendChild($sku);
		$customerApproval = $this->_presswiseXML->createElement('customerApproval', 'accepted');
		$item->appendChild($customerApproval);
		$productID = $this->_presswiseXML->createElement('productID', $this->_orderArray['productID']);
		$item->appendChild($productID);
		$quanity = $this->_presswiseXML->createElement('quanity', $this->_orderArray['quantity']);
		$item->appendChild($quanity);
		$fileF = $this->_presswiseXML->createElement('fileF', $this->_orderArray['fileF']);
		$item->appendChild($fileF);
		$pageCount = $this->_presswiseXML->createElement('numPage', $this->_orderArray['pageCount']);
		$item->appendChild($pageCount);
		$order->appendChild($item);
		$shipMethod = $this->_presswiseXML->createElement('shipMethod', 'Alternative');
		$order->appendChild($shipMethod);
		$sourceOrderID = $this->_presswiseXML->createElement('sourceOrderID', $this->_orderArray['sourceOrderID']);
		$order->appendChild($sourceOrderID);
		$this->_presswiseXML->appendChild($order);

	}

	/**
	 * Creates Error XML for Reporting
	 *
	 * @access private
	 * @return void
	 */
	private function _logErrorsXML() {
		if( !$this->isErrors ){
			return;
		}

		$errors = $this->_presswiseXML->createElement('BadOrder');
		$errors->setAttribute( 'orderId', $this->_errors['sourceJobId'] );
		$errors->setAttribute( 'errorCount', $this->errorCount );

		foreach( $this->_errors as $key=>$value ){
			if( is_numeric( $key) ){
				continue;
			}
			if( empty( $value ) ){
				$errorNode = $this->_presswiseXML->createElement( $key, $value);
				$errors->appendChild($errorNode);
			}
		}

		$this->_presswiseXML->appendChild( $errors );
	}
}