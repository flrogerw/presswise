<?php
/**
 * ProcessPresswise 
 *
 * @package Presswise
 * @subpackage Processing
 * @author gWilli
 * @version 1.0
 * @name ProcessPresswise
 * @copyright 2013
 */
/**
 * Processes Orders for Presswise
 *
 * Process Multiple Instances of Presswise Orders
 *
 * @uses Constants
 * @uses PresswiseDB
 * @package Presswise
 * @subpackage Processing
 * @final Can NOT Extend
 */
final class ProcessPresswise
{
	/**
	 * Total Number of Orders Consumed from DataBase
	 * @access private
	 * @var int
	 */
	private $_orderCount = 0;

	/**
	 * DataBase Connection
	 * @access private
	 * @var PresswiseDb Object
	 */
	private $_dbHandle;

	/**
	 * Total Orders with Successful Outcome
	 * @access private
	 * @var int
	 */
	private $_ordersGood = 0;

	/**
	 * Total Orders with UnSuccessful Outcome
	 * @access private
	 * @var int
	 */
	private $_ordersBad = 0;

	/**
	 * Error Trigger
	 * @access private
	 * @var bool
	 */
	private $_isErrors = false;

	/**
	 * Array of Orders to be Processed
	 * @var array
	 */
	private $_orderArray = array();

	/**
	 * Contains Error Reason and Order XML
	 * @access private
	 * @var array
	*/
	private $_errorXMLArray = array();

	/**
	 * Report Builder DomDocument Object
	 * @access private
	 * @var DomDocument Object
	*/
	private $_reportXML;

	/**
	 * Holds Process Success Array
	 * @access private
	 * @var array
	 */
	private $_successArray = array();


	/**
	 * Object Constructor
	 *
	 * @access public
	 * @return void
	*/
	public function __construct()
	{
		$this->_dbHandle = PresswiseDb::getInstance();
		$this->_orderArray = $this->_dbHandle->getOrders();
		$this->_reportXML = new DomDocument('1.0', 'ISO-8859-1');
		$this->_reportXML->formatOutput = TRUE;
		$this->_reportXML->preserveWhiteSpace = false;
		$xslt = $this->_reportXML->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="./presswise.xsl"');
		$this->_reportXML->appendChild($xslt);
	}

	/**
	 * Returns number of errors for reporting purposes
	 *
	 * @access public
	 * @return number
	 */
	public function errorCount(){

		return( $this->_ordersBad );
	}

	/**
	 * Returns number of errors for reporting purposes
	 *
	 * @access public
	 * @return number
	 */
	public function ordersProcessed(){

		return( $this->_ordersBad + $this->_ordersGood );
	}

	/**
	 * Returns a Report of the Current Processing
	 *
	 * @access public
	 * @return string
	 */
	public function getReport() {

		$report = $this->_reportXML->createElement('Report');
		$report->setAttribute( 'date', date(DATE_RFC822) );

		$summary = $this->_reportXML->createElement('Summary');

		$ordersProcessed = $this->_reportXML->createElement('OrdersProcessed');
		$totalOrders = $this->_reportXML->createElement('TotalOrders', $this->_orderCount );
		$ordersProcessed->appendChild( $totalOrders );
		$goodOrders = $this->_reportXML->createElement('Success', $this->_ordersGood );
		$ordersProcessed->appendChild( $goodOrders );
		$badOrders = $this->_reportXML->createElement('Failures', $this->_ordersBad );
		$ordersProcessed->appendChild( $badOrders );
		$summary->appendChild( $ordersProcessed );
		$report->appendChild( $summary );

		$details = $this->_reportXML->createElement('SuccessDetails');

		foreach( $this->_successArray as $sGoodOrder ) {

			$success = $this->_reportXML->createElement('Success');
			$text = $this->_reportXML->createTextNode( $sGoodOrder );
			$success->appendChild( $text );

			$details->appendChild( $success );
		}

		$report->appendChild( $details );

		$details = $this->_reportXML->createElement('ErrorDetails');

		foreach( $this->_errorXMLArray as $aBadOrder ) {

			$error = $this->_reportXML->createElement('Error');
			$error->setAttribute( 'ErrorType', implode(',', $aBadOrder['error']) );
			$sOrderXML = $this->_reportXML->createElement('OrderXML');
			$cData = $this->_reportXML->createCDATASection($aBadOrder['xml']);
			$sOrderXML->appendChild( $cData );
			$error->appendChild( $sOrderXML );
			$details->appendChild( $error );
		}


		$report->appendChild( $details );
		$this->_reportXML->appendChild( $report );

		$xsl = new DOMDocument;
		$xsl->load( __DIR__ . DIRECTORY_SEPARATOR . 'presswise.xsl');
		$proc = new XSLTProcessor;
		$proc->importStyleSheet($xsl);
		return( $proc->transformToXML($this->_reportXML) );
	}


	/**
	 * Process the Current Orders for Posting to Presswise SOAP
	 *
	 * @access public
	 * @return void
	 */
	public function processSoap() {

		foreach($this->_orderArray as $aOrderData ) {
			$this->_orderCount += 1;
			$oOrder = Presswise::factory( $aOrderData );			
			
			if( $oOrder->isErrors ) {
				$this->_isErrors = true;
				$this->_ordersBad += 1;
				$this->_errorXMLArray[] = array('error' => $oOrder->getErrors(), 'xml' =>  $oOrder->orderXML  );

			} else {
				$oOrder->postSoapOrder();
				if( $oOrder->isErrors ) {
					$this->_ordersBad += 1;
					$this->_errorXMLArray[] = array('error' => $oOrder->getErrors(), 'xml' =>  $oOrder->orderXML );
				} else {
					$this->_ordersGood += 1;
					$this->_successArray[] = $oOrder->orderId;
				}
			}
		}

		if( $this->_ordersBad > 0 ){
			$this->_sendAlertEmail();
		}
	}

	/**
	 * Send Alert Email on Process Error
	 *
	 * @access private
	 * @return void
	 */
	private function _sendAlertEmail(){

		require_once 'Mail.php';
		require_once 'Mail/mime.php';

		$crlf = "\n";
		$hdrs = array(
				'To'      => 'Presswise Admin',
				'From'    => 'presswise@polaroidfotobar.com',
				'Subject' => 'PressWise Process Error Report'
		);

		$mime = new Mail_mime(array('eol' => $crlf));
		$mime->setHTMLBody( $this->getReport() );

		$body = $mime->get();
		$hdrs = $mime->headers($hdrs);

		$smtp = array(
				'host' => SMTP_HOST,
				'port' => SMTP_PORT,
				'auth' => true,
				'username' => SMTP_USER,
				'password' => SMTP_AUTH
		);

		$mail=& Mail::factory("smtp", $smtp);
		$mail->send( ALERT_EMAIL, $hdrs, $body );

	}
}