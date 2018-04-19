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

function promotions_commandes_post_edition($flux) {

	$table = $flux['args']['table'];
	if ($table == 'spip_commandes' and
				$flux['args']['action'] == 'remplir_commande' and
				$id_commande = $flux['args']['id_objet']
			) {
		spip_log(1, 'teste');
		if (! _request('exec')) {
			spip_log(2, 'teste');
			$date = date('Y-m-d H:i:s');
			$sql = sql_select('prix_unitaire_ht, reduction, id_commandes_detail', 'spip_commandes_details', 'id_commande=' . $id_commande);

			$reduction_effective = 0;
			while ($commande_details = sql_fetch($sql)) {
				$sql = sql_select('*', 'spip_promotions', 'statut=' . sql_quote('publie'), '', 'rang');
				$commandes_exclus = _request('commandes_exclus') ? _request('commandes_exclus') : array ();

				$i = 0;
				while ($data = sql_fetch($sql)) {
					// Établir le prix original
					if ($i == 0) {
						$flux['data']['prix_original'] = $commande_details['prix_unitaire_ht'];
					}

					$reduction_original = $commande_details['reduction'];

					$flux['data']['prix_ht'] = $commande_details['prix_unitaire_ht'];

					$plugins_applicables = isset($data['plugins_applicables']) ? unserialize($data['plugins_applicables']) : '';
					//$non_cumulable = isset($data['non_cumulable']) ? unserialize($data['non_cumulable']) : array ();
					$id_promotion = $data['id_promotion'];
					$commandes_exclus_promotion = isset($commandes_exclus[$id_promotion]) ? $commandes_exclus[$id_promotion] : array ();
					$exclure_toutes = (isset($commandes_exclus['toutes'])) ? $commandes_exclus['toutes'] : '';
					if ($details = charger_fonction('action', 'promotions/' . $data['type_promotion'], true) and
							(
									!$plugins_applicables or
									in_array('commandes', $plugins_applicables)
									) and
							(
									$data['date_debut'] == '0000-00-00 00:00:00' or
									$data['date_debut'] <= $date
									) and
							(
									$data['date_fin'] == '0000-00-00 00:00:00'
									or
									$data['date_fin'] >= $date
									) and
									!in_array($id_commande, $commandes_exclus_promotion) and
									(!$exclure_toutes or ($exclure_toutes and $exclure_toutes[0] == $id_promotion))
							) {
								spip_log(3, 'teste');

								$data['valeurs_promotion'] = unserialize($data['valeurs_promotion']);

								// Pour l'enregistrement de la promotion
								$flux['data']['objet'] = 'commandes_detail';
								$flux['data']['table'] = 'spip_commandes_details';

								$reduction_promo = $data['reduction'];
								$type_reduction = $data['type_reduction'];
								$flux['data']['applicable'] = 'non';

								// On passe à la fonction de la promotion pour établir si la promotion s'applique
								$flux = $details($flux, $data);

								// Si oui on modifie le prix
								if ($flux['data']['applicable'] == 'oui') {
									if (is_array($non_cumulable)) {
										foreach ($non_cumulable as $nc) {
											$commandes_exclus[$nc][] = $id_commande;
											if ($nc == 'toutes')
												$commandes_exclus[$nc][0] = $id_promotion;
										}
									}
									set_request('commandes_exclus', $commandes_exclus);

									// On applique les réductions prévues
									// En pourcentage
									if ($type_reduction == 'pourcentage') {

										// Prix de base
										if (isset($data['prix_base'])) {
											if ($data['prix_base'] == 'prix_reduit') {
												$prix_base = $flux['data']['prix_ht'] * (1.0 - $commande_details['reduction']);
											}
											elseif ($data['prix_base'] == 'prix_original') {
												$prix_base = $flux['data']['prix_original'];
											}
										}

										if($prix_base > 0) {
											$reduction = $prix_base / 100 * $reduction_promo;
											$reduction_effective  = $reduction_effective + ($reduction / $prix_base);
											$prix_promotion = $prix_base - $reduction_effective;
										}
									} // En absolu
									elseif ($type_reduction == 'absolu') {
										spip_log(4.2, 'teste');
										if ($prix_base > 0) {
											$reduction_effective = $reduction_effective +($reduction / $prix_base);
											$prix_promotion = $prix_base - $reduction_effective;
										}
									}
									spip_log('prix' . $prix_promotion, 'teste');
									spip_log('reduction' . $reduction_effective, 'teste');
								}

								// On prépare l'enregistrement de la promotion
								set_request('donnees_promotion', array (
									'id_promotion' => $data['id_promotion'],
									'objet' => $flux['data']['objet'],
									'prix_original' => $flux['data']['prix_original'],
									'prix_promotion' => $prix_promotion
								));
								// On passe le nom de la table pour la pipeline post_insertion
								set_request('table', $flux['data']['table']);
							}
							else
								set_request('donnees_promotion', '');
				}

				sql_updateq(
						'spip_commandes_details',
						array('reduction' => $reduction_effective),
						'id_commandes_detail=' . $commande_details['id_commandes_detail']);
			}
		}
	}
	return $flux;
}

