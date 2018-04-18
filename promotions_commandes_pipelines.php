<?php
/**
 * Utilisations de pipelines par Promotions commandes
 *
 * @plugin     Promotions commandes
 * @copyright  2018
 * @author     Rainer Müller
 * @licence    GNU/GPL
 * @package    SPIP\Promotions_commandes\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Intervient sur le montant des prix.
 *
 * @pipeline panier2commande_prix
 *
 * @param array $flux
 *        	Données du pipeline
 * @return array
 */
function promotions_panier2commande_prix($flux) {
	if (! _request('exec')) {
		$date = date('Y-m-d H:i:s');
		$sql = sql_select('*', 'spip_promotions', 'statut=' . sql_quote('publie'), '', 'rang');
		//$commandes_exclus = _request('commandes_exclus') ? _request('commandes_exclus') : array ();
		$id_evenement = $flux['data']['id_evenement'];

		while ($data = sql_fetch($sql)) {

			//$non_cumulable = isset($data['non_cumulable']) ? unserialize($data['non_cumulable']) : array ();
			$id_promotion = $data['id_promotion'];
			//$commandes_exclus_promotion = isset($commandes_exclus[$id_promotion]) ? $commandes_exclus[$id_promotion] : array ();
			$exclure_toutes = (isset($evenements_exclus['toutes'])) ? $evenements_exclus['toutes'] : '';
			if ($details = charger_fonction('action', 'promotions/' . $data['type_promotion'], true)
					and ($data['date_debut'] == '0000-00-00 00:00:00' or $data['date_debut'] <= $date)
					and ($data['date_fin'] == '0000-00-00 00:00:00' or $data['date_fin'] >= $date)
					//and ! in_array($id_evenement, $commandes_exclus_promotion)
					and (! $exclure_toutes or ($exclure_toutes and $exclure_toutes[0] == $id_promotion))) {

						// Essaie de trouver le prix original
						$flux['data']['prix_original'] = isset($flux['data']['prix_original']) ? $flux['data']['prix_original'] : $flux['data']['prix_ht'];
						$data['valeurs_promotion'] = unserialize($data['valeurs_promotion']);

						// Pour l'enregistrement de la promotion
						$flux['data']['objet'] = 'reservations_detail';
						$flux['data']['table'] = 'spip_reservations_details';

						$reduction = $data['reduction'];
						$type_reduction = $data['type_reduction'];
						$flux['data']['applicable'] = 'non';

						// On passe à la fonction de la promotion pour établir si la promotion s'applique
						$flux = $details($flux, $data);

						// Si oui on modifie le prix
						if ($flux['data']['applicable'] == 'oui') {
							/*if (is_array($non_cumulable)) {
								foreach ($non_cumulable as $nc) {
									$evenements_exclus[$nc][] = $id_evenement;
									if ($nc == 'toutes')
										$evenements_exclus[$nc][0] = $id_promotion;
								}
							}
							set_request('evenements_exclus', $evenements_exclus);*/

							// On applique les réductions prévues
							// En pourcentage
							if ($type_reduction == 'pourcentage') {
								// Prix de base
								if (isset($data['prix_base'])) {
									if ($data['prix_base'] == 'prix_reduit')
										$prix_base = $flux['data']['prix_ht'];
										elseif ($data['prix_base'] == 'prix_original')
										$prix_base = $flux['data']['prix_original'];
								}

								if($flux['data']['prix_ht'] > 0) {
									$flux['data']['prix_ht'] = $flux['data']['prix_ht'] - ($prix_base / 100 * $reduction);
								}
								else {
									$flux['data']['prix_ht'] = 0;
								}
								$flux['data']['prix'] = 0;
							} // En absolu
							elseif ($type_reduction == 'absolu')
							if ($flux['data']['prix_ht'] > 0) {
								$flux['data']['prix_ht'] = $flux['data']['prix_ht'] - $reduction;
								$flux['data']['prix'] = 0;
							}
						}

						// On prépare l'enregistrement de la promotion
						set_request('donnees_promotion', array (
							'id_promotion' => $data['id_promotion'],
							'objet' => $flux['data']['objet'],
							'prix_original' => $flux['data']['prix_original'],
							'prix_promotion' => $flux['data']['prix_ht']
						));
						// On passe le nom de la table pour la pipeline post_insertion
						set_request('table', $flux['data']['table']);
					}
					else
						set_request('donnees_promotion', '');
		}
	}
	return $flux;
}

