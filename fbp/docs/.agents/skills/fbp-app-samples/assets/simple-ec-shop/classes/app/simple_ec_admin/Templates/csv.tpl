<p class="sec-csv-description">受注日の開始日から終了日までの、閲覧権限のある受注と明細をCSVで出力します。</p>
<style>{literal}
.sec-csv-description{margin-bottom:16px}
#sec-order-csv .sec-csv-date-range{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px}
#sec-order-csv .sec-csv-date-range>div{min-width:0}
#sec-order-csv .sec-csv-date-range .row_value{display:block;float:none;width:100%;box-sizing:border-box}
#sec-order-csv .sec-csv-date-range input{width:100%;min-width:0;box-sizing:border-box}
{/literal}</style>
<form id="sec-order-csv" onsubmit="return false;">
    <div class="sec-csv-date-range">
        <div>
            <label>受注日（開始）</label>
            {fields_form_original name="date_start" type="date" value=$csv_today}
            <div class="error_date_start error_message"></div>
        </div>
        <div>
            <label>受注日（終了）</label>
            {fields_form_original name="date_end" type="date" value=$csv_today}
            <div class="error_date_end error_message"></div>
        </div>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:20px">
        <button type="button" class="download-link" data-class="simple_ec_admin" data-function="csv" data-form="sec-order-csv" data-filename="受注データ.csv">受注CSVをダウンロード</button>
    </div>
</form>
