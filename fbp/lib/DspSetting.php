<?php

// Existing settings and invalid values keep DSP enabled. Only explicit OFF disables it.
function fbp_normalize_dsp_disabled(mixed $value): int {
    return in_array($value, [1, '1'], true) ? 1 : 0;
}
