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
require_once('Objects/AbstractDocument.php');
$auth = Auth::singleton();
$auth->checkProfiles(
    array(UserAccount::PROFILE_ADMIN, UserAccount::PROFILE_ADMIN_WITHOUT_CASHFLOW,
          UserAccount::PROFILE_ADMIN_VENTES, UserAccount::PROFILE_AERO_ADMIN_VENTES, UserAccount::PROFILE_ACCOUNTANT,
          UserAccount::PROFILE_AERO_INSTRUCTOR, UserAccount::PROFILE_AERO_CUSTOMER, UserAccount::PROFILE_DIR_COMMERCIAL,
          UserAccount::PROFILE_GESTIONNAIRE_STOCK, UserAccount::PROFILE_OPERATOR, UserAccount::PROFILE_TRANSPORTEUR)
);

if(!isset($_GET['id'])) {
    Template::errorDialog(
        _('An error occurred, please try again later.'),
        'javascript:window.close();',
        BASE_POPUP_TEMPLATE
    );
    exit;
}
$mapper = Mapper::singleton('AbstractDocument');
$doc = $mapper->load(array('Id'=>$_GET['id']));

if (!($doc instanceof AbstractDocument)) {
	die('error');
}

$pdfDoc = $doc->getPDFDocument();
if (!($pdfDoc instanceof PDFDocument) || ($data = $pdfDoc->getData()) == null) {
    $url = $doc->getDocumentsReeditionURL(get_class($doc));
    // construction de l'url pour reedition du doc
    Tools::redirectTo(sprintf($url, $_GET['id']));
    exit(0);
}

header('Content-Type: application/pdf');
if(headers_sent())
    die('Some data has already been output to browser, can\'t send PDF file');
header('Content-Length: '.strlen($data));
header('Content-disposition: inline; filename="reedition.pdf"');
echo $data;

?>
