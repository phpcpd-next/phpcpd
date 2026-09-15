<?php

declare(strict_types=1);
/*
 * Ce fichier fait partie de PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * Pour des informations complètes sur le droit d'auteur et la licence,
 * veuillez consulter le fichier LICENSE distribué avec ce code source.
 */
/* Traduction française.
 *
 * Clés, espaces réservés, comptes et ce qui va ici en général : voir
 * docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'ERREUR : :message',
        'warning' => 'AVERTISSEMENT : :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'Dans :file : :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Fichier de configuration introuvable : :path',
        ],
        'unparsable' => [
            'config' => 'Le fichier de configuration n\'a pas pu être analysé : :path',
        ],
        'unknown' => [
            'option'  => 'Option inconnue :flag.',
            'setting' => 'Paramètre inconnu ":name" dans :file',
        ],
        'needsValue' => [
            'option'  => 'L\'option :flag nécessite une valeur.',
            'setting' => 'Le paramètre ":name" dans :file nécessite une valeur.',
        ],
        'takesNoValue' => [
            'option' => 'L\'option :flag ne prend pas de valeur.',
        ],
        'invalidValue' => [
            'option' => 'Valeur invalide ":value" pour :flag (autorisé : :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Le moteur unifié nécessite un :flag d\'au moins :floor (fourni : :given). En dessous de cela, la fenêtre de filtrage descend en dessous de 4 et l\'index cesse d\'être un échantillon, le moteur refuse donc au lieu de se dégrader silencieusement vers une analyse exhaustive.',
        ],
        'unwired' => [
            'option' => 'L\'option :flag n\'a pas de liaison de paramètre.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Refus d\'analyser la racine du système de fichiers (:path). Vouliez-vous dire "./" ?',
            'aboveProject'   => 'Refus d\'analyser :path : il se trouve au-dessus de la racine du projet :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Aucun fichier trouvé à analyser.',
            'afterTriage' => 'Aucun fichier restant à analyser après le triage.',
        ],
        'missingArgument' => [
            'directory' => 'Aucun répertoire spécifié.',
        ],
        'writeFailed' => [
            'report' => 'Impossible d\'écrire le rapport dans :path : :detail',
        ],
        'writePartial' => [
            'report' => 'Seuls sur (:written) octets (:total) du rapport ont été écrits dans :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'le préréglage ":preset" déclare des chemins d\'analyse (:declared), manquants (:missing) : :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans ne peut pas voir tout le projet.',
            'belowRoots'     => '  Chaque racine d\'analyse est en dessous des racines d\'autoload de :manifest.',
            'uncoveredRoots' => '  chemins d\'autoload déclarés dans :manifest mais jamais ouverts (:count) :',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset détecté — préréglage appliqué (--no-preset pour désactiver)',
        ],
        'cache' => [
            'hit' => '(correspondance dans le cache)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag est obsolète ; utilisez --algorithm=unified — qui ne signale pas encore chaque emplacement que le token bag signale, il reste donc sélectionnable)',
        ],
        'incremental' => [
            'combined'    => '(--incremental ignoré en mode combiné)',
            'unsupported' => '(--incremental ignoré : seuls les algorithmes rabin-karp et unified possèdent un index incrémentiel)',
            'index'       => '(index incrémentiel : :reused réutilisés, :scanned analysés)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Aucun clone de code trouvé.',
            'heading'        => 'Clones de code trouvés (:clones):gapped:reordered, lignes dupliquées (:lines), fichiers (:files) :',
            'gapped'         => ', incohérents (:count)',
            'reorderedCount' => ', réordonnés (:count)',
            'reordered'      => '[réordonné]',
            'unreadable'     => 'fichiers illisibles (:count) — dans aucun des totaux :',
            'strataAsserted' => ':asserted affirmés, 0 rétrogradés.',
            'strataSplit'    => ':asserted affirmés, :demoted rétrogradés (:detail).',
            'settled'         => 'lectures écartées comme déjà décrites (:count).',
            'unfounded'      => 'résultats écartés faute de vérification (:count).',
            'hiddenLine'      => ':count résultats sur :total masqués sous la confiance :threshold — --hidden les liste.',
            'hiddenHeading'   => 'Masqués sous la confiance :threshold (:count) :',
            'coverage'         => ':percentage des lignes analysées (:lines) sont du code dupliqué.',
            'literals'         => '[les littéraux diffèrent (:count)]',
            'confidence'       => 'confiance :score (:terms)',
            'functions'        => 'dans :names',
            'sizes'          => 'Le clone moyen a :average lignes ; le plus grand en a :largest.',
        ],
        'ledger' => [
            'line'      => 'Reconnus : sur (:acknowledged) résultats (:total) rétrogradés par le grand livre (ledger) ; obsolètes (:stale).',
            'staleNote' => 'obsolète (le code reconnu a changé) : :note',
            'wrote'     => 'reconnaissances écrites (:count) : :path',
        ],
        'config' => [
            'missingPath' => ':path (manquant)',
            'source' => [
                'default'     => 'par défaut',
                'commandLine' => 'ligne de commande',
                'builtIn'     => '    valeurs par défaut intégrées',
            ],
            'fallback'    => ' retour à la valeur par défaut intégrée',
            'layers'      => '  Couches, priorité la plus faible en premier :',
        ],
        'run' => [
            'throughput' => ' — fichiers (:count) à :rate/s',
            'files'    => ' — fichiers (:count)',
            'usage'  => 'Temps : :duration, Mémoire : :memory Mo',
            'banner' => 'phpcpd :version par :author — basé sur :origin par :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Racine d\'analyse : :root',
            'roots'              => 'Racines d\'analyse :',
            'count' => [
                'file'       => 'fichiers (:count)',
                'directory'  => 'racines (:count)',
                'pattern'    => 'exclusions (:count)',
                'unreadable' => 'illisibles (:count)',
                'generated'  => 'générés (:count)',
            ],
            'counts'             => 'Analysés :counts',
        ],
        'triage' => [
            'nothing'  => 'Triage : rien étiqueté ; chaque fichier est du texte de programme.',
            'removed'  => 'Triage : fichiers (:total), supprimés (:removed)',
            'labelled' => 'Triage : fichiers (:total), étiquetés (:labelled), aucun supprimé',
        ],
        'orphan' => [
            'none'         => 'Aucun symbole orphelin trouvé (symboles (:symbols) dans fichiers (:files)).',
            'found'        => 'symboles orphelins (:count) :',
            'possible'     => 'symboles orphelins possibles (:count) — à examiner avant suppression :',
            'advisory'     => 'symboles orphelins (:count) — indicatif, n\'affecte pas le code de sortie :',
            'notShown'     => 'autres résultats d\'orphelins non affichés (:count) — exécutez --orphans pour les examiner.',
            'suppressed'   => 'Supprimés (:count) : :census',
            'explainHint'  => '  → --explain pour les lister',
            'wholeFile'    => '    ⤷ le fichier entier est déconnecté — aucun symbole déclaré ici n\'est référencé',
            'supersededBy' => '    ⤷ ressemble à une copie remplacée de :name',
            'summary'      => 'symboles (:symbols) analysés dans fichiers (:files) ; orphelins (:orphaned), possibles (:possible), supprimés (:suppressed), planifiés (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'en dehors de l\'ensemble de fichiers laissé par les propres exclusions par défaut de ce projet — un arbre généré, qui peut ne pas témoigner du câblage (wiring)',
            'declaredHere'  => 'symboles déclarés ici (:count), aucun référencé nulle part dans le projet',
            'foreignNs'     => 'déclare :namespaces — un espace de noms qu\'aucun composer.json au-dessus de lui ne déclare, en dehors de chaque répertoire qu\'ils câblent',
        ],
        'role' => [
            'noStatements' => 'aucune instruction de niveau supérieur après le préambule',
            'coupled'      => ':registrations sur :statements instructions de niveau supérieur sont des expressions d\'enregistrement, mais partagent une variable',
            'independent'  => ':registrations sur :statements instructions de niveau supérieur sont des expressions d\'enregistrement indépendantes du flux de données',
        ],
        'orphan' => [
            'guard'          => 'déclaré à l\'intérieur d\'une garde d\'existence — polyfill ou shim de compatibilité',
            'entrypoint'     => 'déclaré dans :namespace — invoqué par la convention du framework',
            'partialProject' => '  Un symbole est qualifié de mort lorsque *rien* ne le référencie, ce qui est une affirmation concernant l\'ensemble
  du projet. Le code en dehors de cette analyse peut toujours référencer ce qui est rapporté ici.',
            'evidence' => [
                'nameAt'   => 'le nom apparaît à',
                'loopAt'   => 'découvert par la boucle à',
                'suffixAt' => 'suffixe déclaré à',
                'namedIn'  => 'nommé dans',
            ],
            'plannedServed'  => 'référencé maintenant — @phpcpd-planned a rempli son rôle et peut être supprimé',
            'manifest'       => 'déclaré dans un point d\'entrée autoload.files de composer',
            'foreignNs'      => 'déclaré en dehors des espaces de noms propres au projet (shim de compatibilité)',
            'fixture'        => 'fixture de test — chargée par chemin ou nommée sous forme de chaîne, jamais référencée',
            'discovery'      => 'découvert par une analyse de répertoire — instancié à partir de son nom de fichier derrière class_exists',
            'convention'     => 'classe compagnon — :base utilise :trait, qui résout ce nom par suffixe à l\'exécution',
            'interface'      => 'jamais référencé (interface — peut être implémenté en dehors de l\'ensemble analysé)',
            'trait'          => 'jamais référencé (trait — peut être utilisé par des classes en dehors de l\'ensemble analysé)',
            'abstract'       => 'jamais référencé (abstrait — peut être étendu en dehors de l\'ensemble analysé)',
            'inString'       => 'jamais référencé dans le code ; le nom apparaît dans un littéral de chaîne (utilisation dynamique possible)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Déclaré conditionnellement (polyfill / shim de compatibilité)',
            'fixtures'    => 'Fixtures de test (chargées par chemin ou par nom)',
            'config'      => 'Enregistré dans un fichier de configuration',
            'template'    => 'Référencé depuis un modèle (blade / twig / latte)',
            'manifest'    => 'Référencé depuis composer.json',
            'namespace'   => 'Déclaré en dehors des espaces de noms propres au projet (shim de compatibilité)',
            'keep'        => 'Marqué à conserver (@api / @phpcpd-keep)',
            'entrypoint'  => 'Points d\'entrée du framework (attribut / classe de test)',
            'discovery'   => 'Découvert par une analyse de répertoire (instancié à partir de son nom de fichier)',
            'convention'  => 'Classe compagnon nommée par convention (suffixe déclaré par un trait)',
            'planned'     => 'Planifié, pas encore câblé',
            'none'        => 'Aucune référence trouvée',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Passez --allow-root-scan si vous vouliez vraiment dire tout le système de fichiers.',
            'allowOutside' => 'Passez --allow-root-scan pour analyser en dehors du projet de toute façon.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Analysez la racine du projet pour obtenir un résultat sur lequel il vaut la peine d\'agir.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard agit sur les étiquettes ; --no-triage ignore l\'étape)',
            'explain' => '  (--explain liste chaque fichier et les preuves pour ou contre)',
        ],
        'clone' => [
            'gapped'  => 'Quasi-clone — envisagez de paramétrer la partie divergente ou d\'aligner les deux copies.',
            'demoted' => 'Rétrogradé en tant que :stratum — c\'est la forme que décrit cette strate, ne l\'extrayez donc que si la répétition n\'est pas le point central.',
            'extract' => 'Envisagez d\'extraire les lignes partagées dans une méthode, une classe ou un trait réutilisable.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Utilisation :',
            'invocation' => '  phpcpd [options] <répertoire>',
        ],
        'group' => [
            'selecting' => 'Options de sélection de fichiers',
            'orphans'   => 'Détection d\'orphelins (code mort)',
            'analysing' => 'Options d\'analyse de fichiers',
            'general'   => 'Options générales',
            'reporting' => 'Options de génération de rapports',
            'ci'        => 'Options d\'intégration CI',
        ],
        'option' => [
            'suffix'              => 'Inclure les fichiers dont les noms se terminent par <suffix> (par défaut : :default ; répétable)',
            'exclude'             => 'Exclure les fichiers contenant <path> dans leur chemin (répétable)',
            'preset'              => 'Appliquer un préréglage de framework (ex. laravel) : définit des chemins, suffixes et exclusions adaptés',
            'triage'              => 'Exécuter le triage de corpus de l\'étape 0 avant la détection (activé par défaut ; ceci le demande explicitement)',
            'no_triage'           => 'Ignorer complètement l\'étape 0 : aucun fichier n\'est étiqueté comme déconnecté, ombragé (shadowed), vendored ou dérivé',
            'triage_posture'      => 'Ce que fait le triage avec un fichier qu\'il étiquette : l\'écarter de l\'analyse (par défaut), ou l\'étiqueter et rien de plus',
            'no_preset'           => 'Ne pas appliquer automatiquement un préréglage de framework lorsqu\'il est détecté (la détection s\'annonce elle-même ; --preset= l\'invalide)',
            'no_default_excludes' => 'Analyser également les arbres générés et de cache (vendor, node_modules, .phpstan.cache, build, ...), qui sont ignorés par défaut',
            'allow_root_scan'     => 'Autoriser une racine d\'analyse de / ou une racine au-dessus du composer.json le plus proche (refusé par défaut : `phpcpd /` est presque toujours une erreur de frappe pour `phpcpd ./`)',
            'orphans'             => 'Détecter les symboles orphelins (classes, interfaces, traits, enums, fonctions non référencés) au lieu des clones',
            'no_suppress'         => 'Désactiver les règles de suppression par nom, séparées par des virgules, ou "all" (:rules)',
            'fail_on'             => 'Niveaux de résultat qui font échouer l\'exécution (code de sortie non nul), séparés par des virgules (par défaut : :default)',
            'explain'             => 'Lister chaque symbole supprimé et la règle qui l\'a supprimé, au lieu de les compter simplement',
            'rk'                  => 'Rabin-Karp uniquement (clones exacts/Type-1 ; plus rapide, pas de détection de réorganisation). Exécute par défaut Rabin-Karp et TokenBag.',
            'min_lines'           => 'Nombre minimum de lignes identiques (par défaut : :default)',
            'min_tokens'          => 'Nombre minimum de tokens identiques (par défaut : :default)',
            'language'            => 'Langue du rapport (par défaut : :default)',
            'verbose'             => 'Afficher le code dupliqué pour chaque clone',
            'algorithm'           => 'Remplacement d\'algorithme unique (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'Seuil de chevauchement TokenBag (par défaut : :default)',
            'raw'                   => 'Comparer le texte brut : les identifiants doivent aussi concorder (désactive la normalisation par défaut)',
            'fuzzy'                 => 'Normalisation aveugle aux noms : comme par défaut mais sans l\'ancrage de type (recherche ; E2 l\'a mesurée dominée)',
            'type_anchored'         => 'Garder les mots-clés de type concrets sous normalisation (actif par défaut ; --fuzzy le désactive)',
            'min_confidence'        => 'Ne lister que les résultats que le modèle note au-dessus de <log-odds> ; les autres sont comptés et lisibles avec --hidden, jamais supprimés, et conditionnent toujours --fail-on',
            'hidden'                => 'Lister les résultats retenus par --min-confidence',
            'cache'                 => 'Mettre les résultats en cache dans \'.phpcpd-cache/\' — un succès exige chaque fichier inchangé, ce qui sert à relancer un commit plutôt qu\'à passer au suivant',
            'acknowledged'        => 'Lire un grand livre de reconnaissances validé à partir de <file> : la duplication listée est rétrogradée, jamais cachée, et les entrées dont le code a changé expirent et sont signalées',
            'write_acknowledged'  => 'Écrire les résultats de cette exécution dans <file> en tant que grand livre de reconnaissances, pour révision et validation',
            'log_pmd'             => 'Écrire le journal au format XML PMD-CPD dans <file>',
            'log_json'            => 'Écrire le journal au format JSON dans <file>',
            'log_sarif'           => 'Écrire le journal au format SARIF 2.1.0 dans <file> (pour GitHub Code Scanning)',
            'cache_dir'           => 'Lire/écrire le cache depuis <path> (implique --cache ; remplace le répertoire par défaut)',
            'incremental'           => 'Index incrémental par fichier : ne retokenise que les fichiers modifiés (rabin-karp ou unified, pas le pipeline combiné par défaut ; utilise le répertoire de cache)',
            'config'              => 'Lire la configuration depuis <file> (par défaut : ./phpcpd.ini lorsqu\'il est présent) ; les clés sont les noms longs des options',
            'show_config'         => 'Afficher les paramètres en vigueur, d\'où chacun provient, et quitter',
            'no_config'           => 'Ignorer ./phpcpd.ini',
            'help'                => 'Afficher cette aide',
            'version'             => 'Afficher les informations de version',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'grand livre de reconnaissances (acknowledgment ledger) de phpcpd-next',
            'what'  => 'Chaque ligne enregistre une duplication que ce projet a examinée et décidée de
conserver. Un résultat reconnu est RÉTROGRADÉ, jamais caché : il est toujours
signalé, toujours compté, et conditionne toujours le code de sortie.',
            'key'   => 'La clé est le contenu de chaque côté de la duplication, haché — ni un chemin
ni un numéro de ligne. Par conséquent, la modification de l\'une ou l\'autre des copies fait expirer l\'entrée et le
résultat est à nouveau affirmé, tandis que le déplacement du code ne change rien. Une entrée
qui ne correspond plus à rien est signalée comme obsolète afin de pouvoir être supprimée.',
            'note'  => 'Le texte après la tabulation est une note humaine. Il n\'est jamais utilisé pour la correspondance.',
        ],
    ],
];
