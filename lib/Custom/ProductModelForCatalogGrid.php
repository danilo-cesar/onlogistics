<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This file is part of Onlogistics, a web based ERP and supply chain 
 * management application. 
 *
 * Copyright (C) 2003-2008 ATEOR
 *
 * This program is free software: you can redistribute it and/or modify it 
 * under the terms of the GNU Affero General Public License as published by 
 * the Free Software Foundation, either version 3 of the License, or (at your 
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or 
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public 
 * License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP version 5.1.0+
 *
 * @package   Onlogistics
 * @author    ATEOR dev team <dev@ateor.com>
 * @copyright 2003-2008 ATEOR <contact@ateor.com> 
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL
 * @version   SVN: $Id: ProductModelGrid.php 71 2008-07-07 09:03:06Z izimobil $
 * @link      http://www.onlogistics.org
 * @link      http://onlogistics.googlecode.com
 * @since     File available since release 0.1.0
 * @filesource
 */

require_once 'ProductCommandTools.php';

class ProductModelForCatalogGrid extends GenericGrid
{
    // ProductModelForCatalogGrid::properties {{{

    protected $customerId = false;
    protected $ownerId = false;

    // }}}
    // ProductModelForCatalogGrid::__construct() {{{

    /**
     *
     * @access public
     * @return void
     */
    public function __construct($params = array()) {
        if (!in_array('readytowear', Preferences::get('TradeContext', array()))
         && !in_array('readytowear2', Preferences::get('TradeContext', array()))) {
            Tools::redirectTo('CustomerCatalog.php');
            exit(0);
        }
        parent::__construct($params);
        $this->preserveGridItems = true;
        $this->jsRequirements = array('js/lib-functions/ClientCatalog.js');

        // Gestion de l'edition du devis si necessaire
        // ouverture d'un popup en arriere-plan, impression du contenu (pdf), et fermeture de ce popup
        if (isset($_REQUEST['editEstimate']) && isset($_REQUEST['estId'])) {
        	$this->additionalContent['beforeForm'] = "
<script language=\"javascript\">
	function kill() {
		window.open(\"KillPopup.html\",'popback','width=800,height=600,toolbars=no,scrollbars=no,menubars=no,status=no');
	}
	function TimeToKill(sec) {
		setTimeout(\"kill()\",sec*1000);
	}
	var w=window.open(\"EstimateEdit.php?estId=" . $_REQUEST['estId']
        . "\",\"popback\",\"width=800,height=600,toolbars=no,scrollbars=no,menubars=no,status=no\");
	w.blur();
	TimeToKill(12);
</script>";
        }
        $this->searchForm->setQuickFormAttributes(array(
            'name' => 'ClientCatalog',
            'onsubmit'=>'return WPCustomerListSubmit();'
        ));
        $this->grid->withNoCheckBox = true;
        $this->grid->setMapper(Mapper::Singleton('Product'));
        $this->searchForm->withResetButton = false;
        $this->searchForm->addAction(array(
            'URL'     => 'dispatcher.php?entity=ProductModel&altname=ProductModelForCatalog&new=1',
            'Caption' => _('Reset all')
        ));
        // Si reinitialisation de toute la commande
        if (isset($_REQUEST['new'])) {
            SearchTools::cleanDataSession('noPrefix');
        }

        // si on veut faire une nouvelle recherche, on vide ces var en session
        if (isset($_REQUEST['search'])) {
            $griditems = SearchTools::getGridItemsSessionName();
             unset($_SESSION['formSubmitted'], $_SESSION['customer'], $_SESSION['owner'],
                $_SESSION['gridItems'], $_SESSION[$griditems]);
        }
        $defaultValues = SearchTools::dataInSessionToDisplay();

        if (isset($_SESSION['customer']) && $_SESSION['customer'] != '##') {
            $defaultValues = array_merge($defaultValues,
                array('CustomerSelected' => $_SESSION['customer']));
            $this->searchForm->setDefaultValues($defaultValues);
        } else {
            $this->searchForm->setDefaultValues(array('CustomerSelected' => 0));
        }

        if (isset($_SESSION['owner']) && $_SESSION['owner'] != '##') {
            $defaultValues = array_merge($defaultValues,
                array( 'OwnerSelected' => $_SESSION['owner']));
            $this->searchForm->setDefaultValues($defaultValues);
        } else {
            $this->searchForm->setDefaultValues(array('OwnerSelected' => 0));
        }

        insertQtiesIntoSession();
    }
    
    // }}} 
    //  ProductModelForCatalogGrid::getMapping() {{{

    /**
     *
     * @access public
     * @return void
     */
    public function getMapping() {
        return array(
            'BaseReference'=>array(
                'label'        => _('Reference'),
                'shortlabel'   => _('Reference'),
                'usedby'       => array('grid', 'searchform'),
                'required'     => true,
                'inplace_edit' => false,
                'add_button'   => false,
                'section'      => ''
            ),
            'Customer'=>array(
                'label'        => _('Customer'),
                'shortlabel'   => _('Customer'),
                'usedby'       => array('searchform'),
                'required'     => true,
                'inplace_edit' => false,
                'add_button'   => false,
                'section'      => ''
            ),
            'Owner'=>array(
                'label'        => _('Owner'),
                'shortlabel'   => _('Owner'),
                'usedby'       => array('searchform'),
                'required'     => true,
                'inplace_edit' => false,
                'add_button'   => false,
                'section'      => ''
            ),
        );
    }
    
    // }}} 
    // ProductModelForCatalogGrid::getFeatures() {{{

    /**
     * Retourne le tableau des "fonctionalités" pour l'objet en cours.
     * Voir Object pour documentation.
     *
     * @static
     * @access public
     * @return array
     * @see Object.php
     */
    public function getFeatures() {
        return array(self::FEATURE_GRID, self::FEATURE_SEARCHFORM);
    }

    // }}}
    // ProductModelForCatalogGrid::renderSearchFormCustomer() {{{

    /**
     * Render custom du customer
     *
     * @access protected
     * @return void
     */
    protected function renderSearchFormCustomer()
    {
        $pf    = $this->auth->getProfile();
        $actor = $this->auth->getActorId();
        $customerSelectMsg = _('Select a customer');
        $clientIsConnected = false;

        if ($pf == UserAccount::PROFILE_CUSTOMER || $pf == UserAccount::PROFILE_OWNER_CUSTOMER) {
            $filter = array('Id' => $actor, 'Active' => 1);
            $customerSelectMsg = '';
            $clientIsConnected = true;
        } else if ($pf == UserAccount::PROFILE_COMMERCIAL){
            $CustomerFilter = array('Commercial' => $auth->getUserId(), 'Active' => 1);
        } else {
            $filter = array('Active' => 1);
        }

        if (isset($_REQUEST['CustomerSelected'])) {
            $this->customerId = $_REQUEST['CustomerSelected'];
        } else if (isset($_SESSION['customer'])) {
            $this->customerId = $_SESSION['customer'];
        } else if ($clientIsConnected) {
            $this->customerId = $this->auth->getActorId();
        }
        $disabled = '';
        if ($this->customerId != false) {
            $this->session->register('customer', $this->customerId, 2);
            $disabled = 'disabled="disabled"';
        }
        $customers = SearchTools::createArrayIDFromCollection(
            array('Customer', 'AeroCustomer'),
            $filter,
            $customerSelectMsg
        );
        $this->searchForm->addElement('select', 'CustomerSelected', _('Customer'),
            array($customers, $disabled),
            array('Disable' => true)
        );
    }

    // }}}
    // ProductModelForCatalogGrid::renderSearchFormOwner() {{{

    /**
     * Render custom du customer
     *
     * @access protected
     * @return void
     */
    protected function renderSearchFormOwner()
    {
        /*
        $pf    = $this->auth->getProfile();
        $actor = $this->auth->getActorId();
        $ownerSelectMsg = _('Select an actor');
        $clientIsConnected = false;

        if ($pf == UserAccount::PROFILE_SUPPLIER_CONSIGNE || $pf == UserAccount::PROFILE_OWNER_CUSTOMER) {
            $filter = array('Id' => $actor) ;
            $ownerSelectMsg = '';
            $clientIsConnected = true;
        } else {
            $filter = array();
        }

        if (isset($_REQUEST['OwnerSelected'])) {
            $this->ownerId= $_REQUEST['OwnerSelected'];
        } else if (isset($_SESSION['owner'])) {
            $this->ownerId= $_SESSION['owner'];
        } else if ($clientIsConnected) {
            $this->ownerId= $this->auth->getActorId();
        }
        $disabled = '';
        if ($this->ownerId != false) {
            $this->session->register('owner', $this->ownerId, 2);
            $disabled = 'disabled="disabled"';
        }
        $owners = SearchTools::createArrayIDFromCollection(
            array('Actor'),
            $filter,
            $ownerSelectMsg
        );
        $this->searchForm->addElement('select', 'Owner', _('Owner'),
            array($owners, $disabled),
            array('Disable' => true)
        ); */


        if (in_array($this->auth->getProfile(), array(UserAccount::PROFILE_OWNER_CUSTOMER, UserAccount::PROFILE_SUPPLIER_CONSIGNE))) {
            $owners = SearchTools::createArrayIDFromCollection(
                'Actor', array('Id' => $this->auth->getActorId()));
        }else {
            $owners = SearchTools::createArrayIDFromCollection(
                'Actor', array(),_('Select an actor'));
        }
           $this->searchForm->addElement('select', 'Owner', _('Owner'), array($owners));


    }

    // }}}

    // ProductModelForCatalogGrid::additionalGridActions() {{{

    /**
     * Ajoute l'action imprimer fiche technique.
     *
     * @access protected
     * @return array
     */
    protected function additionalGridActions() {
        $this->grid->NewAction('Redirect', array(
            'Caption' =>_('Ask for estimate'),
            'Profiles' => array(UserAccount::PROFILE_ADMIN, UserAccount::PROFILE_ADMIN_WITHOUT_CASHFLOW,
                UserAccount::PROFILE_ADMIN_VENTES, UserAccount::PROFILE_AERO_ADMIN_VENTES),
            'TransmitedArrayName' => 'pdt',
            'URL' => 'ProductCommand.php?isEstimate=1'
        ));
        $this->grid->NewAction('Redirect', array(
            'Caption'  => _('Order selected items'),
            'Profiles' => array(UserAccount::PROFILE_ADMIN, UserAccount::PROFILE_ADMIN_WITHOUT_CASHFLOW,
                UserAccount::PROFILE_CUSTOMER, UserAccount::PROFILE_COMMERCIAL, UserAccount::PROFILE_ADMIN_VENTES,
                UserAccount::PROFILE_AERO_ADMIN_VENTES, UserAccount::PROFILE_OWNER_CUSTOMER),
            'TransmitedArrayName' => 'pdt',
            'URL' => 'ProductCommand.php'
        ));
    }

    // }}}
    // ProductModelForCatalogGrid::additionalGridColumns() {{{

    /**
     * Colonnes supplementaires du grid.
     *
     * @access protected
     * @return void
     */
    public function additionalGridColumns() {
        $this->grid->newColumn('RTWModelCustomerCatalog', _('Sizes'), array(
            'actor'    => $this->customerId,
            'method'   => 'getProductCollection',
            'Sortable' => false,
        ));
        $this->grid->newColumn('ProductCommandPriceWithDiscount', _('Unit price'), array(
            'actor'    => Object::load('Actor', $this->customerId),
            'Sortable' => false
        ));
        $this->grid->newColumn('RTWModelCustomerCatalogTotal', _('Total'));
    }

    // }}}
}

?>
