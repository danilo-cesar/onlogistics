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
 * @version   SVN: $Id: RTWModelGrid.php 71 2008-07-07 09:03:06Z izimobil $
 * @link      http://www.onlogistics.org
 * @link      http://onlogistics.googlecode.com
 * @since     File available since release 0.1.0
 * @filesource
 */


class RTWModelForSupplierGrid extends GenericGrid
{
    // Constructeur {{{

    /**
     * Constructeur
     *
     * @param string $entity nom de l'objet
     * @param array $params tableau de paramètres
     * @return void
     */
    public function __construct($params=array()) {
        $params['profiles'] = array(
            UserAccount::PROFILE_ADMIN,
            UserAccount::PROFILE_ADMIN_WITHOUT_CASHFLOW,
            UserAccount::PROFILE_RTW_SUPPLIER,
        );
        parent::__construct($params);
    }
    
    // }}} 
    //  RTWModelForSupplierGrid::getMapping() {{{

    /**
     *
     * @access public
     * @return void
     */
    public function getMapping() {
        return array(
            'StyleNumber'=>array(
                'label'        => _('Style number'),
                'shortlabel'   => _('Style number'),
                'usedby'       => array('grid', 'searchform'),
                'required'     => true,
                'inplace_edit' => false,
                'add_button'   => false,
                'section'      => ''
            ),
            'Season'=>array(
                'label'        => _('Season'),
                'shortlabel'   => _('Season'),
                'usedby'       => array('grid', 'searchform'),
                'required'     => true,
                'inplace_edit' => false,
                'add_button'   => false,
                'section'      => ''
            ),
        );
    }
    
    // }}} 
    // RTWModelForSupplierGrid::getFeatures() {{{

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
    // RTWModelForSupplierGrid::getGridFilter() {{{

    /**
     *
     * @access public
     * @return array
     */
    public function getGridFilter() {
        if ($this->auth->getProfile() == UserAccount::PROFILE_RTW_SUPPLIER) {
            // on n'affiche que les fiches techniques dont les produits 
            // déclinés ont comme fournisseur l'acteur de l'utilisateur
            return SearchTools::newFilterComponent(
                'Owner',
                'RTWProduct().ActorProduct().Actor',
                'Equals',
                $this->auth->getActorId(),
                1,
                'RTWModel'
            );
        }
        // sinon pas de filtre particulier
        return array();
    }

    // }}}
    // RTWModelForSupplierGrid::additionalGridActions() {{{

    /**
     * Ajoute l'action imprimer fiche technique.
     *
     * @access protected
     * @return array
     */
    protected function additionalGridActions() {
        $this->grid->NewAction('Redirect', array(
            'Caption' => _('Print worksheet'),
            'TargetPopup' => true,
            'URL' => 'WorksheetEdit.php',
            'TransmitedArrayName' => 'modelIds'
        ));
    }

    // }}}
}

?>
