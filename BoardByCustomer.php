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
 * @version   SVN: $Id$
 * @link      http://www.onlogistics.org
 * @link      http://onlogistics.googlecode.com
 * @since     File available since release 0.1.0
 * @filesource
 */

require_once('config.inc.php');
require_once('GetTotalCaByActorAndDates.php');
require_once('SQLRequest.php');
require_once('Objects/Command.php');

//Database::connection()->debug = 1;
$Auth = Auth::Singleton();
$Auth->checkProfiles();
$userConnectedActorId = $Auth->getActorId();

$FilterComponentArray = array();

/*  Contruction du formulaire de recherche */
$form = new SearchForm('Actor');

$CustomerArray = SearchTools::createArrayIDFromCollection(array('Customer', 'AeroCustomer'),
    array('Generic'=>0), _('select one or more customers'));

$form->addElement('select', 'Id', _('Customer'),
        array($CustomerArray, 'multiple size="5"'), array());
$types = Command::getTypeConstArray() + array(0 => _('Estimate'));
$form->addElement('select', 'CommandType', _('Type of order'),
    array($types), array('Disable'=>true));

if (in_array('readytowear', Preferences::get('TradeContext', array()))) {
    $supplierArray = SearchTools::createArrayIDFromCollection('Supplier', array(), MSG_SELECT_AN_ELEMENT);
    $form->addElement('select', 'Factor', _('Factor'), array($supplierArray), array('Disable'=>true));
    $seasonArray = SearchTools::createArrayIDFromCollection('RTWSeason', array(), MSG_SELECT_AN_ELEMENT);
    $form->addElement('select', 'Season', _('Season'), array($seasonArray), array('Disable'=>true));
}

$currencyArray = SearchTools::createArrayIDFromCollection(
    'Currency');
$form->addElement('select', 'Currency', _('Currency'), array($currencyArray), array('Disable'=>true));

$form->addElement('checkbox', 'DateOrder1', _('Filter by date'),
		array('', 'onClick="$(\\\'Date1\\\').style.display=this.checked?\\\'block\\\':\\\'none\\\';"'));

$form->addDate2DateElement(
        array('Name'   => 'StartDate',
			  'Format' => array('minYear'   => date("Y") - 2),
			  'Disable' => true),
	    array('Name'   => 'EndDate',
			  'Format' => array('minYear'   => date("Y") - 2),
			  'Disable' => true),
	    array('EndDate' => array('d' => date('d'), 'm' => date('m'), 'Y' => date('Y')),
              'StartDate' => array('Y' => date('Y')))
	  );

$defaultCurrency = Object::load('Currency', array('Name'=>'Euro'));
$defaultValues = array();
$defaultValues['Currency'] = $defaultCurrency->getId();
// array_merge pour ne pas perdre les valeurs par d�faut des dates pass�es dans 
// addDate2DateElement()
$form->setDefaultValues(array_merge($form->getDefaultValues(), $defaultValues));

/*  Affichage du Grid  */
if (true === $form->displayGrid()) {
	$StartDate = SearchTools::requestOrSessionExist('StartDate');
	$MySQLStartDate = DateTimeTools::quickFormDateToMySQL('StartDate') . ' 00:00:00';
	$EndDate = SearchTools::requestOrSessionExist('EndDate');
	$MySQLEndDate = DateTimeTools::quickFormDateToMySQL('EndDate') . ' 23:59:59';
	$commandType = SearchTools::requestOrSessionExist('CommandType');
    $currency = SearchTools::requestOrSessionExist('Currency');
    $factor = SearchTools::requestOrSessionExist('Factor');
    $season = SearchTools::requestOrSessionExist('Season');

	list($ca_ht_num, $ca_ht_str) = getTotalCaByActorAndDates(
            $userConnectedActorId, $MySQLStartDate, $MySQLEndDate, $commandType, $currency);

	$CustomerWithCommandsArray = getCustomersWithCommands(
            $userConnectedActorId, $MySQLStartDate, $MySQLEndDate,$commandType, $currency);
    $FilterComponentArray[] = SearchTools::NewFilterComponent(
            'Id', '', 'In', $CustomerWithCommandsArray, 1);
	// on force le ClassName:  Customer ou AeroCustomer
	$FilterComponentArray[] = SearchTools::NewFilterComponent(
            'ClassName', '', 'In', array('Customer', 'AeroCustomer'), 1);

    if ($factor !== false && $factor !== '##') {
        $sql = 'SELECT DISTINCT T0._Id FROM Actor T0, SupplierCustomer T1, '
             . 'TermsOfPayment T2,TermsOfPaymentItem T3 '
             . 'WHERE T0._Id = T1._Customer AND T1._Supplier = '
             . $userConnectedActorId . ' AND T2._Id = T1._TermsOfPayment AND '
             . 'T2._Id = T3._TermsOfPayment AND T3._Supplier = \'' . $factor . '\'';
        $ret = executeSQL($sql);
        $ids = array();
        while ($ret && !$ret->EOF) {
            $ids[] = $ret->fields['_Id'];
            $ret->moveNext();
        }
        $FilterComponentArray[] = SearchTools::NewFilterComponent('Id', 'Id', 'In', $ids, 1);
    }

	/*  Construction du filtre  */
	$FilterComponentArray = array_merge($FilterComponentArray,
            $form->buildFilterComponentArray());
	$Filter = SearchTools::filterAssembler($FilterComponentArray);

	define('CUSTOMER_LIST_ITEM_PER_PAGE', 100);
	$grid = new Grid();
	$grid->itemPerPage = CUSTOMER_LIST_ITEM_PER_PAGE;
	$grid->withNoCheckBox = true;
	$grid->withNoSortableColumn = false;

	$grid->NewAction('Print', array());
	$grid->NewAction('Export', array('FileName' => 'CAparClient'));

	$grid->NewColumn('FieldMapper', _('Name'), array('Macro' => '%Name%'));
    $grid->NewColumn('FieldMapper', _('Site'), 
        array('Macro' => '%InvoicingSite.Name%', 'Sortable'=>false));
    $grid->NewColumn('FieldMapper', _('Address'),
        array('Macro' => '%InvoicingSite.FormatAddressInfos%', 'Sortable'=>false));
	$grid->NewColumn('FieldMapper', _('RCS num.'), array('Macro' => '%RCS%'));
	$grid->NewColumn('FieldMapper', _('VAT num.'), array('Macro' => '%TVA%'));
    $grid->NewColumn('ActorListIncur', _('Allowed outstanding debts'),
        array('Method'=>'getMaxIncur', 'Sortable'=>false));
    $grid->NewColumn('ActorListIncur', _('Current outstanding debts'),
        array('Method'=>'getUpdateIncur', 'Sortable'=>false));
    $grid->NewColumn('FieldMapper', _('Terms of payment'),
        array('Macro'=>'%SupplierCustomer.TermsOfPayment.Name%', 'Sortable'=>false));
	// Nombre de commandes
	$grid->NewColumn('BoardCustomerCommand', _('Num. of orders'),
					 array('req' => 'command_num',
						   'start' => $MySQLStartDate, 'end' => $MySQLEndDate,
                           'commandType' => $commandType, 'currency'=>$currency,
                           'factor'=>$factor, 'season'=>$season, 'Sortable'=>false));
	// ca par Customer
	$grid->NewColumn('BoardCustomerCommand', _('Turnover') . ' (' . _('orders') . ')',
					 array('req' => 'ca_par_cli',
						   'start' => $MySQLStartDate, 'end' => $MySQLEndDate,
						   'commandType' => $commandType, 'currency'=>$currency,
                           'factor'=>$factor, 'season'=>$season, 'Sortable'=>false));
	$grid->NewColumn('BoardCustomerCommand', _('Billed amount'),
					 array('req' => 'billed_amount',
						   'start' => $MySQLStartDate, 'end' => $MySQLEndDate,
						   'commandType' => $commandType, 'currency'=>$currency,
                           'factor'=>$factor, 'season'=>$season, 'Sortable'=>false));
	$grid->NewColumn('BoardCustomerCommand', _('Num. of est.'),
					 array('req' => 'estimate_num',
						   'start' => $MySQLStartDate, 'end' => $MySQLEndDate,
						   'commandType' => $commandType, 'currency'=>$currency,
                           'factor'=>$factor, 'season'=>$season));
	$grid->NewColumn('BoardCustomerCommand', _('Turnover') . ' (' . _('est.') . ')',
					 array('req' => 'ca_par_cli_estimates',
						   'start' => $MySQLStartDate, 'end' => $MySQLEndDate,
						   'commandType' => $commandType, 'currency'=>$currency,
                           'factor'=>$factor, 'season'=>$season, 'Sortable'=>false));
	// % ca par Customer
	$grid->NewColumn('BoardCustomerCommand', _('% of total turnover'),
					 array('req' => 'ca_percent', 'totalCa' => $ca_ht_num,
						   'start' => $MySQLStartDate, 'end' => $MySQLEndDate,
						   'commandType' => $commandType, 'currency'=>$currency,
                           'factor'=>$factor, 'season'=>$season, 'Sortable'=>false));

	$Order = array('Name' => SORT_ASC);

	$form->displayResult($grid, true, $Filter, $Order, '', array(),
						 array('between' => $ca_ht_str));
} // fin DisplayGrid

else { // on n'affiche que le formulaire de recherche, pas le Grid
	Template::page('', $form->render() . '</form>');
}
?>
