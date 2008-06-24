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

require_once('Pdf/PDFDocumentRender.php');
require_once('Numbers/Words.php');
require_once('Objects/Operation.const.php');
require_once('Objects/SupplierCustomer.php');
require_once('Objects/Command.php');
require_once('Objects/Command.const.php');
require_once('Objects/AbstractDocument.php');
require_once('LangTools.php');

// limite pour le changement de page sur les items
define('PAGE_HEIGHT_LIMIT', 270);
define('PAGE_HEIGHT_LIMIT_TO_TOTAL', 205);
define('PAGE_WIDTH', 190);
define('NUMBER_OF_CELLS_PER_TABLE', 5);
define('PAGE_HEIGHT_LIMIT_TO_CHANGE_LETTER', 90);
define('PAGE_HEIGHT_LIMIT_TO_LOGCARD_BARCODES', 222);

/**
 * DocumentGenerator.
 * Classe de base pour les autres documents pdf.
 *
 */
class DocumentGenerator { // {{{
    /**
     * Constructor
     * @param Object $document un AbstractDocument
     * @param boolean $isReedition true si reedition
     * @param boolean $autoPrint true pour impression automatique
     * @param object $currency devise (je sais pas trop ce que ca fait l�!)
     * @param string $docName nom du pdf g�n�r�
     * @param string $orientation orientation du pdf
     * @param string $unit unit� de mesure
     * @param string $format format du pdf
     * @return void
     */
    public function __construct($document, $isReedition = false,
                                $autoPrint = true, $currency = false,
                                $docName = '', $orientation='P',
                                $unit='mm', $format='A4') {
        $this->document = $document;
        $this->docName  = $docName;
        // un document doit �tre r�imprim� dans sa langue originelle
        if ($this->document instanceof AbstractDocument) {
            $locale = $this->document->getLocale();
            if (empty($locale)) {
                $locale = I18N::getLocaleCode();
                $this->document->setLocale($locale);
                $this->document->save();
            }
            I18N::setLocale($locale);
        }
        //gestion de l'affichage ou non de la mention duplicata
        $dom = $this->document->getDocumentModel();
        if($dom instanceof DocumentModel) {
            $this->documentModel = $dom;
            if($dom->getDisplayDuplicata()==0) {
                $isReedition = false;
            }
        }
        // gestion devise
        $this->currency = $currency instanceof Currency?
            TextTools::entityDecode($currency->getSymbol()):'�';
        $this->currencyCode = $currency instanceof Currency?
            $currency->getName():_('Euro');
        $this->isReedition = $isReedition;

        // le doc pdf
        $this->pdf = new PDFDocumentRender(false, $autoPrint, $orientation,
            $unit, $format);
        $this->pdf->reedit = $isReedition;
        $this->pdf->Command = $this->command;
        $this->pdf->Expeditor = $this->expeditor;
        $this->pdf->ExpeditorSite = $this->expeditorSite;
        $this->pdf->footer = $this->document->getFooter();
    }

    /**
     * Le document PDF
     * @var Object PDFDocumentRender
     */
    public $pdf = false;

    /**
     * Le nom du document
     * @var string
     */
    public $docName = false;

    /**
     * Objet documentModel
     */
    public $documentModel = false;
    /**
     * propri�t�s de classe servant de raccourcis pour les diverses m�thodes
     */
    public $document = false;
    public $command = false;
    public $expeditor = false;
    public $destinator = false;
    public $expeditorSite = false;
    public $destinatorSite = false;
    public $currency = false;
    public $editor = false;   // Acteur editeur du document

    /**
     * Formatte un nombre conformement aux usages dans la langue courante.
     * Fait un appel � I18N::formatNumber(), avec le param $strict � true, pour 
     * afficher le separateur de milliers
     * 
     * @access public
     * @param mixed int, double or string $number le nombre � formatter
     * @param int $dec_num le nombre de d�cimales
     * @param boolean $skip_zeros "effacer" les zeros en fin de chaine
     * @static
     * @return string
     */
    public static function formatNumber($number, $dec_num=2, $skip_zeros=false) {
       return I18N::formatNumber($number, $dec_num, $skip_zeros, true);
    }

    /**
     * Formatte un montant en devise conformement aux usages dans la langue courante.
     * Fait un appel � I18N::formatCurrency(), avec le param $strict � true, pour 
     * afficher le separateur de milliers
     * 
     * @access public
     * @param  string $currency le symbole de la devise
     * @param mixed int, double or string $number le nombre � formatter
     * @param int $dec_num le nombre de d�cimales
     * @param boolean $skip_zeros "effacer" les zeros en fin de chaine
     * @static
     * @return string
     */
    public static function formatCurrency($currency, $number, $dec_num=2, $skip_zeros=false) {
       return I18N::formatCurrency($currency, $number, $dec_num, $skip_zeros, true);
    }
    
    /**
     * Formatte un pourcentage conformement aux usages dans la langue courante.
     * Fait un appel � I18N::formatPercent(), avec le param $strict � true, pour 
     * afficher le separateur de milliers
     * 
     * @access public
     * @param mixed int, double or string $number le nombre � formatter
     * @param int $dec_num le nombre de d�cimales
     * @param boolean $skip_zeros "effacer" les zeros en fin de chaine
     * @static
     * @return string
     */
    public static function formatPercent($number, $dec_num=2, $skip_zeros=false) {
       return I18N::formatPercent($number, $dec_num, $skip_zeros, true);
    }
        
    /**
     * Construit le document pdf
     *
     * @access public
     * @return void
     */
    public function render() {
        trigger_error('Abstract method...', E_USER_WARNING);
    }

    /**
     *
     * @access public
     * @param Object $pdfDoc PDFDocumentRender utilise lors d'edition de n factures
     * dans le meme pdf
     * @return void
     */
    public function renderHeader($pdfDoc=false) {
        $pdfDoc = (!$pdfDoc)?$this->pdf:$pdfDoc;
        $pdfDoc->docTitle = $this->docName . ' N� ' .
            $this->document->getDocumentNo();
        $pdfDoc->logo = base64_decode($this->document->getLogo());
///        $pdfDoc->header();
    }

    /**
     *
     * @access public
     * @return void
     */
    public function renderFooter() {
    }

    /**
     * Render des blocs d'adresses
     * @access public
     * @return void
     */
    public function renderAddressesBloc() {
        // Donnees a afficher dans toutes les pages, juste en dessous du header
        // adresse de facturation
        $iSite = $this->destinator->getInvoicingSite();
        $iAddressStr = $this->destinator->getQualityForAddress()
        . $this->destinator->getName() . "\n"
        . $iSite->getFormatAddressInfos("\n");
        // on appelle cette m�thode pour g�rer les differences entre les
        // types de commandes
        $this->buildLeftAddress();  ///#
        $this->pdf->rightAdressCaption = _('Billing address') . ': ';
        $this->pdf->rightAdress = $iAddressStr;
        $this->pdf->addHeader();
    }

    /**
     *
     * @access public
     * @return void
     */
    public function renderSNLotBloc() {
        trigger_error('Abstract method...', E_USER_WARNING);
    }

    /**
     * Cette m�thode est d�finie pour pouvoir �tre surcharg�e dans les classes
     * qui n'ont pas besoin d'afficher l'addresse de livraison, typiquement les
     * commandes de cours
     *
     * @access public
     * @return void
     **/
    public function buildLeftAddress() {
        trigger_error('Abstract method...', E_USER_WARNING);
    }

    /**
     *
     * @access public
     * @return void
     */
    public function numberWords($float) {
        // faut ajouter un zero si on a qu'une d�cimale
        $array = explode('.', strval($float));
        if (isset($array[1]) && strlen($array[1]) == 1) {
            $array[1] .= '0';
            $float = strval($float) . '0';
        }
        $numberWords = new Numbers_Words();
        $str = $numberWords->toCurrency($float, getNumberWordsLangParam(), $this->currencyCode);
        if(is_string($str)) {
            return $str;
        }
        // pas de m�thode pour la langue, on fait ca � l'ancienne
        // fonction d�finie dans lib-functions/LangTools.php
        $lparam = getNumberWordsLangParam();
        $int = $numberWords->toWords(intval($array[0]), $lparam);
        $dec = '';
        if (isset($array[1])) {
            $dec = $numberWords->toWords(intval($array[1]), $lparam);
        }
        // gestion du pluriel
        // Le nom de la devise prend un "s" au pluriel en france uniquement
        $currency = strtolower($this->currencyCode);
        if($lparam == 'fr' && $float > 1) {
            $e = explode(' ', $currency, 2);
            $currency = $e[0] . 's';
            if(isset($e[1])) {
                $currency .= ' ' . $e[1];
            }
        }
        return sprintf('%s %s %s', $int, $currency, $dec);
    }

    /**
     * M�thode qui cr�e les tableaux du haut en fonction des
     * DocumentModelProperty associ�s au DocumentModel utilis�.
     * Seules les DocumentModelProperty avec Property=0 sont affich�s.
     * Un tableau ne peut contenir que 5 cellules, ont cr�e donc autant
     * de tableau que n�c�ssaire en triant les DocumentModelProperty selon
     * leur Order.
     *
     * @access public
     * @param Object $pdfDoc PDFDocumentRender utilise lors d'edition de n factures
     * dans le meme pdf
     * @return void
     */
    public function renderCustomsBlocs($pdfDoc=false) {
        $pdfDoc = (!$pdfDoc)?$this->pdf:$pdfDoc;
        require_once ('Objects/DocumentModelProperty.inc.php');

        $dom = $this->document->findDocumentModel();
        if($dom instanceof DocumentModel) {
            $domPropCol = $dom->getDocumentModelPropertyCollection(array('Property'=>0));
            $numberOfProperties = $domPropCol->getCount();
            $numberOfTable = ceil($numberOfProperties / NUMBER_OF_CELLS_PER_TABLE);

            $domMapper = Mapper::singleton('DocumentModelProperty');
            // pour chaque tableau :
            for ($i=1 ; $i<=$numberOfTable ; $i++) {
                // r�cup�rer les 5 documentModelProperty de la table dans l'ordre
                $domPropCol = $domMapper->loadCollection(
                    array('Property' => 0,
                          'DocumentModel' => $dom->getId()),
                    array('Order' => SORT_ASC),
                    array('PropertyType'), NUMBER_OF_CELLS_PER_TABLE, $i);

                $headerColumns = array();
                $dataColumns = array();
                $cells = $domPropCol->getCount();
                $cellsWidth = PAGE_WIDTH / $cells;
                for ($j=0 ; $j<$cells ; $j++) {
                    $property = $domPropCol->getItem($j);
                    // cr�ation de header
                    $index = getDocumentModelPropertyCellLabel(
                    $property->getPropertyType());
                    if(!isset($headerColumns[$index])) {
                        $headerColumns[$index] = $cellsWidth;
                        // cr�ation du contenu
                        $dataColumns[0][] = getDocumentModelPropertyCellValue(
                        $property->getPropertyType(), $this);
                    }
                }

                $pdfDoc->tableHeader($headerColumns, 1);
                $pdfDoc->tableBody($dataColumns);
                $pdfDoc->ln(3);
                unset($headerColumns, $dataColumns);
            }
        }
    }

    /**
     * Recupere le contenu du champ d�signation personalise
     * dans le model de document
     *
     * @access public
     * @return string
     */
    public function renderDescriptionOfGoodsField($product) {
        $return = '';
        $dom = $this->document->findDocumentModel();
        if($dom instanceof DocumentModel) {
            $domPropertyCol = $dom->getDocumentModelPropertyCollection(
                    array('PropertyType'=>0), array('Order'=>SORT_ASC));
            $numberOfDomProps = $domPropertyCol->getCount();
            for ($i=0 ; $i<$numberOfDomProps ; $i++) {
                $domProperty = $domPropertyCol->getItem($i);
                $property = $domProperty->getProperty();
                if($product instanceof Product) {
                    $return .= ' '.
                        Tools::getValueFromMacro($product,
                            '%' . $property->getName() . '%');
                }
            }
        }
        return $return;
    }
} // }}}

/**
 * CommandDocumentGenerator.
 * Classe de base pour les autres documents pdf.
 *
 */
class CommandDocumentGenerator extends DocumentGenerator { // {{{
    /**
     * Constructor
     * @param Object $document AbstractDocument
     * @param boolean $isReedition true si reedition
     * @param boolean $autoPrint true si impression auto
     * @param string $docName nom du document
     */
    public function __construct($document, $isReedition = false,
                                $autoPrint = true, $docName = ''){
        $this->command = $document->getCommand();
        $this->expeditor = $this->command->getExpeditor();
        $this->expeditorSite = $this->command->getExpeditorSite();
        $this->destinator = $this->command->getDestinator();
        $this->destinatorSite = $this->command->getDestinatorSite();
        $this->supplierCustomer = $this->command->getSupplierCustomer();
        $this->incoterm = $this->command->getIncoterm();
        $cur = $this->command->getCurrency();
        parent::__construct($document, $isReedition, $autoPrint,
                            $cur, $docName);
    }

    /**
     * Affiche untableau avec les details des infos des SN/Lot
     * r�cup�r�es par AbstractDocument::getSNLotArray()
     * Product Reference | Serial Number | Quantity
     * @access public
     * @param Object $pdfDoc PDFDocumentRender utilise lors d'edition de n factures
     * dans le meme pdf
     * @return void
     */
    public function renderSNLotBloc($pdfDoc=false) {
        $pdfDoc = (!$pdfDoc)?$this->pdf:$pdfDoc;
        $data = $this->document->getSNLotArray();
        if (count($data) > 0) {
            $pdfDoc->addPage();
            $pdfDoc->addHeader();
            $label = sprintf(_('Detail of delivered SN (%s No %s)'),
            $this->docName, $this->document->getDocumentNo());
            $pdfDoc->tableHeader(array($label=>190));
            $pdfDoc->ln(8);
            $pdfDoc->tableHeader(
                array(
                    _('Product Reference')=>50,
                    _('Serial Number')=>40,
                    _('Quantity')=>40
                    ),
                    1
                );
            $pdfDoc->tableBody($data);
        }
    }

    /**
     * Affiche l'adresse de livraison
     *
     * @access public
     * @return void
     **/
    public function buildLeftAddress() {
        // adresse de livraison
        $dSite = $this->command->getDestinatorSite();
        $dAddressStr = $this->destinator->getQualityForAddress()
            . $this->destinator->getName() . "\n"
            . $dSite->getFormatAddressInfos("\n");
        $this->pdf->leftAdressCaption = _('Delivery address') . ': ';
        $this->pdf->leftAdress = $dAddressStr;
    }
} // }}}

/**
 * DeliveryOrderGenerator
 * Classe utilisee pour les bordereaux de livraison.
 *
 */
class DeliveryOrderGenerator extends CommandDocumentGenerator { // {{{

    // DeliveryOrderGenerator::__construct {{{
    /**
     * Constructor
     * @param Object $document DeliveryOrder
     * @param boolean $isReedition true si reedition
     * @return void
     */
    public function __construct($document, $isReedition = false) {
        $autoPrint = $isReedition?false:!DEV_VERSION;
        parent::__construct($document, $isReedition, $autoPrint,
                            _('Delivery order'));
        $date = $this->document->getEditionDate();
        $this->data = $this->command->getDataForBL($date);
    }

    // }}}
    // DeliveryOrder::render() {{{

    /**
     * Construit le document pdf
     *
     * @access public
     * @return void
     */
    public function render() {
        $this->pdf->SetFillColor(220);
        $this->renderHeader();
        $this->pdf->addPage(); // apres le renderHeader()!
        $this->renderAddressesBloc();
        $this->renderCustomsBlocs();
        $this->_renderContent();
        $this->renderTotal1Bloc();
        $this->_renderComment();
        $this->renderFooter();
        $this->renderSNLotBloc();
        return $this->pdf;
    }

    // }}}
    // DeliveryOrder::renderAddressesBloc() {{{

    /**
     * Construction des blocs d'adresses du header
     * @access public
     * @return void
     */
    public function renderAddressesBloc() {
        // Donnees a afficher dans toutes les pages, juste en dessous du header
        // adresse de facturation
        $iSite = $this->destinator->getInvoicingSite();
        $iAddressStr = $this->destinator->getQualityForAddress()
                       . $this->destinator->getName() . "\n"
                       . $iSite->getFormatAddressInfos("\n");
        // on appelle cette m�thode pour g�rer les differences entre les
        // types de commandes
        //$this->pdf->leftAdressCaption = _('Billing address') . ': ';
        //$this->pdf->leftAdress = $iAddressStr;
        $this->_buildRightAddress();
        $this->pdf->addHeader();
    }

    // }}}
    // DeliveryOrder::_buildRightAddress() {{{

    /**
     * Construction de l'adresse de droite (livraison)
     * affich�e dans le header
     *
     * @access protected
     * @return void
     **/
    protected function _buildRightAddress(){
        // adresse de livraison
        $dSite = $this->command->getDestinatorSite();
        $dAddressStr = $dSite->getName() . "\n"
            . $dSite->getFormatAddressInfos("\n");
        $this->pdf->rightAdressCaption = _('Delivery address') . ': ';
        $this->pdf->rightAdress = $dAddressStr;
    }

    // }}}
    // DeliveryOrder::_renderContent() {{{

    /**
     * Render du contenu du doc
     * @access protected
     * @return void
     */
    function _renderContent() {
        //cellule d�signation personnalis� dans Command.getDataForBL()
        $columns = array(
            _('Ordered products') => 24,
            _('Description of goods') => 103,
            _('Ordered qty') => 16,
            _('Selling unit') => 15,
            _('Delivered Qty') => 16,
            _('To deliver') => 16);
        $this->pdf->tableHeader($columns, 1);
        $this->pdf->tableBody($this->data[0], $columns);
        $this->pdf->ln(8);
        if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT) {
            $this->pdf->addPage();
            $this->pdf->addHeader();
            /* reaffiche la derniere ligne du tableau pour que le suivant ne
            * soit pas seul.
            */
            $count = sizeof($this->data[0]);
            $this->pdf->tableHeader($columns, 1);
            $this->pdf->tableBody(array($this->data[0][$count-1]), $columns);
            $this->pdf->ln(8);
        }
    }

    // }}}
    // DeliveryOrder::_renderComment() {{{

    /**
     * Ajoute le commentaire de la commande
     * @access protected
     * @return void
     */
    protected function _renderComment() {
        $comment = $this->command->getComment();
        if (!empty($comment)) {
            $this->pdf->tableHeader(
                array(_('Comment') . ': ' . $comment => 190));
        }
    }

    // }}}
    // DeliveryOrder::renderTotal1Bloc() {{{

    /**
     * Ajoute un tableau avec le total du bl
     * Number of parcels | Parcels total weight (Kg)
     * @access protected
     * @return void
     */
    protected function renderTotal1Bloc() {
        $this->pdf->tableHeader(
            array( _('Number of parcels') . ': ' . $this->data[1][0] => 190));
        $displayTotalWeight = $this->documentModel instanceof DocumentModel?
            $this->documentModel->getDisplayTotalWeight():true;
        if($displayTotalWeight) {
            $this->pdf->tableHeader(
                array(_('Parcels total weight (Kg)') . ': ' .
                $this->data[1][1] => 190));
        }
        $this->pdf->ln(3);
    }

    // }}}
    // DeliveryOrder::renderFooter() {{{

    /**
     * Affiche le pied de page
     * @access public
     * @return void
     **/
    public function renderFooter() {
        $content = _('Except written agreement of our share, our conditions of sale as signed by your care apply completely.');
        $this->pdf->addFooter($content, 60);
        parent::renderFooter();
    }

    // }}}
} // }}}

/**
 * RTWDeliveryOrderGenerator
 * Classe utilisee pour les bordereaux de livraison de commandes produit client
 * en contexte pret a porter.
 *
 */
class RTWDeliveryOrderGenerator extends DeliveryOrderGenerator { // {{{
    // DeliveryOrderGenerator::__construct {{{
    /**
     * Constructor
     * @param Object $document DeliveryOrder
     * @param boolean $isReedition true si reedition
     * @return void
     */
    public function __construct($document, $isReedition = false) {
        $autoPrint = $isReedition?false:!DEV_VERSION;
        parent::__construct($document, $isReedition, $autoPrint,
                            _('Delivery order'));
        $date = $this->document->getEditionDate();
        $this->data = $this->command->getDataForRTWBL($date);
    }

    // }}}
    // DeliveryOrder::render() {{{

    /**
     * Construit le document pdf
     *
     * @access public
     * @return void
     */
    public function render() {
        $this->pdf->SetFillColor(220);
        $this->renderHeader();
        $this->pdf->addPage(); // apres le renderHeader()!
        $this->renderAddressesBloc();
        $this->renderCustomsBlocs();
        $this->_renderContent();
        $this->renderTotal1Bloc();
        $this->_renderComment();
        $this->renderFooter();
        return $this->pdf;
    }

    // }}}
    // DeliveryOrder::_renderContent() {{{

    /**
     * Render du contenu du doc
     * @access protected
     * @return void
     */
    function _renderContent() {
        //cellule d�signation personnalis� dans Command.getDataForBL()
        $columns = array(
            _('Reference') => 34,
            _('Description') => 103,
            _('Ordered qty') => 13,
            _('Selling unit') => 15,
            _('Delivered Qty') => 13,
            _('To deliver') => 13);
        $this->pdf->tableHeader($columns, 1);
        $this->pdf->tableBody($this->data[0], $columns);
        $this->pdf->ln(8);
        if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT) {
            $this->pdf->addPage();
            $this->pdf->addHeader();
            /* reaffiche la derniere ligne du tableau pour que le suivant ne
            * soit pas seul.
            */
            $count = sizeof($this->data[0]);
            $this->pdf->tableHeader($columns, 1);
            $this->pdf->tableBody(array($this->data[0][$count-1]), $columns);
            $this->pdf->ln(8);
        }
    }

    // }}}
} // }}}

/**
 * InvoiceGenerator.
 * Classe utilisee pour les factures.
 *
 */
class InvoiceGenerator extends CommandDocumentGenerator { // {{{
    /**
     * Constructor
     *
     * @param Object $document Invoice
     * @param boolean $isReedition mettre � true s'il s'agit d'une r��dition
     * @param boolean $autoPrint true pour impression auto
     * @access protected
     */
    public function __construct($document, $isReedition = false, $autoPrint=false) {
        parent::__construct($document, $isReedition, $autoPrint,
                            _('Invoice'));
    }

    /**
     * Construit la facture pdf
     *
     * @access public
     * @param Object $container InvoiceCollectionGenerator utilise lors d'edition
     * de n factures dans le meme pdf
     * @return PDFDocumentRender Object
     */
    public function render($container=false) {
        $pdfDoc = (!$container)?$this->pdf:$container->pdf;
        $pdfDoc->setFillColor(220);
        $this->renderHeader($pdfDoc);
        $pdfDoc->addPage();  // Apres le renderHeader() !!!!
        if ($container === false) {
            $this->renderAddressesBloc();
        }else {
            $container->renderAddressesBloc();
        }
        $this->renderCustomsBlocs($pdfDoc);
        $this->_renderContent($pdfDoc);
        $this->renderTotal1Bloc($pdfDoc);
        $this->renderTotal2Bloc($pdfDoc);
        $this->renderSNLotBloc($pdfDoc);
        $this->_renderComment($pdfDoc);
        return $pdfDoc;
    }

    /**
     * Construit le contenu du pdf
     * @access protected
     * @param Object $pdfDoc PDFDocumentRender utilise lors d'edition de n factures
     * @return void
     */
    protected function _renderContent($pdfDoc=false) {
        $pdfDoc = (!$pdfDoc)?$this->pdf:$pdfDoc;
        //cellule d�signation personnalis� dans Invoice.DataForInvoice()
        $columns = array(
            _('Reference')=>25,
            _('Description of goods')=>84,
            _('Qty')=>10,
            _('Unit Price net of tax') . ' ' . $this->currency=>15,
            _('Disc')=>13,
            _('VAT %')=>15,
            _('Total Price net of tax') . ' ' . $this->currency=>28);
        $columnsData = $this->document->DataForInvoice($this->currency);
        $pdfDoc->tableHeader($columns, 1);
        $pdfDoc->tableBody($columnsData, $columns);
        $pdfDoc->ln(8);
        if ($pdfDoc->getY() >= PAGE_HEIGHT_LIMIT) {
            $pdfDoc->addPage();
            $pdfDoc->addHeader();
            /* reaffiche la derniere ligne du tableau pour que le suivant ne
            * soit pas seul.
            */
            $count = sizeof($columnsData);
            $pdfDoc->tableHeader($columns, 1);
            $pdfDoc->tableBody(array($columnsData[$count-1]), $columns);
            $pdfDoc->ln(8);
        }
        $comment = $this->document->getComment();
        if(!empty($comment)) {
            $pdfDoc->tableHeader(array(_('Comment') . ' : ' .
                $comment => 190 ), 1);
            $pdfDoc->ln(8);
        }
    }

    // InvoiceGenerator::renderTotal1Bloc() {{{

    /**
     * Affiche le premier tableau total de la facture
     * @access protected
     * @param Object $pdfDoc PDFDocumentRender utilise lors d'edition de n factures
     * @return void
     */
    protected function renderTotal1Bloc($pdfDoc=false) {
        require_once('InvoiceItemTools.php');
        $pdfDoc = (!$pdfDoc)?$this->pdf:$pdfDoc;

        if ($pdfDoc->getY() >= PAGE_HEIGHT_LIMIT_TO_TOTAL) {
            $pdfDoc->addPage();
            $pdfDoc->addHeader();
        }
        $columns = array(
            _('Carriage cost') . ' ' . $this->currency          => 25,
            _('Packing charges') . ' ' . $this->currency           => 25,
            _('Insurance charges') . ' ' . $this->currency         => 25,
            _('Disc')                                           => 15,
            _('Total Price net of tax') . ' ' . $this->currency => 30,
            _('Total VAT') . ' ' . $this->currency              => 40,
            _('Total price') . ' ' . $this->currency            => 30
            );
        $pdfDoc->tableHeader($columns, 1);
        $handing = $this->document->getGlobalHanding();
        $handing = DocumentGenerator::formatPercent($handing);

        // Pour l'affichage du detail par taux de tva
        //$hasTVA = $this->document->hasTVA();
        $tvaRateArray = $this->document->getTVADetail();
        // Formatage pour l'affichage
        $tvaToDisplay = '';
        foreach($tvaRateArray as $key => $value) {
            $tvaToDisplay .= DocumentGenerator::formatPercent($key) . ': ' .
                DocumentGenerator::formatNumber($value) . "\n";
        }

        $pdfDoc->tableBody(array(
            array(
                DocumentGenerator::formatNumber($this->document->getPort()),
                DocumentGenerator::formatNumber($this->document->getPacking()),
                DocumentGenerator::formatNumber($this->document->getInsurance()),
                $handing,
                DocumentGenerator::formatNumber($this->document->getTotalPriceHT()),
                $tvaToDisplay,
                DocumentGenerator::formatNumber($this->document->getTotalPriceTTC())
                ))
            );
        $toPay = $this->document->getToPayForDocument();
        $remExcept = '';

        if(($customerRemExcept=$this->command->getCustomerRemExcep())>0){
            $remExcept = _('Personal discount') . " % : " . $customerRemExcept;
        }
        
        // Ajout d'une ligne s'il y a une taxe Fodec
        $fodecTaxRate = $this->document->getFodecTaxRate();
        if ($fodecTaxRate > 0) {
            $fodecTax = $this->document->getTotalPriceHT() * $fodecTaxRate / 100;
            $pdfDoc->tableHeader(
                array(
                    '' => 120,
                    _('FODEC tax') . ' (' . DocumentGenerator::formatPercent($fodecTaxRate) . '): ' 
                    . DocumentGenerator::formatCurrency($this->currency, $fodecTax) => 70
                )
            );
        }
        // Ajout d'une ligne s'il y a un timbre fiscal
        $taxStamp = $this->document->getTaxStamp();
        if ($taxStamp > 0) {
            $pdfDoc->tableHeader(
                array(
                    '' => 120,
                    _('Tax stamp') . ': ' 
                    . DocumentGenerator::formatCurrency($this->currency, $taxStamp) => 70
                )
            );
        }
        // Ajout d'une ligne s'il y a un acompte, et que c'est la 1ere facture
        // pour la commande associee
        $installment = $this->command->getInstallment();
        if ($installment > 0 && $this->document->isFirstInvoiceForCommand()) {
            $pdfDoc->tableHeader(
                array(
                    '' => 120,
                    _('Instalment') . ': ' 
                    . DocumentGenerator::formatCurrency($this->currency, $installment) => 70
                    )
                );
        }

        $pdfDoc->tableHeader(
            array(
                $remExcept=>120,
                _('Total to pay') . ': ' . DocumentGenerator::formatCurrency($this->currency, $toPay) => 70
                )
            );
        if (I18N::getLocaleCode() != 'tr_TR') {
            $pdfDoc->tableHeader(
                array(
                    _('In letters') . ': '  . $this->numberWords(
                    I18N::extractNumber(I18N::formatNumber($toPay))) => 190
                )
            );
        }
        if($this->document->getGlobalHanding() > 0) {
            $handingDetail = $this->document->getHandingDetail();
            $handingAmount = _('Global discount amount') . ': ' 
                . DocumentGenerator::formatCurrency($this->currency, $handingDetail['handing']);
            $htWithoutDiscount = _('Total excl. VAT before global discount') . ': ' 
                . DocumentGenerator::formatCurrency($this->currency, $handingDetail['ht']);
            // Le seul pas formatte pour les separateurs de milliers, mais ca semble etre un percent
            if ($handingDetail['handingbyrangepercent'] > 0) {
                $handingAmount .= ' (' . sprintf(I_COMMAND_HANDING, $handingDetail['handingbyrangepercent']) . ')';
            }
            $pdfDoc->tableHeader(
                array($handingAmount=>90, $htWithoutDiscount=>100));
        }
        $pdfDoc->ln(3);
    }

    // }}}
    // InvoiceGenerator::renderTotal2Bloc() {{{
    /**
     * Affiche le second tableau total de la facture
     * @access protected
     * @param Object $pdfDoc PDFDocumentRender utilise lors d'edition de n factures
     * @return void
     */
    protected function renderTotal2Bloc($pdfDoc=false) {
        $pdfDoc = (!$pdfDoc)?$this->pdf:$pdfDoc;
        $columns = array(
            _('Means of payment') => 110,
            _('Date') => 40,
            _('Amount incl. VAT') . ' ' . $this->currency => 40
            );
        $pdfDoc->tableHeader($columns, 1);
        $toPay = $this->document->getToPayForDocument();

        $data = array();
        // si accompte
        if ($this->command->getInstallment() > 0) {
            $data[] = array(
                _('Instalment'),
                $this->command->getCommandDate('localedate_short'),
                DocumentGenerator::formatNumber($this->command->getInstallment())
                );
        }
        $data = array(
            $this->document->getPaymentCondition()=>110,
            $this->document->getPaymentDate('localedate_short')=>40,
            DocumentGenerator::formatNumber($toPay)=>40
        );
        $pdfDoc->tableHeader($data);
    }
    // }}}
    
    // InvoiceGenerator::renderComment() {{{

    /**
     * Ajoute le commentaire de la commande
     * @param Object $pdfDoc PDFDocumentRender utilise lors d'edition de n factures
     * @access protected
     * @return void
     */
    protected function _renderComment($pdfDoc=false) {
        $pdfDoc = (!$pdfDoc)?$this->pdf:$pdfDoc;
        $comment = $this->document->getComment();
        if (!empty($comment)) {
            $pdfDoc->ln(8);
            if ($pdfDoc->getY() >= PAGE_HEIGHT_LIMIT) {
                $pdfDoc->addPage();
                $pdfDoc->addHeader();
            }
            $this->pdf->tableHeader(
                array(_('Comment') . ': ' . $comment => 190));
        }
    }

    // }}}

    /**
     * R�cup�re les detail de l'adresse de la banque
     * de l'expediteur
     * @access public
     * @return string
     **/
    public function getExpeditorBankDetail(){
        $abd = $this->expeditor->getActorBankDetail();
        if (!($abd instanceof ActorBankDetail)) {
            return '';
        }
        if (!in_array($this->supplierCustomer->getModality(),
        array(SupplierCustomer::VIREMENT, SupplierCustomer::TRAITE, SupplierCustomer::BILLET_ORDRE))) {
            return '';
        }
        // streettype
        $array = $abd->getBankAddressStreetTypeConstArray();
        $streettype = isset($array[$abd->getBankAddressStreetType()])?
        $array[$abd->getBankAddressStreetType()]:'';
        $data  = sprintf(_("Bank: %s\n"), $abd->getBankName());
        $data .= sprintf("%s %s %s\n", $abd->getBankAddressNo(),
        $streettype, $abd->getBankAddressStreet());
        if ($abd->getBankAddressAdd() != '') {
            $data .= sprintf("%s\n", $abd->getBankAddressAdd());
        }
        $data .= sprintf("%s %s %s\n", $abd->getBankAddressCity(),
        $abd->getBankAddressZipCode(), $abd->getBankAddressCountry());
        return $data;
    }

    /**
     * Retourne la valeur � afficher dans l'en-t�te des donn�es de la facture
     * pour le champs BL
     *
     * @access public
     * @return string
     **/
    function _getBLFieldValue() {
        $value = "";
        $doCollection = $this->document->getDeliveryOrderCollection();
        $count = $doCollection->getCount();
        for($i = 0; $i < $count; $i++){
            $item = $doCollection->getItem($i);
            $value .= sprintf(_("No %s from %s\n"), $item->getDocumentNo(),
                $item->getEditionDate('localedate_short'));
        }
        return $value;
    }
} // }}}

/**
 * RTWInvoiceGenerator.
 * Classe utilisee pour les factures de commandes produit client en contexte 
 * pret a porter.
 *
 */
class RTWInvoiceGenerator extends InvoiceGenerator { // {{{
    /**
     * Construit la facture pdf
     *
     * @access public
     * @param Object $container InvoiceCollectionGenerator utilise lors d'edition
     * de n factures dans le meme pdf
     * @return PDFDocumentRender Object
     */
    public function render($container=false) {
        $pdfDoc = (!$container)?$this->pdf:$container->pdf;
        $pdfDoc->setFillColor(220);
        $this->renderHeader($pdfDoc);
        $pdfDoc->addPage();  // Apres le renderHeader() !!!!
        if ($container === false) {
            $this->renderAddressesBloc();
        }else {
            $container->renderAddressesBloc();
        }
        $this->renderCustomsBlocs($pdfDoc);
        $this->_renderContent($pdfDoc);
        $this->renderTotal1Bloc($pdfDoc);
        $this->renderTotal2Bloc($pdfDoc);
        $this->_renderComment($pdfDoc);
        return $pdfDoc;
    }

    /**
     * Construit le contenu du pdf
     * @access protected
     * @param Object $pdfDoc PDFDocumentRender utilise lors d'edition de n factures
     * @return void
     */
    protected function _renderContent($pdfDoc=false) {
        $pdfDoc = (!$pdfDoc)?$this->pdf:$pdfDoc;
        //cellule d�signation personnalis� dans Invoice.DataForInvoice()
        $columns = array(
            _('Reference')=>34,
            _('Description')=>90,
            _('Qty')=>13,
            _('Unit Price net of tax') . ' ' . $this->currency=>15,
            _('Disc')=>13,
            _('Total Price net of tax') . ' ' . $this->currency=>25);
        $columnsData = $this->document->dataForRTWInvoice($this->currency);
        $pdfDoc->tableHeader($columns, 1);
        $pdfDoc->tableBody($columnsData, $columns);
        $pdfDoc->ln(8);
        if ($pdfDoc->getY() >= PAGE_HEIGHT_LIMIT) {
            $pdfDoc->addPage();
            $pdfDoc->addHeader();
            // reaffiche la derniere ligne du tableau pour que le suivant ne
            // soit pas seul.
            $count = sizeof($columnsData);
            $pdfDoc->tableHeader($columns, 1);
            $pdfDoc->tableBody(array($columnsData[$count-1]), $columns);
            $pdfDoc->ln(8);
        }
        $comment = $this->document->getComment();
        if(!empty($comment)) {
            $pdfDoc->tableHeader(array(_('Comment') . ' : ' .
                $comment => 190 ), 1);
            $pdfDoc->ln(8);
        }
    }
} // }}}

/**
 * Classe fille pour les commandes de cours avec quelques sp�cificit�s
 *
 **/
class CourseCommandInvoiceGenerator extends InvoiceGenerator { // {{{
    /**
     * Constructor
     *
     * @access protected
     */
    public function __construct($invoice) {
        //$this->documentGenerator($invoice);
        parent::__construct($invoice);
    }

    /**
     * Pas de d'addresse destinataire pour une commande de cours
     *
     * @access public
     * @return void
     */
    public function buildLeftAddress() {
    }

    /**
     * Le bloc contenant les items de la facture est l�g�rement diff�rent.
     *
     * @access protected
     * @return void
     */
    protected function _renderContent() {
        $columns = array(
            _('Service')=>117,
            _('Qty')=>17,
            _('Disc')=>17,
            _('VAT %')=>17,
            _('Total Price net of tax') . ' ' . $this->currency=>22);
        $columnsData = $this->document->DataForInvoice($this->currency);
        $this->pdf->tableHeader($columns, 1);
        $this->pdf->tableBody($columnsData, $columns);
        $this->pdf->ln(8);
        if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT) {
            $this->pdf->addPage();
            $this->pdf->addHeader();
            /* reaffiche la derniere ligne du tableau pour que le suivant ne
            * soit pas seul.
            */
            $count = sizeof($columnsData);
            $this->pdf->tableHeader($columns, 1);
            $this->pdf->tableBody(array($columnsData[$count-1]), $columns);
            $this->pdf->ln(8);
        }
    }

    /**
     * Le bloc recapitulant le total est un peu different pour la commande de
     * cours.
     *
     * @access protected
     * @return void
     */
    protected function renderTotal1Bloc() {
        if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT_TO_TOTAL) {
            $this->pdf->addPage();
            $this->pdf->addHeader();
        }

        $columns = array(
            '       '=>58,
            _('Global discount')=>30,
            _('Total Price net of tax') . ' '  . $this->currency=>33,
            _('Total VAT') . ' ' . $this->currency=>33,
            _('Total price') . ' ' . $this->currency=>36
            );
        $this->pdf->tableHeader($columns, 1);
        $handing = $this->document->getGlobalHanding();
        $handing = DocumentGenerator::formatPercent($handing);
        $this->pdf->tableBody(array(
            array(
                '',
                $handing,
                DocumentGenerator::formatNumber($this->document->getTotalPriceHT()),
                DocumentGenerator::formatNumber($this->document->getTotalPriceTTC() -
                        $this->document->getTotalPriceHT()),
                DocumentGenerator::formatNumber($this->document->getTotalPriceTTC())
                ))
            );
        $toPay = $this->document->getTotalPriceTTC() - $this->command->getInstallment();
        
        // Ajout d'une ligne s'il y a une taxe Fodec
        $fodecTaxRate = $this->document->getFodecTaxRate();
        if ($fodecTaxRate > 0) {
            $fodecTax = $this->document->getTotalPriceHT() * $fodecTaxRate / 100;
            $this->pdf->tableHeader(
                array(
                    '' => 120,
                    _('FODEC tax') . ' (' . DocumentGenerator::formatPercent($fodecTaxRate) . '): ' 
                    . DocumentGenerator::formatCurrency($this->currency, $fodecTax)  => 70
                )
            );
        }
        // Ajout d'une ligne s'il y a un timbre fiscal
        $taxStamp = $this->document->getTaxStamp();
        if ($taxStamp > 0) {
            $this->pdf->tableHeader(
                array(
                    '' => 120,
                    _('Tax stamp') . ': ' 
                    . DocumentGenerator::formatCurrency($this->currency, $taxStamp) => 70
                )
            );
        }
        $this->pdf->tableHeader(
            array(
                '' => 128,
                _('Total to pay') . ': ' 
                . DocumentGenerator::formatCurrency($this->currency, $toPay) => 62
                )
            );
        if (I18N::getLocaleCode() != 'tr_TR') {
            $this->pdf->tableHeader(
                array(
                    _('In letters') . ': '  . $this->numberWords(
                    I18N::extractNumber(I18N::formatNumber($toPay))) => 190
                )
            );
        }
        $this->pdf->ln(3);
    }
} // }}}

/**
 * ChainCommandInvoiceGenerator.
 * classe utilis�e pour des factures de commande de transport.
 *
 */
class ChainCommandInvoiceGenerator extends InvoiceGenerator { // {{{
    // __construct() {{{

    /**
     * Constructor
     *
     * @access protected
     */
    public function __construct($invoice) {
        //$this->InvoiceGenerator($invoice);
        parent::__construct($invoice);
    }

    // }}}
    // render() {{{

    /**
     * Construit la facture pdf
     *
     * @access public
     * @param Object $container InvoiceCollectionGenerator utilise lors d'edition
     * de n factures dans le meme pdf
     * @return PDFDocumentRender Object
     */
    public function render($container=false) {
        $pdfDoc = (!$container)?$this->pdf:$container->pdf;
        $pdfDoc->setFillColor(220);
        $this->renderHeader($pdfDoc);
        $pdfDoc->addPage();  // Apres le renderHeader() !!!!
        if ($container === false) {
            $this->renderAddressesBloc();
        }else {
            $container->renderAddressesBloc();
        }
        $this->renderCustomsBlocs($pdfDoc);
        $this->_renderContent($pdfDoc);
        $this->renderPrestationDetail();
        $this->renderTotal1Bloc($pdfDoc);
        $this->renderTotal2Bloc($pdfDoc);
        $this->renderSNLotBloc($pdfDoc);
        $this->_renderComment($pdfDoc);
        return $pdfDoc;
    }

    // }}}
    // renderAddressesBloc() {{{

    /**
     * Render des blocs d'adresses
     * @access public
     * @return void
     */
    public function renderAddressesBloc() {
        $cus = $this->command->getCustomer();
        $site = $cus->getInvoicingSite();
        $this->pdf->rightAdressCaption = _('Address') . ': ';
        $this->pdf->rightAdress = $cus->getQualityForAddress()
            . $cus->getName() . "\n"
            . $site->getFormatAddressInfos("\n");
        $this->pdf->addHeader();
    }

    // }}}
    // _renderContent() {{{

    /**
     * Le bloc contenant les items de la facture est l�g�rement diff�rent.
     *
     * @access protected
     * @return void
     */
    protected function _renderContent() {
        $columns = array(
            _('Parcel type')=>40,
            _('Product type')=>40,
            _('Dimensions')=>30,
            _('Unit weight (Kg)')=>35,
            _('Number of parcels')=>45
        );
        $chainCmdItemCol = $this->command->getCommandItemCollection();
        $count = $chainCmdItemCol->getCount();
        $columnsData = array();
        for ($i=0 ; $i<$count ; $i++) {
            $chainCmdItem = $chainCmdItemCol->getItem($i);
            $coverType = $chainCmdItem->getCoverType();
            $productType = $chainCmdItem->getProductType();
            $columnsData[] = array(
                $coverType->getName(),
                $productType->getName(),
                $chainCmdItem->getHeight() .
                    ' * ' . $chainCmdItem->getWidth() .
                    ' * ' . $chainCmdItem->getLength(),
                $chainCmdItem->getWeight(),
                $chainCmdItem->getQuantity()
            );
        }
        $this->pdf->tableHeader($columns, 1);
        $this->pdf->tableBody($columnsData, $columns);
        $this->pdf->ln(8);
        if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT) {
            $this->pdf->addPage();
            $this->pdf->addHeader();
            /* reaffiche la derniere ligne du tableau pour que le suivant ne
            * soit pas seul.
            */
            $count = sizeof($columnsData);
            $this->pdf->tableHeader($columns, 1);
            $this->pdf->tableBody(array($columnsData[$count-1]), $columns);
            $this->pdf->ln(8);
        }
    }

    // }}}
    // renderTotal1Bloc() {{{

    /**
     *
     * @access protected
     * @return void
     */
    protected function renderTotal1Bloc() {
        if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT_TO_TOTAL) {
            $this->pdf->addPage();
            $this->pdf->addHeader();
        }

        $columns = array(
            _('Packing charges') . ' ' . $this->currency=>30,
            _('Insurance charges') . ' ' . $this->currency=>30,
            _('Disc')=>15,
            _('Total Price net of tax') . ' '  . $this->currency=>45,
            _('Total VAT') . ' ' . $this->currency=>35,
            _('Total incl. VAT') . ' ' . $this->currency=>35
            );
        $this->pdf->tableHeader($columns, 1);
        $handing = $this->document->getGlobalHanding();
        $handing = DocumentGenerator::formatPercent($handing);
        $this->pdf->tableBody(array(
            array(
                DocumentGenerator::formatNumber($this->document->getPacking()),
                DocumentGenerator::formatNumber($this->document->getInsurance()),
                $handing,
                DocumentGenerator::formatNumber($this->document->getTotalPriceHT()),
                DocumentGenerator::formatNumber($this->document->getTotalPriceTTC() -
                        $this->document->getTotalPriceHT()),
                DocumentGenerator::formatNumber($this->document->getTotalPriceTTC())
                ))
            );
        $toPay = $this->document->getToPayForDocument();
        $remExcept = '';

        if(($customerRemExcept=$this->command->getCustomerRemExcep())>0) {
            $remExcept = _('Personal discount') . " % : " .
            $customerRemExcept;
        }
        
        // Ajout d'une ligne s'il y a une taxe Fodec
        $fodecTaxRate = $this->document->getFodecTaxRate();
        if ($fodecTaxRate > 0) {
            $fodecTax = $this->document->getTotalPriceHT() * $fodecTaxRate / 100;
            $this->pdf->tableHeader(
                array(
                    '' => 120,
                    _('FODEC tax') . ' (' . DocumentGenerator::formatPercent($fodecTaxRate) . '): ' 
                    . DocumentGenerator::formatCurrency($this->currency, $fodecTax) => 70
                )
            );
        }
        // Ajout d'une ligne s'il y a un timbre fiscal
        $taxStamp = $this->document->getTaxStamp();
        if ($taxStamp > 0) {
            $this->pdf->tableHeader(
                array(
                    '' => 120,
                    _('Tax stamp') . ': ' 
                    . DocumentGenerator::formatCurrency($this->currency, $taxStamp) => 70
                )
            );
        }
        // Ajout d'une ligne s'il y a un acompte, et que c'est la 1ere facture
        // pour la commande associee
        $installment = $this->command->getInstallment();
        if ($installment > 0 && $this->document->isFirstInvoiceForCommand()) {
            $this->pdf->tableHeader(
                array(
                    '' => 120,
                    _('Instalment') . ': ' 
                    . DocumentGenerator::formatCurrency($this->currency, $installment) => 70
                    )
                );
        }
        $this->pdf->tableHeader(
            array(
                $remExcept => 128,
                _('Total to pay') . ': ' 
                . DocumentGenerator::formatCurrency($this->currency, $toPay) => 62
                )
            );
        if (I18N::getLocaleCode() != 'tr_TR') {
            $this->pdf->tableHeader(
                array(
                    _('In letters') . ': '  . $this->numberWords(
                    I18N::extractNumber(I18N::formatNumber($toPay))) => 190
                )
            );
        }
        $this->pdf->ln(3);
    }

    // }}}
    // renderTotal2Bloc() {{{

    /**
     *
     * @access protected
     * @return void
     */
    protected function renderTotal2Bloc() {
        $columns = array(
            _('Means of payment') => 110,
            _('Date') => 40,
            _('Total') . ' ' . $this->currency => 40
            );
        $this->pdf->tableHeader($columns, 1);
        $toPay = $this->document->getToPayForDocument();

        $data = array();
        // si accompte
        if ($this->command->getInstallment() > 0) {
            $data[] = array(
                _('Instalment'),
                $this->command->getCommandDate('localedate_short'),
                DocumentGenerator::formatNumber($this->command->getInstallment())
                );
        }
        $data[] = array(
            $this->document->getPaymentCondition(),
            strcmp($this->document->getPaymentDate('localedate_short'), '00/00/0000')?$this->document->getPaymentDate('localedate_short'):'',
            DocumentGenerator::formatNumber($toPay)
            );
        $this->pdf->tableBody($data);
    }

    // }}}
    // renderPrestationDetail() {{{

    /**
     * renderPrestationDetail 
     * 
     * @access public
     * @return void
     */
    public function renderPrestationDetail() {
        $columns = array(
            _('Service')=>60,
            _('Qty')=>24,
            _('Unit Price net of tax') . ' ' . $this->currency=>24,
            _('Disc')=>24,
            _('VAT %')=>24,
            _('Total Price net of tax') . ' ' . $this->currency=>34
        );
        $invoiceItemCol = $this->document->getInvoiceItemCollection();
        $data = array();
        foreach($invoiceItemCol as $ivItem) {
            $tva = $ivItem->getTVA();
            $data[] = array(
                $ivItem->getName(),
                $ivItem->getQuantity(),
                $ivItem->getPrestationCost(),
                $ivItem->getHanding(),
                $tva instanceof TVA ? $tva->getRate(): '',
                $ivItem->getUnitPriceHT());
        }
        if(empty($data)) {
            return true;
        }
        $this->pdf->tableHeader($columns, 1);
        $this->pdf->tableBody($data, $columns);
        $this->pdf->ln(8);
        if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT) {
            $this->pdf->addPage();
            $this->pdf->addHeader();
            /* reaffiche la derniere ligne du tableau pour que le suivant ne
            * soit pas seul.
            */
            $count = sizeof($data);
            $this->pdf->tableHeader($columns, 1);
            $this->pdf->tableBody(array($data[$count-1]), $columns);
            $this->pdf->ln(8);
        }
    }

    // }}}
} // }}}

/**
 * PrestationInvoiceGenerator.
 * classe utilis�e pour des factures de prestation.
 *
 */
class PrestationInvoiceGenerator extends InvoiceGenerator { // {{{
    // PrestationInvoiceGenerator::__construct() {{{

    /**
     * Constructor
     *
     * @param Object PrestationInvoice $invoice la facture de prestation
     * @param boolean $isreedition true si r��dition
     */
    public function __construct($invoice, $isReedition=false) {
        //$this->InvoiceGenerator($invoice, $isReedition);
        parent::__construct($invoice, $isReedition);
    }

    // }}}
    // PrestationInvoiceGenerator::render() {{{

    /**
     * lance la construction du pdf.
     */
    public function render() {
        $this->pdf->SetFillColor(220);
        $this->renderHeader();
        $this->pdf->addPage(); // apres le renderHeader()!
        $this->renderAddressesBloc();
        $this->renderCustomsBlocs();
        $this->_renderContent();
        /*$displayProductDetail = $this->documentModel instanceof DocumentModel?
            $this->documentModel->getDisplayProductDetail():false;
        if($displayProductDetail) {
            $this->_renderDetailForProducts();
        }
        $this->_renderTransportDetails();*/
        $this->renderTotal1Bloc();
        $this->renderTotal2Bloc();
        $this->_renderComment();
        //$this->_renderACOList();
        //$this->_renderStockageList();
        $this->renderFooter();
        $this->renderDetails();
        return $this->pdf;
    }

    // }}}
    // PrestationInvoiceGenerator::_renderContent() {{{

    /**
     * Le bloc contenant les items de la facture est l�g�rement diff�rent.
     *
     * @return void
     */
    protected function _renderContent() {
        $columns = array(
            _('Service')=>60,
            _('Qty')=>24,
            _('Unit Price net of tax') . ' ' . $this->currency=>24,
            _('Disc')=>24,
            _('VAT %')=>24,
            _('Total Price net of tax') . ' ' . $this->currency=>34
            );

        $columnsData = $this->document->DataForInvoice($this->currency);
        $this->pdf->tableHeader($columns, 1);
        $this->pdf->tableBody($columnsData, $columns);
        $this->pdf->ln(8);
        if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT) {
            $this->pdf->addPage();
            $this->pdf->addHeader();
            /* reaffiche la derniere ligne du tableau pour que le suivant ne
            * soit pas seul.
            */
            $count = sizeof($columnsData);
            $this->pdf->tableHeader($columns, 1);
            $this->pdf->tableBody(array($columnsData[$count-1]), $columns);
            $this->pdf->ln(8);
        }
    }

    // }}}
    // PrestationInvoiceGenerator::_renderACOList() {{{

    /**
     * Affiche la liste des aco
     *
     * @return void
     */
    private function _renderACOList() {
        $columnsData = $this->document->getDataForACOList();
        if(!empty($columnsData)) {
            $this->pdf->addPage();
            $this->pdf->addHeader();

            $columns = array(
                _('Service') => 30,
                _('Order number')=>33,
                _('Departure actor')=>33,
                _('Arrival actor')=>33,
                _('Date') => 21,
                _('Weight (kg)') => 20,
                _('Volume') => 20
            );
            $this->pdf->Ln();
            $this->pdf->tableHeader($columns, 1);
            $this->pdf->tableBody($columnsData, $columns);
            $this->pdf->ln(8);
            if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT) {
                $this->pdf->addPage();
                $this->pdf->addHeader();
                /* reaffiche la derniere ligne du tableau pour que le suivant ne
                * soit pas seul.
                */
                $count = sizeof($columnsData);
                $this->pdf->tableHeader($columns, 1);
                $this->pdf->tableBody(array($columnsData[$count-1]), $columns);
                $this->pdf->ln(8);
            }
        }
    }

    // }}}
    // PrestationInvoiceGenerator::_renderStockageList() {{{

    /**
     * Affiche un tableau si prestation de stockage:
     * (StoreName, LocationName)
     *
     * @return void
     */
    private function _renderStockageList() {
        if (!$this->document->isWithStock()) {
            return true;
        }
        // Les InvoiceItem pour le Stockage (au plus 1 seule, a priori...)
        $invoiItemColl = $this->document->getInvoiceItemCollection(
                array('Prestation.Type' => Prestation::PRESTATION_TYPE_STOCKAGE),
                array(), array('Prestation'));
        $count = $invoiItemColl->getCount();
        for($i = 0; $i < $count; $i++){
            $invItem = $invoiItemColl->getItem($i);
            $olColl = $invItem->getOccupiedLocationCollection(
                    array(),
                    array('Location.Store' => SORT_ASC, 'Location' => SORT_ASC),
                    array('Location'));  // lazy
        }
        $columns = array(_('Store') => 95, _('Location')=> 95);

        $this->pdf->Ln();
        $this->pdf->addText(
            _('Details of locations charged in storage service.'));
        $this->pdf->tableHeader($columns, 1);
        $columnsData = $this->document->getStockageLocationList();
        if (!empty($columnsData)) {
            $this->pdf->tableBody($columnsData, $columns);
        }
    }

    // }}}
    // PrestationInvoiceGenerator::_renderDetailForProducts() {{{

    /**
     * Affiche un d�tail des produits factur�s.
     *
     * R�cup�re tous les InvoiceItem.LEM.Product et pour chacun d'entre eux
     * regarde si il existe une ProductPrestationCost. Si oui on ajoute un
     * tableau d�taillant le calcul du prix pour le Product
     *
     * @return void
     */
    private function _renderDetailForProducts() {
        $header = array(
            _('Order number') => 30,
            _('Reference') => 80,
            _('Moved quantity') => 50,
            _('Unit price excl. VAT')  . ' ' . $this->currency => 30);

        /* pour chaque invoiceItem de la facture, si il est associ� � des 
         * mouvements (InvoiceItem.LEM), on affiche un tableau d�taillant les 
         * produits mouvement�s (LEM.Product)
         */
        $invoiceItemCol = $this->document->getInvoiceItemCollection();
        $counti = $invoiceItemCol->getCount();
        $data = array();
        for($i=0 ; $i<$counti ; $i++) {
            $invoiceItem = $invoiceItemCol->getItem($i);
            $prestation = $invoiceItem->getPrestation();
            $lemCol = $invoiceItem->getLocationExecutedMovementCollection();
            $countj = $lemCol->getCount();
            for($j=0 ; $j<$countj ; $j++) {
                $lem = $lemCol->getItem($j);
                $product = $lem->getProduct();
                $cmdNo = Tools::getValueFromMacro($lem,
                        '%ExecutedMovement.ActivatedMovement.ProductCommand.CommandNo%');
                $data[] = array(
                    $cmdNo,
                    $product->getBaseReference(),
                    $lem->getQuantity(),
                    DocumentGenerator::formatNumber($invoiceItem->getPrestationCost())
                );
            }
        }
        $return = array();
        if(!empty($data)) {
            $return = array(
                'title' => _('Details of products moved for the service ') .
                $prestation->getName(),
                'header' => $header,
                'data' => $data);
        }
        return $return;
    }

    // }}}
    // _renderTransportDetails() {{{

    /**
     * _renderTransportDetails 
     * 
     * @access private
     * @return void
     */
    private function _renderTransportDetails() {
        require_once('Objects/ActivatedChainOperation.inc.php');
        require_once('Objects/Task.inc.php');
        
        $invoiceItemCol = $this->document->getInvoiceItemCollection();
        $data = array();
        foreach($invoiceItemCol as $invoiceItem) {
            //echo '### invoiceItemId: ' . $invoiceItem->getId();
            $acoCol = $invoiceItem->getActivatedChainOperationFacturedCollection();
            // #*# CORRECTION A TESTER... experimental...
            // Dans certains cas (!!) on n'a pas de aco, mais des lem!!
/*            if ($acoCol->getCount() == 0) {
                $lemColl = $invoiceItem->getLocationExecutedMovementCollection();
                $lemQties = array();
                foreach($lemColl as $lem) {
                    $cmdNo = Tools::getValueFromMacro($lem,
                        '%ExecutedMovement.ActivatedMovement.ProductCommand.CommandNo%');
                    if (!isset($lemQties[$cmdNo])) {
                        $lemQties[$cmdNo] = 0;
                    }
                    $lemQties[$cmdNo] += $lem->getQuantity();
                    // La date du dernier LEM trouve: <gerard> pour l'instant on ne fait pas mieux
                    $date = $lem->getDate();
                }
                foreach($lemQties as $cmdNo=>$qty) {
                    $data[] = array(
                        $cmdNo,
                        I18n::formatDate($date, I18N::DATE_LONG),
                        '',
                        '',
                        '',
                        $qty,
                        DocumentGenerator::formatNumber($invoiceItem->getPrestationCost()));
                }
            }*/
            // #*# /fin CORRECTION A TESTER... experimental...
            foreach($acoCol as $aco) {
                $depZone = Tools::getValueFromMacro($aco,
                    '%FirstTask.ActorSiteTransition.DepartureSite.Zone%');
                $arrZone = Tools::getValueFromMacro($aco,
                    '%LastTask.ActorSiteTransition.ArrivalSite.Zone%');
                $arrSiteName = Tools::getValueFromMacro($aco,
                    '%LastTask.ActorSiteTransition.ArrivalSite.Name%');
                $date = Tools::getValueFromMacro($aco, '%FirstTask.Begin%');
                $ach = $aco->getActivatedChain();
                $cmiCol = $ach->getCommandItemCollection();
                $cmi = $cmiCol->getItem(0);
                $cmdNo  = $cmi->getCommand()->getCommandNo();
                $qty = $invoiceItem->getQuantity();
// / Debut de bloc comment� le 07/12/2007 pour 'une' correction, et finalement correction annulee le 11/02/2208 
                $qty = 0; 
                // box
                $filter = array(
                    SearchTools::newFilterComponent('ActivatedChainOperation', 
                    'ActivatedChainTask().ActivatedOperation.ActivatedChain.Id',
                        'Equals', $ach->getId(), 1, 'Box'),
                    SearchTools::newFilterComponent('PrestationFactured', '', 'Equals', 1, 1),
                    SearchTools::newFilterComponent('InvoicePrestation', '', 'Equals', 
                        $this->document->getId(), 1));
                $filter = SearchTools::filterAssembler($filter);
                $boxCol = Object::loadCollection('Box', $filter);
                $qty = count($boxCol);
                // lem
                $filter = array(
                    SearchTools::newFilterComponent('LEM',
                        'ExecutedMovement.ActivatedMovement.ActivatedChainTask.ActivatedOperation.ActivatedChain.Id',
                        'Equals', $ach->getId(), 1, 'LocationExecutedMovement'),
                    SearchTools::newFilterComponent('TransportPrestationFactured', '',
                        'Equals', 1, 1),
                    SearchTools::newFilterComponent('InvoicePrestation', '', 'Equals', 
                        $this->document->getId(), 1));
                $filter = SearchTools::filterAssembler($filter);
                $lemCol = Object::loadCollection('LocationExecutedMovement', $filter);
                foreach($lemCol as $lem) {
                    $qty += $lem->getQuantity();
                }

                //echo '  (si ==0 on va chercher le contenu de invoiceItem.qty) qty: ' . $qty . '<br>'; 
                //echo '$qty = $invoiceItem->getQuantity(): ' . $invoiceItem->getQuantity() . '<br>';             
                if($qty == 0) {
                    $qty = $invoiceItem->getQuantity();
                }
// / Fin de bloc comment� pour 'une' correction            
                $data[] = array(
                    $cmdNo,
                    I18n::formatDate($date, I18N::DATE_LONG),
                    $depZone ? $depZone : '',
                    $arrZone ? $arrZone : '',
                    $arrSiteName,
                    $qty,
                    DocumentGenerator::formatNumber($invoiceItem->getPrestationCost()));
            }
        }

        $return = array();
        if(!empty($data)) {
            $header = array(
                _('Order number') => 25,
                _('Date') => 20,
                _('Departure zone') => 35,
                _('Arrival zone') => 35,
                _('Arrival site') => 30,
                _('Qty') => 20,
                _('Unit Price net of tax') . ' ' . $this->currency=>25);
            $return = array(
                'title' => _('Transport operations details'),
                'header' => $header,
                'data' => $data);
        }
        return $return;
    }

    // }}} 
    // PrestationInvoiceGenerator::buildLeftAddress() {{{

    /**
     * pas d'addresse de livraison pour la facture de prestation
     *
     * @access public
     * @return void
     */
    public function buildLeftAddress() {
        $this->pdf->leftAdressCaption = '';
        $this->pdf->leftAdress = '';
    }

    // }}}
    // InvoiceGenerator::renderTotal1Bloc() {{{

    /**
     * Affiche le premier tableau total de la facture
     * @access protected
     * @return void
     */
    protected function renderTotal1Bloc() {
        require_once('InvoiceItemTools.php');

        if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT_TO_TOTAL) {
            $this->pdf->addPage();
            $this->pdf->addHeader();
        }
        $columns = array(
            _('Carriage cost') . ' ' . $this->currency          => 25,
            _('Packing charges') . ' ' . $this->currency           => 25,
            _('Insurance charges') . ' ' . $this->currency         => 25,
            _('Disc')                                           => 15,
            _('Total Price net of tax') . ' ' . $this->currency => 30,
            _('Total VAT') . ' ' . $this->currency              => 40,
            _('Total price incl. VAT') . ' ' . $this->currency            => 30
            );
        $this->pdf->tableHeader($columns, 1);
        $handing = $this->document->getGlobalHanding();
        $handing = DocumentGenerator::formatPercent($handing);

        // Pour l'affichage du detail par taux de tva
        //$hasTVA = $this->document->hasTVA();
        $tvaRateArray = $this->document->getTVADetail();
        // Formatage pour l'affichage
        $tvaToDisplay = '';
        foreach($tvaRateArray as $key => $value) {
            $tvaToDisplay .= DocumentGenerator::formatPercent($key) . ': ' .
                DocumentGenerator::formatNumber($value) . "\n";
        }

        $this->pdf->tableBody(array(
            array(
                DocumentGenerator::formatNumber($this->document->getPort()),
                DocumentGenerator::formatNumber($this->document->getPacking()),
                DocumentGenerator::formatNumber($this->document->getInsurance()),
                $handing,
                DocumentGenerator::formatNumber($this->document->getTotalPriceHT()),
                $tvaToDisplay,
                DocumentGenerator::formatNumber($this->document->getTotalPriceTTC())
                ))
            );
        $toPay = $this->document->getToPayForDocument();
        $remExcept = '';

        if(($customerRemExcept=$this->command->getCustomerRemExcep())>0){
            $remExcept = _('Personal discount') . " % : " . $customerRemExcept;
        }
        
        // Ajout d'une ligne s'il y a une taxe Fodec
        $fodecTaxRate = $this->document->getFodecTaxRate();
        if ($fodecTaxRate > 0) {
            $fodecTax = $this->document->getTotalPriceHT() * $fodecTaxRate / 100;
            $this->pdf->tableHeader(
                array(
                    '' => 120,
                    _('FODEC tax') . ' (' . DocumentGenerator::formatPercent($fodecTaxRate) . '): ' 
                    . DocumentGenerator::formatCurrency($this->currency, $fodecTax) => 70
                )
            );
        }
        // Ajout d'une ligne s'il y a un timbre fiscal
        $taxStamp = $this->document->getTaxStamp();
        if ($taxStamp > 0) {
            $this->pdf->tableHeader(
                array(
                    '' => 120,
                    _('Tax stamp') . ': ' 
                    . DocumentGenerator::formatCurrency($this->currency, $taxStamp) => 70
                )
            );
        }
        // Ajout d'une ligne s'il y a un acompte, et que c'et la 1ere facture
        // pour la commande associee
        $installment = $this->command->getInstallment();
        if ($installment > 0 && $this->document->isFirstInvoiceForCommand()) {
            $this->pdf->tableHeader(
                array(
                    '' => 120,
                    _('Instalment') . ': ' 
                    . DocumentGenerator::formatCurrency($this->currency, $installment) => 70
                    )
                );
        }

        $this->pdf->tableHeader(
            array(
                $remExcept=>120,
                _('Total to pay') . ': ' 
                . DocumentGenerator::formatCurrency($this->currency, $toPay) =>70
                )
            );
        if (I18N::getLocaleCode() != 'tr_TR') {
            $this->pdf->tableHeader(
                array(
                    _('In letters') . ': '  . $this->numberWords(
                    I18N::extractNumber(I18N::formatNumber($toPay)))=>190
                )
            );
        }
        if($this->document->getGlobalHanding()>0){
            $handingDetail = $this->document->getHandingDetail();
            $handingAmount = _('Global discount amount') .': ' 
                . DocumentGenerator::formatCurrency($this->currency, $handingDetail['handing']);
            $htWithoutDiscount = _('Total excl. VAT before global discount') . ': ' 
                . DocumentGenerator::formatCurrency($this->currency, $handingDetail['ht']);
            $this->pdf->tableHeader(
                array($handingAmount=>90, $htWithoutDiscount=>100));
        }
        $this->pdf->ln(3);
    }

    // }}}
    // InvoiceGenerator::renderTotal2Bloc() {{{
    /**
     * Affiche le second tableau total de la facture
     * @access protected
     * @return void
     */
    protected function renderTotal2Bloc() {
        $columns = array(
            _('Means of payment') => 110,
            _('Date') => 40,
            _('Total to pay') . ' ' . $this->currency => 40
            );
        $this->pdf->tableHeader($columns, 1);
        $toPay = $this->document->getToPayForDocument();

        $data = array();
        // si accompte
        if ($this->command->getInstallment() > 0) {
            $data[] = array(
                _('Instalment'),
                $this->command->getCommandDate('localedate_short'),
                DocumentGenerator::formatNumber($this->command->getInstallment())
                );
        }
        $data = array(
            $this->document->getPaymentCondition() => 110,
            $this->document->getPaymentDate('localedate_short') => 40,
            DocumentGenerator::formatNumber($toPay) => 40
        );
        $this->pdf->tableHeader($data);
    }
    // }}}
    // renderDetails() {{{

    public function renderDetails() {
        $details = array();
        $displayProductDetail = $this->documentModel instanceof DocumentModel?
            $this->documentModel->getDisplayProductDetail():false;
        if($displayProductDetail) {
            $result = $this->_renderDetailForProducts();
            if(!empty($result)) {
                $details[] = $result;
            }
        }
        $result = $this->_renderTransportDetails();
        if(!empty($result)) {
            $details[] = $result;
        }
        if(!empty($details)) {
            $this->pdf->addPage();
            $this->renderAddressesBloc();
            foreach($details as $index=>$detail) {
                if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT-17) {
                    $this->pdf->addPage();
                    $this->pdf->addHeader();
                }
                $this->pdf->addText($detail['title'], 
                    array('fontSize'=>11, 'lineHeight'=>5));
                $this->pdf->tableHeader($detail['header'], 1);
                $this->pdf->tableBody($detail['data'], $detail['header']);
                $this->pdf->ln();
            }
        }
    }

    // }}} 
} // }}}

/**
 * ToHaveGenerator
 * Classe utilis�e pour les avoirs.
 *
 */
class ToHaveGenerator extends DocumentGenerator { // {{{
    /**
     * Constructor
     *
     * @param Object $invoice l'objet Invoice
     * @param boolean $isReedition mettre � true s'il s'agit d'une r��dition
     * @access protected
     */
    public function __construct($document, $isReedition = false) {
        $this->supplierCustomer = $document->getSupplierCustomer();
        $this->expeditor = $this->supplierCustomer->getSupplier();
        $this->expeditorSite = $this->expeditor->getMainSite();
        $this->destinator = $this->supplierCustomer->getCustomer();
        $this->destinatorSite = $this->destinator->getMainSite();
        $cur = $document->getCurrency();
        parent::__construct($document, $isReedition, true, $cur, _('Credit note'));
    }

    /**
     * proprietes de classe servant de raccourcis pour les diverses methodes
     */
    public $Customer = false;

    /**
     * Construit la facture pdf
     *
     * @access public
     * @return void
     */
    public function render() {
        $this->pdf->SetFillColor(220);
        $this->renderHeader();
        $this->pdf->addPage(); // apres le renderHeader()!
        $this->renderAddressesBloc();
        $this->renderCustomsBlocs();
        $this->_renderContent();
        $this->renderFooter();
        return $this->pdf;
    }

    /**
     * Pas de d'addresse destinataire pour un avoir
     *
     * @access public
     * @return void
     */
    public function buildLeftAddress() {
    }

    /**
     * Tableau 'principal'
     * @access protected
     * @return void
     */
    protected function _renderContent() {
        $columns = array(
            _('Product Reference') => 33,
            _('Description of goods') => 62,
            _('Qty') => 16,
            _('Unit Price net of tax') . ' ' . $this->currency => 23,
            _('Disc') => 10,
            _('Total VAT') . ' ' . $this->currency => 25 ,
            _('Total incl. VAT') . ' ' . $this->currency => 23
            );

        // calcul de la tva
        $tvaRate = Tools::getValueFromMacro($this->document, '%TVA.Rate%');
        $tva = DocumentGenerator::formatNumber($this->document->getTotalPriceHT() * $tvaRate / 100);
        $tvaStr = DocumentGenerator::formatPercent($tvaRate) .' : ' . $tva;

        $columnsData = array(array($this->document->getDocumentNo(),
            _('Credit note'),
            1,
            DocumentGenerator::formatNumber($this->document->getTotalPriceHT()),
            '',
            $tvaStr,
            DocumentGenerator::formatNumber($this->document->getTotalPriceTTC())));
        $this->pdf->tableHeader($columns, 1);
        $this->pdf->tableBody($columnsData, $columns);
        $this->pdf->ln(5);
        if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT) {
            $this->pdf->addPage();
            $this->pdf->addHeader();
            /* reaffiche la derniere ligne du tableau pour que le suivant ne
            * soit pas seul.
            */
            $count = sizeof($columnsData);
            $this->pdf->tableHeader($columns, 1);
            $this->pdf->tableBody(array($columnsData[$count-1]), $columns);
            $this->pdf->ln(5);
        }
        $this->pdf->tableHeader(array(_('Credit note reason') => 192), 1);
        $this->pdf->tableBody(array(array($this->document->getComment())));
    }

} // }}}

/**
 * PackingListGenerator.
 * Classe utilis�e pour les listes de colisage.
 *
 */
class PackingListGenerator extends DocumentGenerator { // {{{
    /**
     * Constructor
     *
     * @param Object $invoice l'objet Invoice
     * @param boolean $isReedition mettre � true s'il s'agit d'une r��dition
     * @access protected
     */
    public function __construct($document, $isReedition = false) {
        $this->Box = $document->getBox();
        $this->supplierCustomer = $document->getSupplierCustomer();
        $this->expeditor = $this->supplierCustomer->getSupplier();
        $this->expeditorSite = $this->expeditor->getMainSite();
        $this->destinator = $this->supplierCustomer->getCustomer();
        $this->destinatorSite = $this->destinator->getMainSite();
        $cur = $document->getCurrency();
        parent::__construct($document, $isReedition, true, $cur,
                            _('Packing list'));
    }

    /**
     * proprietes de classe servant de raccourcis pour les diverses methodes
     */
    public $Box = false;

    /**
     * Construit la facture pdf
     *
     * @access public
     * @return void
     */
    public function render() {
        $this->pdf->SetFillColor(220);
        $this->renderHeader();
        $this->pdf->addPage(); // apres le renderHeader()!
        $this->renderAddressesBloc();
        $this->renderCustomsBlocs();
        $this->_renderContent();
        $this->_renderTotalBloc();
        $this->renderFooter();
        return $this->pdf;
    }

    /**
     * Donnees a afficher dans toutes les pages, juste en dessous du header
     * @access public
     * @return void
     */
    public function renderAddressesBloc() {
        // adresse de facturation: seulement si une seule Command liee a la Box!
        $Command = $this->Box->getCommand();
        if (!Tools::isEmptyObject($Command)) {
            $iSite = $this->destinator->getInvoicingSite();
            $iAddressStr = $this->destinator->getQualityForAddress()
                . $this->destinator->getName() . "\n"
                . $iSite->getFormatAddressInfos("\n");
            $this->pdf->leftAdressCaption = _('Billing address') . ': ';
            $this->pdf->leftAdress = $iAddressStr;
        }

        $this->_buildRightAddress();
        $this->pdf->addHeader();
    }

    /**
     * Affiche l'Adresse de livraison
     *
     * @access public
     * @return void
     **/
    public function _buildRightAddress() {
        $destinatorSite = $this->Box->getDestinatorSite();
        $destinatorAddressStr = $this->destinator->getQualityForAddress()
            . $this->destinator->getName() . "\n"
            . $destinatorSite->getFormatAddressInfos("\n");
        $this->pdf->rightAdressCaption = _('Delivery address') . ': ';
        $this->pdf->rightAdress = $destinatorAddressStr;
    }

    /**
     * affiche les infos sur la facture et le client et le commercial
     * @access private
     * @return void
     */
    private function _renderPackingListBloc() {
        $columnsData = array();  // Les donnees a afficher
        $columns = array(
            _('Packing list') => 32,
            _('Customer') => 40,
            _('Order') => 30,
            _('Customer order number') => 30,
            _('Deal number') . ' ' => 35,
            _('Carriage cost') . ' ' => 25
            );
        $this->pdf->tableHeader($columns, 1);

        $editionDate = $this->document->getEditionDate('localedate_short');

        $CommandCollection = $this->Box->getCommandCollection();
        $count = $CommandCollection->getCount();
        for($i = 0; $i < $count; $i++) {
            $currentData = array();
            $Command = $CommandCollection->getItem($i);
            $CustomerName = Tools::getValueFromMacro($Command, '%Destinator.Name%');
            $CustomerPhone = Tools::getValueFromMacro($Command, '%DestinatorSite.Phone%');

            $currentData[0] = ($i == 0)?
            sprintf(_('Number %s from %s'), $this->document->getDocumentNo(), $editionDate):'';
            $currentData = $currentData
                + array(1 => sprintf(_("%s \n (Tel: %s)"), $CustomerName, $CustomerPhone),
                    2 => $Command->getCommandNo(),
                    3 => Tools::getValueFromMacro($Command, '%CommandExpeditionDetail.CustomerCommandNo%'),
                    4 => Tools::getValueFromMacro($Command, '%CommandExpeditionDetail.Deal%'),
                    5 => Tools::getValueFromMacro($Command, '%Incoterm.Label%'));
            $columnsData[$i] = $currentData;
            unset($currentData);
        }

        $this->pdf->tableBody($columnsData);
        $this->pdf->ln(5);
    }

    /**
     * Tableau 'principal'
     * @access protected
     * @return void
     */
    protected function _renderContent() {
        //cellule description personalis�e dans Box.getContentInfoForPackingList()
        $columns = array(
            _('Reference') => 42,
            _('Description') => 60,
            _('Quantity') => 20,
            _('Weight (kg)')  => 20,
            _('Dimensions') => 30,
            _('Volume (l)') => 20
            );
        $this->pdf->tableHeader($columns, 1);

        $BoxCollection = $this->Box->getBoxCollection();
        $count = $BoxCollection->getCount();
        $columnsData = array();
        /*for($i = 0; $i < $count; $i++){
        $Box = $BoxCollection->getItem($i);
        $columnsData = array_merge($columnsData, $Box->getDataForPackingList());
        }*/
        $PackingList = $this->Box->getPackingList();
        $columnsData = $this->Box->getDataForPackingList($this->Box->getLevel(),
        $PackingList->getDocumentModel());

        $this->pdf->tableBody($columnsData, $columns);
        $this->pdf->ln(5);
        if ($this->pdf->getY() >= PAGE_HEIGHT_LIMIT) {
            $this->pdf->addPage();
            $this->pdf->addHeader();
            //apres viendra le tableau poid volume, on reaffiche
            // la derniere ligne des donnees pour qu'il ne soit pas seul
            $count = sizeof($columnsData);
            $this->pdf->tableHeader($columns, 1);
            $this->pdf->tableBody(array($columnsData[$count-1]), $columns);
            $this->pdf->ln(5);
        }
    }

    /**
     * Affiche le total du poids et du volume
     * @access private
     * @return void
     */
    private function _renderTotalBloc() {
        $this->pdf->tableHeader(
            array(_('Total weight (kg)') . ': ' . DocumentGenerator::formatNumber($this->Box->getWeight()) => 192)
            );
        $this->pdf->tableHeader(
            array(_('Total volume (m3)') . ': ' . DocumentGenerator::formatNumber($this->Box->getVolume() / 1000, 3) => 192)
            );
        $this->pdf->ln(3);
    }

    /**
     * On surcharge la m�thode DocumentGenerator::renderCustomsBlocs
     * pour afficher les infos de toutes les commandes. Il y a une ligne
     * de donn�e par commande. Le contenu des cellules list� dans $unique
     * n'apparait que sur la premi�re ligne.
     *
     * @access public
     * @return void
     */
    public function renderCustomsBlocs() {
        require_once ('Objects/DocumentModelProperty.inc.php');
        $unique = array(DocumentModelProperty::CELL_NO_DOC);
        $dom = $this->document->findDocumentModel();
        if($dom instanceof DocumentModel) {
            $CommandCollection = $this->Box->getCommandCollection();
            $commandCount = $CommandCollection->getCount();

            $domPropCol = $dom->getDocumentModelPropertyCollection(array('Property'=>0));
            $numberOfProperties = $domPropCol->getCount();
            $numberOfTable = ceil($numberOfProperties / NUMBER_OF_CELLS_PER_TABLE);

            $domMapper = Mapper::singleton('DocumentModelProperty');
            // pour chaque tableau :
            for ($i=1 ; $i<=$numberOfTable ; $i++) {
                // r�cup�rer les 5 documentModelProperty de la table dans l'ordre
                $domPropCol = $domMapper->loadCollection(
                    array('Property' => 0,
                      'DocumentModel' => $dom->getId()),
                    array('Order' => SORT_ASC),
                    array('PropertyType'), NUMBER_OF_CELLS_PER_TABLE, $i);

                $headerColumns = array();
                $dataColumns = array();
                $cells = $domPropCol->getCount();
                $cellsWidth = PAGE_WIDTH / $cells;
                for ($j=0 ; $j<$cells ; $j++) {
                    $property = $domPropCol->getItem($j);
                    // cr�ation du header
                    $headerColumns[getDocumentModelPropertyCellLabel(
                    $property->getPropertyType())] = $cellsWidth;
                    // cr�ation du contenu
                    for($k=0 ; $k<$commandCount ; $k++) {
                        if ($k>0 && in_array($property->getPropertyType(), $unique)) {
                            $dataColumns[$k][] = '';
                            continue;
                        }
                        $cmd = $CommandCollection->getItem($k);
                        $dataColumns[$k][] = getDocumentModelPropertyCellValue(
                        $property->getPropertyType(), $this, $cmd);
                    }
                }
                $this->pdf->tableHeader($headerColumns, 1);
                $this->pdf->tableBody($dataColumns);
                $this->pdf->ln(3);
                unset($headerColumns, $dataColumns);
            }
        }
    }

} // }}}

/**
 * InvoicesListGenerator.
 * Sert � g�n�rer les relev� de factures simples ou avec lettre de change.
 *
 */
class InvoicesListGenerator extends DocumentGenerator { // {{{

    private $_withChangeLetter;
    public $supplierCustomer;

    /**
      * Constructeur
      *
      * @param boolean $full true pour un relev� avec lettre de change
      * @access public
      * @return void
      */
    public function __construct($document, $full=false) {
        $this->_withChangeLetter = $full;
        $this->supplierCustomer = $document->getSupplierCustomer();
        $this->expeditor = $this->supplierCustomer->getSupplier();
        $this->expeditorSite = $this->expeditor->getMainSite();
        $this->destinator = $this->supplierCustomer->getCustomer();
        $this->destinatorSite = $this->destinator->getMainSite();
        $this->bigTotals = array();
        $this->bigTotals['ht'] = 0;
        $this->bigTotals['ttc'] = 0;
        $this->bigTotals['tva'] = 0;
        $this->bigTotals['toPay'] = 0;
        $cur = $document->getCurrency();
        parent::__construct($document, false, true, $cur,
                            _('Statement of invoices'));
    }

    /**
      * Effectue le render du doc.
      *
      * @access public
      * @return Object PDFDocumentRender
      */
    public function render() {
        $this->pdf->SetFillColor(220);
        $this->renderHeader();
        $this->pdf->addPage(); // apres le renderHeader()!
        $this->renderAddressesBloc();
        $this->renderCustomsBlocs();
        $this->_renderContent();
        if($this->_withChangeLetter) {
            $this->_renderChangeLetter();
        }
        $this->renderFooter();
        return $this->pdf;
    }

    /**
      * Le header contient le logo du supplier (acteur connect�)
      * et le nom du document.
      *
      * @return void
      * @access public
      */
    public function renderHeader() {
        $this->pdf->logo = base64_decode($this->expeditor->getLogo());
        $this->pdf->docTitle = $this->docName;
        //$this->pdf->header(); // inutile: appele par addPage
    }

    /**
      * r�cup�re l'addresse de facturation du customer
      * et l'ajoute au header.
      *
      * @access public
      * @return void
      */
    public function renderAddressesBloc() {
        // addresse de facturation
        $invoicingSite = $this->destinator->getInvoicingSite();
        $invoicingAddressStr = $this->destinator->getQualityForAddress() .
            $this->destinator->getName() . "\n" .
            $invoicingSite->getFormatAddressInfos("\n");
        $this->pdf->rightAdressCaption = _('Billing address') . ': ';
        $this->pdf->rightAdress = $invoicingAddressStr;
        $this->pdf->addHeader();
    }

    /**
     * Effectue le render du tableau des factures
     *
     * @access protected
     * @return void
     */
    protected function _renderContent() {
        $text = _('Dear customer, please find enclosed details for invoices remaining to pay');
        $endTextFormat = _('for period from %s to %s.');
        $startDate=$this->document->getBeginDate();
        $endDate=$this->document->getEndDate();
        if($startDate && $endDate) {
            $text .= ' ' . sprintf($endTextFormat,
                $this->document->getBeginDate(),
                $this->document->getEndDate());
        }
        $this->pdf->addText($text);

        $columnsHeader = array(
            _('Edition Date') => 30,
            _('Number') => 32,
            _('Total Price net of tax') . ' ' . $this->currency => 32,
            _('Total VAT') . ' ' . $this->currency=> 32,
            _('Total price') . ' ' . $this->currency => 32,
            _('To pay') . ' ' . $this->currency => 32);

        $columnsData = array();

        $invoiceCol = $this->document->getInvoiceCollection();
        $count = $invoiceCol->getCount();
        for ($i=0 ; $i<$count ; $i++) {
            $invoice = $invoiceCol->getItem($i);
            $ht = $invoice->getTotalPriceHT();
            $ttc = $invoice->getTotalPriceTTC();
            $tva = $ttc - $ht;
            $toPay = $invoice->getToPay();
            $this->bigTotals['ht'] += $ht;
            $this->bigTotals['ttc'] += $ttc;
            $this->bigTotals['tva'] += $tva;
            $this->bigTotals['toPay'] += $toPay;
            $columnsData[$i] = array(
                I18N::formatDate($invoice->getEditionDate(), I18N::DATE_LONG),
                $invoice->getDocumentNo(),
                DocumentGenerator::formatNumber($ht),
                DocumentGenerator::formatNumber($tva),
                DocumentGenerator::formatNumber($ttc),
                DocumentGenerator::formatNumber($toPay));
        }

        $this->pdf->tableHeader($columnsHeader, 1);
        $this->pdf->tableBody($columnsData, $columnsHeader);

        foreach ($this->bigTotals as $key=>$value) {
            $this->bigTotals[$key] = DocumentGenerator::formatNumber($value);
        }

        $this->pdf->tableHeader(array(
            _('Total') => 62,
            $this->bigTotals['ht'] => 32,
            $this->bigTotals['tva'] => 32,
            $this->bigTotals['ttc'] . ' ' => 32,
            $this->bigTotals['toPay'] . '  ' => 32));
    }

    /**
     * Effectue le render de la lettre de change
     *
     * @access private
     * @return void
     */
    private function _renderChangeLetter() {
        require_once('Objects/ActorBankDetail.php');

        if($this->pdf->getY() > PAGE_HEIGHT_LIMIT_TO_CHANGE_LETTER) {
            $this->pdf->addPage();
        }
        $x = $this->pdf->getX();
        $y = $this->pdf->getY();

        $this->pdf->Cell(PAGE_WIDTH, 140, '', 1);
        $y +=5;
        $x +=5;
        $this->pdf->setXY($x, $y);
        if ($this->pdf->logo != '') {
            $this->pdf->Image($this->pdf->logo, $x, $y, 0, 17, 'png');
        }

        $text = _('Please pay amount stated below for this bill of exchange (that excludes charges) to the account of');

        $this->pdf->setXY(90, $y);
        $this->pdf->addText($text, array('width'=>50, 'border'=>1));

        // ActorBankDetail
        $abdId = Tools::getValueFromMacro($this->destinator,
        '%AccountingType.ActorBankDetail.Id%');
        $actorBankDetail = Object::load('ActorBankDetail', $abdId);
        if (!Tools::isEmptyObject($actorBankDetail)) {
            $streetTypes = ActorBankDetail::getBankAddressStreetTypeConstArray();
            $strType = isset($streetTypes[$actorBankDetail->getBankAddressStreetType()])?
                $streetTypes[$actorBankDetail->getBankAddressStreetType()]:'';
            $addressBankStr = $actorBankDetail->getBankName() . "\n" .
            $actorBankDetail->getBankAddressNo() .', ' .
            $strType . ', ' .
            $actorBankDetail->getBankAddressStreet() . "\n" .
            $actorBankDetail->getBankAddressCity() . ' ' .
            $actorBankDetail->getBankAddressZipCode() . "\n" .
            $actorBankDetail->getBankAddressCountry();
        } else {
            $addressBankStr = '';
        }

        $this->pdf->setXY(145, $y);
        $this->pdf->addText($addressBankStr, array('width'=>50, 'border'=>1));
        $this->pdf->ln();


        $x = $this->pdf->getX();
        $y = $this->pdf->getY();
        $text = 'montant en ' . $this->currency;
        $this->pdf->setXY(170, $y);
        $this->pdf->addText($text);
        $this->pdf->setX(170);
        $this->pdf->addText($this->bigTotals['toPay'], array('border'=>1, 'width'=>25));

        $this->pdf->setXY($x+5, $this->pdf->getY()+5);
        $headerColumns = array(
            _('Amount for control') . ' ' . $this->currency => 25,
            _('Creation date') => 25,
            _('Deadline') => 25,
            _('Reference') => 25);
        $dataColumns[0] = array(
            $this->bigTotals['toPay'],
            date('d/m/Y'),
            ' ',
            $this->destinator->getCode());

        $this->pdf->tableHeader($headerColumns, 1);
        $shapeY = $this->pdf->getY();
        $this->pdf->setX($this->pdf->getX()+5);
        $this->pdf->tableBody($dataColumns);
        $this->pdf->ln();
        $x = $this->pdf->getX();
        $y = $this->pdf->getY();

        // shape
        $this->pdf->setXY(120, $shapeY);
        $this->pdf->cell(6, 5, ' ', 'LBR');
        $this->pdf->setXY(131, $shapeY);
        $this->pdf->cell(6, 5, ' ', 'LBR');
        $this->pdf->setXY(142, $shapeY);
        $this->pdf->cell(23, 5, ' ', 'LBR', 1);

        $this->pdf->setXY($x+5, $y);
        $this->pdf->cell(5, 5, ' ', 'TLB');
        $this->pdf->setXY($x+60, $y);
        $this->pdf->cell(5, 5, ' ', 'TBR');
        $this->pdf->setXY($x+70, $y);
        $this->pdf->cell(5, 5, ' ', 'TBL');
        $this->pdf->setXY($x+120, $y);
        $this->pdf->cell(5, 5, ' ', 'TBR');
        $this->pdf->setXY($x+130, $y);
        $this->pdf->cell(5, 5, ' ', 'TBL');
        $this->pdf->setXY($x+150, $y);
        $this->pdf->cell(5, 5, ' ', 'TBR', 1);

        $this->pdf->ln(3);
        $x = $this->pdf->getX();
        $y = $this->pdf->getY();
        $this->pdf->setX($x+5);
        $headerColumns = array(
            _('Banking house') => 25,
            _('Branch number') => 20,
            _('Account') => 20,
            _('Key') => 10);
        $this->pdf->setX($x+5);
        $dataColumns[0] = array(
            ' ', ' ', ' ', ' ');
        $this->pdf->tableHeader($headerColumns, 1);
        $this->pdf->setX($x+5);
        $this->pdf->tableBody($dataColumns);

        $this->pdf->setXY(95, $y);
        $headerColumns = array(_('Name and address')=>45);
        $dataColumns[0] = array($this->pdf->rightAdress);
        $this->pdf->tableHeader($headerColumns, 1);
        $this->pdf->setX(95);
        $this->pdf->tableBody($dataColumns);

        $this->pdf->setXY(150, $y);
        $headerColumns = array(_('SIRET number')=>45);
        $dataColumns[0] = array($this->destinator->getSiret());
        $this->pdf->tableHeader($headerColumns, 1);
        $this->pdf->setX(150);
        $this->pdf->tableBody($dataColumns);
        $this->pdf->ln(20);

        $text = _('Value in: ');
        $x = $this->pdf->getX();
        $y = $this->pdf->getY();
        $this->pdf->addText($text);
        $this->pdf->Line($x+20, $y+4, $x+50, $y+4);
        $shapeY = $this->pdf->getY();
        $shapeX = $this->pdf->getX();

        $text = _('Acceptance or endorsement');
        $this->pdf->addText($text);

        $text = _('Registered address') . "\n" . _('Stamp duties and signature');
        $this->pdf->setXY(95, $y);
        $this->pdf->addText($text, array('border'=>1,
            'width'=>100,
            'align'=>'C',
            'lineHeight'=>20));

        $this->pdf->SetLineWidth(1);
        $this->pdf->Line($shapeX+40, $shapeY, $shapeX+40, $shapeY+4);
        $this->pdf->Line($shapeX+40, $shapeY+4, $shapeX+42, $shapeY+2);
        $this->pdf->Line($shapeX+40, $shapeY+4, $shapeX+38, $shapeY+2);
    }
} // }}}

/**
 * LogCardGenerator.
 * Classe utilis�e pour les fiches suiveuses.
 * Traduction du terme "Fiche suiveuse":
 * http://www.dassault-aviation.com/outils/traducteur_resultat.cfm?op=fr&id=F
 *
 */
class LogCardGenerator extends DocumentGenerator{ // {{{
    /**
     * Constructor
     *
     * @param Object $ack l'objet ActivatedChainTask
     * @access protected
     */
    public function __construct($command, $achId) {
        // doc fictif car on ne sauve pas ces listes suiveuses
        $document = new AbstractDocument();
        $this->command = $command;
        $docName = _('Log card') . ' (' . $command->getCommandNo() . ')';
        $cur = false; // pas important ici...
        parent::__construct($document, false, true, $cur, $docName);
        $this->pdf->showExpeditor = false;
        $this->ackData = array();

        $this->activatedChain = Object::load('ActivatedChain', $achId);
    }

    /**
     * Construit le doc pdf
     *
     * @access public
     * @return void
     */
    public function render() {
        $this->pdf->SetFillColor(220);
        $this->renderHeader();
        $this->pdf->addPage(); // apres le renderHeader()!
        $this->_renderCommandBloc();
        $this->_renderContent();
        //$this->_renderBarcodes();
        return $this->pdf;
    }

    /**
     *
     * @access public
     * @return void
     */
    public function renderHeader() {
        $this->pdf->docTitle = $this->docName;
        $this->pdf->fontSize['HEADER'] = 30;
        //$this->pdf->header();  // inutile: appele par addPage()
    }

    /**
     * affiche les infos sur la facture et le client et le commercial
     * @access private
     * @return void
     */
    private function _renderCommandBloc() {
        $this->pdf->setXY(10, 28);
        $columnsData = array();  // Les donnees a afficher
        $columns = array(
            _('Order number') => 48,
            _('Wished date') => 48,
            _('Reference(s)') => 47,
            _('Ordered quantity') => 47
        );
        $this->pdf->defaultFontSize['DEFAULT'] = 12;
        $this->pdf->tableHeader($columns, 1);
        $cmiCol = $this->command->getCommandItemCollection();
        $count  = $cmiCol->getCount();
        $columnsData = array();
        for($i = 0; $i < $count; $i++) {
            $cmi = $cmiCol->getItem($i);
            if ($i == 0) {
                $currentData = array(
                    0 => $this->command->getCommandNo(),
                    1 => I18N::formatDate($this->command->getWishedStartDate())
                );
            } else {
                $currentData = array(0 => '', 1 => '');
            }
            $currentData[2]  = Tools::getValueFromMacro($cmi, '%Product.BaseReference%');
            $currentData[3]  = $cmi->getQuantity();
            $columnsData[$i] = $currentData;
        }
        $this->pdf->tableBody($columnsData);
        $this->pdf->ln(5);
        $this->pdf->defaultFontSize['DEFAULT'] = 10;
    }

    /**
     * Tableau 'principal'
     * @access protected
     * @return void
     */
    protected function _renderContent() {
        //$ach = $this->command->getActivatedChain();
        $ach = $this->activatedChain;
        if (!($ach instanceof ActivatedChain)) {
            Template::errorDialog(_('Error: invalid order'), 'javascript:window.close();', BASE_POPUP_TEMPLATE);
            exit(1);
        }
        $consultingContext = in_array('consulting',
            Preferences::get('TradeContext', array()));
        require_once('ProductionTaskValidationTools.php');
        $filter = getValidationTaskFilter();
        $ackCol = $ach->getActivatedChainTaskCollection($filter);
        $count  = $ackCol->getCount();
        for($i = 0; $i < $count; $i++) {
            if($this->pdf->getY() > PAGE_HEIGHT_LIMIT_TO_LOGCARD_BARCODES) {
                $this->pdf->addPage();
                $this->pdf->setXY(10, 28);
            }
            $ack  = $ackCol->getItem($i);
            $ackID = $ack->getId();
            $tsk = $ack->getTask();
            $tskname = $tsk->getName() . ' ' . _('number') . ' ' . $ackID;
            $this->pdf->SetFillColor(220);
            $this->pdf->tableHeader(array($tskname=>190), 1);
            $this->pdf->SetFillColor(240);
            $this->pdf->tableHeader(
                array(
                    _('Parts number')  => 20,
                    _('Expected date') => 25,
                    _('Expected duration') => 23,
                    _('Effective duration') => 23,
                    _('Date and operator') => 33,
                    _('Observations') => 33,
                    _('Used material') => 33,
                ),
                1
            );
            $columnsData = array(
                0  => $ack->getRealQuantity(),
                1  => I18N::formatDate($ack->getBegin()),
                2  => I18N::formatDuration($ack->getDuration()),
                3  => '', // champs libre
                4  => '', // champs libre
                5  => '', // champs libre
                6  => '', // champs libre
            );
            $this->pdf->Row($columnsData, array(), array('lineHeight'=>8));
            // pas de codes barre pour les taches non validables ou si contexte 
            // consulting

            if ($consultingContext || !$tsk->getToBeValidated() || 
                $tsk->getId() == TASK_ASSEMBLY || $tsk->getId() == TASK_SUIVI_MATIERE) {
                $this->pdf->SetFillColor(220);
                $this->pdf->ln(4);
                continue;
            }
            $this->pdf->tableHeader(
                array(
                    _('Start') => 47,
                    _('Pause') => 47,
                    _('Restart') => 48,
                    _('Finish') => 48
                ),
                1
            );
            $lh = 22;
            $this->pdf->Row(array('', '', '', ''), array(), array('lineHeight'=>$lh));
            $this->pdf->SetFillColor(0);
            $y = $this->pdf->getY() - $lh + 1;
            $this->pdf->EAN13(12, $y, sprintf('10%010d', $ackID));
            $this->pdf->EAN13(59, $y, sprintf('11%010d', $ackID));
            $this->pdf->EAN13(107, $y, sprintf('12%010d', $ackID));
            $this->pdf->EAN13(155, $y, sprintf('13%010d', $ackID));
            $instructions = $ack->getInstructions();
            if (!empty($instructions)) {
                $this->pdf->tableHeader(array(_('Instructions: ') . $instructions => 190));
            }
            $this->pdf->SetFillColor(220);
            $this->pdf->ln(4);
        }
    }
} // }}}

/**
 * class ForwardingFormGenerator
 * G�n�re les bordereaux d'expedition
 */
class ForwardingFormGenerator extends DocumentGenerator { // {{{

    // ForwardingFormGenerator::__construct() {{{

    /**
     * ForwardingFormGenerator::ForwardingFormGenerator
     * @param Object $forwardingForm
     * @param boolean $reedit
     * @access public
     * @return void
     */
    public function __construct($forwardingForm, $reedit=false) {
        //Database::connection()->debug=true;
        parent::__construct($forwardingForm, $reedit, false);
        $this->supplierCustomer = $this->document->getSupplierCustomer();
        $this->expeditor = $this->supplierCustomer->getSupplier();
        $this->destinator = $this->supplierCustomer->getCustomer();
        $this->destinatorSite = $this->document->getDestinatorSite();
        $this->expeditorSite = $this->expeditor->getMainSite();
        $this->pdf->Expeditor = $this->expeditor;
        $this->pdf->ExpeditorSite = $this->expeditorSite;

    }

    // }}}
    // ForwardingFormGenerator::render() {{{

    /**
     * Construit la facture pdf
     *
     * @access public
     * @return Object PDFDocumentRender
     */
    public function render() {
        $this->pdf->SetFillColor(220);
        $this->pdf->defaultFontSize['DEFAULT'] = 10;
        $this->renderHeader();
        $this->pdf->addPage(); // apres le renderHeader()!
        $this->renderAddressesBloc();
        $this->renderCustomsBlocs();
        $this->_renderContent();
        return $this->pdf;
    }

    // }}}
    // ForwardingFormGenerator::renderAddressesBloc() {{{

    public function renderAddressesBloc() {
        // expediteur
        $eSite = $this->expeditorSite;
        $eAddressStr = $eSite->getName() . "\n"
        . $eSite->getFormatAddressInfos("\n");

        // destinataire
        $dSite = $this->destinatorSite;
        $dAddressStr = $dSite->getName() . "\n"
        . $dSite->getFormatAddressInfos("\n");

        $this->pdf->leftAdressCaption = _('Shipper') . ': ';
        $this->pdf->leftAdress = $eAddressStr;
        $this->pdf->rightAdressCaption = _('Addressee') . ': ';
        $this->pdf->rightAdress = $dAddressStr;

        $this->pdf->addHeader();
    }

    // }}}
    // ForwardingFormGenerator::_renderContent() {{{

    protected function _renderContent() {
        $data = array();
        $productIds = array();
        $FFP_products = array();
        $totalWeight = 0;
        $realWeight = 0;

        $transporter = $this->document->getTransporter();
        if($transporter instanceof Actor) {
            $this->pdf->addText(_('Carrier') . ' : ' . $transporter->getName());
        }

        $ffpCol = $this->document->getForwardingFormPackingCollection(
            array('CoverType'=>0));
        $SecondData = array();
        $count = $ffpCol->getCount();
        for($i=0 ; $i<$count ; $i++) {
            $ffp = $ffpCol->getItem($i);
            $product = $ffp->getProduct();
            if (!($product instanceof Product)) {
                continue;
            }
            $FFP_products[] = $ffp->getProductId();
            $SecondData[] = array($product->getBaseReference(), $ffp->getQuantity());
            $realWeight += $product->getSellUnitWeight() * $ffp->getQuantity();
        }

        $lemCollection = $this->document->getLocationExecutedMovementCollection();
        $count = $lemCollection->getCount();
        for ($i=0 ; $i<$count ; $i++) {
            $lem = $lemCollection->getItem($i);
            $product = $lem->getProduct();
            if(!in_array($product->getId(), $FFP_products)) {
                $productIds[$product->getId()] = $product->getBaseReference();
                $qty = $lem->getQuantity();
                $coeff=-1;
                if($lem->getCancelledMovementId()==0) {
                    $coeff=1;
                }
                if(isset($data[$product->getId()])) {
                    $data[$product->getId()]['qty'] += $coeff * $qty;
                } else {
                    $data[$product->getId()]['qty'] = $coeff * $qty;
                }
                $data[$product->getId()]['description'] =
                    $this->renderDescriptionOfGoodsField($product);
                $data[$product->getId()]['unitWeight'] = $product->getSellUnitWeight();
            }
        }

        $formatedData = array();
        foreach ($data as $key=>$value) {
            $formatedData[] = array($productIds[$key],
                $value['description'], $value['qty']);
            $totalWeight += $value['qty'] * $value['unitWeight'];
        }
        $totalWeight = ceil($totalWeight);
        $realWeight = ceil($realWeight) + $totalWeight;

        $header = array(
            _('Reference')  => 60,
            _('Description of goods') => 80,
            _('Quantity') => 50);
        $this->pdf->tableHeader($header, 1);
        $this->pdf->TableBody($formatedData, $header);

        $this->pdf->ln();

        $ffpCol = $this->document->getForwardingFormPackingCollection(array('Product'=>0));
        $data = array();
        $count = $ffpCol->getCount();
        for($i=0 ; $i<$count ; $i++) {
            $ffp = $ffpCol->getItem($i);
            $coverType = $ffp->getCoverType();
            if ($coverType instanceof CoverType) {
                $data[] = array($coverType->getName(), $ffp->getQuantity());
            }
        }
        if(count($data)>0) {
            $header = array(_('Parcel type') => 100,
                        _('Quantity') => 90);
            $this->pdf->tableHeader($header, 1);
            $this->pdf->TableBody($data, $header);
            $this->pdf->ln();
        }

        if(count($SecondData)>0) {
            $header = array(_('Deposited packings') => 100,
                            _('Quantity') => 90);
            $this->pdf->tableHeader($header, 1);
            $this->pdf->TableBody($SecondData, $header);
        }

        // affichage du poid total des colis et emballages
        $this->pdf->ln();
        $displayTotalWeight = $this->documentModel instanceof DocumentModel?
            $this->documentModel->getDisplayTotalWeight():true;
        if($displayTotalWeight) {
            $this->pdf->tableHeader(
                array(_('Net total weight (Kg)') . ': ' .
                $totalWeight => 190));
            $this->pdf->tableHeader(
                array(_('Raw total weight (Kg)') . ': ' .
                $realWeight => 190));
        }
    }

    // }}}
    // ForwardingFormGenerator::renderCustomsBlocs() {{{

    /**
     * On surcharge la m�thode DocumentGenerator::renderCustomsBlocs
     * pour afficher les infos de toutes les commandes. Il y a une ligne
     * de donn�e par commande. Le contenu des cellules list� dans $unique
     * n'apparait que sur la premi�re ligne.
     *
     * @access public
     * @return void
     */
    public function renderCustomsBlocs() {
        require_once ('Objects/DocumentModelProperty.inc.php');
        $unique = array(DocumentModelProperty::CELL_NO_DOC);
        $dom = $this->document->findDocumentModel();
        if($dom instanceof DocumentModel) {
            $FFP_products = array();
            $ffpCol = $this->document->getForwardingFormPackingCollection(
                array('CoverType'=>0));
            $count = $ffpCol->getCount();
            for($i=0 ; $i<$count ; $i++) {
                $ffp = $ffpCol->getItem($i);
                $FFP_products[] = $ffp->getProductId();
            }
        // LEM.EXM.ACM.ProductCommand
            $CommandCollection = new Collection();
            $CommandCollection->acceptDuplicate=false;
            $lemCol = $this->document->getLocationexecutedMovementCollection();

            $lemCount = $lemCol->getCount();
            for($i=0 ; $i<$lemCount ; $i++) {
                $lem = $lemCol->getItem($i);
                if(!in_array($lem->getProductId(), $FFP_products)) {
                    $cmdId = Tools::getValueFromMacro($lem, '%ExecutedMovement.ActivatedMovement.ProductCommand.Id%');
                    $productCommand = Object::load('Command',$cmdId);
                    if($cmdId==0) {
                        $productCommand->setCommandNo($this->document->getCommandNo());
                    }
                    $CommandCollection->setItem($productCommand);
                    unset($productCommand);
                }
            }
            $commandCount = $CommandCollection->getCount();

            $domPropCol = $dom->getDocumentModelPropertyCollection(array('Property'=>0));
            $numberOfProperties = $domPropCol->getCount();
            $numberOfTable = ceil($numberOfProperties / NUMBER_OF_CELLS_PER_TABLE);

            $domMapper = Mapper::singleton('DocumentModelProperty');
            // pour chaque tableau :
            for ($i=1 ; $i<=$numberOfTable ; $i++) {
                // r�cup�rer les 5 documentModelProperty de la table dans l'ordre
                $domPropCol = $domMapper->loadCollection(
                    array('Property' => 0,
                      'DocumentModel' => $dom->getId()),
                    array('Order' => SORT_ASC),
                    array('PropertyType'), NUMBER_OF_CELLS_PER_TABLE, $i);

                $headerColumns = array();
                $dataColumns = array();
                $cells = $domPropCol->getCount();
                $cellsWidth = PAGE_WIDTH / $cells;
                for ($j=0 ; $j<$cells ; $j++) {
                    $property = $domPropCol->getItem($j);
                    // cr�ation du header
                    $headerColumns[getDocumentModelPropertyCellLabel(
                    $property->getPropertyType())] = $cellsWidth;
                    // cr�ation du contenu
                    for($k=0 ; $k<$commandCount ; $k++) {
                        if ($k>0 && in_array($property->getPropertyType(), $unique)) {
                            $dataColumns[$k][] = '';
                            continue;
                        }
                        $cmd = $CommandCollection->getItem($k);
                        $dataColumns[$k][] = getDocumentModelPropertyCellValue(
                        $property->getPropertyType(), $this, $cmd);
                    }
                }
                $this->pdf->tableHeader($headerColumns, 1);
                $this->pdf->tableBody($dataColumns);
                $this->pdf->ln(3);
                unset($headerColumns, $dataColumns);
            }
        }
    }

    // }}}
} // }}}

/**
 * classe de g�n�ration des r�c�piss�s de commandes.
 *
 */
class CommandReceipt extends DocumentGenerator { // {{{

    // CommandReceipt::__construct() {{{

    /**
     * Constructeur.
     *
     * @param object $command commande
     * @return void
     */
    public function __construct($command) {
        $document = new AbstractDocument();
        $document->setDocumentNo($command->getCommandNo());
        $this->expeditor = $command->getExpeditor();
        $this->expeditorSite = $command->getExpeditorSite();
        $this->destinator = $command->getDestinator();
        $this->destinatorSite = $command->getDestinatorSite();

        parent::__construct($document, false, false,
            $command->getCurrency(), _('Order receipt'));
        $this->pdf->showExpeditor = false;
        $this->command = $command;
    }

    // }}}
    // CommandReceipt::render() {{{

    /**
     * construit le r�c�piss�
     *
     * @return Object PdfDocumentRender
     */
    public function render() {
        $this->pdf->setFillColor(220);
        $this->renderHeader();
        $this->pdf->addPage(); // apres le renderHeader()!
        $this->renderAddressesBloc();
        $this->pdf->addHeader();
        $this->renderContent();
        $this->renderTotalBlock();
        return $this->pdf;
    }

    // }}}
    // CommandReceipt::renderAddressesBloc() {{{

    /**
     * Construit les blocs d'addresses du r�c�piss�.
     * A gauche l'expediteur de la commande, � droite le destinataire.
     *
     * @return void
     */
    public function renderAddressesBloc() {
        $this->pdf->leftAdress = $this->destinator->getQualityForAddress()
        . $this->destinator->getName() . "\n"
        . $this->destinatorSite->getFormatAddressInfos("\n");
        $this->pdf->rightAdress = $this->expeditor->getQualityForAddress()
        . $this->expeditor->getName() . "\n"
        . $this->expeditorSite->getFormatAddressInfos("\n");

        $this->pdf->leftAdressCaption = _('From (address)') . ': ';
        $this->pdf->rightAdressCaption = _('To') . ': ';
    }

    // }}}
    // CommandReceipt::renderContent() {{{

    /**
     * G�n�re le contenu du doc :
     * date souhait�, incoterm et tableau des infos de la commande.
     *
     * @return void
     */
    public function renderContent() {
        $wishedDate = I18N::formatDate($this->command->getWishedStartDate());

        if ($this->command->getWishedEndDate() != 0 &&
            $this->command->getWishedEndDate() != 'NULL') {
            $date_souhaitee = sprintf(_('between %s and %s'),
                $date_souhaitee,
                I18N::formatDate($this->command->getWishedEndDate()));
        }
        $this->pdf->addText(_('Wished date') . ' : ' .
            $wishedDate);
        $incoterm = $this->command->getIncoterm();
        if ($incoterm instanceof Incoterm) {
        $this->pdf->addText(_('Incoterm') . ' : ' .
            $incoterm->toString());
        }

        $header = array(_('Reference') => 30,
                        _('Name')       => 30,
                        _('Quantity')  => 20,
                        _('Basis excl. VAT') . ' ' . $this->currency    => 20,
                        _('Discount')    => 30,
                        _('Amount excl. VAT') . ' ' . $this->currency   => 30,
                        _('Amount incl. VAT') . ' ' . $this->currency  => 30);
        $data = array();
        $commandType = $this->command->getType();
        $supplierCustomer = $this->command->getSupplierCustomer();
        $supplier = $supplierCustomer->getSupplier();

        $commandItemCol = $this->command->getCommandItemCollection();
        $count = $commandItemCol->getcount();
        for($i=0 ; $i<$count ; $i++) {
            $commandItem = $commandItemCol->getItem($i);
            $product = $commandItem->getProduct();
            $productRef = $commandType==Command::TYPE_CUSTOMER?
                $product->getBaseReference():
                $product->getReferenceByActor($supplier);
            $unitQty = $commandType==Command::TYPE_CUSTOMER?
                $product->getSellUnitQuantity():
                $product->getBuyUnitQuantity($supplier);
            /* XXX Comment� par david cf bug 0002626
            $promotion = $commandItem->getPromotion();
            if($promotion instanceof Promotion) {
                $promoRate = DocumentGenerator::formatNumber($promotion->getRate());
            	if($promotion->getType()==Promotion::PROMO_TYPE_PERCENT) {
            	     $symbol = '%';
            	} elseif ($promotion->getType()==Promotion::PROMO_TYPE_MONTANT) {
            	    $currency = $promotion->getCurrency();
            	    $symbol = $currency instanceof Currency?$currency->getSymbol():'';
            	}
            } else {
                $promoRate = $symbol = '';
            }
            */
            // Pas d'affichage des separateurs des milliers ici
            $data [] = array(
                $productRef,
                $product->getName(),
                $commandItem->getQuantity() . ' (' . _('by') . ' ' .
                    $unitQty . ')',
                $commandItem->getPriceHT(),
                /* XXX Comment� par david cf bug 0002626
                $promoRate . ' ' . $symbol,*/
                $commandItem->getHanding(),
                $commandItem->getTotalHT(true),
                $commandItem->getTotalTTC(true));
        }
        $this->pdf->tableHeader($header, 1);
        $this->pdf->tableBody($data, $header);
        $this->pdf->Ln(8);
    }

    // }}}
    // CommandReceipt::renderTotalBlock() {{{

    /**
     * g�n�re le tableau avec le total, affiche l'accompte et
     * le montant � r�gler.
     *
     * @return void
     */
    public function renderTotalBlock() {
        $header = array(_('Delivery expenses') . ' ' . $this->currency =>30,
                        _('Packing') . ' ' . $this->currency =>30,
                        _('Insurance') . ' ' . $this->currency =>30,
                        _('Global discount') => 30,
                        _('Amount excl. VAT') . ' ' . $this->currency =>35,
                        _('Amount incl. VAT'). ' ' . $this->currency =>35);
        $ttcTotalPrice = $this->command->getTotalPriceTTC();
        $data = array(array(
            $this->command->getPort(),
            $this->command->getPacking(),
            $this->command->getInsurance(),
            $this->command->getHanding(),
            DocumentGenerator::formatNumber($this->command->getTotalPriceHT()),
            DocumentGenerator::formatNumber($ttcTotalPrice)));

        $this->pdf->tableHeader($header, 1);
        $this->pdf->tableBody($data, $header);

        $installment = DocumentGenerator::formatNumber($this->command->getInstallment());
        if ($ttcTotalPrice < $installment) {
            $toPay = 0;
        } else {
            $toPay = $ttcTotalPrice - $installment;
        }
        $this->pdf->setX(150);
        $this->pdf->tableHeader(array(
            _('Instalment') . ' ' . $this->currency . ' : ' . $installment=>50));
        $this->pdf->setX(150);
        $this->pdf->tableHeader(array(
            _('To pay') . ' ' . $this->currency .' : ' . $toPay=>50));
    }

    // }}}
} // }}}

/**
 * classe de g�n�ration des r�c�piss�s de commandes de transport.
 *
 */
class ChainCommandReceipt extends CommandReceipt { // {{{

    // ChainCommandReceipt::__construct() {{{

    /**
     * Constructeur.
     *
     * @param object $command ChainCommand
     */
    public function __construct($command) {
        parent::__construct($command);
        $this->commandNoLabel = _('Order number');
    }

    // }}}
    // ChainCommandReceipt::renderAddressesBloc() {{{

    /**
     * Construit les blocs d'addresses du r�c�piss�.
     * A gauche l'expediteur de la commande, � droite le destinataire.
     *
     * @return void
     */
    public function renderAddressesBloc() {
        parent::renderAddressesBloc();
        $this->pdf->leftAdressCaption = _('Collection address') . ': ';
        $this->pdf->rightAdressCaption = _('Delivery address') . ': ';
    }

    // }}}
    // ChainCommandReceipt::renderContent() {{{

    /**
     * G�n�re le contenu du doc :
     * date souhait�, incoterm et tableau des infos de la commande.
     *
     * @return void
     */
    public function renderContent() {
        require_once('Objects/CommandItem.inc.php');
        require_once('FormatNumber.php');
        // informations :
        $this->pdf->addText($this->commandNoLabel . ' : ' .
            $this->command->getCommandNo());
        // date souhait�e
        $cmdDate = I18N::formatDate($this->command->getCommandDate());
        $startDate = I18N::formatDate($this->command->getWishedStartDate());
        $endDate = $this->command->getWishedEndDate();
        $endDate = $endDate==0?false:I18N::formatDate($endDate);
        $wishedDate = $endDate?
            sprintf(_("between %s and %s"), $startDate, $endDate):
            $startDate;
        if($this->command->getDateType()==DATE_TYPE_DELIVERY) {
            $this->pdf->addText(_('Wished collection date') . ' : ' .
                $wishedDate);
        } else {
            $this->pdf->addText(_('Wished delivery date') . ' : ' .
                $wishedDate);
        }
        // Incoterm
        $incoterm = $this->command->getIncoterm();
        $this->pdf->addText(_('Incoterm') . ' : ' . $incoterm->toString());
        // N� d'imputation
        $this->pdf->addText(_('Imputation number or account number') .
            ' : ' . $this->command->getInputationNo());
        // montant � r�cup�rer � la livraison
        $this->pdf->addText(_('Amount to recover on delivery') .
            ' : ' . $this->command->getDeliveryPayment());

        $this->pdf->ln();
        $header = array(
            _('Parcel type')            => 35,
            _('Quantity')                 => 30,
            _('Unit weight') .' (Kg.)' => 30,
            _('Dimensions') . ' (m)'      => 30,
            _('Stackable ratio')              => 30,
            _('Priority dimension')    => 35);
        // items
        $data = array();
        $cmiCollection = $this->command->getCommandItemCollection();
        $count = $cmiCollection->getCount();
        for($i = 0; $i < $count; $i++){
        	$cmi = $cmiCollection->getItem($i);
            $type = $cmi->getCoverType();
            $data[] = array(
                $type instanceof CoverType?$type->toString():'',
                $cmi->getQuantity(),
                $cmi->getWeight(),
                sprintf('%sx%sx%s', $cmi->getWidth(), $cmi->getLength(),
                    $cmi->getHeight()),
                $cmi->getGerbability(),
                getMasterDimensionLabel($cmi->getMasterDimension()));
        }

        $this->pdf->tableHeader($header, 1);
        $this->pdf->tableBody($data, $header);
        $this->pdf->Ln(8);
    }

    // }}}
    // ChainCommandReceipt::renderTotalBlock() {{{

    /**
     * g�n�re le tableau avec le total, affiche l'accompte et
     * le montant � r�gler.
     *
     * @return void
     */
    public function renderTotalBlock() {
        $header = array(
            _('Packing') . ' ' . $this->currency => 30,
            _('Insurance') . ' ' . $this->currency => 30,
            _('VAT') . ' ' . $this->currency => 31,
            _('Amount incl. VAT') . ' ' . $this->currency => 33,
            _('Instalment') . ' ' . $this->currency => 33,
            _('To pay') . ' ' . $this->currency => 33);
        $installment = $this->command->getInstallment();
        $toPay = DocumentGenerator::formatNumber($this->command->getTotalPriceTTC()-$installment);
        $data = array(array(
            $this->command->getPacking(),
            $this->command->getInsurance(),
            DocumentGenerator::formatNumber($this->command->getTotalPriceTTC() -
                $this->command->getTotalPriceHT()),
            DocumentGenerator::formatNumber($this->command->getTotalPriceTTC()),
            $installment?DocumentGenerator::formatNumber($installment):'0',
            $toPay));

        $this->pdf->tableHeader($header, 1);
        $this->pdf->tableBody($data, $header);

        $comment = $this->command->getComment();
        if(!empty($comment)) {
            $this->pdf->tableHeader(array(_('Comment') . ' : ' .
                $comment => 190 ), 1);
        }
    }

    // }}}
} // }}}

/**
 * classe de g�n�ration des devis pour les commandes produits
 *
 */
class ProductCommandEstimateReceipt extends CommandReceipt { // {{{
    // ProductCommandEstimateReceipt::__construct() {{{

    /**
     * Constructeur.
     *
     * @param object $command commande
     * @return void
     */
    public function __construct($command) {
        parent::__construct($command);
        $this->docName = _('Estimate receipt');
    }

    // }}}
} // }}}

/**
 * classe de g�n�ration des devis pour les commandes de transport
 *
 */
class ChainCommandEstimateReceipt extends ChainCommandReceipt { // {{{
    // ChainCommandEstimateReceipt::__construct() {{{

    /**
     * Constructeur.
     *
     * @param object $command commande
     * @return void
     */
    public function __construct($command) {
        parent::__construct($command);
        $this->docName = _('Estimate receipt');
        $this->commandNoLabel = _('Estimate number');
    }

    // }}}
} // }}}

/**
 * InvoiceCollectionGenerator.
 * Classe utilis�e pour imprimer une s�rie de factures dans le meme pdf, avec
 * gestion correcte de la pagination par facture.
 *
 */
class InvoiceCollectionGenerator extends CommandDocumentGenerator { // {{{
    /**
     * La collection de factures a imprimer dans le meme pdf
     * @var string
     */
    public $invoiceColl = false;

    /**
     * Constructor
     *
     * @param Object $document Collection of Invoice
     * @param boolean $isReedition mettre � true s'il s'agit d'une r��dition
     * @param boolean $autoPrint true pour impression auto
     * @access protected
     */
    public function __construct($invoiceColl) { // {{{
        $this->invoiceColl = $invoiceColl;
        $this->pdf = new PDFDocumentRender(false, false);
    }
    // }}}

    /**
     * Construit la facture pdf
     *
     * @access public
     * @return PDFDocumentRender Object
     */
    public function render() { // {{{
        $documentColl = $this->invoiceColl;
        $count = $documentColl->getCount();
        for($i = 0; $i < $count; $i++) {
            $this->pdf->StartPageGroup();  // pour les pageGroup
            $invoice = $documentColl->getItem($i);
            $generator = new InvoiceGenerator($invoice);
            // Les 4 lignes suivantes pour la construction de header
            $this->document = $invoice;
            $this->command = $invoice->getCommand();
            $this->expeditor = $this->command->getExpeditor();
            $this->expeditorSite = $this->command->getExpeditorSite();
            $this->destinator = $this->command->getDestinator();
            $this->destinatorSite = $this->command->getDestinatorSite();
            $this->supplierCustomer = $this->command->getSupplierCustomer();
            $this->incoterm = $this->command->getIncoterm();
            $this->pdf->Command = $generator->command;
            $this->pdf->Expeditor = $generator->expeditor;
            $this->pdf->ExpeditorSite = $generator->expeditorSite;

            // On passe ici le $generator en param au render() pour ne pas agir
            // sur $generator->pdf, mais $this->pdf
            $generator->render($this);  // $this->pdf
        }
        return $this->pdf;
    }
    // }}}

} // }}}

/**
 * WorksheetGenerator.
 * Classe utilis�e pour les fiches techniques.
 *
 */
class WorksheetGenerator extends DocumentGenerator{ // {{{
    /**
     * Constructor
     *
     * @param  object $model l'objet RTWModel
     * @access protected
     */
    public function __construct($model) {
        // doc fictif car on ne sauve pas ces fiches suiveuses
        $document = new AbstractDocument();
        $cur = false; // pas important ici...
        parent::__construct($document, false, false, $cur, '');
        $this->pdf->showExpeditor   = false;
        $this->pdf->showPageNumbers = false;
        $this->model = $model;
    }

    /**
     * Construit le doc pdf
     *
     * @access public
     * @return void
     */
    public function render() {
        $this->pdf->SetFillColor(220);
        $this->renderHeader();
        $this->pdf->addPage(); // apres le renderHeader()!
        // XXX TODO
        $infos = ImageManager::getFileInfo(md5($this->model->getImage()));
        if (false !== $infos) {
            list(,$type) = explode('/', $infos['mimetype']);
		    $this->pdf->image($infos['data'], 70, 8, 130, 0, $type);
        }
        $this->_renderContent();
        return $this->pdf;
    }

    /**
     *
     * @access public
     * @return void
     */
    public function renderHeader() {
        $this->pdf->docTitle = $this->docName;
        $this->pdf->fontSize['HEADER'] = 30;
        $dbOwner = Auth::getDatabaseOwner();
        $this->pdf->logo = base64_decode($dbOwner->getLogo());
        //$this->pdf->header();  // inutile: appele par addPage()
    }

    /**
     * Tableau 'principal'
     * @access protected
     * @return void
     */
    protected function _renderContent() {
        $this->pdf->Ln();
        $this->pdf->Ln();
        $this->pdf->addText(
            _('Worksheet') . ' ' . $this->model->toString(),
            array('fontSize'=>14, 'lineHeight'=>8)
        );
        $this->pdf->Ln();
        $this->pdf->Ln();
        $this->pdf->addText(
            _('Date') . ': ' . I18N::formatDate(time(), I18N::DATE_LONG),
            array('fontSize'=>12, 'lineHeight'=>5)
        );
        if ($this->model->getSeason() instanceof RTWSeason) {
            $this->pdf->addText(
                _('Season') . ': ' . $this->model->getSeason()->toString(),
                array('fontSize'=>12, 'lineHeight'=>5)
            );
        }
        if ($this->model->getManufacturer() instanceof Actor) {
            $this->pdf->addText(
                _('Manufacturer') . ': ' . $this->model->getManufacturer()->toString(),
                array('fontSize'=>12, 'lineHeight'=>5)
            );
        }
        $this->pdf->addText(
            _('Style number') . ': ' . $this->model->getStyleNumber(),
            array('fontSize'=>12, 'lineHeight'=>5)
        );
        $this->pdf->addText(
            _('Description') . ': ' . $this->model->getDescription(),
            array('fontSize'=>12, 'lineHeight'=>5)
        );
        $this->pdf->Ln();
        $this->pdf->Ln();
        $this->pdf->Ln();
        $this->pdf->Ln();
        $this->pdf->Ln();
        $this->pdf->Ln();
        $this->pdf->Ln();
        $items = array(
            'ConstructionType' => _('Construction type'),
            'ConstructionCode' => _('Construction code'),
            'Shape'            => _('Shape'),
            'Label'            => _('Label (griffe)')
        );
        foreach ($items as $k => $v) {
            $getter = 'get' . $k;
            if (is_object($this->model->$getter()) && !($this->model->$getter() instanceof Exception)) {
                $this->pdf->tableHeader(array(
                    $v => 35, 
                    $this->model->$getter()->toString() => 155),
                0);
            }
        }
        $items = $this->model->getMaterialProperties();
        foreach ($items as $attrName => $label) {
            $getter = 'get' . $attrName;
            $mat    = $this->model->$getter();
            $value  = ($mat instanceof RTWMaterial) ? $mat->toString() : 'N/A';
            $this->pdf->tableHeader(array($label => 35, $value => 155), 0);
        }
        $sizes = $this->model->getSizeCollection();
        if (count($sizes) > 0) {
            $this->pdf->tableHeader(array(
                _('Available sizes') => 35,
                implode(', ', array_values($sizes->toArray())) => 155), 
            0);
        }
        $this->pdf->tableHeader(array(
            _('Observations') => 35,
            $this->model->getComment() => 155), 
        0);
        $this->pdf->Ln();
    }
} // }}}

?>
