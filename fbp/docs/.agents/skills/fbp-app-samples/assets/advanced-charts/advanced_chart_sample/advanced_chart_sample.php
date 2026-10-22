<?php
require_once __DIR__ . '/linear_trend.php';

class advanced_chart_sample {
    function dashboard(Controller $ctl) {
        $ctl->assign('advanced_chart_ids', $this->draw($ctl));
        $ctl->show_dashboard_widget('charts.tpl');
    }

    function run(Controller $ctl) {
        $ctl->assign('advanced_chart_ids', $this->draw($ctl));
        $ctl->show_multi_dialog('advanced_chart_sample', 'charts.tpl', '複合グラフ・回帰線', 900);
    }

    private function draw(Controller $ctl): array {
        // Fixed fictional data. Replace with authorized, consistently spaced aggregates.
        $labels = ['1月', '2月', '3月', '4月', '5月', '6月'];
        $sales = [100, 130, 120, 160, 180, 190];
        $ids = ['mixed' => 'mixed_' . $ctl->random_alphabet(12), 'trend' => 'trend_' . $ctl->random_alphabet(12)];
        $mixed = $ctl->create_chart(); // Loads the chart interfaces/classes before subclasses below.
        $mixed->set_type('bar');
        $mixed->set_labels($labels);
        // Per-dataset yAxisID is not exposed by the base wrapper; keep this app-owned extension.
        $bar = new class extends \chartjs\Dataset_Bar_class {
            function to_array() { return array_merge(parent::to_array(), ['yAxisID' => 'sales']); }
        };
        $bar->set_label('売上（万円）');
        $bar->set_data($sales);
        $bar->set_backgroundColor('#149c98');
        $bar->set_borderColor('#149c98');
        $mixed->add_dataset($bar);
        $line = new class extends \chartjs\Dataset_Line_class {
            function to_array() { return array_merge(parent::to_array(), ['yAxisID' => 'count', 'tension' => 0]); }
        };
        $line->set_label('受注件数');
        $line->set_data([8, 11, 9, 14, 15, 16]);
        $line->set_borderColor('#ae5dc4');
        $line->set_fill(false);
        $mixed->add_dataset($line);
        $left = $mixed->create_Scale('sales', 'linear', 'left');
        $left->set_title('売上（万円）');
        $left->set_min(0);
        $right = $mixed->create_Scale('count', 'linear', 'right');
        $right->set_title('受注件数');
        $right->set_min(0);
        $right->add_option('grid', ['drawOnChartArea' => false]);
        $mixed->add_option_scales('sales', $left);
        $mixed->add_option_scales('count', $right);
        $mixed->add_option('maintainAspectRatio', false);
        $ctl->chart_draw($ids['mixed'], $mixed);

        $trend = $ctl->create_chart();
        $trend->set_type('bar');
        $trend->set_labels(array_merge($labels, ['7月（延長）', '8月（延長）']));
        $actual = $trend->create_Dataset_Bar();
        $actual->set_label('実績（万円）');
        $actual->set_data(array_merge($sales, [null, null])); // Future slots are not observations.
        $actual->set_backgroundColor('#149c98');
        $actual->set_borderColor('#149c98');
        $trend->add_dataset($actual);
        $fit = $trend->create_Dataset_Line();
        $fit->set_label('最小二乗の回帰線（末尾2点は延長）');
        $fit->set_data(sample_linear_trend::fit($sales, 2));
        $fit->set_borderColor('#d46336');
        $fit->set_borderDash([6, 4]);
        $fit->set_fill(false);
        $trend->add_dataset($fit);
        $y = $trend->create_Scale('y', 'linear', 'left');
        $y->set_title('売上（万円）');
        $trend->add_option_scales('y', $y);
        $trend->add_option('maintainAspectRatio', false);
        $ctl->chart_draw($ids['trend'], $trend);
        return $ids;
    }
}
