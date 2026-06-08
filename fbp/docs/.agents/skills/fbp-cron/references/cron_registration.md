# Cron Registration Format

`fbp/app/cron/cron.php` と `fbp/app/cron/fmt/cron.fmt` を基準にした、`cron` 登録形式の実仕様。

## 1. 登録項目

`cron` テーブルの主な項目は次の通り。

- `title`
  - 定期処理の表示名
- `class_name`
  - 実行するクラス名
- `function_name`
  - 実行する関数名
- `min`
  - 分の配列
- `hour`
  - 時の配列
- `day`
  - 日の配列
- `month`
  - 月の配列
- `weekday`
  - 曜日の配列

定義は `fbp/app/cron/fmt/cron.fmt` を参照。

## 2. 実行の仕組み

`cron::exec()` は次の流れで実行する。

1. `id` を復号
2. `cron` レコードを取得
3. `class_name` と `function_name` を読む
4. `getClassObject()` でクラスを生成
5. `$obj->$function_name($ctl)` を実行

参照:
- `fbp/app/cron/cron.php`

つまり、Cron登録で重要なのは `class_name` と `function_name` であり、時刻指定は `min/hour/day/month/weekday` に分かれて保存される。

## 3. 時刻指定の意味

各項目は「選択された値の配列」で保持される。

- `min`
  - UI上の候補は `0,10,20,30,40,50`
- `hour`
  - `0-23`
- `day`
  - `1-31`
- `month`
  - `1-12`
- `weekday`
  - `0:Sun, 1:Mon, 2:Tue, 3:Wed, 4:Thu, 5:Fri, 6:Sat`

空配列はワイルドカード相当として扱う。

例:
- `hour=["9"]`, 他は空配列
  - 毎日 9時台
- `min=["0"]`, `hour=["8"]`, `weekday=["1"]`
  - 毎週月曜 8:00
- `min=["0"]`, `hour=["6"]`, `day=["1"]`
  - 毎月1日 6:00

## 4. 具体例

### 毎日 9:00 に実行

```json
{
  "title": "売上集計送信",
  "class_name": "cron_sales_report",
  "function_name": "exec",
  "min": ["0"],
  "hour": ["9"],
  "day": [],
  "month": [],
  "weekday": []
}
```

### 毎週月曜 8:00 に実行

```json
{
  "title": "未入金通知",
  "class_name": "cron_unpaid_notice",
  "function_name": "exec",
  "min": ["0"],
  "hour": ["8"],
  "day": [],
  "month": [],
  "weekday": ["1"]
}
```

### 毎月1日 6:00 に実行

```json
{
  "title": "月次集計",
  "class_name": "cron_monthly_report",
  "function_name": "exec",
  "min": ["0"],
  "hour": ["6"],
  "day": ["1"],
  "month": [],
  "weekday": []
}
```

## 5. 実装時の注意

- `cron_expression` 形式ではなく、必ず `min/hour/day/month/weekday` に分解して扱う。
- `class_name` と `function_name` は、実際に存在するクラス/関数名に合わせる。
- 追加・変更後は `cron_set()` が必要。
- 既存確認は `cron_list` を使う。
- 検証では、対象の class/function を手動実行相当で確認する。
