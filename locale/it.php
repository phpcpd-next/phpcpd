<?php

declare(strict_types=1);
/*
 * Questo file fa parte di PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * Per le informazioni complete su copyright e licenza, consulta il file LICENSE 
 * distribuito con questo codice sorgente.
 */
/*
 * Traduzione in italiano.
 *
 * Chiavi, segnaposto, conteggi e cosa va inserito qui in generale: vedi
 * docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'ERRORE: :message',
        'warning' => 'AVVISO: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'In :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'File di configurazione non trovato: :path',
        ],
        'unparsable' => [
            'config' => 'Impossibile analizzare il file di configurazione: :path',
        ],
        'unknown' => [
            'option'  => 'Opzione sconosciuta :flag.',
            'setting' => 'Impostazione sconosciuta ":name" in :file',
        ],
        'needsValue' => [
            'option'  => 'L\'opzione :flag richiede un valore.',
            'setting' => 'L\'impostazione ":name" in :file richiede un valore.',
        ],
        'takesNoValue' => [
            'option' => 'L\'opzione :flag non accetta un valore.',
        ],
        'invalidValue' => [
            'option' => 'Valore non valido ":value" per :flag (consentiti: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'Il motore unificato necessita di :flag di almeno :floor (fornito: :given). Al di sotto di questo, la finestra di filtraggio scende sotto 4 e l\'indice smette di essere un campione, quindi il motore rifiuta invece di degradare silenziosamente a una scansione esaustiva.',
        ],
        'unwired' => [
            'option' => 'L\'opzione :flag non ha un collegamento di configurazione.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Rifiuto di scansionare la radice del file system (:path). Intendevi "./"?',
            'aboveProject'   => 'Rifiuto di scansionare :path: è al di sopra della radice del progetto :project.',
        ],
        'nothingToScan' => [
            'files'       => 'Nessun file trovato da scansionare.',
            'afterTriage' => 'Nessun file rimasto da scansionare dopo il triage.',
        ],
        'missingArgument' => [
            'directory' => 'Nessuna directory specificata.',
        ],
        'writeFailed' => [
            'report' => 'Impossibile scrivere il report in :path:detail',
        ],
        'writePartial' => [
            'report' => 'Scritti solo di (:written) byte (:total) del report in :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'il preset ":preset" dichiara percorsi di scansione (:declared), mancanti (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans non può vedere l\'intero progetto.',
            'belowRoots'     => '  Ogni radice di scansione si trova al di sotto delle radici di autoload di :manifest.',
            'uncoveredRoots' => '  percorsi di autoload dichiarati in :manifest ma mai aperti (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset rilevato — preset applicato (--no-preset per disabilitare)',
        ],
        'cache' => [
            'hit' => '(hit della cache)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag è deprecato; usa --algorithm=unified — il quale non riporta ancora ogni posizione rilevata dal token bag, per cui rimane selezionabile)',
        ],
        'incremental' => [
            'combined'    => '(--incremental ignorato in modalità combinata)',
            'unsupported' => '(--incremental ignorato: solo gli algoritmi rabin-karp e unified hanno un indice incrementale)',
            'index'       => '(indice incrementale: :reused riutilizzati, :scanned scansionati)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'Nessun clone di codice trovato.',
            'heading'        => 'Trovati cloni di codice (:clones):gapped:reordered, righe duplicate (:lines), file (:files):',
            'gapped'         => ', incoerenti (:count)',
            'reorderedCount' => ', riordinati (:count)',
            'reordered'      => '[riordinato]',
            'unreadable'     => 'file illeggibili (:count) — in nessuno dei totali:',
            'strataAsserted' => ':asserted asseriti, 0 declassati.',
            'strataSplit'    => ':asserted asseriti, :demoted declassati (:detail).',
            'settled'         => 'letture scartate perché già descritte (:count).',
            'unfounded'      => 'risultati scartati perché non verificati (:count).',
            'hiddenLine'      => ':count di :total risultati nascosti sotto la confidenza :threshold — --hidden li elenca.',
            'hiddenHeading'   => 'Nascosti sotto la confidenza :threshold (:count):',
            'coverage'         => 'Il :percentage delle righe analizzate (:lines) è codice duplicato.',
            'literals'         => '[i letterali differiscono (:count)]',
            'confidence'       => 'confidenza :score (:terms)',
            'functions'        => 'in :names',
            'sizes'          => 'Il clone medio ha :average righe; il più grande ne ha :largest.',
        ],
        'ledger' => [
            'line'      => 'Riconosciuti: di (:acknowledged) risultati (:total) declassati dal registro (ledger); obsoleti (:stale).',
            'staleNote' => 'obsoleto (il codice riconosciuto è cambiato): :note',
            'wrote'     => 'riconoscimenti scritti (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (mancante)',
            'source' => [
                'default'     => 'predefinito',
                'commandLine' => 'riga di comando',
                'builtIn'     => '    impostazioni predefinite integrate',
            ],
            'fallback'    => ' ritorno all\'impostazione predefinita integrata',
            'layers'      => '  Livelli, priorità più bassa prima:',
        ],
        'run' => [
            'throughput' => ' — file (:count) a :rate/s',
            'files'    => ' — file (:count)',
            'usage'  => 'Tempo: :duration, Memoria: :memory MB',
            'banner' => 'phpcpd :version di :author — basato su :origin di :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Radice di scansione: :root',
            'roots'              => 'Radici di scansione:',
            'count' => [
                'file'       => 'file (:count)',
                'directory'  => 'radici (:count)',
                'pattern'    => 'esclusioni (:count)',
                'unreadable' => 'illeggibili (:count)',
                'generated'  => 'generati (:count)',
            ],
            'counts'             => 'Scansionati :counts',
        ],
        'triage' => [
            'nothing'  => 'Triage: niente etichettato; ogni file è testo di programma.',
            'removed'  => 'Triage: file (:total), rimossi (:removed)',
            'labelled' => 'Triage: file (:total), etichettati (:labelled), nessuno rimosso',
        ],
        'orphan' => [
            'none'         => 'Nessun simbolo orfano trovato (simboli (:symbols) in file (:files)).',
            'found'        => 'simboli orfani (:count):',
            'possible'     => 'possibili orfani (:count) — rivedere prima di rimuovere:',
            'advisory'     => 'simboli orfani (:count) — informativo, non influisce sul codice di uscita:',
            'notShown'     => 'ulteriori risultati di orfani non mostrati (:count) — esegui --orphans per esaminarli.',
            'suppressed'   => 'Soppressi (:count): :census',
            'explainHint'  => '  → --explain per elencarli',
            'wholeFile'    => '    ⤷ l\'intero file è scollegato — nessun simbolo dichiarato qui è referenziato',
            'supersededBy' => '    ⤷ sembra una copia sostituita di :name',
            'summary'      => 'simboli (:symbols) scansionati in file (:files); orfani (:orphaned), possibili (:possible), soppressi (:suppressed), pianificati (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'al di fuori del set di file lasciato dalle esclusioni predefinite di questo progetto — un albero generato, che potrebbe non testimoniare i collegamenti (wiring)',
            'declaredHere'  => 'simboli dichiarati qui (:count), nessuno referenziato in alcun punto del progetto',
            'foreignNs'     => 'dichiara :namespaces — un namespace non dichiarato da alcun composer.json superiore, al di fuori di ogni directory collegata',
        ],
        'role' => [
            'noStatements' => 'nessuna istruzione di primo livello oltre il preambolo',
            'coupled'      => ':registrations di :statements istruzioni di primo livello sono espressioni di registrazione, ma condividono una variabile',
            'independent'  => ':registrations di :statements istruzioni di primo livello sono espressioni di registrazione indipendenti dal flusso di dati',
        ],
        'orphan' => [
            'guard'          => 'dichiarato all\'interno di un controllo di esistenza (guard) — polyfill o shim di compatibilità',
            'entrypoint'     => 'dichiarato in :namespace — invocato per convenzione del framework',
            'partialProject' => '  Un simbolo è considerato morto quando *niente* lo referenzia, il che è un\'affermazione sull\'intero
  progetto. Il codice al di fuori di questa scansione può ancora referenziare ciò che è riportato qui.',
            'evidence' => [
                'nameAt'   => 'il nome appare in',
                'loopAt'   => 'scoperto dal ciclo in',
                'suffixAt' => 'suffisso dichiarato in',
                'namedIn'  => 'nominato in',
            ],
            'plannedServed'  => 'referenziato ora — @phpcpd-planned ha servito al suo scopo e può essere rimosso',
            'manifest'       => 'dichiarato in un punto di ingresso autoload.files di composer',
            'foreignNs'      => 'dichiarato al di fuori dei namespace propri del progetto (shim di compatibilità)',
            'fixture'        => 'fixture di test — caricato per percorso o nominato come stringa, mai referenziato',
            'discovery'      => 'scoperto da una scansione di directory — istanziato dal suo nome file dietro class_exists',
            'convention'     => 'classe associata — :base usa :trait, che risolve questo nome tramite suffisso a runtime',
            'interface'      => 'mai referenziato (interfaccia — può essere implementata al di fuori del set scansionato)',
            'trait'          => 'mai referenziato (trait — può essere utilizzato da classi al di fuori del set scansionato)',
            'abstract'       => 'mai referenziato (astratta — può essere estesa al di fuori del set scansionato)',
            'inString'       => 'mai referenziato nel codice; il nome appare in un letterale stringa (possibile uso dinamico)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Dichiarato condizionalmente (polyfill / shim di compatibilità)',
            'fixtures'    => 'Fixture di test (caricati per percorso o per nome)',
            'config'      => 'Registrato in un file di configurazione',
            'template'    => 'Referenziato da un template (blade / twig / latte)',
            'manifest'    => 'Referenziato da composer.json',
            'namespace'   => 'Dichiarato al di fuori dei namespace propri del progetto (shim di compatibilità)',
            'keep'        => 'Marcato da mantenere (@api / @phpcpd-keep)',
            'entrypoint'  => 'Punti di ingresso del framework (attributo / classe di test)',
            'discovery'   => 'Scoperto da una scansione di directory (istanziato dal suo nome file)',
            'convention'  => 'Classe associata nominata per convenzione (suffisso dichiarato da un trait)',
            'planned'     => 'Pianificato, non ancora collegato',
            'none'        => 'Nessuna referenza trovata',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Passa --allow-root-scan se intendevi davvero l\'intero file system.',
            'allowOutside' => 'Passa --allow-root-scan per scansionare comunque al di fuori del progetto.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Scansiona la radice del progetto per ottenere un risultato su cui valga la pena agire.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard agisce sulle etichette; --no-triage salta la fase)',
            'explain' => '  (--explain elenca ogni file e le prove a favore o contro)',
        ],
        'clone' => [
            'gapped'  => 'Clone quasi esatto — considera di parametrizzare la parte divergente o allineare entrambe le copie.',
            'demoted' => 'Declassato come :stratum — questa è la forma descritta dallo strato, quindi estrailo solo se la ripetizione non è lo scopo principale.',
            'extract' => 'Considera di estrarre le righe condivise in un metodo, classe o trait riutilizzabile.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Uso:',
            'invocation' => '  phpcpd [opzioni] <directory>',
        ],
        'group' => [
            'selecting' => 'Opzioni per la selezione dei file',
            'orphans'   => 'Rilevamento orfani (codice morto)',
            'analysing' => 'Opzioni per l\'analisi dei file',
            'general'   => 'Opzioni generali',
            'reporting' => 'Opzioni per la generazione dei report',
            'ci'        => 'Opzioni per l\'integrazione CI',
        ],
        'option' => [
            'suffix'              => 'Includi i file i cui nomi terminano in <suffix> (predefinito: :default; ripetibile)',
            'exclude'             => 'Escludi i file con <path> nel loro percorso (ripetibile)',
            'preset'              => 'Applica un preset di framework (es. laravel): imposta percorsi, suffissi ed esclusioni sensati',
            'triage'              => 'Esegui la Fase 0 di triage del corpus prima del rilevamento (attivo per impostazione predefinita; questo lo richiede esplicitamente)',
            'no_triage'           => 'Salta completamente la Fase 0: nessun file viene etichettato come scollegato, oscurato (shadowed), vendored o derivato',
            'triage_posture'      => 'Cosa fa il triage con un file etichettato: scartarlo dalla scansione (predefinito), oppure etichettarlo e nient\'altro',
            'no_preset'           => 'Non auto-applicare un preset di framework quando ne viene rilevato uno (il rilevamento si annuncia da solo; --preset= lo sovrascrive)',
            'no_default_excludes' => 'Scansiona anche gli alberi generati e di cache (vendor, node_modules, .phpstan.cache, build, ...), che vengono saltati per impostazione predefinita',
            'allow_root_scan'     => 'Consenti una radice di scansione in / o una radice superiore al composer.json più vicino (rifiutato per impostazione predefinita: `phpcpd /` è quasi sempre un errore di battitura per `phpcpd ./`)',
            'orphans'             => 'Rileva simboli orfani (classi, interfacce, trait, enum, funzioni non referenziate) invece dei cloni',
            'no_suppress'         => 'Disattiva le regole di soppressione per nome, separate da virgole, o "all" (:rules)',
            'fail_on'             => 'Livelli di risultato che causano l\'uscita dell\'esecuzione con un codice diverso da zero, separati da virgole (predefinito: :default)',
            'explain'             => 'Elenca ogni simbolo soppresso e la regola che lo ha soppresso, invece di contarli semplicemente',
            'rk'                  => 'Solo Rabin-Karp (cloni esatti/Tipo-1; più veloce, nessun rilevamento di riordino). Per impostazione predefinita esegue sia Rabin-Karp che TokenBag.',
            'min_lines'           => 'Numero minimo di righe identiche (predefinito: :default)',
            'min_tokens'          => 'Numero minimo di token identici (predefinito: :default)',
            'language'            => 'Lingua per il report (predefinita: :default)',
            'verbose'             => 'Stampa il codice duplicato per ogni clone',
            'algorithm'           => 'Sovrascrittura per un singolo algoritmo (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'Soglia di sovrapposizione per TokenBag (predefinita: :default)',
            'raw'                   => 'Confrontare il testo grezzo: anche gli identificatori devono coincidere (disattiva la normalizzazione predefinita)',
            'fuzzy'                 => 'Normalizzazione cieca ai nomi: come la predefinita ma senza l\'ancoraggio di tipo (ricerca; E2 l\'ha misurata dominata)',
            'type_anchored'         => 'Mantenere concrete le parole chiave di tipo sotto normalizzazione (attivo per impostazione predefinita; --fuzzy lo disattiva)',
            'min_confidence'        => 'Elencare solo i risultati che il modello valuta a <log-odds> o più; gli altri sono contati e leggibili con --hidden, mai scartati, e continuano a determinare --fail-on',
            'hidden'                => 'Elencare i risultati trattenuti da --min-confidence',
            'cache'                 => 'Mettere in cache i risultati in \'.phpcpd-cache/\' — un successo richiede ogni file invariato, quindi serve a rieseguire un commit anziché il successivo',
            'acknowledged'        => 'Leggi un registro dei riconoscimenti (ledger) committato da <file>: la duplicazione elencata viene declassata, mai nascosta, e le voci in cui il codice è cambiato scadranno e verranno segnalate',
            'write_acknowledged'  => 'Scrivi i risultati di questa esecuzione in <file> come registro dei riconoscimenti, per revisione e commit',
            'log_pmd'             => 'Scrivi il registro in formato XML PMD-CPD in <file>',
            'log_json'            => 'Scrivi il registro in formato JSON in <file>',
            'log_sarif'           => 'Scrivi il registro in formato SARIF 2.1.0 in <file> (per GitHub Code Scanning)',
            'cache_dir'           => 'Leggi/scrivi la cache da <path> (implica --cache; sovrascrive la directory predefinita)',
            'incremental'           => 'Indice incrementale per file: ritokenizza solo i file modificati (rabin-karp o unified, non il predefinito combinato; usa la directory di cache)',
            'config'              => 'Leggi le impostazioni da <file> (predefinito: ./phpcpd.ini se presente); le chiavi sono i nomi lunghi delle opzioni',
            'show_config'         => 'Stampa le impostazioni in vigore, da dove provengono, ed esci',
            'no_config'           => 'Ignora ./phpcpd.ini',
            'help'                => 'Stampa questa guida',
            'version'             => 'Stampa le informazioni sulla versione',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'registro dei riconoscimenti (acknowledgment ledger) di phpcpd-next',
            'what'  => 'Ogni riga registra una duplicazione che questo progetto ha esaminato e deciso di
mantenere. Un risultato riconosciuto è DECLASSATO, mai nascosto: viene ancora
riportato, viene ancora contato e condiziona ancora il codice di uscita.',
            'key'   => 'La chiave è il contenuto di ciascun lato della duplicazione, sottoposto ad hash — non un percorso
e non un numero di riga. Pertanto, la modifica di una qualsiasi delle copie fa scadere la voce e il
risultato viene nuovamente asserito, mentre lo spostamento del codice non cambia nulla. Una voce
che non corrisponde più a nulla viene segnalata come obsoleta in modo che possa essere eliminata.',
            'note'  => 'Il testo dopo la tabulazione è una nota umana. Non viene mai utilizzata per le corrispondenze.',
        ],
    ],
];
