<?php
// Equal-spaced observations only. Missing values must not silently become zero.
final class sample_linear_trend {
    static function fit(array $values, int $future = 0): array {
        if ($future < 0 || $future > 120) throw new InvalidArgumentException('future must be 0..120');
        $values = array_values($values);
        foreach ($values as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                throw new InvalidArgumentException('Use finite numeric observations without gaps');
            }
        }
        $n = count($values);
        if ($n === 0) return [];
        if ($n < 2) throw new InvalidArgumentException('At least two observations are required');
        $meanX = ($n - 1) / 2;
        $meanY = array_sum($values) / $n;
        $covariance = $variance = 0.0;
        foreach ($values as $i => $value) {
            $covariance += ($i - $meanX) * ($value - $meanY);
            $variance += ($i - $meanX) ** 2;
        }
        $slope = $covariance / $variance;
        $result = [];
        for ($i = 0; $i < $n + $future; $i++) {
            $value = $meanY + $slope * ($i - $meanX);
            if (!is_finite($value)) throw new OverflowException('Trend is outside numeric range');
            $result[] = $value;
        }
        return $result;
    }
}
