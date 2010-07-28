<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_site
 * @copyright Copyright (c) 2009, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * A helper class for the site component.
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_site
 * @copyright  Copyright (c) 2009, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @subpackage component
 */
class SiteHelper extends OntoWiki_Component_Helper
{
    /**
     * Name of per-site config file
     */
    const SITE_CONFIG_FILENAME = 'config.ini';
    
    /**
     * Site config for the current site.
     * @var array
     */
    protected $_siteConfig = null;
    
    /**
     * Current pseudo file extension.
     * @var string
     */
    protected $_currentSuffix = '';
    
    public function onPostBootstrap($event)
    {
        $router     = $event->bootstrap->getResource('Router');
        $request    = Zend_Controller_Front::getInstance()->getRequest();
        $controller = $request->getControllerName();
        $action     = $request->getActionName();
        
        if ($router->hasRoute('empty')) {
            $emptyRoute = $router->getRoute('empty');
            $defaults   = $emptyRoute->getDefaults();
            
            $defaultController = $defaults['controller'];
            $defaultAction     = $defaults['action'];
            
            // are we currently following the empty route?
            if ($controller === $defaultController && $action === $defaultAction) {
                /* TODO: this should not be the default site but the site which 
                   matches the model of the selected resource */
                $siteConfig = $this->getSiteConfig();
                
                if (isset($siteConfig['index'])) {
                    // TODO: detect accept header
                    $indexResource = $siteConfig['index'] . $this->getCurrentSuffix();
                    $requestUri    = $this->_config->urlBase
                                   . ltrim($request->getRequestUri(), '/');

                    // redirect if request URI does not match index resource
                    if ($requestUri !== $indexResource) {
                        // response not ready yet, do it the PHP way
                        header('Location: ' . $indexResource, true, 303);
                        exit;
                    }
                }
            }
            
            $emptyRoute = new Zend_Controller_Router_Route(
                    '',
                    array(
                        'controller' => 'site',
                        'action'     => $this->_privateConfig->defaultSite)
                    );
            $router->addRoute('empty', $emptyRoute);
        }
    }
    
    // http://localhost/OntoWiki/SiteTest/
    public function onShouldLinkedDataRedirect($event)
    {
        if ($event->type === 'html') {
            $event->request->setControllerName('site');
            $event->request->setActionName($this->_privateConfig->defaultSite);
            $this->_currentSuffix = '.html';
        } else {
            // export
            $event->request->setControllerName('resource');
            $event->request->setActionName('export');
            $event->request->setParam('f', $event->type);
            $event->request->setParam('r', $event->uri);
        }
        
        $event->request->setDispatched(false);
        return false;
    }
    
    public function onBuildUrl($event)
    {
        $site = $this->getSiteConfig();
        $graph = isset($site['model']) ? $site['model'] : null;
        $resource = isset($event->params['r']) ? OntoWiki_Utils::expandNamespace($event->params['r']) : null;
        
        // URL for this site?
        if ($graph === $event->base) {
            if (false !== strpos($resource, $graph)) {
                // LD-capable
                $event->url = $resource 
                            . $this->getCurrentSuffix();
                
                // URL created
                return true;
            } else {
                // classic
                $event->route      = null;
                $event->controller = 'site';
                $event->action     = 'lod2'; // TODO: detect actual site
                
                // URL not created, but params changed
                return false;
            }
        }
    }
    
    public function getSiteConfig()
    {
        if (null === $this->_siteConfig) {
            $this->_siteConfig = array();
            $site = $this->_privateConfig->defaultSite;
            
            // load the site config
            $configFilePath = sprintf('%s/sites/%s/%s', $this->getComponentRoot(), $site, self::SITE_CONFIG_FILENAME);
            if (is_readable($configFilePath)) {
                if ($config = parse_ini_file($configFilePath, true)) {
                    $this->_siteConfig = $config;
                }
            }
        }
        
        return $this->_siteConfig;
    }
    
    public function getCurrentSuffix()
    {
        return $this->_currentSuffix;
    }
}
