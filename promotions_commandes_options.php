<?php
/**
 * Options au chargement du plugin Promotions commandes
 *
 * @plugin     Promotions commandes
 * @copyright  2018 - 2021
 * @author     Rainer Müller
 * @licence    GNU/GPL
 * @package    SPIP\Promotions_commandes\Options
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}


// Ajoute le plugin commandes aux promotions.
$GLOBALS['promotion_plugin']['commandes'] = _T('commandes:commandes_titre');
