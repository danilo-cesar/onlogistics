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

class CustomerSituationAddEdit extends GenericAddEdit { 
    private $_notDeletedEntity = array();

    // CustomerSituationAddEdit::__construct() {{{

    /**
     * __construct 
     * 
     * @param array $params 
     * @access public
     * @return void
     */
    public function __construct($params=array()) {
        $params['profiles'] = array(UserAccount::PROFILE_ADMIN, UserAccount::PROFILE_ADMIN_WITHOUT_CASHFLOW);
        parent::__construct($params); 
    }

    // }}}
    // CustomerSituationAddEdit::onBeforeDelete() {{{
    
    /**
     * onBeforeDelete 
     * 
     * @access protected
     * @return void
     */
    protected function onBeforeDelete() {
        $objectMapper = Mapper::singleton('CustomerSituation');
        $objectCol = $objectMapper->loadCollection(
			array('Id' => $this->objID));

        $okForDelete = array();
        $count = $objectCol->getCount();
        for($i=0 ; $i<$count ; $i++){
            $object = $objectCol->getItem($i);
            // la suppression n'est possible que si la situation n'est li�e � 
            // aucun acteur.
            if ($object instanceof CustomerSituation) { 
                if (count($object->getCustomerPropertiesCollectionIds()) == 0) {
                    //on peut supprimer
                    $okForDelete[] = $object->getId();
	            } else {
	                //ajout dans le tableau des non suprim�es
                    $this->_notDeletedEntity[] = $object->getName();
                }
            }
        }
        $this->objID = $okForDelete;
    }

    // }}}
    // CustomerSituationAddEdit::onAfterDelete() {{{

    /**
     * onAfterDelete 
     * 
     * @access protected
     * @return void
     */
    protected function onAfterDelete() {
        // redirige vers un message d'info
        $msg = false;
        if (count($this->_notDeletedEntity) > 0) {
            $msg = _('The following situations cannot be deleted because they are link to one or more customers') 
                . sprintf(':<ul><li>%s</li></ul>', implode('</li><li>', $this->_notDeletedEntity));
        }

        if($msg) {
            Template::infoDialog($msg, $this->guessReturnURL());
            exit();
        }
    }
    
    // }}}
}

?>
