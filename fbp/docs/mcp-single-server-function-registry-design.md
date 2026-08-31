# MCP単一Server・関数レジストリ再設計

## 1. 文書の位置づけ

この文書は、FBPのMCP機能を「1プロジェクトに複数MCP Server」から「1プロジェクトに1 MCP Server」へ変更し、関数追加によって拡張できる構造へ再設計するための方針を定義する。

本設計の基盤実装は完了している。`mcp_functions`、`McpFunctionInterface`、決定的クラス解決、単一Server管理UI、標準 `tools/list` / `tools/call`、CLI登録を正本とする。旧レジストリと複数Server処理は、未移行アプリを止めないための移行互換として残している。

## 2. 目的

- `app-xxx` 単位でMCP Serverを1つだけ提供する。
- MCP標準のTool探索・実行方式をそのまま利用する。
- アプリ固有機能を、MCP関数の追加によって拡張できるようにする。
- 関数名とPHPクラス名の対応を規則化し、任意クラス指定や設定間違いを防ぐ。
- 認証、scope、入力検証、安全性表示、確認必須判定、ログを共通化する。
- 既存のタスク管理MCPを新しい関数方式で再作成できるようにする。

## 3. 基本方針

### 3.1 1プロジェクト1 MCP Server

- 1つのFBPプロジェクトは、1つのMCP endpointだけを持つ。
- 正規endpointは `mcp_server*rpc` とする。
- 新設計では、MCP Serverを選択するための `server` / `server_key` パラメータを使用しない。
- OAuth protected resource、authorization endpoint、token endpointも同じ単一Server設定を参照する。
- 認証方式、認可主体、Subject Provider、既定scopeはプロジェクト単位で設定する。

### 3.2 MCP標準のTool一覧・Tool実行を使用する

外部クライアントとの通信には、MCP標準の次のメソッドを使用する。

- `tools/list`: 有効なMCP関数をTool定義として返す。
- `tools/call`: Tool名からMCP関数を解決して実行する。

`tools/list` が公開するToolは、フレームワーク固定の `function_list` と `function_call` の2件だけとする。アプリ固有関数は直接Toolとして公開しない。`function_list` が認可済みの関数ごとの `inputSchema`、`outputSchema`、説明、scope、安全性情報を返し、`function_call` が関数名とargumentsを受けて内部ディスパッチする。

### 3.3 内部構成

```text
MCP endpoint
  -> OAuth・Subject解決
  -> Function Registry
  -> 固定Tool descriptor生成（tools/list: function_list / function_call）
  -> Function Catalog（function_list）
  -> Function Dispatcher（function_call）
       -> scope確認
       -> 確認必須判定
       -> 入力検証
       -> PHPクラス実行
       -> 結果変換
       -> 呼び出しログ
```

## 4. MCP基本設定

現在の複数行を前提とするServer設定は、単一設定として扱う。保存先はMCP専用のフレームワーク管理データとし、通常の業務ノートにはしない。

保持する設定は次を基本とする。

| 項目 | 内容 |
|---|---|
| `enabled` | MCP Serverの有効状態 |
| `title` | MCP Server表示名 |
| `description` | MCP Server説明 |
| `auth_mode` | `oauth2` または許可された認証方式 |
| `subject_type` | `fbp_user` またはアプリ固有subject |
| `subject_provider_class` | custom subject使用時のProviderクラス |
| `default_scope` | プロジェクトの既定scope |
| `updated_at` | 更新日時 |

複数Server選択に使用していた `server_key` と `sort` は、新設計では不要とする。既存データ移行中だけ互換情報として保持する可能性がある。

## 5. MCP関数レジストリ

### 5.1 保存方針

関数登録は、フレームワーク管理の専用ノート `mcp_functions` で管理する。サイドメニューから利用する一般業務ノートではなく、`mcp_manage` から操作する内部レジストリとする。

1プロジェクト1 MCP Serverのため、関数レコードに `server_id` は持たせない。

### 5.2 項目案

| 項目 | 内容 |
|---|---|
| `id` | 内部ID |
| `enabled` | `tools/list` への公開状態 |
| `function_name` | MCPへ公開する一意なTool名 |
| `title` | 人向け表示名 |
| `description` | AIクライアント向け説明 |
| `required_scope` | 実行に必要なOAuth scope |
| `requires_confirmation` | `confirm=true` を必須にするか |
| `read_only` | MCP `readOnlyHint` |
| `destructive` | MCP `destructiveHint` |
| `sort` | `tools/list` の安定した並び順 |
| `handler_config` | 共通Handlerに必要な追加設定。必要な場合だけ使用 |
| `created_at` | 作成日時 |
| `updated_at` | 更新日時 |

PHPクラス名はレコードへ保存しない。`function_name` から規則により一意に決定する。

### 5.3 正本の分担

関数レジストリを正本にするもの:

- 関数名
- 公開状態
- タイトルと説明
- scope
- 読み取り専用・破壊的操作・確認必須の設定
- 並び順

PHPクラスを正本にするもの:

- `inputSchema`
- `outputSchema`
- 実行時の入力検証
- subject・所有者・業務権限の検証
- 業務処理
- 構造化された戻り値

SchemaをレジストリへJSON文字列として重複保存しない。表示用Schemaと実行時検証の乖離を防ぐため、PHPクラスから取得する。

## 6. 関数名とPHPクラス名

### 6.1 MCP関数名

MCP関数名は次の形式に限定する。

```regex
^[a-z][a-z0-9_]*$
```

- 小文字英字で開始する。
- 小文字英字、数字、アンダースコアだけを使用する。
- 関数名自体には `mcp_` を付けない。
- ドット、ハイフン、空白は使用しない。
- プロジェクト内で一意にする。

例:

```text
project_list
project_get
task_list
task_get
task_create
task_update
task_comment_create
```

### 6.2 PHPクラス名と配置

PHPクラス名は次の規則で自動決定する。

```text
PHP class = mcp_<function_name>
```

例:

| MCP関数名 | PHPクラス名 | 配置先 |
|---|---|---|
| `project_list` | `mcp_project_list` | `classes/app/mcp_project_list/mcp_project_list.php` |
| `task_create` | `mcp_task_create` | `classes/app/mcp_task_create/mcp_task_create.php` |
| `task_comment_create` | `mcp_task_comment_create` | `classes/app/mcp_task_comment_create/mcp_task_comment_create.php` |

関数名とクラス名を別々に手入力させない。クラス名を変更可能な設定項目も設けない。

## 7. PHPインターフェイス案

各関数クラスは、フレームワーク共通の `McpFunctionInterface` を実装する。

```php
interface McpFunctionInterface {
	public function getInputSchema(Controller $ctl, array $function): array;
	public function getOutputSchema(Controller $ctl, array $function): array;
	public function execute(
		Controller $ctl,
		McpFunctionRequest $request
	): McpFunctionResult;
}
```

`McpFunctionRequest` は最低限、次を提供する。

- 登録された関数設定
- MCPから渡されたarguments
- 解決済みの `McpSubject`
- `subjectType()` / `subjectId()` / `subjectLabel()`
- 型付きの入力値取得と共通validator

`McpFunctionResult` はテキスト、`structuredContent`、画像などのMCP contentを共通形式へ変換できるものとする。

`McpActionResult` は結果の共通形式として再利用できるが、`McpActionInterface` とApp ActionはMCPの公開・実行経路に使用しない。

## 8. 関数解決と検証

### 8.1 `tools/list`

1. `tools/list` は常に固定2 Toolだけを返す。`mcp_functions` の内容によって外部Tool数は変化しない。
2. `function_list` は `mcp_functions` を `sort` 順で取得する。
3. `enabled=1` の関数だけを対象にする。
4. `function_name` から `mcp_<function_name>` を組み立てる。
5. 対応するクラスファイルを読み込む。
6. `McpFunctionInterface` 実装を確認する。
7. クラスから `inputSchema` と `outputSchema` を取得する。
8. レジストリの説明、scope、安全性情報と合わせて関数記述子を返す。

準備不良の関数を黙って公開しない。管理画面では具体的な不備を表示し、MCP側では利用可能な関数だけを返す。

### 8.2 `tools/call`

1. `function_call` の `function_name` と完全一致する有効な関数レコードを取得する。
2. OAuth tokenとsubjectを検証する。
3. 関数に必要なscopeを検証する。
4. `requires_confirmation=1` の場合は `confirm=true` を要求する。
5. `mcp_<function_name>` クラスを解決する。
6. 入力Schemaに加えてPHP側で実値を検証する。
7. `execute()` を呼び出す。
8. 結果またはTool実行エラーをMCP形式で返す。
9. subject、関数名、引数、結果状態を呼び出しログへ残す。

クライアントからクラス名、PHPメソッド名、所有者IDを受け取らない。

## 9. 管理画面

`mcp_manage` は次の2領域へ整理する。

### 9.1 MCP基本設定

- Serverの有効状態
- タイトル・説明
- 認証方式
- Subject Provider
- OAuth URLとendpoint URL
- OAuth連携と失効

「Server追加」「Server削除」「Serverキー」「Server選択」は廃止する。

### 9.2 MCP関数管理

- 関数の追加・編集・削除
- 公開状態
- タイトル・説明
- scope
- 安全性設定
- 並び順
- 規則から生成したPHPクラス名の読み取り専用表示
- クラス存在、interface、Schemaのreadyチェック
- 呼び出しログ

関数名変更は外部APIの変更になるため、既存関数の安易な名称変更を避ける。名称変更が必要な場合は、新関数の追加と旧関数の無効化・廃止期間を使う。

## 10. 認証・認可

- MCP Serverの認証方式とSubject Providerはプロジェクト単位で1つとする。
- `fbp_user` とcustom subjectの基本モデルは維持する。
- custom subjectを使うアプリでは、通常公開ログインとMCPログインのセッションを分離する。
- 関数ごとに `required_scope` を設定する。
- データ所有者は `McpSubject` からサーバー側で解決し、クライアント指定値を信用しない。
- 破壊的操作は `destructive=1` と `requires_confirmation=1` を原則セットにする。
- 関数のannotationsは安全性判断の補助であり、サーバー側の認可・確認処理を省略しない。

## 11. app-soshikikaikaku タスク管理の再作成

既存タスク管理MCPは、新しい関数方式で再作成する。初期候補は次とする。

```text
project_list
project_get
task_list
task_get
task_create
task_update
task_comment_list
task_comment_get
task_comment_create
```

初期版では削除関数を公開しない。

汎用Note CRUDをそのまま公開するのではなく、タスク管理の業務規則を適用できる専用関数クラスを優先する。少なくとも次の既存動作を維持する。

- タスク追加時の対象プロジェクト検証
- タスク種別・ステータス・日時・権限項目の検証
- 長文詳細のコメントへの分割保存
- 複数画像添付
- post actionの実行
- プロジェクト未完了件数の再計算
- MCP経由のコメント追加時に必要なタスク状態更新
- APIへ返してよい項目だけを明示した出力

## 12. 既存データからの移行

既存アプリの移行では、旧 `mcp_tools` とApp Actionをすべて決定的な `mcp_<function_name>` クラスと `mcp_functions` の関数レコードへ置き換える。旧Toolは公開・実行しない。

OAuth tokenのresourceと旧Server設定の関係が変わるため、既存tokenの自動付け替えは前提にしない。安全性を優先し、再認証を基本案とする。

開発・テスト環境にあるタスク管理以外の検証用Serverやアプリ機能は、単一MCPへの統合対象、別プロジェクトへの分離対象、削除対象のいずれかを実装前に分類する。

## 13. 互換性と実装上の注意

- 現行MCPクライアントが利用する `tools/list` / `tools/call` の外部仕様を維持する。
- Tool名は外部API契約として扱う。
- `structuredContent` とcontentの互換返却を維持する。
- 画像結果、Tool Error、OAuth challenge、subjectログの既存責務を失わない。
- `tools/list` のページング対応要否を関数数の上限と合わせて決定する。
- 関数設定を実行中に変更可能にする場合は、`listChanged` notification対応または再接続方針を決める。
- 現行実装の固定protocol versionは、単一Server化とは分けて対応範囲を決定する。

## 14. 検証項目

### 14.1 単体・CLI検証

- 関数名の正常・異常パターン
- クラス名の自動生成
- クラスファイルなし
- interface未実装
- 入出力Schema取得
- 関数名重複
- 無効関数が一覧・実行対象にならないこと
- scope不足
- `confirm=true` 不足
- Tool実行成功・業務エラー・予期しない例外
- subjectと呼び出しログの一致

### 14.2 MCP・OAuth検証

- `initialize`
- `tools/list`
- `tools/call`
- 未認証時のOAuth challenge
- OAuth authorize、token、refresh、revoke
- `fbp_user` subject
- custom subject
- endpointとOAuth resourceが単一URLで一致すること

### 14.3 移行検証

- 既存タスク管理9 Toolと新関数の機能対応
- 旧URL互換期間中の呼び出し
- 新URLでの再認証
- 旧tokenを誤ったsubjectや関数へ流用しないこと
- 旧呼び出しログを保持したまま新ログを記録できること

## 15. Skillへの反映

実装仕様が確定した段階で、`fbp-mcp-server` Skillを本設計へ更新する。

Skillには最低限、次を記載する。

- 1プロジェクト1 MCP Server
- MCP標準 `tools/list` / `tools/call` の利用
- `mcp_functions` 登録手順
- 関数名 `^[a-z][a-z0-9_]*$`
- PHPクラス名 `mcp_<function_name>`
- クラス配置規則
- `McpFunctionInterface` 実装規則
- Schemaと実行時validatorの併用
- subject・scope・所有者チェック
- CLI、OAuth、ログの検証手順
- 旧 `McpActionInterface` と複数Server設定の移行・互換方針

命名規則はSkillの説明だけに依存せず、フレームワークの登録時と実行時にも検証・強制する。

## 16. 移行完了後の旧仕様・Skill整理

移行後は、互換目的で残した過去の仕様、実装、Skill、サンプル、docsを棚卸しし、新設計を唯一の正本にする。新旧仕様が併記されたままになり、後続実装が旧方式を選択できる状態を移行完了とはしない。

### 16.1 整理対象

- 複数MCP Server、`server_key`、Server別endpointを前提とする説明と実装手順
- `mcp_server_config` の複数行管理、Server追加・削除・選択の管理UI
- `mcp_tools.server_id` など、Server別Tool登録を前提とするデータ項目と処理
- 任意の `action_class` を登録する旧App Action命名・解決方法
- `McpActionInterface` をMCP実行に使う古い実装例
- 旧endpoint URLを掲載するdocs、画面文言、サンプル、テストデータ
- 複数Server対応や旧Tool登録を前提とするCLI・ローカル補助Skill・スクリプト
- app-soshikikaikakuに残る旧タスク管理MCPクラスと旧登録データ
- 開発検証用に追加された旧Server、OAuth token、auth code、呼び出しログの扱い

### 16.2 Skill整理

`fbp-mcp-server` Skillは、新設計の実装・検証手順へ置き換える。移行期間だけ必要な旧方式は、通常手順へ混在させず、明確な期限と削除条件を持つ「移行時のみ」の節へ分離する。

関連SkillやローカルSkillが旧MCP登録方式を参照している場合も同時に確認する。特にMCP Tool登録、App Action、custom subject、MCP専用ログイン、CLI登録例の用語と入口を揃える。

Skill更新後は、少なくとも次を確認する。

- 新規MCP機能の依頼で、複数Server作成を案内しないこと。
- 関数名から `mcp_<function_name>` を導出し、クラス名の手入力を案内しないこと。
- `mcp_functions` と `McpFunctionInterface` を正本として案内すること。
- 旧 `server_key` 付きURLを新規公開URLとして生成しないこと。
- 旧方式が必要な移行作業と、新設計による通常実装を混同しないこと。

### 16.3 docs・サンプル整理

プロジェクトdocsは履歴として必要な事実だけを残し、現在の実装方法として旧方式を案内する記述を修正する。過去の経緯を残す場合は「旧仕様」「移行済み」を明記する。

共通サンプルとapp-soshikikaikakuの実装例は、新しい関数命名、クラス配置、interface、単一endpointを使う形へ更新する。新規開発者が旧サンプルをコピーして複数Server構成を再導入しない状態にする。

### 16.4 互換コードの削除条件

次を確認した後、旧 `server` / `server_key` ルーティング、旧レジストリ読込、旧interface Adapterなどの互換コードを削除する。

- 対象プロジェクトの新関数移行が完了している。
- 新endpointでOAuth再認証が完了している。
- 旧endpointへの有効な接続・呼び出しがない。
- 旧登録データをバックアップまたは移行済みである。
- framework、app、CLI、Skill、docs、サンプルが新設計を参照している。
- 回帰検証で旧方式への依存が見つからない。

旧仕様・Skill・互換コードの整理完了を、本再設計の最終完了条件に含める。

## 17. 実装時の決定

- `mcp_server_config` は単一設定として再利用し、管理UIとruntimeから複数Serverを選択できないようにした。
- 新規レジストリ `mcp_functions` を追加し、`server_id` とPHPクラス名は持たせない。
- 新規実装は `McpFunctionInterface` と `McpFunctionResult` を使う。旧 `McpActionResult` は移行Adapterの戻り値として許容する。
- `tools/list` は全アプリで固定の `function_list` / `function_call` だけを公開し、旧 `mcp_tools` は読まない。
- 正規URLから `server` / `server_key` を除外した。旧URL・旧CLI・旧データは全アプリ移行完了まで互換として保持する。
- `app-soshikikaikaku` のタスク管理は9関数へ移行済み。既存OAuth token、旧Server・Toolデータの物理削除は、接続切替確認後の整理工程で行う。
- MCP protocol versionの更新は本変更に含めず、既存値を維持した。
