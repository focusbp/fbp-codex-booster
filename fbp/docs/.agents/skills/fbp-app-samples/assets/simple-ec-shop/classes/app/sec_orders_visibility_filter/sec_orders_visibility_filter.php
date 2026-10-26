<?php
require_once __DIR__."/../simple_ec/Visibility.php";
class sec_orders_visibility_filter extends SimpleEcVisibility {
    public function get_filter_conditions(Controller $ctl,string $context): array {
        return array_merge(parent::get_filter_conditions($ctl,$context),[['field'=>'status','value'=>0,'match_pattern'=>'>='],['field'=>'status','value'=>4,'match_pattern'=>'<=']]);
    }
}
