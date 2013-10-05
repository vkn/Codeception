<?php
namespace Codeception\Util;
/**
 * The Logger class based on PEAR LOG
 * class that logs messages to a text file.
 *
 * USAGE:
 * require PATH.'/Logger.php';
 * all methods are static, ne need to make instance of class
 * 
 * you can specify log file
 * \Codeception\Util\Logger::setFile($basePath.'app.log');
 *
 * //anywhere in code
 * \Codeception\Util\Logger::debug('START');
 *
 * NOTE: file must exist and be writable or directory where file will be
 * placed must be writable
 *
 *
 * @author  Jon Parise <jon@php.net>
 * @author  Roman Neuhauser <neuhauser@bellavista.cz>
 * @author  vkn <kenyuk@gmail.com>
 *
 */
class Logger {

    /**
     * String containing the name of the log file.
     * @var string
     * @access private
     */
    private static $_filename = NULL;
    /**
     * Handle to the log file.
     * @var resource
     * @access private
     */
    private static $_fp = false;
    /**
     * Should new log entries be append to an existing log file, or should the
     * a new log file overwrite an existing one?
     * @var boolean
     * @access private
     */
    private static $_append = true;
    /**
     * Should advisory file locking (i.e., flock()) be used?
     * @var boolean
     * @access private
     */
    private static $_locking = false;
    /**
     * Integer (in octal) containing the log file's permissions mode.
     * @var integer
     * @access private
     */
    private static $_mode = 0644;
    /**
     * Integer (in octal) specifying the file permission mode that will be
     * used when creating directories that do not already exist.
     * @var integer
     * @access private
     */
    private static $_dirmode = 0755;
    /**
     * String containing the format of a log line.
     * @var string
     * @access private
     * @var string
     * @access private
     */
    private static $_lineFormat = '%1$s %2$s %3$s %4$s';
    /**
     * String containing the timestamp format.  It will be passed directly to
     * strftime().  Note that the timestamp string will generated using the
     * current locale.
     * @var string
     * @access private
     */
    private static $_timeFormat = '%b %d %H:%M:%S';
    private static $_opened = NULL;
    private static $_ident = NULL;
    private static $_mute = False;

    private function __construct() {
        self::$_instance = True;
    }

    public function __destruct() {
        if (self::$_opened) {
            self::close();
        }
    }

    /**
     * set file log
     * @param string $file
     */
    public static  function setFile($file){
        self::$_filename = $file;
    }

    public static function off(){
        self::$_mute = True;
    }

    public static function on(){
        self::$_mute = False;
    }

    /**
     * Creates the given directory path.  If the parent directories don't
     * already exist, they will be created, too.
     *
     * This implementation is inspired by Python's os.makedirs function.
     *
     * @param   string  $path       The full directory path to create.
     * @param   integer $mode       The permissions mode with which the
     *                              directories will be created.
     *
     * @return  True if the full path is successfully created or already
     *          exists.
    *
     * @access  private
     */
    private function _mkpath($path='.', $mode = 0700) {
        $path = self::$_filename;
        $mode = self::$_dirmode;
        /* Separate the last pathname component from the rest of the path. */
        $head = dirname($path);
        $tail = basename($path);

        /* Make sure we've split the path into two complete components. */
        if (empty($tail)) {
            $head = dirname($path);
            $tail = basename($path);
        }

        /* Recurse up the path if our current segment does not exist. */
        if (!empty($head) && !empty($tail) && !is_dir($head)) {
            self::_mkpath($head, $mode);
        }

        /* Create this segment of the path. */
        if(is_writable($head)){
            return @mkdir($head, $mode);
        }
        return false;
    }

    /**
     * Opens the log file for output.  If the specified log file does not
     * already exist, it will be created.  By default, new log entries are
     * appended to the end of the log file.
     *
     * This is implicitly called by log(), if necessary.
     *
     * @access public
     */
    private function open() {
        if (!self::$_opened) {
            if(defined('PRODUCTION_SYSTEM') && PRODUCTION_SYSTEM){
                return false;
            }
            
            if(is_null(self::$_filename)){
                if(defined('BASE_PATH')){
                    self::setFile(BASE_PATH.'app.log');
                }
                else{
                    self::setFile($_SERVER['DOCUMENT_ROOT'].'/app.log');
                }
            }
            
            /* If the log file's directory doesn't exist, create it. */
            if (!is_dir(dirname(self::$_filename))) {
                self::$_opened = self::_mkpath();
                if (!self::$_opened) {
                    return false;
                }
            }

            /* Determine whether the log file needs to be created. */
            $creating = !file_exists(self::$_filename);

            /* Obtain a handle to the log file. */
            if(!is_writable(self::$_filename)){
                return false;
            }
            self::$_fp = fopen(self::$_filename, (self::$_append) ? 'a' : 'w');
            /* We consider the file "opened" if we have a valid file pointer. */
            self::$_opened = (self::$_fp !== false);

            /* Attempt to set the file's permissions if we just created it. */
            if ($creating && self::$_opened) {
                chmod(self::$_filename, self::$_mode);
            }
        }
        return self::$_opened;
    }

    /**
     * Closes the log file if it is open.
     *
     * @access public
     */
    private function close() {
        /* If the log file is open, close it. */
        if (self::$_opened && fclose(self::$_fp)) {
            self::$_opened = false;
        }

        return (self::$_opened === false);
    }

    /**
     * Flushes all pending data to the file handle.
     *
     * @access public
     * @since Log 1.8.2
     */
    public function flush() {
        if (is_resource(self::$_fp)) {
            return fflush(self::$_fp);
        }

        return false;
    }

    /**
     * Logs $message to the output window.
     *
     * @param mixed  $message  String or object containing the message to log.

     * @return boolean  True on success or false on failure.
     * @access public
     */
    public static function log($message, $type='') {

        if (self::$_mute){
            return false;
        }

        /* If the log file isn't already open, open it now. */
        if (!self::$_opened && !self::open()) {
            return false;
        }

        /* Extract the string representation of the message. */
        $message = self::_extractMessage($message);

        /* Build the string containing the complete log line. */
        $line = self::_format($type, $message) . PHP_EOL;

        /* If locking is enabled, acquire an exclusive lock on the file. */
        if (self::$_locking) {
            flock(self::$_fp, LOCK_EX);
        }

        /* Write the log line to the log file. */
        $success = (fwrite(self::$_fp, $line) !== false);

        /* Unlock the file now that we're finished writing to it. */
        if (self::$_locking) {
            flock(self::$_fp, LOCK_UN);
        }

        return $line;
    }

    /**
     * logs $message with prefix DEBUG
     *
     * @example Log::debug(array('a', 'b'), __LINE__); prints
     * 
     * Nov 03 18:52:18  DEBUG 372 Array
     *   (
     *       [0] => a
     *       [1] => b
     *   )
     *
     * 
     *
     * @param string $suffix additional string (e.g. __LINE__)
     * @param mixed $message can be of any type. Printed with print_r
     * @return bool
     */
    public static function debug($message, $suffix='') {
        return self::log($message, $suffix ? 'DEBUG: ' . $suffix : 'DEBUG:');
    }

    /**
     * logs $message with prefix INFO
     *
     * @see Log::debug
     * @param mixed $message
     * @return bool
     */
    public static function info($message, $suffix='') {
        return self::log($message, $suffix ? 'INFO: ' . $suffix : 'INFO:');
    }

    /**
     * logs $message with prefix WARNING
     * 
     * @see Log::debug
     * @param mixed $message
     * @return bool
     */
    public static function warning($message, $suffix='') {
        return self::log($message, $suffix ? 'WARNING: ' . $suffix : 'WARNING:');
    }

    /**
     * logs $message with prefix ERROR
     *
     * @see Log::debug
     * @param mixed $message
     * @return bool
     */
    public static function error($message, $suffix='') {
        return self::log($message, $suffix ? 'ERROR: ' . $suffix : 'ERROR:');
    }

    /**
     * logs $message with prefix EXCEPTION
     *
     * @see Log::debug
     * @param mixed $message
     * @return bool
     */
    public static function exception(\Exception $e, $suffix='') {
        $message = $e->getTraceAsString().PHP_EOL.$e;
        return self::log($message, $suffix ? 'EXCEPTION: ' . $suffix : 'EXCEPTION:');
    }

    /**
     * Produces a formatted log line based on a format string and a set of
     * variables representing the current log record and state.
     *
     * @return  string  Formatted log string.
     *
     * @access  protected
     * @since   Log 1.9.4
     */
    protected function _format($type, $message) {
        /*
         * If the format string references any of the backtrace-driven
         * variables (%5 %6,%7,%8), generate the backtrace and fetch them.
         */
        if (preg_match('/%[5678]/', self::$_lineFormat)) {
            list($file, $line, $func, $class) = self::_getBacktraceVars(2);
        }

        /*
         * Build the formatted string.  We use the sprintf() function's
         * "argument swapping" capability to dynamically select and position
         * the variables which will ultimately appear in the log string.
         */
        return sprintf(self::$_lineFormat,
                strftime(self::$_timeFormat),
                self::$_ident,
                $type,
                $message,
                isset($file) ? $file : '',
                isset($line) ? $line : '',
                isset($func) ? $func : '',
                isset($class) ? $class : '');
    }

    /**
     * Using debug_backtrace(), returns the file, line, and enclosing function
     * name of the source code context from which log() was invoked.
     *
     * @param   int     $depth  The initial number of frames we should step
     *                          back into the trace.
     *
     * @return  array   Array containing four strings: the filename, the line,
     *                  the function name, and the class name from which log()
     *                  was called.
     *
     * @access  private
     * @since   Log 1.9.4
     */
    private function _getBacktraceVars($depth) {
        /* Start by generating a backtrace from the current call (here). */
        $bt = debug_backtrace();

        /*
         * If we were ultimately invoked by the composite handler, we need to
         * increase our depth one additional level to compensate.
         */
        $class = isset($bt[$depth + 1]['class']) ? $bt[$depth + 1]['class'] : null;
        if ($class !== null && strcasecmp($class, 'Log_composite') == 0) {
            $depth++;
            $class = isset($bt[$depth + 1]['class']) ? $bt[$depth + 1]['class'] : null;
        }

        /*
         * We're interested in the frame which invoked the log() function, so
         * we need to walk back some number of frames into the backtrace.  The
         * $depth parameter tells us where to start looking.   We go one step
         * further back to find the name of the encapsulating function from
         * which log() was called.
         */
        $file = isset($bt[$depth]) ? $bt[$depth]['file'] : null;
        $line = isset($bt[$depth]) ? $bt[$depth]['line'] : 0;
        $func = isset($bt[$depth + 1]) ? $bt[$depth + 1]['function'] : null;

        /*
         * However, if log() was called from one of our "shortcut" functions,
         * we're going to need to go back an additional step.
         */
        if (in_array($func, array('info', 'debug', 'error', 'exception', 'warning'))) {
            $file = isset($bt[$depth + 1]) ? $bt[$depth + 1]['file'] : null;
            $line = isset($bt[$depth + 1]) ? $bt[$depth + 1]['line'] : 0;
            $func = isset($bt[$depth + 2]) ? $bt[$depth + 2]['function'] : null;
            $class = isset($bt[$depth + 2]) ? $bt[$depth + 2]['class'] : null;
        }

        /*
         * If we couldn't extract a function name (perhaps because we were
         * executed from the "main" context), provide a default value.
         */
        if (is_null($func)) {
            $func = '(none)';
        }

        /* Return a 4-tuple containing (file, line, function, class). */
        return array($file, $line, $func, $class);
    }

    /**
     * Returns the string representation of the message data.
     *
     * If $message is an object, _extractMessage() will attempt to extract
     * the message text using a known method (such as a PEAR_Error object's
     * getMessage() method).  If a known method, cannot be found, the
     * serialized representation of the object will be returned.
     *
     * If the message data is already a string, it will be returned unchanged.
     *
     * @param  mixed $message   The original message data.  This may be a
     *                          string or any object.
     *
     * @return string           The string representation of the message.
     *
     * @access protected
     */
    protected function _extractMessage($message) {
        /*
         * If we've been given an object, attempt to extract the message using
         * a known method.  If we can't find such a method, default to the
         * "human-readable" version of the object.
         *
         * We also use the human-readable format for arrays.
         */
        if (is_object($message)) {
            //$message = var_export($message, true);
            $message = "<pre>" . print_r($message, 1) . "</pre>";
        } else if (is_array($message)) {
            if (isset($message['message'])) {
                if (is_scalar($message['message'])) {
                    $message = $message['message'];
                } else {
                    $message = var_export($message['message'], true);
                }
            } else {
                $message = print_r($message, true);
            }
        } else if (is_bool($message) || $message === NULL) {
            $message = var_export($message, true);
        }

        /* Otherwise, we assume the message is a string. */
        return $message;
    }
    
    /**
     * print simple trace: current function name and where is it called
     * 
     * calls debug function to print method name and caller method name
     * along with line and filename of caller method
     */
    public static function trace(){
        if (self::$_mute){
            return false;
        }
        $backtrace = debug_backtrace();
        $traceLen = count($backtrace);
        self::debug("===  TRACE  ===");
        $class = '';
        $file = '';
        $line = '';
        $method = '';
        if($traceLen > 2){
            $method = $backtrace[1]['function'];
            if(array_key_exists('type', $backtrace[1]) && $backtrace[1]['type'] ){
                $class = $backtrace[1]['class'].'::';
            }
            if(array_key_exists('file', $backtrace[1])){
                $file = ' FILE '.$backtrace[1]['file'];
            }
            if(array_key_exists('line', $backtrace[1])){
                $line = ' AT LINE '.$backtrace[1]['line'];
            }
        }
        $class2 = '';
        $function = '';
        if($traceLen > 2){
            if(array_key_exists('type', $backtrace[2]) && $backtrace[2]['type'] ){
                $class2 = $backtrace[2]['class'].'::';
            }
            if(array_key_exists('function', $backtrace[2])){
                $function = $backtrace[2]['function'];
            }
        }

        $caller = $class2.$function;
        if(empty($caller) && $traceLen > 1){
            $file = ' AT LINE '.$backtrace[1]['line'];
            $line = ' FILE '.$backtrace[1]['file'];
            $method = $backtrace[1]['function'];
        }
        if(empty($method) && $traceLen > 0){
            $file = ' AT LINE '.$backtrace[0]['line'];
            $line = ' FILE '.$backtrace[0]['file'];
        }
        $trace = $class.$method.' CALLER '.$caller.$line.$file;
        return self::debug($trace);
    } 
    
    public static function traceWithException()
    {
        try {
            throw new \Exception('thrown for tracing');
        } catch (\Exception $exc) {
            return self::exception($exc);
        }
    }

}

