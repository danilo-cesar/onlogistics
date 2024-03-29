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

class LinkQuestionAnswerModel extends Object {
    
    // Constructeur {{{

    /**
     * LinkQuestionAnswerModel::__construct()
     * Constructeur
     *
     * @access public
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    // }}}
    // AnswerOrder int property + getter/setter {{{

    /**
     * AnswerOrder int property
     *
     * @access private
     * @var integer
     */
    private $_AnswerOrder = null;

    /**
     * LinkQuestionAnswerModel::getAnswerOrder
     *
     * @access public
     * @return integer
     */
    public function getAnswerOrder() {
        return $this->_AnswerOrder;
    }

    /**
     * LinkQuestionAnswerModel::setAnswerOrder
     *
     * @access public
     * @param integer $value
     * @return void
     */
    public function setAnswerOrder($value) {
        $this->_AnswerOrder = ($value===null || $value === '')?null:(int)$value;
    }

    // }}}
    // AnswerModel foreignkey property + getter/setter {{{

    /**
     * AnswerModel foreignkey
     *
     * @access private
     * @var mixed object AnswerModel or integer
     */
    private $_AnswerModel = false;

    /**
     * LinkQuestionAnswerModel::getAnswerModel
     *
     * @access public
     * @return object AnswerModel
     */
    public function getAnswerModel() {
        if (is_int($this->_AnswerModel) && $this->_AnswerModel > 0) {
            $mapper = Mapper::singleton('AnswerModel');
            $this->_AnswerModel = $mapper->load(
                array('Id'=>$this->_AnswerModel));
        }
        return $this->_AnswerModel;
    }

    /**
     * LinkQuestionAnswerModel::getAnswerModelId
     *
     * @access public
     * @return integer
     */
    public function getAnswerModelId() {
        if ($this->_AnswerModel instanceof AnswerModel) {
            return $this->_AnswerModel->getId();
        }
        return (int)$this->_AnswerModel;
    }

    /**
     * LinkQuestionAnswerModel::setAnswerModel
     *
     * @access public
     * @param object AnswerModel $value
     * @return void
     */
    public function setAnswerModel($value) {
        if (is_numeric($value)) {
            $this->_AnswerModel = (int)$value;
        } else {
            $this->_AnswerModel = $value;
        }
    }

    // }}}
    // Question foreignkey property + getter/setter {{{

    /**
     * Question foreignkey
     *
     * @access private
     * @var mixed object Question or integer
     */
    private $_Question = false;

    /**
     * LinkQuestionAnswerModel::getQuestion
     *
     * @access public
     * @return object Question
     */
    public function getQuestion() {
        if (is_int($this->_Question) && $this->_Question > 0) {
            $mapper = Mapper::singleton('Question');
            $this->_Question = $mapper->load(
                array('Id'=>$this->_Question));
        }
        return $this->_Question;
    }

    /**
     * LinkQuestionAnswerModel::getQuestionId
     *
     * @access public
     * @return integer
     */
    public function getQuestionId() {
        if ($this->_Question instanceof Question) {
            return $this->_Question->getId();
        }
        return (int)$this->_Question;
    }

    /**
     * LinkQuestionAnswerModel::setQuestion
     *
     * @access public
     * @param object Question $value
     * @return void
     */
    public function setQuestion($value) {
        if (is_numeric($value)) {
            $this->_Question = (int)$value;
        } else {
            $this->_Question = $value;
        }
    }

    // }}}
    // getTableName() {{{

    /**
     * Retourne le nom de la table sql correspondante
     *
     * @static
     * @access public
     * @return string
     */
    public static function getTableName() {
        return 'LinkQuestionAnswerModel';
    }

    // }}}
    // getObjectLabel() {{{

    /**
     * Retourne le "label" de la classe.
     *
     * @static
     * @access public
     * @return string
     */
    public static function getObjectLabel() {
        return _('None');
    }

    // }}}
    // getProperties() {{{

    /**
     * Retourne le tableau des propri�t�s.
     * Voir Object pour documentation.
     *
     * @static
     * @access public
     * @return array
     * @see Object.php
     */
    public static function getProperties() {
        $return = array(
            'AnswerOrder' => Object::TYPE_INT,
            'AnswerModel' => 'AnswerModel',
            'Question' => 'Question');
        return $return;
    }

    // }}}
    // getLinks() {{{

    /**
     * Retourne le tableau des entit�s li�es.
     * Voir Object pour documentation.
     *
     * @static
     * @access public
     * @return array
     * @see Object.php
     */
    public static function getLinks() {
        $return = array();
        return $return;
    }

    // }}}
    // getUniqueProperties() {{{

    /**
     * Retourne le tableau des propri�t�s qui ne peuvent prendre la m�me valeur
     * pour 2 occurrences.
     *
     * @static
     * @access public
     * @return array
     */
    public static function getUniqueProperties() {
        $return = array();
        return $return;
    }

    // }}}
    // getEmptyForDeleteProperties() {{{

    /**
     * Retourne le tableau des propri�t�s doivent �tre "vides" (0 ou '') pour
     * qu'une occurrence puisse �tre supprim�e en base de donn�es.
     *
     * @static
     * @access public
     * @return array
     */
    public static function getEmptyForDeleteProperties() {
        $return = array();
        return $return;
    }

    // }}}
    // getFeatures() {{{

    /**
     * Retourne le tableau des "fonctionalit�s" pour l'objet en cours.
     * Voir Object pour documentation.
     *
     * @static
     * @access public
     * @return array
     * @see Object.php
     */
    public static function getFeatures() {
        return array();
    }

    // }}}
    // getMapping() {{{

    /**
     * Retourne le mapping n�cessaires aux composants g�n�riques.
     * Voir Object pour documentation.
     *
     * @static
     * @access public
     * @return array
     * @see Object.php
     */
    public static function getMapping() {
        $return = array();
        return $return;
    }

    // }}}
}

?>