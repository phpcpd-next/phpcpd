<?php

declare(strict_types=1);
/*
 * Este archivo es parte de PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * Para ver la información completa de derechos de autor y licencia, por
 * favor consulta el archivo LICENSE que se distribuyó con este código
 * fuente.
 */
/* Traducción al español.
 *
 * Claves, marcadores de posición, conteos y lo que va aquí en general: ver
 * docs/localization.md.
 */

return [
    'frame' => [
        'error'   => 'ERROR: :message',
        'warning' => 'ADVERTENCIA: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'En :file: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => 'Archivo de configuración no encontrado: :path',
        ],
        'unparsable' => [
            'config' => 'El archivo de configuración no pudo ser analizado: :path',
        ],
        'unknown' => [
            'option'  => 'Opción desconocida :flag.',
            'setting' => 'Configuración desconocida ":name" en :file',
        ],
        'needsValue' => [
            'option'  => 'La opción :flag necesita un valor.',
            'setting' => 'La configuración ":name" en :file necesita un valor.',
        ],
        'takesNoValue' => [
            'option' => 'La opción :flag no acepta un valor.',
        ],
        'invalidValue' => [
            'option' => 'Valor inválido ":value" para :flag (permitido: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'El motor unificado necesita :flag de al menos :floor (dado: :given). Por debajo de esto, la ventana de filtrado cae bajo 4 y el índice deja de ser una muestra, por lo que el motor rechaza en lugar de degradarse silenciosamente a un escaneo exhaustivo.',
        ],
        'unwired' => [
            'option' => 'La opción :flag no tiene vinculación de configuración.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Se rechaza escanear la raíz del sistema de archivos (:path). ¿Quisiste decir "./"?',
            'aboveProject'   => 'Se rechaza escanear :path: está por encima de la raíz del proyecto :project.',
        ],
        'nothingToScan' => [
            'files'       => 'No se encontraron archivos para escanear.',
            'afterTriage' => 'No quedaron archivos para escanear después del triaje.',
        ],
        'missingArgument' => [
            'directory' => 'No se especificó un directorio.',
        ],
        'writeFailed' => [
            'report' => 'No se pudo escribir el reporte en :path:detail',
        ],
        'writePartial' => [
            'report' => 'Solo se escribieron de (:written) bytes (:total) del reporte en :path',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'el preajuste ":preset" declara rutas de escaneo (:declared), faltan (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans no puede ver todo el proyecto.',
            'belowRoots'     => '  Cada raíz de escaneo está por debajo de las raíces de autoload de :manifest.',
            'uncoveredRoots' => '  rutas de autoload declaradas en :manifest pero nunca abiertas (:count):',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset detectado — preajuste aplicado (--no-preset para deshabilitar)',
        ],
        'cache' => [
            'hit' => '(acierto de caché)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag está obsoleto; usa --algorithm=unified — el cual aún no reporta cada ubicación que hace el token bag, por lo que este sigue siendo seleccionable)',
        ],
        'incremental' => [
            'combined'    => '(--incremental ignorado en modo combinado)',
            'unsupported' => '(--incremental ignorado: solo los algoritmos rabin-karp y unified tienen un índice incremental)',
            'index'       => '(índice incremental: :reused reutilizados, :scanned escaneados)',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'No se encontraron clones de código.',
            'heading'        => 'Encontrados clones de código (:clones):gapped:reordered, líneas duplicadas (:lines), archivos (:files):',
            'gapped'         => ', inconsistentes (:count)',
            'reorderedCount' => ', reordenados (:count)',
            'reordered'      => '[reordenado]',
            'unreadable'     => 'archivos ilegibles (:count) — en ninguno de los totales:',
            'strataAsserted' => ':asserted afirmados, 0 degradados.',
            'strataSplit'    => ':asserted afirmados, :demoted degradados (:detail).',
            'settled'         => 'lecturas descartadas por estar ya descritas (:count).',
            'unfounded'      => 'hallazgos descartados por no verificados (:count).',
            'hiddenLine'      => ':count de :total hallazgos ocultos bajo la confianza :threshold — --hidden los lista.',
            'hiddenHeading'   => 'Ocultos bajo la confianza :threshold (:count):',
            'coverage'         => 'El :percentage de las líneas analizadas (:lines) es código duplicado.',
            'literals'         => '[los literales difieren (:count)]',
            'confidence'       => 'confianza :score (:terms)',
            'functions'        => 'en :names',
            'sizes'          => 'Líneas por clon: promedio (:average), mayor (:largest).',
        ],
        'ledger' => [
            'line'      => 'Reconocidos: de (:acknowledged) hallazgos (:total) degradados por el libro mayor (ledger); obsoletos (:stale).',
            'staleNote' => 'obsoleto (el código que reconoció ha cambiado): :note',
            'wrote'     => 'reconocimientos escritos (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (falta)',
            'source' => [
                'default'     => 'predeterminado',
                'commandLine' => 'línea de comandos',
                'builtIn'     => '    valores predeterminados integrados',
            ],
            'fallback'    => ' volviendo al valor predeterminado integrado',
            'layers'      => '  Capas, menor precedencia primero:',
        ],
        'run' => [
            'throughput' => ' — archivos (:count) a :rate/s',
            'files'    => ' — archivos (:count)',
            'usage'  => 'Tiempo: :duration, Memoria: :memory MB',
            'banner' => 'phpcpd :version por :author — basado en :origin por :originAuthor.',
        ],
        'scan' => [
            'root'               => 'Raíz de escaneo: :root',
            'roots'              => 'Raíces de escaneo:',
            'count' => [
                'file'       => 'archivos (:count)',
                'directory'  => 'raíces (:count)',
                'pattern'    => 'exclusiones (:count)',
                'unreadable' => 'ilegibles (:count)',
                'generated'  => 'generados (:count)',
            ],
            'counts'             => 'Escaneados :counts',
        ],
        'triage' => [
            'nothing'  => 'Triaje: nada etiquetado; cada archivo es texto de programa.',
            'removed'  => 'Triaje: archivos (:total), eliminados (:removed)',
            'labelled' => 'Triaje: archivos (:total), etiquetados (:labelled), ninguno eliminado',
        ],
        'orphan' => [
            'none'         => 'No se encontraron símbolos huérfanos (símbolos (:symbols) en archivos (:files)).',
            'found'        => 'símbolos huérfanos (:count):',
            'possible'     => 'posibles huérfanos (:count) — revisar antes de eliminar:',
            'advisory'     => 'símbolos huérfanos (:count) — informativo, no afecta el código de salida:',
            'notShown'     => 'no se muestran más hallazgos de huérfanos (:count) — ejecuta --orphans para revisarlos.',
            'suppressed'   => 'Suprimidos (:count): :census',
            'explainHint'  => '  → --explain para listarlos',
            'wholeFile'    => '    ⤷ todo el archivo está desconectado — ningún símbolo declarado aquí es referenciado',
            'supersededBy' => '    ⤷ parece una copia reemplazada de :name',
            'summary'      => 'símbolos (:symbols) escaneados en archivos (:files); huérfanos (:orphaned), posibles (:possible), suprimidos (:suppressed), planeados (:planned).',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'fuera del conjunto de archivos que las propias exclusiones predeterminadas de este proyecto dejan — un árbol generado, que puede no atestiguar la vinculación (wiring)',
            'declaredHere'  => 'símbolos declarados aquí (:count), ninguno referenciado en ninguna parte del proyecto',
            'foreignNs'     => 'declara :namespaces — un espacio de nombres que ningún composer.json superior declara, fuera de cada directorio que vinculan',
        ],
        'role' => [
            'noStatements' => 'no hay declaraciones de nivel superior pasado el preámbulo',
            'coupled'      => ':registrations de :statements declaraciones de nivel superior son expresiones de registro, pero comparten una variable',
            'independent'  => ':registrations de :statements declaraciones de nivel superior son expresiones de registro independientes del flujo de datos',
        ],
        'orphan' => [
            'guard'          => 'declarado dentro de una guardia de existencia — polyfill o shim de compatibilidad',
            'entrypoint'     => 'declarado en :namespace — invocado por convención del framework',
            'partialProject' => '  Un símbolo se considera muerto cuando *nada* lo referencia, lo cual es una afirmación sobre todo el
  proyecto. Código fuera de este escaneo aún puede referenciar lo que se reporta aquí.',
            'evidence' => [
                'nameAt'   => 'el nombre aparece en',
                'loopAt'   => 'descubierto por el bucle en',
                'suffixAt' => 'sufijo declarado en',
                'namedIn'  => 'nombrado en',
            ],
            'plannedServed'  => 'referenciado ahora — @phpcpd-planned ha cumplido su propósito y puede ser eliminado',
            'manifest'       => 'declarado en un punto de entrada autoload.files de composer',
            'foreignNs'      => 'declarado fuera de los espacios de nombres propios del proyecto (shim de compatibilidad)',
            'fixture'        => 'fixture de prueba — cargado por ruta o nombrado como una cadena, nunca referenciado',
            'discovery'      => 'descubierto por un escaneo de directorio — instanciado desde su nombre de archivo detrás de class_exists',
            'convention'     => 'clase compañera — :base usa :trait, el cual resuelve este nombre por sufijo en tiempo de ejecución',
            'interface'      => 'nunca referenciado (interfaz — puede implementarse fuera del conjunto escaneado)',
            'trait'          => 'nunca referenciado (trait — puede usarse por clases fuera del conjunto escaneado)',
            'abstract'       => 'nunca referenciado (abstracta — puede extenderse fuera del conjunto escaneado)',
            'inString'       => 'nunca referenciado en el código; el nombre aparece en un literal de cadena (posible uso dinámico)',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => 'Declarado condicionalmente (polyfill / shim de compatibilidad)',
            'fixtures'    => 'Fixtures de prueba (cargados por ruta o por nombre)',
            'config'      => 'Registrado en un archivo de configuración',
            'template'    => 'Referenciado desde una plantilla (blade / twig / latte)',
            'manifest'    => 'Referenciado desde composer.json',
            'namespace'   => 'Declarado fuera de los espacios de nombres propios del proyecto (shim de compatibilidad)',
            'keep'        => 'Marcado para conservar (@api / @phpcpd-keep)',
            'entrypoint'  => 'Puntos de entrada del framework (atributo / clase de prueba)',
            'discovery'   => 'Descubierto por un escaneo de directorio (instanciado desde su nombre de archivo)',
            'convention'  => 'Clase compañera nombrada por convención (sufijo declarado por un trait)',
            'planned'     => 'Planeado, aún no vinculado',
            'none'        => 'No se encontró referencia',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Pasa --allow-root-scan si realmente te referías a todo el sistema de archivos.',
            'allowOutside' => 'Pasa --allow-root-scan para escanear fuera del proyecto de todos modos.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Escanea la raíz del proyecto para obtener un resultado sobre el cual valga la pena actuar.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard actúa sobre las etiquetas; --no-triage omite la etapa)',
            'explain' => '  (--explain enumera cada archivo y la evidencia a favor o en contra)',
        ],
        'clone' => [
            'gapped'  => 'Clon casi exacto — considera parametrizar la parte divergente o alinear ambas copias.',
            'demoted' => 'Degradado como :stratum — esta es la forma que describe el estrato, así que extráelo solo si la repetición no es el punto.',
            'extract' => 'Considera extraer las líneas compartidas en un método, clase o trait reutilizable.',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => 'Uso:',
            'invocation' => '  phpcpd [opciones] <directorio>',
        ],
        'group' => [
            'selecting' => 'Opciones para la selección de archivos',
            'orphans'   => 'Detección de huérfanos (código muerto)',
            'analysing' => 'Opciones para el análisis de archivos',
            'general'   => 'Opciones generales',
            'reporting' => 'Opciones para la generación de reportes',
            'ci'        => 'Opciones para integración con CI',
        ],
        'option' => [
            'suffix'              => 'Incluir archivos con nombres que terminan en <suffix> (predeterminado: :default; repetible)',
            'exclude'             => 'Excluir archivos con <path> en su ruta (repetible)',
            'preset'              => 'Aplicar un preajuste de framework (ej. laravel): establece rutas, sufijos y exclusiones sensibles',
            'triage'              => 'Ejecutar la Etapa 0 de triaje de corpus antes de la detección (activado por defecto; esto lo solicita explícitamente)',
            'no_triage'           => 'Omitir la Etapa 0 por completo: ningún archivo se etiqueta como desconectado, sombreado, de vendor o derivado',
            'triage_posture'      => 'Lo que hace el triaje con un archivo que etiqueta: descartarlo del escaneo (predeterminado), o etiquetarlo y nada más',
            'no_preset'           => 'No auto-aplicar un preajuste de framework cuando se detecta uno (la detección se anuncia a sí misma; --preset= lo anula)',
            'no_default_excludes' => 'Escanear árboles generados y de caché también (vendor, node_modules, .phpstan.cache, build, ...), que se omiten por defecto',
            'allow_root_scan'     => 'Permitir una raíz de escaneo de / o una raíz por encima del composer.json más cercano (rechazado por defecto: `phpcpd /` casi siempre es un error tipográfico de `phpcpd ./`)',
            'orphans'             => 'Detectar símbolos huérfanos (clases, interfaces, traits, enums, funciones no referenciadas) en lugar de clones',
            'no_suppress'         => 'Desactivar reglas de supresión por nombre, separadas por comas, o "all" (:rules)',
            'fail_on'             => 'Niveles de resultado que hacen que la ejecución salga sin cero, separados por comas (predeterminado: :default)',
            'explain'             => 'Listar cada símbolo suprimido y la regla que lo suprimió, en lugar de solo contarlos',
            'rk'                  => 'Solo Rabin-Karp (clones exactos/Tipo-1; más rápido, sin detección de reordenamiento). Por defecto ejecuta tanto Rabin-Karp como TokenBag.',
            'min_lines'           => 'Número mínimo de líneas idénticas (predeterminado: :default)',
            'min_tokens'          => 'Número mínimo de tokens idénticos (predeterminado: :default)',
            'language'            => 'Idioma para el reporte (predeterminado: :default)',
            'verbose'             => 'Imprimir el código duplicado para cada clon',
            'algorithm'           => 'Anulación para un solo algoritmo (rabin-karp | tokenbag | unified)',
            'min_similarity'      => 'Umbral de solapamiento para TokenBag (predeterminado: :default)',
            'raw'                   => 'Comparar el texto en bruto: los identificadores también deben coincidir (desactiva la normalización por defecto)',
            'fuzzy'                 => 'Normalización ciega a los nombres: como la de por defecto pero sin el anclaje de tipos (investigación; E2 la midió dominada)',
            'type_anchored'         => 'Mantener concretas las palabras clave de tipo bajo normalización (activo por defecto; --fuzzy lo desactiva)',
            'min_confidence'        => 'Listar solo los hallazgos que el modelo puntúa en <log-odds> o por encima; el resto se cuentan y se leen con --hidden, nunca se descartan, y siguen condicionando --fail-on',
            'hidden'                => 'Listar los hallazgos que --min-confidence retuvo',
            'cache'                 => 'Cachear resultados en \'.phpcpd-cache/\' — un acierto exige que ningún fichero haya cambiado, así que sirve para repetir un commit, no para el siguiente',
            'acknowledged'        => 'Leer un libro mayor de reconocimientos commiteado desde <file>: la duplicación listada es degradada, nunca ocultada, y las entradas cuyo código cambió expiran y son reportadas',
            'write_acknowledged'  => 'Escribir los hallazgos de esta ejecución en <file> como un libro mayor de reconocimientos, para revisión y commit',
            'log_pmd'             => 'Escribir registro en formato XML PMD-CPD en <file>',
            'log_json'            => 'Escribir registro en formato JSON en <file>',
            'log_sarif'           => 'Escribir registro en formato SARIF 2.1.0 en <file> (para GitHub Code Scanning)',
            'cache_dir'           => 'Leer/escribir caché desde <path> (implica --cache; anula el directorio predeterminado)',
            'incremental'           => 'Índice incremental por archivo: vuelve a tokenizar solo los archivos modificados (rabin-karp o unified, no el predeterminado combinado; usa el directorio de caché)',
            'config'              => 'Leer configuración desde <file> (predeterminado: ./phpcpd.ini cuando está presente); las claves son los nombres largos de las opciones',
            'show_config'         => 'Imprimir la configuración en vigor, de dónde provino cada una, y salir',
            'no_config'           => 'Ignorar ./phpcpd.ini',
            'help'                => 'Imprimir esta ayuda',
            'version'             => 'Imprimir información de versión',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'libro mayor de reconocimientos (acknowledgment ledger) de phpcpd-next',
            'what'  => 'Cada línea registra una duplicación que este proyecto ha revisado y decidido
mantener. Un hallazgo reconocido es DEGRADADO, nunca ocultado: todavía
se reporta, todavía se cuenta, y todavía condiciona el código de salida.',
            'key'   => 'La clave es el contenido de cada lado de la duplicación, convertido en hash — no una ruta
y no un número de línea. Por lo tanto, editar cualquiera de las copias hace expirar la entrada y el
hallazgo se afirma nuevamente, mientras que mover el código no cambia nada. Una entrada
que ya no coincide con nada se reporta como obsoleta para que pueda ser eliminada.',
            'note'  => 'El texto después de la tabulación es una nota humana. Nunca se usa para coincidencias.',
        ],
    ],
];
