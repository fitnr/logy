<?php

/**
  * class of logging functions
  * 
  * This loggering class should work for any twitter bot or cron job.
  * Strings are saved and output once to a file. The email_log function can be
  * set to email the log once a week on a specific day and time.
  * Some ideas and code borrowed from https://github.com/katzgrau/KLogger/.
*/
class logger
{

  /**
   * Error severity, from low to high. From BSD syslog RFC, secion 4.1.1,
   * Except for OFF, which is custom.
   * @link http://www.faqs.org/rfcs/rfc3164.html
  */
  const OFF    = -1;// Log nothing at all
  const EMERG  = 0; // Emergency: system is unusable
  const ALERT  = 1; // Alert: action must be taken immediately
  const CRIT   = 2; // Critical: critical conditions
  const ERR    = 3; // Error: error conditions
  const WARN   = 4; // Warning: warning conditions
  const NOTICE = 5; // Notice: normal but significant condition
  const INFO   = 6; // Informational: informational messages
  const DEBUG  = 7; // Debug: debug messages
  const ALL    = 10; // All error messages

  // Holds the current threshold for logging
  public $_log_level = self::INFO;
  
  // destination for these wonderful tidbits
  public $_log_file;

  // True will print messages to STDOUT
  public $vocal = False; 
  
  public $log_sender;
  public $log_recipient = '';
  
  private $_prefix = '';

  // A few helpful shortcuts for formatting the time
  const FORMAT_YMD_SEC = 'y-m-d H:i:s';
  const FORMAT_YMD_MSEC = 'y-m-d H:i:s:u';
  private $_format = self::FORMAT_YMD_SEC;

  private static $_default_perms = 0777;

  // The internal status, one of three constants.
  private $_status = '';
  const _WRITE_FAILED = 0;
  const _OPEN_FAILED = 1;
  const _LOG_OPEN = 2;

  private $_messages = array(
      'writefail'   => 'The file could not be written to. Check that appropriate permissions have been set.',
      'opensuccess' => 'The log file was opened successfully.',
      'openfail'    => 'The file could not be opened. Check permissions.',
  );

  function __construct($log_file, $log_level, $params=array()) {
    $this->_log_file = $log_file;
    $this->_log_level = $log_level;

    $this->_set_params($params);

    // Create the directory if none exists.
    $logdir = dirname($log_file);
    if (!file_exists($logdir)) {
      mkdir($logdir, self::$_default_perms, true);
    }

    if (file_exists($this->_log_file) && !is_writable($this->_log_file)) {
        $this->_status = self::_WRITE_FAILED;
        echo $this->_messages['writefail'];
        return;
    }

    if (($this->_fileHandle = fopen($this->_log_file, 'a'))) {
        $this->_status = self::_LOG_OPEN;
        $this->log($this->_messages['opensuccess'], self::DEBUG);

    } else {
        $this->_status = self::_OPEN_FAILED;
        echo $this->_messages['openfail'];
    }
  }

  private function _set_param($name, $value) {
    if (isset($this->{$name})) {
      $this->{$name} = $value;
    }
  }

  private function _set_params($params) {
    foreach ($params as $name => $value) {
      $this->_set_param($name, $value);
    }
  }

  /**
   * Save a single string to the log file
   * 
   * @param string $tr The string to be logged.
   * @paran int $level The log severity level of the string.
  */
  public function log($str, $level=self::INFO) {
    // compare passed log level to that defined in the class
    if ($this->_status == self::_LOG_OPEN && $level <= $this->_log_level):
      try {
        $string = $this->_encode_for_log($str, $this->_format);
        
        if ($this->vocal == True):
          echo $string;
        endif;

        $this->_write_line($string);

      } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL . $str;
      }
    endif;
  }

  private function _encode_for_log($str, $timeformat) {
    $str = $this->_prefix . $str;
    $d = new DateTime();

    return sprintf("%s\t%s" . PHP_EOL, $d->format($timeformat), $str);
  }

  /**
   * Writes a line to the log.
   *
   * @param string $line Line to write to the log
   * @return void
   */
  private function _write_line ($line) {
    if (fwrite($this->_fileHandle, $line) === false)
      echo $this->_messages['writefail'];
  }

  /**
   * Sets a prefix for the message part of a line
   *
   * @param string $prefix The string to precede each message
  */
  public function set_prefix ($prefix) {
    $this->_prefix = trim($prefix) . " ";
  }

  public function set_time_format ($format) {
    $this->_format = $format;
  }

  private function get_logfile() {
    return file_get_contents($this->_log_file);
  }

  // Delete the log
  private function truncate_log() {
    $f = fopen($this->_log_file,'w');
    if ($f === false)
      throw new Exception("error truncating log file");
    fclose($f);
  }

  // Send off the log.
  private function emailer($log) {
    $headers  = "From:" . $this->log_sender . "\r\n";
    $headers .= "X-Mailer:PHP " . phpversion() . "\r\n";
    $headers .= "Content-type:text/plain; charset=UTF-8";

    if (mail($this->log_recipient, $this->_log_file, $log, $headers)):
      // Do nothing.
    else:
      throw new Exception("Error mailing the log.");
    endif;
  }

  /**
   * Sends an email if the program is run on a given date and day.
   *
   * @param string $day The day of week e.g. Monday
   * @param string $hour The hour on 24 hour scale, e.g. 13 for 1 pm, 0 for midnight
   * @return void
  */
  public function email_log($day, $hour) {
    if ( date('l') == $day && date('G') == $hour ):
      try {
        $log = $this->get_logfile();
        $this->truncate_log();
      } catch(Exception $e) {
        // Useful if server is set to email any result.
        echo $e->getMessage();
      }
    endif;
  }
}
?>