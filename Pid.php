<?php
/**
 * Pid
 *
 * @package Presswise
 * @subpackage Utilities
 * @author gWilli
 * @version 1.0
 * @name Pid
 * @copyright 2013
 */
/**
 * Class for Maintaining Single Process Instance of a Given Running Application
 *
 * Creates a PID file in a Supplied Directory for the current Running Process.  With Each Instantiation,
 * the Class Looks for the Process PID File and Sets a Parameter for a 'isRunning' Condition.  This Ensures Only
 * One Instance of the Application Can Run at Any Given Time.
 *
 * @uses Constants
 * @package Presswise
 * @subpackage Utilities
 * @final Can NOT Extend
 */
final class Pid {

	/**
	 * Name of the PID file
	 * 
	 * @access protected
	 * @var string
	 */
	protected $filename;
	
	/**
	 * Process ID
	 * 
	 * @access protected
	 * @var int
	 */
	protected $_pid;
	
	/**
	 * Name of Process
	 * 
	 * @access protected
	 * @var string
	 */
	protected $_processName;
	
	/**
	 * Is the Process Already Running
	 * 
	 * @access public
	 * @var boolean
	 */
	public $already_running = false;

	/**
	 * Class Constructor
	 * 
	 * @param string $directory - Where the PID file is written
	 * @param string $processName - Name of the running process
	 * @throws Exception
	 */
	public function __construct( $directory, $processName ) {

		$this->_processName = $processName;
		$this->filename = $directory . '/' . basename($_SERVER['PHP_SELF']) . '.pid';

		if(is_writable($this->filename) || is_writable($directory)) {
				
			if(file_exists($this->filename)) {
				$this->_pid = (int)trim(file_get_contents($this->filename));
				if( posix_kill( $this->_pid, 0 ) && $this->_validatePid() ) {
					$this->already_running = true;
				}
			}
				
		}
		else {
			throw new Exception("Cannot write to pid file '$this->filename'");
		}

		if(!$this->already_running) {
			$pid = getmypid();
			file_put_contents($this->filename, $pid);
		}

	}

	/**
	 * Removes Extinct PID files that May Have Been Left Behind After an UnGraceful Exit
	 * 
	 * @return void
	 */
	protected function _removeZombie(){
		if( file_exists( $this->filename ) ) {
			unlink( $this->filename );
		}
	}

	/**
	 * Checks the Server for the Exsistance of the PID and That it is Assigned to the Current Running Process
	 * 
	 * @access protected
	 * @return boolean
	 */
	protected function _validatePid(){
		exec( "pgrep {$this->_processName}", $output, $return );
		foreach( $output as $oi => $o ) {
			if( $o == $this->_pid ){
				return( true );
			}
		}
		$this->_removeZombie();
		return( false );
	}

	/**
	 * Overrides Magic Method __destruct and Removes the Current PID File.
	 * 
	 * @access public
	 * @return void
	 */
	public function __destruct() {

		if(!$this->already_running && file_exists($this->filename) && is_writeable($this->filename)) {
			unlink($this->filename);
		}
	}		
}
