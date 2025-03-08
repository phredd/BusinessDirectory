<?php
	/**
	 * Script principal d'importation des données d'entreprises
	 * 
	 * Exécution: php import.php [mots-clés] [localisation]
	 * Exemple: php import.php "restaurant,coiffeur,garage" "Paris"
	 * 
	 * Pour importer depuis annuaire-entreprises.data.gouv.fr avec un département spécifique:
	 * php import.php "75" ""
	 */

	require_once __DIR__ . '/vendor/autoload.php';
	require_once __DIR__ . '/src/Importer/EntrepriseImporter.php';

	use App\Importer\EntrepriseImporter;

	// Récupérer les arguments de la ligne de commande
	$keywords = $argv[1] ?? 'restaurant,coiffeur,boulangerie';
	$location = $argv[2] ?? 'Paris';

	// Convertir les mots-clés en tableau
	if (!is_array($keywords))
		$keywords = explode(',', $keywords);

	// Créer et exécuter l'importateur
	try
	{
		$importer = new EntrepriseImporter();
		$importer->runImport($keywords, $location);
		
		echo "Import terminé avec succès.\n";
		exit(0);
	}
	catch (Exception $e)
	{
		echo "Erreur critique: " . $e->getMessage() . "\n";
		exit(1);
	}
?>
