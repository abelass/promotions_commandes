<?php
/**
 * Utilisations de pipelines par Promotions commandes
 *
 * @plugin     Promotions commandes
 * @copyright  2018 - 2021
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
		if (! _request('exec')) {
			$date = date('Y-m-d H:i:s');
			$sql = sql_select('prix_unitaire_ht, reduction, id_commandes_detail', 'spip_commandes_details', 'id_commande=' . $id_commande);

			$reduction_effective = 0;
			while ($commande_details = sql_fetch($sql)) {
				$sql = sql_select('*', 'spip_promotions', 'statut=' . sql_quote('publie'), '', 'rang');
				$commandes_exclus = array();

				$i = 0;
				while ($data = sql_fetch($sql)) {
					// Établir le prix original
					if ($i == 0) {
						$flux['data']['prix_original'] = $commande_details['prix_unitaire_ht'];
					}

					if (!$prix_promotion) {
						$prix_promotion = $flux['data']['prix_original'];
					}

					$reduction_original = $commande_details['reduction'];

					$flux['data']['prix_ht'] = $commande_details['prix_unitaire_ht'];

					$plugins_applicables = isset($data['plugins_applicables']) ? unserialize($data['plugins_applicables']) : '';
					$non_cumulable = isset($data['non_cumulable']) ? unserialize($data['non_cumulable']) : array ();
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

								// On applique les réductions prévues
									// En pourcentage
									if ($type_reduction == 'pourcentage') {

										// Prix de base
										if (isset($data['prix_base'])) {
											if ($data['prix_base'] == 'prix_reduit') {
												$prix_base = $prix_promotion;
											}
											elseif ($data['prix_base'] == 'prix_original') {
												$prix_base = $flux['data']['prix_original'];
											}
										}

										if($prix_base > 0) {
											$reduction = $prix_base / 100 * $reduction_promo;
											$reduction_effective  = $reduction_effective + ($reduction / $flux['data']['prix_original']);
											$prix_promotion = $prix_base * (1.0 - $reduction_effective);
										}
									}
									// En absolu
									elseif ($type_reduction == 'absolu') {
										if ($prix_promotion > 0) {
											$reduction_effective = $reduction_effective + ($reduction_promo / $flux['data']['prix_original']);
											$prix_promotion = $prix_promotion * (1.0 - $reduction_effective);
										}
									}
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

				// En enregistre la réduction.
				sql_updateq(
						'spip_commandes_details',
						array('reduction' => $reduction_effective),
						'id_commandes_detail=' . $commande_details['id_commandes_detail']);
			}
		}
	}
	return $flux;
}

