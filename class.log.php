<?php
require_once __DIR__ . '/../utils/utils.php';

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

  // file permissions
  const DEFAULT_PERMS = '0777';

  // True will print messages to STDOUT
  public $vocal = False;

  public $log_sender;
  public $log_recipient = '';

  private $_prefix = '';

  // A few helpful shortcuts for formatting the time
  const FORMAT_YMD_SEC = 'y-m-d H:i:s';
  const FORMAT_YMD_MSEC = 'y-m-d H:i:s:u';
  private $_timeformat = self::FORMAT_YMD_SEC;

  private $_line_format = '%time$s %prefix$s %level$s %msg$s'; // Set in __construct

  // The internal status, one of three constants.
  private $_status = '';
  const _WRITE_FAILED = 0;
  const _OPEN_FAILED = 1;
  const _LOG_OPEN = 2;

  private $_messages = array(
      'writefail'   => 'The log file could not be written to. Check that appropriate permissions have been set.',
      'opensuccess' => 'The log file was opened successfully.',
      'openfail'    => 'The file could not be opened. Check permissions.',
  );

  function __construct($log_file, $log_level, $params=array()) {
    $this->_log_file = $log_file;
    $this->_log_level = $log_level;

    $this->_set_params($params);

    $this->const_keys = $this->_const_keys();

    // Create the directory if none exists.
    $logdir = dirname($log_file);
    if (!file_exists($logdir)) {
      mkdir($logdir, self::DEFAULT_PERMS, true);
    }

    if (file_exists($this->_log_file) && !is_writable($this->_log_file)) {
        $this->_status = self::_WRITE_FAILED;
        $this->vocal = true;
        $this->err($this->_messages['writefail']);
        return;
    }

    if (($this->_fileHandle = fopen($this->_log_file, 'a'))) {
        $this->_status = self::_LOG_OPEN;
        $this->debug($this->_messages['opensuccess']);

    } else {
        $this->_status = self::_OPEN_FAILED;
        $this->vocal = true;
        $this->err($this->_messages['openfail']);
    }
  }

  private function _const_keys() {
    $reflection = new ReflectionClass($this);
    $consts = $reflection->getConstants();
    return array_combine($consts, array_keys($consts));
  }

  private function _set_param($name, $value) {
    if (isset($this->{$name})) {
      $this->{$name} = strval($value);
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
    
    try {
      $string = $this->_encode_for_log($str, $level);

      if ($this->vocal === true)
        echo $string;

      if ($this->_status == self::_LOG_OPEN && $level <= $this->_log_level)
        $this->_write_line($string, $level);

      } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL . $str;
      }
  }

  public function debug($str) {
    $this->log($str, self::DEBUG);
  }

  public function info($str) {
    $this->log($str, self::INFO);
  }

  public function notice($str) {
    $this->log($str, self::NOTICE);
  }

  public function warn($str) {
    $this->log($str, self::WARN);
  }

  public function error($str) {
    $this->log($str, self::ERR);
  }

  public function err($str) {
    $this->error($str);
  }

  public function critical($str) {
    $this->log($str, self::CRIT);
  }

  public function emergency($str) {
    $this->log($str, self::EMERG);
  }

  private function _encode_for_log($str, $level) {
    $d = new DateTime();

    $args = array(
      'time' => $d->format($this->_timeformat),
      'msg' => $str,
      'prefix' => $this->_prefix
    );

    // used named level if called for
    if (strpos($this->_line_format, '%level$s'))
      $args['level'] = $this->const_keys[$level];

    return sprintfn($this->_line_format . PHP_EOL, $args);
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
    $this->_prefix = trim(strval($prefix)) . " ";
  }

  public function set_time_format ($format) {
    $this->_timeformat = strval($format);

  }

  private function get_logfile() {
    return file_get_contents($this->_log_file);
  }

  // Delete the log
  private function _truncate_log() {
    $f = fopen($this->_log_file,'w');
    if ($f === false)
      throw new Exception("error truncating log file");
    fclose($f);
  }

  // Send off the log.
  private function _email($log) {
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
   * @param int $hour The hour on 24 hour scale, e.g. 13 for 1 pm, 0 for midnight
   * @param int $min The maximum minutes past the hour to send the log.
   * Default to 60, blank vals will send anytime in $hour. For scripts that run four times and hour, set to something under 15.
   * @return void
  */
  public function email_log($day, $hour, $min=60) {
    $cur_hr = intval(date('G'));
    $cur_min = intval(date('i'));
    if ( date('l') == $day && $cur_hr == $hour && $cur_min <= $min ):
      try {
        $log = $this->get_logfile();

        if ($log !== ''):
          $this->_email($log);
          $this->_truncate_log();
        endif;

      } catch(Exception $e) {
        // Useful if server is set to email any result.
        echo $e->getMessage();
      }
    endif;
  }
}

?>