<?php

/**
 * @copyright Copyright (c) Metaways Infosystems GmbH, 2012
 * @license GPLv3, http://www.gnu.org/copyleft/gpl.html
 * @package TYPO3_Arcavias
 * @version $Id$
 */


/**
 * Controller for adminisration interface.
 *
 * @package TYPO3_Arcavias
 */
class Tx_Arcavias_Controller_AdminController extends Tx_Arcavias_Controller_Abstract
{
	private $_controller;


	public function __construct()
	{
		parent::__construct();

		$cntlPaths = Tx_Arcavias_Base::getArcavias()->getCustomPaths( 'controller/extjs' );
		$this->_controller = new Controller_ExtJS_JsonRpc( $this->_getContext(), $cntlPaths );
	}


	/**
	 * Sends the index file for the admin interface.
	 *
	 * @return Index file
	 */
	public function indexAction()
	{
		$html = '';
		$abslen = strlen( PATH_site );
		$langid = $this->_getContext()->getLocale()->getLanguageId();

		foreach( Tx_Arcavias_Base::getArcavias()->getCustomPaths( 'client/extjs' ) as $base => $paths )
		{
			$relJsbPath = '../' . substr( $base, $abslen );

			foreach( $paths as $path )
			{
				$jsbAbsPath = $base . '/' . $path;

				if( !is_file( $jsbAbsPath ) ) {
					throw new Exception( sprintf( 'JSB2 file "%1$s" not found', $jsbAbsPath ) );
				}

				$jsb2 = new MW_Jsb2_Default( $jsbAbsPath, $relJsbPath . '/' . dirname( $path ) );
				$html .= $jsb2->getHTML( 'css' );
				$html .= $jsb2->getHTML( 'js' );
			}
		}

		// rawurldecode() is necessary for ExtJS templates to replace "{site}" properly
		$urlTemplate = rawurldecode( t3lib_BEfunc::getModuleUrl( $this->request->getPluginName(), array( 'tx_arcavias_web_arcaviastxarcaviasadmin' => array( 'site' => '{site}', 'tab' => '{tab}' ) ) ) );
		$serviceUrl = t3lib_BEfunc::getModuleUrl( $this->request->getPluginName(), array( 'tx_arcavias_web_arcaviastxarcaviasadmin' => array( 'controller' => 'Admin', 'action' => 'do' ) ) );

		$this->view->assign( 'htmlHeader', $html );
		$this->view->assign( 'locale', $langid );
		$this->view->assign( 'i18nContent', $this->_getJsonClientI18n( $langid ) );
		$this->view->assign( 'config', $this->_getJsonClientConfig() );
		$this->view->assign( 'site', $this->_getSite( $this->request ) );
		$this->view->assign( 'smd', $this->_controller->getJsonSmd( $serviceUrl ) );
		$this->view->assign( 'itemSchemas', $this->_controller->getJsonItemSchemas() );
		$this->view->assign( 'searchSchemas', $this->_controller->getJsonSearchSchemas() );
		$this->view->assign( 'activeTab', ( $this->request->hasArgument( 'tab' ) ? (int) $this->request->getArgument( 'tab' ) : 0 ) );
		$this->view->assign( 'urlTemplate', $urlTemplate );
	}


	/**
	 * Single entry point for all MShop admin requests.
	 *
	 * @return JSON 2.0 RPC message response
	 */
	public function doAction()
	{
		$this->view->assign( 'response', $this->_controller->process( t3lib_div::_POST(), 'php://input' ) );
	}


	/**
	 * Initializes the object before the real action is called.
	 */
	protected function initializeAction()
	{
		$langid = 'en';
		if( isset( $GLOBALS['BE_USER']->uc['lang'] ) && $GLOBALS['BE_USER']->uc['lang'] != '' ) {
			$langid = $GLOBALS['BE_USER']->uc['lang'];
		}

		$context = $this->_getContext();

		$conf = $this->_getConfig( ( is_array( $this->settings ) ? $this->settings : array() ) );
		$context->setConfig( $conf );

		$context->setI18n( $this->_getI18n( array( $langid ) ) );
		$context->setEditor( $GLOBALS['BE_USER']->user['username'] );

		$localeManager = MShop_Locale_Manager_Factory::createManager( $context );
		$localeItem = $localeManager->createItem();
		$localeItem->setLanguageId( $langid );
		$context->setLocale( $localeItem );
	}


	/**
	 * Uses default view.
	 *
	 * return Tx_Extbase_MVC_View_ViewInterface View object
	 */
	protected function resolveView()
	{
		return Tx_Extbase_MVC_Controller_ActionController::resolveView();
	}


	protected function _getJsonClientConfig()
	{
		$config = $this->_getContext()->getConfig()->get( 'client/extjs', array() );
		return json_encode( array( 'client' => array( 'extjs' => $config ) ), JSON_FORCE_OBJECT );
	}


	protected function _getJsonClientI18n( $locale )
	{
		$i18nPaths = Tx_Arcavias_Base::getArcavias()->getI18nPaths();
		$i18n = new MW_Translation_Zend( $i18nPaths, 'gettext', $locale, array('disableNotices'=>true) );

		$content = array(
			'client/extjs' => $i18n->getAll( 'client/extjs' ),
			'client/extjs/ext' => $i18n->getAll( 'client/extjs/ext' ),
		);

		return json_encode( $content, JSON_FORCE_OBJECT );
	}


	protected function _getSite( Tx_Extbase_MVC_RequestInterface $request )
	{
		$localeManager = MShop_Locale_Manager_Factory::createManager( $this->_getContext() );
		$manager = $localeManager->getSubManager( 'site' );

		$site = 'default';
		if( $request->hasArgument( 'site' ) ) {
			$site = $request->getArgument( 'site' );
		}

		$criteria = $manager->createSearch();
		$criteria->setConditions( $criteria->compare( '==', 'locale.site.code', $site ) );
		$items = $manager->searchItems( $criteria );

		if( ( $item = reset( $items ) ) === false ) {
			throw new Exception( sprintf( 'No site found for code "%1$s"', $site ) );
		}

		return json_encode( $item->toArray() );
	}
}
