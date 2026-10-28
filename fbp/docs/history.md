# app-framework5 History

## 2026-10-28 Excel由来ノートの標準一覧への移行同期

- 本番で検索＋一覧へ切り替えたExcel由来ノートのパターンを取り込む専用CLIを追加。同一ID・内部名と本番列定義・列順の一致を検査し、列設定・業務データを保持したままlist_typeだけを反映する。通常のDeveloper Modeの定義変更保護は維持する。列設定取得APIは検索＋一覧への切替後も既存設定を取得できる。
- 隔離した取込み試験（不一致拒否・再実行・列保持・通常更新拒否）と既存Developer Mode保護27項目、同期スクリプト4試験が成功。ff2d7b4を全配布対象へ反映し、設定再生成66件の成功と3台の配布コード一致を確認。初回の中断を完了扱いにせず、同じ版を再配布して正常終了を確認した。

## 2026-10-28 textarea表示のHTMLエスケープを修正

- Task 4736の調査で、自動エスケープ有効時の`escape|nl2br nofilter`が未エスケープ出力になることを確認。標準の一覧・確認表示を`escape:"force"`へ変更し、URLリンク付きtextareaにもリンク生成前のエスケープを追加。同じ組合せのcronログ・MCP説明・設定説明と、推奨記法のコメントを修正した。
- 専用テスト環境で修正前のHTML描画を再現し、修正後は標準テンプレート2種と関連表示の21ケースが成功。HTMLの無害化、改行・日本語・引用符・既存エンティティ・URLクエリ・空値の表示を確認。framework 999369eを全配布対象3台へ本番反映し、設定再生成66件の成功、各サーバーの配布元コード一致とApache稼働を確認。Task 4736のテスト環境にも同期し、修正完了コメントを投稿して自動実行を再開。アプリ機能全体の完了は別途検証する。

## 2026-10-27 PDFの日本語・英文混在時の折り返しを修正

- 共通MultiCellが、行幅超過時に以前の英文スペースまで戻って大きな空きを残す問題を修正。日本語の文字間も改行候補として扱い、新しい有効な境界を優先する。日本語の句読点・括弧・結合文字、英文の単語境界と明示改行を考慮し、表セルの描画・行高・改ページにも同じ処理を使う。
- 専用テスト環境で修正前の契約書前文の不自然な折返しを再現し、修正後は英文のみ／日本語／混在文／両端揃え／非Unicodeフォント／結合文字／表の複数ページ表示など150項目が成功。対象アプリの契約書レンダラーと最新データでもPDFを生成し、前文が行幅に沿って折り返されることを確認。framework 7c9afa0を全配布対象3台へ本番反映し、配布処理正常終了・設定再生成66件・各サーバーの配布コード一致を確認。利用者も本番PDFの整形改善を確認済み。

## 2026-10-25 公開minimal画面の通知スタイルを補完

- 公開minimal用CSSへ通知本文の余白20px・角丸10px・中央寄せを追加し、管理画面と同じ表示に統一。
- 専用検証環境のminimal CSSと対象アプリの公開画面で、通知の余白・角丸・中央寄せをブラウザーの計算済みスタイルと目視で確認。framework 7c9afa0でPDF折返し修正と合わせて全配布対象へ本番反映し、各サーバーのCSS一致を確認。

## 2026-10-25 タイトルコピー成功表示を吹き出しへ変更

- コピー成功時は全体通知ではなく、クリックしたタイトルのすぐ上へ矢印付きの「コピーしました」を表示。約2秒で消え、次のポインター操作・スクロール・リサイズでも閉じる。コピー失敗時の案内は維持する。
- 管理画面と幅375pxの公開minimal画面で、タイトル上8pxの配置・横はみ出しなし・実クリップボード内容一致・表示の重複なし・時間経過とダイアログを閉じた際の消去を確認。framework 6929f25を全配布対象へ本番反映し、本番配信コードでも実クリップボード内容一致・タイトル上8pxの吹き出し表示・自動消去を確認。

## 2026-10-25 廃止済み画面デバッグAPIの互換対応

- ServerError #670（公開予定画面）・#669（公開サポート画面）は、アプリ側に残るregister_screen_debug_context呼び出しとframeworkからの関数削除が原因。互換関数を復元し、リクエスト収集・セッション保存・ログ保存をせず空文字を返す。
- 専用テスト環境のmethod_callで空文字との一致とPHP構文を確認。framework b3b5704を全配布対象へ本番反映し、#670・#669の記録された公開URLがHTTP 200で画面を返し、未定義関数エラーが出ないことを確認。確認後も両エラーの最終発生日時・回数に増加なし。デバッグ情報の新規収集・保存は停止を維持する。

## 2026-10-25 ダイアログタイトルから画面名をコピー

- タイトルのクリック／Enter／Spaceで「[メニュー名->ダイアログ名]」をクリップボードへコピーし、結果を通知する。開いた時点のメニュー名を保持し、子ダイアログへ引き継ぐ。メニューがない画面は「[ダイアログ名]」とする。
- タイトル文字の選択操作では自動コピーせず、バーの余白からの移動を維持。Clipboard APIの失敗時は代替コピーを試し、コピーできない場合は手動操作を案内する。
- テスト環境で実クリップボードの内容一致、メニュー変更後と子ダイアログの文脈保持、文字選択・移動・キーボード・代替コピー・失敗表示・閉じる操作を確認。framework 9c80a6cを全配布対象へ本番反映し、本番配信コードでもタイトルクリックによる「[顧客管理->編集]」のコピーと閉じる操作を確認。

## 2026-10-25 ダイアログ移動ハンドルの修正

- タイトルバー全体の移動停止を訂正。タイトル文字と操作ボタンはドラッグ対象から除外し、バーの余白から移動できるよう復元。タイトル要素を文字幅に収め、文字選択と移動できる領域を分けた。
- 管理画面・公開minimal双方で、マウスによるタイトル文字選択、文字部分では移動しないこと、余白からのドラッグ移動、閉じる操作を確認。
- framework 8dd80caを全配布対象へ本番反映。本番配信コードでも上記操作を確認。配布中の一時的なCPU上昇と、配布終了後の負荷低下を確認した。

## 2026-10-25 画面デバッグ保存の廃止とダイアログタイトル選択

- screenshot_monitorアイコン、表示時のGET／POST等のデバッグ情報収集、画面IDの新規保存を廃止。旧capture入口はHTTP 410で拒否する。既存ログの認証付き参照は維持する。
- 共通ダイアログのタイトルから移動ハンドルを削除し、管理画面・公開minimal双方で文字選択できるスタイルへ変更。
- 専用テスト環境へ同期し、ブラウザーでタイトル文字選択・移動しないこと・閉じる操作・アイコン非表示・旧captureの410を確認。PHP／JavaScript構文確認済み。framework beaaf7cを全配布対象へ本番反映し、3台の配布ファイルのハッシュ一致と代表本番アプリの旧captureの410を確認。
- ローカルScreenLog取得スクリプトとtaskの呼び出し口・作業状況表示の登録も廃止し、共通手順の旧取得案内を削除。

## 2026-10-22 グラフ・vCard QR・Vimeoの再利用テンプレート

- 複合2軸グラフ／等間隔の回帰線、vCard 3.0のQR付きPDF、Vimeoのアップロード／紐付け／再生／確認付き削除をSkill資産へ整理。manifest、上書き拒否インストーラー、参照手順、READMEの生成指示を追加し、Dashboard・PDF・Mediaの各Skillから案内する。
- 45項目のオフライン検証と3件の導入・上書き拒否を確認。専用テスト環境で実グラフ描画、実ボタンのPDF取得、未ログイン拒否、QR復号と原文の一致を確認。Vimeoは初期拒否と所有者・版・nonce・HTTP失敗をモック確認し、実アカウントへの操作は未実施。既存アップローダーのブラウザーへのトークン送信と、組込み時に必要な認可・保存フックを明記した。

## 2026-10-22 タブ別セッションの保持上限

- 画面用のWID配下の配列を最大20件・シリアライズ容量合計4MiBを目安に整理。最終利用時刻の古い順に削除し、現在処理中の画面は保護。新規タブへの引継ぎ元はコピー完了まで保持。セッション共通キーとScreenLogの記録ファイルは削除しない。
- 削除済み画面からのappcon（ajax-link等）通信はアプリ処理前に止め、ブラウザー標準の確認ダイアログで再読み込みを案内。画像取得では削除済みの印を解除せず、通常のpage表示で解除。未保存入力は再読み込みで失われる。削除履歴は直近256画面に制限し、それ以前の画面まで厳密な失効判定は行わない。
- 単独で上限を超える現在画面は保持するため、容量上限は絶対保証ではない。session_start前の読み込みOOMや、画面外キーの肥大を復旧する機能ではない。古い既存データに利用時刻がない場合は保持順を使う。上限はWindowSessionの定数で定義。
- 専用テスト環境で最終利用順・件数/容量・現在画面/共通キー保護・削除印・再表示・履歴上限の8項目、PHP/JS構文、ブラウザーで削除済みAjaxの中断・確認ダイアログ・再読み込み後の解除を確認。テスト環境へ反映後、本番リリースの指示によりframework `2d9718c`を配布対象3台へ反映し、リリース正常終了とApache稼働を確認。本番代表アプリのログイン・メニュー生成・新クライアント配信に異常なし。

## 2026-10-21 一覧IDの日本語接頭辞

- 標準一覧のID接頭辞に日本語などのUnicode文字を許可し、64文字制限と空白・コロン等の拒否を維持。通常一覧・手動ソートの日本語表示、既存英字、非表示・クリア、CLI/UI保存・不正値拒否を専用テスト環境で確認。本番未反映。

## 2026-10-21 AI背景補完の共通化

- AI画像処理の呼出しをPHPのOpenAI APIとauto-taskのCodex画像編集ツールへ分離。auto-taskからAPIへ自動切替しない。背景補完は共通のマスク準備・候補合成で扱い、範囲外の画素保持、内側の境界混合、入力照合、新規出力を実装。
- 専用テスト環境で両経路・APIスタブ・CLI・拒否条件・一時候補の削除を確認。Codexの実画像編集でも文字除去と範囲外の画素差0を確認。背景の自然さ・境界は案件ごとに目視する。PHP APIのライブ通信と本番配布は未実施。

## 2026-10-21 文字本体の追加太さ

- 汎用画像合成の文字layerへbody_widthを追加。通常・太字フォントの字形を本体色で太らせ、縁取り幅を保って併用できる。既定0の出力互換、太さによる本体色の面積増加、太字フォント併用、領域超過と不正値の拒否を検証。

## 2026-10-20 汎用画像制作ツール

- 文字layerに袋文字（最大4重の色・幅指定縁取り）を追加。縁幅とアンチエイリアス端を含めた領域検査で文字切れを防ぎ、単色・二重縁・透過・不正入力拒否を検証した。

- 汎用画像合成を位置指定クロップ・注目点・余白・回転反転・日本語文字・JPEG/WebPの品質と容量指定へ拡張。比較一覧と設定テンプレートを追加し、案件専用コードなしで定型制作を再利用できるようにした。
- RGB ICC→sRGBはLittle CMSによる明示変換とし、未タグRGBはsRGBと仮定。CMYK/グレーICC・不足依存・文字はみ出し・達成不能容量は停止する。
- 専用検証アプリで既存合成の回帰、追加機能、同梱設定例、日本語描画、JPEG/PNGの実ICC変換、依存不足時の停止、容量不足時の掃除を確認。本番未反映。

## 2026-10-20 PNG処理のGD統一

- PDFへの16bit PNG埋込み時の外部convert実行を廃止し、GDで8bit以下のPNGへ正規化してメモリ上で解析。透過を保持し、変換用一時ファイルを不要にした。16bit精度・ICC色管理の保持は対象外。
- 標準画像リサイズはPNG入力をPNG形式で保存し、元画像のリサイズ・サムネイルの透過を保持。JPEGの既存出力は維持。
- 専用テスト環境で外部コマンド実行を無効化し、16bitグレー・RGB・透過グレー・RGBAのPDF生成、PNG形式・透過・上書きリサイズ、JPEG、不正PNGの拒否を確認。PDF解析も正常。本番未反映。


## 2026-10-20 画像エフェクトCLIの共通化
- frameworkに共通CLIを追加し、ローカルの起動スクリプトと旧Python入口を転送用ラッパーへ変更。描画・モデル・引数定義をframeworkに集約し、処理追加時の二重実装を解消した。
- 既存の処理名・主要引数・確認前停止・新規出力・支持具JSON照合を維持。色空間・ぼかし等はPHP/GDの仕様を正本とし、ICC変換は行わずsRGB化済みの確認フラグを用意した。
- 専用テスト環境で20処理のヘルプ、各種描画、合成・画素保持、設定再現、上書き・不正提案の拒否を確認。ローカル入口でもHEIC・モデル推論・旧Python入口との出力一致を確認した。

## 2026-10-19 画像ビューアーをホイール拡大・ドラッグ移動へ変更

- 標準ダイアログを画像専用の全画面ビューアーへ置換。初期は画面内に収め、ホイールでポインター位置を中心に拡大・縮小、左クリックドラッグで画像を移動。操作ボタンは閉じる「×」だけとし、閉じた後は編集画面と元のフォーカスを保持する。
- 専用テスト環境で一覧・編集・スマートフォン幅のホイール拡大縮小、拡大中心の保持、左ドラッグ移動、右ドラッグ無効、×で閉じた後のフォーカスとページスクロール復帰を確認。c6170d6を対象3サーバーへ本番反映し、正常終了・設定再生成60件の成功・主要ファイル一致と利用アプリのJavaScript／CSS配信一致を確認。

## 2026-10-19 標準画面の画像拡大モーダル

- 編集・一覧の保存済み画像クリックで、標準のモーダルを開く共通表示を追加。サムネイルからも本画像を読み込み、初期100%表示、拡大・縮小、画面に収める、縦横スクロールに対応。画像配信・保存の仕様は維持。
- 専用テスト環境で画像添付から一覧・編集での拡大表示、PC／スマートフォン幅のスクロールと倍率操作、Escapeで閉じて元画像へフォーカスを戻す動作を確認。be90ba7を本番配布し、対象3サーバーの正常終了と主要ファイル一致、利用アプリのJavaScript・CSS配信一致を確認。

## 2026-10-19 PHPからの本体マスク自動生成
- ImageEffects::createMaskを追加。PHPのデコード済みRGBを一時BMPとして渡し、Python標準ライブラリと同梱U2NETP・ONNX Runtimeで候補マスクを返す。推論資産のハッシュ、返却PNGの寸法・ハッシュを検査し、タイムアウト・空マスクを拒否。一時ファイルは成功・失敗時とも削除する。
- 専用テスト環境で実写真の推論、元画素保持、空マスク拒否、実行失敗・タイムアウト時の終了と掃除、既存PHP合成・HEIC・JPEG回転・PNGの回帰を確認。モデル・CPU runtime・ライセンスをframeworkへ同梱し、pipや外部画像APIを使わず動作する。
- 本番と同じopen_basedir制限下では、PHPによるアプリ外Python実行ファイルの存在確認を省き、配列形式のproc_openで起動して成否を判定する。ファイル参照制限は変更しない。
- 28fb4afを配布対象へ本番反映し、PHP CLI・open_basedir有効の条件で実写真からマスクを新規生成して合成まで確認。生成4.791秒、1080×1920 PNG・sRGB、配置・縮小後の本体内部192,984画素の差分0。Web画面の操作確認は含めない。

## 2026-10-19 PHP画像エフェクトライブラリ
- `lib/image_effetcts/ImageEffects.php` を追加。外部で用意した本体マスク・多角形を受け取り、背景、配置、支持具除外、接地影、足別の反射、照明、合成、PNG、比較画像、画素差分、設定JSONをPHP/GDで処理する。AI推論・外部通信・隠れた部品の復元は含めない。
- 既存HEICコンバーターとGDのPNG処理を利用し、入力ファイルを保持。ICC色変換は行わず、ICC付き入力にはsRGB変換済みの明示確認を要求する。既存のController画像リサイズ処理は変更していない。
- 専用テストアプリで実HEIC、JPEGの向き、PNG、設定、確認前停止、除去拒否、本体照明の変更検出、元ファイル保持を確認。フィギュア写真1枚をPHPで合成し、縮小後の本体内部192,984画素の差分0とPNG/sRGB出力を確認。本番未反映。

## 2026-10-18 Excel風ノート追加フラグの本番反映
- framework dea6167を対象3サーバーへ本番配布し、スクリプト正常終了・設定再生成60環境の成功を確認。配布元の追加ガード・メニュー処理・設定定義が検証済みソースと一致した。
- FOCUS2共通配布先17環境の「Excel風ノートの追加」をOffに変更し、全件の設定読戻しで確認。未設定の他プロジェクトは既定On。既存の全行表示の変更も承認の上で同時配布した。

## 2026-10-17 Excel風ノートの追加許可
- システム設定に「Excel風ノートの追加」を追加。未設定はOn、明示的なOffだけでメニューの＋を非表示にし、追加ダイアログと作成処理の直接呼出しも拒否する。既存ノートのデータ・列編集は変更しない。Developer Modeの追加禁止を維持する。

## 2026-10-17 スプレッドシートの全行表示
- Excel風画面の50行ごとのページ分割を廃止し、全行を表示する。ページ番号・総行数・前へ／次へを削除し、行番号は先頭からの連番とする。
- テスト環境で51行の全行表示・行番号1〜51・ページ表示なしをブラウザー確認。一時データは削除済み。本番未反映。

## 2026-10-05 ヘルパー関数のCLI確認
- `method_call`を追加。FBP初期化後にprivate/protected/staticを含む関数へJSON引数を渡し、任意の期待値を型も含めて比較する。出力と戻り値を分け、読込み・生成・呼出し例外や途中終了を成功とみなさない。共通実行部24確認、実CLI8確認と実アプリの時間変換3件を確認。fbp-cliに、一時PHPを自作する前に必要な確認だけCLIで行う手順を追加。

## 2026-10-03 キーワード横断検索のSkill導線
- ローカル入口ルールとfbp-cliからlocal-fbp-findへ案内を追加。ノート不明の文言・キーワード調査は短い横断検索、ノート既知はノート探索、ファイル既知は直接参照と使い分ける。結果取得後の全件再検索を避け、省略・未取得・検索範囲を確認する手順を追加。新規・更新Skillの形式検証に合格。

## 2026-09-30 アプリ独自flockの禁止とFFM排他の指針
- 利用者の指定に基づき、アプリ層の独自flockを禁止し、排他制御をFFMへ統一するルールをfbp-db Skillへ追加。必要なDBの事前オープン後に最新データを読み直して判定・更新すること、追加オープンによるロック再取得、共有ロックと排他ロックの違い、ファイル単位の範囲、原子性・待機期限の限界を明記した。
- テスト用の独立した2プロセスで、同一FFMファイルの排他、DBを逆順に開いても循環待ちを回避すること、再取得中は先に保持していたファイルへ他プロセスが入れることを確認。専用FFMの単純追加だけでは処理全体の排他を保証しない。Skill検証と差分検査に合格。既存アプリの置換・共通ランタイム変更・本番反映は未実施。

## 2026-09-28 プロジェクトサポート案内の配置変更
- 追加調整としてロボット・吹き出しの上余白を10pxに変更。テスト環境の両メニューで余白を確認。
- ホーム画面と左メニューのロボット・吹き出しを管理メニュー末尾の「システム設定」の下へ移動。プロジェクトサポートの表示条件を維持し、システム設定が非表示の場合は最後の表示項目の下に配置する。
- テスト環境で両メニューの配置と画像読込み、ホーム画面の見た目をブラウザ確認。本番未反映。

## 2026-09-28 共通OpenAIクライアントの不要な利用量保存を撤去
- フレームワークec2f1c1を対象全3サーバー・57環境へ本番反映。配布処理の正常終了と、各サーバー配布元のOpenAIクライアントのSHA-256一致を確認。本番での実API呼出しは未実施。
- テスト環境へ同期し、PHP構文・既存提供元テスト7件・既存処理テスト24件・メモリ上のトークン集計を確認。実API再呼出しと本番リリースは未実施。
- 現行ソースに保存先・参照元がないassistants向け月次利用量保存と、その呼び出し・専用回帰テストを削除。メモリ上のトークン集計とgetTokenUsageTotalsは維持。「一緒に作る」の独立した利用量記録には変更なし。

## 2026-09-28 変更影響に応じた検証・証憑の選択
- CLI・ブラウザの重複確認を避け、変更した結果に必要な方法を選ぶようSkillを統一。操作確認と画像を分離し、画面に影響しない処理・文言変更は原則画像不要、見た目は影響する端末・状態だけを確認する。
- 最終報告の短い確認結果を基本の証憑とする。重要な業務条件と明示依頼は維持し、軽微な変更で永続テスト・handoffを必須にしない。Skill検証と関連auto-taskプロンプト生成を確認。

## 2026-09-28 OpenAI利用量記録の任意化
- フレームワーク a46cf5e を対象全3サーバー・57環境へ本番配布し、処理の正常終了を確認。各サーバーの配布元でOpenAIクライアントと標準検索フォームのSHA-256がソースと一致。標準画面の検索フォームID衝突修正、サンプル・ドキュメント更新も承認範囲に含む。本番での個別アプリの実検索は未確認。
- assistantsクラスがアプリ・フレームワークのどちらにもない場合、利用量保存をスキップしてAPI応答を返す。クラスがある場合の月次保存と保存失敗の例外は維持。
- 独立回帰テスト6件とPHP構文を確認。Task 4385のテスト実画面ではクラス解決例外が解消し、検索の根拠確認エラーまで進むことを確認。検索全体の成功は未確認。本番未反映。

## 2026-09-28 ノート起点の既存実装探索
- CLI Skillとローカル入口に、環境の参照専用ノート探索ツールを優先する手順を追加。ノート→クラス→編集元→関数を絞り込み、取得済みの定義の再読込みを減らす。通常作業とauto-taskで同じ入口を使う。
- 専用9テスト・既存工程5テストと実ノートで検証。アプリ実装と業務データの変更はない。

## 2026-09-28 定番業務機能の再利用サンプル
- 親子ノート＋対応履歴、理由付き状態変更、公開フォーム＋管理受付の3種類を、コード・定義manifest・導入手順・単体/ブラウザ検証とともに追加。管理側はStandard Screen。導入時のデータ投入・既存クラス/ノートの上書き・本番操作は行わない。ヒヤリングで希望された本番テストデータ投入は別工程とする。
- 実検証で検索と追加フォームのIDが同じ秒に衝突する問題を検出し、Standard Screenの検索フォームIDを用途とノートIDで分離。必須項目設定の読戻しを導入処理へ追加した。
- 検証：PHP構文9ファイル、manifest、独立単体テスト、導入時の上書き拒否、4ノートの定義読戻し、ブラウザで子追加/編集・必須チェック・状態変更・公開受付/再送・管理側の対応状態編集を確認。PC/390pxの画像を確認し、公開画面の横幅は390px（横あふれなし）。今回の検証データは全件回収し、既存ノート定義の不変とサンプルコードのソース/実行環境一致を確認。
- 復旧待ち：検証準備の一時コピー処理で範囲を誤り、既存テスト用 `classes/app/public_pages/public_pages.php` を上書きした。誤配置のPHP・追加テンプレートは退避済み。元PHPはGit管理外で、過去ログから冒頭110行のみ回収。元のテンプレートとCSSは保持。完全な復元元をユーザーへ照会中で、既存の `orders_contact` / `line_orders_contact` は未復旧。3サンプルの実装とは別の未完了事項として扱う。本番への操作はない。

## 2026-09-27 顧客管理３と履歴管理３
- 統合ビルダーで `crm_customers3`（顧客管理３・8項目）と `crm_history3`（履歴管理３・6項目）を作成。顧客3件と、最初の顧客に紐付く履歴3件を作成時に投入。検索画面・子サイドパネルを各1枚撮影。定義は `note-definitions/crm-customers3.json` と `note-definitions/crm-history3.json`。通常の専用フローとして追加のCRUDテストは実施せず、本番操作なし。
- 依頼受信から両ノートの投入・撮影完了まで90.667秒（作成コマンドは親11.457秒＋子12.451秒＝23.908秒）。同区間の実ログ5応答は入力869,901、うちキャッシュ861,056、未キャッシュ8,845、出力2,409、入力＋出力872,310トークン。reasoning_output_tokensは293。後続の計測集計・履歴記録・最終回答は対象外。長い継続会話での測定のため、前回の新規セッション比較とは条件が異なる。

## 2026-09-27 ノート作成時のサンプル投入統合
- 作成ビルダーの各項目に`sample`を指定して3件を投入する運用へ更新。Standard Screen Skillとローカル入口を整合させ、作成済み定義の再調査や独立投入を不要にした。
- 通常ノートと子ノートで実作成・3件投入・全項目照合・撮影を確認し、子の再撮影でも3件のまま。検証用ノートを削除し、既存の顧客管理（標準）の4件は不変。本番操作なし。

## 2026-09-27 顧客管理（標準）のサンプルデータ投入
- 続けてAI生成・確認を含む全体比較を各方式2回実施。平均は共通方式76.474秒・入力＋出力215,982トークン、直接方式66.513秒・213,137.5トークン。比較用12件は削除し、元の4件が全項目不変で残ることを確認。共通手順・定義の再読込みが大きく、現状では全体の削減効果は確認できなかった。
- `crm_customers`へ会社・担当者・メール・住所等の架空データ3件（ID 17,18,19）を追加。既存の撮影用顧客1件は保持して計4件。比較のために追加した他のレコードは削除済み。全項目の読戻し一致と既存データの不変を確認。本番操作なし。
- 共通テンプレート方式と直接CLI方式を各3回測定。実行時間中央値は0.485秒／0.200秒（共通方式は対象・項目確認を含む）。投入JSONは78／210トークン。AI生成時間・総使用トークンは計測対象外。

## 2026-09-27 従来方式での親子ノート比較
- 専用ビルダー・撮影helperを使わず、都度組み立てた個別CLI操作で `crm_manual_customers`（顧客管理（従来方式）・8項目）と `crm_manual_history`（履歴管理（従来方式）・6項目）を作成。先行版の設計を再利用し、親子関係・画面項目・選択肢を揃えた。撮影用の架空顧客1件を残した。
- 作成後に項目型・長さ・親子設定を読み戻し、標準画面チェッカーは両方エラー0件（親の未使用list_on_sideのみ意図した警告）。親一覧と幅900pxの子画面を撮影し、ブラウザーで子の追加・編集・削除と再描画を確認、検証履歴は削除済み。検討開始から検証完了まで約4分14秒。ブラウザー検証コードの待機対象・日付picker指定の修正を含む。先行版の約2分11秒とは検証範囲が異なるため、単純な登録速度の倍率とは扱わない。記録完了までの総時間は別途計測。本番反映なし。

## 2026-09-27 顧客管理と履歴管理
- 専用ビルダーでテスト環境に `crm_customers`（顧客管理（標準）・8項目）と子ノート `crm_history`（履歴管理・6項目）を作成。既存の独自画面「顧客管理」との名前重複を事前検出し、新規標準画面の表示名を区別した。定義は `note-definitions/crm-customers.json` と `note-definitions/crm-history.json`。
- 子サイドパネル撮影用に架空の顧客を1件登録し、親の検索画面・子サイドパネルを各1枚取得。通常フローの作成後テストは省略。ビルダー成功実行は親12.130秒、子11.573秒。別途、AI検討・名前重複対応・記録を含む経過時間も計測した。本番反映なし。

## 2026-09-27 Child-note builder workflow
- Extended dedicated Standard Screen creation guidance to new child notes under an existing independent search/list parent. The definition declares the parent and side-panel fields; an existing parent record is selected for the single screenshot. Normal completion uses preflight and insert acknowledgements, with no data seeding or post-build tests.
- Standard Screen, DB, CLI, Playwright and Side Panel Skills now share this exception. Builder development verified parent resolution, automatic parent ID fields, cascade/menu/icon settings, failure cases and settled side-panel capture/retry; the temporary child was removed without altering parent records. No production release.

## 2026-09-27 名刺管理ノート
- 専用ビルダーでテスト環境に `business_cards`（名刺管理）を作成。氏名・会社名・部署・役職・メールアドレス・電話番号・住所・名刺交換日・メモを登録し、検索＋一覧の標準画面を構築した。定義は `note-definitions/business-cards.json`。
- 作成前チェックと登録成功、検索画面のスクリーンショット1枚取得で完了。作成後テスト・サンプルデータ投入・本番反映は行っていない。

## 2026-09-27 Standard Screen builder workflow
- Standard Screen, DB, CLI and Playwright Skills now prefer the environment's validated create-only builder for supported new search/list notes. Normal completion is strict preflight, configuration creation and one search-screen screenshot; post-build checks and CRUD tests are omitted. Existing-note changes and unsupported features retain the ordinary workflow.
- Builder development checks cover supported field storage, invalid-input rejection, failure receipts, target collisions and screenshot capture/retry. No framework runtime or production deployment was changed.

## 2026-09-27 Scoped integration settings
- Added a signed/CLI settings endpoint for seven service groups, secret-free registration state, test-derived external-key catalog, scoped set/clear and idempotent apply receipts. No provider connection or credential preflight checks. Existing storage formats remain unchanged.
- Masked system-setting secrets in operation logs and excluded raw setting snapshots. Environment-specific queue/receipt data is excluded from release archives. Verified real test-runtime storage, isolation, retry/conflict handling and consuming application's worker/UI flows. Test environments synchronized; no production release.

## 2026-09-26 External integration keys
- Production framework release `2f512cd` completed on all selected servers (57 application entries). Nine relevant distribution-source files matched local SHA-256 on each server. Includes the Standard Screen ID-prefix feature. Individual application screen operation after deployment was not rechecked.
- Added a system-settings tab immediately after Vimeo for app-owned API credentials/settings: unique case-sensitive key, display title and values up to 8,192 bytes. Server-side `get_external_key` / `require_external_key` provide lossless reads; saved values are never repopulated in management forms, and blank edits preserve the current value.
- Records are environment-specific and excluded from release files; incoming older archives containing this data are also ignored. Access follows system-setting permissions, including the app management guard. FFM logs and browser debugging mask values; secret log snapshots are excluded. See `external-keys.md` for usage and storage semantics.
- Verified 56 storage/API/permission/release-policy checks and Chromium CRUD/search, tab placement and existing setting form ownership. The actual downloaded release excluded the data and fixture value; at 390px the panel measured 342px with no horizontal overflow. Test environment synchronized; production framework release completed as recorded above.

## 2026-09-26 Standard Screen record identifiers
- Added optional note setting `identifier_prefix` to the existing list ID display. Search/list and manual-sort rows show `[prefix:id]` when `show_id=1`; empty prefixes preserve numeric IDs. The setting is available in note settings and `db_tables_add/edit`, with shared validation and escaped, non-wrapping output. Other screens retain their existing behavior.
- Verified both list patterns, unset/cleared/hidden ID behavior, invalid input rejection, management-form save/read-back and standard-screen checks. Chromium checks at 1440px and 390px confirmed the identifier is the first data cell, with no page overflow. Applied `fbpdev` to the consuming app's free-registration list in the test environment. No production release.

## 2026-09-26 Inline saved media response
- Added `res_saved_media($filename, $options)` for content-detected image/video/audio delivery with private/no-store by default, optional public caching, single byte ranges and HEAD. Record authorization remains app-owned. Removed the unreleased opt-in saved-file guard from the previous Task 4378 change; existing image/download APIs return to their pre-guard behavior.
- Verified 33 isolated HTTP checks, 38 app-side access/legacy-entry checks and Chromium MP4 playback/seeking. Framework release 7bb1f49 completed successfully on all selected servers; the three changed runtime files matched the local hashes in each server's distribution source. Direct per-app hash inspection was unavailable with the verification account's permissions.

## 2026-09-26 Admin PDF delivery sample
- Added standalone fictional single/bulk invoice assets, manifest, overwrite-refusing installer and a browser verifier. Management login remains enabled; the sample rejects empty/unknown selections with HTML and uses object `download_pdf()` output. PDF Skills and README now point to actual reusable assets instead of an inline example.
- Verified CLI dialog output, PHP/JS syntax, installation/refusal and Chromium desktop/mobile-width downloads: single A/B, bulk two pages, selected one page, filenames/content, invalid selections and separate-session denial. Parsed all eight PDFs and inspected the rendered sample. Removed the temporary app installation after verification; no production release.

## 2026-09-26 Admin PDF Skill standard
- Standardized new admin PDF guidance on a download dialog, POST via `download-link`, object construction with `create_pdfmaker()`, and `download_pdf()`. Added a reusable PHP/template example and aligned PDF, app-sample, Standard Screen and browser-verification instructions. Template-based `show_pdf()` remains for compatibility or an explicit preview requirement; content-only maintenance does not force migration.

## 2026-09-26 PDF object output APIs
- Audited PDF, public-page, sample, browser-check, temporary-file and CSV/media Skills together. Clarified that public PDF GET delivery and object `download_pdf()` take precedence over generic file-download rules; application cleanup applies only when directly managing saved files. All six Skill validators passed.
- Added `pdfmaker_class::get_pdf_data()` for binary data without response output and `download_pdf()` for an attachment response with managed-file creation, complete-write checks, private/no-store headers and cleanup on failure/exit. Existing `create_pdf()` inline behavior is preserved. Fixed tFPDF string output to read its actual stream buffer instead of the obsolete empty string property.
- Updated the PDF sample and Skills to use the object download API. Verified real PDF parsing/two pages, repeated generation, inline equivalence, HTTP header/output silence, partial-save/send failures, concurrent downloads and exit cleanup; existing text-box tests also passed. Chromium desktop/mobile-width sample preview/download and the consuming app's invoice/receipt downloads passed. The app PDFs match prior text and rendered pixels. Test environments synchronized; production not released. Deploy the supporting framework before apps that call the new API.

## 2026-09-26 PDF delivery sample and required application
- Added a session-bound per-page download-grant sample with current identity/record/issuance rechecks, fail-closed authentication hooks, HTML denial pages, and managed-file initialization/cleanup. PDF/public-page Skills now require reading and adapting the actual delivery assets and distinguishing an unreproduced preventive change from a verified bug fix.
- Verified 49 contract checks, installation/overwrite refusal, CLI responses, and isolated Chromium desktop/mobile-width PDF retrieval, multiple tabs/documents, expiry, changed ownership, revoked issuance, logout and recovery. Parsed PDFs and confirmed cleanup after download exit; Safari/LINE-device behavior remains outside this verification. No customer app or production release was changed.

## 2026-09-25 Project portal robot guidance
- Added the existing Jean Lafitte image and a speech bubble directly below the Project Portal link in both the home menu and side menu. Japanese copy: 「プロジェクトサポートからシステムのご質問・変更依頼ができます。」. The visible menu and setting labels use 「プロジェクトサポート」 (Project Support); internal keys remain unchanged. The guide follows portal-link visibility and uses a framework-owned static image with Japanese/English translations.
- Verified the authenticated framework test screen at 1280px and 390px: home and side-menu placement, image loading, preserved external-link attributes, no horizontal overflow or JavaScript errors. Test environment synchronized; production not released.

## 2026-09-25 管理画面のレスポンシブSkill
- スマホ表示相談では画面種別を確認し、標準画面は「標準画面レスポンシブ」設定と共通機能で対応する方針を明記。独自画面には同じ700px境界・項目名付きカード・検索1列・表示モード尊重の方針と、範囲を限定したtplサンプルを追加した。
- Skill検証、Smartyによる空／データあり一覧の描画、共通CSSを使ったブラウザーfixtureの320／375／700／701／1280px・両表示モード・幅変更後の復元を確認。案件への適用とは分けて扱う。

## 2026-09-24 Authenticated framework test access
- Retrieved framework test-access metadata from the production management API's DB-free special case and verified browser login, management-page rendering, side-menu loading, settings-page Ajax rendering and logout. The settings menu link was outside the automation viewport, so settings rendering was checked through the existing frontend `appcon` path. No setting values or user records were changed.
- The authenticated flow completed with zero HTTP errors, JavaScript exceptions or React asset requests. This completes the previously unavailable management-screen smoke check after React removal and test-routing repair.

## 2026-09-24 Framework test routing and CLI regeneration
- The framework test entrypoint returned Apache 404 while another app on the same gateway returned 200; direct loopback access reproduced the framework-only failure. The generated root `.htaccess` used relative `fbp` as its subpath, producing `fbp/fbp` rewrites, and exactly matched the shared template with that incorrect input.
- Reproduced the incorrect subpath calculation with `SCRIPT_NAME=fbp/cli.php` in `setting::regenerate_setting_files()`. The observed file predated React removal; the specific historical writer was not established. CLI regeneration now preserves the existing valid URL prefix instead of interpreting filesystem paths as URLs; ambiguous or invalid prefixes fail before either generated file is written. Web saves continue to derive the prefix from the request's framework entrypoint.
- Added `setting_regenerate_files` with optional `htaccess_subpath` to explicitly repair routing without changing setting values; an empty string selects the site root. Repaired the framework test routing through this command and verified that relative and absolute CLI invocations retain the repaired prefix, invalid arguments leave it unchanged, and settings are preserved.
- PHP syntax, 23 URL-prefix regression cases and the generated-file write regression passed. Browser verification of the direct PHP entrypoint, root and login route returned HTTP 200 and rendered the Ajax login form. The public form and required-field Ajax validation also passed, with zero HTTP or JavaScript errors and no records saved. Runtime files match source; this fix has not been released to production.

## 2026-09-24 Unused React assets
- Removed the unused React, React DOM, React Flow, JSX runtime shim and React Flow CSS assets, plus their five shared-template references. Existing framework and app source searches found no dependent implementation; other frontend libraries remain unchanged.
- Verified source/runtime synchronization and absence of remaining runtime React references. Browser checks against the local runtime covered login-form Ajax rendering and public-form required-field validation without saving records: zero React requests, JavaScript exceptions or HTTP errors. Authenticated management screens were not verified because framework login credentials were unavailable.

## 2026-09-19 Standard Screen display mode
- Added the WEB panel's 「標準画面レスポンシブ」 selector: 0 keeps responsive lists/search/header; 1 preserves desktop tables/search/header and the 800px management minimum width on narrow screens. Missing/invalid values default to 0. Existing viewport settings and shared input controls remain intact; the shared header choice also applies while viewing custom management screens.
- Normalized setting saves, CLI edits and initial setup consistently. Gated mobile CSS by the management body's display mode and made manual-sort helper sizing follow the actual table/card layout.
- Verified old-format setting migration with every existing field preserved, persistence of both modes, PHP syntax and generated-setting-file regression. Browser checks covered UI save/reload, 375/700/701/1280px, normal/manual-sort lists, search redraw, horizontal scrolling on/off, edit/password dialogs, menu, logout, and real drag persistence followed by resizing. Mobile responsive width measured 375px without overflow; desktop mode retained 800px. Test settings were restored and fixture definitions/data removed; source and test runtime match.
- Released `a603e20` to the configured production framework targets. Set app-daitomiraku to desktop mode and verified the saved value, unchanged other settings and the live shared CSS against source.

## 2026-09-18 Error response privacy
- Removed exception messages, internal paths, stack traces and detail controls from shared page/Ajax and PDF error responses, including report failure and unconfigured reporting. Error IDs, status links and management-side reporting remain available; PHP display_errors is disabled in both entrypoints.
- Verified PHP syntax, nine response scenarios, and browser rendering/status-link navigation using the synchronized test code.

## 2026-09-18 Mobile header
- At widths up to 700px, the management header hides the app title, tagline and login user name, placing the existing password-change and logout controls at the right of the menu bar. Controls remain single instances with their existing actions and 44px touch targets. The mobile bar measures 57px high; desktop presentation returns above the breakpoint.
- Verified 320/375/700px alignment and 701/1280px desktop restoration in a touch-enabled browser, plus menu opening/closing, password dialog opening and logout back to the login form. No JavaScript errors; source and test files match.

## 2026-09-18
- Standard Screen search/list and manual-sort main lists now use labeled cards at viewport widths up to 700px. Search fields and date ranges stack vertically; long values and action buttons wrap. Mobile cards override horizontal-scroll settings. Desktop tables, calendar/single-record screens and child side panels retain their existing layout. Sortable clones its drag helper so cell widths do not persist after resizing.
- Original searchable selects now size entirely through CSS, without copying the source select's inline or computed width. Custom widths belong on the field container or wrapper. Date/time/color/year-month panel widths moved from JavaScript markup to viewport-constrained CSS in both management and public styles; geolocation feedback follows its container through CSS. Internal picker controls and popup positioning retain their functional dimensions/JavaScript.
- Browser verification covered real Standard Screen lists at 320/375/700/701/1280px, search selection, text/select width equality after resizing, date selection, color panel bounds, geolocation success/failure feedback, and mobile-card drag payloads without writing shared record order. No horizontal overflow was present at the tested mobile widths; desktop retains its pre-existing 800px minimum above the mobile breakpoint.
- Also verified edit-dialog opening, mobile horizontal-scroll overrides, desktop drag payloads and unchanged original cell widths, time/year-month selection, and public-style date/time/color/year-month panel bounds at 320px. Browser runs reported no JavaScript errors.

## 2026-09-17
- Made original searchable select wrappers responsive through CSS instead of freezing computed widths at initialization; explicit inline widths remain supported. Capped text inputs, textareas and native selects at their parent width in management/public form styles. Browser fixtures using framework assets verified 1200→768→375→1200 viewport changes, relative/fixed widths, no horizontal overflow, and searchable selection.
- Added the WEB tab’s get_APP_URL protocol override below SSL: 0 preserves request-based detection, 1 forces HTTPS, and 2 forces HTTP. Missing values preserve existing behavior. Initial project setup accepts this setting with default 0; system setting data remains outside app releases. Verified protocol combinations, existing URL/query formats, setting form rendering and HTTPS URL generation in the test wizard.

## 2026-09-17
- Added `prohibit_url_check()` at the start of `app.php`, before session/library/DB initialization. Requests with `_next` or `.next` path segments (including percent-encoded forms and app subpaths) return HTTP 403 and the fixed Next.js rejection message as plain text. HEAD omits the body; query-only matches and similar ordinary names remain allowed. Extend the local rule list for future confirmed probe patterns. Only requests routed to `app.php` are covered.

## 2026-09-16
- Fixed cron execution-log writes after jobs close Controller-managed databases (ServerError #270/#277/#278). Cron retains its existing initial lock lifetime, reacquires the database through Controller and ordered FFM construction when needed, updates only the log of an existing job, and closes through Controller. Throwable failures are logged within the field byte limit and rethrown for ServerError reporting. Isolated real-FFM tests cover normal execution, closed connections, concurrent setting changes/deletion, exceptions, and reverse-order two-process lock acquisition. No standalone lock or automatic retry was added.

## 2026-09-10
- Added the Standard Screen single-record pattern (`list_type=3`): a main-area form using edit fields, insert-on-first-save, updates to the existing ID, and a success Notification. Retains standard validation, uploads, and post-action hooks; rejects multiple records and standard list/duplicate/delete/child operations.
- Supports top buttons only (`place=0`), enforced in button settings, CLI, and drag placement. Pattern conversion rejects parent notes, multiple records, and incompatible buttons. Updated screen-pattern labels, edit-field configuration, the Standard Screen checker, and reusable Skill guidance. Verified concurrent saves, invalid inputs, direct-operation guards, conversion checks, and browser save/notification/top-button behavior. Generic DB writes remain outside the screen-level singleton constraint.

## 2026-09-07
- Added an enabled/disabled selector and save button to the setting screen's MCP Server URL dialog. Saving validates the state and changes only enabled/updated_at on existing records; other server settings remain read-only. Missing configurations display disabled and are initialized only on save. Verified both states, rejected invalid input, preserved other fields, and restored the test state.
- Removed the OAuth URLs row from the setting screen's MCP Server URL dialog. The MCP endpoint remains visible; OAuth behavior is unchanged.

## 2026-09-06
- Replaced Web and CLI template constant preloading with one definition scan and one sorted value scan, grouping values and colors in memory. Preserves name/assignment order, table_fields-last behavior, legacy duplicates and text keys, empty colors, and live table references without persistent caching. Regression fixtures compare all assignments against the old loop; a 54-set/331-value fixture reduced preload time from roughly 200–330ms to 2ms.

## 2026-09-06
- Fixed DB API `describe` rejecting valid four-column FFM definitions with `IDX`. Responses now include `indexed`, matching FFM behavior (including the implicit ID index exception), while invalid options remain rejected.

## 2026-08-31
- Added output schemas to the fixed public MCP `function_list` and `function_call` Tools. The catalog result explicitly returns `functions` and `count`; the dispatcher accepts the selected internal function's structured object result.

## 2026-08-31
- Fixed the public MCP tool surface to `function_list` and `function_call`. Registered `mcp_<function_name>` classes remain an authenticated internal function catalog, while legacy `mcp_tools`, Note CRUD, and App Action registrations are no longer executable or publishable through MCP.

## 2026-08-27
- Implemented the single MCP Server function registry with `mcp_functions`, deterministic `mcp_<function_name>` class loading, `McpFunctionInterface`, standard `tools/list` and `tools/call` dispatch, singleton management UI, CLI registration, and migration-only fallback to legacy tools when an app has no registered functions.
- Removed the standalone blocking read-only preflight lock and routed Standard Screen read-only opens through the existing globally ordered FFM lock path before format validation. Missing data files and format changes now request an ordered writable reopen, and a two-process reverse-order regression test verifies that shared/read-write opens cannot form the former circular wait.
- Documented the planned MCP redesign around one Server per project, standard `tools/list` / `tools/call`, a dedicated function registry, deterministic `mcp_<function_name>` classes, task-management migration, and mandatory cleanup of legacy specifications, Skills, samples, and compatibility code after migration.

## 2026-08-14
- Added opt-in `IDX` fields to fixed file formats, exact-match candidate indexes for `select()` and exact `filter()`, CRUD/change-format index maintenance, and safe full-scan fallback for missing, dirty, or invalid indexes.
- Added the `db_fields.index_flag` management option and preserved the existing behavior for every field without `IDX`.
- Added CLI-only FFM index, concurrency, performance, existing-corpus, and pre/post implementation differential tests under the fixed file manager directory.
- Added 16 irregular index tests and automatic writable-mode rebuilds for missing or structurally invalid indexes; dirty state remains a full-scan condition requiring explicit integrity review.
- Added a daily-use benchmark with 100,000 customers and 1,000,000 child histories, including CRUD, cold-request, memory, and eight-reader concurrency measurements.
- Added an isolated 128-shard JSON versus fixed-binary benchmark for one million index IDs, covering cold, spread, hot-cache, memory, and concurrent-reader behavior.
- Replaced the opt-in FFM JSON index with a versioned 128-shard fixed-binary index using 64-bit IDs, per-shard checksums, binary-search lookup, touched-shard-only CRUD updates, legacy JSON migration, and safe full-scan fallback.
- Expanded irregular tests to 19 binary-index cases and repeated the one-million-row and 100,000-customer/one-million-history benchmarks, reducing cold-open allocation from 53 MiB to 2 MiB per PHP process.
- Re-ran the pre-binary JSON implementation and current binary implementation as a release preflight, including strict IDX-free byte comparison, five existing `.dat` copies, five framework regression tests, one-million-row benchmarks, and a scan confirming no existing test-app `.fmt` currently enables `IDX`.
- Enabled Standard Screen filters to use opt-in indexes for numeric equality conditions while preserving text partial matching, range and OR fallbacks; one million rows improved from 2.487848 seconds to 0.027545 seconds with identical results.
- Added internal 4 MiB block scanning for T-field partial matching without external commands or text index files, with fixed-field boundary checks, candidate-limit fallback, and final legacy-condition revalidation; one million rows improved by about 7.7–9.9x with identical results.
- Opened Standard Screen display-only requests with shared read-only FFM locks so concurrent lists and searches do not serialize, while write functions retain exclusive locks and missing/stale formats safely use the existing writable initialization path.
- Changed Standard Screen rendering to ignore stale `screen_fields` links without deleting configuration during a display-only request, preventing read-only side panels and lists from failing when a field was removed first.

## 2026-08-05
- Changed setting-generated `.htaccess` and `robots.txt` writes to throw an exception when `file_put_contents()` fails, so setting screen/API saves cannot silently report success after a write failure.

## 2026-08-04
- Changed `get_APP_URL()` to generate standard `?key=value` query strings by default, preventing LINE and other external clients from dropping the first parameter.
- Kept the former `&key=value` output available through the explicit `query_format=legacy` option; existing legacy links remain routable without `.htaccess` changes.

## 2026-06-10
- Investigated the existing `show_menu_homepage` setting before changing the homepage menu behavior.
- The current menu link is controlled by `show_menu_homepage` and points to the app root generated by `get_APP_URL()`.
- The existing homepage field is `website_url`; it is only shown on the setting screen and is empty in the checked test apps.
- Checked apps with the menu option enabled or recently enabled: `app-miclubpayment`, `app-nb`, `app-tomi`, and `app-wordgritty`.
- Planned behavior: show the homepage menu only when `show_menu_homepage` is enabled and `website_url` contains a valid `http` or `https` URL, and use `website_url` as the menu link target.

## 2026-06-13
- Added the first framework-level MCP foundation: `mcp_manage` in the development panel owns server/tool/field settings, and `mcp_server` exposes the public MCP/OAuth endpoints.
- MCP OAuth tokens are linked to existing `user.id`; every tool call re-checks the user record so deleting or invalidating the user also invalidates the OAuth connection.
- Added MCP server/tool/field setting data to project release packages while excluding MCP OAuth tokens, auth codes, and call logs.

## 2026-06-16
- MCP Note CRUD `create` and `update` now accept `file` / `image` fields as base64 upload objects, and `list` / `get` return file metadata plus public download/view URLs for saved media.
