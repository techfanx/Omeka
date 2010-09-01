<?php 
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2009-2010
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 * @access private
 */

/**
 * Initialize the session and customize the session name to prevent session
 * overlap between different applications that operate on the same server.
 * 
 * @internal This implements Omeka internals and is not part of the public API.
 * @access private
 * @package Omeka
 * @copyright Center for History and New Media, 2009-2010
 */
class Omeka_Core_Resource_Session extends Zend_Application_Resource_ResourceAbstract
{
    /**
     * @return void
     */
    public function init()
    {
        // Look for the session name as the 'session.name' value in the 
        // config.ini file.  If it can't find that value (or it is blank), it
        // will automatically generate the session name based on the root URL
        // of this particular installation.
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('Config');
        $basicConfig = $bootstrap->getResource('Config');
        Zend_Session::start($this->_getSessionConfig($basicConfig));
    }
    
    /**
     * Retrieve global session configuration options.
     * 
     * @link http://framework.zend.com/manual/en/zend.session.global_session_management.html#zend.session.global_session_management.configuration_options
     * @param Zend_Config $config Application config.
     * @return array An array containing all the global configuration options 
     * for sessions.  This array contains at least one key, 'name', corresponding
     * to the name of the session, which is generated automatically if not 
     * provided.
     */
    private function _getSessionConfig(Zend_Config $config)
    {
        $sessionConfig = isset($config->session) 
                       ? $config->session->toArray()
                       : array();
        
        if (!array_key_exists('name', $sessionConfig) || empty($sessionConfig['name'])) {
            $sessionConfig['name'] = $this->_buildSessionName();
        }
        return $sessionConfig;
    }
    
    /**
     * Create a unique session name.
     *
     * Hashes the base directory, this ensures that session names differ between
     * Omeka instances on the same server.
     *
     * @return string
     */
    private function _buildSessionName()
    {
        return md5(BASE_DIR);
    }
}
