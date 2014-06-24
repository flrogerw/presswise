<?php 
/**
 * ProcessPqm
 *
 *@deprecated
 *@deprecated
 *
 * @package Presswise
 * @subpackage Processing
 * @author gWilli
 * @version 1.0
 * @name ProcessPqm
 * @copyright 2013
 */
/**
 * Include the AWS SDK
 */
//require 'AWSSDKforPHP/aws.phar';

/**
 * Include the RackSpace API
 */
require_once( 'vendor/autoload.php' );
/**
 * Processes PQM Zip Files
 *
 * Process PQM Zip Files, Unzip, Parse and Insert Data Into Pesswise Queue
 *
 * @uses AWS\S3Client
 * @uses AWS\UploadSyncBuilder
 * @uses Constants
 * @uses PresswiseDB
 * @package Presswise
 * @subpackage Processing
 * @final Can NOT Extend
*/
final class ProcessPqm
{
	//use Aws\S3\S3Client;
	//use Aws\S3\Sync\UploadSyncBuilder;
	use OpenCloud\Rackspace;

	/**
	 * @access private
	 * @var RS Cloud File Container Object
	 */
	private $_container;

	/**
	 * DataBase Connection
	 * @access private
	 * @var PresswiseDb Object
	 */
	private $_dbHandle;

	/**
	 * AWS S3 Resource
	 * @deprecated
	 * @access private
	 * @var AWS S3 Object
	 */
	private $_client;

	/**
	 * Object Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		$this->_dbHandle = PresswiseDb::getInstance();
		//$this->_client = S3Client::factory(array('key' => AWS_KEY,'secret' => AWS_SECRET));
		// Set Up RackSpace Connection
		$rsClient = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
				'username' => RS_KEY,
				'apiKey' => RS_SECRET
		));

		$objectStoreService = $rsClient->objectStoreService(null, RS_REGION);
		$this->_container = $objectStoreService->getContainer( 'converter.test' );
		//$this->_container = $objectStoreService->getContainer( PQM_CONTAINER );


	}

	/**
	 * Downloads Zip File From S3, Parses and Updates Presswise Queue Table in the Database.
	 * Then Deletes From S3.
	 *
	 * @access public
	 * @return void
	 */
	public function process(){

		try{
			//$this->_getZipsFromS3();
			$this->_getZipsFromRS();
			//$this->_unZipPqm();
			//$this->_parseOrderXml();
			//$this->_uploadPdfs();
		} catch( Exceptioin $e ) {

		}
	}

	/**
	 * Uplaods Unziped PDFs to RackSpace for Processing
	 *
	 * @return void
	 * @access private
	 */
	private function _uploadPdfs(){
			
		$fileData = fopen( PQM_DOWNLOAD_DIR . $this->_remoteFile, 'r' );
		$uploadData = $this->_container->uploadObject( $this->_remoteFile, $fileData );
		fclose( $fileData );
	}

	/**
	 * Uplaods Unziped PDFs to S3 for Processing
	 * @deprecated
	 * @return void
	 * @access private

	 private function _uploadPdfs(){

		UploadSyncBuilder::getInstance()
		->setClient( $this->_client )
		->setConcurrency( 20 )
		->setBucket( PQM_PRESSWISE_BUCKET )
		->setAcl('public-read')
		->uploadFromDirectory( PQM_DOWNLOAD_DIR )
		->build()
		->transfer();
		}
		*/

	/**
	 * Parse PQM XML Files and Create Order Array
	 *
	 * @access private
	 * @return void
	 */
	private function _parseOrderXml(){

		$xmlFiles = glob( PQM_DOWNLOAD_DIR . DIRECTORY_SEPARATOR . '*.{xml}', GLOB_BRACE);

		foreach( $xmlFiles as $file ) {

			$oOrder = simplexml_load_file( $file );
			$path_parts = pathinfo( $file );

			foreach ($oOrder->order_items->item as $item) {
					
				$aOrderData = array(
						':orderno' => (string)$oOrder->order_info->order_number,
						':source' => (string)$oOrder->source,
						':sku' => (string)$item->sku,
						':sku_name' => (string)$item->name,
						':quantity' => (string)$item->order_quantity,
						':pdf' => (string)$item->file_name,
						':product_type' => (string)$item->product_type,
						':printer_type' => null,
						':printer_code' => null,
						':hot_folder' => null,
						':status' => 'Pending',
						':status_type' => null,
						':user' => null,
						':order_item_id' => (string)$item->order_item_id,
						':order_date' => date("Y-m-d H:i:s", strtotime( str_replace( '@', '', (string)$oOrder->order_info->order_date))),
						':zipfile_name' => $path_parts['filename'] . '.zip',
						':code' => (string)$item->code,
						':size' => (string)$item->size);

				$this->_dbHandle->insertToQueue( $aOrderData );
			}
			unlink( $file );
		}
	}

	/**
	 * Unzip PQM Files From the Download Directory
	 *
	 * @return void
	 * @access private
	 */
	private function _unZipPqm(){

		$zipFiles = glob( PQM_DOWNLOAD_DIR . DIRECTORY_SEPARATOR . '*.{zip}', GLOB_BRACE);
		$zip = new ZipArchive;

		foreach( $zipFiles as $file) {

			$res = $zip->open( $file );
			if ($res === TRUE) {
				$zip->extractTo( PQM_DOWNLOAD_DIR );
				$zip->close();
			} else {
				$this->_errorXMLArray[] = array('error' => "Couldn't Open $file to UnZip" );
			}

			$path_parts = pathinfo( $file );
			rename( $file, PQM_FINAL_DIR . DIRECTORY_SEPARATOR . $path_parts['filename'] . '.zip' );
		}
	}


	/**
	 * Download PQM Zip Files From RackSpace
	 * @deprecated
	 * @access private
	 * @return boolean
	 */
	private function _getZipsFromRS() {

	 $files = $this->_container->ObjectList();

	 while($o = $files->Next())
	 {
	 	$file_name = $o->getName();
	 	$file = $this->_container->getObject($file_name);
	 	printf("** %s\n", $file->getName());

	 	if (!$fp = @fopen( PQM_DOWNLOAD_DIR . $file_name, "wb")) {
	 		$this->_errorXMLArray[] = array('error' => 'Could not open file ' . PQM_DOWNLOAD_DIR . $file_name . ' for writing');
	 	}
	 	//$retval = fwrite($fp, $o->getContent());
	 	if (fwrite($fp, $file->getContent()) === FALSE) {
	 		$this->_errorXMLArray[] = array('error' => 'Cannot write to file: ' . PQM_DOWNLOAD_DIR . $file_name );
	 	}
	 }

	}


	/**
	 * Download PQM Zip Files From S3
	 * @deprecated
	 * @access private
	 * @return boolean
	 */
	private function _getZipsFromS3() {

		$this->_client->downloadBucket( PQM_DOWNLOAD_DIR, PQM_BUCKET, null, array(
				'concurrency' => 20,
				'debug'       => false
		));
	}
}