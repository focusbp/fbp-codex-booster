<?php
require_once __DIR__.'/../feature_excel_ocr/feature_excel_ocr.php';
/** Only the note binding is fixed; fields always come from that note's definitions. */
class feature_products_ocr extends feature_excel_ocr {
    protected const TABLE='feature_sample_products';
}
