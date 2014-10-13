# logy

An inoffensive little logging library.

Started a few years ago with inspiration from [KLogger](https://github.com/katzgrau/KLogger).

## Basic Use

````php
include 'src/class.logy.php';

date_default_timezone_set('America/New_York');

$logger = new fitnr\logger\logy('file.log', fitnr\logger\logy::ALL);

$logger->debug('test');
// 14-10-12 22:37:19 INFO test
````

## Expert
````
$params = array(
    'line_format' => '%time$s %prefix$s%level$s %msg$s', // Custom format the log line using a sprintf-like format
    'time_format' => 'y-m-d H:i:s', // a format accepted by date() - http://php.net/manual/en/function.date.php
    'prefix' => '', // prefixes your messages
    
    'sender' => 'sender@example.com',
    'recipient' => 'recipient@example.com',

    'vocal' => false // when true, outputs log to stdout. Useful for debugging
    );

$logger = new fitnr\logger\logy('file.log', fitnr\logger\logy::ALL, $params);

$logger->debug('test');

// will send the log if it's the midnight hour on Monday
$logger->email_log('Monday', 0)
````

## Logging Methods

* $logy->log(string $message, int $level)
* $logy->debug(string $message
* $logy->info(string $message
* $logy->notice(string $message
* $logy->warn(string $message
* $logy->error(string $message
* $logy->err(string $message
* $logy->critical(string $message
* $logy->emergency(string $message

