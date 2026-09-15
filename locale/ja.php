<?php

declare(strict_types=1);
/*
 * このファイルは PhpcpdNext の一部です。
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * 完全な著作権およびライセンス情報については、
 * このソースコードとともに配布された LICENSE ファイルをご覧ください。
 */
/*
 * 日本語翻訳。
 *
 * キー、プレースホルダー、カウント、およびここに一般的に属するもの：
 * docs/localization.md を参照してください。
 */

return [
    'frame' => [
        'error'   => 'エラー: :message',
        'warning' => '警告: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => ':file 内: :message',
    ],
    'refuse' => [
        'notFound' => [
            'config' => '設定ファイルが見つかりません: :path',
        ],
        'unparsable' => [
            'config' => '設定ファイルを解析できませんでした: :path',
        ],
        'unknown' => [
            'option'  => '不明なオプション :flag です。',
            'setting' => ':file 内の不明な設定 ":name"',
        ],
        'needsValue' => [
            'option'  => 'オプション :flag には値が必要です。',
            'setting' => ':file 内の設定 ":name" には値が必要です。',
        ],
        'takesNoValue' => [
            'option' => 'オプション :flag は値を受け取りません。',
        ],
        'invalidValue' => [
            'option' => ':flag の無効な値 ":value"（許可されている値: :allowed）。',
        ],
        'belowFloor' => [
            'minTokens' => '統合エンジン（unified engine）には少なくとも :floor の :flag が必要です（指定値: :given）。これを下回ると、winnow ウィンドウが 4 を下回り、インデックスがサンプルではなくなるため、エンジンは網羅的なスキャンへとひっそりとフォールバックするのではなく、サービスを拒否します。',
        ],
        'unwired' => [
            'option' => 'オプション :flag には設定のバインドがありません。',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'ファイルシステムルート（:path）のスキャンを拒否します。もしかして "./" ですか？',
            'aboveProject'   => ':path のスキャンを拒否します: プロジェクトのルート :project よりも上にあります。',
        ],
        'nothingToScan' => [
            'files'       => 'スキャンするファイルが見つかりませんでした。',
            'afterTriage' => 'トリアージ後、スキャンするファイルが残っていません。',
        ],
        'missingArgument' => [
            'directory' => 'ディレクトリが指定されていません。',
        ],
        'writeFailed' => [
            'report' => ':path にレポートを書き込めませんでした: :detail',
        ],
        'writePartial' => [
            'report' => ':path への書き込みが不完全です: バイト（:written）／（:total）。',
        ],
    ],
    'warn' => [
        'preset' => [
            'missingPaths' => 'プリセット ":preset" がスキャンパス（:declared）を宣言していますが、以下が不足しています（:missing）: :paths。',
        ],
        'orphan' => [
            'partialProject' => '--orphans はプロジェクト全体を確認できません。',
            'belowRoots'     => '  すべてのスキャンルートは :manifest のオートロードルートの下にあります。',
            'uncoveredRoots' => '  :manifest で宣言されているが一度もオープンされていないオートロードパス（:count）:',
        ],
    ],
    'notice' => [
        'preset' => [
            'detected' => ':preset が検出されました — プリセットが適用されました（無効にするには --no-preset）',
        ],
        'cache' => [
            'hit' => '（キャッシュヒット）',
        ],
        'engine' => [
            'tokenbagDeprecated' => '（--algorithm=tokenbag は非推奨です。--algorithm=unified を使用してください — トークンバッグが報告するすべての場所をまだ報告しないため、この選択肢は残されています）',
        ],
        'incremental' => [
            'combined'    => '（結合モードでは --incremental は無視されます）',
            'unsupported' => '（--incremental は無視されます: rabin-karp および unified アルゴリズムのみがインクリメンタルインデックスを持っています）',
            'index'       => '（インクリメンタルインデックス: :reused 再利用、 :scanned スキャン済み）',
        ],
    ],
    'report' => [
        'clones' => [
            'none'           => 'コードクローンは見つかりませんでした。',
            'heading'        => 'コードクローン（:clones）:gapped:reordered、重複行（:lines）、ファイル（:files）:',
            'gapped'         => '、不一致（:count）',
            'reorderedCount' => '、並べ替え (:count)',
            'reordered'      => '[並べ替え]',
            'unreadable'     => '読み取り不可能なファイル（:count） — 合計に含まれません:',
            'strataAsserted' => 'アサートされた数: :asserted、ダウングレード: 0。',
            'strataSplit'    => 'アサートされた数: :asserted、ダウングレード: :demoted（:detail）。',
            'settled'         => 'すでに記述済みとして破棄された読み取り (:count)。',
            'unfounded'      => '検証できず破棄された検出 (:count)。',
            'hiddenLine'      => '信頼度 :threshold 未満のため :total 件中 :count 件を非表示 — --hidden で一覧できます。',
            'hiddenHeading'   => '信頼度 :threshold 未満で非表示 (:count):',
            'coverage'         => 'スキャンした行（:lines）の :percentage が重複コードです。',
            'literals'         => '[リテラルが異なります（:count）]',
            'confidence'       => '信頼度 :score（:terms）',
            'functions'        => ':names 内',
            'sizes'          => 'クローンの行数: 平均（:average）、最大（:largest）。',
        ],
        'ledger' => [
            'line'      => '台帳による承認: 格下げ（:acknowledged）／検出（:total）、失効（:stale）。',
            'staleNote' => '期限切れ（確認されたコードが変更されています）: :note',
            'wrote'     => '確認情報を書き込みました（:count）: :path',
        ],
        'config' => [
            'missingPath' => ':path（欠落）',
            'source' => [
                'default'     => 'デフォルト',
                'commandLine' => 'コマンドライン',
                'builtIn'     => '    組み込みデフォルト',
            ],
            'fallback'    => ' 組み込みデフォルトにフォールバックします',
            'layers'      => '  レイヤー、優先度が低い順:',
        ],
        'run' => [
            'throughput' => ' — ファイル（:count）毎秒 :rate 件',
            'files'    => ' — ファイル（:count）',
            'usage'  => '時間: :duration、メモリ: :memory MB',
            'banner' => 'phpcpd :version（作者: :author） — :origin（原作者: :originAuthor）をベースにしています。',
        ],
        'scan' => [
            'root'               => 'スキャンルート: :root',
            'roots'              => 'スキャンルート:',
            'count' => [
                'file'       => 'ファイル（:count）',
                'directory'  => 'ルート（:count）',
                'pattern'    => '除外（:count）',
                'unreadable' => '読み取り不可（:count）',
                'generated'  => '生成済み（:count）',
            ],
            'counts'             => ':counts をスキャンしました',
        ],
        'triage' => [
            'nothing'  => 'トリアージ: ラベル付けされたものはありません。すべてのファイルがプログラムテキストです。',
            'removed'  => 'トリアージ: ファイル（:total）、削除（:removed）',
            'labelled' => 'トリアージ: ファイル（:total）、ラベル付け（:labelled）、削除なし',
        ],
        'orphan' => [
            'none'         => '孤立したシンボルは見つかりませんでした — シンボル（:symbols）、ファイル（:files）。',
            'found'        => '孤立シンボル（:count）:',
            'possible'     => '可能な孤立シンボル（:count） — 削除する前に確認してください:',
            'advisory'     => '孤立シンボル（:count） — 警告（終了コードには影響しません）:',
            'notShown'     => 'その他の孤立シンボルの検出結果は表示されていません（:count） — --orphans を実行して確認してください。',
            'suppressed'   => '抑制済み（:count）: :census',
            'explainHint'  => '  → --explain で一覧表示',
            'wholeFile'    => '    ⤷ ファイル全体が未接続です — ここで宣言されているシンボルは一切参照されていません',
            'supersededBy' => '    ⤷ :name の置き換えられたコピーのように見えます',
            'summary'      => 'スキャン済みシンボル（:symbols）、ファイル（:files）。孤立（:orphaned）、可能性あり（:possible）、抑制済み（:suppressed）、予定（:planned）。',
        ],
    ],
    'explain' => [
        'triage' => [
            'generatedTree' => 'このプロジェクト自体のデフォルト除外が残すファイルセットの外側 — 配線（wiring）が証明されていない可能性がある生成されたツリーです',
            'declaredHere'  => 'ここで宣言されているシンボル（:count）。プロジェクト内のどこからも参照されていません',
            'foreignNs'     => '宣言された :namespaces — その上のどの composer.json でも宣言されていない名前空間であり、それらが配線するどのディレクトリの外側にあります',
        ],
        'role' => [
            'noStatements' => '前文以降にトップレベルの文がありません',
            'coupled'      => ':statements 個のトップレベルの文のうち :registrations 個が登録式ですが、それらは変数を共有しています',
            'independent'  => ':statements 個のトップレベルの文のうち :registrations 個がデータフロー非依存の登録式です',
        ],
        'orphan' => [
            'guard'          => '存在ガード内で宣言されています — ポリフィルまたは互換性シム',
            'entrypoint'     => ':namespace で宣言されています — フレームワークの規約によって呼び出されます',
            'partialProject' => '  何も参照していないシンボルはデッドコードと呼ばれます。これはプロジェクト全体に関する主張です。
  このスキャンの外側のコードが、ここで報告されているものを参照している場合があります。',
            'evidence' => [
                'nameAt'   => '名前の出現場所: ',
                'loopAt'   => 'ループによる検出場所: ',
                'suffixAt' => 'サフィックスの宣言場所: ',
                'namedIn'  => '名前の定義: ',
            ],
            'plannedServed'  => '現在参照されています — @phpcpd-planned はその役割を果たしたため削除できます',
            'manifest'       => 'composer の autoload.files エントリポイントで宣言されています',
            'foreignNs'      => 'プロジェクト自身の名前空間の外側で宣言されています（互換性シム）',
            'fixture'        => 'テストフィクスチャ — パスで読み込まれるか文字列として名前が付けられており、直接参照されることはありません',
            'discovery'      => 'ディレクトリのスキャンによって検出されました — class_exists の後ろでファイル名からインスタンス化されます',
            'convention'     => 'コンパニオンクラス — :base は :trait を使用しており、実行時にサフィックスによってこの名前を解決します',
            'interface'      => '参照されていません（インターフェース — スキャンされたセットの外側で実装されている可能性があります）',
            'trait'          => '参照されていません（トレイト — スキャンされたセットの外側のクラスで使用されている可能性があります）',
            'abstract'       => '参照されていません（抽象クラス — スキャンされたセットの外側で拡張されている可能性があります）',
            'inString'       => 'コード内で参照されていません。名前が文字列リテラルに出現します（動的使用の可能性があります）',
        ],
    ],
    'label' => [
        'orphan' => [
            'conditional' => '条件付きで宣言（ポリフィル / 互換性シム）',
            'fixtures'    => 'テストフィクスチャ（パスまたは名前で読み込み）',
            'config'      => '設定ファイルで登録',
            'template'    => 'テンプレートから参照（blade / twig / latte）',
            'manifest'    => 'composer.json から参照',
            'namespace'   => 'プロジェクト自身の名前空間の外側で宣言（互換性シム）',
            'keep'        => '保持としてマーク（@api / @phpcpd-keep）',
            'entrypoint'  => 'フレームワークのエントリポイント（アトリビュート / テストクラス）',
            'discovery'   => 'ディレクトリのスキャンで検出（ファイル名からインスタンス化）',
            'convention'  => '規約によって命名されたコンパニオンクラス（トレイトによって宣言されたサフィックス）',
            'planned'     => '予定されていますが、まだ接続されていません',
            'none'        => '参照が見つかりません',
        ],
    ],
    'advise' => [
        'scan' => [
            'allowRoot'    => 'ファイルシステム全体を対象にしている場合は、--allow-root-scan を渡してください。',
            'allowOutside' => 'プロジェクトの外側をスキャンするには、--allow-root-scan を渡してください。',
        ],
        'orphan' => [
            'scanProjectRoot' => '  対処価値のある結果を得るために、プロジェクトルートをスキャンしてください。',
        ],
        'triage' => [
            'posture' => '  （--triage-posture=discard はラベルに対して作用します。--no-triage はこのステージをスキップします）',
            'explain' => '  （--explain は各ファイルとそれを支持または否定する証拠を一覧表示します）',
        ],
        'clone' => [
            'gapped'  => 'ニアミス・クローン — 異なっている部分をパラメータ化するか、両方のコピーを揃えることを検討してください。',
            'demoted' => ':stratum としてダウングレードされました — これは該当する階層が記述する形式であるため、重複自体が目的でない場合にのみ抽出してください。',
            'extract' => '共有されている行を、再利用可能なメソッド、クラス、またはトレイトに抽出することを検討してください。',
        ],
    ],
    'help' => [
        'frame' => [
            'usage'      => '使用法:',
            'invocation' => '  phpcpd [オプション] <ディレクトリ>',
        ],
        'group' => [
            'selecting' => 'ファイル選択オプション',
            'orphans'   => '孤立コード（デッドコード）検出',
            'analysing' => 'コード解析オプション',
            'general'   => '一般オプション',
            'reporting' => 'レポート生成オプション',
            'ci'        => 'CI 統合オプション',
        ],
        'option' => [
            'suffix'              => '名前が <suffix> で終わるファイルを含める（デフォルト: :default; 複数指定可）',
            'exclude'             => 'パスに <path> を含むファイルを除外する（複数指定可）',
            'preset'              => 'フレームワークのプリセットを適用する（例: laravel）。適切なパス、サフィックス、除外を設定します',
            'triage'              => '検出の前にステージ 0 のコーパストリアージを実行する（デフォルトで有効。これを明示的に要求します）',
            'no_triage'           => 'ステージ 0 を完全にスキップする: ファイルに未接続、シャドウイング済み、ベンダー製、または派生元のラベル付けを行わない',
            'triage_posture'      => 'ラベル付けされたファイルに対するトリアージの動作: スキャンから破棄する（デフォルト）、またはラベル付けのみ行う',
            'no_preset'           => 'フレームワークのプリセットが検出されたときに自動的に適用しない（検出自体は通知されます; --preset= で上書きされます）',
            'no_default_excludes' => 'デフォルトでスキップされる生成ツリーやキャッシュツリー（vendor, node_modules, .phpstan.cache, build など）もスキャンする',
            'allow_root_scan'     => 'スキャンルートとして / または最も近い composer.json より上のルートを許可する（デフォルトでは拒否: `phpcpd /` はほとんどの場合 `phpcpd ./` のタイプミスです）',
            'orphans'             => 'クローンの代わりに孤立シンボル（参照されていないクラス、インターフェース、トレイト、enum、関数）を検出する',
            'no_suppress'         => '名前で指定した抑制ルールを無効にする（カンマ区切り、または "all"）（:rules）',
            'fail_on'             => '実行を非ゼロのコードで終了させる結果の階層（カンマ区切り）（デフォルト: :default）',
            'explain'             => '単に数を数えるだけでなく、抑制されたすべてのシンボルとそれを抑制したルールを一覧表示する',
            'rk'                  => 'Rabin-Karp のみ（厳密/タイプ 1 クローン。より高速で、再順序検出はありません）。デフォルトでは Rabin-Karp と TokenBag の両方を実行します。',
            'min_lines'           => '同一行の最小数（デフォルト: :default）',
            'min_tokens'          => '同一トークンの最小数（デフォルト: :default）',
            'language'            => 'レポートの言語（デフォルト: :default）',
            'verbose'             => '各クローンの重複コードを出力する',
            'algorithm'           => '単一アルゴリズムのオーバーライド（rabin-karp | tokenbag | unified）',
            'min_similarity'      => 'TokenBag の重複閾値（デフォルト: :default）',
            'raw'                   => '生のテキストで照合する: 識別子も一致している必要があります (既定の正規化を無効化)',
            'fuzzy'                 => '名前を無視する正規化: 既定と同様だが型アンカーなし (研究用途。E2 で劣位と測定)',
            'type_anchored'         => '正規化しても型キーワードを具体的なまま保つ (既定で有効。--fuzzy で無効化)',
            'min_confidence'        => 'モデルの評価が <log-odds> 以上の検出のみを一覧表示する。残りも計上され --hidden で読め、破棄されず、--fail-on にも引き続き影響します',
            'hidden'                => '--min-confidence が抑えた検出を一覧表示する',
            'cache'                 => '結果を \'.phpcpd-cache/\' にキャッシュする — ヒットには全ファイルが未変更である必要があるため、次のコミットではなく同一コミットの再実行に役立ちます',
            'acknowledged'        => 'コミットされた確認レジャーを <file> から読み取る: リストされた重複はダウングレードされ、決して隠されません。コードが変更されたエントリは期限切れとなり報告されます',
            'write_acknowledged'  => '今回の実行の検出結果をレビューおよびコミット用の確認レジャーとして <file> に書き込む',
            'log_pmd'             => 'PMD-CPD XML 形式のログを <file> に書き込む',
            'log_json'            => 'JSON 形式のログを <file> に書き込む',
            'log_sarif'           => 'SARIF 2.1.0 形式のログを <file> に書き込む（GitHub Code Scanning 用）',
            'cache_dir'           => 'キャッシュの読み取り/書き込みを <path> から行う（--cache を内包し、デフォルトディレクトリを上書きします）',
            'incremental'           => 'ファイル単位の増分インデックス: 変更されたファイルのみ再トークン化します（rabin-karp または unified。統合デフォルトでは使えません。キャッシュディレクトリを使用します）',
            'config'              => '<file> から設定を読み込む（デフォルト: 存在する場合は ./phpcpd.ini）。キーは長いオプション名です',
            'show_config'         => '有効な設定とその由来を出力して終了する',
            'no_config'           => './phpcpd.ini を無視する',
            'help'                => 'このヘルプを表示する',
            'version'             => 'バージョン情報を表示する',
        ],
    ],
    'document' => [
        'ledger' => [
            'title' => 'phpcpd-next 確認レジャー（acknowledgment ledger）',
            'what'  => '各行は、このプロジェクトが検討し、そのまま維持することを決定した 1 つの重複を記録します。
確認された検出結果はダウングレードされますが決して隠されません。依然として報告され、
カウントされ、終了コードに影響を与えます。',
            'key'   => 'キーは、パスでも行番号でもなく、重複の両側のコンテンツをハッシュ化したものです。
そのため、どちらかのコピーを編集するとエントリが期限切れになり検出結果が再度アサートされますが、
コードを移動しても何も変わりません。
どの内容とも一致しなくなったエントリは、削除できるように期限切れとして報告されます。',
            'note'  => 'タブの後のテキストは人間のためのメモです。マッチングには一切使用されません。',
        ],
    ],
];
