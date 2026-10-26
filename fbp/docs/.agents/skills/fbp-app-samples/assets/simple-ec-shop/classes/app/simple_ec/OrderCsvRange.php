<?php
final class SimpleEcOrderCsvRange {
    public static function day($value,DateTimeZone $zone): string {
        if(!is_string($value)&&!is_int($value))throw new InvalidArgumentException('受注日を正しく指定してください。');
        $value=trim((string)$value);
        if(preg_match('/^[0-9]{1,11}$/D',$value) && (int)$value>0){
            return (new DateTimeImmutable('@'.$value))->setTimezone($zone)->format('Y-m-d');
        }
        if(preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/D',$value,$m) && checkdate((int)$m[2],(int)$m[3],(int)$m[1])){
            return sprintf('%04d-%02d-%02d',(int)$m[1],(int)$m[2],(int)$m[3]);
        }
        throw new InvalidArgumentException('受注日を正しく指定してください。');
    }
    public static function bounds($start,$end,DateTimeZone $zone): array {
        if($start===null||$start===''||$end===null||$end==='')throw new InvalidArgumentException('開始日と終了日を指定してください。');
        $range=[self::day($start,$zone),self::day($end,$zone)];
        if($range[0]>$range[1])throw new InvalidArgumentException('終了日は開始日以降を指定してください。');
        return $range;
    }
    public static function contains($date,array $range,DateTimeZone $zone): bool {
        try{$date=self::day($date,$zone);}catch(InvalidArgumentException $e){return false;}
        return $range[0]<=$date && $date<=$range[1];
    }
}
