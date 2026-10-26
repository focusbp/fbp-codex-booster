<?php
require_once __DIR__."/../simple_ec/Visibility.php";
class sec_products_visibility_filter extends SimpleEcVisibility {
    public function get_filter_conditions(Controller $ctl,string $context): array {
        return array_merge(parent::get_filter_conditions($ctl,$context),[['field'=>'payment_available','value'=>1,'match_pattern'=>'=']]);
    }
}
