#!/usr/bin/php
<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
require_once( __DIR__ . DIRECTORY_SEPARATOR . 'Constants.php' );


/**
 * PHP Script that Instantiates the Presswise Process
 *
 * @package Presswise
 * @author gWilli
 * @version 1.0
 * @name Controller Action
 * @copyright 2013
 * @uses Constants
*/

spl_autoload_register(function ($sClass) {
	$sClass = str_replace( "_", DIRECTORY_SEPARATOR, $sClass );
	if( file_exists( __DIR__ . DIRECTORY_SEPARATOR . $sClass . '.php' ) ){
		require_once( __DIR__ . DIRECTORY_SEPARATOR . $sClass . '.php' );
	}
});

	// See if Process is Already Running
	$pid = new Pid( '/tmp', basename( __FILE__ ) );

	if( !$pid->already_running ) {

		try {

			$oPresswiseProcess = new ProcessPresswise();
						
			$oPresswiseProcess->processSoap();
			PresswiseDb::getInstance()->updatePrintedOrders();

			if( $oPresswiseProcess->ordersProcessed() > 0 ){
				PresswiseDb::getInstance()->logProcessResults( $oPresswiseProcess->ordersProcessed(), $oPresswiseProcess->errorCount(), $oPresswiseProcess->getReport() );
			}
		} catch (Exception $e) {
			PresswiseDb::logError( $e );
		}
	}
	return(1);