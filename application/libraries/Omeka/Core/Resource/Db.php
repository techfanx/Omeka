<?php 
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2007-2010
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 * @access private
 */

/**
 * Set up the default database connection for Omeka.
 *
 * @internal This implements Omeka internals and is not part of the public API.
 * @access private
 * @package Omeka
 * @copyright Center for History and New Media, 2007-2010
 */
class Omeka_Core_Resource_Db extends Zend_Application_Resource_Db
{
    /**
     * Path to the database configuration file.
     * Set in application.ini
     *
     * @var string
     */
    private $_iniPath;
    
    /**
     * @return Omeka_Db
     */
    public function init()
    {
        $dbFile = $this->_iniPath;
        
        if (!file_exists($dbFile)) {
            throw new Zend_Config_Exception('Your Omeka database configuration file is missing.');
        }
        
        if (!is_readable($dbFile)) {
            throw new Zend_Config_Exception('Your Omeka database configuration file cannot be read by the application.');
        }
        
        $dbIni = new Zend_Config_Ini($dbFile, 'database');
        
        // Fail on improperly configured db.ini file
        if (!isset($dbIni->host) || ($dbIni->host == 'XXXXXXX')) {
            throw new Zend_Config_Exception('Your Omeka database configuration file has not been set up properly.  Please edit the configuration and reload this page.');
        }
        
        $connectionParams = array('host'     => $dbIni->host,
                                                'username' => $dbIni->username,
                                                'password' => $dbIni->password,
                                                'dbname'   => $dbIni->name);
        
        // 'port' parameter was introduced in 0.10, conditional check needed
        // for backwards compatibility.
        if (isset($dbIni->port)) {
            $connectionParams['port'] = $dbIni->port;
        }
        
        $dbh = Zend_Db::factory('Mysqli', $connectionParams);
        
        $db_obj = new Omeka_Db($dbh, $dbIni->prefix);
        
        // Enable SQL logging (potentially).
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('Config');
        $loggingEnabled = ($config = $this->getBootstrap()->getResource('Config'))
                        && ($config->log->sql);
        if ($loggingEnabled) {
            $bootstrap->bootstrap('Logger');
            $db_obj->setLogger($bootstrap->getResource('Logger'));
        }
        return $db_obj;
    }
    
    /**
     * Set the path to the database configuration file.
     * Allows {@link $_iniPath} to be set by the app configuration.
     *
     * @param string $path
     * @return void
     */
    public function setinipath($path)
    {
        $this->_iniPath = $path;
    }
}
